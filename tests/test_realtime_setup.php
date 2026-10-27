<?php
/**
 * Test Real-Time Telemetry and System Diagnostics Endpoints
 */
session_start();
header('Content-Type: text/plain');

echo "=== REAL-TIME TELEMETRY & SYSTEM TEST SUITE ===\n\n";

// Set up Master Admin Session (Charan)
$_SESSION['reunite_admin_auth'] = true;
$_SESSION['admin_logged_in']   = true;
$_SESSION['admin_id']          = 6;
$_SESSION['reunite_admin_name']= 'Charan';
$_SESSION['username']          = 'charan';
$_SESSION['email']             = 'charan@reunite.site';
$_SESSION['role']              = 'master_admin';
$_SESSION['reunite_admin_role']= 'master_admin';
$_SESSION['admin_campus_id']   = null;
$_SESSION['admin_campus_name'] = 'All Campuses (Global)';
$_SESSION['admin_campus_code'] = 'ALL';

require_once __DIR__ . '/../Backend/config/config.php';
require_once __DIR__ . '/../Backend/functions.php';

// Test 1: Real-Time Telemetry Endpoint
$testMode = $_GET['test'] ?? 'telemetry';

if ($testMode === 'backfill') {
    echo "=== BACKFILLING CAMPUS_ID = 1 FOR SVGP ===\n";
    $conn->query("UPDATE found_reports SET campus_id = 1 WHERE campus_id IS NULL OR campus_id = 0");
    echo "found_reports backfilled: " . $conn->affected_rows . "\n";

    $conn->query("UPDATE lost_reports SET campus_id = 1 WHERE campus_id IS NULL OR campus_id = 0");
    echo "lost_reports backfilled: " . $conn->affected_rows . "\n";

    $conn->query("UPDATE matches SET campus_id = 1 WHERE campus_id IS NULL OR campus_id = 0");
    echo "matches backfilled: " . $conn->affected_rows . "\n";

    $conn->query("UPDATE recovery_cases SET campus_id = 1 WHERE campus_id IS NULL OR campus_id = 0");
    echo "recovery_cases backfilled: " . $conn->affected_rows . "\n";

    $conn->query("UPDATE users SET campus_id = 1 WHERE campus_id IS NULL OR campus_id = 0");
    echo "users backfilled: " . $conn->affected_rows . "\n";

    echo "\n=== COUNTS FOR CAMPUS #1 (SVGP) ===\n";
    echo "Users: " . $conn->query("SELECT COUNT(*) as c FROM users WHERE campus_id = 1")->fetch_assoc()['c'] . "\n";
    echo "Lost Reports: " . $conn->query("SELECT COUNT(*) as c FROM lost_reports WHERE campus_id = 1")->fetch_assoc()['c'] . "\n";
    echo "Found Reports: " . $conn->query("SELECT COUNT(*) as c FROM found_reports WHERE campus_id = 1")->fetch_assoc()['c'] . "\n";
    echo "Matches: " . $conn->query("SELECT COUNT(*) as c FROM matches WHERE campus_id = 1")->fetch_assoc()['c'] . "\n";
    echo "Recovery Cases: " . $conn->query("SELECT COUNT(*) as c FROM recovery_cases WHERE campus_id = 1")->fetch_assoc()['c'] . "\n";
    exit;
}

if ($testMode === 'inspect_reports') {
    echo "=== INSPECTING REPORTS SCHEMA & DATA ===\n\n";
    
    echo "--- lost_reports columns ---\n";
    $lr = $conn->query("SHOW COLUMNS FROM lost_reports");
    if ($lr) while ($c = $lr->fetch_assoc()) echo " - {$c['Field']} ({$c['Type']})\n";
    
    echo "\n--- lost_reports sample rows ---\n";
    $ls = $conn->query("SELECT * FROM lost_reports LIMIT 5");
    if ($ls) while ($r = $ls->fetch_assoc()) echo json_encode($r) . "\n";

    echo "\n--- found_reports sample rows ---\n";
    $fs = $conn->query("SELECT * FROM found_reports LIMIT 5");
    if ($fs) while ($r = $fs->fetch_assoc()) echo json_encode($r) . "\n";

    echo "\n--- matches sample rows ---\n";
    $ms = $conn->query("SELECT * FROM matches LIMIT 5");
    if ($ms) while ($r = $ms->fetch_assoc()) echo json_encode($r) . "\n";

    echo "\n--- recovery_cases sample rows ---\n";
    $cs = $conn->query("SELECT * FROM recovery_cases LIMIT 5");
    if ($cs) while ($r = $cs->fetch_assoc()) echo json_encode($r) . "\n";
    exit;
}

