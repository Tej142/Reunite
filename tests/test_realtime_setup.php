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
