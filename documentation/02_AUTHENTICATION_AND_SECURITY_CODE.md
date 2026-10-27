# Authentication & Multi-Tenant Security Code

## 1. Unified Authentication Processor
**Source File:** `Frontend/api/auth.php`

```php
<?php
/**
 * Frontend Auth API Bridge
 * Dispatches to Backend authentication & profile handlers
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../Backend/config/config.php';
require_once __DIR__ . '/../../Backend/functions.php';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? $_GET['action'] ?? '';
global $conn;

if ($action === 'login') {
    $identifier = trim($input['identifier'] ?? $input['pin'] ?? $input['email'] ?? '');
    $password = trim($input['password'] ?? '');
    $role = strtolower(trim($input['role'] ?? 'student')); // 'student' or 'campus_admin'

    if (empty($identifier) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
        exit;
    }

    $defaultAdminUser = function_exists('get_config_val') ? get_config_val('ADMIN_EMAIL', 'admin') : 'admin';
    $defaultAdminPass = function_exists('get_config_val') ? get_config_val('ADMIN_PASSWORD', 'pass') : 'pass';

    // ─────────────────────────────────────────────────────────────
    // 1. CAMPUS ADMIN EXPLICIT LOGIN
    // ─────────────────────────────────────────────────────────────
    if ($role === 'campus_admin') {
        if ($conn) {
            ensureAdminsTable();
            $stmt = $conn->prepare("SELECT admin_id, username, full_name, email, password_hash, role, status, campus_id FROM admins WHERE username = ? OR email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("ss", $identifier, $identifier);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows === 1) {
                    $admin = $res->fetch_assoc();
                    $stmt->close();

                    if (isset($admin['status']) && strtolower($admin['status']) === 'suspended') {
                        echo json_encode(['success' => false, 'message' => 'This administrator account is currently suspended.']);
                        exit;
                    }

                    $isMasterPass = ($password === $defaultAdminPass && in_array(strtolower($identifier), ['admin', 'admin@reunite.site']));

                    if (password_verify($password, $admin['password_hash']) || $isMasterPass) {
                        @$conn->query("UPDATE admins SET last_login = NOW() WHERE admin_id = " . (int)$admin['admin_id']);
                        login_admin($admin);
                        if (function_exists('logAccess')) {
                            logAccess($admin['admin_id'], 'Campus Admin Portal Login');
                        }
                        echo json_encode([
                            'success'  => true,
                            'redirect' => 'admin/index.php',
                            'role'     => $admin['role'] ?? 'campus_admin',
                            'message'  => 'Welcome back, ' . ($admin['full_name'] ?? 'Administrator') . '!'
                        ]);
                        exit;
                    }
                } else {
                    $stmt->close();
                }
            }
        }

        // Fallback Master Admin credentials
        if ((strtolower($identifier) === 'admin' || strtolower($identifier) === 'admin@reunite.site') && $password === $defaultAdminPass) {
            login_admin([
                'admin_id'  => 1,
                'full_name' => 'System Administrator',
                'username'  => 'admin',
                'email'     => 'admin@reunite.site',
                'role'      => 'master_admin',
                'campus_id' => null
            ]);
            echo json_encode([
                'success'  => true,
                'redirect' => 'admin/index.php',
                'role'     => 'master_admin',
                'message'  => 'Welcome back, Master Administrator!'
            ]);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Invalid administrator username/email or password.']);
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 2. STUDENT LOGIN (WITH SMART ADMIN FALLBACK)
    // ─────────────────────────────────────────────────────────────
    if ($conn) {
        $encrypted_id = encryptData($identifier);
        $sql = "SELECT user_id, full_name, pin, email, phone, password_hash, trust_score, role, status, college, branch, campus_id FROM users WHERE pin = ? OR email = ? OR email = ? OR phone = ? OR phone = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sssss", $identifier, $encrypted_id, $identifier, $encrypted_id, $identifier);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows === 1) {
                $user = $res->fetch_assoc();
                $stmt->close();

                if (password_verify($password, $user['password_hash'])) {
                    login_user([
                        'user_id' => $user['user_id'],
                        'name' => $user['full_name'],
                        'pin' => $user['pin'] ?: $identifier,
                        'email' => decryptData($user['email']),
                        'college' => $user['college'],
                        'branch' => $user['branch'],
                        'campus_id' => $user['campus_id'] ?? null,
                        'trust_score' => $user['trust_score'],
                        'role' => $user['role']
                    ]);
                    echo json_encode(['success' => true, 'redirect' => 'home.php', 'role' => 'student']);
                    exit;
                }
            } else {
                $stmt->close();
            }
        }
    }
}
```

---

## 2. Session-Only Admin Security & Zero-Cookie Policy
**Source File:** `Backend/admin_auth.php`

