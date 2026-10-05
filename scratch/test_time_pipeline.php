<?php
require_once dirname(__DIR__) . '/Backend/config/config.php';
require_once dirname(__DIR__) . '/Backend/functions.php';

global $conn;

echo "========================================================\n";
echo "TEST: SERVER TIMESTAMP & TEMPORAL AI RESOLUTION\n";
echo "Current Server Date & Time: " . date('Y-m-d H:i:s') . "\n";
echo "========================================================\n\n";

$testCases = [
    [
        'input_date' => 'yesterday',
        'desc' => 'I lost my watch yesterday around 3:30 pm near library',
        'expected_date' => date('Y-m-d', strtotime('-1 day')),
        'expected_time' => '15:30:00'
    ],
    [
        'input_date' => '',
        'desc' => 'Found black leather wallet today morning near canteen',
        'expected_date' => date('Y-m-d'),
        'expected_time' => '09:30:00'
    ],
    [
        'input_date' => '2 days ago',
        'desc' => 'Lost my blue water bottle 2 days ago in seminar hall',
        'expected_date' => date('Y-m-d', strtotime('-2 days')),
        'expected_time' => null
    ],
    [
        'input_date' => '02-10-2026',
        'desc' => 'Found Casio calculator on 02-10-2026 at 4:00 pm',
        'expected_date' => '2026-10-02',
        'expected_time' => '16:00:00'
    ]
];

foreach ($testCases as $idx => $tc) {
    echo "Test Case #" . ($idx + 1) . ":\n";
    echo "  Input Date: '{$tc['input_date']}'\n";
    echo "  Description: '{$tc['desc']}'\n";

    $res = resolveReportDateTime($tc['input_date'], $tc['desc']);
    echo "  => Resolved Date: {$res['date']} (Expected: {$tc['expected_date']})\n";
    echo "  => Resolved Time: " . ($res['time'] ?: 'None') . " (Expected: " . ($tc['expected_time'] ?: 'None') . ")\n";

    if ($res['date'] === $tc['expected_date']) {
        echo "  ✅ DATE MATCHES PERFECTLY!\n";
    } else {
        echo "  ❌ DATE MISMATCH!\n";
    }
    echo "\n";
}

// Test inserting into DB with relative phrasing
echo "=== TESTING DATABASE INSERTION WITH RELATIVE TIME ===\n";
$temporal = resolveReportDateTime('yesterday', 'I lost my watch yesterday at 3:30 PM');
$stmt = $conn->prepare("INSERT INTO lost_reports (user_id, category, title, description, location, date_lost, lost_time, status) VALUES (5, 'Watch', 'Temporal Test Watch', 'I lost my watch yesterday at 3:30 PM', 'Library', ?, ?, 'active')");
$stmt->bind_param("ss", $temporal['date'], $temporal['time']);
$stmt->execute();
$newId = $stmt->insert_id;
$stmt->close();

$savedRow = $conn->query("SELECT id, title, date_lost, lost_time, created_at FROM lost_reports WHERE id = $newId")->fetch_assoc();
echo "Saved Record in MySQL:\n";
print_r($savedRow);

// Clean test row
$conn->query("DELETE FROM lost_reports WHERE id = $newId");
echo "✅ Cleaned test row from database.\n";
