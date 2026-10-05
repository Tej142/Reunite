<?php
require_once dirname(__DIR__) . '/Backend/config/config.php';
require_once dirname(__DIR__) . '/Backend/functions.php';

global $conn;

echo "=== RUNNING MATCHMAKING FOR EXISTING REPORTS ===\n";

$lost = $conn->query("SELECT * FROM lost_reports WHERE id = 2")->fetch_assoc();
$found = $conn->query("SELECT * FROM found_reports WHERE id = 2")->fetch_assoc();

echo "Lost Report #{$lost['id']} (User {$lost['user_id']}): [{$lost['category']}] {$lost['title']} - {$lost['description']}\n";
echo "Found Report #{$found['id']} (User {$found['user_id']}): [{$found['category']}] {$found['title']} - {$found['description']}\n\n";

// Execute matchmaking
$matches = runRealtimeMatchmaking(
    $found['id'],
    'found',
    $found['category'],
    $found['title'],
    $found['description'],
    $found['location'],
    $found['image_path'],
    $found['user_id']
);

echo "Matches Result:\n";
print_r($matches);

echo "\n=== MATCHES TABLE IN DB ===\n";
$mRes = $conn->query("SELECT * FROM matches");
while($m = $mRes->fetch_assoc()) print_r($m);

echo "\n=== NOTIFICATIONS TABLE IN DB ===\n";
$nRes = $conn->query("SELECT * FROM notifications");
while($n = $nRes->fetch_assoc()) print_r($n);
