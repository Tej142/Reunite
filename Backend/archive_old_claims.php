<?php
/**
 * Reunite - Idempotent Archival of Legacy In-Flight Claims
 * Safely marks pre-existing pending_verification cases as 'archived'
 * and resets affected found items back to 'active' status.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';

global $conn;

if (!$conn) {
    echo "ERROR: Database connection failed: " . ($db_connection_error ?? 'Unknown error') . "\n";
    exit(1);
}

echo "========================================================\n";
echo "REUNITE: ARCHIVING LEGACY IN-FLIGHT CLAIMS\n";
echo "========================================================\n\n";

// 1. Check existing pending_verification cases
$checkRes = $conn->query("SELECT id, report_id, status FROM recovery_cases WHERE status = 'pending_verification'");
$casesToArchive = [];
if ($checkRes) {
    while ($row = $checkRes->fetch_assoc()) {
        $casesToArchive[] = $row;
    }
}

echo "Found " . count($casesToArchive) . " legacy recovery cases in 'pending_verification' status.\n";

if (!empty($casesToArchive)) {
    // 2. Archive legacy cases
    $archStmt = $conn->query("UPDATE recovery_cases SET status = 'archived' WHERE status = 'pending_verification'");
    echo "Archived " . $conn->affected_rows . " recovery cases to status 'archived'.\n";

    // 3. Reset found reports associated with these cases back to active
    $reportIds = array_filter(array_column($casesToArchive, 'report_id'));
    if (!empty($reportIds)) {
        $idList = implode(',', array_map('intval', $reportIds));
        $resetStmt = $conn->query("UPDATE found_reports SET status = 'active' WHERE id IN ($idList) AND status = 'claimed'");
        echo "Reset " . $conn->affected_rows . " found reports (IDs: $idList) from 'claimed' to 'active'.\n";
    }
} else {
    echo "Zero cases require archival. State is already clean.\n";
}

// 4. Final verification count
$verifyRes = $conn->query("SELECT status, COUNT(*) as cnt FROM recovery_cases GROUP BY status");
echo "\nCurrent recovery_cases status breakdown:\n";
if ($verifyRes) {
    while ($r = $verifyRes->fetch_assoc()) {
        echo " - " . $r['status'] . ": " . $r['cnt'] . "\n";
    }
}

echo "\nDone!\n";
