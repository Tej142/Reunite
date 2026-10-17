<?php
/**
 * Reunite - Generate OTP API
 * Generates a fresh 6-digit OTP valid for 5 minutes when the owner initiates an active handover.
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
if (!$match_id || $match_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing match ID.']);
    exit;
}

try {
    // Detect column names dynamically
    $mCols = [];
    $cRes = mysqli_query($conn, "SHOW COLUMNS FROM matches");
    if ($cRes) {
        while ($cRow = mysqli_fetch_assoc($cRes)) {
            $mCols[] = strtolower($cRow['Field']);
        }
    }
    $mPk = in_array('match_id', $mCols) ? 'match_id' : 'id';
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

    // Look up match and confirm user is the claimant or founder
    $stmt = mysqli_prepare($conn, "
        SELECT m.`$mPk` AS id, m.status, m.`$lostCol` AS lost_report_id, m.`$foundCol` AS found_report_id,
               lr.user_id as claimant_id, fr.user_id as founder_id
        FROM matches m
        LEFT JOIN lost_reports lr ON m.`$lostCol` = lr.`$lPk`
        LEFT JOIN found_reports fr ON m.`$foundCol` = fr.`$fPk`
        WHERE m.`$mPk` = ?
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

    if ((int)$match['claimant_id'] !== $user_id && (int)$match['founder_id'] !== $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not authorized for this handover.']);
        exit;
    }

    // Generate 6-digit OTP
    $otp = (string)random_int(100000, 999999);
    $otp_expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    // Store OTP in session as instant fallback
    $_SESSION['handover_otp_' . $match_id] = [
        'otp' => $otp,
        'expires_at' => strtotime('+5 minutes')
    ];

    // Store in matches table if columns exist
    $check_m = mysqli_query($conn, "SHOW COLUMNS FROM matches LIKE 'otp'");
    if ($check_m && mysqli_num_rows($check_m) > 0) {
        $u_stmt = mysqli_prepare($conn, "UPDATE matches SET otp = ?, otp_expires_at = ? WHERE `$mPk` = ?");
        if ($u_stmt) {
            mysqli_stmt_bind_param($u_stmt, 'ssi', $otp, $otp_expires_at, $match_id);
            mysqli_stmt_execute($u_stmt);
            mysqli_stmt_close($u_stmt);
        }
    }

    // Store in recovery_cases table if columns exist
    $check_r = mysqli_query($conn, "SHOW COLUMNS FROM recovery_cases LIKE 'otp'");
    if ($check_r && mysqli_num_rows($check_r) > 0) {
        $found_id = (int)$match['found_report_id'];
        $u_rec = mysqli_prepare($conn, "UPDATE recovery_cases SET otp = ?, otp_expires_at = ? WHERE report_id = ?");
        if ($u_rec) {
            mysqli_stmt_bind_param($u_rec, 'ssi', $otp, $otp_expires_at, $found_id);
            mysqli_stmt_execute($u_rec);
            mysqli_stmt_close($u_rec);
        }
    }

    // Persist OTP note in recovery_cases proof_details
    $found_id = (int)$match['found_report_id'];
    $u_rec_note = mysqli_prepare($conn, "UPDATE recovery_cases SET proof_details = CONCAT(COALESCE(proof_details, ''), '\n[Generated OTP] Handover OTP: ', ?) WHERE report_id = ?");
    if ($u_rec_note) {
        mysqli_stmt_bind_param($u_rec_note, 'si', $otp, $found_id);
        mysqli_stmt_execute($u_rec_note);
        mysqli_stmt_close($u_rec_note);
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Handover OTP generated successfully. Valid for 5 minutes.',
        'otp' => $otp,
        'expires_at' => $otp_expires_at,
        'expires_in_seconds' => 300
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}