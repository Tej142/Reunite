/**
 * Reunite Platform Admin Command Center Controller
 * Asynchronous AJAX interaction & real-time telemetry management
 */

const API_ENDPOINT = '../../Backend/admin_api.php';
let activeTab = 'overview';
let cachedUsers = [];

document.addEventListener('DOMContentLoaded', () => {
  initNavigation();
  initEventListeners();
  loadStats();
  loadSystemDiagnostics();
});

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
    users: { title: 'User Accounts', sub: 'Directory of registered students and administrators.' },
    lost: { title: 'Lost Reports', sub: 'Items reported missing across campus.' },
    found: { title: 'Found Items', sub: 'Items in custody awaiting claim verification.' },
    matches: { title: 'AI Matches', sub: 'Semantic and image similarity pairings.' },
    claims: { title: 'Ownership Claims', sub: 'Submitted proofs and handoff authorizations.' },
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
  document.getElementById('btnGlobalRefresh').addEventListener('click', () => {
    loadStats();
    switchTab(activeTab);
    showToast('Platform data refreshed.', 'success');
  });

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

/* ── 1. Load Overview Stats ─────────────────────────────────────────── */
async function loadStats() {
  try {
    const res = await fetch(`${API_ENDPOINT}?action=stats`);
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
    const res = await fetch(`${API_ENDPOINT}?action=users&search=${search}&role=${role}&status=${status}`);
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
    const res = await fetch(`${API_ENDPOINT}?action=lost_reports&search=${search}&category=${cat}&status=${stat}`);
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
    const res = await fetch(`${API_ENDPOINT}?action=found_reports&search=${search}&category=${cat}&status=${stat}`);
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
    const res = await fetch(`${API_ENDPOINT}?action=matches`);
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

  try {
    const res = await fetch(`${API_ENDPOINT}?action=recovery_cases`);
    const json = await res.json();
    const cases = json.cases || json.data?.cases;
    if (json.success && Array.isArray(cases)) {
      if (cases.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--muted);">No recovery claim cases registered.</td></tr>`;
        return;
      }

      tbody.innerHTML = cases.map(c => `
        <tr>
          <td><strong style="color:var(--muted);">#${c.id}</strong></td>
          <td><code>Match #${c.match_id || c.found_report_id || 'N/A'}</code></td>
          <td>
            <div style="font-weight:600; color:var(--ink);">${escapeHtml(c.claimant_name || 'Claimant')}</div>
            <div style="font-size:0.75rem; color:var(--muted);">${escapeHtml(c.claimant_email || '')} • ${escapeHtml(c.claimant_phone || '')}</div>
          </td>
          <td>
            <div style="font-size:0.85rem; color:var(--ink-secondary); max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escapeHtml(c.proof_description || '')}">
              ${escapeHtml(c.proof_description || 'Proof of ownership documents submitted')}
            </div>
            ${c.proof_image_path ? `
              <a href="../../${escapeHtml(c.proof_image_path)}" target="_blank" style="font-size:0.75rem; color:var(--accent); text-decoration:none;">
                <i class="fa-solid fa-paperclip"></i> View Proof Attachment
              </a>
            ` : ''}
          </td>
          <td>
            <span class="status-pill ${c.status === 'approved' || c.status === 'handed_off' ? 'active' : (c.status === 'rejected' ? 'blocked' : 'pending')}">
              ${escapeHtml(c.status || 'pending_verification')}
            </span>
          </td>
          <td>${c.created_at ? c.created_at.substring(0, 10) : 'N/A'}</td>
          <td>
            <div class="action-btn-group">
              <button class="btn-action-icon success" title="Approve Claim" onclick="updateClaimStatus(${c.id}, 'approved')">
                <i class="fa-solid fa-check"></i>
              </button>
              <button class="btn-action-icon" title="Mark Handed Off to Student" onclick="updateClaimStatus(${c.id}, 'handed_off')">
                <i class="fa-solid fa-handshake"></i>
              </button>
              <button class="btn-action-icon danger" title="Reject Claim" onclick="updateClaimStatus(${c.id}, 'rejected')">
                <i class="fa-solid fa-ban"></i>
              </button>
            </div>
          </td>
        </tr>
      `).join('');
    } else {
      tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--muted);">No recovery claim cases registered.</td></tr>`;
    }
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--danger);">Error loading claims.</td></tr>`;
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
    const res = await fetch(`${API_ENDPOINT}?action=logs&search=${search}&category=${cat}`);
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
  ['Db','Ai','Smtp'].forEach(key => {
    const badge = document.getElementById(`diag${key}Badge`);
    const pulse = document.getElementById(`diag${key}Pulse`);
    const card  = document.getElementById(`diagCard${key}`);
    if (badge) { badge.textContent = '…'; badge.className = 'diag-status-badge'; }
    if (pulse) { pulse.className = 'diag-pulse'; }
    if (card)  { card.className = 'diag-service-card'; }
  });

  try {
    const res  = await fetch(`${API_ENDPOINT}?action=system_test`);
    const json = await res.json();

    if (json.success) {
      const d = json.data || json;

      // ── Database ──────────────────────────────────────────────
      const dbOk = !!(d.database && d.database.toLowerCase().includes('connect'));
      applyDiagCard({
        cardId:   'diagCardDb',
        iconId:   'diagDbIcon',
        pulseId:  'diagDbPulse',
        badgeId:  'diagDbBadge',
        detailId: 'diagDbDetail',
        ok:       dbOk,
        badgeText: dbOk ? 'Online' : 'Offline',
        detail:   d.database || 'MySQL'
      });

      // ── AI Microservice ────────────────────────────────────────
      const aiOk    = !!(d.ai_backend_status && d.ai_backend_status.toLowerCase().includes('online'));
      const latency = parseInt(d.ai_latency_ms || 0, 10);
      applyDiagCard({
        cardId:   'diagCardAi',
        iconId:   'diagAiIcon',
        pulseId:  'diagAiPulse',
        badgeId:  'diagAiBadge',
        detailId: 'diagAiLatency',
        ok:       aiOk,
        badgeText: aiOk ? 'Online' : 'Offline',
        detail:   aiOk ? (d.ai_backend_url || 'Render Cloud') : 'Unreachable'
      });
      // Latency bar
      const barWrap = document.getElementById('diagLatencyBarWrap');
      const fill    = document.getElementById('diagLatencyFill');
      const label   = document.getElementById('diagLatencyLabel');
      if (barWrap && aiOk) {
        barWrap.style.display = 'flex';
        const pct   = Math.min(latency / 800 * 100, 100);
        const color = latency < 200 ? '#10B981' : latency < 500 ? '#F59E0B' : '#EF4444';
        if (fill)  { fill.style.width = pct + '%'; fill.style.background = color; }
        if (label) { label.textContent = latency + ' ms'; label.style.color = color; }
      } else if (barWrap) {
        barWrap.style.display = 'none';
      }

      // ── SMTP ──────────────────────────────────────────────────
      const smtpOk = !!(d.smtp_configured && d.smtp_configured.toLowerCase().includes('configured'));
      applyDiagCard({
        cardId:   'diagCardSmtp',
        iconId:   'diagSmtpIcon',
        pulseId:  'diagSmtpPulse',
        badgeId:  'diagSmtpBadge',
        detailId: 'diagSmtpStatus',
        ok:       smtpOk,
        badgeText: smtpOk ? 'Active' : 'Not set',
        detail:   d.smtp_configured || 'Brevo API'
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
  const card  = document.getElementById(cardId);
  const icon  = document.getElementById(iconId);
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
    badge.className   = 'diag-status-badge ' + (ok ? 'badge-online' : 'badge-offline');
  }
  if (detailEl && detail) {
    detailEl.textContent = detail;
  }
}



/* ── Toast Helper ──────────────────────────────────────────────────── */
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
  return function(...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), delay);
  };
}
