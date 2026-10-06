<?php
/**
 * Reunite Platform Admin Authentication Handler
 * Authenticates administrators and manages privileged session states
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';

init_session();
$data = getRequestData();
$isApi = isApiRequest();

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION['role'] = 'user';
    unset($_SESSION['admin_logged_in']);
    setcookie('reunite_admin_session', '', ['expires' => time() - 3600, 'path' => '/']);
    if ($isApi) {
        sendJsonResponse(true, "Admin session terminated successfully.", ['redirect' => '../admin/login.php']);
    } else {
        header("Location: ../../Frontend/admin/login.php");
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    if ($isApi) {
        sendJsonResponse(false, "Method not allowed. Use POST.", [], 405);
    } else {
        header("Location: ../../Frontend/admin/login.php");
        exit();
    }
}

$identifier = trim($data['identifier'] ?? $data['email'] ?? $data['username'] ?? '');
$password = trim($data['password'] ?? '');

if (empty($identifier) || empty($password)) {
    if ($isApi) {
        sendJsonResponse(false, "Please provide Admin identifier and password.", [], 400);
    } else {
        header("Location: ../Frontend/admin/login.php?error=" . urlencode("Please provide identifier and password."));
        exit();
    }
}

// 1. Ensure dedicated admins table exists & default admin is provisioned
ensureAdminsTable();

$defaultAdminUser = get_config_val('ADMIN_EMAIL', 'admin');
$defaultAdminPass = get_config_val('ADMIN_PASSWORD', 'pass');

/**
 * Set session + admin cookie, then redirect or return JSON.
 * Using a cookie alongside session fixes shared-host session persistence.
 */
function adminLoginSuccess($adminData, $isApi) {
    $_SESSION['admin_id']        = $adminData['admin_id'];
    $_SESSION['full_name']       = $adminData['full_name'];
    $_SESSION['username']        = $adminData['username'];
    $_SESSION['email']           = $adminData['email'];
    $_SESSION['role']            = 'admin';
    $_SESSION['admin_logged_in'] = true;

    // Fallback admin cookie — ensures auth survives across AJAX-to-page-load
    // on shared hosting environments where sessions can be unreliable.
    $payload = base64_encode(json_encode([
        'admin_id' => $adminData['admin_id'],
        'username' => $adminData['username'],
        'ts'       => time()
    ]));
    setcookie('reunite_admin_session', $payload, [
        'expires'  => time() + 3600 * 8,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    if ($isApi) {
        sendJsonResponse(true, "Admin authentication successful!", [
            'redirect' => '../Frontend/admin/index.php',
            'admin'    => [
                'admin_id'  => $adminData['admin_id'],
                'full_name' => $adminData['full_name'],
                'username'  => $adminData['username'],
                'role'      => 'admin'
            ]
        ]);
    } else {
        header("Location: ../Frontend/admin/index.php");
        exit();
    }
}

// 2. Query DB for existing admin in `admins` table
if ($conn) {
    $stmt = $conn->prepare("SELECT admin_id, username, full_name, email, password_hash, role, status FROM admins WHERE username = ? OR email = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
            $admin = $res->fetch_assoc();
            $stmt->close();

            if (isset($admin['status']) && strtolower($admin['status']) === 'suspended') {
                if ($isApi) {
                    sendJsonResponse(false, "This administrator account is currently suspended.", [], 403);
                } else {
                    header("Location: ../Frontend/admin/login.php?error=" . urlencode("This administrator account is currently suspended."));
                    exit();
                }
            }

            $isDirectMasterPass = ($password === $defaultAdminPass && in_array(strtolower($identifier), ['admin', 'admin@reunite.site']));

            if (password_verify($password, $admin['password_hash']) || $isDirectMasterPass) {
                if ($isDirectMasterPass && !password_verify($password, $admin['password_hash'])) {
                    $newHash = password_hash($defaultAdminPass, PASSWORD_DEFAULT);
                    $up = $conn->prepare("UPDATE admins SET password_hash = ? WHERE admin_id = ?");
                    if ($up) {
                        $up->bind_param("si", $newHash, $admin['admin_id']);
                        $up->execute();
                        $up->close();
                    }
                }
                @$conn->query("UPDATE admins SET last_login = NOW() WHERE admin_id = " . (int)$admin['admin_id']);
                logAccess($admin['admin_id'], 'Platform Admin Portal Login');
                adminLoginSuccess($admin, $isApi);
            }
        } else {
            $stmt->close();
        }
    }
}

// 3. Fallback Master Authentication (works even without DB)
if (
    (strtolower($identifier) === 'admin' || strtolower($identifier) === 'admin@reunite.site')
    && $password === $defaultAdminPass
) {
    adminLoginSuccess([
        'admin_id'  => 1,
        'full_name' => 'System Administrator',
        'username'  => 'admin',
        'email'     => 'admin@reunite.site'
    ], $isApi);
}

// 4. Authentication Failed
if ($isApi) {
    sendJsonResponse(false, "Invalid administrator credentials. Access denied.", [], 401);
} else {
    header("Location: ../Frontend/admin/login.php?error=" . urlencode("Invalid administrator credentials. Access denied."));
    exit();
}
