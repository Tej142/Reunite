<?php
/**
 * Authentication and Session Management Helper for Reunite
 * Integrated with Backend lost_connect_db configuration
 */

require_once __DIR__ . '/../../Backend/config/config.php';
require_once __DIR__ . '/../../Backend/functions.php';

function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function set_permanent_auth_cookie($userData) {
    $payload = [
        'user_id' => $userData['user_id'] ?? null,
        'pin' => $userData['pin'] ?? '',
        'email' => $userData['email'] ?? '',
        'created' => time()
    ];
    $encryptedToken = encryptData(json_encode($payload));
    // 10-Year Permanent Cookie Lifetime (No expiration)
    $lifetime = time() + (10 * 365 * 86400);
    setcookie('reunite_auth_token', $encryptedToken, [
        'expires' => $lifetime,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    if (!empty($userData['pin'])) {
        setcookie('reunite_remembered_pin', $userData['pin'], [
            'expires' => $lifetime,
            'path' => '/',
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
    }
}

function check_permanent_auth_cookie() {
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return true;
    }

    if (!empty($_COOKIE['reunite_auth_token'])) {
        try {
            $decrypted = decryptData($_COOKIE['reunite_auth_token']);
            $payload = json_decode($decrypted, true);
            if ($payload && (!empty($payload['user_id']) || !empty($payload['pin']))) {
                global $conn;
                $userId = $payload['user_id'] ?? null;
                $pin = $payload['pin'] ?? '';

                if ($conn) {
                    $stmt = $conn->prepare("SELECT user_id, full_name, pin, email, phone, trust_score, role, status, dob, college, branch FROM users WHERE user_id = ? OR pin = ? LIMIT 1");
                    if ($stmt) {
                        $stmt->bind_param("is", $userId, $pin);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        if ($row = $res->fetch_assoc()) {
                            $stmt->close();
                            if (!isset($row['status']) || strtolower($row['status']) !== 'blocked') {
                                // Auto-Login user session seamlessly
                                login_user([
                                    'user_id' => $row['user_id'],
                                    'name' => $row['full_name'],
                                    'pin' => $row['pin'] ?: $pin,
                                    'dob' => $row['dob'] ?? '',
                                    'email' => decryptData($row['email']),
                                    'phone' => decryptData($row['phone']),
                                    'college' => $row['college'],
                                    'branch' => $row['branch'],
                                    'trust_score' => $row['trust_score'] ?? 100,
                                    'role' => $row['role'] ?? 'user'
                                ], false); // do not reset cookie again
                                return true;
                            }
                        } else {
                            $stmt->close();
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Permanent cookie authentication error: " . $e->getMessage());
        }
    }
    return false;
}

function is_logged_in() {
    init_session();
    if ((isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) || (isset($_SESSION['user']) && !empty($_SESSION['user']))) {
        return true;
    }
    return check_permanent_auth_cookie();
}

function get_current_user_data() {
    init_session();
    if (function_exists('getCurrentUser')) {
        $user = getCurrentUser();
        if ($user) {
            $user['name'] = $user['full_name'] ?? ($user['name'] ?? ($_SESSION['full_name'] ?? 'Student'));
            return $user;
        }
    }
    $name = $_SESSION['full_name'] ?? ($_SESSION['user']['name'] ?? 'Student');
    $bCode = $_SESSION['branch'] ?? ($_SESSION['user']['branch'] ?? 'cme');
    return [
        'name' => $name,
        'full_name' => $name,
        'pin' => $_SESSION['pin'] ?? ($_SESSION['user']['pin'] ?? ''),
        'dob' => $_SESSION['dob'] ?? ($_SESSION['user']['dob'] ?? ''),
        'email' => $_SESSION['email'] ?? ($_SESSION['user']['email'] ?? ''),
        'phone' => $_SESSION['phone'] ?? ($_SESSION['user']['phone'] ?? ''),
        'college' => $_SESSION['college'] ?? ($_SESSION['user']['college'] ?? 'Sri Venkateswara Govt Polytechnic'),
        'branch' => $bCode,
        'branch_name' => function_exists('get_branch_name') ? get_branch_name($bCode) : $bCode,
        'trust_score' => $_SESSION['trust_score'] ?? 100
    ];
}

function login_user($userData, $setCookie = true) {
    init_session();
    $_SESSION['user_id'] = $userData['user_id'] ?? 1;
    $_SESSION['full_name'] = $userData['full_name'] ?? $userData['name'] ?? 'Student';
    $_SESSION['pin'] = $userData['pin'] ?? '24155-cm-002';
    $_SESSION['dob'] = $userData['dob'] ?? '';
    $_SESSION['email'] = $userData['email'] ?? 'student@college.edu';
    $_SESSION['phone'] = $userData['phone'] ?? '+91 98765 43210';
    $_SESSION['college'] = $userData['college'] ?? 'Sri Venkateswara Govt Polytechnic';
    $_SESSION['branch'] = $userData['branch'] ?? 'Computer Science';
    $_SESSION['trust_score'] = $userData['trust_score'] ?? 100;
    $_SESSION['role'] = $userData['role'] ?? 'user';

    $_SESSION['user'] = array(
        'name' => $_SESSION['full_name'],
        'pin' => $_SESSION['pin'],
        'dob' => $_SESSION['dob'],
        'email' => $_SESSION['email'],
        'phone' => $_SESSION['phone'],
        'college' => $_SESSION['college'],
        'branch' => $_SESSION['branch'],
        'trust_score' => $_SESSION['trust_score'],
        'logged_in_at' => time()
    );

    // Automatically set permanent cookie with no expiration
    if ($setCookie) {
        set_permanent_auth_cookie($userData);
    }
    return true;
}

function logout_user() {
    init_session();
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    // Delete permanent authentication cookie
    setcookie('reunite_auth_token', '', [
        'expires' => time() - 3600,
        'path' => '/'
    ]);
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
    foreach ($updatedData as $key => $val) {
        if ($key !== 'logged_in_at' && $key !== 'pin') {
            $_SESSION[$key] = $val;
            if (isset($_SESSION['user'])) {
                $_SESSION['user'][$key] = $val;
            }
        }
    }
    return true;
}
