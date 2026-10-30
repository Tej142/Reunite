<?php
/**
 * Real-Time Lost Report ID Tracker & Private Match Search
 * Connected directly to MySQL database in real time.
 */
require_once __DIR__ . '/../../Backend/config/config.php';
require_once __DIR__ . '/../../Backend/functions.php';
require_once __DIR__ . '/../includes/auth.php';

init_session();
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    searchMatchesReply(false, 'Method not allowed.', [], 405);
}

if (empty($conn)) {
    searchMatchesReply(false, 'Database connection is temporarily unavailable.', [], 503);
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);
$reportCode = strtoupper(trim((string)($payload['report_id'] ?? ($_POST['report_id'] ?? ($_GET['report_id'] ?? '')))));
$reportCode = ltrim($reportCode, '#');

// Extract numeric ID from e.g. RL-00042, RL-42, 42
if (!preg_match('/^(?:RL-)?([0-9]{1,10})$/i', $reportCode, $idMatch)) {
    searchMatchesReply(false, 'Please enter a valid Lost Report ID such as RL-00042.', ['matches' => []], 400);
}

$lostId = (int)$idMatch[1];
$formattedReportId = 'RL-' . str_pad($lostId, 5, '0', STR_PAD_LEFT);

// Get column names to ensure schema resilience
$lostCols = searchMatchesColumns($conn, 'lost_reports');
$matchCols = searchMatchesColumns($conn, 'matches');
$foundCols = searchMatchesColumns($conn, 'found_reports');

$lostPk = in_array('id', $lostCols, true) ? 'id' : (in_array('lost_id', $lostCols, true) ? 'lost_id' : 'id');
$lostTitleCol = in_array('title', $lostCols, true) ? 'title' : "''";
$lostCatCol = in_array('category', $lostCols, true) ? 'category' : "''";
$lostDescCol = in_array('description', $lostCols, true) ? 'description' : "''";
$lostLocCol = in_array('location', $lostCols, true) ? 'location' : "''";
$lostStatusCol = in_array('status', $lostCols, true) ? 'status' : "'active'";
$lostImgCol = in_array('image_path', $lostCols, true) ? 'image_path' : "''";

$lostDateCol = in_array('date_lost', $lostCols, true) ? 'date_lost' : (in_array('lost_date', $lostCols, true) ? 'lost_date' : "''");

// 1. Query the live database for this lost report
$stmtLost = $conn->prepare("SELECT `$lostPk` AS id, $lostTitleCol AS title, $lostCatCol AS category, $lostDescCol AS description, $lostLocCol AS location, $lostStatusCol AS status, $lostImgCol AS image_path, $lostDateCol AS date_lost FROM lost_reports WHERE `$lostPk` = ? LIMIT 1");
if (!$stmtLost) {
    searchMatchesReply(false, 'Database query error.', [], 500);
}
$stmtLost->bind_param('i', $lostId);
$stmtLost->execute();
$lostReport = $stmtLost->get_result()->fetch_assoc();
$stmtLost->close();

if (!$lostReport) {
    searchMatchesReply(false, "No report found with ID {$formattedReportId} in the database. Please verify your Report ID.", ['matches' => []], 404);
}

$lostStatus = ucfirst(strtolower($lostReport['status'] ?? 'active'));

// 2. Query matches table linked to this lost report
$matchPk = in_array('id', $matchCols, true) ? 'id' : (in_array('match_id', $matchCols, true) ? 'match_id' : 'id');
$matchLost = in_array('lost_report_id', $matchCols, true) ? 'lost_report_id' : (in_array('lost_id', $matchCols, true) ? 'lost_id' : 'lost_report_id');
$matchFound = in_array('found_report_id', $matchCols, true) ? 'found_report_id' : (in_array('found_id', $matchCols, true) ? 'found_id' : 'found_report_id');
$scoreCol = in_array('similarity_score', $matchCols, true) ? 'similarity_score' : (in_array('match_percentage', $matchCols, true) ? 'match_percentage' : (in_array('score', $matchCols, true) ? 'score' : 'similarity_score'));
$foundPk = in_array('id', $foundCols, true) ? 'id' : (in_array('found_id', $foundCols, true) ? 'found_id' : 'id');

$thumbCol = in_array('image_path', $foundCols, true) ? 'f.image_path' : (in_array('privacy_thumbnail_path', $foundCols, true) ? 'f.privacy_thumbnail_path' : "''");
$locCol = in_array('location', $foundCols, true) ? 'f.location' : "''";
$titleCol = in_array('title', $foundCols, true) ? 'f.title' : "''";
$catCol = in_array('category', $foundCols, true) ? 'f.category' : "''";
$descCol = in_array('description', $foundCols, true) ? 'f.description' : "''";
$dateCol = in_array('date_found', $foundCols, true) ? 'f.date_found' : (in_array('found_date', $foundCols, true) ? 'f.found_date' : "''");

