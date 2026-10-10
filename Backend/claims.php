<?php
/**
 * Reunite - Ownership Claims & Recovery Verification Engine
 * Backend API for submitting, tracking, and resolving ownership claims.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

global $conn;

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$isAdmin = !empty($_SESSION['reunite_admin_auth']) && $_SESSION['reunite_admin_auth'] === true;

$data = getRequestData();
$action = $data['action'] ?? $_GET['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'submit' : 'list');

// ── 1. SUBMIT OWNERSHIP CLAIM ──────────────────────────────────
if ($action === 'submit') {
    if (!$userId) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'code' => 'UNAUTHORIZED',
            'error' => 'You must be logged in to file an ownership claim.'
        ]);
        exit;
    }

    $reportId = (int)($data['report_id'] ?? 0);
    $proofDetails = trim($data['proof_details'] ?? '');
    $contactInfo = trim($data['contact_info'] ?? '');

    if ($reportId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid or missing found report ID.']);
        exit;
    }

    if (mb_strlen($proofDetails) < 5) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Proof details must be at least 5 characters long.']);
        exit;
    }

    // Combine proof with optional contact note
    if (!empty($contactInfo)) {
        $proofDetails .= "\n\n[Claimant Contact Info: " . $contactInfo . "]";
    }

    // Fetch found report to verify existence, status, and finder
    $stmt = $conn->prepare("SELECT id, user_id, title, status FROM found_reports WHERE id = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Query preparation error']);
        exit;
    }
    $stmt->bind_param("i", $reportId);
    $stmt->execute();
    $res = $stmt->get_result();
    $report = $res->fetch_assoc();
    $stmt->close();

    if (!$report) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Found item report not found.']);
        exit;
    }

    // Prevent self-claim
    if ((int)$report['user_id'] === (int)$userId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'You cannot claim an item that you reported finding.'
        ]);
        exit;
    }

    // Check if report is already resolved
    if (in_array($report['status'], ['claimed', 'closed'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'This item has already been claimed or closed.'
        ]);
        exit;
    }

    // Check if user already submitted a pending claim on this report
    $chkStmt = $conn->prepare("SELECT id FROM recovery_cases WHERE claimant_id = ? AND report_id = ? AND status = 'pending_verification'");
    if ($chkStmt) {
        $chkStmt->bind_param("ii", $userId, $reportId);
        $chkStmt->execute();
        $chkRes = $chkStmt->get_result();
        if ($chkRes->num_rows > 0) {
            $chkStmt->close();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'You already have a pending verification claim for this item.'
            ]);
            exit;
        }
        $chkStmt->close();
    }

    // Insert claim into recovery_cases
    $insStmt = $conn->prepare("INSERT INTO recovery_cases (claimant_id, proof_details, report_id, status, created_at) VALUES (?, ?, ?, 'pending_verification', NOW())");
    if (!$insStmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error preparing claim record']);
        exit;
    }

    $insStmt->bind_param("isi", $userId, $proofDetails, $reportId);
    if (!$insStmt->execute()) {
        $insErr = $insStmt->error;
        $insStmt->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save claim: ' . $insErr]);
        exit;
    }

    $claimId = $insStmt->insert_id;
    $insStmt->close();

    // Create notification for the finder
    $finderId = (int)$report['user_id'];
    if ($finderId > 0) {
        $notifTitle = "New Claim on Found Item";
        $notifMsg = "A user submitted ownership proof for your found item: \"" . $report['title'] . "\".";
        $notifLink = "item.php?type=found&id=" . $reportId;
        
        $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, report_type, report_id, type, message, link, is_read, created_at) VALUES (?, ?, 'FOUND', ?, 'claim', ?, ?, 0, NOW())");
        if ($nStmt) {
            $nStmt->bind_param("isiss", $finderId, $notifTitle, $reportId, $notifMsg, $notifLink);
            $nStmt->execute();
            $nStmt->close();
        }
    }

    // Log access event if function exists
    if (function_exists('logAccess')) {
        logAccess($userId, 'claim_submitted', 'ownership_claim', 'Submitted claim #' . $claimId . ' for found report #' . $reportId);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Ownership claim submitted successfully. The finder has been notified.',
        'claim_id' => $claimId,
        'report_id' => $reportId
    ]);
    exit;
}

// ── 2. LIST USER'S CLAIMS ───────────────────────────────────────
if ($action === 'list') {
    if (!$userId) {
        echo json_encode(['success' => true, 'claims' => [], 'logged_in' => false]);
        exit;
    }

    $query = "SELECT c.id, c.report_id, c.proof_details, c.status, c.created_at,
                     r.title, r.category, r.image_path, r.location, r.date_found
              FROM recovery_cases c
              JOIN found_reports r ON c.report_id = r.id
              WHERE c.claimant_id = ?
              ORDER BY c.created_at DESC";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Query error']);
        exit;
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $claims = [];
    while ($row = $res->fetch_assoc()) {
        $claims[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'count' => count($claims),
        'claims' => $claims
    ]);
    exit;
}

// ── 3. RESOLVE CLAIM (APPROVE / REJECT) ─────────────────────────
if ($action === 'resolve') {
    if (!$userId && !$isAdmin) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $claimId = (int)($data['claim_id'] ?? 0);
    $newStatus = trim($data['status'] ?? ''); // 'approved' or 'rejected' or 'handed_off'

    if ($claimId <= 0 || !in_array($newStatus, ['approved', 'rejected', 'handed_off'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid claim ID or status value.']);
        exit;
    }

    // Retrieve claim & report
    $cStmt = $conn->prepare("SELECT c.*, r.user_id as finder_id, r.title 
                             FROM recovery_cases c 
                             JOIN found_reports r ON c.report_id = r.id 
                             WHERE c.id = ?");
    if (!$cStmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit;
    }
    $cStmt->bind_param("i", $claimId);
    $cStmt->execute();
    $claim = $cStmt->get_result()->fetch_assoc();
    $cStmt->close();

    if (!$claim) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Claim record not found.']);
        exit;
    }

    // Verify permission: Must be the finder of the item or an admin
    if (!$isAdmin && (int)$claim['finder_id'] !== (int)$userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only the finder of this item or a campus admin can resolve this claim.']);
        exit;
    }

    // Update claim status
    $ownerVer = ($newStatus === 'approved' || $newStatus === 'handed_off') ? 1 : 0;
    $finderConf = ($newStatus === 'approved' || $newStatus === 'handed_off') ? 1 : 0;

    $uStmt = $conn->prepare("UPDATE recovery_cases SET status = ?, owner_verified = ?, finder_confirmed = ? WHERE id = ?");
    $uStmt->bind_param("siii", $newStatus, $ownerVer, $finderConf, $claimId);
    $uStmt->execute();
    $uStmt->close();

    // If approved or handed_off, update the found item status
    if ($newStatus === 'approved' || $newStatus === 'handed_off') {
        $repStmt = $conn->prepare("UPDATE found_reports SET status = 'claimed' WHERE id = ?");
        $repStmt->bind_param("i", $claim['report_id']);
        $repStmt->execute();
        $repStmt->close();
    }

    // Notify claimant
    $claimantId = (int)$claim['claimant_id'];
    $notifTitle = ($newStatus === 'approved' || $newStatus === 'handed_off') ? "Claim Approved!" : "Claim Update";
    $notifMsg = ($newStatus === 'approved' || $newStatus === 'handed_off') 
        ? "Congratulations! Your claim for \"" . $claim['title'] . "\" has been approved."
        : "Your claim for \"" . $claim['title'] . "\" could not be verified at this time.";
    $notifLink = "item.php?type=found&id=" . $claim['report_id'];

    $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, report_type, report_id, type, message, link, is_read, created_at) VALUES (?, ?, 'FOUND', ?, 'claim', ?, ?, 0, NOW())");
    if ($nStmt) {
        $nStmt->bind_param("isiss", $claimantId, $notifTitle, $claim['report_id'], $notifMsg, $notifLink);
        $nStmt->execute();
        $nStmt->close();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Claim has been successfully updated to ' . $newStatus,
        'claim_id' => $claimId,
        'new_status' => $newStatus
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Unknown action requested']);
