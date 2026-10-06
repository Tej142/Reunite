/**
 * Reunite System Administration & Site Maintenance Engine
 * Manual On-Demand Health Diagnostics & Real-Time Maintenance Tools
 */

document.addEventListener('DOMContentLoaded', () => {
  const adminAuthScreen = document.getElementById('adminAuthScreen');
  const adminDashboard = document.getElementById('adminDashboard');
  const adminLoginForm = document.getElementById('adminLoginForm');
  const adminPasscodeInput = document.getElementById('adminPasscode');
  const btnAdminLogout = document.getElementById('btnAdminLogout');
  const adminUserPillName = document.getElementById('adminUserPillName');

  // DNA Modal Elements
  const dnaModalBackdrop = document.getElementById('dnaModalBackdrop');
  const dnaModalTitle = document.getElementById('dnaModalTitle');
  const dnaModalContent = document.getElementById('dnaModalContent');
  const btnCloseDnaModal = document.getElementById('btnCloseDnaModal');

  // Outage Banner Elements
  const serverOutageBanner = document.getElementById('serverOutageBanner');
  const outageBannerTitle = document.getElementById('outageBannerTitle');
  const outageBannerDesc = document.getElementById('outageBannerDesc');
  const outageBannerHint = document.getElementById('outageBannerHint');
  const btnRetryOutagePing = document.getElementById('btnRetryOutagePing');
  const adminPulseDot = document.querySelector('.admin-pulse-dot');

  // Cached audit logs for export
  let currentLogsData = [];

  // Theme Toggle Support
  const savedAdminTheme = localStorage.getItem('reunite_admin_theme') || 'dark';
  if (savedAdminTheme === 'light') {
    document.body.classList.add('admin-light');
    document.documentElement.setAttribute('data-admin-theme', 'light');
  }

  const themeToggleBtns = document.querySelectorAll('.btn-admin-theme');
  themeToggleBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const isLight = document.body.classList.toggle('admin-light');
      const newTheme = isLight ? 'light' : 'dark';
      document.documentElement.setAttribute('data-admin-theme', newTheme);
      localStorage.setItem('reunite_admin_theme', newTheme);
    });
  });

  // Init
  checkAdminAuth();

  // ── 1. Authentication Check ───────────────────────────────
  async function checkAdminAuth() {
    try {
      const res = await fetch('../Backend/admin_api.php?action=check_auth');
      const data = await res.json();
      if (data.success && data.authenticated) {
        showDashboard(data.admin_name || 'System Administrator');
      } else {
        showAuthScreen();
      }
    } catch (e) {
      showAuthScreen();
    }
  }

  function showAuthScreen() {
    if (adminAuthScreen) adminAuthScreen.style.display = 'flex';
    if (adminDashboard) adminDashboard.style.display = 'none';
  }

  function showDashboard(name) {
    if (adminAuthScreen) adminAuthScreen.style.display = 'none';
    if (adminDashboard) adminDashboard.style.display = 'block';
    if (adminUserPillName) adminUserPillName.textContent = name;
    
    // Initial load of reports and diagnostics on login
    loadSystemDiagnostics(true);
    loadReportsTable();
  }

  // Login submission
  if (adminLoginForm) {
    adminLoginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const code = adminPasscodeInput ? adminPasscodeInput.value.trim() : '';
      if (!code) return;

      const submitBtn = adminLoginForm.querySelector('button[type="submit"]');
      const origText = submitBtn ? submitBtn.textContent : '';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Verifying Security Clearance...';
      }

      try {
        const formData = new FormData();
        formData.append('action', 'login');
        formData.append('passcode', code);

        const res = await fetch('../Backend/admin_api.php', {
          method: 'POST',
          body: formData
        });

        const data = await res.json();
        if (data.success) {
          showDashboard('System Administrator');
          showToast('Clearance Granted', 'Welcome to Reunite System Maintenance Console.', 'success');
        } else {
          showToast('Access Denied', data.error || 'Invalid Admin Clearance Key.', 'error');
          if (adminPasscodeInput) {
            adminPasscodeInput.value = '';
            adminPasscodeInput.focus();
          }
        }
      } catch (err) {
        showToast('Connection Error', 'Could not reach administration backend.', 'error');
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = origText;
        }
      }
    });
  }

  // Logout
  if (btnAdminLogout) {
    btnAdminLogout.addEventListener('click', async () => {
      await fetch('../Backend/admin_api.php?action=logout');
      showAuthScreen();
      showToast('Logged Out', 'Admin session terminated safely.', 'info');
    });
  }

  // ── 2. Tab Navigation ─────────────────────────────────────
  const tabBtns = document.querySelectorAll('.admin-tab-btn');
  const tabPanes = document.querySelectorAll('.admin-tab-pane');

  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const tabKey = btn.getAttribute('data-tab');
      tabBtns.forEach(b => b.classList.remove('active'));
      tabPanes.forEach(p => p.classList.remove('active'));

      btn.classList.add('active');
      const pane = document.getElementById(`tabPane_${tabKey}`);
      if (pane) pane.classList.add('active');

      // Trigger lazy tab refresh on user navigation
      if (tabKey === 'reports') loadReportsTable();
      if (tabKey === 'vector') loadVectorStats();
      if (tabKey === 'vault') scanMediaVault();
      if (tabKey === 'logs') loadAuditLogs();
    });
  });

  // ── 3. Health & Diagnostics HUD (Manual On-Demand Execution) ───
  // Note: Only runs on user click or initial dashboard login; no background polling loops.
  async function loadSystemDiagnostics(isInitial = false) {
    const btnHealth = document.getElementById('btnRefreshDiagnostics');
    if (btnHealth && !isInitial) {
      btnHealth.disabled = true;
      btnHealth.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-1px; margin-right:3px;"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.1-8.24"/></svg> Checking Diagnostics...';
    }

    try {
      const res = await fetch('../Backend/admin_api.php?action=get_status');
      const data = await res.json();
      if (!data.success) {
        handleFullOutage('API Error: Backend returned unsuccessful response.');
        return;
      }

      // Update tracked maintenance state for the toggle button logic
      if (typeof currentMaintenanceState !== 'undefined') {
        currentMaintenanceState    = !!data.maintenance_mode;
        currentMaintenanceSettings = data.maintenance_settings || {};
      }

      const isAiOnline = Boolean(data.ai_engine && data.ai_engine.online);
      const isDbOnline = Boolean(data.database && data.database.connected);

      // ── Handle Outage States ──────────────────────────────
      if (!isAiOnline || !isDbOnline) {
        if (serverOutageBanner) serverOutageBanner.style.display = 'flex';
        if (adminPulseDot) adminPulseDot.classList.add('offline');

        if (!isAiOnline && !isDbOnline) {
          if (outageBannerTitle) outageBannerTitle.textContent = 'CRITICAL: AI Engine & Database Server are BOTH OFFLINE';
          if (outageBannerDesc) outageBannerDesc.textContent = 'Both the Python Flask AI Server and MySQL database are unreachable. All site operations, vector searches, and data lookups are halted.';
          if (outageBannerHint) outageBannerHint.innerHTML = '1. Start MySQL in XAMPP &nbsp;|&nbsp; 2. Start Flask: <span class="admin-code-snippet">python flask_server.py</span>';
          if (adminUserPillName) adminUserPillName.textContent = 'Dual Server Outage';
          if (outageBannerTitle) outageBannerTitle.textContent = 'Python Flask AI Cloud Server is OFFLINE';
          if (outageBannerDesc) outageBannerDesc.textContent = 'The cloud AI multi-modal engine (reunite-ai-backend.onrender.com) is waking up or unreachable. Visual ONNX vision parsing, DINOv2 & CLIP vector embeddings, and neural late-fusion matching are currently paused.';
          if (outageBannerHint) outageBannerHint.innerHTML = 'Check Render.com dashboard or restart your AI backend web service.';
          if (adminUserPillName) adminUserPillName.textContent = 'AI Server Offline';
        } else {
          if (outageBannerTitle) outageBannerTitle.textContent = 'MySQL Database Connection Failed';
          if (outageBannerDesc) outageBannerDesc.textContent = 'Could not establish connection with MySQL lost_connect_db.';
          if (outageBannerHint) outageBannerHint.innerHTML = 'Ensure MySQL service is running in your XAMPP Control Panel.';
          if (adminUserPillName) adminUserPillName.textContent = 'Database Offline';
        }

        // Disable vector sync buttons
        if (btnSyncChroma) btnSyncChroma.disabled = true;
        if (btnPurgeChroma) btnPurgeChroma.disabled = true;

      } else {
        // All systems online & healthy
        if (serverOutageBanner) serverOutageBanner.style.display = 'none';
        if (adminPulseDot) adminPulseDot.classList.remove('offline');
        if (adminUserPillName) adminUserPillName.textContent = 'System Administrator (All Systems Online)';

        // Re-enable vector sync buttons
        if (btnSyncChroma) btnSyncChroma.disabled = false;
        if (btnPurgeChroma) btnPurgeChroma.disabled = false;
      }

      // ── Update Health Indicators ──────────────────────────
      const setEl = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
      };

      // Server metrics
      setEl('metricPhpVersion', data.server.php_version);
      setEl('metricMemory', data.server.memory_usage);
      setEl('metricServerTime', data.server.server_time);

      // AI Engine & Vector DB
      const flaskBadge = document.getElementById('badgeFlaskStatus');
      if (flaskBadge) {
        if (isAiOnline) {
          flaskBadge.className = 'admin-badge success';
          flaskBadge.textContent = `Online (${data.ai_engine.latency_ms}ms)`;
        } else {
          flaskBadge.className = 'admin-badge danger';
          flaskBadge.textContent = 'OFFLINE / UNREACHABLE';
        }
      }

      setEl('metricChromaDino', data.chromadb.dinov2_count);
      setEl('metricChromaClip', data.chromadb.clip_count);
      setEl('metricChromaTotal', data.chromadb.total_vectors);

      // Database
      setEl('metricLostCount', data.database.lost_count);
      setEl('metricFoundCount', data.database.found_count);
      setEl('metricDnaCount', data.database.dna_count);
      setEl('metricUsersCount', data.database.users_count);

      // Media Vault
      setEl('metricVaultFiles', data.media_vault.total_files);
      setEl('metricVaultSize', data.media_vault.total_size);

      // Maintenance Mode Status — update badge + card UI
      const maintSettings = data.maintenance_settings || {};
      updateMaintenanceUI(data.maintenance_mode, maintSettings);

      if (!isInitial) {
        showToast('Diagnostics Complete', `Flask AI (${isAiOnline ? 'Online' : 'Offline'}) | DB (${isDbOnline ? 'Connected' : 'Error'})`, isAiOnline && isDbOnline ? 'success' : 'warning');
      }

    } catch (e) {
      console.error('Error fetching diagnostics:', e);
      handleFullOutage('Network Error: Could not contact administration backend.');
    } finally {
      if (btnHealth) {
        btnHealth.disabled = false;
        btnHealth.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px; margin-right:3px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> Run System Health Check';
      }
    }
  }

  function handleFullOutage(reason) {
    if (serverOutageBanner) serverOutageBanner.style.display = 'flex';
    if (adminPulseDot) adminPulseDot.classList.add('offline');
    if (outageBannerTitle) outageBannerTitle.textContent = 'Administration Backend Unreachable';
    if (outageBannerDesc) outageBannerDesc.textContent = reason || 'Local web server or PHP backend is not responding.';
    if (outageBannerHint) outageBannerHint.innerHTML = 'Ensure Apache &amp; MySQL are running in XAMPP.';
    if (adminUserPillName) adminUserPillName.textContent = 'Backend Outage';
  }

  // Health Check Buttons (Explicit user click only)
  const btnRefreshDiagnostics = document.getElementById('btnRefreshDiagnostics');
  if (btnRefreshDiagnostics) {
    btnRefreshDiagnostics.addEventListener('click', () => {
      loadSystemDiagnostics(false);
    });
  }

  if (btnRetryOutagePing) {
    btnRetryOutagePing.addEventListener('click', async () => {
      btnRetryOutagePing.textContent = 'Checking...';
      btnRetryOutagePing.disabled = true;
      await loadSystemDiagnostics(false);
      btnRetryOutagePing.disabled = false;
      btnRetryOutagePing.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-1px; margin-right:3px;"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.1-8.24"/></svg> Retry Connection';
    });
  }

  // ── Maintenance UI State Management ─────────────────────
  function updateMaintenanceUI(isOn, settings) {
    settings = settings || {};
    const badge         = document.getElementById('badgeMaintenanceMode');
    const toggleBtn     = document.getElementById('btnToggleMaintenance');
    const statusTitle   = document.getElementById('maintStatusTitle');
    const statusSub     = document.getElementById('maintStatusSub');
    const indicator     = document.getElementById('maintStatusIndicator');
    const modeLabel     = document.getElementById('maintModeLabel');
    const etaLabel      = document.getElementById('maintEtaLabel');
    const activeInfo    = document.getElementById('adminMaintActiveInfo');
    const infoMsg       = document.getElementById('maintInfoMsg');
    const infoMsgText   = document.getElementById('maintInfoMsgText');
    const infoEta       = document.getElementById('maintInfoEta');
    const infoEtaText   = document.getElementById('maintInfoEtaText');
    const infoEmail     = document.getElementById('maintInfoEmail');
    const infoEmailText = document.getElementById('maintInfoEmailText');
    const maintCard     = document.getElementById('adminMaintCard');

    if (isOn) {
      if (maintCard) maintCard.classList.add('is-active');
      // Badge
      if (badge) { badge.className = 'admin-badge danger'; badge.textContent = 'Maintenance Mode Active'; }
      // Toggle button
      if (toggleBtn) {
        toggleBtn.textContent = 'Disable Maintenance Mode';
        toggleBtn.className = 'btn-admin btn-maint-toggle active danger';
      }
      // Status card
      if (statusTitle) statusTitle.textContent = 'Maintenance Mode Active';
      if (statusSub)   statusSub.textContent   = 'Non-admin users are redirected to the maintenance page.';
      if (indicator)   { indicator.style.background = 'var(--admin-danger)'; indicator.style.boxShadow = '0 0 10px var(--admin-danger)'; }
      if (modeLabel)   { modeLabel.textContent = 'Maintenance Active'; modeLabel.style.color = 'var(--admin-danger)'; }
      if (etaLabel && settings.eta) {
        const d = new Date(settings.eta);
        etaLabel.textContent = 'ETA: ' + d.toLocaleString();
      } else if (etaLabel) {
        etaLabel.textContent = 'Non-admin users blocked';
      }
      // Active info panel
      if (activeInfo) activeInfo.style.display = 'block';
      if (settings.message && infoMsg && infoMsgText) {
        infoMsgText.textContent = settings.message;
        infoMsg.style.display = 'flex';
      } else if (infoMsg) infoMsg.style.display = 'none';
      if (settings.eta && infoEta && infoEtaText) {
        const d = new Date(settings.eta);
        infoEtaText.textContent = d.toLocaleString();
        infoEta.style.display = 'flex';
      } else if (infoEta) infoEta.style.display = 'none';
      if (settings.support_email && infoEmail && infoEmailText) {
        infoEmailText.textContent = settings.support_email;
        infoEmail.style.display = 'flex';
      } else if (infoEmail) infoEmail.style.display = 'none';

    } else {
      if (maintCard) maintCard.classList.remove('is-active');
      // Badge
      if (badge) { badge.className = 'admin-badge success'; badge.textContent = 'Site is Live'; }
      // Toggle button
      if (toggleBtn) {
        toggleBtn.textContent = 'Enable Maintenance Mode';
        toggleBtn.className = 'btn-admin btn-maint-toggle secondary';
      }
      // Status card
      if (statusTitle) statusTitle.textContent = 'Site is Live';
      if (statusSub)   statusSub.textContent   = 'All users have full access to the platform.';
      if (indicator)   { indicator.style.background = 'var(--admin-success)'; indicator.style.boxShadow = '0 0 10px var(--admin-success)'; }
      if (modeLabel)   { modeLabel.textContent = 'Live'; modeLabel.style.color = 'var(--admin-success)'; }
      if (etaLabel)    etaLabel.textContent = 'All users have full access';
      // Hide active info
      if (activeInfo)  activeInfo.style.display = 'none';
    }
  }

  // ── Maintenance Modal ─────────────────────────────────────
  const maintModalBackdrop = document.getElementById('maintModalBackdrop');
  const btnCloseMaintModal = document.getElementById('btnCloseMaintModal');
  const btnCancelMaint     = document.getElementById('btnCancelMaint');
  const btnConfirmMaint    = document.getElementById('btnConfirmMaint');
  const maintModalTitle    = document.getElementById('maintModalTitle');

  // Current maintenance state (updated by loadSystemDiagnostics)
  let currentMaintenanceState  = false;
  let currentMaintenanceSettings = {};

  function openMaintModal(isCurrentlyOn) {
    if (!maintModalBackdrop) return;
    if (isCurrentlyOn) {
      // Turning OFF — no confirmation needed, do it directly
      applyMaintenance(false, null, null, null);
      return;
    }
    // Turning ON — show confirmation modal
    if (maintModalTitle) maintModalTitle.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg> Enable Maintenance Mode';
    maintModalBackdrop.style.display = 'flex';
    // Pre-fill ETA min value to now
    const etaInput = document.getElementById('maintModalEta');
    if (etaInput && !etaInput.value) {
      const now = new Date();
      now.setMinutes(now.getMinutes() + 30);
      etaInput.value = now.toISOString().slice(0, 16);
    }
  }

  function closeMaintModal() {
    if (maintModalBackdrop) maintModalBackdrop.style.display = 'none';
  }

  async function applyMaintenance(enable, message, eta, email) {
    const toggleBtn = document.getElementById('btnToggleMaintenance');
    if (toggleBtn) { toggleBtn.disabled = true; }
    if (btnConfirmMaint) { btnConfirmMaint.disabled = true; btnConfirmMaint.textContent = enable ? 'Enabling…' : 'Disabling…'; }

    try {
      const body = JSON.stringify({
        enabled:       enable ? 1 : 0,
        message:       message || '',
        eta:           eta || '',
        support_email: email || '',
      });
      const res  = await fetch('../Backend/admin_api.php?action=toggle_maintenance', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    body,
      });
      const data = await res.json();

      if (data.success) {
        currentMaintenanceState    = data.maintenance_mode;
        currentMaintenanceSettings = {
          message:       data.message_text,
          eta:           data.eta,
          support_email: data.support_email,
        };
        updateMaintenanceUI(data.maintenance_mode, currentMaintenanceSettings);
        closeMaintModal();
        showToast(
          data.maintenance_mode ? 'Maintenance Mode On' : 'Site Live',
          data.message,
          data.maintenance_mode ? 'warning' : 'success'
        );
      } else {
        showToast('Error', data.error || 'Failed to update maintenance mode.', 'error');
      }
    } catch (err) {
      console.error(err);
      showToast('Network Error', 'Could not reach the admin API.', 'error');
    } finally {
      if (toggleBtn) { toggleBtn.disabled = false; }
      if (btnConfirmMaint) {
        btnConfirmMaint.disabled = false;
        btnConfirmMaint.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Confirm — Enable Maintenance';
      }
    }
  }

  // Toggle button — open modal or disable directly
  const btnToggleMaintenance = document.getElementById('btnToggleMaintenance');
  if (btnToggleMaintenance) {
    btnToggleMaintenance.addEventListener('click', () => {
      openMaintModal(currentMaintenanceState);
    });
  }

  // Modal controls
  if (btnCloseMaintModal) btnCloseMaintModal.addEventListener('click', closeMaintModal);
  if (btnCancelMaint)     btnCancelMaint.addEventListener('click', closeMaintModal);

  // Backdrop click to close
  if (maintModalBackdrop) {
    maintModalBackdrop.addEventListener('click', (e) => {
      if (e.target === maintModalBackdrop) closeMaintModal();
    });
  }

  // Confirm button
  if (btnConfirmMaint) {
    btnConfirmMaint.addEventListener('click', () => {
      const msg   = (document.getElementById('maintModalMsg')?.value || '').trim();
      const eta   = document.getElementById('maintModalEta')?.value || '';
      const email = (document.getElementById('maintModalEmail')?.value || '').trim();
      applyMaintenance(true, msg || null, eta || null, email || null);
    });
  }


  // ── 4. Reports & Digital DNA Manager ───────────────────────
  const reportsTableBody = document.getElementById('reportsTableBody');
  const reportTypeFilter = document.getElementById('reportTypeFilter');
  const reportSearchInput = document.getElementById('reportSearchInput');
  const btnRefreshReports = document.getElementById('btnRefreshReports');

  if (btnRefreshReports) {
    btnRefreshReports.addEventListener('click', () => {
      loadReportsTable();
      showToast('Refreshed', 'Reports list updated from database.', 'info');
    });
  }

  async function loadReportsTable() {
    if (!reportsTableBody) return;
    reportsTableBody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: var(--admin-text-muted); padding: 2rem;">Loading reports from database...</td></tr>`;

    const type = reportTypeFilter ? reportTypeFilter.value : 'all';
    const search = reportSearchInput ? reportSearchInput.value.trim() : '';

    try {
      const res = await fetch(`../Backend/admin_api.php?action=list_reports&type=${encodeURIComponent(type)}&search=${encodeURIComponent(search)}`);
      const data = await res.json();
      if (!data.success || !data.reports || data.reports.length === 0) {
        reportsTableBody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: var(--admin-text-muted); padding: 2rem;">No reports found matching criteria.</td></tr>`;
        return;
      }

      let html = '';
      data.reports.forEach(r => {
        const isLost = r.report_type === 'lost';
        const typeBadge = isLost ? `<span class="admin-badge warning">Lost</span>` : `<span class="admin-badge info">Found</span>`;
        const dnaBadge = r.has_dna 
          ? `<button type="button" class="btn-admin sm secondary btn-view-dna" data-report-id="${escapeHtml(r.formatted_id)}" data-db-id="${r.id}" data-type="${isLost ? 'LOST' : 'FOUND'}"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px; margin-right:3px;"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg> View DNA</button>` 
          : `<span style="font-size:0.75rem; color: var(--admin-text-muted);">None</span>`;
        
        let thumbHtml = `<div style="width:40px;height:40px;border-radius:6px;background:var(--admin-surface-3);display:flex;align-items:center;justify-content:center;color:var(--admin-text-muted);"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg></div>`;
        if (r.image_path) {
          thumbHtml = `<img src="../${escapeHtml(r.image_path)}" class="admin-table-thumb" alt="Report" onerror="this.outerHTML='<div class=\\'admin-table-thumb\\' style=\\'display:flex;align-items:center;justify-content:center;\\'><svg width=\\'16\\' height=\\'16\\' viewBox=\\'0 0 24 24\\' fill=\\'none\\' stroke=\\'currentColor\\' stroke-width=\\'2\\'><rect width=\\'18\\' height=\\'18\\' x=\\'3\\' y=\\'3\\' rx=\\'2\\'/></svg></div>'" />`;
        }

        html += `
          <tr>
            <td>
              <strong>${escapeHtml(r.formatted_id)}</strong>
              <div style="font-size:0.75rem; color:var(--admin-text-muted);">${escapeHtml(r.created_at || '')}</div>
            </td>
            <td>${typeBadge}</td>
            <td>${thumbHtml}</td>
            <td>
              <div style="font-weight:600;">${escapeHtml(r.title || r.category || 'Untitled')}</div>
              <div style="font-size:0.75rem; color:var(--admin-text-muted); max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(r.description || '')}</div>
            </td>
            <td>
              <div>${escapeHtml(r.location || 'Campus')}</div>
              <div style="font-size:0.75rem; color:var(--admin-text-muted);">${escapeHtml(r.date_lost || r.date_found || '')}</div>
            </td>
            <td>${dnaBadge}</td>
            <td>
              <button type="button" class="btn-admin sm danger btn-delete-report" data-type="${r.report_type}" data-db-id="${r.id}" data-formatted-id="${escapeHtml(r.formatted_id)}" title="Purge from DB, Media Vault, and ChromaDB">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px; margin-right:2px;"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/></svg> Purge
              </button>
            </td>
          </tr>
        `;
      });

      reportsTableBody.innerHTML = html;
      bindReportTableActions();
    } catch (e) {
      reportsTableBody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: var(--admin-danger); padding: 2rem;">Failed to load reports.</td></tr>`;
    }
  }

  if (reportTypeFilter) reportTypeFilter.addEventListener('change', () => loadReportsTable());
  if (reportSearchInput) {
    let timer;
    reportSearchInput.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => loadReportsTable(), 400);
    });
  }

  function bindReportTableActions() {
    // DNA Inspector
    document.querySelectorAll('.btn-view-dna').forEach(btn => {
      btn.addEventListener('click', async () => {
        const reportId = btn.getAttribute('data-report-id');
        const dbId = btn.getAttribute('data-db-id');
        const type = btn.getAttribute('data-type');
        openDnaModal(reportId, dbId, type);
      });
    });

    // Delete Report
    document.querySelectorAll('.btn-delete-report').forEach(btn => {
      btn.addEventListener('click', async () => {
        const type = btn.getAttribute('data-type');
        const dbId = btn.getAttribute('data-db-id');
        const fId = btn.getAttribute('data-formatted-id');

        if (!confirm(`Are you sure you want to permanently purge ${fId}?\nThis will remove the report, its Digital DNA, its Media Vault image, and its ChromaDB vector embedding.`)) {
          return;
        }

        try {
          const fd = new FormData();
          fd.append('action', 'delete_report');
          fd.append('report_type', type);
          fd.append('db_id', dbId);
          fd.append('formatted_id', fId);

          const res = await fetch('../Backend/admin_api.php', { method: 'POST', body: fd });
          const data = await res.json();
          if (data.success) {
            showToast('Purged', data.message, 'success');
            loadReportsTable();
          } else {
            showToast('Error', data.error || 'Failed to delete report.', 'error');
          }
        } catch (err) {
          showToast('Error', 'Deletion failed.', 'error');
        }
      });
    });
  }

  // Open DNA Modal
  async function openDnaModal(reportId, dbId, type) {
    if (!dnaModalBackdrop || !dnaModalContent) return;
    dnaModalTitle.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg> Digital DNA Inspector — ${escapeHtml(reportId)}`;
    dnaModalContent.textContent = 'Decrypting and retrieving DNA vector payload...';
    dnaModalBackdrop.style.display = 'flex';

    try {
      const res = await fetch(`../Backend/admin_api.php?action=get_dna&report_id=${encodeURIComponent(reportId)}&db_id=${dbId}&type=${type}`);
      const data = await res.json();
      if (data.success && data.dna) {
        dnaModalContent.textContent = JSON.stringify(data.dna, null, 2);
      } else {
        dnaModalContent.textContent = data.error || 'No DNA structure found.';
      }
    } catch (e) {
      dnaModalContent.textContent = 'Error parsing Digital DNA.';
    }
  }

  if (btnCloseDnaModal) {
    btnCloseDnaModal.addEventListener('click', () => {
      if (dnaModalBackdrop) dnaModalBackdrop.style.display = 'none';
    });
  }

  if (dnaModalBackdrop) {
    dnaModalBackdrop.addEventListener('click', (e) => {
      if (e.target === dnaModalBackdrop) dnaModalBackdrop.style.display = 'none';
    });
  }

  // ── 5. ChromaDB Vector Engine Control ──────────────────────
  async function loadVectorStats() {
    // Lazy update of Chroma statistics
    try {
      const res = await fetch('../Backend/admin_api.php?action=get_status');
      const data = await res.json();
      if (data.success && data.chromadb) {
        const setEl = (id, val) => {
          const el = document.getElementById(id);
          if (el) el.textContent = val;
        };
        setEl('metricChromaDino', data.chromadb.dinov2_count);
        setEl('metricChromaClip', data.chromadb.clip_count);
      }
    } catch (e) {}
  }

  const btnSyncChroma = document.getElementById('btnSyncChroma');
  if (btnSyncChroma) {
    btnSyncChroma.addEventListener('click', async () => {
      btnSyncChroma.disabled = true;
      btnSyncChroma.textContent = 'Syncing All MySQL Records to ChromaDB...';

      try {
        const res = await fetch('../Backend/admin_api.php?action=sync_chroma');
        const data = await res.json();
        if (data.success) {
          showToast('Sync Completed', data.message, 'success');
          loadVectorStats();
        } else {
          showToast('Sync Notice', data.error || 'ChromaDB resync failed.', 'error');
        }
      } catch (err) {
        showToast('Error', 'Failed to reach AI Vector Server.', 'error');
      } finally {
        btnSyncChroma.disabled = false;
        btnSyncChroma.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/></svg> Re-index &amp; Sync All Reports to ChromaDB';
      }
    });
  }

  const btnPurgeChroma = document.getElementById('btnPurgeChroma');
  if (btnPurgeChroma) {
    btnPurgeChroma.addEventListener('click', async () => {
      if (!confirm('CAUTION: This will clear all existing vector embeddings in ChromaDB.\nAre you sure you want to proceed?')) {
        return;
      }

      btnPurgeChroma.disabled = true;
      btnPurgeChroma.textContent = 'Purging Vector Collections...';

      try {
        const res = await fetch('../Backend/admin_api.php?action=purge_chroma');
        const data = await res.json();
        if (data.success) {
          showToast('Purged', data.message || 'All ChromaDB vectors purged.', 'success');
          loadVectorStats();
        } else {
          showToast('Error', data.error || 'Failed to purge ChromaDB.', 'error');
        }
      } catch (e) {
        showToast('Error', 'Server error.', 'error');
      } finally {
        btnPurgeChroma.disabled = false;
        btnPurgeChroma.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg> Purge All ChromaDB Vectors';
      }
    });
  }

  // ── 6. Media Vault Storage Manager ─────────────────────────
  const vaultTableBody = document.getElementById('vaultTableBody');
  const vaultScanSummary = document.getElementById('vaultScanSummary');

  async function scanMediaVault() {
    if (!vaultTableBody) return;
    vaultTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--admin-text-muted); padding: 2rem;">Scanning Media Vault folders...</td></tr>`;

    try {
      const res = await fetch('../Backend/admin_api.php?action=scan_media_vault');
      const data = await res.json();
      if (!data.success) return;

      if (vaultScanSummary) {
        vaultScanSummary.textContent = `Total Files: ${data.total_files} | Orphaned / Unlinked: ${data.orphaned_count}`;
      }

      if (data.files.length === 0) {
        vaultTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--admin-text-muted); padding: 2rem;">Media Vault is currently empty.</td></tr>`;
        return;
      }

      let html = '';
      data.files.forEach(f => {
        const statusBadge = f.is_orphaned 
          ? `<span class="admin-badge warning">Orphaned (Unlinked)</span>` 
          : `<span class="admin-badge success">Active in DB</span>`;

        html += `
          <tr>
            <td>
              <img src="../${escapeHtml(f.rel_path)}" class="admin-table-thumb" alt="Upload" onerror="this.outerHTML='<div class=\\'admin-table-thumb\\' style=\\'display:flex;align-items:center;justify-content:center;color:var(--admin-text-muted);\\'><svg width=\\'16\\' height=\\'16\\' viewBox=\\'0 0 24 24\\' fill=\\'none\\' stroke=\\'currentColor\\' stroke-width=\\'2\\'><path d=\\'M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z\\'/></svg></div>'" />
            </td>
            <td>
              <strong>${escapeHtml(f.filename)}</strong>
              <div style="font-size:0.75rem; color:var(--admin-text-muted);">${escapeHtml(f.folder)}</div>
            </td>
            <td>${escapeHtml(f.size_formatted)}</td>
            <td>${escapeHtml(f.modified)}</td>
            <td>${statusBadge}</td>
          </tr>
        `;
      });

      vaultTableBody.innerHTML = html;
    } catch (e) {
      vaultTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--admin-danger); padding: 2rem;">Failed to scan Media Vault.</td></tr>`;
    }
  }

  const btnPurgeOrphans = document.getElementById('btnPurgeOrphans');
  if (btnPurgeOrphans) {
    btnPurgeOrphans.addEventListener('click', async () => {
      if (!confirm('Are you sure you want to delete all unreferenced images from Media Vault?')) return;

      try {
        const res = await fetch('../Backend/admin_api.php?action=purge_orphans');
        const data = await res.json();
        if (data.success) {
          showToast('Purged', data.message, 'success');
          scanMediaVault();
        } else {
          showToast('Error', data.error || 'Failed to purge orphans.', 'error');
        }
      } catch (err) {
        showToast('Error', 'Purge failed.', 'error');
      }
    });
  }

  const btnRescanVault = document.getElementById('btnRescanVault');
  if (btnRescanVault) {
    btnRescanVault.addEventListener('click', () => {
      scanMediaVault();
      showToast('Rescanned', 'Media Vault directory re-scanned.', 'info');
    });
  }

  // ── 7. Audit & Access Logs ─────────────────────────────────
  const logsTableBody = document.getElementById('logsTableBody');

  async function loadAuditLogs() {
    if (!logsTableBody) return;
    logsTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--admin-text-muted); padding: 2rem;">Loading audit logs...</td></tr>`;

    try {
      const res = await fetch('../Backend/admin_api.php?action=get_logs&limit=100');
      const data = await res.json();
      if (!data.success || !data.logs || data.logs.length === 0) {
        currentLogsData = [];
        logsTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--admin-text-muted); padding: 2rem;">No audit logs recorded yet.</td></tr>`;
        return;
      }

      currentLogsData = data.logs;
      let html = '';
      data.logs.forEach(l => {
        html += `
          <tr>
            <td style="font-family:var(--admin-mono); font-size:0.75rem;">${escapeHtml(l.created_at || '')}</td>
            <td><strong style="color:var(--admin-accent);">${escapeHtml(l.action || '')}</strong></td>
            <td>${escapeHtml(l.device_info || 'System / Direct')}</td>
            <td style="font-family:var(--admin-mono); font-size:0.75rem;">${escapeHtml(l.ip_address || '127.0.0.1')}</td>
            <td>${escapeHtml(l.user_name || (l.user_id ? 'User #' + l.user_id : 'System'))}</td>
          </tr>
        `;
      });

      logsTableBody.innerHTML = html;
    } catch (e) {
      logsTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--admin-danger); padding: 2rem;">Failed to load audit logs.</td></tr>`;
    }
  }

  const btnClearLogs = document.getElementById('btnClearLogs');
  if (btnClearLogs) {
    btnClearLogs.addEventListener('click', async () => {
      if (!confirm('Are you sure you want to clear all audit logs?')) return;
      try {
        const res = await fetch('../Backend/admin_api.php?action=clear_logs');
        const data = await res.json();
        if (data.success) {
          showToast('Cleared', data.message, 'success');
          loadAuditLogs();
        }
      } catch (err) {
        showToast('Error', 'Failed to clear logs.', 'error');
      }
    });
  }

  // Export JSON Logs
  const btnExportLogs = document.getElementById('btnExportLogs');
  if (btnExportLogs) {
    btnExportLogs.addEventListener('click', () => {
      if (!currentLogsData || currentLogsData.length === 0) {
        showToast('Export Notice', 'No audit log entries available to export.', 'warning');
        return;
      }
      const jsonStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(currentLogsData, null, 2));
      const downloadAnchor = document.createElement('a');
      downloadAnchor.setAttribute("href", jsonStr);
      downloadAnchor.setAttribute("download", `reunite_audit_logs_${new Date().toISOString().slice(0,10)}.json`);
      document.body.appendChild(downloadAnchor);
      downloadAnchor.click();
      downloadAnchor.remove();
      showToast('Exported', 'Audit logs exported to JSON file.', 'success');
    });
  }

  // ── 8. Database Optimization ───────────────────────────────
  const btnOptimizeDb = document.getElementById('btnOptimizeDb');
  if (btnOptimizeDb) {
    btnOptimizeDb.addEventListener('click', async () => {
      btnOptimizeDb.disabled = true;
      btnOptimizeDb.textContent = 'Optimizing Tables...';
      try {
        const res = await fetch('../Backend/admin_api.php?action=optimize_db');
        const data = await res.json();
        if (data.success) {
          showToast('Optimized', data.message, 'success');
        }
      } catch (err) {
        showToast('Error', 'Optimization failed.', 'error');
      } finally {
        btnOptimizeDb.disabled = false;
        btnOptimizeDb.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px; margin-right:3px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> Optimize &amp; Rebuild MySQL Indexes';
      }
    });
  }

  // ── Toast Notification Helper ──────────────────────────────
  function showToast(title, msg, type = 'info') {
    if (window.ReuniteToast && typeof window.ReuniteToast[type] === 'function') {
      window.ReuniteToast[type](title, msg, 4000);
      return;
    }

    const toast = document.createElement('div');
    toast.style.cssText = `
      position: fixed; bottom: 2rem; right: 2rem; z-index: 9999;
      background: var(--admin-surface-2); border: 1px solid var(--admin-border);
      border-left: 4px solid ${type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : '#C4622D'};
      color: #fff; padding: 1rem 1.25rem; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);
      font-family: var(--admin-font); font-size: 0.875rem; max-width: 360px; animation: adminFadeIn 0.2s ease;
    `;
    toast.innerHTML = `<div style="font-weight:700; margin-bottom:0.25rem;">${escapeHtml(title)}</div><div style="color:var(--admin-text-muted); font-size:0.8125rem;">${escapeHtml(msg)}</div>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }
});
