<?php
require_once dirname(__DIR__) . '/Backend/config/config.php';
if ($conn) {
    // Check if maintenance_settings table exists
    $r = $conn->query("SHOW TABLES LIKE 'maintenance_settings'");
    echo "maintenance_settings table exists: " . ($r->num_rows > 0 ? "YES" : "NO") . "\n";
    
    // Show access_logs columns
    $r2 = $conn->query("SHOW COLUMNS FROM access_logs");
    echo "access_logs columns:\n";
    while ($row = $r2->fetch_assoc()) {
        echo "  " . $row['Field'] . " " . $row['Type'] . "\n";
    }
    
    // Check users table for role column
    $r3 = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    echo "users.role exists: " . ($r3->num_rows > 0 ? "YES" : "NO") . "\n";
} else {
    echo "No DB connection\n";
}
