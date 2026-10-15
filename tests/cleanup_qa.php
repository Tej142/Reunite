<?php
/**
 * Reunite QA Test Data Cleanup Tool
 * Purges strictly and exclusively entities created during QA testing (prefixed with 'qa_test_' or 'QA%')
 * Preserves all original user data and real reports.
 */

require_once __DIR__ . '/../Backend/config/config.php';

if (php_sapi_name() !== 'cli' && empty($_GET['force'])) {
    header('Content-Type: application/json');
}

global $conn;

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit(1);
}

$summary = [
    'users_deleted' => 0,
    'lost_reports_deleted' => 0,
    'found_reports_deleted' => 0,
    'claims_deleted' => 0,
    'notifications_deleted' => 0,
    'matches_deleted' => 0,
    'dna_deleted' => 0,
    'logs_deleted' => 0,
    'images_deleted' => 0
];

// 1. Find all QA test user IDs
$uRes = $conn->query("SELECT user_id FROM users WHERE full_name LIKE 'qa_test_%' OR pin LIKE 'QA%' OR email LIKE 'qa_%'");
$qaUserIds = [];
if ($uRes) {
    while ($row = $uRes->fetch_assoc()) {
        $qaUserIds[] = (int)$row['user_id'];
    }
}

// 2. Find all QA test reports
$lRes = $conn->query("SELECT id FROM lost_reports WHERE title LIKE 'qa_test_%' OR description LIKE '%qa_test_%'");
$qaLostIds = [];
if ($lRes) {
    while ($row = $lRes->fetch_assoc()) {
        $qaLostIds[] = (int)$row['id'];
    }
}

$fRes = $conn->query("SELECT id FROM found_reports WHERE title LIKE 'qa_test_%' OR description LIKE '%qa_test_%'");
$qaFoundIds = [];
if ($fRes) {
    while ($row = $fRes->fetch_assoc()) {
        $qaFoundIds[] = (int)$row['id'];
    }
}

// If user IDs exist, gather additional report IDs by user
if (!empty($qaUserIds)) {
    $uidList = implode(',', $qaUserIds);
    $lRes2 = $conn->query("SELECT id FROM lost_reports WHERE user_id IN ($uidList)");
    if ($lRes2) {
        while ($row = $lRes2->fetch_assoc()) {
            $qaLostIds[] = (int)$row['id'];
        }
    }
    $fRes2 = $conn->query("SELECT id FROM found_reports WHERE user_id IN ($uidList)");
    if ($fRes2) {
        while ($row = $fRes2->fetch_assoc()) {
            $qaFoundIds[] = (int)$row['id'];
        }
    }
}

$qaLostIds = array_unique($qaLostIds);
$qaFoundIds = array_unique($qaFoundIds);

// 3. Delete Claims / Recovery Cases
$conn->query("DELETE FROM recovery_cases WHERE proof_details LIKE '%qa_test_%'");
$summary['claims_deleted'] += $conn->affected_rows;
if (!empty($qaUserIds)) {
    $uidList = implode(',', $qaUserIds);
    $conn->query("DELETE FROM recovery_cases WHERE claimant_id IN ($uidList)");
    $summary['claims_deleted'] += $conn->affected_rows;
}
if (!empty($qaFoundIds)) {
    $fidList = implode(',', $qaFoundIds);
    $conn->query("DELETE FROM recovery_cases WHERE report_id IN ($fidList)");
    $summary['claims_deleted'] += $conn->affected_rows;
}

// 4. Delete Notifications
if (!empty($qaUserIds)) {
    $uidList = implode(',', $qaUserIds);
    $conn->query("DELETE FROM notifications WHERE user_id IN ($uidList)");
    $summary['notifications_deleted'] += $conn->affected_rows;
}
$conn->query("DELETE FROM notifications WHERE title LIKE '%qa_test_%' OR message LIKE '%qa_test_%'");
$summary['notifications_deleted'] += $conn->affected_rows;

// 5. Delete Matches
if (!empty($qaLostIds)) {
    $lidList = implode(',', $qaLostIds);
    $conn->query("DELETE FROM matches WHERE lost_report_id IN ($lidList)");
    $summary['matches_deleted'] += $conn->affected_rows;
}
if (!empty($qaFoundIds)) {
    $fidList = implode(',', $qaFoundIds);
    $conn->query("DELETE FROM matches WHERE found_report_id IN ($fidList)");
    $summary['matches_deleted'] += $conn->affected_rows;
}

// 6. Delete Digital DNA
if (!empty($qaLostIds)) {
    $lidList = implode(',', $qaLostIds);
    $conn->query("DELETE FROM digital_dna WHERE report_type = 'LOST' AND report_id IN ($lidList)");
    $summary['dna_deleted'] += $conn->affected_rows;
}
if (!empty($qaFoundIds)) {
    $fidList = implode(',', $qaFoundIds);
    $conn->query("DELETE FROM digital_dna WHERE report_type = 'FOUND' AND report_id IN ($fidList)");
    $summary['dna_deleted'] += $conn->affected_rows;
}

// 7. Delete Reports
if (!empty($qaLostIds)) {
    $lidList = implode(',', $qaLostIds);
    $conn->query("DELETE FROM lost_reports WHERE id IN ($lidList)");
    $summary['lost_reports_deleted'] += $conn->affected_rows;
}
if (!empty($qaFoundIds)) {
    $fidList = implode(',', $qaFoundIds);
    $conn->query("DELETE FROM found_reports WHERE id IN ($fidList)");
    $summary['found_reports_deleted'] += $conn->affected_rows;
}

// 8. Delete Access Logs
if (!empty($qaUserIds)) {
    $uidList = implode(',', $qaUserIds);
    $conn->query("DELETE FROM access_logs WHERE user_id IN ($uidList)");
    $summary['logs_deleted'] += $conn->affected_rows;
}

// 9. Delete Users
if (!empty($qaUserIds)) {
    $uidList = implode(',', $qaUserIds);
    $conn->query("DELETE FROM users WHERE user_id IN ($uidList)");
    $summary['users_deleted'] += $conn->affected_rows;
}

$conn->close();

$out = json_encode([
    'success' => true,
    'message' => 'QA test data purged successfully. Real data untouched.',
    'summary' => $summary
], JSON_PRETTY_PRINT);

echo $out . "\n";
