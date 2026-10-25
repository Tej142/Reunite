<?php
/** Private, session-authorized lookup over precomputed MySQL matches. */
require_once __DIR__ . '/../../Backend/config/config.php';
require_once __DIR__ . '/../../Backend/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function searchMatchesReply($ok, $message, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $ok, 'message' => $message, 'data' => $data]);
    exit;
}
function searchMatchesColumns($db, $table) {
    $columns = [];
    $res = @$db->query("SHOW COLUMNS FROM `$table`");
    if ($res) while ($row = $res->fetch_assoc()) $columns[] = strtolower($row['Field']);
    return $columns;
}
function searchMatchesLog($uid, $success) {
    if (function_exists('logAccess')) logAccess($uid, $success ? 'Private Match Lookup' : 'Private Match Lookup Failed', $success ? 'Eligible results returned' : 'Invalid, inaccessible, or empty report lookup');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') searchMatchesReply(false, 'Method not allowed.', [], 405);
$csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrf)) searchMatchesReply(false, 'Request could not be verified. Refresh the page and try again.', [], 403);
$userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$userId) searchMatchesReply(false, 'Your session has expired. Please sign in again.', [], 401);
if (empty($conn)) searchMatchesReply(false, 'Search is temporarily unavailable.', [], 503);
$payload = json_decode(file_get_contents('php://input'), true);
$reportCode = strtoupper(trim((string)($payload['report_id'] ?? '')));
$validFormat = preg_match('/^RL-([0-9]{1,10})$/', $reportCode, $idMatch) === 1;
$ipHash = hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? '', ENCRYPTION_KEY);
$attemptsTable = 'search_lookup_attempts';
$windowMinutes = max(1, (int)SEARCH_LOOKUP_WINDOW_MINUTES);
$cooldownMinutes = max(1, (int)SEARCH_LOOKUP_COOLDOWN_MINUTES);
$attemptTableCheck = @$conn->query("SHOW TABLES LIKE '$attemptsTable'");
if ($attemptTableCheck && $attemptTableCheck->num_rows > 0) {
    $rateSql = "SELECT COUNT(*) AS recent_count FROM `$attemptsTable` WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$windowMinutes} MINUTE) AND (user_id = ? OR ip_hash = ?)";
    $rate = $conn->prepare($rateSql);
    if ($rate) {
        $rate->bind_param('is', $userId, $ipHash);
        $rate->execute(); $rateRow = $rate->get_result()->fetch_assoc(); $rate->close();
        if ((int)($rateRow['recent_count'] ?? 0) >= SEARCH_LOOKUP_FAIL_LIMIT) {
            $blockedLog = $conn->prepare("INSERT INTO `$attemptsTable` (user_id, ip_hash) VALUES (?, ?)");
            if ($blockedLog) { $blockedLog->bind_param('is', $userId, $ipHash); $blockedLog->execute(); $blockedLog->close(); }
            if (function_exists('logAccess')) logAccess($userId, 'Private Match Lookup Rate Limited', 'Account/IP cooldown applied for ' . $cooldownMinutes . ' minutes');
            searchMatchesReply(false, 'No matches are available for that report.', ['matches' => []], 429);
        }
    }
    if (random_int(1, 100) === 1) @$conn->query("DELETE FROM `$attemptsTable` WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY) LIMIT 5000");
}
$failed = function () use ($conn, $attemptsTable, $userId, $ipHash) {
    $table = @$conn->query("SHOW TABLES LIKE '$attemptsTable'");
    if ($table && $table->num_rows > 0) {
        $s = $conn->prepare("INSERT INTO `$attemptsTable` (user_id, ip_hash) VALUES (?, ?)");
        if ($s) { $s->bind_param('is', $userId, $ipHash); $s->execute(); $s->close(); }
    }
    searchMatchesLog($userId, false);
};
if (!$validFormat) { $failed(); searchMatchesReply(true, 'No matches are available for that report.', ['matches' => []]); }

