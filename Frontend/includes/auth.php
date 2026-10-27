<?php
/**
 * Authentication and Session Management Helper for Reunite
 * Integrated with Backend lost_connect_db configuration
 */

require_once __DIR__ . '/../../Backend/config/config.php';
require_once __DIR__ . '/../../Backend/functions.php';
require_once __DIR__ . '/orbi.php';

if (!function_exists('init_session')) {
    function init_session() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            try {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                $_SESSION['csrf_token'] = md5(uniqid((string)mt_rand(), true));
            }
        }
        if (function_exists('enforceMaintenanceMode')) {
            enforceMaintenanceMode();
        }
    }
}

// Immediately initialize session and enforce maintenance checks
init_session();

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
    $userId = $_SESSION['user_id'] ?? ($_SESSION['user']['user_id'] ?? ($_SESSION['user']['id'] ?? null));
    if (function_exists('getCurrentUser')) {
        $user = getCurrentUser();
        if ($user) {
            $user['user_id'] = $user['user_id'] ?? $userId;
            $user['name'] = $user['full_name'] ?? ($user['name'] ?? ($_SESSION['full_name'] ?? 'Student'));
            return $user;
        }
    }
    $name = $_SESSION['full_name'] ?? ($_SESSION['user']['name'] ?? 'Student');
    $bCode = $_SESSION['branch'] ?? ($_SESSION['user']['branch'] ?? 'cme');
    return [
        'user_id' => $userId,
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
    $_SESSION['campus_id'] = $userData['campus_id'] ?? null;
    $_SESSION['trust_score'] = $userData['trust_score'] ?? 100;
    $_SESSION['role'] = $userData['role'] ?? 'user';

    $_SESSION['user'] = array(
        'name' => $_SESSION['full_name'],
        'pin' => $_SESSION['pin'],
        'dob' => $_SESSION['dob'],
        'email' => $_SESSION['email'],
        'phone' => $_SESSION['phone'],
        'college' => $_SESSION['college'],
        'campus_id' => $_SESSION['campus_id'],
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

function login_admin($adminData) {
    init_session();
    global $conn;
    $rawRole = strtolower($adminData['role'] ?? 'campus_admin');
    $campusId = !empty($adminData['campus_id']) ? (int)$adminData['campus_id'] : null;
    $isMaster = ($rawRole === 'master_admin' || $rawRole === 'superadmin' || ($campusId === null && in_array($rawRole, ['admin', 'superadmin', 'master_admin'])));
    $normalizedRole = $isMaster ? 'master_admin' : 'campus_admin';

    $_SESSION['admin_id']           = (int)($adminData['admin_id'] ?? 1);
    $_SESSION['full_name']          = $adminData['full_name'] ?? 'Campus Administrator';
    $_SESSION['username']           = $adminData['username'] ?? 'admin';
    $_SESSION['email']              = $adminData['email'] ?? '';
    $_SESSION['role']               = $normalizedRole;
    $_SESSION['admin_logged_in']    = true;
    $_SESSION['reunite_admin_auth']  = true;
    $_SESSION['admin_campus_id']    = $campusId;

    if ($campusId && $conn) {
        $cStmt = $conn->prepare("SELECT name, code FROM campuses WHERE id = ? LIMIT 1");
        if ($cStmt) {
            $cStmt->bind_param("i", $campusId);
            $cStmt->execute();
            if ($cRow = $cStmt->get_result()->fetch_assoc()) {
                $_SESSION['admin_campus_name'] = $cRow['name'];
                $_SESSION['admin_campus_code'] = $cRow['code'];
            }
            $cStmt->close();
        }
    } else {
        $_SESSION['admin_campus_name'] = 'All Campuses (Global)';
        $_SESSION['admin_campus_code'] = 'ALL';
    }

    $payload = base64_encode(json_encode([
        'admin_id'  => (int)$_SESSION['admin_id'],
        'username'  => $_SESSION['username'],
        'role'      => $normalizedRole,
        'campus_id' => $campusId,
        'ts'        => time()
    ]));
    setcookie('reunite_admin_session', $payload, [
        'expires'  => time() + 3600 * 8,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
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

function is_admin() {
    init_session();
    if (!empty($_SESSION['reunite_admin_auth']) && $_SESSION['reunite_admin_auth'] === true) {
        return true;
    }
    if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        return true;
    }
    if (!empty($_SESSION['role']) && in_array(strtolower($_SESSION['role']), ['admin', 'superadmin', 'moderator', 'master_admin', 'campus_admin'])) {
        return true;
    }
    // Shared hosting fallback: verify admin session cookie against database
    if (!empty($_COOKIE['reunite_admin_session'])) {
        try {
            $adminCookie = json_decode(base64_decode($_COOKIE['reunite_admin_session']), true);
            if (is_array($adminCookie) && !empty($adminCookie['ts']) && (time() - $adminCookie['ts'] < 86400 * 7)) {
                $cAdminId = (int)($adminCookie['admin_id'] ?? 0);
                $cUsername = trim($adminCookie['username'] ?? '');
                
                global $conn;
                if ($conn && $cAdminId > 0 && !empty($cUsername)) {
                    $cStmt = $conn->prepare("SELECT admin_id, username, full_name, email, role, campus_id, status FROM admins WHERE admin_id = ? AND username = ? LIMIT 1");
                    if ($cStmt) {
                        $cStmt->bind_param("is", $cAdminId, $cUsername);
                        $cStmt->execute();
                        $cRes = $cStmt->get_result();
                        if ($cRes && $row = $cRes->fetch_assoc()) {
                            if (strtolower($row['status'] ?? '') !== 'suspended') {
                                $rawRole = strtolower($row['role'] ?? 'admin');
                                $campusId = !empty($row['campus_id']) ? (int)$row['campus_id'] : null;
                                $isMaster = ($rawRole === 'master_admin' || $rawRole === 'superadmin' || ($campusId === null && in_array($rawRole, ['admin', 'superadmin', 'master_admin'])));
                                $normalizedRole = $isMaster ? 'master_admin' : 'campus_admin';

                                $_SESSION['reunite_admin_auth'] = true;
                                $_SESSION['admin_logged_in'] = true;
                                $_SESSION['admin_id'] = $row['admin_id'];
                                $_SESSION['username'] = $row['username'];
                                $_SESSION['full_name'] = $row['full_name'];
                                $_SESSION['email'] = $row['email'];
                                $_SESSION['role'] = $normalizedRole;
                                $_SESSION['admin_campus_id'] = $campusId;

                                if ($campusId) {
                                    $cpStmt = $conn->prepare("SELECT name, code FROM campuses WHERE id = ? LIMIT 1");
                                    if ($cpStmt) {
                                        $cpStmt->bind_param("i", $campusId);
                                        $cpStmt->execute();
                                        if ($cpRow = $cpStmt->get_result()->fetch_assoc()) {
                                            $_SESSION['admin_campus_name'] = $cpRow['name'];
                                            $_SESSION['admin_campus_code'] = $cpRow['code'];
                                        }
                                        $cpStmt->close();
                                    }
                                } else {
                                    $_SESSION['admin_campus_name'] = 'All Campuses (Global)';
                                    $_SESSION['admin_campus_code'] = 'ALL';
                                }

                                $cStmt->close();
                                return true;
                            }
                        }
                        $cStmt->close();
                    }
                } elseif ($cAdminId === 1 && $cUsername === 'admin') {
                    // Fallback for master administrator when DB is connecting
                    $_SESSION['reunite_admin_auth'] = true;
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = 1;
                    $_SESSION['username'] = 'admin';
                    $_SESSION['role'] = 'master_admin';
                    $_SESSION['admin_campus_id'] = null;
                    $_SESSION['admin_campus_name'] = 'All Campuses (Global)';
                    $_SESSION['admin_campus_code'] = 'ALL';
                    return true;
                }
            }
        } catch (Exception $e) {
            // Malformed cookie ignored
        }
    }
    return false;
}

function require_admin() {
    init_session();
    if (!is_admin()) {
        // If accessed within the admin/ subdirectory, redirect to login.php
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (strpos($script, '/admin/') !== false) {
            header("Location: login.php");
            exit();
        }
        header("Location: ../admin.php");
        exit();
    }
}

