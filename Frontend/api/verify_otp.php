<?php
/**
 * Reunite - Verify OTP API
 * Verifies the 6-digit OTP presented during in-person item return to complete the recovery.
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

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method. POST required.']);
    exit;
}

$user_id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
    exit;
}

$match_id = filter_input(INPUT_POST, 'match_id', FILTER_VALIDATE_INT)
            ?: filter_var($_POST['match_id'] ?? null, FILTER_VALIDATE_INT);
$otp = trim($_POST['otp'] ?? '');

if (!$match_id || $match_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid match ID.']);
    exit;
}

if (!preg_match('/^[0-9]{6}$/', $otp)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'OTP must be exactly 6 digits.']);
    exit;
}

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

    $lCols = [];
    $lc = @mysqli_query($conn, "SHOW COLUMNS FROM lost_reports");
    if ($lc) while ($r = mysqli_fetch_assoc($lc)) $lCols[] = strtolower($r['Field']);
    $lPk = in_array('lost_id', $lCols) ? 'lost_id' : 'id';

    $fCols = [];
    $fc = @mysqli_query($conn, "SHOW COLUMNS FROM found_reports");
    if ($fc) while ($r = mysqli_fetch_assoc($fc)) $fCols[] = strtolower($r['Field']);
    $fPk = in_array('found_id', $fCols) ? 'found_id' : 'id';
    $fUserCol = in_array('finder_id', $fCols) ? 'finder_id' : (in_array('user_id', $fCols) ? 'user_id' : 'id');
    $lUserCol = in_array('user_id', $lCols) ? 'user_id' : (in_array('claimant_id', $lCols) ? 'claimant_id' : (in_array('owner_id', $lCols) ? 'owner_id' : 'id'));

    // 1. Look up match and confirm user is the founder
    $stmt = mysqli_prepare($conn, "
        SELECT m.`$pkCol` AS id, m.status, m.`$lostCol` AS lost_report_id, m.`$foundCol` AS found_report_id,
               lr.`$lUserCol` as claimant_id, fr.`$fUserCol` as founder_id
        FROM matches m
        LEFT JOIN lost_reports lr ON m.`$lostCol` = lr.`$lPk`
        LEFT JOIN found_reports fr ON m.`$foundCol` = fr.`$fPk`
        WHERE m.`$pkCol` = ?
        LIMIT 1
    ");
    if (!$stmt) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, 'i', $match_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $match = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$match) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Match record not found.']);
        exit;
    }

    if ((int)$match['founder_id'] !== $user_id && (int)$match['claimant_id'] !== $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not authorized to verify this handover.']);
        exit;
    }

    $found_id = (int)$match['found_report_id'];
    $lost_id = (int)$match['lost_report_id'];
    $claimant_id = (int)$match['claimant_id'];

    // 2. Fetch Stored OTP (Check DB columns or session)
    $stored_otp = null;
    $is_expired = false;

    // A. Check matches table
    $check_m = mysqli_query($conn, "SHOW COLUMNS FROM matches LIKE 'otp'");
    if ($check_m && mysqli_num_rows($check_m) > 0) {
        $m_res = mysqli_query($conn, "SELECT otp, otp_expires_at FROM matches WHERE id = " . $match_id);
        if ($m_row = mysqli_fetch_assoc($m_res)) {
            if (!empty($m_row['otp'])) {
                $stored_otp = $m_row['otp'];
                if (!empty($m_row['otp_expires_at']) && strtotime($m_row['otp_expires_at']) < time()) {
                    $is_expired = true;
                }
            }
        }
    }

    // B. Check session fallback
    if (!$stored_otp && isset($_SESSION['handover_otp_' . $match_id])) {
        $sess_otp_info = $_SESSION['handover_otp_' . $match_id];
        $stored_otp = $sess_otp_info['otp'];
        if ($sess_otp_info['expires_at'] < time()) {
            $is_expired = true;
        }
    }

    // C. Check recovery_cases proof_details fallback
    if (!$stored_otp) {
        $rc_query = mysqli_prepare($conn, "SELECT proof_details FROM recovery_cases WHERE report_id = ?");
        if ($rc_query) {
            mysqli_stmt_bind_param($rc_query, 'i', $found_id);
            mysqli_stmt_execute($rc_query);
            $rc_res = mysqli_stmt_get_result($rc_query);
            if ($rc_row = mysqli_fetch_assoc($rc_res)) {
                if (!empty($rc_row['proof_details']) && preg_match_all('/Handover OTP:\s*([0-9]{6})/', $rc_row['proof_details'], $m_otps)) {
                    $stored_otp = end($m_otps[1]);
                }
            }
            mysqli_stmt_close($rc_query);
        }
    }

    if (!$stored_otp) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No active OTP found. Please ask the owner to generate a new OTP.']);
        exit;
    }

    if ($is_expired) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This OTP has expired. Please ask the owner to generate a fresh OTP.']);
        exit;
    }

    // 2. Lockout and Attempt Limiting Check
    $lockKey = 'otp_lockout_' . $match_id;
    if (isset($_SESSION[$lockKey]) && $_SESSION[$lockKey] > time()) {
        $remainingSecs = $_SESSION[$lockKey] - time();
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Too many incorrect attempts. Handover verification is temporarily locked for ' . ceil($remainingSecs / 60) . ' more minute(s).'
        ]);
        exit;
    }

    $attemptKey = 'otp_attempts_' . $match_id;
    $attempts = (int)($_SESSION[$attemptKey] ?? 0);

    if ($stored_otp !== $otp) {
        $attempts++;
        $_SESSION[$attemptKey] = $attempts;
        if (function_exists('logAccess')) {
            logAccess($user_id, 'Handover OTP Failed', "Match ID: $match_id, Attempt: $attempts/5");
        }

        if ($attempts >= 5) {
            $_SESSION[$lockKey] = time() + 900; // 15-minute lockout
            unset($_SESSION[$attemptKey]);
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => 'Too many incorrect OTP attempts. Handover verification locked for 15 minutes.'
            ]);
            exit;
        }

        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Incorrect OTP code. ' . (5 - $attempts) . ' attempt(s) remaining before temporary lockout.'
        ]);
        exit;
    }

    // Reset attempt counter on success
    unset($_SESSION[$attemptKey]);
    unset($_SESSION[$lockKey]);

    // 3. Mark Recovery Complete, Invalidate OTP, & Close Reports
    mysqli_begin_transaction($conn);

    // Invalidate OTP immediately to prevent reuse and set status to verified
    mysqli_query($conn, "UPDATE matches SET otp = NULL, otp_expires_at = NULL, status = 'verified' WHERE `$pkCol` = " . (int)$match_id);

    // Update reports to claimed / closed
    mysqli_query($conn, "UPDATE found_reports SET status = 'claimed' WHERE `$fPk` = " . $found_id);
    if ($lost_id > 0) {
        mysqli_query($conn, "UPDATE lost_reports SET status = 'claimed' WHERE `$lPk` = " . $lost_id);
    }

    // Update recovery_cases status
    $rcCols = [];
    $rcc = @mysqli_query($conn, "SHOW COLUMNS FROM recovery_cases");
    if ($rcc) while ($r = mysqli_fetch_assoc($rcc)) $rcCols[] = strtolower($r['Field']);
    $rcRepCol = in_array('report_id', $rcCols) ? 'report_id' : (in_array('found_id', $rcCols) ? 'found_id' : 'report_id');
    mysqli_query($conn, "UPDATE recovery_cases SET status = 'handed_off'" . (in_array('owner_verified', $rcCols) ? ", owner_verified = 1" : "") . (in_array('finder_confirmed', $rcCols) ? ", finder_confirmed = 1" : "") . " WHERE `$rcRepCol` = " . $found_id);

    // Reward trust scores (+10 for finder, +5 for owner, max 150)
    if (!empty($match['founder_id'])) {
        mysqli_query($conn, "UPDATE users SET trust_score = LEAST(150, COALESCE(trust_score, 100) + 10) WHERE user_id = " . (int)$match['founder_id']);
    }
    if (!empty($match['claimant_id'])) {
        mysqli_query($conn, "UPDATE users SET trust_score = LEAST(150, COALESCE(trust_score, 100) + 5) WHERE user_id = " . (int)$match['claimant_id']);
    }

    // Clear session OTP
    unset($_SESSION['handover_otp_' . $match_id]);

    // Audit Log
    if (function_exists('logAccess')) {
        logAccess($user_id, 'Handover Completed (OTP Verified)', "Match ID: $match_id, Found ID: $found_id, Lost ID: $lost_id");
    }

    // Send Completion Notifications to Both Parties
    $notif_title = "Item Handover Completed!";
    $notif_msg = "The physical handover has been verified and confirmed. The recovery case is now officially completed. Thank you!";
    $notif_link = "item.php?id=" . $found_id;

    if (function_exists('insertSystemNotification')) {
        if ($claimant_id > 0) insertSystemNotification($claimant_id, $notif_title, $notif_msg, $notif_link, 'FOUND', $found_id, 'system');
        if ($user_id > 0) insertSystemNotification($user_id, $notif_title, $notif_msg, $notif_link, 'FOUND', $found_id, 'system');
    } else {
        if ($claimant_id > 0) @mysqli_query($conn, "INSERT INTO notifications (user_id, report_type, report_id, type, message, is_read, created_at) VALUES ($claimant_id, 'FOUND', $found_id, 'system', '" . mysqli_real_escape_string($conn, $notif_msg) . "', 0, NOW())");
        if ($user_id > 0) @mysqli_query($conn, "INSERT INTO notifications (user_id, report_type, report_id, type, message, is_read, created_at) VALUES ($user_id, 'FOUND', $found_id, 'system', '" . mysqli_real_escape_string($conn, $notif_msg) . "', 0, NOW())");
    }

    mysqli_commit($conn);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Handover verified successfully! Item marked as returned and case closed.'
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}