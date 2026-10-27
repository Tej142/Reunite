<?php
/**
 * Test Desk OTP Provisioning & End-to-End Verification Harness
 * Sets up a live test claim for Campus Desk verification with known OTP.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../Backend/config/config.php';
require_once __DIR__ . '/../Backend/functions.php';

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$testOtp = '482915';
$otpHashed = hashOtpCode($testOtp);

// 1. Inspect existing recovery_cases for Campus 1
$caseRes = $conn->query("
    SELECT * FROM recovery_cases 
    WHERE (campus_id = 1 OR campus_id IS NULL OR campus_id = 0)
    ORDER BY id DESC LIMIT 10
");

$existingCases = [];
if ($caseRes) {
    while ($r = $caseRes->fetch_assoc()) {
        $existingCases[] = $r;
    }
}

// 2. Select a target case to arm with the test OTP
// We prefer Case #11 (or the topmost case)
$targetCaseId = 0;
$targetMatchId = 0;

if (!empty($existingCases)) {
    // Look for Case 11 first, else first available
    foreach ($existingCases as $c) {
        if ((int)$c['id'] === 11) {
            $targetCaseId = 11;
            $targetMatchId = (int)($c['match_id'] ?? 11);
            break;
        }
    }
    if (!$targetCaseId) {
        $targetCaseId = (int)$existingCases[0]['id'];
        $targetMatchId = (int)($existingCases[0]['match_id'] ?? $targetCaseId);
    }
}

if ($targetCaseId > 0) {
    // Re-arm this recovery case with active status and test OTP
    $updateSql = "
        UPDATE recovery_cases 
        SET status = 'in_progress',
            return_method = 'admin_office',
            campus_id = 1,
            otp_hash = '$otpHashed',
            otp_expires_at = DATE_ADD(NOW(), INTERVAL 4 HOUR),
            otp_attempts = 0,
            otp_last_generated = NOW()
        WHERE id = $targetCaseId
    ";
    $conn->query($updateSql);

    // Also ensure matches status is in_progress / active
    if ($targetMatchId > 0) {
        $conn->query("UPDATE matches SET status = 'in_progress' WHERE match_id = $targetMatchId OR id = $targetMatchId");
    }
} else {
    // If no case exists at all, insert a test recovery case
    $insSql = "
        INSERT INTO recovery_cases (campus_id, match_id, report_id, claimant_id, return_method, status, proof_details, otp_hash, otp_expires_at, otp_attempts)
        VALUES (1, 1, 1, 1, 'admin_office', 'in_progress', 'Test Ownership Claim Verification at Campus Desk', '$otpHashed', DATE_ADD(NOW(), INTERVAL 4 HOUR), 0)
    ";
    $conn->query($insSql);
    $targetCaseId = $conn->insert_id;
    $targetMatchId = 1;
}

// 3. Optional End-to-End Test Verification parameter
$dryRunVerified = false;
if (isset($_GET['dry_run_test'])) {
    $dryRunVerified = verifyOtpCode($testOtp, $otpHashed);
}

// Fetch the armed case details
$armedQuery = $conn->query("SELECT id, match_id, status, return_method, campus_id, otp_expires_at, otp_attempts FROM recovery_cases WHERE id = $targetCaseId");
$armedRow = $armedQuery ? $armedQuery->fetch_assoc() : null;

echo json_encode([
    'success' => true,
    'message' => "Desk OTP successfully armed for testing!",
    'test_desk_otp' => $testOtp,
    'case_id' => $targetCaseId,
    'match_id' => $targetMatchId,
    'case_details' => $armedRow,
    'dry_run_verified' => $dryRunVerified,
    'instructions' => [
        'step_1' => 'Log in to Admin Panel as Campus Admin (Dr. K. Ramesh) on https://reunite.site.je/Frontend/admin/index.php',
        'step_2' => 'Go to the Claims tab.',
        'step_3' => "You will see Case #$targetCaseId with a green 'Verify OTP' button, or click the top 'Verify Walk-in Desk OTP' button.",
        'step_4' => "Enter the 6-digit OTP code: $testOtp",
        'step_5' => "Click OK: The modal validates the OTP, marks the claim Reunited, awards points, and updates telemetry in real-time!"
    ]
], JSON_PRETTY_PRINT);
