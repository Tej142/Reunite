/**
 * Reunite Platform Admin Command Center Controller
 * Asynchronous AJAX interaction & real-time telemetry management
 */

const API_ENDPOINT = '../../Backend/admin_api.php';
let activeTab = 'overview';
let cachedUsers = [];
let cachedCampuses = [];
let cachedAdmins = [];
let selectedCampusId = '';

document.addEventListener('DOMContentLoaded', () => {
  initThemeEngine();
  initNavigation();
  initEventListeners();
  if (window.REUNITE_ADMIN?.isMasterAdmin) {
    loadCampusesForDropdown();
  }
  loadStats();
  if (window.REUNITE_ADMIN?.isMasterAdmin) {
    loadSystemDiagnostics();
  }
  initRealtimeEngine();
});

/* ── Theme Engine (Default: Light / White Theme) ───────────────────── */
function initThemeEngine() {
  const savedTheme = localStorage.getItem('reunite_admin_theme') || 'light';
  applyTheme(savedTheme);

  const topToggle = document.getElementById('btnThemeToggle');
  const sideToggle = document.getElementById('btnSidebarThemeToggle');

  function handleToggle() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    applyTheme(next);
    showToast(`Switched to ${next === 'dark' ? 'Dark' : 'Light'} theme`, 'info');
  }

  if (topToggle) topToggle.addEventListener('click', handleToggle);
  if (sideToggle) sideToggle.addEventListener('click', handleToggle);
}

function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  localStorage.setItem('reunite_admin_theme', theme);

  const isDark = theme === 'dark';
  const iconClass = isDark ? 'fa-sun' : 'fa-moon';
  const labelText = isDark ? 'Switch to Light Theme' : 'Switch to Dark Theme';

  const topIcon = document.getElementById('themeToggleIcon');
  if (topIcon) {
    topIcon.className = `fa-solid ${iconClass}`;
  }
  const topBtn = document.getElementById('btnThemeToggle');
  if (topBtn) {
    topBtn.title = labelText;
    topBtn.setAttribute('aria-label', labelText);
  }

  const sideIcon = document.getElementById('sidebarThemeIcon');
  if (sideIcon) {
    sideIcon.className = `fa-solid ${iconClass}`;
  }
  const sideBtn = document.getElementById('btnSidebarThemeToggle');
  if (sideBtn) {
    sideBtn.title = labelText;
    sideBtn.setAttribute('aria-label', labelText);
  }
}

function getCampusParam() {
  return selectedCampusId ? `&campus_id=${encodeURIComponent(selectedCampusId)}` : '';
}

function handleCampusFilterChange(campusId) {
  selectedCampusId = campusId;
  const nodeEl = document.getElementById('overviewCampusNode');
  if (nodeEl) {
    const sel = document.getElementById('globalCampusSelector');
    nodeEl.textContent = sel && sel.selectedIndex > 0 ? sel.options[sel.selectedIndex].text : 'All Campuses (Global)';
  }
  loadStats();
  if (typeof updateLiveTelemetry === 'function') {
    updateLiveTelemetry(true);
  }
  switchTab(activeTab);
  showToast(`View updated: ${selectedCampusId ? 'Campus #' + selectedCampusId : 'All Campuses'}`, 'info');
}

/* ── Navigation & Tabs ──────────────────────────────────────────────── */
function initNavigation() {
  const tabButtons = document.querySelectorAll('.nav-tab-btn[data-tab]');
  tabButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const tabId = btn.getAttribute('data-tab');
      switchTab(tabId);
    });
  });

  const mobileToggle = document.getElementById('mobileSidebarToggle');
  const sidebar = document.getElementById('adminSidebar');
  if (mobileToggle && sidebar) {
    mobileToggle.addEventListener('click', () => {
      sidebar.classList.toggle('mobile-open');
    });
  }
}

function switchTab(tabId) {
  activeTab = tabId;

  // Update sidebar active buttons
  document.querySelectorAll('.nav-tab-btn[data-tab]').forEach(btn => {
    if (btn.getAttribute('data-tab') === tabId) {
      btn.classList.add('active');
    } else {
      btn.classList.remove('active');
    }
  });

  // Update visible pane
  document.querySelectorAll('.admin-tab-pane').forEach(pane => {
    pane.style.display = 'none';
  });

  const activePane = document.getElementById(`pane-${tabId}`);
  if (activePane) {
    activePane.style.display = 'block';
  }

  // Update Topbar Titles
  const titles = {
    overview: { title: 'Dashboard', sub: 'Overview of campus reports, matches, and operations.' },
    users: { title: 'Student Accounts', sub: 'Directory of registered students with institutional scoping.' },
    lost: { title: 'Lost Reports', sub: 'Items reported missing across campus.' },
    found: { title: 'Found Items', sub: 'Items in custody awaiting claim verification.' },
    matches: { title: 'AI Matches', sub: 'Semantic and image similarity pairings.' },
    claims: { title: 'Ownership Claims', sub: 'Submitted proofs and handoff authorizations.' },
    campuses: { title: 'Campuses Directory', sub: 'Manage institutions, short codes, and campus node statuses.' },
    campus_admins: { title: 'Campus Administrators', sub: 'Institutional administrators with scoped access.' },
    logs: { title: 'Audit Logs', sub: 'System access and activity trail.' },
    system: { title: 'System Health', sub: 'Service latency and infrastructure status.' }
  };

  if (titles[tabId]) {
    document.getElementById('viewTitle').textContent = titles[tabId].title;
    document.getElementById('viewSubtitle').textContent = titles[tabId].sub;
  }

  // Load relevant tab data
  switch (tabId) {
    case 'overview': loadStats(); break;
    case 'users': loadUsers(); break;
    case 'lost': loadLostReports(); break;
    case 'found': loadFoundReports(); break;
    case 'matches': loadMatches(); break;
    case 'claims': loadClaims(); break;
    case 'campuses': loadCampuses(); break;
    case 'campus_admins': loadCampusAdmins(); break;
    case 'logs': loadLogs(); break;
    case 'system': loadSystemDiagnostics(); break;
  }

  // Close mobile sidebar if open
  const sidebar = document.getElementById('adminSidebar');
  if (sidebar && window.innerWidth < 900) {
    sidebar.classList.remove('mobile-open');
  }
}

/* ── Event Listeners ───────────────────────────────────────────────── */
function initEventListeners() {
  const btnRefresh = document.getElementById('btnGlobalRefresh');
  if (btnRefresh) {
    btnRefresh.addEventListener('click', () => {
      loadStats();
      if (typeof updateLiveTelemetry === 'function') {
        updateLiveTelemetry(true);
      }
      switchTab(activeTab);
      showToast('Platform data refreshed.', 'success');
    });
  }

  const pill = document.getElementById('liveRealtimePill');
  if (pill) {
    pill.addEventListener('click', toggleRealtimeSync);
  }

  const soundBtn = document.getElementById('btnSoundAlertToggle');
  if (soundBtn) {
    soundBtn.addEventListener('click', toggleSoundAlerts);
  }

  // Users Filters
  const searchUsers = document.getElementById('searchUsersInput');
  const filterRole = document.getElementById('filterUserRole');
  const filterStatus = document.getElementById('filterUserStatus');

  if (searchUsers) searchUsers.addEventListener('input', debounce(loadUsers, 300));
  if (filterRole) filterRole.addEventListener('change', loadUsers);
  if (filterStatus) filterStatus.addEventListener('change', loadUsers);

  // Lost Filters
  const searchLost = document.getElementById('searchLostInput');
  const filterLostCat = document.getElementById('filterLostCategory');
  const filterLostStat = document.getElementById('filterLostStatus');

  if (searchLost) searchLost.addEventListener('input', debounce(loadLostReports, 300));
  if (filterLostCat) filterLostCat.addEventListener('change', loadLostReports);
  if (filterLostStat) filterLostStat.addEventListener('change', loadLostReports);

  // Found Filters
  const searchFound = document.getElementById('searchFoundInput');
  const filterFoundCat = document.getElementById('filterFoundCategory');
  const filterFoundStat = document.getElementById('filterFoundStatus');

  if (searchFound) searchFound.addEventListener('input', debounce(loadFoundReports, 300));
  if (filterFoundCat) filterFoundCat.addEventListener('change', loadFoundReports);
  if (filterFoundStat) filterFoundStat.addEventListener('change', loadFoundReports);

  // Logs Filters
  const searchLogs = document.getElementById('searchLogsInput');
  const filterLogsCat = document.getElementById('filterLogsCategory');

  if (searchLogs) searchLogs.addEventListener('input', debounce(loadLogs, 300));
  if (filterLogsCat) filterLogsCat.addEventListener('change', loadLogs);

  // System Diagnostics Run Button
  const btnDiag = document.getElementById('btnRunDiagnostics');
  if (btnDiag) {
    btnDiag.addEventListener('click', () => {
      btnDiag.disabled = true;
      btnDiag.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Testing System...';
      loadSystemDiagnostics(() => {
        btnDiag.disabled = false;
        btnDiag.innerHTML = '<i class="fa-solid fa-vial-circle-check"></i> Run Diagnostic Ping';
      });
    });
  }
}

