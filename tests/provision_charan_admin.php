<?php
/**
 * Provision Charan Admin Account
 */
require_once __DIR__ . '/../Backend/config/config.php';
require_once __DIR__ . '/../Backend/functions.php';

header('Content-Type: text/plain');
echo "=== PROVISIONING ADMIN: charan ===\n\n";

global $conn;
if (!$conn) {
    die("[FAIL] Database connection failed.\n");
}

ensureAdminsTable();

// 1. List existing admins to inspect current state
echo "Current admins in database:\n";
$res = $conn->query("SELECT admin_id, username, full_name, email, role, campus_id, status FROM admins");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        echo " - ID: {$r['admin_id']}, User: {$r['username']}, Role: {$r['role']}, Campus: " . ($r['campus_id'] ?? 'NULL') . ", Status: {$r['status']}\n";
    }
}

// 2. Insert or update admin 'charan' with password 'pass'
$username = 'charan';
$fullName = 'Charan';
$email = 'charan@reunite.site';
$password = 'pass';
$passwordHash = password_hash($password, PASSWORD_BCRYPT);
$role = 'master_admin'; // full access admin
$campusId = null; // master admin / all campuses
$status = 'active';

// Check if username already exists
$chk = $conn->prepare("SELECT admin_id FROM admins WHERE username = ? LIMIT 1");
$chk->bind_param("s", $username);
$chk->execute();
$existing = $chk->get_result()->fetch_assoc();
$chk->close();

if ($existing) {
    $adminId = $existing['admin_id'];
    echo "\nAdmin '$username' already exists (ID: $adminId). Updating password to 'pass'...\n";
    $up = $conn->prepare("UPDATE admins SET password_hash = ?, full_name = ?, email = ?, role = ?, status = ? WHERE admin_id = ?");
    $up->bind_param("sssssi", $passwordHash, $fullName, $email, $role, $status, $adminId);
    $up->execute();
    $up->close();
    echo "[PASS] Successfully updated password for '$username'.\n";
} else {
    echo "\nInserting new admin '$username' with password 'pass'...\n";
    $ins = $conn->prepare("INSERT INTO admins (username, full_name, email, password_hash, role, campus_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $ins->bind_param("sssssis", $username, $fullName, $email, $passwordHash, $role, $campusId, $status);
    $ins->execute();
    $adminId = $ins->insert_id;
    $ins->close();
    echo "[PASS] Successfully created admin '$username' with ID #$adminId.\n";
}

// 3. Test verification
$chk2 = $conn->prepare("SELECT admin_id, username, password_hash, role FROM admins WHERE username = ? LIMIT 1");
$chk2->bind_param("s", $username);
$chk2->execute();
$adm = $chk2->get_result()->fetch_assoc();
$chk2->close();

if ($adm && password_verify('pass', $adm['password_hash'])) {
    echo "[PASS] Verified: password 'pass' authenticates successfully for username 'charan'!\n";
} else {
    echo "[FAIL] Verification failed.\n";
}

echo "\n=== PROVISIONING COMPLETED ===\n";
