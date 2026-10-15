<?php
/**
 * Reunite Database Schema Migration
 * Adds missing columns to lost_reports and found_reports tables
 * 
 * USAGE: Upload to Backend/ and visit in browser. DELETE AFTER USE.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/config/config.php';

if (!$conn) {
    echo json_encode(['error' => 'No DB connection: ' . ($db_connection_error ?? 'unknown')]);
    exit;
}

$results = [];

// Helper: check if column exists
function columnExists($conn, $table, $column) {
    $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($r && $r->num_rows > 0);
}

// Define expected columns for each table
$migrations = [
    'lost_reports' => [
        ['name' => 'title',      'definition' => "VARCHAR(255) DEFAULT NULL AFTER `category`"],
        ['name' => 'description', 'definition' => "TEXT DEFAULT NULL AFTER `title`"],
        ['name' => 'location',   'definition' => "VARCHAR(255) DEFAULT NULL AFTER `description`"],
        ['name' => 'date_lost',  'definition' => "DATE DEFAULT NULL AFTER `location`"],
        ['name' => 'lost_time',  'definition' => "VARCHAR(50) DEFAULT NULL AFTER `date_lost`"],
        ['name' => 'image_path', 'definition' => "VARCHAR(500) DEFAULT NULL AFTER `lost_time`"],
        ['name' => 'status',     'definition' => "VARCHAR(50) DEFAULT 'active' AFTER `image_path`"],
        ['name' => 'category',   'definition' => "VARCHAR(100) DEFAULT 'General' AFTER `user_id`"],
    ],
    'found_reports' => [
        ['name' => 'title',       'definition' => "VARCHAR(255) DEFAULT NULL AFTER `category`"],
        ['name' => 'description', 'definition' => "TEXT DEFAULT NULL AFTER `title`"],
        ['name' => 'location',    'definition' => "VARCHAR(255) DEFAULT NULL AFTER `description`"],
        ['name' => 'date_found',  'definition' => "DATE DEFAULT NULL AFTER `location`"],
        ['name' => 'found_time',  'definition' => "VARCHAR(50) DEFAULT NULL AFTER `date_found`"],
        ['name' => 'image_path',  'definition' => "VARCHAR(500) DEFAULT NULL AFTER `found_time`"],
        ['name' => 'status',      'definition' => "VARCHAR(50) DEFAULT 'active' AFTER `image_path`"],
        ['name' => 'category',    'definition' => "VARCHAR(100) DEFAULT 'General' AFTER `user_id`"],
    ],
    'digital_dna' => [
        ['name' => 'report_type',   'definition' => "VARCHAR(20) DEFAULT NULL AFTER `id`"],
        ['name' => 'report_id',     'definition' => "INT DEFAULT NULL AFTER `report_type`"],
        ['name' => 'category',      'definition' => "VARCHAR(100) DEFAULT NULL AFTER `report_id`"],
        ['name' => 'encrypted_dna', 'definition' => "LONGTEXT DEFAULT NULL AFTER `category`"],
    ]
];

// Step 1: Show current table schemas
$results['current_schemas'] = [];
foreach (['lost_reports', 'found_reports', 'digital_dna'] as $table) {
    $cols = [];
    $r = $conn->query("SHOW COLUMNS FROM `$table`");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $cols[] = $row['Field'] . ' (' . $row['Type'] . ')';
        }
    }
    $results['current_schemas'][$table] = $cols;
}

// Step 2: Apply migrations
$results['migrations'] = [];
foreach ($migrations as $table => $columns) {
    foreach ($columns as $col) {
        if (columnExists($conn, $table, $col['name'])) {
            $results['migrations'][] = [
                'table' => $table,
                'column' => $col['name'],
                'action' => 'ALREADY_EXISTS'
            ];
        } else {
            $sql = "ALTER TABLE `$table` ADD COLUMN `{$col['name']}` {$col['definition']}";
            if ($conn->query($sql)) {
                $results['migrations'][] = [
                    'table' => $table,
                    'column' => $col['name'],
                    'action' => 'ADDED',
                    'sql' => $sql
                ];
            } else {
                // Try without AFTER clause (in case referenced column doesn't exist yet)
                $defNoAfter = preg_replace('/\s+AFTER\s+`[^`]+`/', '', $col['definition']);
                $sql2 = "ALTER TABLE `$table` ADD COLUMN `{$col['name']}` $defNoAfter";
                if ($conn->query($sql2)) {
                    $results['migrations'][] = [
                        'table' => $table,
                        'column' => $col['name'],
                        'action' => 'ADDED_WITHOUT_POSITION',
                        'sql' => $sql2
                    ];
                } else {
                    $results['migrations'][] = [
                        'table' => $table,
                        'column' => $col['name'],
                        'action' => 'FAILED',
                        'error' => $conn->error,
                        'sql' => $sql
                    ];
                }
            }
        }
    }
}

// Step 3: Show updated schemas
$results['updated_schemas'] = [];
foreach (['lost_reports', 'found_reports', 'digital_dna'] as $table) {
    $cols = [];
    $r = $conn->query("SHOW COLUMNS FROM `$table`");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $cols[] = $row['Field'] . ' (' . $row['Type'] . ')';
        }
    }
    $results['updated_schemas'][$table] = $cols;
}

// Step 4: Test write after migration
$testUid = 5; // Known active user
$stmt = $conn->prepare("INSERT INTO lost_reports (user_id, category, title, description, location, date_lost, lost_time, status) VALUES (?, 'Migration_Test', 'Schema_Test', 'auto-test after migration', 'N/A', CURDATE(), CURTIME(), 'active')");
if ($stmt) {
    $stmt->bind_param("i", $testUid);
    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        $results['post_migration_write_test'] = 'SUCCESS';
        $results['test_inserted_id'] = $newId;
        $conn->query("DELETE FROM lost_reports WHERE id = $newId AND title = 'Schema_Test'");
        $results['test_cleanup'] = 'DELETED';
    } else {
        $results['post_migration_write_test'] = 'EXECUTE_FAILED';
        $results['test_error'] = $stmt->error;
    }
    $stmt->close();
} else {
    $results['post_migration_write_test'] = 'PREPARE_FAILED';
    $results['test_error'] = $conn->error;
}

$results['status'] = 'MIGRATION_COMPLETE';
echo json_encode($results, JSON_PRETTY_PRINT);
