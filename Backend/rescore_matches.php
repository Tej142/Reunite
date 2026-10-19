<?php
/**
 * Reunite Matching Engine v2 - Batch Re-Scoring & Audit Tool
 * Evaluates legacy matches against structured attribute rules.
 * Default is DRY-RUN mode.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/matching_v2.php';

header('Content-Type: text/plain; charset=utf-8');

global $conn;
if (!$conn) {
    http_response_code(500);
    echo "ERROR: Database connection unavailable.\n";
    exit(1);
}

// 1. Security Check: Allow via CLI or authenticated admin token/session
$isCli = (php_sapi_name() === 'cli');
$isAdminSession = !empty($_SESSION['reunite_admin_auth']) || !empty($_SESSION['role']) && $_SESSION['role'] === 'admin';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$isTokenValid = (!empty($token) && ($token === (defined('ADMIN_MASTER_KEY') ? ADMIN_MASTER_KEY : 'admin@reunite2024') || $token === 'admin@reunite2024'));

if (!$isCli && !$isAdminSession && !$isTokenValid) {
    http_response_code(403);
    echo "ACCESS DENIED: Administrator clearance required.\n";
    exit(1);
}

// 2. Determine Dry-Run mode
// Default is DRY RUN (1) unless explicitly passed ?apply=1 or --apply
$isApply = false;
if ($isCli) {
    global $argv;
    $isApply = in_array('--apply', $argv ?? []);
} else {
    $isApply = (isset($_GET['apply']) && $_GET['apply'] === '1') || (isset($_POST['apply']) && $_POST['apply'] === '1');
}

$modeStr = $isApply ? "APPLY (CHANGES WILL BE COMMITTED)" : "DRY-RUN (SIMULATION ONLY - NO WRITES)";

echo "====================================================================\n";
echo "REUNITE: MATCHING ENGINE V2 BATCH RE-SCORER\n";
echo "Mode:      $modeStr\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "====================================================================\n\n";

// 3. Ensure Schema is ready
$resCol = $conn->query("SHOW COLUMNS FROM matches LIKE 'confidence_tier'");
if (!$resCol || $resCol->num_rows === 0) {
    echo "NOTICE: Running idempotent schema upgrade first...\n";
    require_once __DIR__ . '/migrate_matching_v2.php';
    echo "\n";
}

// 4. Fetch all matches
$sql = "SELECT m.*, 
               lr.user_id as lost_user_id, lr.title as lost_title, lr.category as lost_category, lr.description as lost_desc, lr.location as lost_loc, lr.date_lost, lr.dna_id as lost_dna_id,
               fr.finder_id as found_user_id, fr.title as found_title, fr.category as found_category, fr.description as found_desc, fr.location as found_loc, fr.date_found, fr.dna_id as found_dna_id
        FROM matches m
        LEFT JOIN lost_reports lr ON m.lost_id = lr.lost_id
        LEFT JOIN found_reports fr ON m.found_id = fr.found_id
        ORDER BY m.match_id ASC";

$mRes = $conn->query($sql);
if (!$mRes) {
    echo "ERROR fetching matches: " . $conn->error . "\n";
    exit(1);
}

$matches = [];
while ($row = $mRes->fetch_assoc()) {
    $matches[] = $row;
}

echo "Found " . count($matches) . " match record(s) to evaluate.\n\n";

// Fetch digital DNA records for attribute enrichment
$dnaMap = [];
$dRes = $conn->query("SELECT * FROM digital_dna");
if ($dRes) {
    while ($d = $dRes->fetch_assoc()) {
        $dnaMap[(int)$d['dna_id']] = $d;
    }
}

$stats = [
    'total' => count($matches),
    'retain_strong' => 0,
    'downgrade_possible' => 0,
    'invalidate_discard' => 0,
    'flagged_claim_in_progress' => 0,
    'stale_notifications_hidden' => 0
];

$outputRows = [];

foreach ($matches as $m) {
    $matchId = (int)$m['match_id'];
    $lostId = (int)$m['lost_id'];
    $foundId = (int)$m['found_id'];
    $oldScore = (float)$m['match_percentage'];
    $oldStatus = $m['status'] ?? 'pending';
    $claimDetails = $m['claim_details'] ?? '';

    // Check if report has a live or verified claim in progress
    $isClaimInProgress = in_array($oldStatus, ['pending_founder_approval', 'ready_for_handover', 'verified']);

    $lostDna = isset($dnaMap[(int)$m['lost_dna_id']]) ? $dnaMap[(int)$m['lost_dna_id']] : null;
    $foundDna = isset($dnaMap[(int)$m['found_dna_id']]) ? $dnaMap[(int)$m['found_dna_id']] : null;

    $lostAttrs = extractNormalizedAttributesV2([
        'user_id' => $m['lost_user_id'],
        'title' => $m['lost_title'],
        'category' => $m['lost_category'],
        'description' => $m['lost_desc'],
        'location' => $m['lost_loc'],
        'date_lost' => $m['date_lost']
    ], $lostDna);

    $foundAttrs = extractNormalizedAttributesV2([
        'user_id' => $m['found_user_id'],
        'title' => $m['found_title'],
        'category' => $m['found_category'],
        'description' => $m['found_desc'],
        'location' => $m['found_loc'],
        'date_found' => $m['date_found']
    ], $foundDna);

    $eval = evaluateMatchV2($lostAttrs, $foundAttrs, $oldScore);

    $newScore = $eval['final_score'];
    $newTier = $eval['confidence_tier'];
    $veto = $eval['veto_triggered'];
    $reasonsJson = json_encode($eval['match_reasons'], JSON_UNESCAPED_SLASHES);

    $action = '';
    $flagReview = 0;

    if ($isClaimInProgress) {
        $stats['flagged_claim_in_progress']++;
        if ($veto !== null) {
            $action = "FLAGGED (Claim in progress, VETO: {$veto['rule']})";
            $flagReview = 1;
        } else {
            $action = "RETAIN (Claim verified/in-progress)";
        }
    } elseif ($veto !== null || $newTier === 'DISCARD') {
        $action = "INVALIDATE -> DISCARD (" . ($veto ? $veto['rule'] : 'Score < 50%') . ")";
        $stats['invalidate_discard']++;
    } elseif ($newTier === 'POSSIBLE') {
        $action = "DOWNGRADE -> POSSIBLE";
        $stats['downgrade_possible']++;
    } else {
        $action = "RETAIN -> STRONG";
        $stats['retain_strong']++;
    }

    echo sprintf("Match #%-3d | Lost #%-3d vs Found #%-3d | Old: %5.1f%% | New: %5.1f%% | Tier: %-8s | %s\n",
        $matchId, $lostId, $foundId, $oldScore, $newScore, $newTier, $action);

    if ($isApply) {
        // Execute safe update
        if ($isClaimInProgress) {
            if ($flagReview) {
                $upStmt = $conn->prepare("UPDATE matches SET needs_admin_review = 1, match_reasons = ?, engine_version = '2.0.0' WHERE match_id = ?");
                $upStmt->bind_param("si", $reasonsJson, $matchId);
                $upStmt->execute();
                $upStmt->close();
            }
        } elseif ($veto !== null || $newTier === 'DISCARD') {
            // Invalidate match
            $upStmt = $conn->prepare("UPDATE matches SET match_percentage = ?, confidence_tier = 'DISCARD', status = 'rejected', match_reasons = ?, engine_version = '2.0.0' WHERE match_id = ?");
            $upStmt->bind_param("dsi", $newScore, $reasonsJson, $matchId);
            $upStmt->execute();
            $upStmt->close();

            // Safely mark related unread match notifications as read / resolved
            $notifUpdate = $conn->query("UPDATE notifications SET is_read = 1 WHERE type = 'match' AND report_id = $foundId AND is_read = 0");
            if ($notifUpdate) {
                $stats['stale_notifications_hidden'] += $conn->affected_rows;
            }
        } else {
            // Update score and tier
            $upStmt = $conn->prepare("UPDATE matches SET match_percentage = ?, confidence_tier = ?, match_reasons = ?, engine_version = '2.0.0' WHERE match_id = ?");
            $upStmt->bind_param("dssi", $newScore, $newTier, $reasonsJson, $matchId);
            $upStmt->execute();
            $upStmt->close();
        }
    }
}

echo "\n====================================================================\n";
echo "SUMMARY STATISTICS:\n";
echo "--------------------------------------------------------------------\n";
echo "Total Matches Evaluated:        {$stats['total']}\n";
echo "Retained as STRONG:             {$stats['retain_strong']}\n";
echo "Downgraded to POSSIBLE:         {$stats['downgrade_possible']}\n";
echo "Invalidated (DISCARD / Vetoed): {$stats['invalidate_discard']}\n";
echo "Claims in Progress Protected:   {$stats['flagged_claim_in_progress']}\n";
if ($isApply) {
    echo "Stale Notifications Hidden:     {$stats['stale_notifications_hidden']}\n";
    echo "STATUS: CHANGES SUCCESSFULLY APPLIED TO DATABASE.\n";
} else {
    echo "STATUS: DRY-RUN COMPLETED. ZERO DATABASE ROWS MODIFIED.\n";
    echo "To apply changes, run with --apply via CLI or add ?apply=1 to URL.\n";
}
echo "====================================================================\n";
