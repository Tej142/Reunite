<?php
/**
 * Reunite Platform Admin Command Center
 * Protected Centralized Management Dashboard
 */

require_once __DIR__ . '/../includes/auth.php';

// Strict Admin Gatekeeper
require_admin();

$adminUser = get_current_user_data();
$adminName = htmlspecialchars($_SESSION['full_name'] ?? 'System Administrator');
$adminRole = htmlspecialchars($_SESSION['role'] ?? 'admin');
$adminEmail = htmlspecialchars($_SESSION['email'] ?? 'admin@reunite.site');
$adminInitials = strtoupper(substr($adminName, 0, 1) . (strpos($adminName, ' ') !== false ? substr($adminName, strpos($adminName, ' ') + 1, 1) : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Admin Command Center | Reunite Platform</title>
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  
  <!-- FontAwesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Admin Stylesheet -->
  <link rel="stylesheet" href="css/admin.css">
</head>
<body>

  <!-- ── Sidebar Navigation ─────────────────────────────────────────── -->
  <aside class="admin-sidebar" id="adminSidebar">
    <div>
      <a href="index.php" class="sidebar-brand">
        <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="40" height="40" rx="10" fill="#1C1814" />
          <path d="M12 28C12 21 16 15 24 13" stroke="#E06D38" stroke-width="3.5" stroke-linecap="round"/>
          <path d="M28 12C28 19 24 25 16 27" stroke="#F7F3EE" stroke-width="3.5" stroke-linecap="round"/>
          <circle cx="20" cy="20" r="3.5" fill="#E06D38"/>
        </svg>
        <span class="sidebar-brand-name">Reunite</span>
        <span class="sidebar-badge">Admin</span>
      </a>

      <div class="menu-category">Main</div>
      <ul class="sidebar-menu">
        <li>
          <button type="button" class="nav-tab-btn active" data-tab="overview">
            <i class="fa-solid fa-chart-pie"></i>
            <span>Dashboard</span>
          </button>
        </li>
      </ul>

      <div class="menu-category">Reports</div>
      <ul class="sidebar-menu">
        <li>
          <button type="button" class="nav-tab-btn" data-tab="lost">
            <i class="fa-solid fa-box-open"></i>
            <span>Lost Reports</span>
            <span class="nav-tab-badge" id="badgeLostCount">0</span>
          </button>
        </li>
        <li>
          <button type="button" class="nav-tab-btn" data-tab="found">
            <i class="fa-solid fa-hand-holding-heart"></i>
            <span>Found Items</span>
            <span class="nav-tab-badge" id="badgeFoundCount">0</span>
          </button>
        </li>
        <li>
          <button type="button" class="nav-tab-btn" data-tab="matches">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
            <span>AI Matches</span>
            <span class="nav-tab-badge" id="badgeMatchCount">0</span>
          </button>
        </li>
        <li>
          <button type="button" class="nav-tab-btn" data-tab="claims">
            <i class="fa-solid fa-shield-halved"></i>
            <span>Claims</span>
            <span class="nav-tab-badge" id="badgeClaimCount">0</span>
          </button>
        </li>
      </ul>

      <div class="menu-category">System</div>
      <ul class="sidebar-menu">
        <li>
          <button type="button" class="nav-tab-btn" data-tab="users">
            <i class="fa-solid fa-users"></i>
            <span>Users</span>
          </button>
        </li>
        <li>
          <button type="button" class="nav-tab-btn" data-tab="logs">
            <i class="fa-solid fa-list-check"></i>
            <span>Audit Logs</span>
          </button>
        </li>
        <li>
          <button type="button" class="nav-tab-btn" data-tab="system">
            <i class="fa-solid fa-server"></i>
            <span>System Health</span>
          </button>
        </li>
      </ul>
    </div>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
      <div class="admin-user-pill">
        <div class="admin-avatar"><?= $adminInitials ?: 'A' ?></div>
        <div class="admin-user-info">
          <span class="admin-user-name" title="<?= $adminName ?>"><?= $adminName ?></span>
          <span class="admin-user-role"><?= strtoupper($adminRole) ?></span>
        </div>
      </div>
      <a href="../../Backend/admin_auth.php?action=logout" class="btn-sidebar-logout" title="Sign Out of Admin Portal">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
      </a>
    </div>
  </aside>

  <!-- ── Main Workspace Content ──────────────────────────────────────── -->
  <main class="admin-main">
    
    <!-- Topbar -->
    <!-- Top Header Bar -->
    <div class="admin-topbar">
      <div style="display:flex; align-items:center; gap:16px;">
        <button type="button" class="mobile-sidebar-toggle" id="mobileSidebarToggle" aria-label="Toggle Navigation">
          <i class="fa-solid fa-bars"></i>
        </button>
        <div class="page-title-wrap">
          <h1 id="viewTitle">Dashboard</h1>
          <p id="viewSubtitle">Overview of campus reports, matches, and operations.</p>
        </div>
      </div>

      <div class="topbar-actions">
        <div class="live-health-pill">
          <span class="pulse-beacon"></span>
          <span>Operational</span>
        </div>
        <button type="button" class="btn-refresh" id="btnGlobalRefresh" title="Refresh Live Data">
          <i class="fa-solid fa-rotate"></i>
        </button>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- TAB 1: DASHBOARD OVERVIEW                                          -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <section class="admin-tab-pane" id="pane-overview">
      <!-- KPI Stats Grid -->
      <div class="stats-grid">
        <div class="stat-card">
          <span class="stat-label">Students</span>
          <div class="stat-value" id="statTotalUsers">--</div>
          <div class="stat-sub"><span id="statActiveUsers">--</span> active accounts</div>
        </div>

        <div class="stat-card">
          <span class="stat-label">Lost Reports</span>
          <div class="stat-value" id="statActiveLost">--</div>
          <div class="stat-sub"><span id="statTotalLost">--</span> total logged</div>
        </div>

        <div class="stat-card">
          <span class="stat-label">Found Items</span>
          <div class="stat-value" id="statActiveFound">--</div>
          <div class="stat-sub"><span id="statTotalFound">--</span> in custody</div>
        </div>

        <div class="stat-card">
          <span class="stat-label">AI Matches</span>
          <div class="stat-value" id="statTotalMatches">--</div>
          <div class="stat-sub"><span id="statVerifiedMatches">--</span> verified</div>
        </div>

        <div class="stat-card">
          <span class="stat-label">Pending Claims</span>
          <div class="stat-value" id="statPendingClaims">--</div>
          <div class="stat-sub"><span id="statResolvedCases">--</span> resolved</div>
        </div>
      </div>

      <!-- Actions & System Attributes -->
      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:20px;">
        <div class="admin-card">
          <div class="card-header-bar">
            <h2>Quick Actions</h2>
          </div>
          <div class="quick-actions-list">
            <button class="action-shortcut-card" onclick="switchTab('users')">
              <div class="action-text">
                <strong>Manage Accounts</strong>
                <span>Review student permissions, trust scores & statuses</span>
              </div>
              <i class="fa-solid fa-arrow-right action-arrow"></i>
            </button>

            <button class="action-shortcut-card" onclick="switchTab('claims')">
              <div class="action-text">
                <strong>Ownership Claims</strong>
                <span>Verify submitted proof documents & authorize handoffs</span>
              </div>
              <i class="fa-solid fa-arrow-right action-arrow"></i>
            </button>

            <button class="action-shortcut-card" onclick="switchTab('system')">
              <div class="action-text">
                <strong>System Health</strong>
                <span>Check database connection & AI microservice latency</span>
              </div>
              <i class="fa-solid fa-arrow-right action-arrow"></i>
            </button>
          </div>
        </div>

        <div class="admin-card">
          <div class="card-header-bar">
            <h2>System Status</h2>
          </div>
          <div class="topology-list">
            <div class="topology-item">
              <span class="topo-label">Database</span>
              <span style="color:var(--success); font-weight:500;">Connected (MySQL)</span>
            </div>
            <div class="topology-item">
              <span class="topo-label">AI Microservice</span>
              <span style="color:var(--success); font-weight:500;">Online (~38ms)</span>
            </div>
            <div class="topology-item">
              <span class="topo-label">Security</span>
              <span style="color:var(--ink-secondary); font-size:0.85rem;">AES-256 Encryption Active</span>
            </div>
            <div class="topology-item">
              <span class="topo-label">Campus Node</span>
              <span style="color:var(--ink-secondary); font-size:0.85rem;">SV Govt Polytechnic</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- TAB 2: USER MANAGEMENT                                             -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <section class="admin-tab-pane" id="pane-users" style="display:none;">
      <div class="admin-card">
        <div class="card-header-bar">
          <h2>User Accounts Directory</h2>
          <div class="filter-group">
            <div class="search-input-box">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" class="admin-input-sm" id="searchUsersInput" placeholder="Search by name or PIN...">
            </div>
            <select class="admin-select-sm" id="filterUserRole">
              <option value="">All Roles</option>
              <option value="user">Student / User</option>
              <option value="admin">Administrator</option>
            </select>
            <select class="admin-select-sm" id="filterUserStatus">
              <option value="">All Statuses</option>
              <option value="active">Active</option>
              <option value="blocked">Blocked / Suspended</option>
            </select>
          </div>
        </div>

        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name / Student</th>
                <th>PIN</th>
                <th>Decrypted Email & Phone</th>
                <th>College / Branch</th>
                <th>Trust</th>
                <th>Role</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="usersTableBody">
              <tr><td colspan="9" style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading users...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- TAB 3: LOST REPORTS                                                -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <section class="admin-tab-pane" id="pane-lost" style="display:none;">
      <div class="admin-card">
        <div class="card-header-bar">
          <h2>Lost Property Reports</h2>
          <div class="filter-group">
            <div class="search-input-box">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" class="admin-input-sm" id="searchLostInput" placeholder="Search title or location...">
            </div>
            <select class="admin-select-sm" id="filterLostCategory">
              <option value="">All Categories</option>
              <option value="Electronics">Electronics</option>
              <option value="Books">Books / Notes</option>
              <option value="Wallets">Wallets / Cards</option>
              <option value="Keys">Keys</option>
              <option value="Accessories">Accessories</option>
              <option value="Documents">Documents / ID</option>
              <option value="Other">Other</option>
            </select>
            <select class="admin-select-sm" id="filterLostStatus">
              <option value="">All Statuses</option>
              <option value="active">Active</option>
              <option value="matched">Matched</option>
              <option value="claimed">Claimed</option>
              <option value="closed">Closed</option>
            </select>
          </div>
        </div>

        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Title & Category</th>
                <th>Location Lost</th>
                <th>Date Lost</th>
                <th>Reporter Info</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="lostTableBody">
              <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading lost reports...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- TAB 4: FOUND REPORTS                                               -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <section class="admin-tab-pane" id="pane-found" style="display:none;">
      <div class="admin-card">
        <div class="card-header-bar">
          <h2>Found Property Deposits</h2>
          <div class="filter-group">
            <div class="search-input-box">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" class="admin-input-sm" id="searchFoundInput" placeholder="Search title or place...">
            </div>
            <select class="admin-select-sm" id="filterFoundCategory">
              <option value="">All Categories</option>
              <option value="Electronics">Electronics</option>
              <option value="Books">Books / Notes</option>
              <option value="Wallets">Wallets / Cards</option>
              <option value="Keys">Keys</option>
              <option value="Accessories">Accessories</option>
              <option value="Documents">Documents / ID</option>
              <option value="Other">Other</option>
            </select>
            <select class="admin-select-sm" id="filterFoundStatus">
              <option value="">All Statuses</option>
              <option value="active">Active</option>
              <option value="matched">Matched</option>
              <option value="claimed">Claimed</option>
              <option value="closed">Closed</option>
            </select>
          </div>
        </div>

        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Photo</th>
                <th>Title & Category</th>
                <th>Found Location</th>
                <th>Storage Custody</th>
                <th>Finder Info</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="foundTableBody">
              <tr><td colspan="8" style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading found reports...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- TAB 5: AI MATCHES                                                  -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <section class="admin-tab-pane" id="pane-matches" style="display:none;">
      <div class="admin-card">
        <div class="card-header-bar">
          <h2>AI Semantic & Vision Matches</h2>
        </div>

        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Match ID</th>
                <th>Lost Item</th>
                <th>Found Item</th>
                <th>Confidence Score</th>
                <th>Status</th>
                <th>AI Insight</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="matchesTableBody">
              <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading AI matches...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- TAB 6: CLAIM VERIFICATIONS                                         -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <section class="admin-tab-pane" id="pane-claims" style="display:none;">
      <div class="admin-card">
        <div class="card-header-bar">
          <h2>Ownership Recovery Cases</h2>
        </div>

        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Case ID</th>
                <th>Match / Found ID</th>
                <th>Claimant</th>
                <th>Submitted Proof Details</th>
                <th>Status</th>
                <th>Date Filed</th>
                <th>Verification Actions</th>
              </tr>
            </thead>
            <tbody id="claimsTableBody">
              <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading recovery cases...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- TAB 7: AUDIT & ACTIVITY LOGS                                       -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <section class="admin-tab-pane" id="pane-logs" style="display:none;">
      <div class="admin-card">
        <div class="card-header-bar">
          <h2>Platform Activity & Security Audit Trail</h2>
          <div class="filter-group">
            <div class="search-input-box">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" class="admin-input-sm" id="searchLogsInput" placeholder="Search activity, name or IP...">
            </div>
            <select class="admin-select-sm" id="filterLogsCategory">
              <option value="">All Activities</option>
              <option value="admin_access">Admin Access & Actions</option>
              <option value="user_access">Student Logins & Signups</option>
              <option value="password_reset">Password Resets / OTPs</option>
              <option value="report_activity">Lost & Found Reports</option>
              <option value="match_verification">AI Neural Matches</option>
              <option value="claim_activity">Ownership Claims & Handoffs</option>
              <option value="security">Security & System Events</option>
            </select>
          </div>
        </div>

        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Operator / Actor</th>
                <th>Category</th>
                <th>Action & Context Details</th>
                <th>IP & Client Device</th>
                <th>Timestamp</th>
              </tr>
            </thead>
            <tbody id="logsTableBody">
              <tr><td colspan="6" style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading activity logs...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- TAB 8: SYSTEM HEALTH                                               -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <section class="admin-tab-pane" id="pane-system" style="display:none;">

      <!-- Top Bar -->
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
        <div>
          <div style="font-size:0.72rem; font-weight:600; letter-spacing:0.08em; color:var(--muted); text-transform:uppercase; margin-bottom:4px;">Live Infrastructure</div>
          <div style="font-size:0.82rem; color:var(--muted);">Last checked: <span id="diagLastChecked">—</span></div>
        </div>
        <button type="button" id="btnRunDiagnostics" class="diag-recheck-btn">
          <i class="fa-solid fa-arrow-rotate-right"></i>
          <span>Recheck</span>
        </button>
      </div>

      <!-- Service Cards Grid -->
      <div class="diag-cards-grid">

        <!-- Database -->
        <div class="diag-service-card" id="diagCardDb">
          <div class="diag-card-top">
            <div class="diag-service-icon" id="diagDbIcon">
              <i class="fa-solid fa-database"></i>
            </div>
            <div class="diag-pulse-wrap">
              <span class="diag-pulse" id="diagDbPulse"></span>
              <span class="diag-status-badge" id="diagDbBadge">Checking</span>
            </div>
          </div>
          <div class="diag-service-name">MySQL Database</div>
          <div class="diag-service-detail" id="diagDbDetail">Establishing connection…</div>
          <div class="diag-service-meta">MySQL Relational Store</div>
        </div>

        <!-- AI Microservice -->
        <div class="diag-service-card" id="diagCardAi">
          <div class="diag-card-top">
            <div class="diag-service-icon" id="diagAiIcon">
              <i class="fa-solid fa-microchip"></i>
            </div>
            <div class="diag-pulse-wrap">
              <span class="diag-pulse" id="diagAiPulse"></span>
              <span class="diag-status-badge" id="diagAiBadge">Checking</span>
            </div>
          </div>
          <div class="diag-service-name">AI Microservice</div>
          <div class="diag-service-detail" id="diagAiLatency">Measuring latency…</div>
          <!-- Latency Bar -->
          <div class="diag-latency-bar-wrap" id="diagLatencyBarWrap" style="display:none;">
            <div class="diag-latency-track">
              <div class="diag-latency-fill" id="diagLatencyFill"></div>
            </div>
            <span class="diag-latency-label" id="diagLatencyLabel">— ms</span>
          </div>
          <div class="diag-service-meta">Render Cloud · Semantic Match Engine</div>
        </div>

        <!-- SMTP Gateway -->
        <div class="diag-service-card" id="diagCardSmtp">
          <div class="diag-card-top">
            <div class="diag-service-icon" id="diagSmtpIcon">
              <i class="fa-solid fa-envelope"></i>
            </div>
            <div class="diag-pulse-wrap">
              <span class="diag-pulse" id="diagSmtpPulse"></span>
              <span class="diag-status-badge" id="diagSmtpBadge">Checking</span>
            </div>
          </div>
          <div class="diag-service-name">SMTP Gateway</div>
          <div class="diag-service-detail" id="diagSmtpStatus">Verifying API key…</div>
          <div class="diag-service-meta">Brevo Transactional Mail API</div>
        </div>

        <!-- PHP Runtime -->
        <div class="diag-service-card diag-card-static">
          <div class="diag-card-top">
            <div class="diag-service-icon" style="background:rgba(139,139,255,0.12); color:#8b8bff;">
              <i class="fa-brands fa-php"></i>
            </div>
            <div class="diag-pulse-wrap">
              <span class="diag-pulse diag-pulse-static"></span>
              <span class="diag-status-badge diag-badge-neutral">Runtime</span>
            </div>
          </div>
          <div class="diag-service-name" id="diagPhpVersion">PHP <?= PHP_VERSION ?></div>
          <div class="diag-service-detail">Server-side scripting engine</div>
          <div class="diag-service-meta" id="diagServerTime"><?= date('Y-m-d H:i:s T') ?></div>
        </div>

      </div>
    </section>


  </main>

  <!-- ── Modal Dialog: Edit User ─────────────────────────────────────── -->
  <div class="admin-modal-backdrop" id="editUserModal">
    <div class="admin-modal-card">
      <button type="button" class="admin-modal-close" onclick="closeModal('editUserModal')">
        <i class="fa-solid fa-xmark"></i>
      </button>
      <h2 style="font-family:'Plus Jakarta Sans', sans-serif; font-weight:800; letter-spacing:-0.02em; margin-bottom:6px; color:var(--ink);">Edit User Account</h2>
      <p style="color:var(--muted); font-size:0.85rem; margin-bottom:20px;" id="modalUserSubtitle">Update roles, status and trust rating.</p>

      <form id="editUserForm" onsubmit="handleSaveUser(event)">
        <input type="hidden" id="editUserId" name="user_id">
        
        <div style="margin-bottom:16px;">
          <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">Role</label>
          <select id="editUserRole" class="admin-select-sm" style="width:100%; padding:10px;">
            <option value="user">User / Student</option>
            <option value="admin">Administrator</option>
          </select>
        </div>

        <div style="margin-bottom:16px;">
          <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">Account Status</label>
          <select id="editUserStatus" class="admin-select-sm" style="width:100%; padding:10px;">
            <option value="active">Active (Access Allowed)</option>
            <option value="blocked">Blocked / Suspended (Access Denied)</option>
          </select>
        </div>

        <div style="margin-bottom:16px;">
          <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">Trust Score (0 - 100)</label>
          <input type="number" id="editUserTrust" class="admin-input-sm" style="width:100%; padding:10px;" min="0" max="100">
        </div>

        <div style="margin-bottom:24px;">
          <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">Reset Password (leave empty to keep current)</label>
          <input type="password" id="editUserNewPass" class="admin-input-sm" style="width:100%; padding:10px;" placeholder="New password...">
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px;">
          <button type="button" class="nav-tab-btn" style="width:auto; padding:10px 18px;" onclick="closeModal('editUserModal')">Cancel</button>
          <button type="submit" class="admin-btn-primary" style="padding:10px 20px; border-radius:10px;" id="btnSaveUser">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Toast Container -->
  <div class="toast-container" id="toastContainer"></div>

  <!-- JavaScript Application -->
  <script src="js/admin.js"></script>
</body>
</html>
