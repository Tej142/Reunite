# 02 — Authentication & Multi-Tenant Security Policy

## 1. Unified Authentication Architecture
All authentication across the Reunite Platform is centralized in a single gateway: [`Frontend/login.php`](file:///c:/xampp/htdocs/pw/Reunite/Frontend/login.php).

```
                      ┌─────────────────────────────────┐
                      │    Frontend/login.php Gateway   │
                      │  - Student Tab                  │
                      │  - Campus Admin Tab             │
                      │  - Master Admin Fallback        │
                      └────────────────┬────────────────┘
                                       │
                ┌──────────────────────┴──────────────────────┐
                ▼                                             ▼
     [Student Credentials]                         [Admin Credentials]
   - College PIN / Reg No.                       - Username or Email
   - Encrypted in Database                       - Verified against `admins`
   - Scoped to Campus ID                         - Scoped to Campus ID
                │                                             │
                ▼                                             ▼
  ┌───────────────────────────┐                 ┌───────────────────────────┐
  │  $_SESSION['user_id']     │                 │ $_SESSION['admin_id']     │
  │  $_SESSION['role']='user' │                 │ $_SESSION['role']='admin' │
  │  Redirect: home.php       │                 │ Redirect: admin/index.php │
  └───────────────────────────┘                 └───────────────────────────┘
```

---

## 2. Zero-Cookie Policy for Administrators
To mitigate cookie hijacking, session replay, and cross-site scripting (XSS) risks on shared university devices:
- **No persistent cookies** (`reunite_admin_session`) are issued to administrators.
- Privileged authorization is stored **exclusively in volatile server-side PHP `$_SESSION`**.
- Any legacy or stale cookies presented by the client browser are actively expired and purged upon login.

### Implementation Excerpt: Admin Login Processor
File: `Backend/admin_auth.php`

```php
function adminLoginSuccess($adminData, $isApi) {
    global $conn;
    $rawRole = strtolower($adminData['role'] ?? 'campus_admin');
    $campusId = !empty($adminData['campus_id']) ? (int)$adminData['campus_id'] : null;
    $isMaster = ($rawRole === 'master_admin' || $rawRole === 'superadmin' || ($campusId === null && in_array($rawRole, ['admin', 'superadmin', 'master_admin'])));
    $normalizedRole = $isMaster ? 'master_admin' : 'campus_admin';

    // Store privileged session state strictly in server memory
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
    // Explicitly expire and unset any existing admin cookies from the client browser.
    if (isset($_COOKIE['reunite_admin_session'])) {
        setcookie('reunite_admin_session', '', time() - 86400, '/');
        unset($_COOKIE['reunite_admin_session']);
    }

    if ($isApi) {
        sendJsonResponse(true, "Admin authentication successful!", [
            'redirect' => '../Frontend/admin/index.php',
            'admin'    => [
                'admin_id'    => (int)$adminData['admin_id'],
                'full_name'   => $adminData['full_name'],
                'username'    => $adminData['username'],
                'role'        => $normalizedRole,
                'campus_id'   => $campusId,
                'campus_name' => $_SESSION['admin_campus_name']
            ]
        ]);
    } else {
        header("Location: ../Frontend/admin/index.php");
        exit();
    }
}
```

---

## 3. Multi-Tenant Campus Isolation & Anti-IDOR Defense
Campus Administrators are strictly confined to items, claims, students, and logs belonging to their designated campus. Master Administrators maintain unrestricted global oversight.

### Implementation Excerpt: Campus Scoping Engine
File: `Backend/admin_scope.php`

```php
/**
 * Injects multi-tenant campus boundaries into SQL queries.
 * Master Admin: Returns unmodified query (or filters by optional ?campus_id= parameter).
 * Campus Admin: Hard-enforces `AND table_alias.campus_id = [admin_campus_id]` on all reads/writes.
 */
function apply_campus_scope_sql($sql, $tableAlias = 'i', $adminSession = null) {
    if (!$adminSession) {
        $adminSession = get_admin_session();
    }
    if (!$adminSession) {
        return $sql . " AND 1=0"; // Fail closed if unauthorized
    }

    // Master Admin has global access or can filter arbitrarily
    if ($adminSession['is_master']) {
        if (!empty($_GET['campus_id'])) {
            $filterCampusId = (int)$_GET['campus_id'];
            return $sql . " AND {$tableAlias}.campus_id = {$filterCampusId}";
        }
        return $sql;
    }

    // Campus Admin is strictly locked to their assigned institution
    $campusId = (int)$adminSession['campus_id'];
    return $sql . " AND {$tableAlias}.campus_id = {$campusId}";
}

/**
 * Anti-IDOR Security Guard: Asserts ownership before any state mutation.
 */
function assert_campus_item_access($itemId, $itemType = 'lost', $adminSession = null) {
    global $conn;
    if (!$adminSession) $adminSession = get_admin_session();
    if (!$adminSession) return false;
    if ($adminSession['is_master']) return true;

    $table = ($itemType === 'found') ? 'found_items' : 'lost_items';
    $stmt = $conn->prepare("SELECT campus_id FROM {$table} WHERE id = ? LIMIT 1");
    if (!$stmt) return false;

    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Verify item belongs strictly to admin's campus
    return ($row && (int)$row['campus_id'] === (int)$adminSession['campus_id']);
}
```
