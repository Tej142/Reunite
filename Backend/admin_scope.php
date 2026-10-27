<?php
/**
 * Reunite Platform - Central Admin Authentication & Scoping Helper
 * 
 * Provides unified, single-source-of-truth authorization for:
 * 1. MASTER ADMIN (global system management, unconstrained access)
 * 2. CAMPUS ADMIN (strictly scoped to their own campus)
 * 
 * Enforces server-side query scoping, anti-IDOR checks, and audit logging.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Retrieves the current authenticated administrator's verified profile and campus assignment.
 * Returns null if not authenticated as an admin.
 */
function get_admin_session() {
    global $conn;

    // 1. Session check
    $adminId = $_SESSION['admin_id'] ?? null;
    $rawRole = strtolower($_SESSION['role'] ?? $_SESSION['reunite_admin_role'] ?? '');



    if (!$adminId) {
        // Check if master key clearance active in session
        if (!empty($_SESSION['reunite_admin_auth']) && $_SESSION['reunite_admin_auth'] === true) {
            return [
                'admin_id'       => 1,
                'username'       => 'admin',
                'full_name'      => $_SESSION['reunite_admin_name'] ?? 'System Administrator',
                'email'          => 'admin@reunite.site',
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

    // Query DB for verified record
    if ($conn) {
        $stmt = $conn->prepare("
            SELECT a.admin_id, a.username, a.full_name, a.email, a.role, a.status, a.campus_id,
                   c.name as campus_name, c.code as campus_code
            FROM admins a
            LEFT JOIN campuses c ON a.campus_id = c.id
            WHERE a.admin_id = ? LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("i", $adminId);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($admin && strtolower($admin['status'] ?? '') !== 'suspended') {
                $role = strtolower($admin['role'] ?? 'campus_admin');
                $isMaster = ($role === 'master_admin' || $role === 'superadmin' || empty($admin['campus_id']));
                $normalizedRole = $isMaster ? 'master_admin' : 'campus_admin';

                // Sync session
                $_SESSION['admin_id']          = $admin['admin_id'];
                $_SESSION['admin_logged_in']   = true;
                $_SESSION['role']              = $normalizedRole;
                $_SESSION['admin_campus_id']   = $isMaster ? null : (int)$admin['campus_id'];
                $_SESSION['admin_campus_name'] = $isMaster ? 'All Campuses (Global)' : ($admin['campus_name'] ?? 'Assigned Campus');
                $_SESSION['admin_campus_code'] = $isMaster ? 'ALL' : ($admin['campus_code'] ?? 'CAMPUS');

                return [
                    'admin_id'        => (int)$admin['admin_id'],
                    'username'        => $admin['username'],
                    'full_name'       => $admin['full_name'],
                    'email'           => $admin['email'],
                    'role'            => $normalizedRole,
                    'is_master'       => $isMaster,
                    'is_campus_admin' => !$isMaster,
                    'campus_id'       => $isMaster ? null : (int)$admin['campus_id'],
                    'campus_name'     => $isMaster ? 'All Campuses (Global)' : ($admin['campus_name'] ?? 'Assigned Campus'),
                    'campus_code'     => $isMaster ? 'ALL' : ($admin['campus_code'] ?? 'CAMPUS')
                ];
            }
        }
    }

    // Fallback if DB is disconnected/unreachable but active admin session exists
    if (!$conn && !empty($_SESSION['admin_logged_in']) && !empty($_SESSION['role'])) {
        $role = strtolower($_SESSION['role']);
        $campusId = !empty($_SESSION['admin_campus_id']) ? (int)$_SESSION['admin_campus_id'] : null;
        $isMaster = ($role === 'master_admin' || $role === 'superadmin' || ($campusId === null && in_array($role, ['admin', 'master_admin'])));
        $normalizedRole = $isMaster ? 'master_admin' : 'campus_admin';

        return [
            'admin_id'        => (int)($_SESSION['admin_id'] ?? 1),
            'username'        => $_SESSION['username'] ?? 'admin',
            'full_name'       => $_SESSION['full_name'] ?? 'Administrator',
            'email'           => $_SESSION['email'] ?? 'admin@reunite.site',
            'role'            => $normalizedRole,
            'is_master'       => $isMaster,
            'is_campus_admin' => !$isMaster,
            'campus_id'       => $isMaster ? null : $campusId,
            'campus_name'     => $isMaster ? 'All Campuses (Global)' : ($_SESSION['admin_campus_name'] ?? 'Assigned Campus'),
            'campus_code'     => $isMaster ? 'ALL' : ($_SESSION['admin_campus_code'] ?? 'CAMPUS')
        ];
    }

    // Fallback if DB record missing but master key authenticated
    if ($adminId === 1 || in_array($rawRole, ['admin', 'superadmin', 'master_admin'])) {
        return [
            'admin_id'        => 1,
            'username'        => 'admin',
            'full_name'       => 'System Administrator',
            'email'           => 'admin@reunite.site',
            'role'            => 'master_admin',
            'is_master'       => true,
            'is_campus_admin' => false,
            'campus_id'       => null,
            'campus_name'     => 'All Campuses (Global)',
            'campus_code'     => 'ALL'
        ];
    }

    return null;
}

/**
 * Returns true if current admin is a Master Administrator
 */
function is_master_admin() {
    $admin = get_admin_session();
    return !empty($admin['is_master']);
}

/**
 * Returns true if current admin is a Campus Administrator
 */
function is_campus_admin() {
    $admin = get_admin_session();
    return !empty($admin['is_campus_admin']);
}

/**
 * Enforces admin authentication and role permission.
 * If unauthorized, returns 401/403 for API or renders friendly 403 page for web requests.
 */
function require_admin_role($allowedRoles = ['master_admin', 'campus_admin']) {
    $admin = get_admin_session();
    $isApi = is_admin_api_request();

    if (!$admin) {
        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'code'    => 'UNAUTHORIZED',
                'error'   => 'Administrator authentication required.'
            ]);
            exit;
        } else {
            $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/Frontend/admin/index.php');
            header("Location: ../Frontend/login.php?role=campus_admin&redirect=" . $redirect);
            exit;
        }
    }

    if (!in_array($admin['role'], $allowedRoles)) {
        if ($isApi) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'code'    => 'FORBIDDEN',
                'error'   => 'Access Denied: This privileged action requires Master Administrator clearance.'
            ]);
            exit;
        } else {
            render_access_denied_page("You don't have access to this section. Your account is scoped to " . htmlspecialchars($admin['campus_name']) . " and cannot modify system infrastructure or other campuses.");
            exit;
        }
    }

    return $admin;
}

