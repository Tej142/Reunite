<?php
/**
 * Global Head Branding & Icons Partial
 * Injects favicons, Apple touch icon, PWA manifest, and brand theme colors.
 */
$_isAdminDir = (basename(dirname($_SERVER['PHP_SELF'])) === 'admin');
$_assetPrefix = $_isAdminDir ? '../' : '';
?>
<!-- Reunite Platform Brand Favicons & App Icons -->
<link rel="icon" href="<?= $_assetPrefix ?>img/favicon.ico" sizes="48x48">
<link rel="icon" href="<?= $_assetPrefix ?>img/favicon.svg" type="image/svg+xml">
<link rel="icon" type="image/png" sizes="32x32" href="<?= $_assetPrefix ?>img/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= $_assetPrefix ?>img/favicon-16x16.png">
<link rel="apple-touch-icon" sizes="180x180" href="<?= $_assetPrefix ?>img/apple-touch-icon.png">
<link rel="manifest" href="<?= $_assetPrefix ?>manifest.webmanifest">
<meta name="theme-color" content="#C1592F">
<meta name="apple-mobile-web-app-title" content="Reunite">
<meta name="application-name" content="Reunite">
