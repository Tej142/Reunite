<?php
/**
 * Reunite - Claim Lifecycle v2: Secure Live OTP Verification API
 * Endpoint: POST Frontend/api/verify_otp.php
 * Verifies the 6-digit numeric OTP entered by the finder or campus administrator.
 * Security: Timing-safe hash_equals(), 5-attempt brute-force lockout, row-level transaction lock,
 * updates recovery_cases, marks reports 'reunited', awards trust points.
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
$userId = filter_var($rawUid, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) ?: 0;
$isAdmin = !empty($_SESSION['admin_logged_in']) || !empty($_SESSION['reunite_admin_auth']) || in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'superadmin', 'staff', 'moderator']);
$adminId = filter_var($_SESSION['admin_id'] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) ?: 0;

if (!$userId && !$isAdmin) {
    http_response_code(401);
    echo json_encode(['success' => false, 'code' => 'UNAUTHORIZED', 'message' => 'Please login to verify the handover code.']);
    exit;
}

// 3. Feature Flag Enforcement
if ($userId && !isClaimModuleActiveForUser($userId)) {
    http_response_code(503);
    echo json_encode(['success' => false, 'code' => 'MODULE_UPGRADE', 'message' => 'Claiming is currently being upgraded and will be available shortly.']);
    exit;
}

// 4. Input Parsing & Validation
$inputOtp = trim($_POST['otp'] ?? '');
$matchId = filter_input(INPUT_POST, 'match_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
           ?: filter_var($_POST['match_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!preg_match('/^[0-9]{6}$/', $inputOtp)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'code' => 'INVALID_OTP_FORMAT', 'message' => 'Verification code must be exactly 6 digits.']);
    exit;
}

// If match_id was not explicitly supplied (e.g. walk-in desk OTP entry), auto-resolve via OTP hash
if (!$matchId && $conn) {
    $otpHashed = hashOtpCode($inputOtp);
    $adminCampusId = !empty($_SESSION['admin_campus_id']) ? (int)$_SESSION['admin_campus_id'] : 0;
    $campusClause = ($adminCampusId > 0) ? "AND (campus_id = $adminCampusId OR campus_id IS NULL OR campus_id = 0)" : "";
    $cLookup = $conn->query("
        SELECT id, match_id 
        FROM recovery_cases 
        WHERE otp_hash = '$otpHashed' 
          AND LOWER(status) NOT IN ('completed', 'resolved', 'handed_off', 'rejected', 'archived')
          AND (otp_expires_at IS NULL OR otp_expires_at > NOW())
          $campusClause
        ORDER BY id DESC LIMIT 1
    ");
    if ($cLookup && $cRow = $cLookup->fetch_assoc()) {
        $matchId = (int)(!empty($cRow['match_id']) ? $cRow['match_id'] : $cRow['id']);
    }
}

if (!$matchId) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'code' => 'INVALID_MATCH', 
        'message' => 'No active recovery case found for this OTP code. Please enter the Case # or verify the code.'
    ]);
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

    // 5. Look up match details
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
        FOR UPDATE
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

    // If match was not found, check if $matchId was actually a recovery_cases ID
    if (!$match) {
        $rcCheck = $conn->query("SELECT * FROM recovery_cases WHERE `$rcPk` = $matchId LIMIT 1 FOR UPDATE");
        if ($rcCheck && $rcRow = $rcCheck->fetch_assoc()) {
            $realMatchId = (int)($rcRow['match_id'] ?? 0);
            if ($realMatchId > 0) {
                $stmt2 = mysqli_prepare($conn, $selectSql);
                mysqli_stmt_bind_param($stmt2, 'i', $realMatchId);
                mysqli_stmt_execute($stmt2);
                $match = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));
                mysqli_stmt_close($stmt2);
                if ($match) {
                    $matchId = $realMatchId;
                }
            }
            if (!$match) {
                $foundId = (int)($rcRow[$rcRepCol] ?? 0);
                $ownerId = (int)($rcRow[$rcClaimantCol] ?? 0);
                $lostId = (int)($rcRow['lost_id'] ?? 0);
                $fQ = $conn->query("SELECT `$fUserCol` as founder_id FROM found_reports WHERE `$fPk` = $foundId LIMIT 1");
                $founderId = ($fQ && $fr = $fQ->fetch_assoc()) ? (int)$fr['founder_id'] : 0;
                $match = [
                    'id'              => 0,
                    'match_status'    => 'pending',
                    'lost_report_id'  => $lostId,
                    'found_report_id' => $foundId,
                    'owner_id'        => $ownerId,
                    'founder_id'      => $founderId
                ];
            }
        }
    }

    if (!$match) {
        http_response_code(404);
        echo json_encode(['success' => false, 'code' => 'NOT_FOUND', 'message' => 'Match record not found.']);
        if ($transactionStarted) mysqli_rollback($conn);
        exit;
    }

    $foundId = (int)$match['found_report_id'];
    $lostId = (int)$match['lost_report_id'];
    $founderId = (int)$match['founder_id'];
    $ownerId = (int)$match['owner_id'];

    // 6. Look up Recovery Case with Row Lock
    $caseSql = "SELECT * FROM recovery_cases WHERE ";
    if (in_array('match_id', $rcCols)) {
        $caseSql .= "match_id = $matchId OR (`$rcRepCol` = $foundId AND `$rcClaimantCol` = $ownerId)";
    } else {
        $caseSql .= "`$rcRepCol` = $foundId AND `$rcClaimantCol` = $ownerId";
    }
    $caseSql .= " ORDER BY `$rcPk` DESC LIMIT 1 FOR UPDATE";

    $caseRes = $conn->query($caseSql);
    $case = $caseRes ? $caseRes->fetch_assoc() : null;

    if (!$case) {
        http_response_code(404);
        echo json_encode(['success' => false, 'code' => 'NO_CASE', 'message' => 'No active recovery case found for this item.']);
        if ($transactionStarted) mysqli_rollback($conn);
        exit;
    }

    $caseId = (int)$case[$rcPk];
    $returnMethod = $case['return_method'] ?? 'hand_on_hand';

    // 7. Authorization Check:
    // User must be the finder, OR an administrator if return_method is admin_office
    $userRole = strtolower($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');
    $isAdmin = $isAdmin || in_array($userRole, ['admin', 'superadmin', 'staff', 'moderator', 'master_admin', 'campus_admin']);

    if ($userId !== $founderId) {
        if (!($isAdmin && ($returnMethod === 'admin_office' || $returnMethod === 'in_person' || !empty($isAdmin)))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'code' => 'FORBIDDEN', 'message' => 'Only the finder or campus administrator can verify this handover.']);
            if ($transactionStarted) mysqli_rollback($conn);
            exit;
        }
    }

    // 7B. Anti-IDOR: Campus Admin Scoping Check
    if ($isAdmin && !empty($_SESSION['admin_campus_id'])) {
        $adminCampus = (int)$_SESSION['admin_campus_id'];
        $itemCampus = !empty($case['campus_id']) ? (int)$case['campus_id'] : 0;
        if ($itemCampus <= 0) {
            $fcQ = $conn->query("SELECT campus_id FROM found_reports WHERE id = $foundId LIMIT 1");
            if ($fcQ && $fcR = $fcQ->fetch_assoc()) {
                $itemCampus = (int)($fcR['campus_id'] ?? 0);
            }
        }
        if ($itemCampus > 0 && $itemCampus !== $adminCampus) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'code'    => 'CAMPUS_MISMATCH',
                'message' => 'Access Denied: This handover belongs to another campus administration desk.'
            ]);
            if ($transactionStarted) mysqli_rollback($conn);
            exit;
        }
    }

    // 8. Case Status Check
    $caseStatus = strtolower($case['status'] ?? '');
    if ($caseStatus === 'completed' || $caseStatus === 'resolved') {
        http_response_code(200);
        echo json_encode(['success' => true, 'code' => 'ALREADY_COMPLETED', 'message' => 'This item has already been marked Reunited.']);
        if ($transactionStarted) mysqli_rollback($conn);
        exit;
    }

    // 9. Brute Force 5-Attempt Lockout Check
    $attempts = (int)($case['otp_attempts'] ?? 0);
    if ($attempts >= 5) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'code' => 'LOCKOUT',
            'message' => 'Maximum invalid attempts (5) reached. For security, the owner must generate a fresh verification code.'
        ]);
        if ($transactionStarted) mysqli_rollback($conn);
        exit;
    }

    // 10. Fetch Stored OTP Hash and Expiration
    $storedHash = $case['otp_hash'] ?? null;
    $expiresAtStr = $case['otp_expires_at'] ?? null;

    // Fallback to session if column not populated
    if (empty($storedHash) && isset($_SESSION['handover_otp_' . $matchId])) {
        $sessOtp = $_SESSION['handover_otp_' . $matchId];
        $storedHash = $sessOtp['hash'] ?? null;
        $expiresAtStr = !empty($sessOtp['expires_at']) ? date('Y-m-d H:i:s', $sessOtp['expires_at']) : null;
    }

    if (empty($storedHash) || empty($expiresAtStr)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'code' => 'NO_OTP_ACTIVE',
            'message' => 'No active verification code found. Please ask the owner to tap "Get Live OTP".'
        ]);
        if ($transactionStarted) mysqli_rollback($conn);
        exit;
    }

    // 11. Expiration Check
    $expiresTs = strtotime($expiresAtStr);
    if ($expiresTs && time() > $expiresTs) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'code' => 'OTP_EXPIRED',
            'message' => 'This verification code has expired (valid for 5 minutes). Please ask the owner to refresh the code.'
        ]);
        if ($transactionStarted) mysqli_rollback($conn);
        exit;
    }

    // 12. Timing-Safe Constant-Time Verification
    if (!verifyOtpCode($inputOtp, $storedHash)) {
        $newAttempts = $attempts + 1;
        $remaining = max(0, 5 - $newAttempts);

        $conn->query("UPDATE recovery_cases SET otp_attempts = $newAttempts WHERE `$rcPk` = $caseId");
        mysqli_commit($conn);

        http_response_code(400);
        echo json_encode([
            'success' => false,
            'code' => 'INVALID_OTP',
            'message' => ($remaining > 0)
                ? "Incorrect code. {$remaining} attempt(s) remaining."
                : "Incorrect code. Maximum attempts reached. The owner must generate a fresh code.",
            'remaining_attempts' => $remaining
        ]);
        exit;
    }

    // 13. SUCCESS ACTIONS (Verified Handover)
    // A. Update recovery_cases
    $uCase = mysqli_prepare($conn, "
        UPDATE recovery_cases 
        SET status = 'Completed', completed_at = NOW(), otp_hash = NULL, otp_attempts = 0
        WHERE `$rcPk` = ?
    ");
    if ($uCase) {
        mysqli_stmt_bind_param($uCase, 'i', $caseId);
        mysqli_stmt_execute($uCase);
        mysqli_stmt_close($uCase);
    }

    // B. Update matches table
    $conn->query("UPDATE matches SET status = 'verified' WHERE `$pkCol` = $matchId");

    // C. Mark found_reports and lost_reports as 'reunited'
    $conn->query("UPDATE found_reports SET status = 'reunited' WHERE `$fPk` = $foundId");
    $conn->query("UPDATE lost_reports SET status = 'reunited' WHERE `$lPk` = $lostId");

    // D. Award Trust Points (+10 to Founder, +5 to Owner)
    $hasTrustScore = false;
    $uCols = @$conn->query("SHOW COLUMNS FROM users LIKE 'trust_score'");
    if ($uCols && $uCols->num_rows > 0) {
        $hasTrustScore = true;
    }
    if ($hasTrustScore) {
        if ($founderId > 0) {
            $conn->query("UPDATE users SET trust_score = trust_score + 10 WHERE user_id = $founderId");
        }
        if ($ownerId > 0) {
            $conn->query("UPDATE users SET trust_score = trust_score + 5 WHERE user_id = $ownerId");
        }
    }

    // E. Clear session cache
    unset($_SESSION['handover_otp_' . $matchId]);

    // F. Send Success Notifications to Both Parties
    $foundFormatted = "#RF-" . str_pad($foundId, 5, '0', STR_PAD_LEFT);
    $notifLink = "item.php?type=found&id=" . $foundId;

    $founderTitle = "Item Reunited! 🎉 (+10 Points)";
    $founderMsg = "Physical handover for {$foundFormatted} verified! You earned +10 Trust Points. Thank you for making campus better!";
    
    $ownerTitle = "Item Recovered! 🎉";
    $ownerMsg = "Your item {$foundFormatted} has been officially reunited with you! Handover successfully confirmed.";

    if (function_exists('insertSystemNotification')) {
        insertSystemNotification($founderId, $founderTitle, $founderMsg, $notifLink, 'FOUND', $foundId, 'handover_complete');
        insertSystemNotification($ownerId, $ownerTitle, $ownerMsg, $notifLink, 'FOUND', $foundId, 'handover_complete');
    } else {
        $safeFM = mysqli_real_escape_string($conn, $founderMsg);
        $safeFT = mysqli_real_escape_string($conn, $founderTitle);
        $safeOM = mysqli_real_escape_string($conn, $ownerMsg);
        $safeOT = mysqli_real_escape_string($conn, $ownerTitle);
        $safeL = mysqli_real_escape_string($conn, $notifLink);
        @mysqli_query($conn, "INSERT INTO notifications (user_id, report_type, report_id, type, message, link, is_read, created_at) VALUES ($founderId, 'FOUND', $foundId, 'handover_complete', '$safeFM', '$safeL', 0, NOW())");
        @mysqli_query($conn, "INSERT INTO notifications (user_id, report_type, report_id, type, message, link, is_read, created_at) VALUES ($ownerId, 'FOUND', $foundId, 'handover_complete', '$safeOM', '$safeL', 0, NOW())");
    }

    mysqli_commit($conn);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Handover successfully verified! Both reports are now marked Reunited.',
        'status' => 'Completed',
        'points_awarded' => 10
    ]);

} catch (Exception $e) {
    if ($transactionStarted) {
        mysqli_rollback($conn);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'SERVER_ERROR', 'message' => 'Error during OTP verification: ' . $e->getMessage()]);
}