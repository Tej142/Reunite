<?php
require_once __DIR__ . '/includes/auth.php';
logout_user();

// Explicit safety clearing
foreach (['reunite_auth_token', 'reunite_remembered_pin', 'reunite_remember_me', 'reunite_user_pin', 'reunite_admin_session'] as $c) {
    setcookie($c, '', time() - 86400, '/');
    if (!empty($_SERVER['HTTP_HOST'])) {
        setcookie($c, '', time() - 86400, '/', $_SERVER['HTTP_HOST']);
    }
    unset($_COOKIE[$c]);
}

header("Location: login.php?logged_out=1");
exit;
