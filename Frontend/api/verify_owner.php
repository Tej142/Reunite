<?php
/**
 * Reunite - Claim Lifecycle v2: Verify Owner API
 * Endpoint: POST Frontend/api/verify_owner.php
 * Allows claimant to submit text proof (20-1000 chars) and optional sanitized proof image.
 * Implements anti-spam shield (24h ban check, max 2 active claims quota, credential filtering, row locks).
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
    echo json_encode(['success' => false, 'code' => 'UNAUTHORIZED', 'message' => 'You must be logged in to claim an item.']);
    exit;
}

// 3. Feature Flag Enforcement
if (!isClaimModuleActiveForUser($userId)) {
    http_response_code(503);
    echo json_encode(['success' => false, 'code' => 'MODULE_UPGRADE', 'message' => 'Claiming is currently being upgraded and will be available shortly.']);
    exit;
}

// 4. Anti-Spam Check 1: 24-Hour Ban Shield
$banStatus = isClaimantBanned($userId);
if ($banStatus['banned']) {
    $remainingHours = ceil($banStatus['seconds_remaining'] / 3600);
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'code' => 'USER_BANNED',
        'message' => "You are temporarily banned from submitting claims. Ban lifts in approximately {$remainingHours} hour(s).",
        'ban_until' => $banStatus['ban_until'],
        'seconds_remaining' => $banStatus['seconds_remaining']
    ]);
    exit;
}

// 5. Input Validation
$matchId = filter_input(INPUT_POST, 'match_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
           ?: filter_var($_POST['match_id'] ?? ($_REQUEST['match_id'] ?? null), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$proofText = trim($_POST['proof_text'] ?? ($_REQUEST['proof_text'] ?? ''));

// Fallback to raw php://input if multipart/urlencoded body was parsed differently by proxy
if (empty($proofText) || !$matchId) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $json = json_decode($rawInput, true);
        if (is_array($json)) {
            if (empty($proofText) && isset($json['proof_text'])) {
                $proofText = trim((string)$json['proof_text']);
            }
            if (!$matchId && isset($json['match_id'])) {
                $matchId = filter_var($json['match_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            }
        } else {
            parse_str($rawInput, $parsedPost);
            if (empty($proofText) && isset($parsedPost['proof_text'])) {
                $proofText = trim((string)$parsedPost['proof_text']);
            }
            if (!$matchId && isset($parsedPost['match_id'])) {
                $matchId = filter_var($parsedPost['match_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            }
        }
    }
}

if (!$matchId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'code' => 'INVALID_MATCH', 'message' => 'Valid match ID is required.']);
    exit;
}

$proofLength = mb_strlen($proofText, 'UTF-8');
if ($proofLength < 20 || $proofLength > 1000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'code' => 'INVALID_LENGTH', 'message' => 'Ownership proof must be between 20 and 1000 characters. Received ' . $proofLength . ' characters.']);
    exit;
}

// 6. Sensitive Credential Filter
if (containsSensitiveCredentials($proofText)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'code' => 'SENSITIVE_CREDENTIALS',
        'message' => 'Please remove sensitive credentials (such as passwords, plain text PINs, or card numbers) from your proof.'
    ]);
    exit;
}

// 7. Optional Proof Image Sanitization (Max 8MB, EXIF stripped)
$proofImagePath = null;
if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
    $imgResult = sanitizeAndStoreProofImage($_FILES['proof_image'], $matchId);
    if (!$imgResult['success']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'code' => 'IMAGE_ERROR', 'message' => $imgResult['error']]);
        exit;
    }
    $proofImagePath = $imgResult['web_path'];
}

$transactionStarted = false;

try {
    // Detect column names on matches table
    $mCols = [];
    $cRes = mysqli_query($conn, "SHOW COLUMNS FROM matches");
    if ($cRes) {
        while ($cRow = mysqli_fetch_assoc($cRes)) {
            $mCols[] = strtolower($cRow['Field']);
        }
    }
    $pkCol = in_array('match_id', $mCols) ? 'match_id' : 'id';
    $lostCol = in_array('lost_id', $mCols) ? 'lost_id' : 'lost_report_id';
    $foundCol = in_array('found_id', $mCols) ? 'found_id' : 'found_report_id';

    // Detect column names on found_reports and lost_reports
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

    mysqli_begin_transaction($conn);
    $transactionStarted = true;
    // A user-row lock serializes claims from this account across different lost reports.
    $accountLock = mysqli_prepare($conn, 'SELECT user_id FROM users WHERE user_id = ? FOR UPDATE');
    if (!$accountLock) throw new Exception('Could not lock claimant account.');
    mysqli_stmt_bind_param($accountLock, 'i', $userId); mysqli_stmt_execute($accountLock); mysqli_stmt_get_result($accountLock); mysqli_stmt_close($accountLock);

    // 8. Row lock and match verification
    $selectSql = "
        SELECT 
            m.`$pkCol` AS id,
            m.status, 
            m.`$lostCol` AS lost_report_id,
            m.`$foundCol` AS found_report_id,
            r_lost.`$lUserCol` AS claimant_user_id, 
            r_found.`$fUserCol` AS founder_user_id 
        FROM matches m
        LEFT JOIN lost_reports r_lost ON m.`$lostCol` = r_lost.`$lPk`
        LEFT JOIN found_reports r_found ON m.`$foundCol` = r_found.`$fPk`
        WHERE m.`$pkCol` = ? 
        FOR UPDATE
    ";
    
    $vStmt = mysqli_prepare($conn, $selectSql);
    if (!$vStmt) {
        throw new Exception("Database prepare failed: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($vStmt, "i", $matchId);
    mysqli_stmt_execute($vStmt);
    $vResult = mysqli_stmt_get_result($vStmt);
    $matchData = mysqli_fetch_assoc($vResult);
    mysqli_stmt_close($vStmt);

    if (!$matchData) {
        http_response_code(404);
        echo json_encode(['success' => false, 'code' => 'NOT_FOUND', 'message' => 'Match record not found.']);
        if ($transactionStarted) mysqli_rollback($conn);
        exit;
    }

    // Ownership check: Session user must be claimant who filed the lost report
    if (!empty($matchData['claimant_user_id']) && (int)$matchData['claimant_user_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'code' => 'FORBIDDEN', 'message' => 'You are not authorized to submit proof for this match.']);
        if ($transactionStarted) mysqli_rollback($conn);
        exit;
    }

    $lostReportId = (int)$matchData['lost_report_id'];
    $foundReportId = (int)$matchData['found_report_id'];
    $founderUserId = (int)$matchData['founder_user_id'];

    // 9. Anti-Spam Check 2: Max 2 Active Claims per Lost Item
    $activeClaims = getActiveClaimsCountForLostItem($lostReportId);
    if ($activeClaims >= 2 && !in_array($matchData['status'], ['pending_founder_approval', 'ready_for_handover'])) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'code' => 'TOO_MANY_ACTIVE_CLAIMS',
            'message' => 'You have reached the maximum of 2 active claims for this lost item. Please wait for the founder to review existing claims before submitting another.'
        ]);
        if ($transactionStarted) mysqli_rollback($conn);
        exit;
    }

    // Account-wide active claim cap (serialized by the users row lock above).
    $accountClaimsSql = "SELECT COUNT(*) AS active_count FROM matches m JOIN lost_reports l ON m.`$lostCol` = l.`$lPk` WHERE l.`$lUserCol` = ? AND m.status IN ('pending_founder_approval','ready_for_handover','in_progress','approved')";
    $accountClaimsStmt = mysqli_prepare($conn, $accountClaimsSql);
    if (!$accountClaimsStmt) throw new Exception('Could not verify active claim quota.');
    mysqli_stmt_bind_param($accountClaimsStmt, 'i', $userId); mysqli_stmt_execute($accountClaimsStmt);
    $accountClaimsRow = mysqli_fetch_assoc(mysqli_stmt_get_result($accountClaimsStmt)); mysqli_stmt_close($accountClaimsStmt);
    if ((int)($accountClaimsRow['active_count'] ?? 0) >= 2 && !in_array($matchData['status'], ['pending_founder_approval','ready_for_handover','in_progress','approved'], true)) {
        http_response_code(429); echo json_encode(['success'=>false,'code'=>'ACCOUNT_ACTIVE_CLAIM_LIMIT','message'=>'You have reached the maximum of 2 active claims across your reports. Wait for a review, handover, rejection, cancellation, or expiry to free a slot.']);
        mysqli_rollback($conn); exit;
    }
    // Status check
    if (!in_array($matchData['status'], ['pending', 'rejected'])) {
        http_response_code(409);
        echo json_encode(['success' => false, 'code' => 'INVALID_STATE', 'message' => 'This match is no longer open for claim submission.']);
        if ($transactionStarted) mysqli_rollback($conn);
        exit;
    }

    // 10. Update matches record
    $proofCol = in_array('claim_details', $mCols) ? 'claim_details' : (in_array('owner_proof', $mCols) ? 'owner_proof' : 'claim_details');
    $hasProofImgCol = in_array('proof_image', $mCols);
    $hasClaimSubmittedAt = in_array('claim_submitted_at', $mCols);
    $claimTimeSql = $hasClaimSubmittedAt ? ', claim_submitted_at = NOW()' : '';

    if ($hasProofImgCol) {
        $uStmt = mysqli_prepare($conn, "
            UPDATE matches 
            SET status = 'pending_founder_approval', `$proofCol` = ?, proof_image = COALESCE(?, proof_image)$claimTimeSql
            WHERE `$pkCol` = ?
        ");
        if ($uStmt) {
            mysqli_stmt_bind_param($uStmt, "ssi", $proofText, $proofImagePath, $matchId);
            mysqli_stmt_execute($uStmt);
            mysqli_stmt_close($uStmt);
        }
    } else {
        $uStmt = mysqli_prepare($conn, "
            UPDATE matches 
            SET status = 'pending_founder_approval', `$proofCol` = ?$claimTimeSql
            WHERE `$pkCol` = ?
        ");
        if ($uStmt) {
            mysqli_stmt_bind_param($uStmt, "si", $proofText, $matchId);
            mysqli_stmt_execute($uStmt);
            mysqli_stmt_close($uStmt);
        }
    }

    // 11. Send Real-Time Notification to Founder
    $notifTitle = "Ownership Proof Received!";
    $notifMsg = "A student submitted verification proof for your found item (#RF-" . str_pad($foundReportId, 5, '0', STR_PAD_LEFT) . "). Please review.";
    $notifLink = "item.php?type=found&id=" . $foundReportId . "&match_id=" . $matchId . "&view_claims=1";

    if (function_exists('insertSystemNotification')) {
        insertSystemNotification($founderUserId, $notifTitle, $notifMsg, $notifLink, 'FOUND', $foundReportId, 'founder_approval');
    } else {
        $safeMsg = mysqli_real_escape_string($conn, $notifMsg);
        $safeTitle = mysqli_real_escape_string($conn, $notifTitle);
        $safeLink = mysqli_real_escape_string($conn, $notifLink);
        @mysqli_query($conn, "INSERT INTO notifications (user_id, report_type, report_id, type, message, link, is_read, created_at) VALUES ($founderUserId, 'FOUND', $foundReportId, 'founder_approval', '$safeMsg', '$safeLink', 0, NOW())");
    }

    mysqli_commit($conn);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Proof submitted successfully! The founder has been notified to review your claim.',
        'proof_image' => $proofImagePath,
        'status' => 'pending_founder_approval'
    ]);

} catch (Exception $e) {
    if ($transactionStarted) {
        mysqli_rollback($conn);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'SERVER_ERROR', 'message' => 'An error occurred while submitting proof: ' . $e->getMessage()]);
}
