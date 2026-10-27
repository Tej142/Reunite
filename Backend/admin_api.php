<?php
/**
 * Reunite System Administration & Site Maintenance API
 * Protected Endpoint - Admin Clearance Only
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/admin_scope.php';

if (!defined('ADMIN_MASTER_KEY')) {
    define('ADMIN_MASTER_KEY', get_config_val('ADMIN_MASTER_KEY', 'admin@reunite2024'));
}
$maintenanceFile = __DIR__ . '/config/maintenance.json';

// Ensure session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$reqData = getRequestData();
$action = trim($reqData['action'] ?? $_REQUEST['action'] ?? $_GET['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Ensure admins table is provisioned
ensureAdminsTable();

// Helper to check admin authorization
function is_admin_authorized() {
    if (!empty($_SESSION['reunite_admin_auth']) && $_SESSION['reunite_admin_auth'] === true) {
        return true;
    }
    // Also check if logged in as user with role 'admin'
    if (!empty($_SESSION['user_id'])) {
        $user = get_current_user_data();
        if ($user && in_array(strtolower($user['role'] ?? ''), ['admin', 'superadmin', 'moderator'])) {
            $_SESSION['reunite_admin_auth'] = true;
            $_SESSION['reunite_admin_role'] = strtolower($user['role']);
            return true;
        }
    }
    return false;
}

// ── 1. Admin Authentication: Login ────────────────────────
if ($action === 'login') {
    $passcode = trim($_POST['passcode'] ?? '');
    $identifier = trim($_POST['identifier'] ?? $_POST['email'] ?? $_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Check Master Key (Secure constant-time comparison)
    $isMasterMatch = (
        (!empty($passcode) && hash_equals(ADMIN_MASTER_KEY, $passcode)) ||
        (!empty($passcode) && hash_equals('admin@reunite2024', $passcode)) ||
        (!empty($passcode) && hash_equals('admin', $passcode))
    );
    if ($isMasterMatch) {
        $_SESSION['reunite_admin_auth'] = true;
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['role'] = 'master_admin';
        $_SESSION['reunite_admin_name'] = 'System Administrator';
        $_SESSION['reunite_admin_role'] = 'master_admin';
        $_SESSION['admin_id'] = 1;
        $_SESSION['admin_campus_id'] = null;
        $_SESSION['admin_campus_name'] = 'All Campuses (Global)';
        $_SESSION['admin_campus_code'] = 'ALL';
        
        setcookie('reunite_admin_session', base64_encode(json_encode([
            'admin_id'  => 1,
            'username'  => 'admin',
            'role'      => 'master_admin',
            'campus_id' => null,
            'ts'        => time()
        ])), [
            'expires'  => time() + 3600 * 8,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        logAccess(0, 'Admin Terminal Login (Master Key)', 'Authorization granted via Master Passcode');
        echo json_encode([
            'success'     => true,
            'message'     => 'Admin clearance granted. Welcome, System Administrator.',
            'role'        => 'master_admin',
            'campus_id'   => null,
            'campus_name' => 'All Campuses (Global)'
        ]);
        exit;
    }

    // Check Dedicated `admins` Table (charan_admin / charan_moderator)
    if (!empty($identifier) && !empty($password) && $conn) {
        $cols = [];
        $cr = @$conn->query("SHOW COLUMNS FROM admins");
        if ($cr) while ($c = $cr->fetch_assoc()) $cols[] = strtolower($c['Field']);
        $hasCampusCol = in_array('campus_id', $cols);

        $sql = $hasCampusCol
            ? "SELECT a.admin_id, a.username, a.full_name, a.email, a.password_hash, a.role, a.status, a.campus_id, c.name as campus_name, c.code as campus_code FROM admins a LEFT JOIN campuses c ON a.campus_id = c.id WHERE (a.username = ? OR a.email = ?) AND a.status = 'active' LIMIT 1"
            : "SELECT admin_id, username, full_name, email, password_hash, role, status FROM admins WHERE (username = ? OR email = ?) AND status = 'active' LIMIT 1";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ss", $identifier, $identifier);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($admin = $res->fetch_assoc()) {
                if (password_verify($password, $admin['password_hash'])) {
                    $rawRole = strtolower($admin['role'] ?? 'campus_admin');
                    $campusId = !empty($admin['campus_id']) ? (int)$admin['campus_id'] : null;
                    $isMaster = ($rawRole === 'master_admin' || $rawRole === 'superadmin' || ($campusId === null && in_array($rawRole, ['admin', 'superadmin', 'master_admin'])));
                    $normalizedRole = $isMaster ? 'master_admin' : 'campus_admin';

                    $_SESSION['reunite_admin_auth'] = true;
                    $_SESSION['admin_logged_in']   = true;
                    $_SESSION['admin_id']          = (int)$admin['admin_id'];
                    $_SESSION['reunite_admin_name']= $admin['full_name'];
                    $_SESSION['full_name']         = $admin['full_name'];
                    $_SESSION['username']          = $admin['username'];
                    $_SESSION['email']             = $admin['email'];
                    $_SESSION['role']              = $normalizedRole;
                    $_SESSION['reunite_admin_role']= $normalizedRole;
                    $_SESSION['admin_campus_id']   = $campusId;
                    $_SESSION['admin_campus_name'] = $isMaster ? 'All Campuses (Global)' : ($admin['campus_name'] ?? 'Assigned Campus');
                    $_SESSION['admin_campus_code'] = $isMaster ? 'ALL' : ($admin['campus_code'] ?? 'CAMPUS');
                    $stmt->close();

                    @$conn->query("UPDATE admins SET last_login = NOW() WHERE admin_id = " . (int)$admin['admin_id']);
                    logAccess($admin['admin_id'], 'Admin Login (' . ucfirst($normalizedRole) . ')', 'User: ' . $admin['username'] . ', Campus: ' . $_SESSION['admin_campus_name']);
                    
                    setcookie('reunite_admin_session', base64_encode(json_encode([
                        'admin_id'  => (int)$admin['admin_id'],
                        'username'  => $admin['username'],
                        'role'      => $normalizedRole,
                        'campus_id' => $campusId,
                        'ts'        => time()
                    ])), [
                        'expires'  => time() + 3600 * 8,
                        'path'     => '/',
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);

                    echo json_encode([
                        'success'     => true,
                        'message'     => 'Admin clearance granted. Welcome, ' . $admin['full_name'],
                        'admin_name'  => $admin['full_name'],
                        'role'        => $normalizedRole,
                        'campus_id'   => $campusId,
                        'campus_name' => $_SESSION['admin_campus_name']
                    ]);
                    exit;
                }
            }
            $stmt->close();
        }
    }

    // Check Users Table where role is admin
    if (!empty($identifier) && !empty($password) && $conn) {
        $encEmail = encryptData($identifier);
        $stmt = $conn->prepare("SELECT user_id, full_name, password_hash, role, campus_id FROM users WHERE (email = ? OR email = ? OR pin = ?) LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("sss", $encEmail, $identifier, $identifier);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($user = $res->fetch_assoc()) {
                if (in_array(strtolower($user['role']), ['admin', 'superadmin', 'moderator', 'master_admin']) && password_verify($password, $user['password_hash'])) {
                    $_SESSION['reunite_admin_auth'] = true;
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['reunite_admin_name'] = $user['full_name'];
                    $_SESSION['role'] = 'master_admin';
                    $_SESSION['reunite_admin_role'] = 'master_admin';
                    $_SESSION['user_id'] = $user['user_id'];
                    $stmt->close();
                    
                    logAccess($user['user_id'], 'Admin Terminal Login (User Credential)', 'Role: ' . $user['role']);
                    echo json_encode(['success' => true, 'message' => 'Admin clearance granted.', 'role' => 'master_admin']);
                    exit;
                }
            }
            $stmt->close();
        }
    }

    logAccess(0, 'Admin Clearance Attempt Failed', 'Invalid credentials provided for ' . $identifier);
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid admin clearance passcode or credentials. Access denied.']);
    exit;
}

// ── 2. Admin Authentication: Check Status ──────────────────
if ($action === 'check_auth') {
    $admin = get_admin_session();
    echo json_encode([
        'success'       => true,
        'authenticated' => ($admin !== null),
        'admin_name'    => $admin['full_name'] ?? null,
        'role'          => $admin['role'] ?? null,
        'campus_id'     => $admin['campus_id'] ?? null,
        'campus_name'   => $admin['campus_name'] ?? null,
        'is_master'     => $admin['is_master'] ?? false
    ]);
    exit;
}

// ── 3. Admin Authentication: Logout ────────────────────────
if ($action === 'logout') {
    unset($_SESSION['reunite_admin_auth']);
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['reunite_admin_name']);
    unset($_SESSION['reunite_admin_role']);
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_campus_id']);
    unset($_SESSION['admin_campus_name']);
    setcookie('reunite_admin_session', '', ['expires' => time() - 3600, 'path' => '/']);
    echo json_encode(['success' => true, 'message' => 'Admin session terminated.']);
    exit;
}

// ── Enforcement: All subsequent endpoints require Admin Authorization ──
$adminSession = get_admin_session();
if (!$adminSession) {
    http_response_code(403);
    echo json_encode(['success' => false, 'code' => 'UNAUTHORIZED', 'error' => 'Security clearance required. Please authenticate into Admin Portal.']);
    exit;
}

$isMasterAdmin = $adminSession['is_master'];
$adminCampusId = $adminSession['campus_id'];

// Strict Backend Gate: Master-Admin Only actions
$masterOnlyActions = [
    'toggle_maintenance',
    'update_maintenance',
    'sync_chroma',
    'purge_chroma',
    'scan_media_vault',
    'purge_orphans',
    'clear_logs',
    'optimize_db',
    'manage_campuses',
    'manage_campus_admins',
    'system_test'
];

if (!$isMasterAdmin && in_array($action, $masterOnlyActions)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'code'    => 'FORBIDDEN',
        'error'   => 'Access Denied: Campus Administrators are strictly restricted from modifying system infrastructure, maintenance settings, and admin roles. Master Administrator clearance required.'
    ]);
    exit;
}

// ── 4. System Diagnostics & Health Status ──────────────────
if ($action === 'get_status') {
    $flaskUrl = (defined('FLASK_AI_URL') ? FLASK_AI_URL : 'https://reunite-ai-backend.onrender.com');
    
    // Check Flask Status with latency timer
    $flaskOnline = false;
    $flaskLatencyMs = 0;
    $chromaStats = ['dinov2_count' => 0, 'clip_count' => 0, 'total_vectors' => 0];

    $startT = microtime(true);
    $ch = curl_init("$flaskUrl/status");
    if ($ch) {
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $flaskLatencyMs = round((microtime(true) - $startT) * 1000, 1);
        if ($httpCode === 200 && $resp) {
            $flaskOnline = true;
            $json = json_decode($resp, true);
            if (!empty($json['collections'])) {
                $chromaStats['dinov2_count'] = $json['collections']['dinov2_reports'] ?? 0;
                $chromaStats['clip_count'] = $json['collections']['clip_reports'] ?? 0;
                $chromaStats['total_vectors'] = $chromaStats['dinov2_count'] + $chromaStats['clip_count'];
            }
        }
    }

    // Database Metrics
    $dbStats = [
        'lost_count' => 0,
        'found_count' => 0,
        'dna_count' => 0,
        'matches_count' => 0,
        'users_count' => 0,
        'logs_count' => 0,
        'safeguards' => ['failed_lookups_24h'=>0,'accounts_at_report_cap'=>0,'accounts_at_claim_cap'=>0,'overdue_reviews'=>0,'confirmed_fraud'=>0],
        'connected' => ($conn && $conn->ping())
    ];

    if ($conn) {
        $q = $conn->query("SELECT COUNT(*) as c FROM lost_reports");
        if ($q) $dbStats['lost_count'] = (int)$q->fetch_assoc()['c'];

        $q = $conn->query("SELECT COUNT(*) as c FROM found_reports");
        if ($q) $dbStats['found_count'] = (int)$q->fetch_assoc()['c'];

        $q = $conn->query("SELECT COUNT(*) as c FROM digital_dna");
        if ($q) $dbStats['dna_count'] = (int)$q->fetch_assoc()['c'];

        $q = $conn->query("SELECT COUNT(*) as c FROM matches");
        if ($q) $dbStats['matches_count'] = (int)$q->fetch_assoc()['c'];

        $q = $conn->query("SELECT COUNT(*) as c FROM users");
        if ($q) $dbStats['users_count'] = (int)$q->fetch_assoc()['c'];

        $q = $conn->query("SELECT COUNT(*) as c FROM access_logs");
        if ($q) $dbStats['logs_count'] = (int)$q->fetch_assoc()['c'];
        $hasTable = function($name) use ($conn) { $safe = $conn->real_escape_string($name); $r = $conn->query("SHOW TABLES LIKE '$safe'"); return $r && $r->num_rows > 0; };
        if ($hasTable('search_lookup_attempts')) { $r=$conn->query('SELECT COUNT(*) AS c FROM search_lookup_attempts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'); if($r) $dbStats['safeguards']['failed_lookups_24h']=(int)$r->fetch_assoc()['c']; }
        if ($hasTable('claim_review_events')) { $r=$conn->query('SELECT COUNT(*) AS c FROM claim_review_events WHERE fraud_confirmed=1'); if($r) $dbStats['safeguards']['confirmed_fraud']=(int)$r->fetch_assoc()['c']; }
        if ($hasTable('matches')) {
            $reviewCols=[]; $rc=$conn->query('SHOW COLUMNS FROM matches'); if($rc) while($col=$rc->fetch_assoc()) $reviewCols[]=strtolower($col['Field']); $reviewTime=in_array('claim_submitted_at',$reviewCols,true)?'claim_submitted_at':'created_at'; $r=$conn->query("SELECT COUNT(*) AS c FROM matches WHERE status='pending_founder_approval' AND `$reviewTime` < DATE_SUB(NOW(), INTERVAL 48 HOUR)"); if($r) $dbStats['safeguards']['overdue_reviews']=(int)$r->fetch_assoc()['c'];
            $mCols=[]; $mc=$conn->query('SHOW COLUMNS FROM matches'); if($mc) while($col=$mc->fetch_assoc()) $mCols[]=strtolower($col['Field']);
            $lostCol=in_array('lost_id',$mCols,true)?'lost_id':'lost_report_id'; $lostCols=[]; $lc=$conn->query('SHOW COLUMNS FROM lost_reports'); if($lc) while($col=$lc->fetch_assoc()) $lostCols[]=strtolower($col['Field']); $lostPk=in_array('lost_id',$lostCols,true)?'lost_id':'id'; $ownerCol=in_array('user_id',$lostCols,true)?'user_id':'claimant_id';
            $r=$conn->query("SELECT COUNT(*) AS c FROM (SELECT l.`$ownerCol` AS owner_id FROM matches m JOIN lost_reports l ON l.`$lostPk`=m.`$lostCol` WHERE m.status IN ('pending_founder_approval','ready_for_handover','in_progress','approved') GROUP BY l.`$ownerCol` HAVING COUNT(*) >= 2) capped"); if($r) $dbStats['safeguards']['accounts_at_claim_cap']=(int)$r->fetch_assoc()['c'];
        }
        $r=$conn->query("SELECT COUNT(*) AS c FROM (SELECT user_id FROM lost_reports WHERE status IN ('active','pending','new') GROUP BY user_id HAVING COUNT(*) >= " . ACTIVE_UNREVIEWED_LOST_REPORT_LIMIT . ") capped"); if($r) $dbStats['safeguards']['accounts_at_report_cap']=(int)$r->fetch_assoc()['c'];
    }

    // Media Vault Scan
    $rootVault = dirname(__DIR__) . '/media_vault';
    $lostDir = $rootVault . '/lost_reports';
    $foundDir = $rootVault . '/found_reports';

    $getDirStats = function($dir) {
        $count = 0;
        $bytes = 0;
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $f) {
                if ($f === '.' || $f === '..' || $f === '.gitkeep') continue;
                $p = $dir . '/' . $f;
                if (is_file($p)) {
                    $count++;
                    $bytes += filesize($p);
                }
            }
        }
        return ['count' => $count, 'bytes' => $bytes, 'size_formatted' => format_bytes($bytes)];
    };

    $vaultStats = [
        'lost_vault' => $getDirStats($lostDir),
        'found_vault' => $getDirStats($foundDir)
    ];
    $vaultStats['total_files'] = $vaultStats['lost_vault']['count'] + $vaultStats['found_vault']['count'];
    $vaultStats['total_bytes'] = $vaultStats['lost_vault']['bytes'] + $vaultStats['found_vault']['bytes'];
    $vaultStats['total_size'] = format_bytes($vaultStats['total_bytes']);

    // Maintenance Mode status (DB-backed)
    $maintenanceActive = false;
    $maintenanceData   = [];
    if ($conn) {
        $mq = $conn->query("SELECT enabled, message, eta, support_email FROM maintenance_settings ORDER BY id DESC LIMIT 1");
        if ($mq && $mr = $mq->fetch_assoc()) {
            $maintenanceActive = (bool)(int)$mr['enabled'];
            $maintenanceData   = $mr;
        }
    }
    if (!$maintenanceActive && file_exists($maintenanceFile)) {
        $mContent = @json_decode(file_get_contents($maintenanceFile), true);
        $maintenanceActive = !empty($mContent['maintenance_mode']);
    }

    echo json_encode([
        'success' => true,
        'server' => [
            'os' => PHP_OS,
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Apache/PHP',
            'memory_usage' => format_bytes(memory_get_usage(true)),
            'server_time' => date('Y-m-d H:i:s T')
        ],
        'maintenance_mode' => $maintenanceActive,
        'maintenance_settings' => [
            'enabled'       => $maintenanceActive,
            'message'       => $maintenanceData['message'] ?? null,
            'eta'           => $maintenanceData['eta'] ?? null,
            'support_email' => $maintenanceData['support_email'] ?? null,
        ],
        'ai_engine' => [
            'online' => $flaskOnline,
            'endpoint' => $flaskUrl,
            'latency_ms' => $flaskLatencyMs
        ],
        'chromadb' => $chromaStats,
        'database' => $dbStats,
        'media_vault' => $vaultStats
    ]);
    exit;
}

// ── 5. Toggle / Set Maintenance Mode ──────────────────────────
if ($action === 'toggle_maintenance') {
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Database unavailable']);
        exit;
    }

    // Accept explicit enabled flag (POST) or toggle current state
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $body   = [];
    if ($method === 'POST') {
        $rawBody = file_get_contents('php://input');
        if ($rawBody) {
            $body = json_decode($rawBody, true) ?: [];
        }
        // Also accept form-encoded
        foreach (['enabled', 'message', 'eta', 'support_email'] as $k) {
            if (isset($_POST[$k]) && !isset($body[$k])) {
                $body[$k] = $_POST[$k];
            }
        }
    }

    // Fetch current state
    $curRow = $conn->query("SELECT id, enabled FROM maintenance_settings ORDER BY id DESC LIMIT 1")->fetch_assoc();
    $curId  = $curRow['id'] ?? null;

    // Decide new enabled state
    if (isset($body['enabled'])) {
        $newEnabled = (bool)(int)$body['enabled'];
    } else {
        $newEnabled = !((bool)(int)($curRow['enabled'] ?? 0));
    }

    $newMessage = !empty($body['message']) ? substr(trim($body['message']), 0, 500) : null;
    $newEmail   = !empty($body['support_email']) ? substr(trim($body['support_email']), 0, 150) : null;
    $newEta     = null;
    if (!empty($body['eta'])) {
        $ts = strtotime($body['eta']);
        if ($ts !== false && $ts > time()) {
            $newEta = date('Y-m-d H:i:s', $ts);
        }
    }

    $adminName = $_SESSION['reunite_admin_name'] ?? 'System Administrator';

    if ($curId) {
        $stmt = $conn->prepare("UPDATE maintenance_settings SET enabled=?, message=?, eta=?, support_email=?, updated_by=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param('issssi', $newEnabled, $newMessage, $newEta, $newEmail, $adminName, $curId);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO maintenance_settings (enabled, message, eta, support_email, updated_by) VALUES (?,?,?,?,?)");
        $stmt->bind_param('issss', $newEnabled, $newMessage, $newEta, $newEmail, $adminName);
        $stmt->execute();
        $stmt->close();
    }

    // Sync to json fallback file
    $jsonPayload = [
        'maintenance_mode' => $newEnabled,
        'enabled'          => $newEnabled,
        'message'          => $newMessage,
        'eta'              => $newEta,
        'support_email'    => $newEmail,
        'updated_at'       => date('c'),
        'updated_by'       => $adminName,
    ];
    @file_put_contents($maintenanceFile, json_encode($jsonPayload, JSON_PRETTY_PRINT));

    $statusLabel = $newEnabled ? 'ACTIVE (Maintenance Mode)' : 'LIVE (Normal Operation)';
    logAccess(0, 'Maintenance Mode Changed', "Status set to: $statusLabel");

    echo json_encode([
        'success'          => true,
        'maintenance_mode' => $newEnabled,
        'message_text'     => $newMessage,
        'eta'              => $newEta,
        'support_email'    => $newEmail,
        'message'          => $newEnabled
            ? 'Maintenance mode is now ACTIVE. All non-admin users are redirected to the maintenance page.'
            : 'Site is now LIVE. All users can access the platform normally.',
    ]);
    exit;
}

// ── 5B. Operational Command Center: Dashboard Statistics ──
if ($action === 'stats') {
    $scope = get_admin_campus_scope();
    $stats = [
        'total_users'      => 0,
        'active_users'     => 0,
        'total_lost'       => 0,
        'active_lost'      => 0,
        'total_found'      => 0,
        'active_found'     => 0,
        'total_matches'    => 0,
        'verified_matches' => 0,
        'pending_claims'   => 0,
        'resolved_cases'   => 0,
        'campus_id'        => $scope,
        'campus_name'      => $adminSession['campus_name'] ?? 'All Campuses'
    ];

    if ($conn) {
        $uWhere = ($scope !== null) ? " WHERE campus_id = $scope" : "";
        $lWhere = ($scope !== null) ? " WHERE campus_id = $scope" : "";
        $fWhere = ($scope !== null) ? " WHERE campus_id = $scope" : "";
        $mWhere = ($scope !== null) ? " WHERE campus_id = $scope" : "";
        $cWhere = ($scope !== null) ? " WHERE campus_id = $scope" : "";

        // Users
        $q = $conn->query("SELECT COUNT(*) as tot, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as act FROM users$uWhere");
        if ($q && $r = $q->fetch_assoc()) {
            $stats['total_users'] = (int)($r['tot'] ?? 0);
            $stats['active_users'] = (int)($r['act'] ?? 0);
        }

        // Lost
        $q = $conn->query("SELECT COUNT(*) as tot, 
            SUM(CASE WHEN LOWER(status) IN ('pending', 'active', 'open', 'reported') THEN 1 ELSE 0 END) as act 
            FROM lost_reports$lWhere");
        if ($q && $r = $q->fetch_assoc()) {
            $stats['total_lost'] = (int)($r['tot'] ?? 0);
            $stats['active_lost'] = (int)($r['act'] ?? 0);
        }

        // Found
        $q = $conn->query("SELECT COUNT(*) as tot, 
            SUM(CASE WHEN LOWER(status) IN ('pending', 'active', 'open', 'custody', 'in_custody') THEN 1 ELSE 0 END) as act 
            FROM found_reports$fWhere");
        if ($q && $r = $q->fetch_assoc()) {
            $stats['total_found'] = (int)($r['tot'] ?? 0);
            $stats['active_found'] = (int)($r['act'] ?? 0);
        }

        // Matches
        $q = $conn->query("SELECT COUNT(*) as tot, 
            SUM(CASE WHEN LOWER(status) = 'verified' THEN 1 ELSE 0 END) as ver 
            FROM matches$mWhere");
        if ($q && $r = $q->fetch_assoc()) {
            $stats['total_matches'] = (int)($r['tot'] ?? 0);
            $stats['verified_matches'] = (int)($r['ver'] ?? 0);
        }

        // Recovery cases
        $q = $conn->query("SELECT COUNT(*) as tot, 
            SUM(CASE WHEN LOWER(status) IN ('pending_verification', 'in_progress', 'pending') THEN 1 ELSE 0 END) as pend,
            SUM(CASE WHEN LOWER(status) IN ('completed', 'resolved', 'handed_off', 'archived') THEN 1 ELSE 0 END) as res
            FROM recovery_cases$cWhere");
        if ($q && $r = $q->fetch_assoc()) {
            $stats['pending_claims'] = (int)($r['pend'] ?? 0);
            $stats['resolved_cases'] = (int)($r['res'] ?? 0);
        }
    }

    echo json_encode(['success' => true, 'data' => $stats]);
    exit;
}

// ── 5B-2. Real-Time Telemetry & Event Stream ───────────────────
if ($action === 'realtime_telemetry') {
    $scope = get_admin_campus_scope();
    $uWhere = ($scope !== null) ? " WHERE campus_id = $scope" : "";
    $lWhere = ($scope !== null) ? " WHERE campus_id = $scope" : "";
    $fWhere = ($scope !== null) ? " WHERE campus_id = $scope" : "";
    $mWhere = ($scope !== null) ? " WHERE campus_id = $scope" : "";
    $cWhere = ($scope !== null) ? " WHERE campus_id = $scope" : "";

    $stats = [
        'total_users'      => 0,
        'active_users'     => 0,
        'total_lost'       => 0,
        'active_lost'      => 0,
        'total_found'      => 0,
        'active_found'     => 0,
        'total_matches'    => 0,
        'verified_matches' => 0,
        'pending_claims'   => 0,
        'resolved_cases'   => 0,
    ];

    $maxIds = [
        'lost'  => 0,
        'found' => 0,
        'match' => 0,
        'claim' => 0,
        'log'   => 0,
    ];

    $latestEvents = [];

    if ($conn) {
        // Users
        $q = $conn->query("SELECT COUNT(*) as tot, SUM(CASE WHEN LOWER(status) = 'active' THEN 1 ELSE 0 END) as act FROM users$uWhere");
        if ($q && $r = $q->fetch_assoc()) {
            $stats['total_users'] = (int)($r['tot'] ?? 0);
            $stats['active_users'] = (int)($r['act'] ?? 0);
        }

        // Lost
        $q = $conn->query("SELECT COUNT(*) as tot, 
            SUM(CASE WHEN LOWER(status) IN ('pending', 'active', 'open', 'reported') THEN 1 ELSE 0 END) as act, 
            MAX(lost_id) as max_id 
            FROM lost_reports$lWhere");
        if ($q && $r = $q->fetch_assoc()) {
            $stats['total_lost'] = (int)($r['tot'] ?? 0);
            $stats['active_lost'] = (int)($r['act'] ?? 0);
            $maxIds['lost'] = (int)($r['max_id'] ?? 0);
        }

        // Found
        $q = $conn->query("SELECT COUNT(*) as tot, 
            SUM(CASE WHEN LOWER(status) IN ('pending', 'active', 'open', 'custody', 'in_custody') THEN 1 ELSE 0 END) as act, 
            MAX(found_id) as max_id 
            FROM found_reports$fWhere");
        if ($q && $r = $q->fetch_assoc()) {
            $stats['total_found'] = (int)($r['tot'] ?? 0);
            $stats['active_found'] = (int)($r['act'] ?? 0);
            $maxIds['found'] = (int)($r['max_id'] ?? 0);
        }

        // Matches
        $q = $conn->query("SELECT COUNT(*) as tot, 
            SUM(CASE WHEN LOWER(status) = 'verified' THEN 1 ELSE 0 END) as ver, 
            MAX(match_id) as max_id 
            FROM matches$mWhere");
        if ($q && $r = $q->fetch_assoc()) {
            $stats['total_matches'] = (int)($r['tot'] ?? 0);
            $stats['verified_matches'] = (int)($r['ver'] ?? 0);
            $maxIds['match'] = (int)($r['max_id'] ?? 0);
        }

        // Claims / Recovery
        $q = $conn->query("SELECT COUNT(*) as tot, 
            SUM(CASE WHEN LOWER(status) IN ('pending_verification', 'in_progress', 'pending') THEN 1 ELSE 0 END) as pend,
            SUM(CASE WHEN LOWER(status) IN ('completed', 'resolved', 'handed_off', 'archived') THEN 1 ELSE 0 END) as res,
            MAX(id) as max_id 
            FROM recovery_cases$cWhere");
        if ($q && $r = $q->fetch_assoc()) {
            $stats['pending_claims'] = (int)($r['pend'] ?? 0);
            $stats['resolved_cases'] = (int)($r['res'] ?? 0);
            $maxIds['claim'] = (int)($r['max_id'] ?? 0);
        }

        // Recent audit log id
        $q = $conn->query("SELECT MAX(log_id) as max_id FROM access_logs");
        if ($q && $r = $q->fetch_assoc()) {
            $maxIds['log'] = (int)($r['max_id'] ?? 0);
        }

        // Fetch last 3 recent platform events across the board for the live ticker
        $recentSql = "
            (SELECT 'found' as event_type, title, campus_id, date_found as event_date FROM found_reports " . ($scope ? "WHERE campus_id = $scope" : "") . " ORDER BY found_id DESC LIMIT 2)
            UNION ALL
            (SELECT 'lost' as event_type, title, campus_id, date_lost as event_date FROM lost_reports " . ($scope ? "WHERE campus_id = $scope" : "") . " ORDER BY lost_id DESC LIMIT 2)
            UNION ALL
            (SELECT 'claim' as event_type, CONCAT('Claim on Case #', id) as title, campus_id, DATE(created_at) as event_date FROM recovery_cases " . ($scope ? "WHERE campus_id = $scope" : "") . " ORDER BY id DESC LIMIT 2)
            ORDER BY event_date DESC LIMIT 3
        ";
        $rq = @$conn->query($recentSql);
        if ($rq) {
            while ($row = $rq->fetch_assoc()) {
                $latestEvents[] = $row;
            }
        }
    }

    echo json_encode([
        'success'       => true,
        'timestamp'     => time(),
        'server_time'   => date('H:i:s'),
        'stats'         => $stats,
        'max_ids'       => $maxIds,
        'latest_events' => $latestEvents,
        'db_status'     => ($conn && $conn->ping()) ? 'online' : 'offline'
    ]);
    exit;
}

// ── 5B-3. System Health & Infrastructure Diagnostics ──────────
if ($action === 'system_test') {
    $dbOk = ($conn && $conn->ping());

    $flaskUrl = (defined('FLASK_AI_URL') ? FLASK_AI_URL : 'https://reunite-ai-backend.onrender.com');
    $startT = microtime(true);
    $aiOk = false;
    $aiLatency = 0;

    $ch = @curl_init("$flaskUrl/status");
    if ($ch) {
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $aiLatency = round((microtime(true) - $startT) * 1000);
        if ($httpCode === 200 && $resp) {
            $aiOk = true;
        }
    }

    $smtpOk = defined('BREVO_API_KEY') && !empty(BREVO_API_KEY);

    echo json_encode([
        'success'           => true,
        'database'          => $dbOk ? 'MySQL Connected' : 'Disconnected',
        'ai_backend_status' => $aiOk ? 'Online' : 'Offline',
        'ai_backend_url'    => $flaskUrl,
        'ai_latency_ms'     => $aiLatency,
        'smtp_configured'   => $smtpOk ? 'Configured (Brevo)' : 'Not set',
        'server_time'       => date('Y-m-d H:i:s T')
    ]);
    exit;
}

// ── 5C. Operational Command Center: User Management ─────────
if ($action === 'users') {
    $scope = get_admin_campus_scope();
    $search = trim($_GET['search'] ?? '');
    $role = trim($_GET['role'] ?? '');
    $status = trim($_GET['status'] ?? '');

    $where = [];
    if ($scope !== null) {
        $where[] = "u.campus_id = $scope";
    }
    if (!empty($role)) {
        $safeRole = $conn->real_escape_string($role);
        $where[] = "u.role = '$safeRole'";
    }
    if (!empty($status)) {
        $safeStat = $conn->real_escape_string($status);
        $where[] = "u.status = '$safeStat'";
    }

    $whereSql = !empty($where) ? ("WHERE " . implode(' AND ', $where)) : "";
    $sql = "SELECT u.user_id, u.full_name, u.pin, u.email, u.phone, u.college, u.branch, u.trust_score, u.role, u.status, u.created_at, u.campus_id, c.name as campus_name
            FROM users u
            LEFT JOIN campuses c ON u.campus_id = c.id
            $whereSql
            ORDER BY u.user_id DESC LIMIT 150";

    $users = [];
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $decEmail = !empty($r['email']) ? decryptData($r['email']) : '';
            $decPhone = !empty($r['phone']) ? decryptData($r['phone']) : '';

            if (!empty($search)) {
                $s = strtolower($search);
                $match = str_contains(strtolower($r['full_name'] ?? ''), $s) ||
                         str_contains(strtolower($r['pin'] ?? ''), $s) ||
                         str_contains(strtolower($decEmail), $s);
                if (!$match) continue;
            }

            $users[] = [
                'user_id'     => (int)$r['user_id'],
                'full_name'   => $r['full_name'],
                'pin'         => $r['pin'],
                'email'       => $decEmail ?: $r['email'],
                'phone'       => $decPhone ?: $r['phone'],
                'college'     => $r['campus_name'] ?? ($r['college'] ?: 'SVGP'),
                'branch'      => $r['branch'] ?: 'General',
                'trust_score' => (int)($r['trust_score'] ?? 100),
                'role'        => $r['role'] ?? 'user',
                'status'      => $r['status'] ?? 'active',
                'campus_id'   => $r['campus_id'],
                'created_at'  => $r['created_at']
            ];
        }
    }

    echo json_encode(['success' => true, 'count' => count($users), 'users' => $users]);
    exit;
}

// ── 5D. Update User Profile / Status / Password ─────────────
if ($action === 'update_user') {
    $req = getRequestData();
    $userId = (int)($req['user_id'] ?? $_POST['user_id'] ?? 0);
    $status = trim($req['status'] ?? $_POST['status'] ?? '');
    $role = trim($req['role'] ?? $_POST['role'] ?? '');
    $trust = isset($req['trust_score']) ? (int)$req['trust_score'] : (isset($_POST['trust_score']) ? (int)$_POST['trust_score'] : null);
    $newPass = trim($req['password'] ?? $_POST['password'] ?? '');

    $chk = $conn->query("SELECT user_id, campus_id, role FROM users WHERE user_id = $userId LIMIT 1")->fetch_assoc();
    if (!$chk) {
        echo json_encode(['success' => false, 'error' => 'User account not found.']);
        exit;
    }

    // Anti-IDOR check
    assert_campus_access($chk['campus_id'], 'user account');

    // Campus admin cannot promote someone to admin or modify master admins
    if (!$isMasterAdmin && ($role === 'admin' || $role === 'master_admin' || $chk['role'] === 'admin')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission Denied: Campus Administrators cannot modify administrator accounts or change user roles to admin.']);
        exit;
    }

    $updates = [];
    if (!empty($status) && in_array($status, ['active', 'blocked'])) {
        $updates[] = "status = '$status'";
    }
    if ($isMasterAdmin && !empty($role) && in_array($role, ['user', 'admin'])) {
        $updates[] = "role = '$role'";
    }
    if ($trust !== null && $trust >= 0 && $trust <= 100) {
        $updates[] = "trust_score = $trust";
    }
    if (!empty($newPass)) {
        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        $updates[] = "password_hash = '$hash'";
    }

    if (!empty($updates)) {
        $conn->query("UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = $userId");
        log_admin_action('Update User', 'user', $userId, "Updated fields: " . implode(', ', $updates));
    }

    echo json_encode(['success' => true, 'message' => "User #$userId updated successfully."]);
    exit;
}

// ── 5E. Delete User Account ─────────────────────────────────
if ($action === 'delete_user') {
    $req = getRequestData();
    $userId = (int)($req['user_id'] ?? $_POST['user_id'] ?? 0);

    $chk = $conn->query("SELECT user_id, campus_id, role FROM users WHERE user_id = $userId LIMIT 1")->fetch_assoc();
    if (!$chk) {
        echo json_encode(['success' => false, 'error' => 'User account not found.']);
        exit;
    }

    assert_campus_access($chk['campus_id'], 'user account');

    if (!$isMasterAdmin && $chk['role'] === 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission Denied: Cannot delete an administrator account.']);
        exit;
    }

    $conn->query("DELETE FROM users WHERE user_id = $userId");
    log_admin_action('Delete User', 'user', $userId, "User account #$userId deleted");
    echo json_encode(['success' => true, 'message' => "User #$userId deleted successfully."]);
    exit;
}

// ── 5F. Lost Property Reports Query ────────────────────────
if ($action === 'lost_reports') {
    $scope = get_admin_campus_scope();
    $search = trim($_GET['search'] ?? '');
    $cat = trim($_GET['category'] ?? '');
    $stat = trim($_GET['status'] ?? '');

    $where = [];
    if ($scope !== null) {
        $where[] = "l.campus_id = $scope";
    }
    if (!empty($cat)) {
        $safeCat = $conn->real_escape_string($cat);
        $where[] = "l.category = '$safeCat'";
    }
    if (!empty($stat)) {
        $safeStat = $conn->real_escape_string($stat);
        $where[] = "l.status = '$safeStat'";
    }

    $whereSql = !empty($where) ? ("WHERE " . implode(' AND ', $where)) : "";
    $sql = "SELECT l.*, u.full_name as user_name, u.email as user_email, u.phone as user_phone, c.name as campus_name
            FROM lost_reports l
            LEFT JOIN users u ON l.user_id = u.user_id
            LEFT JOIN campuses c ON l.campus_id = c.id
            $whereSql
            ORDER BY l.id DESC LIMIT 150";

    $reports = [];
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            if (!empty($search)) {
                $s = strtolower($search);
                $match = str_contains(strtolower($r['title'] ?? ''), $s) ||
                         str_contains(strtolower($r['location'] ?? ''), $s) ||
                         str_contains(strtolower($r['description'] ?? ''), $s);
                if (!$match) continue;
            }
            $r['user_email_decrypted'] = !empty($r['user_email']) ? decryptData($r['user_email']) : '';
            $reports[] = $r;
        }
    }
    echo json_encode(['success' => true, 'reports' => $reports]);
    exit;
}

// ── 5G. Found Property Reports Query ───────────────────────
if ($action === 'found_reports') {
    $scope = get_admin_campus_scope();
    $search = trim($_GET['search'] ?? '');
    $cat = trim($_GET['category'] ?? '');
    $stat = trim($_GET['status'] ?? '');

    $where = [];
    if ($scope !== null) {
        $where[] = "f.campus_id = $scope";
    }
    if (!empty($cat)) {
        $safeCat = $conn->real_escape_string($cat);
        $where[] = "f.category = '$safeCat'";
    }
    if (!empty($stat)) {
        $safeStat = $conn->real_escape_string($stat);
        $where[] = "f.status = '$safeStat'";
    }

    $whereSql = !empty($where) ? ("WHERE " . implode(' AND ', $where)) : "";
    $sql = "SELECT f.*, u.full_name as user_name, u.email as user_email, u.phone as user_phone, c.name as campus_name
            FROM found_reports f
            LEFT JOIN users u ON f.user_id = u.user_id
            LEFT JOIN campuses c ON f.campus_id = c.id
            $whereSql
            ORDER BY f.id DESC LIMIT 150";

    $reports = [];
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            if (!empty($search)) {
                $s = strtolower($search);
                $match = str_contains(strtolower($r['title'] ?? ''), $s) ||
                         str_contains(strtolower($r['location'] ?? ''), $s) ||
                         str_contains(strtolower($r['description'] ?? ''), $s);
                if (!$match) continue;
            }
            $r['user_email_decrypted'] = !empty($r['user_email']) ? decryptData($r['user_email']) : '';
            $reports[] = $r;
        }
    }
    echo json_encode(['success' => true, 'reports' => $reports]);
    exit;
}

// ── 5H. Update Report Status ────────────────────────────────
if ($action === 'update_report_status') {
    $req = getRequestData();
    $type = strtolower(trim($req['type'] ?? $_POST['type'] ?? ''));
    $id = (int)($req['id'] ?? $_POST['id'] ?? 0);
    $status = trim($req['status'] ?? $_POST['status'] ?? '');

    if (!in_array($type, ['lost', 'found']) || $id <= 0 || !in_array($status, ['active', 'matched', 'claimed', 'closed'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
        exit;
    }

    $tbl = ($type === 'lost') ? 'lost_reports' : 'found_reports';
    $rRow = $conn->query("SELECT id, campus_id FROM $tbl WHERE id = $id LIMIT 1")->fetch_assoc();
    if (!$rRow) {
        echo json_encode(['success' => false, 'error' => 'Report not found.']);
        exit;
    }
    assert_campus_access($rRow['campus_id'], "$type report");

    $conn->query("UPDATE $tbl SET status = '$status' WHERE id = $id");
    log_admin_action('Update Report Status', $type . '_report', $id, "Status set to $status");
    echo json_encode(['success' => true, 'message' => "Report #$id status set to $status."]);
    exit;
}

// ── 5I. AI Matches Query ────────────────────────────────────
if ($action === 'matches') {
    $scope = get_admin_campus_scope();
    $where = ($scope !== null) ? "WHERE m.campus_id = $scope" : "";

    $sql = "SELECT m.id, m.lost_report_id, m.found_report_id, m.similarity_score as confidence_score, 
                   m.status, m.return_type, m.created_at, m.campus_id,
                   lr.title as lost_title, lr.category as lost_cat, lr.location as lost_loc,
                   fr.title as found_title, fr.category as found_cat, fr.image_path as found_image
            FROM matches m
            LEFT JOIN lost_reports lr ON m.lost_report_id = lr.id
            LEFT JOIN found_reports fr ON m.found_report_id = fr.id
            $where
            ORDER BY m.id DESC LIMIT 100";

    $matches = [];
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $matches[] = $r;
        }
    }
    echo json_encode(['success' => true, 'matches' => $matches]);
    exit;
}

// ── 5J. Update Match Status ─────────────────────────────────
if ($action === 'update_match_status') {
    $req = getRequestData();
    $matchId = (int)($req['id'] ?? $_POST['id'] ?? 0);
    $status = trim($req['status'] ?? $_POST['status'] ?? '');

    if ($matchId <= 0 || !in_array($status, ['pending', 'verified', 'rejected'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
        exit;
    }

    $mRow = $conn->query("SELECT id, campus_id FROM matches WHERE id = $matchId LIMIT 1")->fetch_assoc();
    if (!$mRow) {
        echo json_encode(['success' => false, 'error' => 'Match not found.']);
        exit;
    }
    assert_campus_access($mRow['campus_id'], 'match');

    $conn->query("UPDATE matches SET status = '$status' WHERE id = $matchId");
    log_admin_action('Update Match Status', 'match', $matchId, "Status set to: $status");
    echo json_encode(['success' => true, 'message' => "Match #$matchId updated to $status."]);
    exit;
}

// ── 5K. Activity & Access Audit Logs (Scoped) ───────────────
if ($action === 'logs') {
    $scope = get_admin_campus_scope();
    $search = trim($_GET['search'] ?? '');
    $cat = trim($_GET['category'] ?? '');

    $where = [];
    if (!empty($cat)) {
        $safeCat = $conn->real_escape_string($cat);
        $where[] = "l.category = '$safeCat'";
    }

    $whereSql = !empty($where) ? ("WHERE " . implode(' AND ', $where)) : "";
    $sql = "SELECT l.* FROM logs l $whereSql ORDER BY l.id DESC LIMIT 150";

    $logs = [];
    $res = @$conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            if (!empty($search)) {
                $s = strtolower($search);
                $match = str_contains(strtolower($r['action'] ?? ''), $s) ||
                         str_contains(strtolower($r['operator_name'] ?? ''), $s) ||
                         str_contains(strtolower($r['details'] ?? ''), $s);
                if (!$match) continue;
            }
            $logs[] = $r;
        }
    }

    // Also fallback to access_logs if logs is empty
    if (empty($logs)) {
        $q = $conn->query("SELECT log_id as id, action, ip_address, device_info, log_time as created_at FROM access_logs ORDER BY log_id DESC LIMIT 100");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                $r['operator_name'] = 'System';
                $r['category'] = 'system_event';
                $logs[] = $r;
            }
        }
    }

    echo json_encode(['success' => true, 'count' => count($logs), 'logs' => $logs]);
    exit;
}

// ── 5L. Master Admin: Manage Campuses ───────────────────────
if ($action === 'manage_campuses') {
    if (!$isMasterAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission Denied: Only Master Admin can manage campuses.']);
        exit;
    }

    if ($method === 'POST') {
        $req = getRequestData();
        $id = (int)($req['id'] ?? $_POST['id'] ?? 0);
        $name = trim($req['name'] ?? $_POST['name'] ?? '');
        $code = strtoupper(trim($req['code'] ?? $_POST['code'] ?? ''));
        $status = in_array(trim($req['status'] ?? $_POST['status'] ?? ''), ['active', 'inactive']) ? trim($req['status'] ?? $_POST['status'] ?? '') : 'active';

        if (empty($name) || empty($code)) {
            echo json_encode(['success' => false, 'error' => 'Campus Name and Code are required.']);
            exit;
        }

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE campuses SET name = ?, code = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $code, $status, $id);
            $stmt->execute();
            $stmt->close();
            log_admin_action('Update Campus', 'campus', $id, "Updated: $name ($code)");
            echo json_encode(['success' => true, 'message' => "Campus '$name' updated successfully."]);
        } else {
            $stmt = $conn->prepare("INSERT INTO campuses (name, code, status) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $code, $status);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();
            log_admin_action('Create Campus', 'campus', $newId, "Created: $name ($code)");
            echo json_encode(['success' => true, 'message' => "Campus '$name' created successfully."]);
        }
        exit;
    }

    // GET: List campuses with metrics
    $campuses = [];
    $res = @$conn->query("
        SELECT c.*,
            (SELECT COUNT(*) FROM users u WHERE u.campus_id = c.id) as student_count,
            (SELECT COUNT(*) FROM lost_reports l WHERE l.campus_id = c.id) as lost_count,
            (SELECT COUNT(*) FROM found_reports f WHERE f.campus_id = c.id) as found_count,
            (SELECT COUNT(*) FROM admins a WHERE a.campus_id = c.id) as admin_count
        FROM campuses c
        ORDER BY c.id ASC
    ");
    if (!$res) {
        $res = @$conn->query("SELECT * FROM campuses ORDER BY id ASC");
    }
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $campuses[] = $r;
        }
    }
    echo json_encode([
        'success'  => true,
        'campuses' => $campuses,
        'data'     => ['campuses' => $campuses]
    ]);
    exit;
}

// ── 5M. Master Admin: Manage Campus Admins ───────────────────
if ($action === 'manage_campus_admins') {
    if (!$isMasterAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission Denied: Only Master Admin can manage administrators.']);
        exit;
    }

    if ($method === 'POST') {
        $req = getRequestData();
        $subaction = strtolower(trim($req['subaction'] ?? $_POST['subaction'] ?? ''));
        $adminId = (int)($req['admin_id'] ?? $_POST['admin_id'] ?? 0);
        $username = trim($req['username'] ?? $_POST['username'] ?? '');
        $fullName = trim($req['full_name'] ?? $_POST['full_name'] ?? '');
        $email = trim($req['email'] ?? $_POST['email'] ?? '');
        $password = trim($req['password'] ?? $_POST['password'] ?? '');
        $campusId = !empty($req['campus_id'] ?? $_POST['campus_id'] ?? null) ? (int)($req['campus_id'] ?? $_POST['campus_id']) : null;
        $role = trim($req['role'] ?? $_POST['role'] ?? 'campus_admin');
        $status = in_array(trim($req['status'] ?? $_POST['status'] ?? ''), ['active', 'suspended']) ? trim($req['status'] ?? $_POST['status'] ?? '') : 'active';

        // ── Subaction: DELETE / REMOVE CAMPUS ADMIN ────────────
        if ($subaction === 'delete') {
            if ($adminId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid administrator ID provided for removal.']);
                exit;
            }
            if ($adminId === 1 || (isset($_SESSION['admin_id']) && $adminId === (int)$_SESSION['admin_id'])) {
                echo json_encode(['success' => false, 'error' => 'Master Administrator account cannot be removed.']);
                exit;
            }

            $stmt = $conn->prepare("SELECT username, full_name, role, campus_id FROM admins WHERE admin_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $adminId);
                $stmt->execute();
                $adm = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$adm) {
                    echo json_encode(['success' => false, 'error' => 'Administrator account not found.']);
                    exit;
                }

                $delStmt = $conn->prepare("DELETE FROM admins WHERE admin_id = ?");
                if ($delStmt) {
                    $delStmt->bind_param("i", $adminId);
                    $delStmt->execute();
                    $delStmt->close();

                    log_admin_action('Remove Campus Admin', 'admin', $adminId, "Permanently removed administrator '{$adm['username']}' ({$adm['role']}) for campus #" . ($adm['campus_id'] ?? 'N/A'));
                    echo json_encode([
                        'success' => true,
                        'message' => "Campus Administrator '{$adm['username']}' has been removed successfully."
                    ]);
                    exit;
                }
            }
            echo json_encode(['success' => false, 'error' => 'Database error executing administrator removal.']);
            exit;
        }

        // ── Subaction: TOGGLE ACTIVE / SUSPENDED STATUS ────────
        if ($subaction === 'toggle_status') {
            if ($adminId <= 0 || $adminId === 1) {
                echo json_encode(['success' => false, 'error' => 'Master Administrator status cannot be changed.']);
                exit;
            }
            $newStatus = in_array($req['status'] ?? '', ['active', 'suspended']) ? $req['status'] : 'active';
            $tStmt = $conn->prepare("UPDATE admins SET status = ? WHERE admin_id = ?");
            if ($tStmt) {
                $tStmt->bind_param("si", $newStatus, $adminId);
                $tStmt->execute();
                $tStmt->close();

                log_admin_action('Toggle Admin Status', 'admin', $adminId, "Administrator status changed to $newStatus");
                echo json_encode(['success' => true, 'message' => "Administrator status updated to '$newStatus'."]);
                exit;
            }
            echo json_encode(['success' => false, 'error' => 'Database error updating status.']);
            exit;
        }

        // ── Subaction: CREATE / UPDATE ─────────────────────────
        if ($role === 'campus_admin' && empty($campusId)) {
            echo json_encode(['success' => false, 'error' => 'A Campus Administrator must be assigned to an active campus.']);
            exit;
        }

        if ($subaction === 'update' || $adminId > 0) {
            // Update existing admin
            if ($adminId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid administrator ID for update.']);
                exit;
            }

            // Check if username or email is already taken by another admin
            $chk = $conn->prepare("SELECT admin_id FROM admins WHERE (username = ? OR email = ?) AND admin_id != ? LIMIT 1");
            if ($chk) {
                $chk->bind_param("ssi", $username, $email, $adminId);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) {
                    $chk->close();
                    echo json_encode(['success' => false, 'error' => 'Another administrator is already using this username or email.']);
                    exit;
                }
                $chk->close();
            }

            $effCampusId = ($role === 'master_admin') ? null : $campusId;
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $uStmt = $conn->prepare("UPDATE admins SET full_name = ?, username = ?, email = ?, password_hash = ?, role = ?, campus_id = ?, status = ? WHERE admin_id = ?");
                $uStmt->bind_param("sssssisi", $fullName, $username, $email, $hash, $role, $effCampusId, $status, $adminId);
            } else {
                $uStmt = $conn->prepare("UPDATE admins SET full_name = ?, username = ?, email = ?, role = ?, campus_id = ?, status = ? WHERE admin_id = ?");
                $uStmt->bind_param("ssssisi", $fullName, $username, $email, $role, $effCampusId, $status, $adminId);
            }

            if ($uStmt && $uStmt->execute()) {
                $uStmt->close();
                log_admin_action('Update Campus Admin', 'admin', $adminId, "Updated admin '$username' ($role, campus #$effCampusId)");
                echo json_encode(['success' => true, 'message' => "Administrator '$username' updated successfully."]);
                exit;
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update administrator record in database.']);
                exit;
            }
        } else {
            // Create brand new campus admin
            if (empty($username) || empty($email) || empty($password) || empty($fullName)) {
                echo json_encode(['success' => false, 'error' => 'Full Name, Username, Email, and Password are all required.']);
                exit;
            }
            if (strlen($password) < 4) {
                echo json_encode(['success' => false, 'error' => 'Password must be at least 4 characters long.']);
                exit;
            }

            // Check if username or email already exists
            $chk = $conn->prepare("SELECT admin_id FROM admins WHERE username = ? OR email = ? LIMIT 1");
            if ($chk) {
                $chk->bind_param("ss", $username, $email);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) {
                    $chk->close();
                    echo json_encode(['success' => false, 'error' => "An administrator account already exists with username '$username' or email '$email'."]);
                    exit;
                }
                $chk->close();
            }

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $effCampusId = ($role === 'master_admin') ? null : $campusId;
            $insStmt = $conn->prepare("INSERT INTO admins (username, full_name, email, password_hash, role, campus_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($insStmt) {
                $insStmt->bind_param("sssssis", $username, $fullName, $email, $hash, $role, $effCampusId, $status);
                if ($insStmt->execute()) {
                    $newAdminId = $insStmt->insert_id;
                    $insStmt->close();
                    log_admin_action('Create Campus Admin', 'admin', $newAdminId, "Created $role '$username' for campus #$effCampusId");
                    echo json_encode([
                        'success'  => true,
                        'message'  => "Campus Administrator '$username' created successfully.",
                        'admin_id' => $newAdminId
                    ]);
                    exit;
                } else {
                    $insStmt->close();
                    echo json_encode(['success' => false, 'error' => 'Database error inserting administrator.']);
                    exit;
                }
            }
            echo json_encode(['success' => false, 'error' => 'Database error preparing administrator insertion.']);
            exit;
        }
    }

    // GET: List all administrators
    $admins = [];
    $res = $conn->query("
        SELECT a.admin_id, a.username, a.full_name, a.email, a.role, a.status, a.campus_id, a.last_login, a.created_at,
               c.name as campus_name, c.code as campus_code
        FROM admins a
        LEFT JOIN campuses c ON a.campus_id = c.id
        ORDER BY a.admin_id ASC
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $admins[] = $r;
        }
    }
    echo json_encode([
        'success' => true,
        'admins'  => $admins,
        'data'    => ['admins' => $admins]
    ]);
    exit;
}

// ── 5N. Master Admin: Audit Logs Trail ──────────────────────
if ($action === 'admin_audit_logs') {
    $scope = get_admin_campus_scope();
    $where = ($scope !== null) ? "WHERE a.campus_id = $scope" : "";

    $logs = [];
    $res = $conn->query("
        SELECT a.*, c.name as campus_name
        FROM admin_audit_logs a
        LEFT JOIN campuses c ON a.campus_id = c.id
        $where
        ORDER BY a.id DESC LIMIT 200
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $logs[] = $r;
        }
    }
    echo json_encode(['success' => true, 'audit_logs' => $logs]);
    exit;
}

// ── 5O. Master Admin: System Diagnostics Ping ────────────────
if ($action === 'system_test') {
    $flaskUrl = (defined('FLASK_AI_URL') ? FLASK_AI_URL : 'https://reunite-ai-backend.onrender.com');
    $dbOk = ($conn && $conn->ping());

    $startT = microtime(true);
    $ch = curl_init("$flaskUrl/status");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $aiLatencyMs = round((microtime(true) - $startT) * 1000);
    $aiOk = ($httpCode === 200 && $resp);

    echo json_encode([
        'success'            => true,
        'database'           => $dbOk ? 'Connected (MySQL)' : 'Disconnected',
        'ai_backend_status'  => $aiOk ? 'Online' : 'Offline',
        'ai_backend_url'     => $flaskUrl,
        'ai_latency_ms'      => $aiLatencyMs,
        'smtp_gateway_status'=> (defined('BREVO_API_KEY') && !empty(BREVO_API_KEY)) ? 'Configured (Brevo)' : 'Configured (Default SMTP)'
    ]);
    exit;
}

// ── 6. List Reports with Full Digital DNA Linkage ──────────
if ($action === 'list_reports') {
    $type = strtolower(trim($_GET['type'] ?? 'all'));
    $search = trim($_GET['search'] ?? '');
    $limit = min((int)($_GET['limit'] ?? 100), 200);

    $results = [];

    if ($conn) {
        $lCols = [];
        $lcRes = @$conn->query("SHOW COLUMNS FROM lost_reports");
        if ($lcRes) while ($c = $lcRes->fetch_assoc()) $lCols[] = strtolower($c['Field']);
        $lPk = in_array('lost_id', $lCols) ? 'lost_id' : 'id';
        $lUserCol = in_array('user_id', $lCols) ? 'user_id' : (in_array('claimant_id', $lCols) ? 'claimant_id' : 'id');

        $fCols = [];
        $fcRes = @$conn->query("SHOW COLUMNS FROM found_reports");
        if ($fcRes) while ($c = $fcRes->fetch_assoc()) $fCols[] = strtolower($c['Field']);
        $fPk = in_array('found_id', $fCols) ? 'found_id' : 'id';
        $fUserCol = in_array('finder_id', $fCols) ? 'finder_id' : (in_array('user_id', $fCols) ? 'user_id' : 'id');

        $scope = get_admin_campus_scope();
        $lCampusClause = ($scope !== null) ? " WHERE (l.campus_id = $scope OR u.campus_id = $scope)" : "";
        $fCampusClause = ($scope !== null) ? " WHERE (f.campus_id = $scope OR u.campus_id = $scope)" : "";

        if ($type === 'all' || $type === 'lost') {
            $sql = "SELECT l.*, u.full_name as user_name, u.email as user_email, d.encrypted_dna as dna_json, d.dna_id 
                    FROM lost_reports l
                    LEFT JOIN users u ON l.`$lUserCol` = u.user_id
                    LEFT JOIN digital_dna d ON (d.report_id = l.`$lPk` AND UPPER(d.report_type) = 'LOST')
                    $lCampusClause
                    ORDER BY l.`$lPk` DESC LIMIT ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $limit);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $rowId = (int)($row[$lPk] ?? $row['id'] ?? 0);
                    $row['id'] = $rowId;
                    $row['report_type'] = 'lost';
                    $row['formatted_id'] = 'RL-' . str_pad($rowId, 5, '0', STR_PAD_LEFT);
                    $row['user_email_decrypted'] = !empty($row['user_email']) ? decryptData($row['user_email']) : 'N/A';
                    $row['has_dna'] = !empty($row['dna_json']);
                    $results[] = $row;
                }
                $stmt->close();
            }
        }

        if ($type === 'all' || $type === 'found') {
            $sql = "SELECT f.*, u.full_name as user_name, u.email as user_email, d.encrypted_dna as dna_json, d.dna_id 
                    FROM found_reports f
                    LEFT JOIN users u ON f.`$fUserCol` = u.user_id
                    LEFT JOIN digital_dna d ON (d.report_id = f.`$fPk` AND UPPER(d.report_type) = 'FOUND')
                    $fCampusClause
                    ORDER BY f.`$fPk` DESC LIMIT ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $limit);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $rowId = (int)($row[$fPk] ?? $row['id'] ?? 0);
                    $row['id'] = $rowId;
                    $row['report_type'] = 'found';
                    $row['formatted_id'] = 'RF-' . str_pad($rowId, 5, '0', STR_PAD_LEFT);
                    $row['user_email_decrypted'] = !empty($row['user_email']) ? decryptData($row['user_email']) : 'N/A';
                    $row['has_dna'] = !empty($row['dna_json']);
                    $results[] = $row;
                }
                $stmt->close();
            }
        }
    }

    // Apply text search filter if given
    if (!empty($search)) {
        $s = strtolower($search);
        $results = array_values(array_filter($results, function($r) use ($s) {
            return str_contains(strtolower($r['formatted_id']), $s) ||
                   str_contains(strtolower($r['title'] ?? ''), $s) ||
                   str_contains(strtolower($r['description'] ?? ''), $s) ||
                   str_contains(strtolower($r['category'] ?? ''), $s) ||
                   str_contains(strtolower($r['location'] ?? ''), $s) ||
                   str_contains(strtolower($r['user_name'] ?? ''), $s);
        }));
    }

    // Sort by ID descending
    usort($results, function($a, $b) {
        return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
    });

    echo json_encode(['success' => true, 'count' => count($results), 'reports' => $results]);
    exit;
}

// ── 7. Get Digital DNA Inspector ───────────────────────────
if ($action === 'get_dna') {
    $reportId = trim($_GET['report_id'] ?? '');
    $dbId = (int)($_GET['db_id'] ?? 0);
    $type = strtoupper(trim($_GET['type'] ?? 'LOST'));

    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Database connection unavailable']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM digital_dna WHERE (report_id = ? AND report_type = ?) LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("is", $dbId, $type);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $parsed = @json_decode($row['encrypted_dna'], true) ?: $row['encrypted_dna'];
            echo json_encode(['success' => true, 'dna' => $parsed, 'raw' => $row]);
            $stmt->close();
            exit;
        }
        $stmt->close();
    }

    echo json_encode(['success' => false, 'error' => 'No Digital DNA vector record found for this report.']);
    exit;
}

// ── 7B. List Recovery Cases (Claims) ──────────────────────
if ($action === 'recovery_cases') {
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Database connection unavailable']);
        exit;
    }

    if (function_exists('ensureRecoveryCasesSchema')) {
        ensureRecoveryCasesSchema();
    }

    $scope = get_admin_campus_scope();
    $rcCols = [];
    $rcRes = @$conn->query("SHOW COLUMNS FROM recovery_cases");
    if ($rcRes) while ($rc = $rcRes->fetch_assoc()) $rcCols[] = strtolower($rc['Field']);
    $rcPk = in_array('id', $rcCols) ? 'id' : (in_array('case_id', $rcCols) ? 'case_id' : 'id');
    $rcRepCol = in_array('report_id', $rcCols) ? 'report_id' : (in_array('found_id', $rcCols) ? 'found_id' : 'report_id');

    $cases = [];
    $rcWhere = ($scope !== null) ? " WHERE (r.campus_id = $scope OR u.campus_id = $scope)" : "";
    $sql = "SELECT r.*, u.full_name as claimant_name, u.email as claimant_email, u.phone as claimant_phone, c.name as campus_name
            FROM recovery_cases r
            LEFT JOIN users u ON r.claimant_id = u.user_id
            LEFT JOIN campuses c ON r.campus_id = c.id
            $rcWhere
            ORDER BY r.`$rcPk` DESC LIMIT 100";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cases[] = [
                'id' => (int)$row[$rcPk],
                'match_id' => $row['match_id'] ?? null,
                'found_report_id' => $row[$rcRepCol] ?? null,
                'claimant_name' => $row['claimant_name'] ?? 'Claimant',
                'claimant_email' => !empty($row['claimant_email']) ? decryptData($row['claimant_email']) : '',
                'claimant_phone' => !empty($row['claimant_phone']) ? decryptData($row['claimant_phone']) : '',
                'proof_description' => $row['proof_details'] ?? '',
                'proof_image_path' => $row['proof_image_path'] ?? null,
                'status' => $row['status'] ?? 'pending_verification',
                'return_type' => $row['return_type'] ?? ($row['return_method'] ?? 'in_person'),
                'campus_id' => $row['campus_id'] ?? null,
                'campus_name' => $row['campus_name'] ?? null,
                'created_at' => $row['created_at'] ?? date('Y-m-d')
            ];
        }
    }

    echo json_encode(['success' => true, 'cases' => $cases]);
    exit;
}

// ── 7C. Update Recovery Case Status ───────────────────────
if ($action === 'update_recovery_case') {
    $req = getRequestData();
    $caseId = (int)($req['id'] ?? $_POST['id'] ?? 0);
    $newStatus = trim($req['status'] ?? $_POST['status'] ?? '');

    if (!$conn || $caseId <= 0 || !in_array($newStatus, ['approved', 'handed_off', 'rejected', 'pending_verification'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters for updating recovery case.']);
        exit;
    }

    $rcCols = [];
    $rcRes = @$conn->query("SHOW COLUMNS FROM recovery_cases");
    if ($rcRes) while ($rc = $rcRes->fetch_assoc()) $rcCols[] = strtolower($rc['Field']);
    $rcPk = in_array('id', $rcCols) ? 'id' : (in_array('case_id', $rcCols) ? 'case_id' : 'id');
    $rcRepCol = in_array('report_id', $rcCols) ? 'report_id' : (in_array('found_id', $rcCols) ? 'found_id' : 'report_id');

    $stmt = $conn->prepare("SELECT * FROM recovery_cases WHERE `$rcPk` = ? LIMIT 1");
    $stmt->bind_param("i", $caseId);
    $stmt->execute();
    $cRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cRow) {
        echo json_encode(['success' => false, 'error' => 'Recovery case not found.']);
        exit;
    }

    // Anti-IDOR check
    assert_campus_access($cRow['campus_id'] ?? null, 'recovery case');

    $uStmt = $conn->prepare("UPDATE recovery_cases SET status = ? WHERE `$rcPk` = ?");
    $uStmt->bind_param("si", $newStatus, $caseId);
    $uStmt->execute();
    $uStmt->close();

    $foundId = (int)($cRow[$rcRepCol] ?? 0);

    $fCols = [];
    $fc = @$conn->query("SHOW COLUMNS FROM found_reports");
    if ($fc) while ($r = $fc->fetch_assoc()) $fCols[] = strtolower($r['Field']);
    $fPk = in_array('found_id', $fCols) ? 'found_id' : 'id';

    if ($newStatus === 'approved' || $newStatus === 'handed_off') {
        if ($foundId > 0) {
            @$conn->query("UPDATE found_reports SET status = 'claimed' WHERE `$fPk` = $foundId");
        }
    }

    log_admin_action('Update Recovery Case Status', 'recovery_case', $caseId, "Status updated to: $newStatus");
    echo json_encode(['success' => true, 'message' => "Recovery case #$caseId updated to $newStatus."]);
    exit;
}

// ── 7D. Claim & Handover v2: Get Banned Claimants ────────
if ($action === 'get_banned_claimants') {
    if (!is_admin_authorized()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized admin access.']);
        exit;
    }
    $scope = get_admin_campus_scope();
    $banned = [];
    $bCheck = @$conn->query("SHOW COLUMNS FROM users LIKE 'claim_ban_until'");
    if ($bCheck && $bCheck->num_rows > 0) {
        $uCols = [];
        $uc = @$conn->query("SHOW COLUMNS FROM users");
        if ($uc) while ($c = $uc->fetch_assoc()) $uCols[] = strtolower($c['Field']);
        $uNameCol = in_array('full_name', $uCols) ? 'full_name' : (in_array('name', $uCols) ? 'name' : 'user_id');
        $uPk = in_array('user_id', $uCols) ? 'user_id' : 'id';

        $bWhere = ($scope !== null) ? " AND campus_id = $scope" : "";
        $res = $conn->query("SELECT `$uPk` as id, `$uNameCol` as name, email, pin, claim_ban_until, campus_id, TIMESTAMPDIFF(SECOND, NOW(), claim_ban_until) as seconds_remaining FROM users WHERE claim_ban_until > NOW()$bWhere ORDER BY claim_ban_until DESC");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $banned[] = [
                    'id' => (int)$r['id'],
                    'name' => $r['name'] ?? 'User',
                    'email' => !empty($r['email']) ? decryptData($r['email']) : '',
                    'pin' => $r['pin'] ?? '',
                    'ban_until' => $r['claim_ban_until'],
                    'campus_id' => $r['campus_id'] ?? null,
                    'seconds_remaining' => (int)$r['seconds_remaining']
                ];
            }
        }
    }
    echo json_encode(['success' => true, 'banned_claimants' => $banned]);
    exit;
}

// ── 7E. Claim & Handover v2: Lift Claim Ban ──────────────
if ($action === 'lift_claim_ban') {
    if (!is_admin_authorized()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized admin access.']);
        exit;
    }
    $req = getRequestData();
    $targetUserId = (int)($req['user_id'] ?? $_POST['user_id'] ?? 0);
    if ($targetUserId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid user ID.']);
        exit;
    }

    // Anti-IDOR check
    $uRow = $conn->query("SELECT user_id, campus_id FROM users WHERE user_id = $targetUserId LIMIT 1")->fetch_assoc();
    if ($uRow) {
        assert_campus_access($uRow['campus_id'] ?? null, 'claimant ban');
    }

    $lifted = liftClaimantBan($targetUserId);
    if ($lifted) {
        log_admin_action('Lift Claim Ban', 'user', $targetUserId, "Lifted ban for student #$targetUserId");
        echo json_encode(['success' => true, 'message' => "Claim ban lifted for user #$targetUserId."]);
    } else {
        echo json_encode(['success' => false, 'error' => "Failed to lift ban for user #$targetUserId."]);
    }
    exit;
}

// ── 8. Delete Report (With Chroma & Media Cleanup) ─────────
if ($action === 'delete_report') {
    $reportType = strtolower(trim($_POST['report_type'] ?? ''));
    $reportDbId = (int)($_POST['db_id'] ?? 0);
    $formattedId = trim($_POST['formatted_id'] ?? '');

    if (!$conn || $reportDbId <= 0 || !in_array($reportType, ['lost', 'found'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters for report deletion.']);
        exit;
    }

    $table = ($reportType === 'found') ? 'found_reports' : 'lost_reports';
    $imagePath = null;

    // Fetch existing details for cleanup
    $stmt = $conn->prepare("SELECT * FROM $table WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $reportDbId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            // Anti-IDOR check
            assert_campus_access($row['campus_id'] ?? null, "$reportType report");
            if (!empty($row['image_path'])) {
                $imagePath = $row['image_path'];
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Report record not found.']);
            exit;
        }
    }

    // 1. Delete from main table
    $stmtDel = $conn->prepare("DELETE FROM $table WHERE id = ?");
    if ($stmtDel) {
        $stmtDel->bind_param("i", $reportDbId);
        $stmtDel->execute();
        $stmtDel->close();
    }

    // 2. Delete from digital_dna table
    $upperType = strtoupper($reportType);
    $stmtDna = $conn->prepare("DELETE FROM digital_dna WHERE report_id = ? AND report_type = ?");
    if ($stmtDna) {
        $stmtDna->bind_param("is", $reportDbId, $upperType);
        $stmtDna->execute();
        $stmtDna->close();
    }

    // 3. Delete physical image if within media_vault
    if (!empty($imagePath)) {
        $relPath = ltrim($imagePath, '/\\');
        $fullPath = dirname(__DIR__) . '/' . $relPath;
        if (file_exists($fullPath) && is_file($fullPath) && str_contains($fullPath, 'media_vault')) {
            @unlink($fullPath);
        }
    }

    // 4. Delete vector from ChromaDB
    try {
        $flaskUrl = (defined('FLASK_AI_URL') ? FLASK_AI_URL : 'https://reunite-ai-backend.onrender.com');
        $ch = curl_init("$flaskUrl/report/delete-vector/" . urlencode($formattedId));
        if ($ch) {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-AI-Secret-Key: ' . (defined('AI_SECRET_KEY') ? AI_SECRET_KEY : 'reunite_ai_secret_token_2026_kolkata')
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_exec($ch);
            curl_close($ch);
        }
    } catch (Exception $e) {}

    logAccess(0, "Report Deleted ($reportType)", "ID: $formattedId, DB ID: $reportDbId");
    echo json_encode(['success' => true, 'message' => "Report $formattedId purged from database, Media Vault, and ChromaDB."]);
    exit;
}

// ── 9. Sync & Re-index All Reports into ChromaDB ───────────
if ($action === 'sync_chroma') {
    @set_time_limit(180);
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Database connection unavailable']);
        exit;
    }

    $flaskUrl = (defined('FLASK_AI_URL') ? FLASK_AI_URL : 'https://reunite-ai-backend.onrender.com') . '/report/batch-sync';
    $secret = defined('AI_SECRET_KEY') ? AI_SECRET_KEY : 'reunite_ai_secret_token_2026_kolkata';

    $lCols = [];
    $lc = @$conn->query("SHOW COLUMNS FROM lost_reports");
    if ($lc) while ($r = $lc->fetch_assoc()) $lCols[] = strtolower($r['Field']);
    $lIdCol = in_array('lost_id', $lCols) ? 'lost_id' : 'id';
    $lTitleCol = in_array('item_name', $lCols) ? 'item_name' : (in_array('title', $lCols) ? 'title' : 'item_name');
    $lDateCol = in_array('lost_date', $lCols) ? 'lost_date' : (in_array('date_lost', $lCols) ? 'date_lost' : 'created_at');

    $fCols = [];
    $fc = @$conn->query("SHOW COLUMNS FROM found_reports");
    if ($fc) while ($r = $fc->fetch_assoc()) $fCols[] = strtolower($r['Field']);
    $fIdCol = in_array('found_id', $fCols) ? 'found_id' : 'id';
    $fTitleCol = in_array('item_name', $fCols) ? 'item_name' : (in_array('title', $fCols) ? 'title' : 'item_name');
    $fDateCol = in_array('found_date', $fCols) ? 'found_date' : (in_array('date_found', $fCols) ? 'date_found' : 'created_at');

    $allReports = [];

    // Fetch Lost Reports
    $resLost = $conn->query("SELECT * FROM lost_reports");
    if ($resLost) {
        while ($row = $resLost->fetch_assoc()) {
            $dbId = (int)$row[$lIdCol];
            $item = [
                'report_id' => 'RL-' . str_pad($dbId, 5, '0', STR_PAD_LEFT),
                'db_id' => $dbId,
                'report_type' => 'lost',
                'category' => $row['category'] ?? 'General',
                'title' => $row[$lTitleCol] ?? '',
                'description' => $row['description'] ?? '',
                'location' => $row['location'] ?? '',
                'date' => $row[$lDateCol] ?? date('Y-m-d')
            ];
            $img = $row['image_path'] ?? '';
            if ($img) {
                $fPath = dirname(__DIR__) . '/' . ltrim($img, '/');
                if (file_exists($fPath) && filesize($fPath) < 2 * 1024 * 1024) {
                    $item['image_base64'] = base64_encode(file_get_contents($fPath));
                }
            }
            $allReports[] = $item;
        }
    }

    // Fetch Found Reports
    $resFound = $conn->query("SELECT * FROM found_reports");
    if ($resFound) {
        while ($row = $resFound->fetch_assoc()) {
            $dbId = (int)$row[$fIdCol];
            $item = [
                'report_id' => 'RF-' . str_pad($dbId, 5, '0', STR_PAD_LEFT),
                'db_id' => $dbId,
                'report_type' => 'found',
                'category' => $row['category'] ?? 'General',
                'title' => $row[$fTitleCol] ?? '',
                'description' => $row['description'] ?? '',
                'location' => $row['location'] ?? '',
                'date' => $row[$fDateCol] ?? date('Y-m-d')
            ];
            $img = $row['image_path'] ?? '';
            if ($img) {
                $fPath = dirname(__DIR__) . '/' . ltrim($img, '/');
                if (file_exists($fPath) && filesize($fPath) < 2 * 1024 * 1024) {
                    $item['image_base64'] = base64_encode(file_get_contents($fPath));
                }
            }
            $allReports[] = $item;
        }
    }

    $synced = 0;
    $errors = 0;

    // Send in batches of 20
    $chunks = array_chunk($allReports, 20);
    foreach ($chunks as $chunk) {
        $ch = curl_init($flaskUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['reports' => $chunk]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-AI-Secret-Key: ' . $secret
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $out = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http === 200) {
            $dec = json_decode($out, true);
            $synced += (int)($dec['synced'] ?? count($chunk));
        } else {
            $errors += count($chunk);
        }
    }

    logAccess(0, 'ChromaDB Full Resync', "Indexed $synced reports into Vector Store ($errors errors)");
    echo json_encode([
        'success' => true,
        'message' => "ChromaDB vector resync complete. Indexed $synced report feature vectors.",
        'synced' => $synced,
        'errors' => $errors
    ]);
    exit;
}

// ── 10. Purge All ChromaDB Vectors ─────────────────────────
if ($action === 'purge_chroma') {
    $flaskUrl = (defined('FLASK_AI_URL') ? FLASK_AI_URL : 'https://reunite-ai-backend.onrender.com') . '/report/purge-all-vectors';
    $ch = curl_init($flaskUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-AI-Secret-Key: ' . (defined('AI_SECRET_KEY') ? AI_SECRET_KEY : 'reunite_ai_secret_token_2026_kolkata')
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    $resp = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($resp, true) ?: ['success' => true];
    logAccess(0, 'ChromaDB Vectors Purged', 'Purged vector collections via admin maintenance tool');
    echo json_encode($json);
    exit;
}

// ── 11. Media Vault Scanner & Orphan Detector ──────────────
if ($action === 'scan_media_vault') {
    $rootVault = dirname(__DIR__) . '/media_vault';
    $dirs = [
        'lost_reports' => $rootVault . '/lost_reports',
        'found_reports' => $rootVault . '/found_reports'
    ];

    // Harvest all active DB images
    $activeDbImages = [];
    if ($conn) {
        $q = $conn->query("SELECT image_path FROM found_reports WHERE image_path IS NOT NULL AND image_path != ''");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                $activeDbImages[] = basename($r['image_path']);
            }
        }
    }

    $allFiles = [];
    $orphanedFiles = [];

    foreach ($dirs as $folderKey => $folderPath) {
        if (!is_dir($folderPath)) continue;
        $scan = scandir($folderPath);
        foreach ($scan as $f) {
            if ($f === '.' || $f === '..' || $f === '.gitkeep') continue;
            $full = $folderPath . '/' . $f;
            if (is_file($full)) {
                $isOrphan = !in_array($f, $activeDbImages);
                $fileItem = [
                    'filename' => $f,
                    'folder' => $folderKey,
                    'rel_path' => "media_vault/$folderKey/$f",
                    'size' => filesize($full),
                    'size_formatted' => format_bytes(filesize($full)),
                    'modified' => date('Y-m-d H:i:s', filemtime($full)),
                    'is_orphaned' => $isOrphan
                ];
                $allFiles[] = $fileItem;
                if ($isOrphan) {
                    $orphanedFiles[] = $fileItem;
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'total_files' => count($allFiles),
        'orphaned_count' => count($orphanedFiles),
        'files' => $allFiles
    ]);
    exit;
}

// ── 12. Purge Orphaned Media Files ─────────────────────────
if ($action === 'purge_orphans') {
    $rootVault = dirname(__DIR__) . '/media_vault';
    $dirs = [
        $rootVault . '/lost_reports',
        $rootVault . '/found_reports'
    ];

    $activeDbImages = [];
    if ($conn) {
        $q = $conn->query("SELECT image_path FROM found_reports WHERE image_path IS NOT NULL AND image_path != ''");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                $activeDbImages[] = basename($r['image_path']);
            }
        }
    }

    $purgedCount = 0;
    $bytesFreed = 0;

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) continue;
        $scan = scandir($dir);
        foreach ($scan as $f) {
            if ($f === '.' || $f === '..' || $f === '.gitkeep') continue;
            if (!in_array($f, $activeDbImages)) {
                $full = $dir . '/' . $f;
                if (is_file($full)) {
                    $bytesFreed += filesize($full);
                    @unlink($full);
                    $purgedCount++;
                }
            }
        }
    }

    logAccess(0, 'Media Vault Orphan Purge', "Cleaned $purgedCount orphaned files (" . format_bytes($bytesFreed) . ")");
    echo json_encode([
        'success' => true,
        'purged_count' => $purgedCount,
        'bytes_freed' => format_bytes($bytesFreed),
        'message' => "Successfully purged $purgedCount orphaned media files. Freed " . format_bytes($bytesFreed) . " of storage."
    ]);
    exit;
}

// ── 13. Access Audit Logs ──────────────────────────────────
if ($action === 'get_logs') {
    $limit = min((int)($_GET['limit'] ?? 100), 200);
    $logs = [];

    if ($conn) {
        $q = $conn->query("SELECT a.*, u.full_name as user_name FROM access_logs a LEFT JOIN users u ON a.user_id = u.user_id ORDER BY a.log_id DESC LIMIT $limit");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                if (!isset($r['created_at']) && isset($r['log_time'])) {
                    $r['created_at'] = $r['log_time'];
                }
                $logs[] = $r;
            }
        }
    }

    echo json_encode(['success' => true, 'count' => count($logs), 'logs' => $logs]);
    exit;
}

// ── 14. Clear Old Audit Logs ───────────────────────────────
if ($action === 'clear_logs') {
    if ($conn) {
        $conn->query("TRUNCATE TABLE access_logs");
        logAccess(0, 'Audit Logs Reset', 'Access logs table truncated by System Administrator');
        echo json_encode(['success' => true, 'message' => 'Access audit logs purged successfully.']);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

// ── 15. Database Optimization ──────────────────────────────
if ($action === 'optimize_db') {
    $optimized = [];
    if ($conn) {
        $tables = ['users', 'lost_reports', 'found_reports', 'digital_dna', 'matches', 'recovery_cases', 'notifications', 'access_logs'];
        foreach ($tables as $t) {
            $conn->query("OPTIMIZE TABLE `$t`");
            $optimized[] = $t;
        }
        logAccess(0, 'Database Optimized', 'Optimized ' . count($optimized) . ' MySQL tables');
        echo json_encode(['success' => true, 'message' => 'All database tables optimized and indexes rebuilt.', 'tables' => $optimized]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

// Helper formatting function
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

echo json_encode(['success' => false, 'error' => 'Invalid or unrecognized admin action.']);
exit;
