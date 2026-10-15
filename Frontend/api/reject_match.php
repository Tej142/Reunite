<?php
/**
 * Reunite - Reject Match API
 * Allows authorized users (either the loser or finder) to dismiss a pending match.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

require_once __DIR__ . '/../../Backend/config/config.php';
require_once __DIR__ . '/../../Backend/functions.php';

// 1. Authentication Check (401)
$current_user_id = (int)($_SESSION['user_id'] ?? 0);
if (!$current_user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
    exit;
}

// 2. Request Method Check (405)
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

// 3. Input Validation (400)
$match_id = filter_input(INPUT_POST, 'match_id', FILTER_VALIDATE_INT)
            ?: filter_var($_POST['match_id'] ?? null, FILTER_VALIDATE_INT);
if (!$match_id || $match_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid Match ID is required.']);
    exit;
}

try {
    // 4. Verification: Look up match with joined lost/found report owners
    $select_query = "
        SELECT m.id, m.status, lr.user_id as lost_user_id, fr.user_id as found_user_id
        FROM matches m
        LEFT JOIN lost_reports lr ON m.lost_report_id = lr.id
        LEFT JOIN found_reports fr ON m.found_report_id = fr.id
        WHERE m.id = ?
    ";
    $stmt = mysqli_prepare($conn, $select_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "i", $match_id);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
    }

    $result = mysqli_stmt_get_result($stmt);
    $match = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // a. Existence check (404)
    if (!$match) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Match record not found.']);
        exit;
    }

    // b. Ownership check (403): User must be claimant (loser) or finder
    if ($current_user_id !== (int)$match['lost_user_id'] && $current_user_id !== (int)$match['found_user_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to reject this match.']);
        exit;
    }

    // c. Status condition (409)
    if ($match['status'] !== 'pending') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Only pending matches can be rejected.']);
        exit;
    }

    // 5. Database Update
    $update_query = "UPDATE matches SET status = 'rejected' WHERE id = ? AND status = 'pending'";
    $update_stmt = mysqli_prepare($conn, $update_query);
    if (!$update_stmt) {
        throw new Exception("Update prepare failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($update_stmt, "i", $match_id);
    if (!mysqli_stmt_execute($update_stmt)) {
        throw new Exception("Update execute failed: " . mysqli_stmt_error($update_stmt));
    }

    $affected_rows = mysqli_stmt_affected_rows($update_stmt);
    mysqli_stmt_close($update_stmt);

    if ($affected_rows > 0) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Match rejected successfully.']);
    } else {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Failed to reject match. The status may have already changed.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error: ' . $e->getMessage()]);
}