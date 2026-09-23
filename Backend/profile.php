<?php
/**
 * Reunite User Profile Handler
 * Handles profile retrieval, information updates, and password changes
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';

requireAuth();

$user = getCurrentUser();
$userId = $_SESSION['user_id'];
$data = getRequestData();
$action = $data['action'] ?? $_GET['action'] ?? '';
$isApi = isApiRequest();

global $conn;

// ── 1. Update Profile Information ─────────────────────────
if ($action === 'update_profile' || ($action === '' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($data['full_name']))) {
    $full_name = trim($data['full_name'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $college = trim($data['college'] ?? '');
    $branch = trim($data['branch'] ?? '');
    $dob = trim($data['dob'] ?? '');

    if (empty($full_name)) {
        sendJsonResponse(false, "Full name cannot be empty.", [], 400);
    }

    if ($conn) {
        $encrypted_phone = !empty($phone) ? encryptData($phone) : null;
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, college = ?, branch = ?, dob = ? WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("sssssi", $full_name, $encrypted_phone, $college, $branch, $dob, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Update Session
    $_SESSION['full_name'] = $full_name;
    $_SESSION['phone'] = $phone;
    $_SESSION['college'] = $college;
    $_SESSION['branch'] = $branch;

    logAccess($userId, 'Profile Updated');

    sendJsonResponse(true, "Profile updated successfully!", [
        'user' => getCurrentUser()
    ]);
}

// ── 2. Change Password ───────────────────────────────────
if ($action === 'change_password') {
    $current_password = $data['current_password'] ?? '';
    $new_password = $data['new_password'] ?? '';

    if (empty($current_password) || empty($new_password)) {
        sendJsonResponse(false, "Please provide current and new passwords.", [], 400);
    }

    if (strlen($new_password) < 8) {
        sendJsonResponse(false, "New password must be at least 8 characters long.", [], 400);
    }

    if ($conn) {
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                if (!password_verify($current_password, $row['password_hash'])) {
                    $stmt->close();
                    sendJsonResponse(false, "Current password is incorrect.", [], 400);
                }
            }
            $stmt->close();

            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            if ($update_stmt) {
                $update_stmt->bind_param("si", $new_hash, $userId);
                $update_stmt->execute();
                $update_stmt->close();
            }
        }
    }

    logAccess($userId, 'Password Changed');
    sendJsonResponse(true, "Password changed successfully!");
}

// ── 3. Default GET: Return Profile Details & Statistics ───
$stats = [
    'lost_reports_count' => 0,
    'found_reports_count' => 0,
    'matches_count' => 0,
    'reunited_count' => 0
];

if ($conn) {
    // Count lost reports
    $l_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM lost_reports WHERE user_id = ?");
    if ($l_stmt) {
        $l_stmt->bind_param("i", $userId);
        $l_stmt->execute();
        $stats['lost_reports_count'] = (int)($l_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $l_stmt->close();
    }

    // Count found reports
    $f_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM found_reports WHERE user_id = ?");
    if ($f_stmt) {
        $f_stmt->bind_param("i", $userId);
        $f_stmt->execute();
        $stats['found_reports_count'] = (int)($f_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $f_stmt->close();
    }
}

sendJsonResponse(true, "Profile loaded", [
    'user' => $user,
    'stats' => $stats
]);
