<?php
/**
 * DIAGNOSTIC: Test report insertion directly
 * Upload this to your live server and visit it in the browser
 * DELETE THIS FILE AFTER DEBUGGING
 */
header('Content-Type: application/json');

// Load config & DB connection
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';

$diagnostics = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'steps' => []
];

// Step 1: Check DB connection
if (!$conn) {
    $diagnostics['steps'][] = ['step' => 'DB Connection', 'status' => 'FAILED', 'error' => $db_connection_error ?? 'Unknown'];
    echo json_encode($diagnostics, JSON_PRETTY_PRINT);
    exit;
}
$diagnostics['steps'][] = ['step' => 'DB Connection', 'status' => 'OK', 'host' => DB_HOST, 'db' => DB_NAME];

// Step 2: Check if users table has any rows
$userCount = $conn->query("SELECT COUNT(*) as cnt FROM users");
$uc = $userCount ? $userCount->fetch_assoc()['cnt'] : 'QUERY FAILED';
$diagnostics['steps'][] = ['step' => 'Users Count', 'status' => 'OK', 'count' => $uc];

// Step 3: Get first valid user_id
$firstUser = $conn->query("SELECT user_id, pin, full_name FROM users WHERE status = 'active' ORDER BY user_id ASC LIMIT 1");
$testUserId = null;
if ($firstUser && $row = $firstUser->fetch_assoc()) {
    $testUserId = (int)$row['user_id'];
    $diagnostics['steps'][] = ['step' => 'First Active User', 'status' => 'OK', 'user_id' => $testUserId, 'pin' => $row['pin'], 'name' => $row['full_name']];
} else {
    $diagnostics['steps'][] = ['step' => 'First Active User', 'status' => 'FAILED', 'error' => 'No active users found'];
    echo json_encode($diagnostics, JSON_PRETTY_PRINT);
    exit;
}

// Step 4: Check lost_reports table structure
$describe = $conn->query("DESCRIBE lost_reports");
$columns = [];
if ($describe) {
    while ($col = $describe->fetch_assoc()) {
        $columns[] = $col['Field'] . ' (' . $col['Type'] . ')' . ($col['Null'] === 'NO' ? ' NOT NULL' : ' NULLABLE') . ($col['Default'] !== null ? ' DEFAULT=' . $col['Default'] : '');
    }
}
$diagnostics['steps'][] = ['step' => 'lost_reports Schema', 'status' => 'OK', 'columns' => $columns];

// Step 5: Check foreign key constraints
$fkQuery = $conn->query("SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_NAME = 'lost_reports' AND TABLE_SCHEMA = '" . DB_NAME . "' AND REFERENCED_TABLE_NAME IS NOT NULL");
$fks = [];
if ($fkQuery) {
    while ($fk = $fkQuery->fetch_assoc()) {
        $fks[] = $fk;
    }
}
$diagnostics['steps'][] = ['step' => 'Foreign Keys on lost_reports', 'status' => 'OK', 'constraints' => $fks];

// Step 6: Try ACTUAL test insert
$testTitle = 'DIAG_TEST_' . time();
$testDesc = 'Diagnostic test insert - safe to delete';
$testCategory = 'Test';
$testLocation = 'Campus';
$testDate = date('Y-m-d');
$testTime = date('H:i:s');
$testStatus = 'active';

$stmt = $conn->prepare("INSERT INTO lost_reports (user_id, category, title, description, location, date_lost, lost_time, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    $diagnostics['steps'][] = ['step' => 'Prepare INSERT', 'status' => 'FAILED', 'error' => $conn->error, 'errno' => $conn->errno];
    echo json_encode($diagnostics, JSON_PRETTY_PRINT);
    exit;
}
$diagnostics['steps'][] = ['step' => 'Prepare INSERT', 'status' => 'OK'];

$nullImg = null;
$stmt->bind_param("issssssss", $testUserId, $testCategory, $testTitle, $testDesc, $testLocation, $testDate, $testTime, $nullImg, $testStatus);

