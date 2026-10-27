<?php
/**
 * Unified Admin Portal Gateway
 * All administrative authentication is consolidated into the centralized portal:
 * Frontend/login.php?role=campus_admin
 */
require_once __DIR__ . '/../includes/auth.php';
init_session();

// If already authenticated as administrator, go directly to Command Center
if (is_admin()) {
    header("Location: index.php");
    exit();
}

$queryParams = ['role' => 'campus_admin'];
if (isset($_GET['logged_out']) && $_GET['logged_out'] == '1') {
    $queryParams['logged_out'] = '1';
}
if (!empty($_GET['error'])) {
    $queryParams['error'] = $_GET['error'];
}

header("Location: ../login.php?" . http_build_query($queryParams));
exit();