if ($testMode === 'campuses') {
    echo "Testing action=manage_campuses...\n";
    $cRes = $conn->query("SHOW COLUMNS FROM campuses");
    if ($cRes) {
        while ($col = $cRes->fetch_assoc()) {
            echo "Col: {$col['Field']} ({$col['Type']})\n";
        }
    } else {
        echo "campuses table error: " . $conn->error . "\n";
    }

    echo "--- found_reports columns ---\n";
    $fr = $conn->query("SHOW COLUMNS FROM found_reports");
    if ($fr) while ($c = $fr->fetch_assoc()) echo " - {$c['Field']}\n";
    
    // Add campus_id if missing
    $cCheck = $conn->query("SHOW COLUMNS FROM found_reports LIKE 'campus_id'");
    if ($cCheck && $cCheck->num_rows === 0) {
        $alterRes = $conn->query("ALTER TABLE found_reports ADD COLUMN campus_id INT(11) NULL DEFAULT NULL, ADD INDEX idx_found_campus (campus_id)");
        echo "ALTER found_reports result: " . ($alterRes ? "SUCCESS" : "ERROR: " . $conn->error) . "\n";
    }

    $lCheck = $conn->query("SHOW COLUMNS FROM lost_reports LIKE 'campus_id'");
    if ($lCheck && $lCheck->num_rows === 0) {
        $alterRes2 = $conn->query("ALTER TABLE lost_reports ADD COLUMN campus_id INT(11) NULL DEFAULT NULL, ADD INDEX idx_lost_campus (campus_id)");
        echo "ALTER lost_reports result: " . ($alterRes2 ? "SUCCESS" : "ERROR: " . $conn->error) . "\n";
    }

    // Now test manage_campuses query directly from admin_api
    $_GET['action'] = 'manage_campuses';
    ob_start();
    require __DIR__ . '/../Backend/admin_api.php';
    echo ob_get_clean();
    exit;
}

if ($testMode === 'system') {
    echo "Testing action=system_test...\n";
    $_GET['action'] = 'system_test';
    ob_start();
    require __DIR__ . '/../Backend/admin_api.php';
    echo ob_get_clean();
    exit;
}

echo "1. Testing action=realtime_telemetry...\n";
$_GET['action'] = 'realtime_telemetry';
ob_start();
require __DIR__ . '/../Backend/admin_api.php';
echo ob_get_clean();
exit;

$telemetryJson = json_decode($telemetryOut, true);
if ($telemetryJson && !empty($telemetryJson['success'])) {
    echo "  [PASS] action=realtime_telemetry succeeded!\n";
    echo "         - Total Users: " . ($telemetryJson['stats']['total_users'] ?? 'N/A') . "\n";
    echo "         - Active Lost: " . ($telemetryJson['stats']['active_lost'] ?? 'N/A') . "\n";
    echo "         - Active Found: " . ($telemetryJson['stats']['active_found'] ?? 'N/A') . "\n";
    echo "         - Total Matches: " . ($telemetryJson['stats']['total_matches'] ?? 'N/A') . "\n";
    echo "         - Pending Claims: " . ($telemetryJson['stats']['pending_claims'] ?? 'N/A') . "\n";
    echo "         - Max IDs: " . json_encode($telemetryJson['max_ids'] ?? []) . "\n";
    echo "         - DB Status: " . ($telemetryJson['db_status'] ?? 'N/A') . "\n";
} else {
    echo "  [FAIL] action=realtime_telemetry failed. Output:\n";
    echo "  $telemetryOut\n";
}

echo "\n=== SUITE EXECUTION COMPLETED ===\n";
