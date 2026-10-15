<?php
/**
 * Reunite - Verify Owner API
 * Allows claimant to submit text proof and an optional proof image (receipt, bill, old photo).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

require_once __DIR__ . '/../../Backend/config/config.php';
require_once __DIR__ . '/../../Backend/functions.php';

// 1. Strict HTTP Method
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

// 3. Input Validation
$match_id = filter_input(INPUT_POST, 'match_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            ?: filter_var($_POST['match_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$proof_text = trim($_POST['proof_text'] ?? ''); 

if (!$match_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing match ID.']);
    exit;
}

$proof_length = mb_strlen($proof_text, 'UTF-8');
if ($proof_length < 5 || $proof_length > 2000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Proof text must be at least 5 characters.']);
    exit;
}

// Image Upload Handling (Saved in media_vault/proofs)
$proof_image_web_path = null;
if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
    $vault_dir = dirname(__DIR__, 2) . '/media_vault/proofs/';
    if (!is_dir($vault_dir)) {
        @mkdir($vault_dir, 0777, true);
    }
    
    $file_ext = strtolower(pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
    
    if (in_array($file_ext, $allowed_exts)) {
        $file_name = 'proof_' . $match_id . '_' . time() . '.' . $file_ext;
        $target_file = $vault_dir . $file_name;
        
        if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $target_file)) {
            $proof_image_web_path = 'media_vault/proofs/' . $file_name;
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Only JPG, JPEG, PNG, and WebP images are allowed.']);
        exit;
    }
}

$transaction_started = false;

try {
    mysqli_begin_transaction($conn);
    $transaction_started = true;

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

    // 4. Verify Authorization
    $verify_query = "
        SELECT 
            m.`$pkCol` AS id,
            m.status, 
            m.`$lostCol` AS lost_report_id,
            m.`$foundCol` AS found_report_id,
            r_lost.user_id AS claimant_user_id, 
            r_found.user_id AS founder_user_id 
        FROM matches m
        LEFT JOIN lost_reports r_lost ON m.`$lostCol` = r_lost.id
        LEFT JOIN found_reports r_found ON m.`$foundCol` = r_found.id
        WHERE m.`$pkCol` = ? 
        LIMIT 1
    ";
    
    $v_stmt = mysqli_prepare($conn, $verify_query);
    if (!$v_stmt) {
        throw new Exception("Database prepare failed for SELECT: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($v_stmt, "i", $match_id);
    if (!mysqli_stmt_execute($v_stmt)) {
        throw new Exception("Failed to execute match verification query.");
    }
    
    $v_result = mysqli_stmt_get_result($v_stmt);
    $match_data = mysqli_fetch_assoc($v_result);
    mysqli_stmt_close($v_stmt);

    if (!$match_data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Match record not found.']);
        if ($transaction_started) mysqli_rollback($conn);
        exit;
    }

    if (!empty($match_data['claimant_user_id']) && (int)$match_data['claimant_user_id'] !== $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not authorized to claim this item.']);
        if ($transaction_started) mysqli_rollback($conn);
        exit;
    }

    if ($match_data['status'] !== 'pending') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This match is no longer available for claiming.']);
        if ($transaction_started) mysqli_rollback($conn);
        exit;
    }

    $founder_user_id = (int)$match_data['founder_user_id'];
    $found_report_id = (int)$match_data['found_report_id'];

    // 5. Store Claim in recovery_cases
    $full_proof_details = $proof_text;
    if ($proof_image_web_path) {
        $full_proof_details .= "\n\n[Proof Attachment: " . $proof_image_web_path . "]";
    }

    $rec_stmt = mysqli_prepare($conn, "
        INSERT INTO recovery_cases (claimant_id, report_id, proof_details, status)
        VALUES (?, ?, ?, 'pending_verification')
    ");
    if ($rec_stmt) {
        mysqli_stmt_bind_param($rec_stmt, "iis", $user_id, $found_report_id, $full_proof_details);
        mysqli_stmt_execute($rec_stmt);
        mysqli_stmt_close($rec_stmt);
    }

    // 6. Update matches table status safely
    if (in_array('owner_proof', $mCols)) {
        $u_stmt = mysqli_prepare($conn, "
            UPDATE matches 
            SET status = 'pending_founder_approval', owner_proof = ?, proof_image = ? 
            WHERE `$pkCol` = ?
        ");
        if ($u_stmt) {
            mysqli_stmt_bind_param($u_stmt, "ssi", $proof_text, $proof_image_web_path, $match_id);
            mysqli_stmt_execute($u_stmt);
            mysqli_stmt_close($u_stmt);
        }
    } else {
        mysqli_query($conn, "UPDATE matches SET status = 'pending_founder_approval' WHERE `$pkCol` = " . (int)$match_id);
    }

    // 7. Send Real-Time Notification to Founder
    $notif_title = "Ownership Proof Received!";
    $notif_msg = "A student has submitted proof for the item you found. Please verify and approve.";
    $notif_link = "item.php?id=" . $found_report_id . "&match_id=" . $match_id;

    if (function_exists('insertSystemNotification')) {
        insertSystemNotification($founder_user_id, $notif_title, $notif_msg, $notif_link, 'FOUND', $found_report_id, 'claim');
    } else {
        @mysqli_query($conn, "INSERT INTO notifications (user_id, report_type, report_id, type, message, is_read, created_at) VALUES ($founder_user_id, 'FOUND', $found_report_id, 'claim', '" . mysqli_real_escape_string($conn, $notif_msg) . "', 0, NOW())");
    }

    mysqli_commit($conn);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Proof submitted successfully! The founder has been notified.',
        'proof_image' => $proof_image_web_path
    ]);

} catch (Exception $e) {
    if ($transaction_started) {
        mysqli_rollback($conn);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}