$lostCols = searchMatchesColumns($conn, 'lost_reports');
$matchCols = searchMatchesColumns($conn, 'matches');
$foundCols = searchMatchesColumns($conn, 'found_reports');
$lostPk = in_array('lost_id', $lostCols, true) ? 'lost_id' : 'id';
$ownerCol = in_array('user_id', $lostCols, true) ? 'user_id' : 'claimant_id';
$matchPk = in_array('match_id', $matchCols, true) ? 'match_id' : 'id';
$matchLost = in_array('lost_id', $matchCols, true) ? 'lost_id' : 'lost_report_id';
$matchFound = in_array('found_id', $matchCols, true) ? 'found_id' : 'found_report_id';
$scoreCol = in_array('match_percentage', $matchCols, true) ? 'match_percentage' : (in_array('similarity_score', $matchCols, true) ? 'similarity_score' : 'score');
$foundPk = in_array('found_id', $foundCols, true) ? 'found_id' : 'id';
$lostId = (int)$idMatch[1];
$ownerCheck = $conn->prepare("SELECT `$lostPk` FROM lost_reports WHERE `$lostPk` = ? AND `$ownerCol` = ? LIMIT 1");
if (!$ownerCheck) searchMatchesReply(false, 'Search is temporarily unavailable.', [], 503);
$ownerCheck->bind_param('ii', $lostId, $userId); $ownerCheck->execute(); $owned = $ownerCheck->get_result()->fetch_assoc(); $ownerCheck->close();
if (!$owned) { $failed(); searchMatchesReply(true, 'No matches are available for that report.', ['matches' => []]); }

$thumbCol = in_array('privacy_thumbnail_path', $foundCols, true) ? 'f.privacy_thumbnail_path' : "''";
$locationCol = in_array('location', $foundCols, true) ? 'f.location' : "''";
$foundStatus = in_array('status', $foundCols, true) ? "LOWER(COALESCE(f.status, 'active'))" : "'active'";
$sql = "SELECT m.`$matchPk` AS match_id, m.`$matchFound` AS found_id, m.`$scoreCol` AS score, $thumbCol AS safe_thumbnail, $locationCol AS broad_location FROM matches m JOIN found_reports f ON f.`$foundPk` = m.`$matchFound` WHERE m.`$matchLost` = ? AND LOWER(COALESCE(m.status, 'pending')) IN ('pending','new','pending_founder_approval','rejected') AND $foundStatus IN ('active','pending','matched') ORDER BY m.`$scoreCol` DESC LIMIT 30";
$stmt = $conn->prepare($sql);
if (!$stmt) searchMatchesReply(false, 'Search is temporarily unavailable.', [], 503);
$stmt->bind_param('i', $lostId); $stmt->execute(); $result = $stmt->get_result(); $matches = [];
while ($row = $result->fetch_assoc()) {
    $score = (float)$row['score'];
    if ($score > 0 && $score <= 1) $score *= 100;
    $rawLocation = strtolower((string)$row['broad_location']);
    $area = 'Campus area';
    foreach (['library'=>'Library area','cafeteria'=>'Cafeteria area','canteen'=>'Cafeteria area','bus'=>'Near Bus Stand','auditorium'=>'Auditorium area','sports'=>'Sports complex area','parking'=>'Parking area','lab'=>'Lab area','block'=>'Academic block area'] as $needle => $label) {
        if (strpos($rawLocation, $needle) !== false) { $area = $label; break; }
    }
    $safeImage = '';
    $candidate = (string)($row['safe_thumbnail'] ?? '');
    if ($candidate !== '' && preg_match('/^(media_vault\/safe_thumbnails\/|uploads\/safe_thumbnails\/)[A-Za-z0-9._-]+$/', $candidate)) $safeImage = $candidate;
    $matches[] = ['match_id'=>(int)$row['match_id'], 'report_id'=>'RF-'.str_pad((string)$row['found_id'],5,'0',STR_PAD_LEFT), 'score'=>round($score), 'thumbnail'=>$safeImage, 'location'=>$area];
}
$stmt->close();
if (!$matches) {
    $table = @$conn->query("SHOW TABLES LIKE '$attemptsTable'");
    if ($table && $table->num_rows > 0) {
        $failedResult = $conn->prepare("INSERT INTO `$attemptsTable` (user_id, ip_hash) VALUES (?, ?)");
        if ($failedResult) { $failedResult->bind_param('is', $userId, $ipHash); $failedResult->execute(); $failedResult->close(); }
    }
    searchMatchesLog($userId, false);
    searchMatchesReply(true, 'No matches are available for that report.', ['matches' => []]);
}
searchMatchesLog($userId, true);
searchMatchesReply(true, 'Possible matches loaded.', ['matches' => $matches]);
