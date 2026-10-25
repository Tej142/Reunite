<?php
/**
 * Reunite - Claim Lifecycle v2: Secure Live OTP Generation API
 * Endpoint: POST Frontend/api/generate_otp.php
 * Generates a fresh 6-digit numeric OTP valid strictly for 5 minutes.
 * Security: Hashed via HMAC-SHA256, rate-limited to 5/hr, timing-synchronized with server_now.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
date_default_timezone_set('Asia/Kolkata');

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
    echo json_encode(['success' => false, 'code' => 'DB_UNAVAILABLE', 'message' => 'Database connection failed.']);
    exit;
}

// 2. Validate User Authentication
$rawUid = $_SESSION['user_id'] ?? ($_SESSION['user']['user_id'] ?? ($_SESSION['user']['id'] ?? null));
$userId = filter_var($rawUid, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'code' => 'UNAUTHORIZED', 'message' => 'Please login to generate your handover code.']);
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
if (!$matchId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'code' => 'INVALID_MATCH', 'message' => 'Valid match ID is required.']);
    exit;
}

try {
    // Detect column names
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

    // 5. Look up match and confirm user is the verified owner
    $selectSql = "
        SELECT 
            m.`$pkCol` AS id, 
            m.status AS match_status, 
            m.`$lostCol` AS lost_report_id, 
            m.`$foundCol` AS found_report_id,
            lr.`$lUserCol` AS owner_id,
            fr.`$fUserCol` AS founder_id
        FROM matches m
        LEFT JOIN lost_reports lr ON m.`$lostCol` = lr.`$lPk`
        LEFT JOIN found_reports fr ON m.`$foundCol` = fr.`$fPk`
        WHERE m.`$pkCol` = ?
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $selectSql);
    if (!$stmt) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, 'i', $matchId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $match = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$match) {
        http_response_code(404);
        echo json_encode(['success' => false, 'code' => 'NOT_FOUND', 'message' => 'Match record not found.']);
        exit;
    }

    // Authorization: Only the owner of the lost item can generate the verification OTP
    if ((int)$match['owner_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'code' => 'FORBIDDEN', 'message' => 'Only the verified owner can generate the handover OTP.']);
        exit;
    }

    if (!in_array($match['match_status'], ['ready_for_handover', 'verified', 'pending'])) {
        http_response_code(409);
        echo json_encode(['success' => false, 'code' => 'INVALID_MATCH_STATUS', 'message' => 'This match is not approved for handover.']);
        exit;
    }

    $foundId = (int)$match['found_report_id'];

    // 6. Look up or auto-init recovery case
    $caseSql = "SELECT * FROM recovery_cases WHERE ";
    if (in_array('match_id', $rcCols)) {
        $caseSql .= "match_id = $matchId OR (`$rcRepCol` = $foundId AND `$rcClaimantCol` = $userId)";
    } else {
        $caseSql .= "`$rcRepCol` = $foundId AND `$rcClaimantCol` = $userId";
    }
    $caseSql .= " ORDER BY `$rcPk` DESC LIMIT 1";

    $caseRes = $conn->query($caseSql);
    $case = $caseRes ? $caseRes->fetch_assoc() : null;

    if (!$case) {
        // Auto-initialize recovery case if match was approved
        $initSql = "INSERT INTO recovery_cases (`$rcRepCol`, `$rcClaimantCol`, status) VALUES ($foundId, $userId, 'in_progress')";
        $conn->query($initSql);
        $caseId = $conn->insert_id;
        $case = ['id' => $caseId, 'status' => 'in_progress', 'otp_regen_count' => 0];
    }

    $caseId = (int)($case[$rcPk] ?? 0);

    // 7. Rate Limiting: Max 5 regenerations per hour
    $lastGen = !empty($case['otp_last_generated']) ? strtotime($case['otp_last_generated']) : 0;
    $regenCount = (int)($case['otp_regen_count'] ?? 0);
    $oneHourAgo = time() - 3600;

    if ($lastGen > $oneHourAgo && $regenCount >= 5) {
        http_response_code(429);
        $retryAfterSec = 3600 - (time() - $lastGen);
        $retryAfterMin = ceil($retryAfterSec / 60);
        echo json_encode([
            'success' => false,
            'code' => 'RATE_LIMIT_EXCEEDED',
            'message' => "Maximum of 5 OTP regenerations per hour exceeded. Please wait {$retryAfterMin} minute(s) before generating a new code.",
            'retry_after_seconds' => $retryAfterSec
        ]);
        exit;
    }

    $newRegenCount = ($lastGen > $oneHourAgo) ? ($regenCount + 1) : 1;

    // 8. Cryptographically Secure 6-Digit Numeric OTP
    $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $serverNow = time();
    $validitySeconds = 300; // Strictly 5 minutes
    $expiresAtTimestamp = $serverNow + $validitySeconds;
    $expiresAtSql = date('Y-m-d H:i:s', $expiresAtTimestamp);

    // 9. HMAC-SHA256 Hashing (Never store plaintext)
    $otpHashed = hashOtpCode($otp);

    // Update recovery_cases securely
    $hasOtpHashCol = in_array('otp_hash', $rcCols);
    if ($hasOtpHashCol) {
        $uCase = mysqli_prepare($conn, "
            UPDATE recovery_cases 
            SET otp_hash = ?, otp_expires_at = ?, otp_attempts = 0, otp_last_generated = NOW(), otp_regen_count = ?
            WHERE `$rcPk` = ?
        ");
        if ($uCase) {
            mysqli_stmt_bind_param($uCase, 'ssii', $otpHashed, $expiresAtSql, $newRegenCount, $caseId);
            mysqli_stmt_execute($uCase);
            mysqli_stmt_close($uCase);
        }
    } else {
        // Transitional fallback with session
        $_SESSION['handover_otp_' . $matchId] = [
            'hash' => $otpHashed,
            'expires_at' => $expiresAtTimestamp,
            'attempts' => 0
        ];
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Live handover OTP generated. Show this code to the finder when meeting.',
        'otp' => $otp,
        'expires_at_timestamp' => $expiresAtTimestamp,
        'server_now' => $serverNow,
        'validity_seconds' => $validitySeconds,
        'regens_remaining' => max(0, 5 - $newRegenCount)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'SERVER_ERROR', 'message' => 'Could not generate OTP: ' . $e->getMessage()]);
}