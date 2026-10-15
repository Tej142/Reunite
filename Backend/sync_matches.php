<?php
/**
 * Reunite Retroactive Match Synchronizer & Health Engine
 * Scans all submitted reports, resolves status enums, calculates cross-similarity,
 * and generates matches and notifications for both users.
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';

global $conn;

if (!$conn) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection unavailable.'
    ], JSON_PRETTY_PRINT);
    exit;
}

// 1. Normalize report statuses in database ('Pending' or empty -> 'active')
$conn->query("UPDATE lost_reports SET status = 'active' WHERE status = 'Pending' OR status = '' OR status IS NULL");
$conn->query("UPDATE found_reports SET status = 'active' WHERE status = 'Pending' OR status = '' OR status IS NULL");

// 2. Fetch all active found reports
$foundColsRes = $conn->query("SHOW COLUMNS FROM found_reports");
$fCols = [];
if ($foundColsRes) {
    while ($fc = $foundColsRes->fetch_assoc()) $fCols[] = strtolower($fc['Field']);
}
$fIdCol = in_array('id', $fCols) ? 'id' : 'found_id';
$fUserCol = in_array('user_id', $fCols) ? 'user_id' : (in_array('finder_id', $fCols) ? 'finder_id' : 'user_id');

$foundReports = [];
$resF = $conn->query("SELECT $fIdCol AS id, $fUserCol AS user_id, category, title, description, location, image_path, status FROM found_reports WHERE status = 'active'");
if ($resF) {
    while ($row = $resF->fetch_assoc()) {
        $foundReports[] = $row;
    }
}

$allCreatedMatches = [];

// 3. For each found report, run real-time matchmaking against all lost reports
foreach ($foundReports as $fReport) {
    $fId = (int)$fReport['id'];
    $fUserId = (int)$fReport['user_id'];
    $fCat = $fReport['category'] ?? '';
    $fTitle = $fReport['title'] ?? '';
    $fDesc = $fReport['description'] ?? '';
    $fLoc = $fReport['location'] ?? '';
    $fImg = $fReport['image_path'] ?? '';

    $matches = runRealtimeMatchmaking(
        $fId,
        'found',
        $fCat,
        $fTitle,
        $fDesc,
        $fLoc,
        $fImg,
        $fUserId
    );

    if (!empty($matches)) {
        foreach ($matches as $m) {
            $allCreatedMatches[] = $m;
        }
    }
}

// 4. Fetch count of all unread notifications in system
$notifCount = 0;
$nRes = $conn->query("SELECT COUNT(*) as cnt FROM notifications");
if ($nRes && ($nrow = $nRes->fetch_assoc())) {
    $notifCount = (int)$nrow['cnt'];
}

echo json_encode([
    'success' => true,
    'message' => 'Match synchronization completed successfully.',
    'active_found_reports_scanned' => count($foundReports),
    'new_matches_created' => count($allCreatedMatches),
    'total_notifications_in_db' => $notifCount,
    'matches' => $allCreatedMatches
], JSON_PRETTY_PRINT);
