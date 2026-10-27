# 04 — Lost, Found & Claim Lifecycle Protocol

## 1. Lifecycle State Machine

```
   ┌──────────────────┐
   │  Item Reported   │◄─── Student submits Lost or Found report with photo & metadata
   └────────┬─────────┘
            │
            ▼
   ┌──────────────────┐
   │ Vector Ingestion │◄─── Flask microservice embeds item into ChromaDB
   └────────┬─────────┘
            │
            ▼
   ┌──────────────────┐
   │ AI DNA Matching  │◄─── Cosine distance calculated against opposite inventory
   └────────┬─────────┘
            │
            ▼
   ┌──────────────────┐
   │ Claim Initiated  │◄─── Potential owner initiates claim; answers adaptive questionnaire
   └────────┬─────────┘
            │
            ▼
   ┌──────────────────┐
   │ Founder Approval │◄─── Finder inspects claimant answers & photo proof via verification modal
   └────────┬─────────┘
            │
            ▼
   ┌──────────────────┐
   │  OTP Handshake   │◄─── 6-digit cryptographic OTP exchanged during in-person meeting
   └────────┬─────────┘
            │
            ▼
   ┌──────────────────┐
   │  Item Restored   │◄─── Handover confirmed; trust score updated; audit log committed
   └──────────────────┘
```

---

## 2. Founder Verification & Approval Handshake
When a claimant requests return of an item, the finder (founder) receives an immediate interactive modal to approve or challenge the claim.

### Implementation Excerpt: Founder Approval API
File: `Frontend/api/founder_approval.php`

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

// 1. Verify that current user is the genuine founder of this item
$stmt = $conn->prepare("
    SELECT c.claim_id, c.item_id, c.claimant_id, f.finder_id, f.title
    FROM claims c
    JOIN found_items f ON c.item_id = f.id
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
        SET status = 'approved', handover_otp_hash = ?, approved_at = NOW() 
        WHERE claim_id = ?
    ");
    $upStmt->bind_param("si", $otpHash, $claimId);
    $upStmt->execute();
    $upStmt->close();

    // Notify claimant with handover instructions
    createNotification(
        $claim['claimant_id'],
        "Claim Approved! Your handover verification PIN is ready. Coordinate meeting on campus."
    );

    sendJsonResponse(true, "Handover authorized! Meet claimant at the designated campus desk.", [
        'handover_ready' => true
    ]);
} else {
    // Reject claim and release item back to matching pool
    $upStmt = $conn->prepare("UPDATE claims SET status = 'rejected' WHERE claim_id = ?");
    $upStmt->bind_param("i", $claimId);
    $upStmt->execute();
    $upStmt->close();

    sendJsonResponse(true, "Claim rejected. The item remains listed on the campus board.");
}
```

---

## 3. Physical Handover OTP Verification
During the physical meet on campus:
1. Claimant presents their 6-digit PIN.
2. The founder enters the PIN in their mobile app or web portal.
3. Once verified, the item is marked as `restored` and permanently resolved.
4. Both students receive an algorithmic boost to their university **Trust Score**.
