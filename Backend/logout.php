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
session_destroy();

if (isApiRequest()) {
    sendJsonResponse(true, "Successfully logged out.", ['redirect' => FRONTEND_URL . "/login.php"]);
} else {
    header("Location: " . FRONTEND_URL . "/login.php?msg=" . urlencode("You have been signed out."));
    exit();
}
