<?php
/**
 * Reunite User Logout Handler
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';

if (isUserLoggedIn()) {
    $userId = $_SESSION['user_id'];
    logAccess($userId, 'User Logged Out');
}

// Clear all session data
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear persistent cookies
foreach (['reunite_auth_token', 'reunite_remembered_pin', 'reunite_remember_me', 'reunite_user_pin', 'reunite_admin_session'] as $c) {
    setcookie($c, '', time() - 86400, '/');
    if (!empty($_SERVER['HTTP_HOST'])) {
        setcookie($c, '', time() - 86400, '/', $_SERVER['HTTP_HOST']);
    }
    unset($_COOKIE[$c]);
}

session_destroy();

if (isApiRequest()) {
    sendJsonResponse(true, "Successfully logged out.", ['redirect' => FRONTEND_URL . "/login.php?logged_out=1"]);
} else {
    header("Location: " . FRONTEND_URL . "/login.php?logged_out=1");
    exit();
}