/* ── Real-Time Telemetry & Live Sync Engine ───────────────────────── */
let realtimeTimer = null;
let realtimeCountdown = 8;
let isRealtimePaused = false;
let soundAlertsEnabled = localStorage.getItem('reunite_admin_sound') !== 'false';
let lastKnownStats = {};
let lastKnownMaxIds = { lost: 0, found: 0, match: 0, claim: 0, log: 0 };
let audioCtx = null;

function initRealtimeEngine() {
  updateSoundButtonUI();
  updateLiveTelemetry();

  // Run 1-second countdown clock
  if (realtimeTimer) clearInterval(realtimeTimer);
  realtimeTimer = setInterval(() => {
    if (isRealtimePaused || document.hidden) return;

    realtimeCountdown--;
    const timerEl = document.getElementById('liveSyncTimer');
    if (timerEl) timerEl.textContent = `${realtimeCountdown}s`;

    if (realtimeCountdown <= 0) {
      realtimeCountdown = 8;
      updateLiveTelemetry();
    }
  }, 1000);

  // Background tab visibility handling
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden && !isRealtimePaused) {
      realtimeCountdown = 8;
      updateLiveTelemetry();
    }
  });
}

function toggleRealtimeSync() {
  isRealtimePaused = !isRealtimePaused;
  const pill = document.getElementById('liveRealtimePill');
  const textEl = document.getElementById('liveSyncText');
  const timerEl = document.getElementById('liveSyncTimer');

  if (isRealtimePaused) {
    if (pill) {
      pill.classList.remove('active');
      pill.classList.add('paused');
      pill.title = 'Real-Time Sync is PAUSED. Click to Resume.';
    }
    if (textEl) textEl.textContent = 'PAUSED';
    if (timerEl) timerEl.textContent = 'OFF';
    showToast('Live auto-sync paused.', 'warning');
  } else {
    if (pill) {
      pill.classList.add('active');
      pill.classList.remove('paused');
      pill.title = 'Real-Time Sync is ACTIVE. Click to Pause.';
    }
    if (textEl) textEl.textContent = 'LIVE SYNC';
    realtimeCountdown = 8;
    if (timerEl) timerEl.textContent = '8s';
    updateLiveTelemetry();
    showToast('Live auto-sync active.', 'success');
  }
}

function updateSoundButtonUI() {
  const btn = document.getElementById('btnSoundAlertToggle');
  const icon = document.getElementById('soundAlertIcon');
  if (!btn || !icon) return;

  if (soundAlertsEnabled) {
    btn.classList.remove('muted');
    btn.title = 'Audio Alerts: ON (Click to Mute)';
    icon.className = 'fa-solid fa-bell';
  } else {
    btn.classList.add('muted');
    btn.title = 'Audio Alerts: MUTED (Click to Enable)';
    icon.className = 'fa-solid fa-bell-slash';
  }
}

function toggleSoundAlerts() {
  soundAlertsEnabled = !soundAlertsEnabled;
  localStorage.setItem('reunite_admin_sound', soundAlertsEnabled);
  updateSoundButtonUI();
  if (soundAlertsEnabled) {
    playAlertTone();
    showToast('Real-time audio alerts enabled.', 'success');
  } else {
    showToast('Real-time audio alerts muted.', 'info');
  }
}

function playAlertTone() {
  if (!soundAlertsEnabled) return;
  try {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) return;
    if (!audioCtx) audioCtx = new AudioCtx();
    if (audioCtx.state === 'suspended') {
      audioCtx.resume();
    }

    const now = audioCtx.currentTime;
    // Tone 1: 587.33 Hz (D5)
    const osc1 = audioCtx.createOscillator();
    const gain1 = audioCtx.createGain();
    osc1.type = 'sine';
    osc1.frequency.setValueAtTime(587.33, now);
    gain1.gain.setValueAtTime(0.12, now);
    gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.12);
    osc1.connect(gain1);
    gain1.connect(audioCtx.destination);
    osc1.start(now);
    osc1.stop(now + 0.12);

    // Tone 2: 880 Hz (A5)
    const osc2 = audioCtx.createOscillator();
    const gain2 = audioCtx.createGain();
    osc2.type = 'sine';
    osc2.frequency.setValueAtTime(880, now + 0.08);
    gain2.gain.setValueAtTime(0.15, now + 0.08);
    gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.25);
    osc2.connect(gain2);
    gain2.connect(audioCtx.destination);
    osc2.start(now + 0.08);
    osc2.stop(now + 0.25);
  } catch (e) {
    // AudioContext blocked by autoplay policy until user gesture
  }
}

async function updateLiveTelemetry(manual = false) {
  try {
    const res = await fetch(`${API_ENDPOINT}?action=realtime_telemetry${getCampusParam()}`);
    const json = await res.json();
    if (!json.success) return;

    const s = json.stats || {};
    const max = json.max_ids || {};

    // 1. Update KPI Values with visual highlight pulse on change
    updateStatWithPulse('statTotalUsers', s.total_users);
    updateStatWithPulse('statActiveUsers', s.active_users);
    updateStatWithPulse('statTotalLost', s.total_lost);
    updateStatWithPulse('statActiveLost', s.active_lost);
    updateStatWithPulse('statTotalFound', s.total_found);
    updateStatWithPulse('statActiveFound', s.active_found);
    updateStatWithPulse('statTotalMatches', s.total_matches);
    updateStatWithPulse('statVerifiedMatches', s.verified_matches);
    updateStatWithPulse('statPendingClaims', s.pending_claims);
    updateStatWithPulse('statResolvedCases', s.resolved_cases);

    // 2. Update Sidebar Badges
    const bLost = document.getElementById('badgeLostCount');
    const bFound = document.getElementById('badgeFoundCount');
    const bMatch = document.getElementById('badgeMatchCount');
    const bClaim = document.getElementById('badgeClaimCount');
    if (bLost) bLost.textContent = s.active_lost ?? 0;
    if (bFound) bFound.textContent = s.active_found ?? 0;
    if (bMatch) bMatch.textContent = s.total_matches ?? 0;
    if (bClaim) bClaim.textContent = s.pending_claims ?? 0;

    // 3. Detect Real-Time Delta Events (skip initial boot)
    if (lastKnownMaxIds.lost > 0 && max.lost > lastKnownMaxIds.lost) {
      const diff = max.lost - lastKnownMaxIds.lost;
      showToast(`⚡ Real-time alert: ${diff} new Lost Report${diff > 1 ? 's' : ''} submitted!`, 'info');
      playAlertTone();
      if (activeTab === 'lost') loadLostReports();
    }

    if (lastKnownMaxIds.found > 0 && max.found > lastKnownMaxIds.found) {
      const diff = max.found - lastKnownMaxIds.found;
      showToast(`⚡ Real-time alert: ${diff} new Found Item${diff > 1 ? 's' : ''} reported into custody!`, 'success');
      playAlertTone();
      if (activeTab === 'found') loadFoundReports();
    }

    if (lastKnownMaxIds.match > 0 && max.match > lastKnownMaxIds.match) {
      const diff = max.match - lastKnownMaxIds.match;
      showToast(`⚡ Real-time alert: Neural engine generated ${diff} new AI Match${diff > 1 ? 'es' : ''}!`, 'success');
      playAlertTone();
      if (activeTab === 'matches') loadMatches();
    }

    if (lastKnownMaxIds.claim > 0 && max.claim > lastKnownMaxIds.claim) {
      const diff = max.claim - lastKnownMaxIds.claim;
      showToast(`⚡ Real-time alert: ${diff} new Ownership Claim${diff > 1 ? 's' : ''} submitted for verification!`, 'info');
      playAlertTone();
      if (activeTab === 'claims') loadClaims();
    }

    // 4. Update Ticker Feed
    const tickerText = document.getElementById('liveTickerText');
    const tickerMeta = document.getElementById('liveMetaTime');
    const dbMeta = document.getElementById('liveMetaDb');

    if (json.latest_events && json.latest_events.length > 0 && tickerText) {
      const topEvt = json.latest_events[0];
      const typeIcons = {
        found: '<i class="fa-solid fa-hand-holding-heart" style="color:var(--success);"></i>',
        lost: '<i class="fa-solid fa-compass" style="color:var(--accent);"></i>',
        claim: '<i class="fa-solid fa-shield-halved" style="color:var(--warning);"></i>'
      };
      const icon = typeIcons[topEvt.event_type] || '<i class="fa-solid fa-circle-dot"></i>';
      tickerText.innerHTML = `${icon} <strong>${escapeHtml(topEvt.title || 'Platform Event')}</strong> <span style="color:var(--muted); font-size:0.75rem; margin-left:6px;">(${topEvt.created_at ? topEvt.created_at.substring(11, 16) : 'Just now'})</span>`;
    } else if (tickerText) {
      tickerText.innerHTML = '<span style="color:var(--muted);">All clear · Real-time telemetry pipeline connected and monitoring.</span>';
    }

    if (tickerMeta) {
      tickerMeta.textContent = json.server_time ? `Updated at ${json.server_time}` : 'Updated just now';
    }
    if (dbMeta) {
      dbMeta.innerHTML = `<i class="fa-solid fa-database" style="color:var(--success);"></i> DB: Online`;
    }

    // 5. Store current state
    lastKnownStats = s;
    lastKnownMaxIds = max;

  } catch (err) {
    console.warn('Real-time telemetry poll failed:', err);
    const dbMeta = document.getElementById('liveMetaDb');
    if (dbMeta) {
      dbMeta.innerHTML = `<i class="fa-solid fa-circle-exclamation" style="color:var(--warning);"></i> Reconnecting...`;
    }
  }
}

