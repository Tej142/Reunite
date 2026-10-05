<?php
/**
 * Test matching engine and real-time notifications
 */
require_once dirname(__DIR__) . '/Backend/config/config.php';
require_once dirname(__DIR__) . '/Backend/functions.php';

global $conn;

echo "========================================================\n";
echo "TEST: AI MATCHING ENGINE & REAL-TIME NOTIFICATIONS\n";
echo "========================================================\n\n";

if (!$conn) {
    die("DB Connection failed\n");
}

// 1. Get or create two test users
$u1 = $conn->query("SELECT user_id, full_name FROM users ORDER BY user_id ASC LIMIT 1")->fetch_assoc();
$u2 = $conn->query("SELECT user_id, full_name FROM users ORDER BY user_id DESC LIMIT 1")->fetch_assoc();

if (!$u1 || !$u2) {
    die("Need at least 2 users in users table\n");
}

$user1Id = (int)$u1['user_id'];
$user2Id = (int)$u2['user_id'];
echo "[1] Test Users Selected:\n";
echo "    User 1 (Lost Reporter): ID #{$user1Id} - {$u1['full_name']}\n";
echo "    User 2 (Found Reporter): ID #{$user2Id} - {$u2['full_name']}\n\n";

// Clear previous test notifications for clean test
$conn->query("DELETE FROM notifications WHERE user_id IN ($user1Id, $user2Id)");
echo "[2] Cleared previous test notifications for User $user1Id and User $user2Id.\n\n";

// 2. Submit Lost Report as User 1
echo "[3] Submitting Lost Report as User #{$user1Id}...\n";
$_SESSION['user_id'] = $user1Id;

$lostTitle = "Lost Titan Octane Chronograph Watch";
$lostCategory = "Watch";
$lostDesc = "Lost my black dial Titan Octane watch with brown leather strap and silver bezel.";
$lostLoc = "Library 2nd Floor Reading Room";
$lostDate = date('Y-m-d');

