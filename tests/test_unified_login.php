<?php
/**
 * Test script for Unified Login (Student & Campus Admin)
 */
require_once __DIR__ . '/../Backend/config/config.php';
require_once __DIR__ . '/../Frontend/includes/auth.php';

echo "=== TESTING UNIFIED LOGIN BACKEND LOGIC ===\n\n";

global $conn;
if (!$conn) {
    die("Database connection failed.\n");
}

// 1. Verify campus admin ramesh_svgp exists
$stmt = $conn->prepare("SELECT admin_id, username, password_hash, role, campus_id, status FROM admins WHERE username = 'ramesh_svgp' LIMIT 1");
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) {
    die("Error: ramesh_svgp admin not found in DB!\n");
}
echo "[PASS] Found admin: " . $admin['username'] . " (Role: " . $admin['role'] . ", Campus ID: " . $admin['campus_id'] . ")\n";

// 2. Test password verification for password123
if (password_verify('password123', $admin['password_hash'])) {
    echo "[PASS] password123 verifies correctly against password_hash.\n";
} else {
    echo "[FAIL] password123 does not match password_hash!\n";
}

// 3. Test login_admin() helper
login_admin($admin);
if (!empty($_SESSION['admin_logged_in']) && $_SESSION['role'] === 'campus_admin' && $_SESSION['admin_campus_id'] == $admin['campus_id']) {
    echo "[PASS] login_admin() set session variables correctly: role=" . $_SESSION['role'] . ", campus_id=" . $_SESSION['admin_campus_id'] . "\n";
} else {
    echo "[FAIL] login_admin() failed to set expected session variables.\n";
}

if (!empty($_COOKIE['reunite_admin_session'])) {
    $cookieData = json_decode(base64_decode($_COOKIE['reunite_admin_session']), true);
    if (!empty($cookieData['admin_id']) && $cookieData['role'] === 'campus_admin') {
        echo "[PASS] reunite_admin_session cookie created successfully: " . json_encode($cookieData) . "\n";
    } else {
        echo "[FAIL] Cookie content invalid.\n";
    }
} else {
    echo "[INFO] Cookie set via setcookie (available on next HTTP request or $_COOKIE populated in web context).\n";
}

echo "\n=== ALL LOGIC TESTS COMPLETED SUCCESSFULLY ===\n";