function updateStatWithPulse(elementId, newVal) {
  const el = document.getElementById(elementId);
  if (!el || newVal === undefined || newVal === null) return;

  const currentVal = parseInt(el.textContent.replace(/[^0-9]/g, ''), 10);
  if (!isNaN(currentVal) && currentVal !== parseInt(newVal, 10)) {
    const card = el.closest('.stat-card');
    if (card) {
      card.classList.remove('stat-updated');
      void card.offsetWidth; // Trigger reflow for animation restart
      card.classList.add('stat-updated');
    }
  }
  el.textContent = newVal;
}

/* ── 1. Load Overview Stats ─────────────────────────────────────────── */
async function loadStats() {
  try {
    const res = await fetch(`${API_ENDPOINT}?action=stats${getCampusParam()}`);
    const json = await res.json();
    if (json.success) {
      const d = json.data || json;
      document.getElementById('statTotalUsers').textContent = d.total_users ?? 0;
      document.getElementById('statActiveUsers').textContent = d.active_users ?? 0;
      document.getElementById('statTotalLost').textContent = d.total_lost ?? 0;
      document.getElementById('statActiveLost').textContent = d.active_lost ?? 0;
      document.getElementById('statTotalFound').textContent = d.total_found ?? 0;
      document.getElementById('statActiveFound').textContent = d.active_found ?? 0;
      document.getElementById('statTotalMatches').textContent = d.total_matches ?? 0;
      document.getElementById('statVerifiedMatches').textContent = d.verified_matches ?? 0;
      document.getElementById('statPendingClaims').textContent = d.pending_claims ?? 0;
      document.getElementById('statResolvedCases').textContent = d.resolved_cases ?? 0;

      // Update sidebar badges
      document.getElementById('badgeLostCount').textContent = d.active_lost ?? 0;
      document.getElementById('badgeFoundCount').textContent = d.active_found ?? 0;
      document.getElementById('badgeMatchCount').textContent = d.total_matches ?? 0;
      document.getElementById('badgeClaimCount').textContent = d.pending_claims ?? 0;
    }
  } catch (err) {
    console.error("Failed to load statistics:", err);
  }
}

/* ── 2. Users Management ────────────────────────────────────────────── */
async function loadUsers() {
  const tbody = document.getElementById('usersTableBody');
  const search = encodeURIComponent(document.getElementById('searchUsersInput')?.value || '');
  const role = encodeURIComponent(document.getElementById('filterUserRole')?.value || '');
  const status = encodeURIComponent(document.getElementById('filterUserStatus')?.value || '');

  tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-spinner fa-spin"></i> Querying accounts...</td></tr>`;

  try {
    const res = await fetch(`${API_ENDPOINT}?action=users&search=${search}&role=${role}&status=${status}${getCampusParam()}`);
    const json = await res.json();
    const users = json.users || json.data?.users;
    if (json.success && Array.isArray(users)) {
      cachedUsers = users;
      if (cachedUsers.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:30px; color:var(--muted);">No users match the search criteria.</td></tr>`;
        return;
      }

      tbody.innerHTML = cachedUsers.map(u => `
        <tr>
          <td><strong style="color:var(--muted);">#${u.user_id}</strong></td>
          <td>
            <div style="font-weight:600; color:var(--ink);">${escapeHtml(u.full_name || 'User')}</div>
            <div style="font-size:0.75rem; color:var(--muted);">Joined: ${u.created_at ? u.created_at.substring(0, 10) : 'N/A'}</div>
          </td>
          <td><code>${escapeHtml(u.pin || 'N/A')}</code></td>
          <td>
            <div>${escapeHtml(u.email || 'No email')}</div>
            <div style="font-size:0.75rem; color:var(--muted);">${escapeHtml(u.phone || '')}</div>
          </td>
          <td>
            <div>${escapeHtml(u.college || 'Default College')}</div>
            <div style="font-size:0.75rem; color:var(--accent);">${escapeHtml(u.branch || 'General')}</div>
          </td>
          <td>
            <span style="font-weight:700; color:${u.trust_score > 70 ? 'var(--success)' : (u.trust_score > 40 ? 'var(--warning)' : 'var(--danger)')}">
              ${u.trust_score || 100}%
            </span>
          </td>
          <td>
            <span class="status-pill ${u.role === 'admin' ? 'claimed' : 'active'}" style="text-transform:uppercase;">
              ${escapeHtml(u.role || 'user')}
            </span>
          </td>
          <td>
            <span class="status-pill ${u.status === 'active' ? 'active' : 'blocked'}">
              ${escapeHtml(u.status || 'active')}
            </span>
          </td>
          <td>
            <div class="action-btn-group">
              <button class="btn-action-icon" title="Edit User Details" onclick="openEditUserModal(${u.user_id})">
                <i class="fa-solid fa-pen-to-square"></i>
              </button>
              ${u.status === 'active' ? `
                <button class="btn-action-icon danger" title="Suspend / Block User" onclick="toggleUserStatus(${u.user_id}, 'blocked')">
                  <i class="fa-solid fa-ban"></i>
                </button>
              ` : `
                <button class="btn-action-icon success" title="Unblock / Reactivate User" onclick="toggleUserStatus(${u.user_id}, 'active')">
                  <i class="fa-solid fa-circle-check"></i>
                </button>
              `}
              <button class="btn-action-icon danger" title="Delete Account" onclick="deleteUser(${u.user_id})">
                <i class="fa-solid fa-trash-can"></i>
              </button>
            </div>
          </td>
        </tr>
      `).join('');
    } else {
      tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:30px; color:var(--danger);">${json.message || 'Failed to fetch users.'}</td></tr>`;
    }
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:30px; color:var(--danger);">Error loading users.</td></tr>`;
  }
}

function openEditUserModal(userId) {
  const user = cachedUsers.find(u => u.user_id == userId);
  if (!user) return;

  document.getElementById('editUserId').value = user.user_id;
  document.getElementById('modalUserSubtitle').textContent = `Editing account for ${user.full_name} (PIN: ${user.pin || 'N/A'})`;
  document.getElementById('editUserRole').value = user.role || 'user';
  document.getElementById('editUserStatus').value = user.status || 'active';
  document.getElementById('editUserTrust').value = user.trust_score || 100;
  document.getElementById('editUserNewPass').value = '';

  document.getElementById('editUserModal').classList.add('open');
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) modal.classList.remove('open');
}

async function handleSaveUser(e) {
  e.preventDefault();
  const btn = document.getElementById('btnSaveUser');
  btn.disabled = true;
  btn.textContent = 'Saving...';

  const payload = {
    user_id: document.getElementById('editUserId').value,
    role: document.getElementById('editUserRole').value,
    status: document.getElementById('editUserStatus').value,
    trust_score: document.getElementById('editUserTrust').value,
    new_password: document.getElementById('editUserNewPass').value
  };

  try {
    const res = await fetch(`${API_ENDPOINT}?action=update_user`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (json.success) {
      showToast('User account successfully updated.', 'success');
      closeModal('editUserModal');
      loadUsers();
      loadStats();
    } else {
      showToast(json.message || 'Failed to update user.', 'error');
    }
  } catch (err) {
    showToast('Network error saving user.', 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Save Changes';
  }
}

async function toggleUserStatus(userId, newStatus) {
  if (!confirm(`Are you sure you want to set User #${userId} to ${newStatus}?`)) return;
  try {
    const res = await fetch(`${API_ENDPOINT}?action=update_user`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ user_id: userId, status: newStatus })
    });
    const json = await res.json();
    if (json.success) {
      showToast(`User marked as ${newStatus}.`, 'success');
      loadUsers();
      loadStats();
    } else {
      showToast(json.message || 'Action failed.', 'error');
    }
  } catch (err) {
    showToast('Failed to change user status.', 'error');
  }
}

