<?php
require_once dirname(__DIR__) . '/Backend/config/config.php';
global $conn;

if (!$conn) {
    die("DB connection failed\n");
}

echo "=== CURRENT NOTIFICATIONS ===\n";
$res = $conn->query("SELECT notification_id, user_id, title, message, created_at FROM notifications");
echo "Count: " . $res->num_rows . "\n";
while ($row = $res->fetch_assoc()) {
    echo " - ID #{$row['notification_id']} (User {$row['user_id']}): {$row['title']} | {$row['message']}\n";
}

// Delete all dummy/test notifications
$conn->query("TRUNCATE TABLE notifications");
echo "\n✅ Successfully cleared all notifications from `notifications` table!\n";

// Also check and clean test matches
$conn->query("TRUNCATE TABLE matches");
echo "✅ Cleaned `matches` table.\n";

// Remove dummy reports created during testing
$conn->query("DELETE FROM lost_reports WHERE title LIKE '%Titan%' OR title LIKE '%Test%'");
$conn->query("DELETE FROM found_reports WHERE title LIKE '%Titan%' OR title LIKE '%Test%'");
echo "✅ Cleaned test reports.\n";

// Reset auto increment if desired
echo "\n=== VERIFICATION ===\n";
$c1 = $conn->query("SELECT COUNT(*) as c FROM notifications")->fetch_assoc()['c'];
$c2 = $conn->query("SELECT COUNT(*) as c FROM matches")->fetch_assoc()['c'];
$c3 = $conn->query("SELECT COUNT(*) as c FROM lost_reports")->fetch_assoc()['c'];
$c4 = $conn->query("SELECT COUNT(*) as c FROM found_reports")->fetch_assoc()['c'];
echo "Notifications remaining: $c1\n";
echo "Matches remaining: $c2\n";
echo "Lost reports remaining: $c3\n";
echo "Found reports remaining: $c4\n";