$execResult = $stmt->execute();
if (!$execResult) {
    $diagnostics['steps'][] = [
        'step' => 'Execute INSERT',
        'status' => 'FAILED',
        'error' => $stmt->error,
        'errno' => $stmt->errno,
        'user_id_used' => $testUserId
    ];
} else {
    $newId = $stmt->insert_id;
    $diagnostics['steps'][] = [
        'step' => 'Execute INSERT',
        'status' => 'SUCCESS',
        'inserted_id' => $newId,
        'user_id_used' => $testUserId
    ];

    // Step 7: Verify the row exists
    $verify = $conn->query("SELECT id, user_id, title, created_at FROM lost_reports WHERE id = $newId");
    if ($verify && $vRow = $verify->fetch_assoc()) {
        $diagnostics['steps'][] = ['step' => 'Verify INSERT', 'status' => 'CONFIRMED', 'row' => $vRow];
    } else {
        $diagnostics['steps'][] = ['step' => 'Verify INSERT', 'status' => 'ROW NOT FOUND AFTER INSERT'];
    }

    // Cleanup: delete the test row
    $conn->query("DELETE FROM lost_reports WHERE id = $newId AND title = '$testTitle'");
    $diagnostics['steps'][] = ['step' => 'Cleanup test row', 'status' => 'DELETED'];
}
$stmt->close();

// Step 8: Check session state (simulating what reports.php would see)
require_once __DIR__ . '/../Frontend/includes/auth.php';
$sessionUserId = $_SESSION['user_id'] ?? 'NOT SET';
$sessionPin = $_SESSION['pin'] ?? 'NOT SET';
$cookiePin = $_COOKIE['reunite_remembered_pin'] ?? 'NOT SET';
$cookieAuth = isset($_COOKIE['reunite_auth_token']) ? 'PRESENT' : 'NOT SET';

$diagnostics['steps'][] = [
    'step' => 'Session/Auth State',
    'session_user_id' => $sessionUserId,
    'session_pin' => $sessionPin,
    'cookie_remembered_pin' => $cookiePin,
    'cookie_auth_token' => $cookieAuth
];

// Step 9: Simulate the EXACT same user resolution logic from reports.php
$simUserId = $_SESSION['user_id'] ?? ($_SESSION['user']['user_id'] ?? null);

if (empty($simUserId) && !empty($_SESSION['pin'])) {
    $uStmt = $conn->prepare("SELECT user_id FROM users WHERE pin = ? LIMIT 1");
    if ($uStmt) {
        $uStmt->bind_param("s", $_SESSION['pin']);
        $uStmt->execute();
        $uRes = $uStmt->get_result();
        if ($uRow = $uRes->fetch_assoc()) {
            $simUserId = (int)$uRow['user_id'];
        }
        $uStmt->close();
    }
}

if (empty($simUserId) && !empty($_COOKIE['reunite_remembered_pin'])) {
    $remPin = $_COOKIE['reunite_remembered_pin'];
    $uStmt = $conn->prepare("SELECT user_id FROM users WHERE pin = ? LIMIT 1");
    if ($uStmt) {
        $uStmt->bind_param("s", $remPin);
        $uStmt->execute();
        $uRes = $uStmt->get_result();
        if ($uRow = $uRes->fetch_assoc()) {
            $simUserId = (int)$uRow['user_id'];
        }
        $uStmt->close();
    }
}

// Validate against users table
$simValid = false;
if (!empty($simUserId)) {
    $chk = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
    if ($chk) {
        $chk->bind_param("i", $simUserId);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            $simValid = true;
        }
        $chk->close();
    }
}

if (!$simValid) {
    $fb = $conn->query("SELECT user_id, pin FROM users WHERE pin = '24155-cm-002' OR status = 'active' ORDER BY user_id ASC LIMIT 1");
    if ($fb && ($fRow = $fb->fetch_assoc())) {
        $simUserId = (int)$fRow['user_id'];
        $simValid = true;
    }
}

$diagnostics['steps'][] = [
    'step' => 'Simulated reports.php user_id resolution',
    'resolved_user_id' => $simUserId,
    'is_valid' => $simValid
];

$diagnostics['overall'] = 'DIAGNOSTIC COMPLETE';
echo json_encode($diagnostics, JSON_PRETTY_PRINT);