async function deleteUser(userId) {
  if (!confirm(`Warning: Deleting user #${userId} is irreversible. Proceed?`)) return;
  try {
    const res = await fetch(`${API_ENDPOINT}?action=delete_user`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ user_id: userId })
    });
    const json = await res.json();
    if (json.success) {
      showToast('User deleted permanently.', 'success');
      loadUsers();
      loadStats();
    } else {
      showToast(json.message || 'Delete failed.', 'error');
    }
  } catch (err) {
    showToast('Failed to delete user.', 'error');
  }
}

/* ── 3. Lost Property Reports ──────────────────────────────────────── */
async function loadLostReports() {
  const tbody = document.getElementById('lostTableBody');
  const search = encodeURIComponent(document.getElementById('searchLostInput')?.value || '');
  const cat = encodeURIComponent(document.getElementById('filterLostCategory')?.value || '');
  const stat = encodeURIComponent(document.getElementById('filterLostStatus')?.value || '');

  tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading lost records...</td></tr>`;

  try {
    const res = await fetch(`${API_ENDPOINT}?action=lost_reports&search=${search}&category=${cat}&status=${stat}${getCampusParam()}`);
    const json = await res.json();
    const reports = json.reports || json.data?.reports;
    if (json.success && Array.isArray(reports)) {
      if (reports.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--muted);">No lost reports found.</td></tr>`;
        return;
      }

      tbody.innerHTML = reports.map(r => `
        <tr>
          <td><strong style="color:var(--muted);">#${r.id}</strong></td>
          <td>
            <div style="font-weight:600; color:var(--ink);">${escapeHtml(r.title)}</div>
            <span class="status-pill closed" style="font-size:0.7rem; margin-top:4px;">${escapeHtml(r.category)}</span>
          </td>
          <td><i class="fa-solid fa-location-dot" style="color:var(--accent); font-size:0.8rem; margin-right:4px;"></i> ${escapeHtml(r.location)}</td>
          <td>${r.date_lost || r.created_at ? (r.date_lost || r.created_at).substring(0, 10) : 'N/A'}</td>
          <td>
            <div>${escapeHtml(r.reporter_name || 'Anonymous')}</div>
            <div style="font-size:0.75rem; color:var(--muted);">${escapeHtml(r.reporter_email || '')}</div>
          </td>
          <td>
            <select class="admin-select-sm" style="padding:4px 8px; font-size:0.78rem;" onchange="updateReportStatus('lost', ${r.id}, this.value)">
              <option value="active" ${r.status === 'active' ? 'selected' : ''}>Active</option>
              <option value="matched" ${r.status === 'matched' ? 'selected' : ''}>Matched</option>
              <option value="claimed" ${r.status === 'claimed' ? 'selected' : ''}>Claimed</option>
              <option value="closed" ${r.status === 'closed' ? 'selected' : ''}>Closed</option>
            </select>
          </td>
          <td>
            <button class="btn-action-icon danger" title="Delete Listing" onclick="deleteReport('lost', ${r.id})">
              <i class="fa-solid fa-trash-can"></i>
            </button>
          </td>
        </tr>
      `).join('');
    } else {
      tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--muted);">No lost reports found.</td></tr>`;
    }
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--danger);">Error loading lost listings.</td></tr>`;
  }
}

/* ── 4. Found Property Deposits ─────────────────────────────────────── */
async function loadFoundReports() {
  const tbody = document.getElementById('foundTableBody');
  const search = encodeURIComponent(document.getElementById('searchFoundInput')?.value || '');
  const cat = encodeURIComponent(document.getElementById('filterFoundCategory')?.value || '');
  const stat = encodeURIComponent(document.getElementById('filterFoundStatus')?.value || '');

  tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading found records...</td></tr>`;

  try {
    const res = await fetch(`${API_ENDPOINT}?action=found_reports&search=${search}&category=${cat}&status=${stat}${getCampusParam()}`);
    const json = await res.json();
    const reports = json.reports || json.data?.reports;
    if (json.success && Array.isArray(reports)) {
      if (reports.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:30px; color:var(--muted);">No found reports found.</td></tr>`;
        return;
      }

      tbody.innerHTML = reports.map(r => `
        <tr>
          <td><strong style="color:var(--muted);">#${r.id}</strong></td>
          <td>
            ${r.image_path ? `
              <img src="../../${escapeHtml(r.image_path)}" alt="Found Item" style="width:44px; height:44px; object-fit:cover; border-radius:8px; border:1px solid var(--border-color);">
            ` : `
              <div style="width:44px; height:44px; background:var(--bg-surface); border-radius:8px; display:flex; align-items:center; justify-content:center; color:var(--muted);"><i class="fa-solid fa-image"></i></div>
            `}
          </td>
          <td>
            <div style="font-weight:600; color:var(--ink);">${escapeHtml(r.title)}</div>
            <span class="status-pill closed" style="font-size:0.7rem; margin-top:4px;">${escapeHtml(r.category)}</span>
          </td>
          <td>${escapeHtml(r.location)}</td>
          <td><span style="color:var(--accent); font-weight:500;">${escapeHtml(r.storage_location || 'Campus Front Desk')}</span></td>
          <td>
            <div>${escapeHtml(r.finder_name || 'Staff / Student')}</div>
            <div style="font-size:0.75rem; color:var(--muted);">${escapeHtml(r.finder_email || '')}</div>
          </td>
          <td>
            <select class="admin-select-sm" style="padding:4px 8px; font-size:0.78rem;" onchange="updateReportStatus('found', ${r.id}, this.value)">
              <option value="active" ${r.status === 'active' ? 'selected' : ''}>Active</option>
              <option value="matched" ${r.status === 'matched' ? 'selected' : ''}>Matched</option>
              <option value="claimed" ${r.status === 'claimed' ? 'selected' : ''}>Claimed</option>
              <option value="closed" ${r.status === 'closed' ? 'selected' : ''}>Closed</option>
            </select>
          </td>
          <td>
            <button class="btn-action-icon danger" title="Delete Deposit" onclick="deleteReport('found', ${r.id})">
              <i class="fa-solid fa-trash-can"></i>
            </button>
          </td>
        </tr>
      `).join('');
    } else {
      tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:30px; color:var(--muted);">No found reports found.</td></tr>`;
    }
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:30px; color:var(--danger);">Error loading found deposits.</td></tr>`;
  }
}

async function updateReportStatus(type, id, newStatus) {
  try {
    const res = await fetch(`${API_ENDPOINT}?action=update_report_status`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type, id, status: newStatus })
    });
    const json = await res.json();
    if (json.success) {
      showToast(`${type.toUpperCase()} #${id} status changed to ${newStatus}.`, 'success');
      loadStats();
    } else {
      showToast(json.message || 'Failed to update status.', 'error');
    }
  } catch (err) {
    showToast('Error modifying report.', 'error');
  }
}

async function deleteReport(type, id) {
  if (!confirm(`Are you sure you want to delete ${type} report #${id}?`)) return;
  try {
    const res = await fetch(`${API_ENDPOINT}?action=delete_report`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type, id })
    });
    const json = await res.json();
    if (json.success) {
      showToast('Report deleted.', 'success');
      if (type === 'found') loadFoundReports(); else loadLostReports();
      loadStats();
    } else {
      showToast(json.message || 'Delete failed.', 'error');
    }
  } catch (err) {
    showToast('Error deleting report.', 'error');
  }
}

