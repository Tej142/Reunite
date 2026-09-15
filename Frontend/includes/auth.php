<?php
/**
 * Authentication and Session Management Helper for Reunite
 */

function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function is_logged_in() {
    init_session();
    return isset($_SESSION['user']) && !empty($_SESSION['user']);
}

function get_current_user_data() {
    init_session();
    return $_SESSION['user'] ?? null;
}

function login_user($userData) {
    init_session();
    $_SESSION['user'] = array(
        'name' => $userData['name'] ?? 'Student',
        'pin' => $userData['pin'] ?? '24155-cm-002',
        'email' => $userData['email'] ?? 'student@college.edu',
        'college' => $userData['college'] ?? 'Sri Venkateswara Govt Polytechnic',
        'branch' => $userData['branch'] ?? 'Computer Science',
        'logged_in_at' => time()
    );
    return true;
}

function logout_user() {
    init_session();
    unset($_SESSION['user']);
    session_destroy();
}

function require_login() {
    init_session();
    if (!is_logged_in()) {
        $current_page = urlencode($_SERVER['REQUEST_URI']);
        header("Location: login.php?redirect=" . $current_page);
        exit;
    }
}

function update_user_profile($updatedData) {
    init_session();
    if (!isset($_SESSION['user'])) {
        return false;
    }
    foreach ($updatedData as $key => $val) {
        if ($key !== 'logged_in_at' && $key !== 'pin') { // keep pin protected as unique identifier
            $_SESSION['user'][$key] = $val;
        }
    }
    return true;
}