```php
<?php
/**
 * Reunite Platform Admin Authentication Handler
 * Authenticates administrators and manages privileged session states
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle Logout: Cleanly wipes in-memory session and purges client cookies
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION['role'] = 'user';
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['reunite_admin_auth']);
    unset($_SESSION['admin_id']);
    unset($_SESSION['username']);
    unset($_SESSION['full_name']);
    unset($_SESSION['email']);
    unset($_SESSION['admin_campus_id']);
    unset($_SESSION['admin_campus_name']);
    unset($_SESSION['admin_campus_code']);

    // Ensure client browser has no residual admin cookies
    setcookie('reunite_admin_session', '', time() - 86400, '/');
    unset($_COOKIE['reunite_admin_session']);

    header("Location: ../Frontend/login.php?role=campus_admin&logged_out=1");
    exit();
}

/**
 * Commits verified admin credentials strictly into server-side $_SESSION memory
 */
function adminLoginSuccess($adminData, $isApi) {
    global $conn;
    $rawRole = strtolower($adminData['role'] ?? 'campus_admin');
    $campusId = !empty($adminData['campus_id']) ? (int)$adminData['campus_id'] : null;
    $isMaster = ($rawRole === 'master_admin' || $rawRole === 'superadmin' || ($campusId === null && in_array($rawRole, ['admin', 'superadmin', 'master_admin'])));
    $normalizedRole = $isMaster ? 'master_admin' : 'campus_admin';

    $_SESSION['admin_id']           = (int)$adminData['admin_id'];
    $_SESSION['full_name']          = $adminData['full_name'];
    $_SESSION['username']           = $adminData['username'];
    $_SESSION['email']              = $adminData['email'];
    $_SESSION['role']               = $normalizedRole;
    $_SESSION['admin_logged_in']    = true;
    $_SESSION['reunite_admin_auth'] = true;
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

    // SECURITY POLICY: No persistent authentication cookies are issued to administrators.
    if (isset($_COOKIE['reunite_admin_session'])) {
        setcookie('reunite_admin_session', '', time() - 86400, '/');
        unset($_COOKIE['reunite_admin_session']);
    }

    header("Location: ../Frontend/admin/index.php");
    exit();
}
```

---

## 3. Multi-Tenant Campus Isolation & IDOR Protection
**Source File:** `Backend/admin_scope.php`

```php
<?php
/**
 * Campus Multi-Tenant Authorization Engine
 * Enforces institutional boundaries and protects against IDOR attacks.
 */

function get_admin_session() {
    $adminId = $_SESSION['admin_id'] ?? null;
    $rawRole = strtolower($_SESSION['role'] ?? $_SESSION['reunite_admin_role'] ?? '');

    if (!$adminId) {
        if (!empty($_SESSION['reunite_admin_auth']) && $_SESSION['reunite_admin_auth'] === true) {
            return [
                'admin_id'       => 1,
                'username'       => 'admin',
                'full_name'      => 'System Administrator',
                'role'           => 'master_admin',
                'is_master'      => true,
                'is_campus_admin'=> false,
                'campus_id'      => null,
                'campus_name'    => 'All Campuses (Global)',
                'campus_code'    => 'ALL'
            ];
        }
        return null;
    }

    $isMaster = ($rawRole === 'master_admin' || $rawRole === 'superadmin' || empty($_SESSION['admin_campus_id']));
    return [
        'admin_id'       => (int)$adminId,
        'username'       => $_SESSION['username'] ?? 'admin',
        'full_name'      => $_SESSION['full_name'] ?? 'Administrator',
        'role'           => $isMaster ? 'master_admin' : 'campus_admin',
        'is_master'      => $isMaster,
        'is_campus_admin'=> !$isMaster,
        'campus_id'      => $_SESSION['admin_campus_id'] ?? null,
        'campus_name'    => $_SESSION['admin_campus_name'] ?? 'Assigned Campus',
        'campus_code'    => $_SESSION['admin_campus_code'] ?? 'CAMPUS'
    ];
}

function apply_campus_scope_sql($sql, $tableAlias = 'i', $adminSession = null) {
    if (!$adminSession) $adminSession = get_admin_session();
    if (!$adminSession) return $sql . " AND 1=0";

    if ($adminSession['is_master']) {
        if (!empty($_GET['campus_id'])) {
            $filterCampusId = (int)$_GET['campus_id'];
            return $sql . " AND {$tableAlias}.campus_id = {$filterCampusId}";
        }
        return $sql;
    }

    $campusId = (int)$adminSession['campus_id'];
    return $sql . " AND {$tableAlias}.campus_id = {$campusId}";
}

function assert_campus_item_access($itemId, $itemType = 'lost', $adminSession = null) {
    global $conn;
    if (!$adminSession) $adminSession = get_admin_session();
    if (!$adminSession) return false;
    if ($adminSession['is_master']) return true;

    $table = ($itemType === 'found') ? 'found_reports' : 'lost_reports';
    $stmt = $conn->prepare("SELECT campus_id FROM {$table} WHERE id = ? LIMIT 1");
    if (!$stmt) return false;

    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ($row && (int)$row['campus_id'] === (int)$adminSession['campus_id']);
}
```