/* ── 5. AI Matches Engine ───────────────────────────────────────────── */
async function loadMatches() {
  const tbody = document.getElementById('matchesTableBody');
  tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading neural pairings...</td></tr>`;

  try {
    const res = await fetch(`${API_ENDPOINT}?action=matches${getCampusParam()}`);
    const json = await res.json();
    const matches = json.matches || json.data?.matches;
    if (json.success && Array.isArray(matches)) {
      if (matches.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--muted);">No AI matches generated yet.</td></tr>`;
        return;
      }

      tbody.innerHTML = matches.map(m => {
        const score = Math.round((parseFloat(m.confidence_score || 0)) * 100);
        return `
          <tr>
            <td><strong style="color:var(--muted);">#${m.id}</strong></td>
            <td>
              <div style="font-weight:600; color:var(--ink);">${escapeHtml(m.lost_title || 'Lost Item #' + m.lost_report_id)}</div>
              <div style="font-size:0.75rem; color:var(--muted);">${escapeHtml(m.lost_cat || '')} • ${escapeHtml(m.lost_loc || '')}</div>
            </td>
            <td>
              <div style="font-weight:600; color:var(--ink);">${escapeHtml(m.found_title || 'Found Item #' + m.found_report_id)}</div>
              <div style="font-size:0.75rem; color:var(--muted);">${escapeHtml(m.found_cat || '')}</div>
            </td>
            <td>
              <div style="display:flex; align-items:center; gap:8px;">
                <div style="flex:1; height:6px; background:var(--bg-surface); border-radius:999px; overflow:hidden; width:80px;">
                  <div style="height:100%; width:${score}%; background:${score > 75 ? 'var(--success)' : (score > 50 ? 'var(--warning)' : 'var(--danger)')}; border-radius:999px;"></div>
                </div>
                <strong style="font-size:0.85rem; color:${score > 75 ? 'var(--success)' : 'var(--warning)'};">${score}%</strong>
              </div>
            </td>
            <td>
              <span class="status-pill ${m.status === 'verified' ? 'active' : (m.status === 'rejected' ? 'blocked' : 'pending')}">
                ${escapeHtml(m.status || 'pending')}
              </span>
            </td>
            <td>
              <span style="font-size:0.8rem; color:var(--muted);">${escapeHtml(m.notes || 'Similarity matched via ResNet + Transformer text embeddings')}</span>
            </td>
            <td>
              <div class="action-btn-group">
                <button class="btn-action-icon success" title="Approve & Verify AI Match" onclick="updateMatchStatus(${m.id}, 'verified')">
                  <i class="fa-solid fa-check"></i>
                </button>
                <button class="btn-action-icon danger" title="Reject Match" onclick="updateMatchStatus(${m.id}, 'rejected')">
                  <i class="fa-solid fa-xmark"></i>
                </button>
              </div>
            </td>
          </tr>
        `;
      }).join('');
    } else {
      tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--muted);">No AI matches generated yet.</td></tr>`;
    }
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--danger);">Error loading AI matches.</td></tr>`;
  }
}

async function updateMatchStatus(matchId, status) {
  try {
    const res = await fetch(`${API_ENDPOINT}?action=update_match_status`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: matchId, status })
    });
    const json = await res.json();
    if (json.success) {
      showToast(`Match #${matchId} set to ${status}.`, 'success');
      loadMatches();
      loadStats();
    } else {
      showToast(json.message || 'Failed to update match.', 'error');
    }
  } catch (err) {
    showToast('Error updating match status.', 'error');
  }
}

/* ── 6. Claim Verifications ─────────────────────────────────────────── */
async function loadClaims() {
  const tbody = document.getElementById('claimsTableBody');
  tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading ownership claims...</td></tr>`;

  // Also load banned claimants table
  loadBannedClaimants();

  try {
    const res = await fetch(`${API_ENDPOINT}?action=recovery_cases${getCampusParam()}`);
    const json = await res.json();
    const cases = json.cases || json.data?.cases;
    if (json.success && Array.isArray(cases)) {
      if (cases.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--muted);">No recovery claim cases registered.</td></tr>`;
        return;
      }

      tbody.innerHTML = cases.map(c => {
        const isDeskReturn = (c.return_type === 'admin_office' || c.return_method === 'admin_office');
        const isCompleted = (c.status === 'Completed' || c.status === 'resolved' || c.status === 'handed_off');
        return `
        <tr>
          <td><strong style="color:var(--muted);">#${c.id}</strong></td>
          <td>
            <code>Match #${c.match_id || c.found_report_id || 'N/A'}</code>
            <div style="margin-top: 4px;">
              <span class="status-pill ${isDeskReturn ? 'active' : 'pending'}" style="font-size:0.68rem; padding: 2px 7px;">
                ${isDeskReturn ? '🏢 Campus Desk' : '🤝 In-Person'}
              </span>
            </div>
          </td>
          <td>
            <div style="font-weight:600; color:var(--ink);">${escapeHtml(c.claimant_name || 'Claimant')}</div>
            <div style="font-size:0.75rem; color:var(--muted);">${escapeHtml(c.claimant_email || '')} • ${escapeHtml(c.claimant_phone || '')}</div>
          </td>
          <td>
            <div style="font-size:0.85rem; color:var(--ink-secondary); max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escapeHtml(c.proof_description || '')}">
              ${escapeHtml(c.proof_description || 'Proof of ownership submitted')}
            </div>
            ${c.proof_image_path ? `
              <a href="../../${escapeHtml(c.proof_image_path)}" target="_blank" style="font-size:0.75rem; color:var(--accent); text-decoration:none; display:inline-flex; align-items:center; gap:4px; margin-top:2px;">
                <i class="fa-solid fa-paperclip"></i> View Proof Photo
              </a>
            ` : ''}
          </td>
          <td>
            <span class="status-pill ${isCompleted ? 'active' : (c.status === 'rejected' ? 'blocked' : 'pending')}">
              ${escapeHtml(c.status || 'in_progress')}
            </span>
          </td>
          <td>${c.created_at ? c.created_at.substring(0, 10) : 'N/A'}</td>
          <td>
            <div class="action-btn-group">
              ${!isCompleted && c.status !== 'rejected' && c.status !== 'archived' ? `
                <button class="btn-action-icon success" style="width: auto; padding: 4px 10px; font-size: 0.75rem; gap: 4px; display: inline-flex; align-items: center;" title="Verify Owner Code & Complete Handover at Desk" onclick="verifyDeskOtp(${c.match_id || c.id})">
                  <i class="fa-solid fa-key"></i> Verify OTP
                </button>
              ` : ''}
              ${!isCompleted && c.status !== 'rejected' && c.status !== 'archived' ? `
                <button class="btn-action-icon danger" title="Reject / Cancel Claim" onclick="updateClaimStatus(${c.id}, 'rejected')">
                  <i class="fa-solid fa-ban"></i>
                </button>
              ` : (c.status === 'rejected' ? `
                <span style="font-size: 0.78rem; color: var(--danger); font-weight: 600;">✕ Rejected</span>
              ` : (c.status === 'archived' ? `
                <span style="font-size: 0.78rem; color: var(--muted); font-weight: 500;">Archived</span>
              ` : `
                <span style="font-size: 0.78rem; color: var(--success); font-weight: 600;">✓ Reunited</span>
              `))}
            </div>
          </td>
        </tr>
      `;
      }).join('');
    } else {
      tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--muted);">No recovery claim cases registered.</td></tr>`;
    }
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--danger);">Error loading claims.</td></tr>`;
  }
}

async function openWalkInDeskOtpModal() {
  const code = prompt('Enter the 6-digit verification OTP presented by the student at the Campus Desk:');
  if (!code) return;
  const cleanCode = code.trim();
  if (!/^\d{6}$/.test(cleanCode)) {
    alert('Invalid code: Verification OTP must be exactly 6 numeric digits.');
    return;
  }

  const matchInput = prompt('(Optional) Enter the Case # or Match # (or leave blank if unknown):');
  const matchId = matchInput ? matchInput.trim().replace('#', '') : '';

  try {
    const res = await fetch(`../../Frontend/api/verify_otp.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'otp=' + encodeURIComponent(cleanCode) + (matchId ? '&match_id=' + encodeURIComponent(matchId) : '')
    });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast('🎉 Handover verified successfully! Item marked Reunited.', 'success');
      loadClaims();
      loadStats();
    } else {
      showToast(data.message || 'OTP verification failed.', 'error');
    }
  } catch (err) {
    showToast('Network error verifying code.', 'error');
  }
}

async function verifyDeskOtp(matchId) {
  const code = prompt('Enter the 6-digit verification code presented by the owner at the desk:');
  if (!code) return;
  const cleanCode = code.trim();
  if (!/^\d{6}$/.test(cleanCode)) {
    alert('Code must be exactly 6 numeric digits.');
    return;
  }

  try {
    const res = await fetch(`../../Frontend/api/verify_otp.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'match_id=' + encodeURIComponent(matchId) + '&otp=' + encodeURIComponent(cleanCode)
    });
    const data = await res.json();
    if (res.ok && data.success) {
      showToast('🎉 Handover verified successfully! Item marked Reunited.', 'success');
      loadClaims();
      loadStats();
    } else {
      showToast(data.message || 'OTP verification failed.', 'error');
    }
  } catch (err) {
    showToast('Network error verifying code.', 'error');
  }
}

