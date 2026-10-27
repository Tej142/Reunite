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