$sql = "SELECT 
            m.`$matchPk` AS match_id,
            m.`$matchFound` AS found_id,
            m.`$scoreCol` AS score,
            $thumbCol AS thumbnail,
            $locCol AS location,
            $titleCol AS title,
            $catCol AS category,
            $descCol AS description,
            $dateCol AS date_found,
            COALESCE(m.status, 'pending') AS match_status
        FROM matches m
        JOIN found_reports f ON f.`$foundPk` = m.`$matchFound`
        WHERE m.`$matchLost` = ?
          AND LOWER(COALESCE(m.status, 'pending')) NOT IN ('dismissed', 'cancelled')
        ORDER BY m.`$scoreCol` DESC
        LIMIT 30";

$stmt = $conn->prepare($sql);
$matches = [];
if ($stmt) {
    $stmt->bind_param('i', $lostId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rawScore = (float)$row['score'];
        if ($rawScore > 0 && $rawScore <= 1) $rawScore *= 100;

        // Calculate REAL-TIME AI similarity score
        $realtimeScore = calculateRealtimeSimilarityScore($lostReport, [
            'category' => $row['category'] ?? '',
            'title' => $row['title'] ?? '',
            'description' => $row['description'] ?? '',
            'location' => $row['location'] ?? '',
            'date_found' => $row['date_found'] ?? '',
            'thumbnail' => $row['thumbnail'] ?? ''
        ], $rawScore > 0 ? $rawScore : null);

        // Filter: Dismiss incompatible categories or matches below 40% threshold
        if ($realtimeScore < 40.0) {
            @$conn->query("UPDATE matches SET status = 'dismissed', `$scoreCol` = " . (float)$realtimeScore . " WHERE `$matchPk` = " . (int)$row['match_id']);
            continue;
        }

        // Update database if match had 0% or was flagged rejected so live DB stays fresh & synced
        if ($rawScore <= 0 || strtolower($row['match_status']) === 'rejected') {
            @$conn->query("UPDATE matches SET `$scoreCol` = " . (float)$realtimeScore . ", status = 'pending' WHERE `$matchPk` = " . (int)$row['match_id']);
        }

        $rawLoc = strtolower((string)($row['location'] ?? ''));
        $area = 'Campus area';
        foreach (['library'=>'Library area','cafeteria'=>'Cafeteria area','canteen'=>'Cafeteria area','bus'=>'Near Bus Stand','auditorium'=>'Auditorium area','sports'=>'Sports complex area','parking'=>'Parking area','lab'=>'Lab area','block'=>'Academic block area'] as $needle => $label) {
            if (strpos($rawLoc, $needle) !== false) { $area = $label; break; }
        }

        $safeImage = '';
        $cand = trim((string)($row['thumbnail'] ?? ''));
        if (!empty($cand)) {
            $safeImage = ltrim($cand, '/\\');
        }

        $itemTitle = (!empty($row['title']) && $row['title'] !== 'Found Item') ? $row['title'] : (!empty($row['category']) ? $row['category'] : 'Found Belonging');

        $matches[] = [
            'match_id' => (int)$row['match_id'],
            'report_id' => 'RF-' . str_pad((string)$row['found_id'], 5, '0', STR_PAD_LEFT),
            'score' => round($realtimeScore),
            'thumbnail' => $safeImage,
            'location' => $area,
            'title' => $itemTitle,
            'status' => 'pending'
        ];
    }
    $stmt->close();
}

