<?php
/**
 * Reunite - Founder Approval API
 * Allows finder to approve or reject a submitted ownership claim.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

require_once __DIR__ . '/../../Backend/config/config.php';
require_once __DIR__ . '/../../Backend/functions.php';

// 1. HTTP Method Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. POST required.']);
    exit;
}

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit;
}

// 2. Validate User Login
$user_id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
    exit;
}

// 3. Receive POST Data
$match_id = filter_input(INPUT_POST, 'match_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            ?: filter_var($_POST['match_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$action = $_POST['action'] ?? '';
$return_type = $_POST['return_type'] ?? '';
$meet_location = trim($_POST['meet_location'] ?? '');
$meet_time = trim($_POST['meet_time'] ?? '');

if (!$match_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing match ID.']);
    exit;
}

if ($action !== 'approve' && $action !== 'reject') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action. Must be approve or reject.']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    // 4. Fetch Match & Verify Founder Authorization
    $select_sql = "
        SELECT 
            m.id,
            m.status,
            m.lost_report_id,
            m.found_report_id,
            r_found.user_id AS founder_id,
            r_lost.user_id AS claimant_id
        FROM matches m
        LEFT JOIN found_reports r_found ON m.found_report_id = r_found.id
        LEFT JOIN lost_reports r_lost ON m.lost_report_id = r_lost.id
        WHERE m.id = ? 
        FOR UPDATE
    ";

    $select_stmt = mysqli_prepare($conn, $select_sql);
    if (!$select_stmt) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($select_stmt, 'i', $match_id);
    mysqli_stmt_execute($select_stmt);
    $result = mysqli_stmt_get_result($select_stmt);
    $match = mysqli_fetch_assoc($result);
    mysqli_stmt_close($select_stmt);

    if (!$match) {
        throw new Exception('Match record not found.');
    }

    if ((int)$match['founder_id'] !== $user_id) {
        throw new Exception('Unauthorized. Only the finder can approve or reject this claim.');
    }

    $found_id = (int)$match['found_report_id'];
    $claimant_id = (int)$match['claimant_id'];

    // 5. REJECT LOGIC
    if ($action === 'reject') {
        $reject_sql = "UPDATE matches SET status = 'rejected' WHERE id = ?";
        $reject_stmt = mysqli_prepare($conn, $reject_sql);
        mysqli_stmt_bind_param($reject_stmt, 'i', $match_id);
        mysqli_stmt_execute($reject_stmt);
        mysqli_stmt_close($reject_stmt);

        // Update recovery_cases status
        $u_rec = mysqli_prepare($conn, "UPDATE recovery_cases SET status = 'rejected' WHERE report_id = ? AND claimant_id = ?");
        if ($u_rec) {
            mysqli_stmt_bind_param($u_rec, 'ii', $found_id, $claimant_id);
            mysqli_stmt_execute($u_rec);
            mysqli_stmt_close($u_rec);
        }

        // Notify Claimant
        $notif_title = "Claim Update: Verification Unsuccessful";
        $notif_msg = "Your ownership proof was reviewed by the founder but could not be verified.";
        $notif_link = "item.php?id=" . $found_id;
        $n_stmt = mysqli_prepare($conn, "
            INSERT INTO notifications (user_id, type, title, message, report_type, report_id, link)
            VALUES (?, 'claim', ?, ?, 'FOUND', ?, ?)
        ");
        if ($n_stmt) {
            mysqli_stmt_bind_param($n_stmt, "issis", $claimant_id, $notif_title, $notif_msg, $found_id, $notif_link);
            mysqli_stmt_execute($n_stmt);
            mysqli_stmt_close($n_stmt);
        }

        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Claim rejected successfully.']);
        exit;
    }

    // 6. APPROVE LOGIC
    if ($return_type !== 'admin_office' && $return_type !== 'hand_on_hand') {
        throw new Exception('A valid return type (admin_office or hand_on_hand) is required.');
    }

    if ($return_type === 'hand_on_hand') {
        if ($meet_location === '' || $meet_time === '') {
            throw new Exception('Meeting location and time are required for direct handover.');
        }
    } else {
        $meet_location = 'Campus Lost & Found Administrative Desk';
        $meet_time = 'Standard Office Hours (9:00 AM - 5:00 PM)';
    }

    // Generate Secure 6-Digit OTP (Valid for Handover)
    $otp = (string)random_int(100000, 999999);
    $otp_expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Store in session as instant fallback
    $_SESSION['handover_otp_' . $match_id] = [
        'otp' => $otp,
        'expires_at' => strtotime('+24 hours')
    ];

    // Update matches table if extra columns exist
    $check_otp_col = mysqli_query($conn, "SHOW COLUMNS FROM matches LIKE 'otp'");
    if ($check_otp_col && mysqli_num_rows($check_otp_col) > 0) {
        $u_m = mysqli_prepare($conn, "
            UPDATE matches 
            SET status = 'verified', return_type = ?, meet_location = ?, meet_time = ?, otp = ?, otp_expires_at = ?
            WHERE id = ?
        ");
        if ($u_m) {
            mysqli_stmt_bind_param($u_m, 'sssssi', $return_type, $meet_location, $meet_time, $otp, $otp_expires_at, $match_id);
            mysqli_stmt_execute($u_m);
            mysqli_stmt_close($u_m);
        }
    } else {
        mysqli_query($conn, "UPDATE matches SET status = 'verified' WHERE id = " . $match_id);
    }

    // Update recovery_cases status and store meeting note
    $meet_note = "[Approved Return: {$return_type}] Meeting at {$meet_location} on {$meet_time}. Handover OTP: {$otp}";
    $u_rec = mysqli_prepare($conn, "
        UPDATE recovery_cases 
        SET status = 'approved', finder_confirmed = 1, proof_details = CONCAT(proof_details, '\n\n', ?)
        WHERE report_id = ? AND claimant_id = ?
    ");
    if ($u_rec) {
        mysqli_stmt_bind_param($u_rec, 'sii', $meet_note, $found_id, $claimant_id);
        mysqli_stmt_execute($u_rec);
        mysqli_stmt_close($u_rec);
    }

    // Update found_reports status to claimed
    mysqli_query($conn, "UPDATE found_reports SET status = 'claimed' WHERE id = " . $found_id);

    // Notify Claimant with meeting details
    $notif_title = "Claim Approved! Handover Scheduled";
    $notif_msg = "Your claim was APPROVED! Handover type: {$return_type}. Location: {$meet_location} at {$meet_time}. Your Verification Code: {$otp}";
    $notif_link = "item.php?id=" . $found_id;

    $n_stmt = mysqli_prepare($conn, "
        INSERT INTO notifications (user_id, type, title, message, report_type, report_id, link)
        VALUES (?, 'claim', ?, ?, 'FOUND', ?, ?)
    ");
    if ($n_stmt) {
        mysqli_stmt_bind_param($n_stmt, "issis", $claimant_id, $notif_title, $notif_msg, $found_id, $notif_link);
        mysqli_stmt_execute($n_stmt);
        mysqli_stmt_close($n_stmt);
    }

    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Claim approved successfully! Handover instructions sent to the owner.',
        'otp' => $otp,
        'meet_location' => $meet_location,
        'meet_time' => $meet_time
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}