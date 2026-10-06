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

if (!defined('ADMIN_MASTER_KEY')) {
    define('ADMIN_MASTER_KEY', get_config_val('ADMIN_MASTER_KEY', 'admin@reunite2024'));
}
$maintenanceFile = __DIR__ . '/config/maintenance.json';

// Ensure session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = trim($_REQUEST['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Helper to check admin authorization
function is_admin_authorized() {
    if (!empty($_SESSION['reunite_admin_auth']) && $_SESSION['reunite_admin_auth'] === true) {
        return true;
    }
    // Also check if logged in as user with role 'admin'
    if (!empty($_SESSION['user_id'])) {
        $user = get_current_user_data();
        if ($user && ($user['role'] ?? '') === 'admin') {
            $_SESSION['reunite_admin_auth'] = true;
            return true;
        }
    }
    return false;
}

// ── 1. Admin Authentication: Login ────────────────────────
if ($action === 'login') {
    $passcode = trim($_POST['passcode'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Check Master Key / Admin Passcode
    $adminPin = get_config_val('ADMIN_PIN', 'admin');
    if (!empty($passcode) && ($passcode === 'admin' || hash_equals(ADMIN_MASTER_KEY, $passcode) || hash_equals($adminPin, $passcode))) {
        $_SESSION['reunite_admin_auth'] = true;
        $_SESSION['reunite_admin_name'] = 'System Administrator';
        $_SESSION['reunite_admin_role'] = 'SuperAdmin';
        
        logAccess(0, 'Admin Terminal Login (Master Key)', 'Authorization granted via Master Passcode');
        echo json_encode(['success' => true, 'message' => 'Admin clearance granted. Welcome, System Administrator.']);
        exit;
    }

    // Check DB Admin User Credentials
    if (!empty($email) && !empty($password) && $conn) {
        $encEmail = encryptData($email);
        $stmt = $conn->prepare("SELECT user_id, full_name, password_hash, role FROM users WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $encEmail);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($user = $res->fetch_assoc()) {
                if ($user['role'] === 'admin' && verify_password_custom($password, $user['password_hash'])) {
                    $_SESSION['reunite_admin_auth'] = true;
                    $_SESSION['reunite_admin_name'] = $user['full_name'];
                    $_SESSION['reunite_admin_role'] = 'Admin';
                    $_SESSION['user_id'] = $user['user_id'];
                    $stmt->close();
                    
                    logAccess($user['user_id'], 'Admin Terminal Login (User Credential)', 'Admin role verified');
                    echo json_encode(['success' => true, 'message' => 'Admin clearance granted.']);
                    exit;
                }
            }
            $stmt->close();
        }
    }

    logAccess(0, 'Admin Clearance Attempt Failed', 'Invalid key or credentials provided');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid admin clearance passcode or credentials. Access denied.']);
    exit;
}

// ── 2. Admin Authentication: Check Status ──────────────────
if ($action === 'check_auth') {
    echo json_encode([
        'success' => true,
        'authenticated' => is_admin_authorized(),
        'admin_name' => $_SESSION['reunite_admin_name'] ?? null,
        'role' => $_SESSION['reunite_admin_role'] ?? null
    ]);
    exit;
}

// ── 3. Admin Authentication: Logout ────────────────────────
if ($action === 'logout') {
    unset($_SESSION['reunite_admin_auth']);
    unset($_SESSION['reunite_admin_name']);
    unset($_SESSION['reunite_admin_role']);
    echo json_encode(['success' => true, 'message' => 'Admin session terminated.']);
    exit;
}

// ── Enforcement: All subsequent endpoints require Admin Authorization ──
if (!is_admin_authorized()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Security clearance required. Please authenticate into Admin Terminal.']);
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

// ── 6. List Reports with Full Digital DNA Linkage ──────────
if ($action === 'list_reports') {
    $type = strtolower(trim($_GET['type'] ?? 'all'));
    $search = trim($_GET['search'] ?? '');
    $limit = min((int)($_GET['limit'] ?? 100), 200);

    $results = [];

    if ($conn) {
        if ($type === 'all' || $type === 'lost') {
            $sql = "SELECT l.*, u.full_name as user_name, u.email as user_email, d.encrypted_dna as dna_json, d.dna_id 
                    FROM lost_reports l
                    LEFT JOIN users u ON l.user_id = u.user_id
                    LEFT JOIN digital_dna d ON (d.report_id = l.id AND d.report_type = 'LOST')
                    ORDER BY l.id DESC LIMIT ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $limit);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $row['report_type'] = 'lost';
                    $row['formatted_id'] = 'RL-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT);
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
                    LEFT JOIN users u ON f.user_id = u.user_id
                    LEFT JOIN digital_dna d ON (d.report_id = f.id AND d.report_type = 'FOUND')
                    ORDER BY f.id DESC LIMIT ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $limit);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $row['report_type'] = 'found';
                    $row['formatted_id'] = 'RF-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT);
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
        if ($row && !empty($row['image_path'])) {
            $imagePath = $row['image_path'];
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
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
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
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Database connection unavailable']);
        exit;
    }

    $flaskUrl = (defined('FLASK_AI_URL') ? FLASK_AI_URL : 'https://reunite-ai-backend.onrender.com') . '/report/embed-and-store';
    $synced = 0;
    $errors = 0;

    // Sync Lost Reports
    $resLost = $conn->query("SELECT l.*, d.encrypted_dna as dna_json FROM lost_reports l LEFT JOIN digital_dna d ON (d.report_id = l.id AND d.report_type = 'LOST')");
    if ($resLost) {
        while ($row = $resLost->fetch_assoc()) {
            $fId = 'RL-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT);
            $payload = [
                'report_id' => $fId,
                'db_id' => $row['id'],
                'report_type' => 'lost',
                'category' => $row['category'],
                'title' => $row['title'] ?? '',
                'description' => $row['description'] ?? '',
                'location' => $row['location'] ?? '',
                'date' => $row['date_lost'] ?? date('Y-m-d'),
                'digital_dna' => !empty($row['dna_json']) ? json_decode($row['dna_json'], true) : null
            ];

            $ch = curl_init($flaskUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            $out = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http === 200) $synced++; else $errors++;
        }
    }

    // Sync Found Reports
    $resFound = $conn->query("SELECT f.*, d.encrypted_dna as dna_json FROM found_reports f LEFT JOIN digital_dna d ON (d.report_id = f.id AND d.report_type = 'FOUND')");
    if ($resFound) {
        while ($row = $resFound->fetch_assoc()) {
            $fId = 'RF-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT);
            $payload = [
                'report_id' => $fId,
                'db_id' => $row['id'],
                'report_type' => 'found',
                'category' => $row['category'],
                'title' => $row['title'] ?? '',
                'description' => $row['description'] ?? '',
                'location' => $row['location'] ?? '',
                'date' => $row['date_found'] ?? date('Y-m-d'),
                'image_path' => $row['image_path'] ?? '',
                'digital_dna' => !empty($row['dna_json']) ? json_decode($row['dna_json'], true) : null
            ];

            $ch = curl_init($flaskUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            $out = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http === 200) $synced++; else $errors++;
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
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
