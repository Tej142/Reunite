# Claim Verification & Handover OTP Protocol Code

## 1. Founder Claim Approval Processor
**Source File:** `Frontend/api/founder_approval.php`

```php
<?php
/**
 * Founder Claim Approval Processor
 * Allows the original finder to review claim evidence and authorize handover
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../Backend/functions.php';

init_session();
require_auth();

$user = get_current_user_data();
$userId = (int)$user['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$claimId = (int)($input['claim_id'] ?? 0);
$decision = strtolower(trim($input['decision'] ?? '')); // 'approve' or 'reject'

// 1. Verify that current user is the genuine finder of this item
$stmt = $conn->prepare("
    SELECT c.claim_id, c.lost_id, c.found_id, c.claimant_id, f.finder_id, f.title
    FROM claims c
    JOIN found_reports f ON c.found_id = f.id
    WHERE c.claim_id = ? AND f.finder_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $claimId, $userId);
$stmt->execute();
$claim = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$claim) {
    sendJsonResponse(false, "Unauthorized: You are not the recorded finder of this item.", [], 403);
}

if ($decision === 'approve') {
    // Generate secure 6-digit handover OTP
    $otp = sprintf("%06d", mt_rand(100000, 999999));
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);

    $upStmt = $conn->prepare("
        UPDATE claims 
        SET status = 'ready_for_handover', handover_otp_hash = ?, approved_at = NOW() 
        WHERE claim_id = ?
    ");
    $upStmt->bind_param("si", $otpHash, $claimId);
    $upStmt->execute();
    $upStmt->close();

    // Transmit one-time code to genuine claimant
    sendJsonResponse(true, "Handover authorized! 6-digit OTP generated for meeting.", [
        'handover_ready' => true,
        'claimant_id'    => $claim['claimant_id']
    ]);
} else {
    // Reject claim and release item back to campus pool
    $upStmt = $conn->prepare("UPDATE claims SET status = 'rejected' WHERE claim_id = ?");
    $upStmt->bind_param("i", $claimId);
    $upStmt->execute();
    $upStmt->close();

    sendJsonResponse(true, "Claim rejected. The item remains listed on the campus board.");
}
```

---

## 2. In-Person Handover OTP Verification Handshake
**Source File:** `Frontend/api/verify_otp.php`

```php
<?php
/**
 * Physical Handover OTP Verification Handshake
 * Validates the 6-digit PIN presented in-person between Finder and Claimant.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../Backend/functions.php';

init_session();
require_auth();

$user = get_current_user_data();
$currentUserId = (int)$user['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$claimId = (int)($input['claim_id'] ?? 0);
$enteredOtp = trim($input['otp'] ?? '');

if (empty($claimId) || strlen($enteredOtp) !== 6) {
    sendJsonResponse(false, "Please provide the complete 6-digit verification code.", [], 400);
}

// Fetch claim handover state
$stmt = $conn->prepare("
    SELECT c.claim_id, c.lost_id, c.found_id, c.claimant_id, c.handover_otp_hash, c.status, f.finder_id
    FROM claims c
    JOIN found_reports f ON c.found_id = f.id
    WHERE c.claim_id = ? AND c.status = 'ready_for_handover'
    LIMIT 1
");
$stmt->bind_param("i", $claimId);
$stmt->execute();
$claim = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$claim) {
    sendJsonResponse(false, "Claim is not ready for handover or invalid.", [], 404);
}

// Assert that current user is either the Finder or the Claimant
if ($currentUserId !== (int)$claim['finder_id'] && $currentUserId !== (int)$claim['claimant_id']) {
    sendJsonResponse(false, "Unauthorized to verify handover for this claim.", [], 403);
}

// Cryptographically verify 6-digit OTP
if (password_verify($enteredOtp, $claim['handover_otp_hash'])) {
    $conn->begin_transaction();

    // 1. Mark claim completed
    $cStmt = $conn->prepare("UPDATE claims SET status = 'completed', completed_at = NOW() WHERE claim_id = ?");
    $cStmt->bind_param("i", $claimId);
    $cStmt->execute();
    $cStmt->close();

    // 2. Mark found item handed over
    $fStmt = $conn->prepare("UPDATE found_reports SET status = 'handed_over' WHERE id = ?");
    $fStmt->bind_param("i", $claim['found_id']);
    $fStmt->execute();
    $fStmt->close();

    // 3. Mark lost report restored
    $lStmt = $conn->prepare("UPDATE lost_reports SET status = 'restored' WHERE id = ?");
    $lStmt->bind_param("i", $claim['lost_id']);
    $lStmt->execute();
    $lStmt->close();

    // 4. Boost university trust scores
    $conn->query("UPDATE users SET trust_score = trust_score + 15 WHERE user_id = " . (int)$claim['finder_id']);
    $conn->query("UPDATE users SET trust_score = trust_score + 10 WHERE user_id = " . (int)$claim['claimant_id']);

    $conn->commit();
    sendJsonResponse(true, "Handover verified successfully! Item officially marked as restored.");
} else {
    sendJsonResponse(false, "Incorrect 6-digit verification code. Please try again.", [], 401);
}
```
