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
    // 1. Look up match and confirm user is the founder
    $stmt = mysqli_prepare($conn, "
        SELECT m.id, m.status, m.lost_report_id, m.found_report_id,
               lr.user_id as claimant_id, fr.user_id as founder_id
        FROM matches m
        LEFT JOIN lost_reports lr ON m.lost_report_id = lr.id
        LEFT JOIN found_reports fr ON m.found_report_id = fr.id
        WHERE m.id = ?
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

    if ($stored_otp !== $otp) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Incorrect OTP code. Please try again.']);
        exit;
    }

    // 3. Mark Recovery Complete & Close Reports
    mysqli_begin_transaction($conn);

    // Update matches status
    mysqli_query($conn, "UPDATE matches SET status = 'verified' WHERE id = " . $match_id);

    // Update reports to claimed / closed
    mysqli_query($conn, "UPDATE found_reports SET status = 'claimed' WHERE id = " . $found_id);
    mysqli_query($conn, "UPDATE lost_reports SET status = 'claimed' WHERE id = " . $lost_id);

    // Update recovery_cases status
    mysqli_query($conn, "UPDATE recovery_cases SET status = 'handed_off', owner_verified = 1, finder_confirmed = 1 WHERE report_id = " . $found_id);

    // Clear session OTP
    unset($_SESSION['handover_otp_' . $match_id]);

    // Send Completion Notifications to Both Parties
    $notif_title = "Item Handover Completed! 🎉";
    $notif_msg = "The physical handover has been verified and confirmed. The recovery case is now officially completed. Thank you!";
    $notif_link = "item.php?id=" . $found_id;

    $n1 = mysqli_prepare($conn, "INSERT INTO notifications (user_id, type, title, message, report_type, report_id, link) VALUES (?, 'system', ?, ?, 'FOUND', ?, ?)");
    if ($n1) {
        mysqli_stmt_bind_param($n1, "issis", $claimant_id, $notif_title, $notif_msg, $found_id, $notif_link);
        mysqli_stmt_execute($n1);
        mysqli_stmt_close($n1);
    }

    $n2 = mysqli_prepare($conn, "INSERT INTO notifications (user_id, type, title, message, report_type, report_id, link) VALUES (?, 'system', ?, ?, 'FOUND', ?, ?)");
    if ($n2) {
        mysqli_stmt_bind_param($n2, "issis", $user_id, $notif_title, $notif_msg, $found_id, $notif_link);
        mysqli_stmt_execute($n2);
        mysqli_stmt_close($n2);
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