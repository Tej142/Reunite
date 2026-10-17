<?php
/**
 * Reunite Platform — Cloud Health & Database Verification Endpoint
 * Validates MySQL connectivity, table counts, and Render AI microservice status.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../../Backend/config/config.php';
require_once __DIR__ . '/../../Backend/functions.php';

$results = [
    'timestamp' => date('c'),
    'environment' => [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'host' => $_SERVER['HTTP_HOST'] ?? 'localhost',
    ],
    'database' => [
        'status' => 'disconnected',
        'host' => defined('DB_HOST') ? DB_HOST : 'unknown',
        'database' => defined('DB_NAME') ? DB_NAME : 'unknown',
        'tables' => [],
        'table_counts' => [],
        'missing_tables' => [],
        'schema_healthy' => false,
        'error' => null
    ],
    'ai_microservice' => [
        'status' => 'offline',
        'endpoint' => defined('FLASK_AI_URL') ? FLASK_AI_URL : 'https://reunite-ai-backend.onrender.com',
        'latency_ms' => null,
        'details' => null
    ]
];

// 1. Verify Database
global $conn, $db_connection_error;
if ($conn && !$conn->connect_error) {
    $results['database']['status'] = 'connected';
    $results['database']['server_info'] = $conn->server_info;

    // Check tables
    $tableRes = $conn->query("SHOW TABLES");
    if ($tableRes) {
        $expected = ['users', 'digital_dna', 'lost_reports', 'found_reports', 'matches', 'recovery_cases', 'notifications', 'password_resets', 'maintenance_settings', 'admins', 'logs', 'question_sets'];
        $found = [];
        while ($r = $tableRes->fetch_array()) {
            $tName = $r[0];
            $found[] = $tName;
            
            // Get count for key tables
            if (in_array($tName, $expected)) {
                $cRes = $conn->query("SELECT COUNT(*) FROM `$tName`");
                if ($cRes) {
                    $results['database']['table_counts'][$tName] = (int)$cRes->fetch_row()[0];
                }
            }
        }
        $results['database']['tables'] = $found;
        $missing = array_diff($expected, $found);
        $results['database']['missing_tables'] = array_values($missing);
        $results['database']['schema_healthy'] = empty($missing);

        // Registered users inspection (RESTRICTED TO AUTHENTICATED ADMINS ONLY)
        $isAdmin = !empty($_SESSION['reunite_admin_auth']) && $_SESSION['reunite_admin_auth'] === true;
        if ($isAdmin) {
            $uList = [];
            $uRes = $conn->query("SELECT user_id, full_name, pin, role, status FROM users LIMIT 10");
            if ($uRes) {
                while ($u = $uRes->fetch_assoc()) {
                    $uid = (int)$u['user_id'];
                    $lCnt = 0; $fCnt = 0; $mCnt = 0;
                    $lQ = $conn->query("SELECT COUNT(*) FROM lost_reports WHERE user_id = $uid");
                    if ($lQ) $lCnt = (int)$lQ->fetch_row()[0];
                    $fQ = $conn->query("SELECT COUNT(*) FROM found_reports WHERE user_id = $uid");
                    if ($fQ) $fCnt = (int)$fQ->fetch_row()[0];
                    $mQ = $conn->query("SELECT COUNT(DISTINCT m.id) FROM matches m LEFT JOIN lost_reports lr ON m.lost_report_id = lr.id LEFT JOIN found_reports fr ON m.found_report_id = fr.id WHERE lr.user_id = $uid OR fr.user_id = $uid");
                    if ($mQ) $mCnt = (int)$mQ->fetch_row()[0];

                    $uList[] = [
                        'user_id' => $uid,
                        'full_name' => $u['full_name'],
                        'pin' => $u['pin'],
                        'role' => $u['role'],
                        'status' => $u['status'],
                        'profile_numbers' => [
                            'reports_filed' => $lCnt + $fCnt,
                            'lost_count' => $lCnt,
                            'found_count' => $fCnt,
                            'ai_matches' => $mCnt
                        ]
                    ];
                }
            }
            $results['database']['users_profile_verification'] = $uList;
        }

        // Optional: Seed action if requested via ?seed=1 or ?action=seed (ADMIN ONLY with parameterized query)
        if ($isAdmin && (isset($_GET['seed']) || (isset($_GET['action']) && $_GET['action'] === 'seed'))) {
            $seedPin = trim($_GET['pin'] ?? '24155-cm-002');
            $uStmt = $conn->prepare("SELECT user_id FROM `users` WHERE pin = ? LIMIT 1");
            $targetUid = null;
            if ($uStmt) {
                $uStmt->bind_param("s", $seedPin);
                $uStmt->execute();
                $uRes = $uStmt->get_result();
                if ($uRow = $uRes->fetch_assoc()) {
                    $targetUid = (int)$uRow['user_id'];
                }
                $uStmt->close();
            }
            if ($targetUid) {
                
                // 1. Insert lost report if none exists
                $lCheck = $conn->query("SELECT id FROM `lost_reports` WHERE user_id = $targetUid LIMIT 1");
                $lostId = null;
                if ($lCheck && $lCheck->num_rows === 0) {
                    $lStmt = $conn->prepare("INSERT INTO `lost_reports` (user_id, category, title, description, location, date_lost, status) VALUES (?, 'Electronics', 'HP Pavilion 15 Laptop (Silver)', 'Left in 2nd floor library study area. Silver aluminum finish with black protective sleeve.', 'Campus Central Library', CURDATE(), 'active')");
                    if ($lStmt) {
                        $lStmt->bind_param("i", $targetUid);
                        $lStmt->execute();
                        $lostId = $lStmt->insert_id;
                        $lStmt->close();
                    }
                } else {
                    $lostId = (int)$lCheck->fetch_assoc()['id'];
                }

                // 2. Insert found report if none exists
                $fCheck = $conn->query("SELECT id FROM `found_reports` LIMIT 1");
                $foundId = null;
                if ($fCheck && $fCheck->num_rows === 0) {
                    $fStmt = $conn->prepare("INSERT INTO `found_reports` (user_id, category, title, description, location, date_found, status) VALUES (?, 'Electronics', 'Silver HP Laptop in Black Pouch', 'Found unattended laptop on study desk in 2nd floor library hall.', 'Central Library Hall', CURDATE(), 'active')");
                    if ($fStmt) {
                        $fStmt->bind_param("i", $targetUid);
                        $fStmt->execute();
                        $foundId = $fStmt->insert_id;
                        $fStmt->close();
                    }
                } else {
                    $foundId = (int)$fCheck->fetch_assoc()['id'];
                }

                // 3. Insert AI match if both exist
                if ($lostId && $foundId) {
                    $mCheck = $conn->query("SELECT id FROM `matches` WHERE lost_report_id = $lostId AND found_report_id = $foundId LIMIT 1");
                    if ($mCheck && $mCheck->num_rows === 0) {
                        $mStmt = $conn->prepare("INSERT INTO `matches` (lost_report_id, found_report_id, similarity_score, status, return_type) VALUES (?, ?, 94.50, 'pending', 'hand_on_hand')");
                        if ($mStmt) {
                            $mStmt->bind_param("ii", $lostId, $foundId);
                            $mStmt->execute();
                            $mStmt->close();
                        }
                    }
                }

                $results['database']['seed_result'] = [
                    'status' => 'success',
                    'message' => "Seeded sample lost report (ID #$lostId), found report (ID #$foundId), and 94.5% AI match for user $targetUid ($seedPin)!",
                    'lost_id' => $lostId,
                    'found_id' => $foundId
                ];
            }
        }

        $results['database']['users_profile_verification'] = $uList;
    }
} else {
    $results['database']['error'] = !empty($db_connection_error) ? $db_connection_error : ($conn ? $conn->connect_error : 'Connection null');
}

// 2. Verify AI Microservice
$aiUrl = $results['ai_microservice']['endpoint'] . '/status';
$start = microtime(true);
$ch = curl_init($aiUrl);
if ($ch) {
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $results['ai_microservice']['latency_ms'] = round((microtime(true) - $start) * 1000, 1);
    if ($httpCode === 200 && $response) {
        $results['ai_microservice']['status'] = 'online';
        $results['ai_microservice']['details'] = json_decode($response, true);
    } else {
        $results['ai_microservice']['http_code'] = $httpCode;
    }
}

// 3. Overall Verdict
$dbOk = ($results['database']['status'] === 'connected' && ($results['database']['schema_healthy'] ?? false));
$aiOk = ($results['ai_microservice']['status'] === 'online');

$results['healthy'] = $dbOk && $aiOk;
$results['verdict'] = $results['healthy'] ? 'ALL_SYSTEMS_OPERATIONAL' : ($dbOk ? 'DB_OK_AI_ISSUES' : 'DATABASE_DISCONNECTED');

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
