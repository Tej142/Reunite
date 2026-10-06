<?php
/**
 * Reunite - Site Maintenance & System Administration Console
 * Direct Admin Access Only
 */

require_once __DIR__ . '/../Backend/config/config.php';
require_once __DIR__ . '/../Backend/functions.php';

// Session already started in config.php
$isAdmin = !empty($_SESSION['reunite_admin_auth']) && $_SESSION['reunite_admin_auth'] === true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>System Administration &amp; Site Maintenance — Reunite</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="css/admin.css?v=<?php echo time(); ?>">
</head>
<body class="admin-body">

  <!-- ───────────────────────────────────────────────────────────── -->
  <!-- 1. SECURITY CLEARANCE TERMINAL AUTH SCREEN -->
  <!-- ───────────────────────────────────────────────────────────── -->
  <div class="admin-auth-screen" id="adminAuthScreen" style="<?php echo $isAdmin ? 'display:none;' : 'display:flex;'; ?>">
    <div style="position: absolute; top: 1.5rem; right: 1.5rem; z-index: 10;">
      <button type="button" class="btn-admin-theme" id="btnAdminThemeToggleAuth" title="Toggle Light/Dark Theme" aria-label="Toggle theme">
        <svg class="sun-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
        <svg class="moon-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>
    </div>
    <div class="admin-auth-card">
      <div class="admin-auth-icon">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <h1 class="admin-auth-title">System Admin Clearance</h1>
      <p class="admin-auth-sub">
        Restricted Site Maintenance Console. Authorized system administrators only.
      </p>

      <form class="admin-auth-form" id="adminLoginForm">
        <div class="admin-auth-field">
          <label for="adminPasscode">Security Clearance Key / Passcode</label>
          <input type="password" class="admin-input" id="adminPasscode" placeholder="Enter System Master Passcode..." required autofocus autocomplete="off" />
        </div>
        <button type="submit" class="btn-admin admin-auth-btn">
          Authenticate &amp; Access Terminal &rarr;
        </button>
      </form>
    </div>
  </div>

  <!-- ───────────────────────────────────────────────────────────── -->
  <!-- 2. AUTHENTICATED SITE MAINTENANCE DASHBOARD -->
  <!-- ───────────────────────────────────────────────────────────── -->
  <?php
  // Admin maintenance banner — visible to admin when maintenance is ON
  $maint = getMaintenanceSettings();
  ?>
  <?php if ($isAdmin && $maint['enabled']): ?>
  <div class="admin-maint-on-banner" role="alert">
    <span><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" stroke="none" style="vertical-align:-1px; margin-right:4px; color:var(--admin-warning);"><circle cx="12" cy="12" r="10"/></svg> Maintenance Mode is <strong>ON</strong> — non-admin users see the maintenance page.</span>
    <span style="font-size:0.8125rem; color: var(--admin-warning);">Turn it OFF in the Health &amp; HUD tab when ready.</span>
  </div>
  <?php endif; ?>

  <div id="adminDashboard" style="<?php echo $isAdmin ? 'display:block;' : 'display:none;'; ?>">
    
    <!-- Top Header -->
    <header class="admin-header">
      <div class="admin-brand">
        <div class="admin-brand-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        </div>
        <div>
          <span class="admin-brand-title">Reunite Core Ops</span>
          <span class="admin-brand-badge">Site Maintenance</span>
        </div>
      </div>

      <div class="admin-header-actions">
        <button type="button" class="btn-admin-theme" id="btnAdminThemeToggle" title="Toggle Light/Dark Theme" aria-label="Toggle theme">
          <svg class="sun-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
          <svg class="moon-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <div class="admin-user-pill">
          <span class="admin-pulse-dot"></span>
          <span id="adminUserPillName">System Administrator</span>
        </div>
        <button type="button" class="btn-admin-logout" id="btnAdminLogout" title="Terminate Admin Session">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Exit Terminal
        </button>
      </div>
    </header>

    <!-- Tab Navigation -->
    <nav class="admin-nav-bar">
      <button type="button" class="admin-tab-btn active" data-tab="health">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> System Health &amp; HUD
      </button>
      <button type="button" class="admin-tab-btn" data-tab="reports">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 17v-4"/><path d="M12 17V7"/><path d="M17 17v-8"/></svg> Reports &amp; Digital DNA
      </button>
      <button type="button" class="admin-tab-btn" data-tab="vector">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M12 2a4 4 0 0 1 4 4v1a4 4 0 0 1-8 0V6a4 4 0 0 1 4-4z"/><path d="M6 11v2a6 6 0 0 0 12 0v-2M12 19v3M8 22h8"/></svg> ChromaDB Vector Engine
      </button>
      <button type="button" class="admin-tab-btn" data-tab="vault">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><rect width="20" height="8" x="2" y="3" rx="2"/><rect width="20" height="8" x="2" y="13" rx="2"/><line x1="10" x1="10" y1="7" y2="7"/><line x1="10" x1="10" y1="17" y2="17"/></svg> Media Vault Storage
      </button>
      <button type="button" class="admin-tab-btn" data-tab="logs">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Access Audit Logs
      </button>
    </nav>

    <!-- Main Container -->
    <main class="admin-main">

      <!-- Server Outage Alert Banner (Appears dynamically when Flask AI or DB is offline) -->
      <div class="admin-outage-banner" id="serverOutageBanner" style="display: none;">
        <div class="admin-outage-content">
          <div class="admin-outage-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          </div>
          <div>
            <div class="admin-outage-title" id="outageBannerTitle">Server Component Offline</div>
            <div class="admin-outage-desc" id="outageBannerDesc">
              Local AI Engine or Database is unreachable. AI Vision analysis, DINOv2 &amp; CLIP neural vector matchings are paused.
            </div>
            <div id="outageBannerHint"></div>
          </div>
        </div>
        <div>
          <button type="button" class="btn-admin secondary sm" id="btnRetryOutagePing" style="white-space: nowrap;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-1px; margin-right:3px;"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.1-8.24"/></svg> Retry Connection
          </button>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════ -->
      <!-- TAB 1: SYSTEM HEALTH & HUD DIAGNOSTICS -->
      <!-- ═══════════════════════════════════════════════════════════ -->
      <div class="admin-tab-pane active" id="tabPane_health">
        
        <!-- Live Metrics Grid -->
        <div class="admin-metrics-grid">
          
          <div class="admin-metric-card">
            <div class="admin-metric-header">
              <span>Flask AI Multimodal</span>
              <span id="badgeFlaskStatus" class="admin-badge success">Checking...</span>
            </div>
            <div class="admin-metric-value" id="metricChromaTotal">0</div>
            <div class="admin-metric-sub">Total ChromaDB Vector Embeddings</div>
          </div>

          <div class="admin-metric-card">
            <div class="admin-metric-header">
              <span>Database Reports</span>
              <span class="admin-badge info">MySQL</span>
            </div>
            <div class="admin-metric-value">
              <span id="metricLostCount">0</span>
              <span style="font-size: 1rem; color: var(--admin-text-muted);">Lost / <span id="metricFoundCount">0</span> Found</span>
            </div>
            <div class="admin-metric-sub"><span id="metricDnaCount">0</span> Encrypted Digital DNA Records</div>
          </div>

          <div class="admin-metric-card">
            <div class="admin-metric-header">
              <span>Media Vault Storage</span>
              <span class="admin-badge warning">Local Vault</span>
            </div>
            <div class="admin-metric-value" id="metricVaultSize">0 B</div>
            <div class="admin-metric-sub"><span id="metricVaultFiles">0</span> Uploaded Media Files</div>
          </div>

          <div class="admin-metric-card">
            <div class="admin-metric-header">
              <span>Site Status</span>
              <span id="badgeMaintenanceMode" class="admin-badge success">Site is Live</span>
            </div>
            <div class="admin-metric-value" style="font-size: 1.1rem; color: var(--admin-success);" id="maintModeLabel">Live</div>
            <div class="admin-metric-sub" id="maintEtaLabel">All users have full access</div>
          </div>

        </div>

        <!-- Maintenance Toggle Card -->
        <div class="admin-card admin-maint-card" id="adminMaintCard">
          <div class="admin-card-header">
            <div class="admin-card-title">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg> Maintenance Mode Control
            </div>
            <div class="admin-card-actions">
              <button type="button" class="btn-admin" id="btnRefreshDiagnostics">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px; margin-right:3px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> Run Health Check
              </button>
            </div>
          </div>

          <div class="admin-maint-toggle-row">
            <div class="admin-maint-status-block">
              <div class="admin-maint-status-indicator" id="maintStatusIndicator"></div>
              <div>
                <div class="admin-maint-status-title" id="maintStatusTitle">Site is Live</div>
                <div class="admin-maint-status-sub" id="maintStatusSub">All users have full access to the platform</div>
              </div>
            </div>
            <button type="button" class="btn-admin btn-maint-toggle" id="btnToggleMaintenance" aria-label="Toggle maintenance mode">
              Enable Maintenance Mode
            </button>
          </div>

          <!-- Current settings display (shown when maintenance is ON) -->
          <div class="admin-maint-active-info" id="adminMaintActiveInfo" style="display:none;">
            <div class="admin-maint-info-row" id="maintInfoMsg" style="display:none;">
              <span class="admin-maint-info-label">Message:</span>
              <span id="maintInfoMsgText"></span>
            </div>
            <div class="admin-maint-info-row" id="maintInfoEta" style="display:none;">
              <span class="admin-maint-info-label">ETA:</span>
              <span id="maintInfoEtaText"></span>
            </div>
            <div class="admin-maint-info-row" id="maintInfoEmail" style="display:none;">
              <span class="admin-maint-info-label">Support Email:</span>
              <span id="maintInfoEmailText"></span>
            </div>
          </div>

          <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--admin-border);">
            <button type="button" class="btn-admin secondary" id="btnOptimizeDb">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px; margin-right:3px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> Optimize &amp; Rebuild MySQL Indexes
            </button>
          </div>
        </div>

        <!-- Server Environment Specs -->
        <div class="admin-card">
          <div class="admin-card-header">
            <div class="admin-card-title">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg> Server Environment &amp; Memory Specs
            </div>
          </div>
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; font-size: 0.875rem;">
            <div>
              <div style="color: var(--admin-text-muted); font-size: 0.75rem; text-transform: uppercase;">PHP Version</div>
              <strong id="metricPhpVersion"><?php echo PHP_VERSION; ?></strong>
            </div>
            <div>
              <div style="color: var(--admin-text-muted); font-size: 0.75rem; text-transform: uppercase;">Memory Allocation</div>
              <strong id="metricMemory">--</strong>
            </div>
            <div>
              <div style="color: var(--admin-text-muted); font-size: 0.75rem; text-transform: uppercase;">Server System Time</div>
              <strong id="metricServerTime"><?php echo date('Y-m-d H:i:s'); ?></strong>
            </div>
            <div>
              <div style="color: var(--admin-text-muted); font-size: 0.75rem; text-transform: uppercase;">Registered Users</div>
              <strong id="metricUsersCount">0</strong>
            </div>
          </div>
        </div>

      </div>

      <!-- ═══════════════════════════════════════════════════════════ -->
      <!-- TAB 2: REPORTS & DIGITAL DNA MANAGER -->
      <!-- ═══════════════════════════════════════════════════════════ -->
      <div class="admin-tab-pane" id="tabPane_reports">
        
        <div class="admin-card">
          <div class="admin-card-header">
            <div class="admin-card-title">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg> Lost &amp; Found Central Registry
            </div>
            <div class="admin-card-actions">
              <button type="button" class="btn-admin secondary sm" id="btnRefreshReports">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-1px; margin-right:3px;"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.1-8.24"/></svg> Refresh Reports
              </button>
            </div>
          </div>

          <!-- Filter & Search Bar -->
          <div class="admin-filter-bar">
            <select class="admin-select" id="reportTypeFilter">
              <option value="all">All Report Types</option>
              <option value="lost">Lost Reports Only</option>
              <option value="found">Found Reports Only</option>
            </select>
            <input type="text" class="admin-input admin-search-input" id="reportSearchInput" placeholder="Search reports by ID, title, description, location..." />
          </div>

          <!-- Reports Table -->
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Report ID</th>
                  <th>Type</th>
                  <th>Photo</th>
                  <th>Title &amp; Description</th>
                  <th>Location &amp; Date</th>
                  <th>Digital DNA</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="reportsTableBody">
                <tr>
                  <td colspan="7" style="text-align: center; color: var(--admin-text-muted); padding: 2rem;">Loading reports...</td>
                </tr>
              </tbody>
            </table>
          </div>

        </div>

      </div>

      <!-- ═══════════════════════════════════════════════════════════ -->
      <!-- TAB 3: CHROMADB VECTOR ENGINE CONTROL -->
      <!-- ═══════════════════════════════════════════════════════════ -->
      <div class="admin-tab-pane" id="tabPane_vector">
        
        <div class="admin-card">
          <div class="admin-card-header">
            <div class="admin-card-title">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M12 2a4 4 0 0 1 4 4v1a4 4 0 0 1-8 0V6a4 4 0 0 1 4-4z"/><path d="M6 11v2a6 6 0 0 0 12 0v-2M12 19v3M8 22h8"/></svg> ChromaDB Late-Fusion Multi-Modal Vector Control
            </div>
          </div>

          <p style="color: var(--admin-text-muted); font-size: 0.875rem; margin-bottom: 1.5rem; line-height: 1.6;">
            The Reunite Neural Vector Engine indexes reports into two separate vector collections: <strong>DINOv2</strong> (384-dimensional visual image embeddings) and <strong>CLIP</strong> (512-dimensional semantic text embeddings) with dynamic 180-day time decay late fusion.
          </p>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
            <div style="background: var(--admin-surface-2); border: 1px solid var(--admin-border); border-radius: 10px; padding: 1.25rem;">
              <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--admin-text-muted); margin-bottom: 0.25rem;">DINOv2 Visual Vectors</div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #10B981;" id="metricChromaDino">0</div>
              <div style="font-size: 0.75rem; color: var(--admin-text-muted); margin-top: 0.25rem;">Visual feature embeddings stored in ChromaDB</div>
            </div>

            <div style="background: var(--admin-surface-2); border: 1px solid var(--admin-border); border-radius: 10px; padding: 1.25rem;">
              <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--admin-text-muted); margin-bottom: 0.25rem;">CLIP Text Vectors</div>
              <div style="font-size: 1.75rem; font-weight: 700; color: #3B82F6;" id="metricChromaClip">0</div>
              <div style="font-size: 0.75rem; color: var(--admin-text-muted); margin-top: 0.25rem;">NLP semantic feature embeddings stored in ChromaDB</div>
            </div>
          </div>

          <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <button type="button" class="btn-admin" id="btnSyncChroma">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/></svg> Re-index &amp; Sync All Reports to ChromaDB
            </button>
            <button type="button" class="btn-admin danger" id="btnPurgeChroma">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg> Purge All ChromaDB Vectors
            </button>
          </div>
        </div>

      </div>

      <!-- ═══════════════════════════════════════════════════════════ -->
      <!-- TAB 4: MEDIA VAULT STORAGE MANAGER -->
      <!-- ═══════════════════════════════════════════════════════════ -->
      <div class="admin-tab-pane" id="tabPane_vault">
        
        <div class="admin-card">
          <div class="admin-card-header">
            <div class="admin-card-title">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><rect width="20" height="8" x="2" y="3" rx="2"/><rect width="20" height="8" x="2" y="13" rx="2"/><line x1="10" x1="10" y1="7" y2="7"/><line x1="10" x1="10" y1="17" y2="17"/></svg> Media Vault Storage &amp; Image Clean Up
            </div>
            <div class="admin-card-actions">
              <span id="vaultScanSummary" style="font-size: 0.8125rem; color: var(--admin-text-muted);"></span>
              <button type="button" class="btn-admin secondary sm" id="btnRescanVault">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-1px; margin-right:3px;"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.1-8.24"/></svg> Rescan
              </button>
            </div>
          </div>

          <p style="color: var(--admin-text-muted); font-size: 0.875rem; margin-bottom: 1.5rem;">
            Images uploaded by users are compartmentalized into <code>media_vault/lost_reports/</code> and <code>media_vault/found_reports/</code>. Use the tool below to detect and purge unlinked or orphaned files.
          </p>

          <div style="margin-bottom: 1.5rem;">
            <button type="button" class="btn-admin secondary" id="btnPurgeOrphans">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg> Purge Orphaned Media Files (Free Storage)
            </button>
          </div>

          <!-- Media Vault Table -->
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Preview</th>
                  <th>Filename &amp; Vault Subfolder</th>
                  <th>File Size</th>
                  <th>Last Modified</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="vaultTableBody">
                <tr>
                  <td colspan="5" style="text-align: center; color: var(--admin-text-muted); padding: 2rem;">Scanning Media Vault...</td>
                </tr>
              </tbody>
            </table>
          </div>

        </div>

      </div>

      <!-- ═══════════════════════════════════════════════════════════ -->
      <!-- TAB 5: ACCESS & SECURITY AUDIT LOGS -->
      <!-- ═══════════════════════════════════════════════════════════ -->
      <div class="admin-tab-pane" id="tabPane_logs">
        
        <div class="admin-card">
          <div class="admin-card-header">
            <div class="admin-card-title">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Real-Time Access &amp; Security Audit Trail
            </div>
            <div class="admin-card-actions" style="display: flex; gap: 0.5rem;">
              <button type="button" class="btn-admin secondary sm" id="btnExportLogs">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px; margin-right:3px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Export JSON
              </button>
              <button type="button" class="btn-admin secondary sm" id="btnClearLogs">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px; margin-right:3px;"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg> Clear Logs
              </button>
            </div>
          </div>

          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Timestamp</th>
                  <th>Action / Event</th>
                  <th>Details / Device Info</th>
                  <th>IP Address</th>
                  <th>Actor</th>
                </tr>
              </thead>
              <tbody id="logsTableBody">
                <tr>
                  <td colspan="5" style="text-align: center; color: var(--admin-text-muted); padding: 2rem;">Loading audit trail...</td>
                </tr>
              </tbody>
            </table>
          </div>

        </div>

      </div>

    </main>

  </div>

  <!-- ───────────────────────────────────────────────────────────── -->
  <!-- MAINTENANCE MODE CONFIRMATION MODAL -->
  <!-- ───────────────────────────────────────────────────────────── -->
  <div class="admin-modal-backdrop" id="maintModalBackdrop" style="display:none;">
    <div class="admin-modal" style="max-width: 520px;" role="dialog" aria-modal="true" aria-labelledby="maintModalTitle">
      <div class="admin-modal-header">
        <div class="admin-card-title" id="maintModalTitle">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg> Enable Maintenance Mode
        </div>
        <button type="button" class="btn-admin secondary sm" id="btnCloseMaintModal" aria-label="Close">&times; Close</button>
      </div>
      <div class="admin-modal-body" style="background: var(--admin-surface); color: var(--admin-text); font-family: var(--admin-font); padding: 1.5rem;">
        <!-- Warning banner -->
        <div class="admin-outage-banner" style="margin-bottom:1.25rem; font-size:0.875rem;">
          <div class="admin-outage-content">
            <div class="admin-outage-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div>
              <div class="admin-outage-title">This will block all non-admin access.</div>
              <div class="admin-outage-desc">All visitors will immediately see the maintenance page. Admins stay logged in.</div>
            </div>
          </div>
        </div>

        <!-- Custom message -->
        <div style="margin-bottom:1.1rem;">
          <label for="maintModalMsg" style="display:block; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--admin-text-muted); margin-bottom:0.4rem;">
            Custom Message <span style="opacity:0.5; font-weight:400;">(optional)</span>
          </label>
          <textarea id="maintModalMsg" class="admin-input" rows="2" placeholder="e.g. Upgrading AI models and database schema…" style="width:100%; resize:vertical; line-height:1.5;" maxlength="500"></textarea>
        </div>

        <!-- ETA -->
        <div style="margin-bottom:1.1rem;">
          <label for="maintModalEta" style="display:block; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--admin-text-muted); margin-bottom:0.4rem;">
            Estimated Return Time <span style="opacity:0.5; font-weight:400;">(optional)</span>
          </label>
          <input type="datetime-local" id="maintModalEta" class="admin-input" style="width:100%;">
        </div>

        <!-- Support email -->
        <div style="margin-bottom:0.75rem;">
          <label for="maintModalEmail" style="display:block; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--admin-text-muted); margin-bottom:0.4rem;">
            Support Email <span style="opacity:0.5; font-weight:400;">(optional)</span>
          </label>
          <input type="email" id="maintModalEmail" class="admin-input" placeholder="support@example.com" style="width:100%;">
        </div>
      </div>
      <div class="admin-modal-footer" style="gap:0.75rem;">
        <button type="button" class="btn-admin secondary" id="btnCancelMaint">Cancel</button>
        <button type="button" class="btn-admin danger" id="btnConfirmMaint">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Confirm — Enable Maintenance
        </button>
      </div>
    </div>
  </div>

  <!-- ───────────────────────────────────────────────────────────── -->
  <!-- DIGITAL DNA INSPECTOR MODAL -->
  <!-- ───────────────────────────────────────────────────────────── -->
  <div class="admin-modal-backdrop" id="dnaModalBackdrop">
    <div class="admin-modal">
      <div class="admin-modal-header">
        <div class="admin-card-title" id="dnaModalTitle">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg> Digital DNA Inspector
        </div>
        <button type="button" class="btn-admin secondary sm" id="btnCloseDnaModal">&times; Close</button>
      </div>
      <div class="admin-modal-body" id="dnaModalContent">
        Loading DNA payload...
      </div>
      <div class="admin-modal-footer">
        <button type="button" class="btn-admin secondary" onclick="document.getElementById('dnaModalBackdrop').style.display='none'">Dismiss</button>
      </div>
    </div>
  </div>

  <script src="js/admin.js?v=<?php echo time(); ?>"></script>
</body>
</html>