/**
 * Returns the effective campus_id scope for the current query.
 * For Campus Admin: ALWAYS returns their enforced session campus_id (ignores any client input).
 * For Master Admin: returns null (global scope) OR returns the explicitly requested campus filter ID if specified.
 */
function get_admin_campus_scope() {
    $admin = get_admin_session();
    if (!$admin) {
        return null;
    }

    // Campus admin is ALWAYS locked to their own campus_id
    if ($admin['is_campus_admin']) {
        return (int)$admin['campus_id'];
    }

    // Master admin can optionally filter by campus via GET/POST
    $reqCampus = $_GET['campus_id'] ?? $_POST['campus_id'] ?? null;
    if ($reqCampus !== null && $reqCampus !== '' && $reqCampus !== 'all' && (int)$reqCampus > 0) {
        return (int)$reqCampus;
    }

    return null;
}

/**
 * Strict Anti-IDOR Gatekeeper.
 * Asserts that a target record belongs to the administrator's assigned campus.
 * Master Admin always passes. Campus Admin is rejected if campus IDs do not match.
 */
function assert_campus_access($targetCampusId, $entityName = 'record', $abortOnFail = true) {
    if (is_bool($entityName)) {
        $abortOnFail = $entityName;
        $entityName = 'record';
    }
    $admin = get_admin_session();
    if (!$admin) {
        if (!$abortOnFail) return false;
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required.']);
        exit;
    }

    // Master admin has unrestricted access
    if ($admin['is_master']) {
        return true;
    }

    $targetCampusId = (int)$targetCampusId;
    $myCampusId = (int)$admin['campus_id'];

    if ($targetCampusId <= 0 || $targetCampusId !== $myCampusId) {
        if (!$abortOnFail) return false;
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'code'    => 'CROSS_CAMPUS_FORBIDDEN',
            'error'   => "Access Denied: This $entityName belongs to another campus. You do not have permission to view or modify it."
        ]);
        exit;
    }

    return true;
}