async function loadBannedClaimants() {
  const tbody = document.getElementById('bannedClaimantsTableBody');
  if (!tbody) return;

  try {
    const res = await fetch(`${API_ENDPOINT}?action=get_banned_claimants${getCampusParam()}`);
    const json = await res.json();
    const banned = json.banned_claimants || [];

    if (json.success && Array.isArray(banned)) {
      if (banned.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:18px; color:var(--muted);">No students are currently banned from submitting claims.</td></tr>`;
        return;
      }

      tbody.innerHTML = banned.map(b => {
        const remainingHrs = Math.max(1, Math.ceil((b.seconds_remaining || 0) / 3600));
        return `
          <tr>
            <td><strong>#${b.id}</strong></td>
            <td><div style="font-weight:600; color:var(--ink);">${escapeHtml(b.name || 'Student')}</div></td>
            <td><code>${escapeHtml(b.pin || 'N/A')}</code></td>
            <td><div style="font-size:0.8rem; color:var(--muted);">${escapeHtml(b.email || 'N/A')}</div></td>
            <td>${escapeHtml(b.ban_until || '')}</td>
            <td><span class="status-pill blocked" style="font-size:0.75rem;">~${remainingHrs} hr(s) left</span></td>
            <td>
              <button class="btn-action-icon success" style="width: auto; padding: 4px 10px; font-size: 0.75rem; gap: 4px; display: inline-flex; align-items: center;" title="Lift claim ban immediately" onclick="liftClaimBan(${b.id})">
                <i class="fa-solid fa-unlock"></i> Lift Ban
              </button>
            </td>
          </tr>
        `;
      }).join('');
    }
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:18px; color:var(--danger);">Error loading banned claimants.</td></tr>`;
  }
}

async function liftClaimBan(userId) {
  if (!confirm(`Are you sure you want to lift the claim ban for student #${userId}?`)) return;

  try {
    const res = await fetch(`${API_ENDPOINT}?action=lift_claim_ban`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'user_id=' + encodeURIComponent(userId)
    });
    const json = await res.json();
    if (json.success) {
      showToast(json.message || `Claim ban lifted for user #${userId}.`, 'success');
      loadBannedClaimants();
    } else {
      showToast(json.error || 'Failed to lift claim ban.', 'error');
    }
  } catch (err) {
    showToast('Network error lifting claim ban.', 'error');
  }
}

async function updateClaimStatus(claimId, status) {
  try {
    const res = await fetch(`${API_ENDPOINT}?action=update_recovery_case`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: claimId, status })
    });
    const json = await res.json();
    if (json.success) {
      showToast(`Claim #${claimId} status updated to ${status}.`, 'success');
      loadClaims();
      loadStats();
    } else {
      showToast(json.message || 'Failed to update claim.', 'error');
    }
  } catch (err) {
    showToast('Error modifying claim.', 'error');
  }
}

/* ── 7. Audit & Activity Logs ───────────────────────────────────────── */
async function loadLogs() {
  const tbody = document.getElementById('logsTableBody');
  const search = encodeURIComponent(document.getElementById('searchLogsInput')?.value || '');
  const cat = encodeURIComponent(document.getElementById('filterLogsCategory')?.value || '');

  tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading platform activity logs...</td></tr>`;

  try {
    const res = await fetch(`${API_ENDPOINT}?action=logs&search=${search}&category=${cat}${getCampusParam()}`);
    const json = await res.json();
    const logs = json.logs || json.data?.logs;
    if (json.success && Array.isArray(logs)) {
      if (logs.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:30px; color:var(--muted);">No activity logs match the selected filter.</td></tr>`;
        return;
      }

      const getCategoryBadge = (category) => {
        switch (category) {
          case 'admin_access': return '<span class="status-pill claimed"><i class="fa-solid fa-shield"></i> Admin</span>';
          case 'user_access': return '<span class="status-pill active"><i class="fa-solid fa-user"></i> Student</span>';
          case 'password_reset': return '<span class="status-pill pending"><i class="fa-solid fa-key"></i> Security</span>';
          case 'report_activity': return '<span class="status-pill matched"><i class="fa-solid fa-box"></i> Listing</span>';
          case 'match_verification': return '<span class="status-pill purple"><i class="fa-solid fa-wand-magic-sparkles"></i> AI Match</span>';
          case 'claim_activity': return '<span class="status-pill active"><i class="fa-solid fa-handshake"></i> Claim</span>';
          default: return '<span class="status-pill closed">System</span>';
        }
      };

      tbody.innerHTML = logs.map(l => `
        <tr>
          <td><strong style="color:var(--muted);">#${l.id}</strong></td>
          <td>
            <div style="font-weight:600; color:var(--ink);">${escapeHtml(l.operator_name || 'System Operator')}</div>
            <div style="font-size:0.75rem; color:var(--muted);">${escapeHtml(l.operator_identifier || l.user_type || 'user')}</div>
          </td>
          <td>${getCategoryBadge(l.category)}</td>
          <td>
            <div style="font-weight:500; color:var(--ink);">${escapeHtml(l.action || 'Event')}</div>
            ${l.details ? `<div style="font-size:0.75rem; color:var(--muted); margin-top:2px;">${escapeHtml(l.details)}</div>` : ''}
          </td>
          <td>
            <div><code>${escapeHtml(l.ip_address || '127.0.0.1')}</code></div>
            <div style="font-size:0.72rem; color:var(--muted); max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escapeHtml(l.device_info || '')}">${escapeHtml(l.device_info || 'Web')}</div>
          </td>
          <td>${l.created_at ? l.created_at.substring(0, 19) : 'Just now'}</td>
        </tr>
      `).join('');
    } else {
      tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:30px; color:var(--muted);">No activity logs captured.</td></tr>`;
    }
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:30px; color:var(--danger);">Error loading activity records.</td></tr>`;
  }
}

/* ── 8. System Diagnostics & Health ────────────────────────────────── */
async function loadSystemDiagnostics(callback) {
  const btn = document.getElementById('btnRunDiagnostics');
  if (btn) { btn.disabled = true; }

  // Reset to checking state
  ['Db', 'Ai', 'Smtp'].forEach(key => {
    const badge = document.getElementById(`diag${key}Badge`);
    const pulse = document.getElementById(`diag${key}Pulse`);
    const card = document.getElementById(`diagCard${key}`);
    if (badge) { badge.textContent = '…'; badge.className = 'diag-status-badge'; }
    if (pulse) { pulse.className = 'diag-pulse'; }
    if (card) { card.className = 'diag-service-card'; }
  });

  try {
    const res = await fetch(`${API_ENDPOINT}?action=system_test`);
    const json = await res.json();

    if (json.success) {
      const d = json.data || json;

      // ── Database ──────────────────────────────────────────────
      const dbOk = !!(d.database && d.database.toLowerCase().includes('connect'));
      applyDiagCard({
        cardId: 'diagCardDb',
        iconId: 'diagDbIcon',
        pulseId: 'diagDbPulse',
        badgeId: 'diagDbBadge',
        detailId: 'diagDbDetail',
        ok: dbOk,
        badgeText: dbOk ? 'Online' : 'Offline',
        detail: d.database || 'MySQL'
      });

      // ── AI Microservice ────────────────────────────────────────
      const aiOk = !!(d.ai_backend_status && d.ai_backend_status.toLowerCase().includes('online'));
      const latency = parseInt(d.ai_latency_ms || 0, 10);
      applyDiagCard({
        cardId: 'diagCardAi',
        iconId: 'diagAiIcon',
        pulseId: 'diagAiPulse',
        badgeId: 'diagAiBadge',
        detailId: 'diagAiLatency',
        ok: aiOk,
        badgeText: aiOk ? 'Online' : 'Offline',
        detail: aiOk ? (d.ai_backend_url || 'Render Cloud') : 'Unreachable'
      });
      // Latency bar
      const barWrap = document.getElementById('diagLatencyBarWrap');
      const fill = document.getElementById('diagLatencyFill');
      const label = document.getElementById('diagLatencyLabel');
      if (barWrap && aiOk) {
        barWrap.style.display = 'flex';
        const pct = Math.min(latency / 800 * 100, 100);
        const color = latency < 200 ? '#10B981' : latency < 500 ? '#F59E0B' : '#EF4444';
        if (fill) { fill.style.width = pct + '%'; fill.style.background = color; }
        if (label) { label.textContent = latency + ' ms'; label.style.color = color; }
      } else if (barWrap) {
        barWrap.style.display = 'none';
      }

      // ── SMTP ──────────────────────────────────────────────────
      const smtpOk = !!(d.smtp_configured && d.smtp_configured.toLowerCase().includes('configured'));
      applyDiagCard({
        cardId: 'diagCardSmtp',
        iconId: 'diagSmtpIcon',
        pulseId: 'diagSmtpPulse',
        badgeId: 'diagSmtpBadge',
        detailId: 'diagSmtpStatus',
        ok: smtpOk,
        badgeText: smtpOk ? 'Active' : 'Not set',
        detail: d.smtp_configured || 'Brevo API'
      });

      // ── Last Checked ──────────────────────────────────────────
      const ts = document.getElementById('diagLastChecked');
      if (ts) ts.textContent = new Date().toLocaleTimeString();

      if (callback) showToast(`All services checked · AI ${latency}ms`, 'success');
    }
  } catch (err) {
    console.error('Diagnostic error:', err);
    if (callback) showToast('Diagnostics endpoint unreachable.', 'error');
  } finally {
    if (btn) { btn.disabled = false; }
    if (typeof callback === 'function') callback();
  }
}

