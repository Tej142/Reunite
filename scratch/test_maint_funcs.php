<?php
require_once dirname(__DIR__) . '/Backend/config/config.php';
require_once dirname(__DIR__) . '/Backend/functions.php';

// Test getMaintenanceSettings()
$s = getMaintenanceSettings();
echo 'getMaintenanceSettings() works: ' . ($s !== null ? 'YES' : 'NO') . PHP_EOL;
echo 'enabled: ' . ($s['enabled'] ? 'true' : 'false') . PHP_EOL;
echo 'message: ' . ($s['message'] ?: '(none)') . PHP_EOL;
echo 'eta: ' . ($s['eta'] ?: '(none)') . PHP_EOL;
echo 'support_email: ' . ($s['support_email'] ?: '(none)') . PHP_EOL;

// Verify DB table
if ($conn) {
    $q   = $conn->query('SELECT id, enabled FROM maintenance_settings ORDER BY id DESC LIMIT 1');
    $row = $q->fetch_assoc();
    echo 'DB row id=' . $row['id'] . ' enabled=' . $row['enabled'] . PHP_EOL;
}
echo 'All checks passed.' . PHP_EOL;