// 3. Real-time dynamic candidate lookup from found_reports if no precomputed matches exist
if (empty($matches)) {
    $lostCat = trim((string)($lostReport['category'] ?? ''));
    $lostTitle = trim((string)($lostReport['title'] ?? ''));
    $lostDesc = trim((string)($lostReport['description'] ?? ''));

    $rawTokens = preg_split('/[\s,\.;:\-\/]+/', strtolower($lostTitle . ' ' . $lostCat . ' ' . $lostDesc));
    $stopWords = ['the','and','with','for','item','lost','found','campus','near','has','have','was','were','this','that','some','area'];
    $tokens = [];
    foreach ($rawTokens as $t) {
        $t = trim($t);
        if (strlen($t) >= 3 && !in_array($t, $stopWords, true)) {
            $tokens[$t] = true;
        }
    }
    $tokens = array_keys($tokens);

    $thumbColRaw = in_array('image_path', $foundCols, true) ? '`image_path`' : (in_array('privacy_thumbnail_path', $foundCols, true) ? '`privacy_thumbnail_path`' : "''");
    $locColRaw = in_array('location', $foundCols, true) ? '`location`' : "''";
    $titleColRaw = in_array('title', $foundCols, true) ? '`title`' : "''";
    $catColRaw = in_array('category', $foundCols, true) ? '`category`' : "''";
    $dateColRaw = in_array('date_found', $foundCols, true) ? '`date_found`' : (in_array('found_date', $foundCols, true) ? '`found_date`' : "''");

    $fSql = "SELECT `$foundPk` AS found_id, $titleColRaw AS title, $catColRaw AS category, $thumbColRaw AS thumbnail, $locColRaw AS location, $dateColRaw AS date_found, COALESCE(description, '') AS description 
             FROM found_reports 
             WHERE LOWER(COALESCE(status, 'active')) IN ('active', 'matched', 'pending')
             ORDER BY `$foundPk` DESC LIMIT 60";
    $fRes = $conn->query($fSql);
    if ($fRes) {
        while ($fRow = $fRes->fetch_assoc()) {
            $fCat = trim((string)($fRow['category'] ?? ''));
            $fTitle = trim((string)($fRow['title'] ?? ''));
            $fDesc = trim((string)($fRow['description'] ?? ''));

            $catMatch = (!empty($lostCat) && strcasecmp($lostCat, $fCat) === 0);
            $wordHits = 0;
            $fHaystack = strtolower($fTitle . ' ' . $fDesc . ' ' . $fCat);
            foreach ($tokens as $tok) {
                if (strpos($fHaystack, $tok) !== false) {
                    $wordHits++;
                }
            }

            $calcScore = calculateRealtimeSimilarityScore($lostReport, [
                'category' => $fRow['category'] ?? '',
                'title' => $fRow['title'] ?? '',
                'description' => $fRow['description'] ?? '',
                'location' => $fRow['location'] ?? '',
                'date_found' => $fRow['date_found'] ?? '',
                'thumbnail' => $fRow['thumbnail'] ?? ''
            ]);

            if ($calcScore >= 40.0 && ($catMatch || $wordHits >= 1 || $calcScore >= 60.0)) {
                $newMatchId = null;
                if (function_exists('insertMatchRecord')) {
                    $newMatchId = insertMatchRecord($lostId, (int)$fRow['found_id'], $calcScore, 'STRONG', ['category_match' => $catMatch, 'word_matches' => $wordHits]);
                }
                if (!$newMatchId) {
                    $ins = $conn->prepare("INSERT INTO matches (`$matchLost`, `$matchFound`, `$scoreCol`, status) VALUES (?, ?, ?, 'pending')");
                    if ($ins) {
                        $ins->bind_param('iid', $lostId, $fRow['found_id'], $calcScore);
                        $ins->execute();
                        $newMatchId = $ins->insert_id;
                        $ins->close();
                    }
                }
                if (!$newMatchId) $newMatchId = (int)$fRow['found_id'];

                $rawLoc = strtolower((string)($fRow['location'] ?? ''));
                $area = 'Campus area';
                foreach (['library'=>'Library area','cafeteria'=>'Cafeteria area','canteen'=>'Cafeteria area','bus'=>'Near Bus Stand','auditorium'=>'Auditorium area','sports'=>'Sports complex area','parking'=>'Parking area','lab'=>'Lab area','block'=>'Academic block area'] as $needle => $label) {
                    if (strpos($rawLoc, $needle) !== false) { $area = $label; break; }
                }

                $safeImage = '';
                $cand = trim((string)($fRow['thumbnail'] ?? ''));
                if (!empty($cand)) $safeImage = ltrim($cand, '/\\');

                $matches[] = [
                    'match_id' => (int)$newMatchId,
                    'report_id' => 'RF-' . str_pad((string)$fRow['found_id'], 5, '0', STR_PAD_LEFT),
                    'score' => round($calcScore),
                    'thumbnail' => $safeImage,
                    'location' => $area,
                    'title' => (!empty($fRow['title']) && $fRow['title'] !== 'Found Item') ? $fRow['title'] : (!empty($fRow['category']) ? $fRow['category'] : 'Found Belonging'),
                    'status' => 'pending'
                ];
            }
        }
    }
}

// Ensure matches are always sorted by highest real-time similarity score
if (!empty($matches)) {
    usort($matches, function($a, $b) {
        return ($b['score'] <=> $a['score']);
    });
}

// 4. Return real-time results
$count = count($matches);
if ($count > 0) {
    $message = "We found {$count} possible match" . ($count === 1 ? '' : 'es') . " for Report #{$formattedReportId}. Review them and verify ownership privately.";
} else {
    $message = "Report #{$formattedReportId} is {$lostStatus} in the database. No matching found items have been reported yet.";
}

searchMatchesReply(true, $message, [
    'report_id' => $formattedReportId,
    'status' => $lostStatus,
    'category' => $lostReport['category'] ?? '',
    'title' => $lostReport['title'] ?? '',
    'location' => $lostReport['location'] ?? '',
    'matches' => $matches
]);
