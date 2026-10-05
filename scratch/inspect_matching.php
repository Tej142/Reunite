<?php
require_once dirname(__DIR__) . '/Backend/config/config.php';
if ($conn) {
    // Show all tables
    $tables = $conn->query('SHOW TABLES');
    echo "=== DB TABLES ===\n";
    while($row = $tables->fetch_row()) echo " - " . $row[0] . "\n";

    // Check schema for key tables
    echo "\n=== lost_reports columns ===\n";
    $cols = $conn->query('DESCRIBE lost_reports');
    while($r = $cols->fetch_assoc()) echo " {$r['Field']} ({$r['Type']})\n";

    echo "\n=== found_reports columns ===\n";
    $cols = $conn->query('DESCRIBE found_reports');
    while($r = $cols->fetch_assoc()) echo " {$r['Field']} ({$r['Type']})\n";

    echo "\n=== notifications columns ===\n";
    $q = $conn->query('DESCRIBE notifications');
    if ($q) { while($r = $q->fetch_assoc()) echo " {$r['Field']} ({$r['Type']})\n"; }
    else echo " [TABLE MISSING]\n";

    echo "\n=== matches columns ===\n";
    $q = $conn->query('DESCRIBE matches');
    if ($q) { while($r = $q->fetch_assoc()) echo " {$r['Field']} ({$r['Type']})\n"; }
    else echo " [TABLE MISSING]\n";

    echo "\n=== digital_dna columns ===\n";
    $q = $conn->query('DESCRIBE digital_dna');
    if ($q) { while($r = $q->fetch_assoc()) echo " {$r['Field']} ({$r['Type']})\n"; }
    else echo " [TABLE MISSING]\n";

    // Counts
    echo "\n=== ROW COUNTS ===\n";
    foreach(['users','lost_reports','found_reports','notifications','matches','digital_dna','access_logs'] as $t) {
        $r = $conn->query("SELECT COUNT(*) c FROM `$t`");
        $n = $r ? $r->fetch_assoc()['c'] : 'N/A';
        echo " $t: $n\n";
    }
} else {
    echo "NO DB CONNECTION\n";
}
