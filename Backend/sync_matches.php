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

$diagnostics = [];

// 1. Check raw table counts
$resLostCount = $conn->query("SELECT COUNT(*) as cnt FROM lost_reports");
$lostCount = $resLostCount ? (int)$resLostCount->fetch_assoc()['cnt'] : 0;

$resFoundCount = $conn->query("SELECT COUNT(*) as cnt FROM found_reports");
$foundCount = $resFoundCount ? (int)$resFoundCount->fetch_assoc()['cnt'] : 0;

$diagnostics['raw_lost_count'] = $lostCount;
$diagnostics['raw_found_count'] = $foundCount;

// 2. Normalize statuses: set anything not claimed/closed/matched to 'active'
@$conn->query("UPDATE lost_reports SET status = 'active' WHERE status IS NULL OR status = '' OR status = 'Pending'");
@$conn->query("UPDATE found_reports SET status = 'active' WHERE status IS NULL OR status = '' OR status = 'Pending'");

// 3. Fetch all found reports using SELECT * so no column errors are possible
$foundReports = [];
$resF = $conn->query("SELECT * FROM found_reports");
if ($resF) {
    while ($row = $resF->fetch_assoc()) {
        $foundReports[] = $row;
    }
} else {
    $diagnostics['found_query_error'] = $conn->error;
}

// 4. Fetch all lost reports using SELECT *
$lostReports = [];
$resL = $conn->query("SELECT * FROM lost_reports");
if ($resL) {
    while ($row = $resL->fetch_assoc()) {
        $lostReports[] = $row;
    }
} else {
    $diagnostics['lost_query_error'] = $conn->error;
}

$allCreatedMatches = [];

// 5. Run matching for every found report
foreach ($foundReports as $fReport) {
    $fId = (int)($fReport['id'] ?? $fReport['found_id'] ?? 0);
    $fUserId = (int)($fReport['user_id'] ?? $fReport['finder_id'] ?? 0);
    $fCat = $fReport['category'] ?? '';
    $fTitle = $fReport['title'] ?? '';
    $fDesc = $fReport['description'] ?? '';
    $fLoc = $fReport['location'] ?? '';
    $fImg = $fReport['image_path'] ?? $fReport['image_url'] ?? '';

    if ($fId > 0) {
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
}

// 6. Notifications count
$notifCount = 0;
$nRes = $conn->query("SELECT COUNT(*) as cnt FROM notifications");
if ($nRes && ($nrow = $nRes->fetch_assoc())) {
    $notifCount = (int)$nrow['cnt'];
}

// 7. Recent notifications in DB
$recentNotifs = [];
$rnRes = $conn->query("SELECT notification_id, user_id, title, message, created_at FROM notifications ORDER BY notification_id DESC LIMIT 5");
if ($rnRes) {
    while ($rn = $rnRes->fetch_assoc()) {
        $recentNotifs[] = $rn;
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Match synchronization completed successfully.',
    'diagnostics' => $diagnostics,
    'active_found_reports_scanned' => count($foundReports),
    'active_lost_reports_scanned' => count($lostReports),
    'new_matches_created' => count($allCreatedMatches),
    'total_notifications_in_db' => $notifCount,
    'matches' => $allCreatedMatches,
    'recent_notifications' => $recentNotifs
], JSON_PRETTY_PRINT);
