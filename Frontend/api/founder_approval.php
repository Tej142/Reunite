<?php
/**
 * Reunite - Claim Lifecycle v2: Founder Approval API
 * Endpoint: POST Frontend/api/founder_approval.php
 * Allows finder to approve or reject a claimant's ownership proof.
 * - On Reject: sets matches.status = 'rejected', triggers strict 24h ban on claimant.
 * - On Approve: sets matches.status = 'ready_for_handover', inserts recovery_case (in_progress),
 *   auto-declines competing claims without ban, and notifies owner.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

require_once __DIR__ . '/../../Backend/config/config.php';
require_once __DIR__ . '/../../Backend/functions.php';
require_once __DIR__ . '/../includes/auth.php';
init_session();
check_permanent_auth_cookie();

// 1. Strict HTTP Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'code' => 'METHOD_NOT_ALLOWED', 'message' => 'Method Not Allowed. POST required.']);
    exit;
}

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'DB_UNAVAILABLE', 'message' => 'Database connection unavailable.']);
    exit;
}

// 2. Validate User Authentication
$rawUid = $_SESSION['user_id'] ?? ($_SESSION['user']['user_id'] ?? ($_SESSION['user']['id'] ?? null));
$userId = filter_var($rawUid, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'code' => 'UNAUTHORIZED', 'message' => 'You must be logged in to review claims.']);
    exit;
}

// 3. Feature Flag Enforcement
if (!isClaimModuleActiveForUser($userId)) {
    http_response_code(503);
    echo json_encode(['success' => false, 'code' => 'MODULE_UPGRADE', 'message' => 'Claiming is currently being upgraded and will be available shortly.']);
    exit;
}

// 4. Input Parsing
$matchId = filter_input(INPUT_POST, 'match_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
           ?: filter_var($_POST['match_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$action = strtolower(trim($_POST['action'] ?? ''));
$rejectionReason = trim((string)($_POST['rejection_reason'] ?? ''));
$fraudConfirmed = (string)($_POST['fraud_confirmed'] ?? '0') === '1';
$returnType = strtolower(trim($_POST['return_type'] ?? $_POST['return_method'] ?? ''));
$meetLocation = trim($_POST['meet_location'] ?? $_POST['meeting_location'] ?? '');
$meetTime = trim($_POST['meet_time'] ?? $_POST['meeting_time'] ?? '');

if (!$matchId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'code' => 'INVALID_MATCH', 'message' => 'Valid match ID is required.']);
    exit;
}

if ($action !== 'approve' && $action !== 'reject') {
    http_response_code(400);
    echo json_encode(['success' => false, 'code' => 'INVALID_ACTION', 'message' => "Action must be 'approve' or 'reject'."]);
    exit;
}

$transactionStarted = false;

try {
    // Detect column names across tables
    $mCols = [];
    $cRes = mysqli_query($conn, "SHOW COLUMNS FROM matches");
    if ($cRes) while ($cRow = mysqli_fetch_assoc($cRes)) $mCols[] = strtolower($cRow['Field']);
    $pkCol = in_array('match_id', $mCols) ? 'match_id' : 'id';
    $lostCol = in_array('lost_id', $mCols) ? 'lost_id' : 'lost_report_id';
    $foundCol = in_array('found_id', $mCols) ? 'found_id' : 'found_report_id';

    $fCols = [];
    $fRes = @mysqli_query($conn, "SHOW COLUMNS FROM found_reports");
    if ($fRes) while ($r = mysqli_fetch_assoc($fRes)) $fCols[] = strtolower($r['Field']);
    $fPk = in_array('found_id', $fCols) ? 'found_id' : 'id';
    $fUserCol = in_array('finder_id', $fCols) ? 'finder_id' : (in_array('user_id', $fCols) ? 'user_id' : 'finder_id');

    $lCols = [];
    $lRes = @mysqli_query($conn, "SHOW COLUMNS FROM lost_reports");
    if ($lRes) while ($r = mysqli_fetch_assoc($lRes)) $lCols[] = strtolower($r['Field']);
    $lPk = in_array('lost_id', $lCols) ? 'lost_id' : 'id';
    $lUserCol = in_array('user_id', $lCols) ? 'user_id' : (in_array('claimant_id', $lCols) ? 'claimant_id' : 'user_id');

    $rcCols = [];
    $rcRes = @mysqli_query($conn, "SHOW COLUMNS FROM recovery_cases");
    if ($rcRes) while ($r = mysqli_fetch_assoc($rcRes)) $rcCols[] = strtolower($r['Field']);
    $rcPk = in_array('id', $rcCols) ? 'id' : (in_array('case_id', $rcCols) ? 'case_id' : 'id');
    $rcRepCol = in_array('report_id', $rcCols) ? 'report_id' : (in_array('found_id', $rcCols) ? 'found_id' : 'report_id');
    $rcClaimantCol = in_array('claimant_id', $rcCols) ? 'claimant_id' : (in_array('owner_id', $rcCols) ? 'owner_id' : 'claimant_id');

    mysqli_begin_transaction($conn);
    $transactionStarted = true;

    // 5. Fetch Match with Row Lock
    $selectSql = "
        SELECT 
            m.`$pkCol` AS id,
            m.status,
            m.`$lostCol` AS lost_report_id,
            m.`$foundCol` AS found_report_id,
            r_found.`$fUserCol` AS founder_id,
            r_lost.`$lUserCol` AS claimant_id
        FROM matches m
        LEFT JOIN found_reports r_found ON m.`$foundCol` = r_found.`$fPk`
        LEFT JOIN lost_reports r_lost ON m.`$lostCol` = r_lost.`$lPk`
        WHERE m.`$pkCol` = ? 
        FOR UPDATE
    ";

    $selectStmt = mysqli_prepare($conn, $selectSql);
    if (!$selectStmt) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($selectStmt, 'i', $matchId);
    mysqli_stmt_execute($selectStmt);
    $result = mysqli_stmt_get_result($selectStmt);
    $match = mysqli_fetch_assoc($result);
    mysqli_stmt_close($selectStmt);

    if (!$match) {
        http_response_code(404);
        echo json_encode(['success' => false, 'code' => 'NOT_FOUND', 'message' => 'Match record not found.']);
        if ($transactionStarted) mysqli_rollback($conn);
        exit;
    }

    // Authorization: Only the founder who reported the found item can decide
    if (!empty($match['founder_id']) && (int)$match['founder_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'code' => 'FORBIDDEN', 'message' => 'Unauthorized. Only the finder can approve or reject this claim.']);
        if ($transactionStarted) mysqli_rollback($conn);
        exit;
    }

    $foundId = (int)$match['found_report_id'];
    $lostId = (int)$match['lost_report_id'];
    $claimantId = (int)$match['claimant_id'];

    // 6. ACTION: REJECT
    if ($action === 'reject') {
        // Update matches status
        $rejectSql = "UPDATE matches SET status = 'rejected' WHERE `$pkCol` = ?";
        $rejectStmt = mysqli_prepare($conn, $rejectSql);
        mysqli_stmt_bind_param($rejectStmt, 'i', $matchId);
        mysqli_stmt_execute($rejectStmt);
        mysqli_stmt_close($rejectStmt);

        // A routine proof rejection does not imply fraud. Only an explicit reasoned fraud decision triggers escalation.
        $rejectionReason = mb_substr($rejectionReason ?: 'Insufficient or unclear proof; the claimant may clarify and retry.', 0, 1000, 'UTF-8');
        $sanctionHours = 0; $needsAdminReview = false;
        if ($fraudConfirmed && mb_strlen($rejectionReason, 'UTF-8') >= 10) {
            $priorFraud = 0;
            $eventTable = @$conn->query("SHOW TABLES LIKE 'claim_review_events'");
            if ($eventTable && $eventTable->num_rows > 0) {
                $priorStmt = $conn->prepare('SELECT COUNT(*) AS total FROM claim_review_events WHERE claimant_id = ? AND fraud_confirmed = 1');
                if ($priorStmt) { $priorStmt->bind_param('i',$claimantId); $priorStmt->execute(); $priorFraud=(int)($priorStmt->get_result()->fetch_assoc()['total'] ?? 0); $priorStmt->close(); }
            }
            if ($priorFraud === 0) $sanctionHours = 24;
            elseif ($priorFraud === 1) $sanctionHours = 168;
            else $needsAdminReview = true;
            if ($sanctionHours > 0) setClaimantBan($claimantId, $sanctionHours);
        } else { $fraudConfirmed = false; }
        $eventTable = @$conn->query("SHOW TABLES LIKE 'claim_review_events'");
        if ($eventTable && $eventTable->num_rows > 0) {
            $eventStmt = $conn->prepare("INSERT INTO claim_review_events (match_id, claimant_id, actor_user_id, decision, reason, fraud_confirmed, sanction_hours) VALUES (?, ?, ?, 'rejected', ?, ?, ?)");
            if ($eventStmt) { $eventStmt->bind_param('iiisii',$matchId,$claimantId,$userId,$rejectionReason,$fraudConfirmed,$sanctionHours); $eventStmt->execute(); $eventStmt->close(); }
        }
        if (function_exists('logAccess')) logAccess($userId, $fraudConfirmed ? 'Claim Fraud Confirmed' : 'Claim Proof Rejected', 'Match '.$matchId.'; reason recorded; sanction '.$sanctionHours.'h; admin_review '.($needsAdminReview?'yes':'no'));
        // Notify Claimant
        $notifTitle = "Claim Update: Verification Declined";
        $notifMsg = $fraudConfirmed ? "Your claim was rejected after confirmed fraud/abuse review. A {$sanctionHours}-hour restriction was applied." : "Your ownership proof for #RF-" . str_pad($foundId, 5, '0', STR_PAD_LEFT) . " was not sufficient or clear enough. You may clarify the proof and retry if the match remains open.";
        $notifLink = "search.php";

        if (function_exists('insertSystemNotification')) {
            insertSystemNotification($claimantId, $notifTitle, $notifMsg, $notifLink, 'FOUND', $foundId, 'claim_rejected');
        } else {
            $safeMsg = mysqli_real_escape_string($conn, $notifMsg);
            $safeTitle = mysqli_real_escape_string($conn, $notifTitle);
            $safeLink = mysqli_real_escape_string($conn, $notifLink);
            @mysqli_query($conn, "INSERT INTO notifications (user_id, report_type, report_id, type, message, link, is_read, created_at) VALUES ($claimantId, 'FOUND', $foundId, 'claim_rejected', '$safeMsg', '$safeLink', 0, NOW())");
        }

        mysqli_commit($conn);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $fraudConfirmed ? ($needsAdminReview ? 'Confirmed fraud recorded and referred for administrator review.' : 'Confirmed fraud recorded; account restriction applied.') : 'Proof was rejected as insufficient or unclear. No fraud sanction was applied.',
            'status' => 'rejected'
        ]);
        exit;
    }

    // 7. ACTION: APPROVE
    if ($returnType !== 'admin_office' && $returnType !== 'hand_on_hand') {
        http_response_code(400);
        echo json_encode(['success' => false, 'code' => 'INVALID_RETURN_TYPE', 'message' => 'Return method must be admin_office or hand_on_hand.']);
        if ($transactionStarted) mysqli_rollback($conn);
        exit;
    }

    $formattedMeetTime = null;
    if ($returnType === 'hand_on_hand') {
        if ($meetLocation === '' || mb_strlen($meetLocation) < 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'code' => 'MISSING_LOCATION', 'message' => 'A valid meeting location is required for in-person handover.']);
            if ($transactionStarted) mysqli_rollback($conn);
            exit;
        }

        $meetTs = strtotime($meetTime);
        $minTs = time() + 1800; // 30 minutes in future
        $maxTs = time() + (14 * 86400); // 14 days in future
        if (!$meetTs || $meetTs < $minTs || $meetTs > $maxTs) {
            http_response_code(400);
            echo json_encode(['success' => false, 'code' => 'INVALID_TIME', 'message' => 'Meeting time must be between 30 minutes and 14 days from now.']);
            if ($transactionStarted) mysqli_rollback($conn);
            exit;
        }
        $formattedMeetTime = date('Y-m-d H:i:s', $meetTs);
    } else {
        $meetLocation = 'Campus Lost & Found Administrative Desk (Room 104)';
        $formattedMeetTime = date('Y-m-d 17:00:00', strtotime('+1 day'));
    }

    // A. Update match status to ready_for_handover
    $uMatch = mysqli_prepare($conn, "UPDATE matches SET status = 'ready_for_handover' WHERE `$pkCol` = ?");
    mysqli_stmt_bind_param($uMatch, 'i', $matchId);
    mysqli_stmt_execute($uMatch);
    mysqli_stmt_close($uMatch);

    // B. Auto-decline competing pending claims on same found item without ban
    $conn->query("UPDATE matches SET status = 'declined_competition' WHERE `$foundCol` = $foundId AND `$pkCol` != $matchId AND status IN ('pending', 'pending_founder_approval')");

    // C. Insert or update recovery_cases with status 'in_progress' and OTP columns NULL
    $hasMatchIdCol = in_array('match_id', $rcCols);
    $hasReturnMethodCol = in_array('return_method', $rcCols);
    $hasMeetLocCol = in_array('meeting_location', $rcCols);

    if ($hasMatchIdCol && $hasReturnMethodCol && $hasMeetLocCol) {
        $rcSql = "
            INSERT INTO recovery_cases 
            (match_id, `$rcRepCol`, `$rcClaimantCol`, founder_id, return_method, meeting_location, meeting_time, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'in_progress')
            ON DUPLICATE KEY UPDATE 
                return_method = VALUES(return_method),
                meeting_location = VALUES(meeting_location),
                meeting_time = VALUES(meeting_time),
                status = 'in_progress',
                otp_hash = NULL,
                otp_expires_at = NULL,
                otp_attempts = 0
        ";
        $rcStmt = mysqli_prepare($conn, $rcSql);
        if ($rcStmt) {
            mysqli_stmt_bind_param($rcStmt, 'iiiisss', $matchId, $foundId, $claimantId, $userId, $returnType, $meetLocation, $formattedMeetTime);
            mysqli_stmt_execute($rcStmt);
            mysqli_stmt_close($rcStmt);
        }
    } else {
        // Fallback for transitional schema
        $rcSql = "
            INSERT INTO recovery_cases (`$rcRepCol`, `$rcClaimantCol`, status, proof_details)
            VALUES (?, ?, 'in_progress', ?)
            ON DUPLICATE KEY UPDATE status = 'in_progress'
        ";
        $note = "[Handover Approved: $returnType] Location: $meetLocation | Time: $formattedMeetTime";
        $rcStmt = mysqli_prepare($conn, $rcSql);
        if ($rcStmt) {
            mysqli_stmt_bind_param($rcStmt, 'iis', $foundId, $claimantId, $note);
            mysqli_stmt_execute($rcStmt);
            mysqli_stmt_close($rcStmt);
        }
    }

    // D. Notify Claimant with meeting details
    $notifTitle = "Claim Approved! Handover Ready";
    $notifMsg = "Your claim was APPROVED! Method: " . ($returnType === 'admin_office' ? 'Campus Admin Office' : 'Direct Meetup') . ". Location: {$meetLocation}. View details to initiate live handover.";
    $notifLink = "item.php?type=found&id=" . $foundId . "&match_id=" . $matchId;

    if (function_exists('insertSystemNotification')) {
        insertSystemNotification($claimantId, $notifTitle, $notifMsg, $notifLink, 'FOUND', $foundId, 'claim_approved');
    } else {
        $safeMsg = mysqli_real_escape_string($conn, $notifMsg);
        $safeTitle = mysqli_real_escape_string($conn, $notifTitle);
        $safeLink = mysqli_real_escape_string($conn, $notifLink);
        @mysqli_query($conn, "INSERT INTO notifications (user_id, report_type, report_id, type, message, link, is_read, created_at) VALUES ($claimantId, 'FOUND', $foundId, 'claim_approved', '$safeMsg', '$safeLink', 0, NOW())");
    }

    mysqli_commit($conn);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Claim approved successfully! Handover instructions sent to the owner.',
        'status' => 'ready_for_handover',
        'return_method' => $returnType,
        'meeting_location' => $meetLocation,
        'meeting_time' => $formattedMeetTime
    ]);

} catch (Exception $e) {
    if ($transactionStarted) {
        mysqli_rollback($conn);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'SERVER_ERROR', 'message' => 'An error occurred during approval: ' . $e->getMessage()]);
}