$stmtLost = $conn->prepare("INSERT INTO lost_reports (user_id, category, title, description, location, date_lost, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
$stmtLost->bind_param("isssss", $user1Id, $lostCategory, $lostTitle, $lostDesc, $lostLoc, $lostDate);
$stmtLost->execute();
$lostId = $stmtLost->insert_id;
$stmtLost->close();

$formattedLostId = 'RL-' . str_pad($lostId, 5, '0', STR_PAD_LEFT);
echo "    ✅ Lost Report Created! DB ID: $lostId ($formattedLostId)\n";

// Store in ChromaDB
try {
    $embedPayload = [
        'report_id' => $formattedLostId,
        'db_id' => $lostId,
        'report_type' => 'lost',
        'category' => $lostCategory,
        'title' => $lostTitle,
        'description' => $lostDesc,
        'location' => $lostLoc,
        'date' => $lostDate
    ];
    $ch = curl_init('http://127.0.0.1:5000/report/embed-and-store');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($embedPayload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    $storeRes = curl_exec($ch);
    curl_close($ch);
    echo "    ChromaDB Store Response: " . substr($storeRes, 0, 80) . "...\n\n";
} catch (Exception $e) {
    echo "    ChromaDB store notice: " . $e->getMessage() . "\n\n";
}

// 3. Now User 2 submits a Found Report matching the watch
echo "[4] Submitting matching Found Report as User #{$user2Id}...\n";
$_SESSION['user_id'] = $user2Id;

$foundTitle = "Found Titan Octane Watch with Leather Strap";
$foundCategory = "Watch";
$foundDesc = "Found a black dial Titan watch with brown leather strap lying on desk in library 2nd floor.";
$foundLoc = "Library 2nd Floor";
$foundDate = date('Y-m-d');

$stmtFound = $conn->prepare("INSERT INTO found_reports (user_id, category, title, description, location, date_found, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
$stmtFound->bind_param("isssss", $user2Id, $foundCategory, $foundTitle, $foundDesc, $foundLoc, $foundDate);
$stmtFound->execute();
$foundId = $stmtFound->insert_id;
$stmtFound->close();

$formattedFoundId = 'RF-' . str_pad($foundId, 5, '0', STR_PAD_LEFT);
echo "    ✅ Found Report Created! DB ID: $foundId ($formattedFoundId)\n";

// Run Realtime Matchmaking
echo "\n[5] Executing Real-Time Matchmaking Engine...\n";
$matches = runRealtimeMatchmaking(
    $foundId,
    'found',
    $foundCategory,
    $foundTitle,
    $foundDesc,
    $foundLoc,
    null,
    $user2Id
);

echo "    Matches generated count: " . count($matches) . "\n";
print_r($matches);

// 4. Inspect DB `matches` table
echo "\n[6] Checking `matches` table in database...\n";
$mRow = $conn->query("SELECT * FROM matches WHERE lost_report_id = $lostId AND found_report_id = $foundId")->fetch_assoc();
if ($mRow) {
    echo "    ✅ Match successfully recorded in DB!\n";
    echo "       Match ID: {$mRow['id']}\n";
    echo "       Lost Report ID: {$mRow['lost_report_id']}\n";
    echo "       Found Report ID: {$mRow['found_report_id']}\n";
    echo "       Similarity Score: {$mRow['similarity_score']}%\n";
    echo "       Status: {$mRow['status']}\n\n";
} else {
    echo "    ❌ ERROR: Match row was not found in DB!\n\n";
}

// 5. Inspect DB `notifications` table for User 1
echo "[7] Checking `notifications` table for User 1 (Lost Reporter ID #{$user1Id})...\n";
$notifUser1 = $conn->query("SELECT * FROM notifications WHERE user_id = $user1Id ORDER BY notification_id DESC LIMIT 1")->fetch_assoc();
if ($notifUser1) {
    echo "    ✅ Instant Notification generated for User 1:\n";
    echo "       Notif ID: {$notifUser1['notification_id']}\n";
    echo "       Title: {$notifUser1['title']}\n";
    echo "       Message: {$notifUser1['message']}\n";
    echo "       Type: {$notifUser1['type']}\n";
    echo "       Link: {$notifUser1['link']}\n";
    echo "       Is Read: {$notifUser1['is_read']}\n\n";
} else {
    echo "    ❌ ERROR: No notification for User 1!\n\n";
}

// 6. Inspect DB `notifications` table for User 2
echo "[8] Checking `notifications` table for User 2 (Found Reporter ID #{$user2Id})...\n";
$notifUser2 = $conn->query("SELECT * FROM notifications WHERE user_id = $user2Id ORDER BY notification_id DESC LIMIT 1")->fetch_assoc();
if ($notifUser2) {
    echo "    ✅ Instant Notification generated for User 2:\n";
    echo "       Notif ID: {$notifUser2['notification_id']}\n";
    echo "       Title: {$notifUser2['title']}\n";
    echo "       Message: {$notifUser2['message']}\n";
    echo "       Type: {$notifUser2['type']}\n";
    echo "       Link: {$notifUser2['link']}\n";
    echo "       Is Read: {$notifUser2['is_read']}\n\n";
} else {
    echo "    ❌ ERROR: No notification for User 2!\n\n";
}

// 7. Verify API output from Backend/notifications.php for User 1
echo "[9] Verifying Backend/notifications.php API response for User 1...\n";
$_SESSION['user_id'] = $user1Id;
ob_start();
$_GET['action'] = 'get';
require dirname(__DIR__) . '/Backend/notifications.php';
$apiResp = ob_get_clean();
$apiJson = json_decode($apiResp, true);

echo "    API Success: " . ($apiJson['success'] ? 'true' : 'false') . "\n";
echo "    API Unread Count: " . ($apiJson['unread_count'] ?? 0) . "\n";
echo "    API Notifications List Count: " . count($apiJson['notifications'] ?? []) . "\n";

if (($apiJson['unread_count'] ?? 0) > 0 && $mRow && $notifUser1) {
    echo "\n🎉 ALL REAL-TIME MATCHING AND NOTIFICATION TESTS PASSED PERFECTLY!\n";
} else {
    echo "\n⚠️ Test had some issues.\n";
}