function applyDiagCard({ cardId, iconId, pulseId, badgeId, detailId, ok, badgeText, detail }) {
  const card = document.getElementById(cardId);
  const icon = document.getElementById(iconId);
  const pulse = document.getElementById(pulseId);
  const badge = document.getElementById(badgeId);
  const detailEl = document.getElementById(detailId);

  if (card) {
    card.classList.remove('diag-online', 'diag-offline');
    card.classList.add(ok ? 'diag-online' : 'diag-offline');
  }
  if (icon) {
    icon.classList.remove('icon-online', 'icon-offline');
    icon.classList.add(ok ? 'icon-online' : 'icon-offline');
  }
  if (pulse) {
    pulse.className = 'diag-pulse ' + (ok ? 'pulse-online' : 'pulse-offline');
  }
  if (badge) {
    badge.textContent = badgeText;
    badge.className = 'diag-status-badge ' + (ok ? 'badge-online' : 'badge-offline');
  }
  if (detailEl && detail) {
    detailEl.textContent = detail;
  }
}

/* ── 8. Campuses Management (Master Admin Only) ─────────────────────── */
async function loadCampusesForDropdown() {
  try {
    const res = await fetch(`${API_ENDPOINT}?action=manage_campuses`);
    const json = await res.json();
    const campuses = json.campuses || json.data?.campuses || [];
    if (json.success && Array.isArray(campuses)) {
      cachedCampuses = campuses;

      const activeList = cachedCampuses.filter(c => c.status === 'active' || c.is_active == 1 || !c.status);

      // Populate Topbar Filter
      const sel = document.getElementById('globalCampusSelector');
      if (sel) {
        const curVal = sel.value;
        sel.innerHTML = '<option value="">All Campuses (Global)</option>' +
          activeList.map(c => `
            <option value="${c.id}" ${curVal == c.id ? 'selected' : ''}>${escapeHtml(c.name)} (${escapeHtml(c.code)})</option>
          `).join('');
      }

      // Populate Admin Modal Campus Select
      const adminCampSel = document.getElementById('adminModalCampusSelect');
      if (adminCampSel) {
        const curSel = adminCampSel.value;
        adminCampSel.innerHTML = '<option value="">Select Campus...</option>' +
          activeList.map(c => `
            <option value="${c.id}" ${curSel == c.id ? 'selected' : ''}>${escapeHtml(c.name)} (${escapeHtml(c.code)})</option>
          `).join('');
      }
    }
  } catch (err) {
    console.error("Failed to populate campuses dropdown:", err);
  }
}

async function loadCampuses() {
  const tbody = document.getElementById('campusesTableBody');
  if (!tbody) return;

  tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading campuses...</td></tr>`;

  try {
    const res = await fetch(`${API_ENDPOINT}?action=manage_campuses`);
    const json = await res.json();
    const campuses = json.campuses || json.data?.campuses || [];

    if (json.success && Array.isArray(campuses)) {
      cachedCampuses = campuses;
      loadCampusesForDropdown();

      if (campuses.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--muted);">No campuses found. Click "Add Campus" to provision one.</td></tr>`;
        return;
      }

      tbody.innerHTML = campuses.map(c => `
        <tr>
          <td><strong style="color:var(--muted);">#${c.id}</strong></td>
          <td>
            <div style="font-weight:600; color:var(--ink);">${escapeHtml(c.name)}</div>
          </td>
          <td><code>${escapeHtml(c.code)}</code></td>
          <td><div style="font-size:0.85rem; color:var(--muted);">${escapeHtml(c.address || 'Not specified')}</div></td>
          <td>
            <span class="status-pill ${c.status === 'active' || c.is_active == 1 ? 'active' : 'blocked'}">
              ${c.status === 'active' || c.is_active == 1 ? 'Active' : 'Inactive'}
            </span>
          </td>
          <td>${c.created_at ? c.created_at.substring(0, 10) : 'N/A'}</td>
          <td>
            <div class="action-btn-group">
              <button class="btn-action-icon" title="Edit Campus" onclick="openEditCampusModal(${c.id})">
                <i class="fa-solid fa-pen-to-square"></i>
              </button>
              <button class="btn-action-icon ${c.is_active == 1 ? 'danger' : 'success'}" title="${c.is_active == 1 ? 'Deactivate Campus' : 'Activate Campus'}" onclick="toggleCampusStatus(${c.id}, ${c.is_active == 1 ? 0 : 1})">
                <i class="fa-solid ${c.is_active == 1 ? 'fa-ban' : 'fa-circle-check'}"></i>
              </button>
            </div>
          </td>
        </tr>
      `).join('');
    } else {
      tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--danger);">${json.message || 'Failed to load campuses.'}</td></tr>`;
    }
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--danger);">Error loading campuses.</td></tr>`;
  }
}

function openAddCampusModal() {
  document.getElementById('campusModalTitle').textContent = 'Add New Campus';
  document.getElementById('campusFormId').value = '';
  document.getElementById('campusFormName').value = '';
  document.getElementById('campusFormCode').value = '';
  document.getElementById('campusFormAddress').value = '';
  document.getElementById('campusModal').classList.add('open');
}

function openEditCampusModal(campusId) {
  const c = cachedCampuses.find(item => item.id == campusId);
  if (!c) return;

  document.getElementById('campusModalTitle').textContent = 'Edit Campus';
  document.getElementById('campusFormId').value = c.id;
  document.getElementById('campusFormName').value = c.name;
  document.getElementById('campusFormCode').value = c.code;
  document.getElementById('campusFormAddress').value = c.address || '';
  document.getElementById('campusModal').classList.add('open');
}

async function handleSaveCampus(e) {
  e.preventDefault();
  const btn = document.getElementById('btnSaveCampus');
  btn.disabled = true;
  btn.textContent = 'Saving...';

  const campusId = document.getElementById('campusFormId').value;
  const isEdit = !!campusId;
  const payload = {
    subaction: isEdit ? 'update' : 'create',
    id: campusId,
    name: document.getElementById('campusFormName').value.trim(),
    code: document.getElementById('campusFormCode').value.trim().toUpperCase(),
    address: document.getElementById('campusFormAddress').value.trim()
  };

  try {
    const res = await fetch(`${API_ENDPOINT}?action=manage_campuses`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (json.success) {
      showToast(json.message || 'Campus saved successfully.', 'success');
      closeModal('campusModal');
      loadCampuses();
    } else {
      showToast(json.message || 'Failed to save campus.', 'error');
    }
  } catch (err) {
    showToast('Network error saving campus.', 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Save Campus';
  }
}

async function toggleCampusStatus(campusId, newStatus) {
  const actionName = newStatus === 1 ? 'activate' : 'deactivate';
  if (!confirm(`Are you sure you want to ${actionName} Campus #${campusId}?`)) return;

  try {
    const res = await fetch(`${API_ENDPOINT}?action=manage_campuses`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        subaction: 'toggle_status',
        id: campusId,
        is_active: newStatus
      })
    });
    const json = await res.json();
    if (json.success) {
      showToast(`Campus status updated.`, 'success');
      loadCampuses();
    } else {
      showToast(json.message || 'Action failed.', 'error');
    }
  } catch (err) {
    showToast('Error updating campus status.', 'error');
  }
}

