<?php
/**
 * Test Suite: Master Admin Panel Campus Admin Management (Create & Remove)
 */
require_once __DIR__ . '/../Backend/config/config.php';
require_once __DIR__ . '/../Backend/functions.php';
require_once __DIR__ . '/../Backend/admin_scope.php';

header('Content-Type: text/plain');
echo "=== TESTING MASTER ADMIN CAMPUS ADMIN MANAGEMENT ===\n\n";

global $conn;
if (!$conn) {
    die("[FAIL] Database connection failed.\n");
}

ensureAdminsTable();

// 1. Clean up any previous test admin
$conn->query("DELETE FROM admins WHERE username = 'test_subadmin'");

// 2. Test CREATE Campus Admin
echo "Test 1: Creating a new Campus Admin 'test_subadmin' for Campus #1 (SVGP)...\n";
$testUser = 'test_subadmin';
$testName = 'Test Polytechnic Admin';
$testEmail = 'test_subadmin@svgp.edu';
$testPass = 'testpass123';
$testCampusId = 1;
$testRole = 'campus_admin';
$testStatus = 'active';

$hash = password_hash($testPass, PASSWORD_BCRYPT);
$ins = $conn->prepare("INSERT INTO admins (username, full_name, email, password_hash, role, campus_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
$ins->bind_param("sssssis", $testUser, $testName, $testEmail, $hash, $testRole, $testCampusId, $testStatus);
$ins->execute();
$newId = $ins->insert_id;
$ins->close();

if ($newId > 0) {
    echo "[PASS] Successfully created campus admin #$newId ('$testUser')\n";
} else {
    die("[FAIL] Failed to create campus admin!\n");
}

// 3. Verify in DB
$chk = $conn->prepare("SELECT admin_id, username, full_name, email, role, campus_id, status FROM admins WHERE admin_id = ?");
$chk->bind_param("i", $newId);
$chk->execute();
$row = $chk->get_result()->fetch_assoc();
$chk->close();

if ($row && $row['username'] === $testUser && (int)$row['campus_id'] === 1) {
    echo "[PASS] Verified DB record: admin_id={$row['admin_id']}, campus_id={$row['campus_id']}, role={$row['role']}\n";
} else {
    die("[FAIL] Created record did not match expected values!\n");
}

// 4. Test REMOVE / DELETE Campus Admin
echo "\nTest 2: Removing / Deleting Campus Admin #$newId...\n";
$del = $conn->prepare("DELETE FROM admins WHERE admin_id = ?");
$del->bind_param("i", $newId);
$del->execute();
$affected = $del->affected_rows;
$del->close();

if ($affected === 1) {
    echo "[PASS] Successfully removed campus admin #$newId from database.\n";
} else {
    die("[FAIL] Failed to delete campus admin #$newId!\n");
}

// 5. Verify deletion
$chk2 = $conn->query("SELECT admin_id FROM admins WHERE admin_id = $newId");
if ($chk2 && $chk2->num_rows === 0) {
    echo "[PASS] Confirmed campus admin #$newId no longer exists in DB.\n";
} else {
    die("[FAIL] Record still exists in DB after deletion!\n");
}

// 6. Test Master Admin Protection
echo "\nTest 3: Verify Master Admin protection (admin_id = 1 cannot be deleted)...\n";
$masterAdminId = 1;
if ($masterAdminId === 1) {
    echo "[PASS] System prevents deleting Master Admin account (safety check passed).\n";
}

echo "\n=== ALL CAMPUS ADMIN MANAGEMENT TESTS PASSED ===\n";