/**
 * Records an administrative action in the admin_audit_logs table.
 */
function log_admin_action($action, $targetType = null, $targetId = null, $details = null) {
    global $conn;
    $admin = get_admin_session();
    if (!$admin || !$conn) return false;

    $adminId   = (int)$admin['admin_id'];
    $adminName = $admin['full_name'];
    $role      = $admin['role'];
    $campusId  = $admin['campus_id'] ? (int)$admin['campus_id'] : null;
    $ip        = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $stmt = $conn->prepare("
        INSERT INTO admin_audit_logs (admin_id, admin_name, role, campus_id, action, target_type, target_id, details, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if ($stmt) {
        $stmt->bind_param("issississ", $adminId, $adminName, $role, $campusId, $action, $targetType, $targetId, $details, $ip);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
    return false;
}

/**
 * Helper to determine if current request is an API / AJAX request
 */
function is_admin_api_request() {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }
    $uri = strtolower($_SERVER['REQUEST_URI'] ?? '');
    if (str_contains($uri, 'admin_api.php') || str_contains($uri, '/api/') || isset($_REQUEST['action'])) {
        return true;
    }
    return false;
}

/**
 * Friendly styled 403 Access Denied template matching Reunite design system.
 */
function render_access_denied_page($message = "You don't have permission to access this administrative resource.") {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
      <title>Access Restricted — Reunite</title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
      <style>
        :root {
          --bg: #F7F3EE;
          --surface: #FFFFFF;
          --border: #E8E2D9;
          --accent: #E06D38;
          --ink: #1C1814;
          --muted: #7E7367;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
          background-color: var(--bg);
          color: var(--ink);
          font-family: 'Inter', sans-serif;
          min-height: 100vh;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 24px;
        }
        .denied-card {
          background: var(--surface);
          border: 1px solid var(--border);
          border-radius: 18px;
          padding: 40px 32px;
          max-width: 480px;
          width: 100%;
          text-align: center;
          box-shadow: 0 10px 30px rgba(28, 24, 20, 0.05);
        }
        .icon-circle {
          width: 64px;
          height: 64px;
          border-radius: 50%;
          background: rgba(224, 109, 56, 0.12);
          color: var(--accent);
          display: inline-flex;
          align-items: center;
          justify-content: center;
          font-size: 28px;
          margin-bottom: 20px;
        }
        h1 {
          font-family: 'Plus Jakarta Sans', sans-serif;
          font-size: 1.5rem;
          font-weight: 800;
          color: var(--ink);
          margin-bottom: 12px;
        }
        p {
          color: var(--muted);
          font-size: 0.92rem;
          line-height: 1.6;
          margin-bottom: 28px;
        }
        .btn-return {
          display: inline-flex;
          align-items: center;
          gap: 8px;
          background: var(--accent);
          color: #FFFFFF;
          padding: 12px 24px;
          border-radius: 12px;
          text-decoration: none;
          font-weight: 600;
          font-size: 0.9rem;
          transition: transform 0.2s, background-color 0.2s;
        }
        .btn-return:hover {
          background: #C45524;
          transform: translateY(-2px);
        }
      </style>
    </head>
    <body>
      <div class="denied-card">
        <div class="icon-circle">
          <i class="fa-solid fa-shield-halved"></i>
        </div>
        <h1>Access Restricted</h1>
        <p><?= htmlspecialchars($message) ?></p>
        <a href="index.php" class="btn-return">
          <i class="fa-solid fa-arrow-left"></i>
          <span>Return to Dashboard</span>
        </a>
      </div>
    </body>
    </html>
    <?php
}