/* ── 9. Campus Administrators Management (Master Admin Only) ────────── */
async function loadCampusAdmins() {
  const tbody = document.getElementById('campusAdminsTableBody');
  if (!tbody) return;

  tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading administrators...</td></tr>`;

  try {
    const res = await fetch(`${API_ENDPOINT}?action=manage_campus_admins`);
    const json = await res.json();
    const admins = json.admins || json.data?.admins || [];

    if (json.success && Array.isArray(admins)) {
      cachedAdmins = admins;

      if (admins.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:30px; color:var(--muted);">No administrators found.</td></tr>`;
        return;
      }

      tbody.innerHTML = admins.map(a => `
        <tr>
          <td><strong style="color:var(--muted);">#${a.admin_id}</strong></td>
          <td>
            <div style="font-weight:600; color:var(--ink);">${escapeHtml(a.full_name)}</div>
          </td>
          <td><code>${escapeHtml(a.username)}</code></td>
          <td><div style="font-size:0.85rem; color:var(--muted);">${escapeHtml(a.email)}</div></td>
          <td>
            <div style="display:flex; align-items:center; gap:6px;">
              <i class="fa-solid fa-building-columns" style="color:var(--accent); font-size:0.8rem;"></i>
              <span style="font-weight:600; color:var(--ink); font-size:0.85rem;">${escapeHtml(a.campus_name || (a.role === 'master_admin' ? 'All Campuses (Global)' : 'Unassigned'))}</span>
              ${a.campus_code ? `<span class="status-pill closed" style="font-size:0.68rem; padding:1px 5px;">${escapeHtml(a.campus_code)}</span>` : ''}
            </div>
          </td>
          <td>
            <span class="status-pill ${a.role === 'master_admin' ? 'active' : 'claimed'}" style="text-transform:uppercase;">
              ${a.role === 'master_admin' ? 'Master Admin' : 'Campus Admin'}
            </span>
          </td>
          <td>
            <span class="status-pill ${a.status === 'active' ? 'active' : 'blocked'}">
              ${escapeHtml(a.status || 'active')}
            </span>
          </td>
          <td>${a.created_at ? a.created_at.substring(0, 10) : 'N/A'}</td>
          <td>
            <div class="action-btn-group">
              <button class="btn-action-icon" title="Edit Admin" onclick="openEditAdminModal(${a.admin_id})">
                <i class="fa-solid fa-pen-to-square"></i>
              </button>
              ${a.admin_id != 1 ? `
                <button class="btn-action-icon ${a.status === 'active' ? 'danger' : 'success'}" title="${a.status === 'active' ? 'Suspend Admin' : 'Activate Admin'}" onclick="toggleAdminStatus(${a.admin_id}, '${a.status === 'active' ? 'suspended' : 'active'}')">
                  <i class="fa-solid ${a.status === 'active' ? 'fa-ban' : 'fa-circle-check'}"></i>
                </button>
                <button class="btn-action-icon danger" title="Remove Admin" onclick="deleteCampusAdmin(${a.admin_id}, '${escapeHtml(a.username)}')">
                  <i class="fa-solid fa-trash"></i>
                </button>
              ` : ''}
            </div>
          </td>
        </tr>
      `).join('');
    } else {
      tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:30px; color:var(--danger);">${json.message || 'Failed to load administrators.'}</td></tr>`;
    }
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:30px; color:var(--danger);">Error loading administrators.</td></tr>`;
  }
}

function handleAdminRoleSelectChange(val) {
  const campusGrp = document.getElementById('adminModalCampusGroup');
  const campusSel = document.getElementById('adminModalCampusSelect');
  if (val === 'master_admin') {
    if (campusGrp) campusGrp.style.opacity = '0.4';
    if (campusSel) campusSel.disabled = true;
  } else {
    if (campusGrp) campusGrp.style.opacity = '1';
    if (campusSel) campusSel.disabled = false;
  }
}

function openAddAdminModal() {
  loadCampusesForDropdown();
  document.getElementById('adminModalTitle').textContent = 'Add Campus Administrator';
  document.getElementById('adminModalId').value = '';
  document.getElementById('adminModalFullName').value = '';
  document.getElementById('adminModalUsername').value = '';
  document.getElementById('adminModalEmail').value = '';
  document.getElementById('adminModalPassword').value = '';
  document.getElementById('adminModalPassword').required = true;
  document.getElementById('adminModalPasswordLabel').textContent = 'Password (Required, min 4 chars)';
  document.getElementById('adminModalRole').value = 'campus_admin';
  handleAdminRoleSelectChange('campus_admin');
  document.getElementById('adminModalCampusSelect').value = '';
  document.getElementById('campusAdminModal').classList.add('open');
}

function openEditAdminModal(adminId) {
  loadCampusesForDropdown();
  const a = cachedAdmins.find(item => item.admin_id == adminId);
  if (!a) return;

  document.getElementById('adminModalTitle').textContent = 'Edit Administrator';
  document.getElementById('adminModalId').value = a.admin_id;
  document.getElementById('adminModalFullName').value = a.full_name;
  document.getElementById('adminModalUsername').value = a.username;
  document.getElementById('adminModalEmail').value = a.email;
  document.getElementById('adminModalPassword').value = '';
  document.getElementById('adminModalPassword').required = false;
  document.getElementById('adminModalPasswordLabel').textContent = 'Password (leave blank to keep current)';
  document.getElementById('adminModalRole').value = a.role === 'master_admin' ? 'master_admin' : 'campus_admin';
  handleAdminRoleSelectChange(a.role);
  document.getElementById('adminModalCampusSelect').value = a.campus_id || '';
  document.getElementById('campusAdminModal').classList.add('open');
}

async function handleSaveCampusAdmin(e) {
  e.preventDefault();
  const btn = document.getElementById('btnSaveAdmin');
  btn.disabled = true;
  btn.textContent = 'Saving...';

  const adminId = document.getElementById('adminModalId').value;
  const isEdit = !!adminId;
  const role = document.getElementById('adminModalRole').value;
  const campusId = role === 'campus_admin' ? document.getElementById('adminModalCampusSelect').value : null;

  if (role === 'campus_admin' && !campusId) {
    showToast('Campus Admin must be assigned to an active campus.', 'error');
    btn.disabled = false;
    btn.textContent = 'Save Admin';
    return;
  }

  const payload = {
    subaction: isEdit ? 'update' : 'create',
    admin_id: adminId,
    full_name: document.getElementById('adminModalFullName').value.trim(),
    username: document.getElementById('adminModalUsername').value.trim(),
    email: document.getElementById('adminModalEmail').value.trim(),
    role: role,
    campus_id: campusId,
    password: document.getElementById('adminModalPassword').value
  };

  try {
    const res = await fetch(`${API_ENDPOINT}?action=manage_campus_admins`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (json.success) {
      showToast(json.message || 'Administrator saved successfully.', 'success');
      closeModal('campusAdminModal');
      loadCampusAdmins();
    } else {
      showToast(json.message || json.error || 'Failed to save administrator.', 'error');
    }
  } catch (err) {
    showToast('Network error saving administrator.', 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Save Admin';
  }
}

async function toggleAdminStatus(adminId, newStatus) {
  const actionName = newStatus === 'active' ? 'reactivate' : 'suspend';
  if (!confirm(`Are you sure you want to ${actionName} Administrator #${adminId}?`)) return;

  try {
    const res = await fetch(`${API_ENDPOINT}?action=manage_campus_admins`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        subaction: 'toggle_status',
        admin_id: adminId,
        status: newStatus
      })
    });
    const json = await res.json();
    if (json.success) {
      showToast(`Administrator marked as ${newStatus}.`, 'success');
      loadCampusAdmins();
    } else {
      showToast(json.message || json.error || 'Action failed.', 'error');
    }
  } catch (err) {
    showToast('Error updating administrator status.', 'error');
  }
}

async function deleteCampusAdmin(adminId, username) {
  if (!confirm(`Are you sure you want to permanently remove campus administrator "${username}"?\n\nThis will revoke their access to their campus portal immediately.`)) {
    return;
  }

  try {
    const res = await fetch(`${API_ENDPOINT}?action=manage_campus_admins`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        subaction: 'delete',
        admin_id: adminId
      })
    });
    const json = await res.json();
    if (json.success) {
      showToast(json.message || `Campus Admin "${username}" removed successfully.`, 'success');
      loadCampusAdmins();
    } else {
      showToast(json.message || json.error || 'Failed to remove administrator.', 'error');
    }
  } catch (err) {
    showToast('Network error removing administrator.', 'error');
  }
}
function showToast(message, type = 'success') {
  const container = document.getElementById('toastContainer');
  if (!container) return;

  const toast = document.createElement('div');
  toast.className = `admin-toast ${type}`;
  toast.innerHTML = `
    <i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'}" style="color:${type === 'success' ? 'var(--success)' : 'var(--danger)'}"></i>
    <span>${escapeHtml(message)}</span>
  `;
  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
    toast.style.transition = 'all 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function debounce(fn, delay) {
  let timer;
  return function (...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), delay);
  };
}
