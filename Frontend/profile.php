<?php
require_once __DIR__ . '/includes/auth.php';
// Protected page: require student to be logged in
require_login();
$user = get_current_user_data();
$user_name = $user['full_name'] ?? $user['name'] ?? ($_SESSION['full_name'] ?? 'Student');
$user_pin = $user['pin'] ?? ($_SESSION['pin'] ?? '');
$user_email = $user['email_raw'] ?? $user['email'] ?? ($_SESSION['email'] ?? '');
$user_phone = $user['phone_raw'] ?? $user['phone'] ?? ($_SESSION['phone'] ?? '');
$user_college = $user['college'] ?? ($_SESSION['college'] ?? '');
$user_branch = $user['branch'] ?? ($_SESSION['branch'] ?? '');
$user_branch_display = function_exists('get_branch_name') ? get_branch_name($user_branch) : $user_branch;
$user_initial = !empty(trim($user_name)) ? strtoupper(substr(trim($user_name), 0, 1)) : 'S';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Profile — Reunite</title>
  <meta name="description" content="Manage your student profile, view your active lost &amp; found reports, and configure security settings." />
  <script>
    (function(){
      var t = localStorage.getItem('reunite_theme');
      if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    })();
  </script>
  <link rel="stylesheet" href="css/variables.css?v=<?php echo time(); ?>" />
  <link rel="stylesheet" href="css/profile.css?v=<?php echo time(); ?>" />
</head>
<body>

<!-- ── Navigation ─────────────────────────────────────────── -->
<?php include 'components/nav.php'; ?>

<!-- ── Main Profile Container ─────────────────────────────── -->
<main class="profile-page-main">

  <!-- Breadcrumb & Status -->
  <div class="profile-top-bar">
    <a href="home.php" class="back-link">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
      </svg>
      Back to Dashboard
    </a>
    <div class="badge-verified">
      <span class="verified-dot"></span>
      Verified Student Account
    </div>
  </div>

  <!-- ── Profile Header Hero ── -->
  <section class="profile-hero-card">
    <div class="profile-hero-layout">
      <!-- Large Avatar Circle -->
      <div class="profile-avatar-large">
        <span class="avatar-letter"><?php echo htmlspecialchars($user_initial); ?></span>
        <button type="button" class="btn-avatar-edit" title="Change Avatar" aria-label="Change Avatar">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>
          </svg>
        </button>
      </div>

      <!-- User Information -->
      <div class="profile-hero-details">
        <div class="profile-name-row">
          <h1 id="profileDisplayName"><?php echo htmlspecialchars($user_name); ?></h1>
          <span class="pin-tag"><?php echo htmlspecialchars($user_pin); ?></span>
        </div>
        <p class="profile-meta-text">
          <span>🏛️ <?php echo htmlspecialchars($user_college); ?></span> &bull; 
          <span>💻 <?php echo htmlspecialchars($user_branch_display); ?></span>
        </p>
        <p class="profile-joined-text">Student Member &bull; Active Session</p>
      </div>

      <!-- Quick Action CTA -->
      <div class="profile-hero-actions">
        <a href="report-lost-item.php" class="btn-profile-action action-lost">Report Missing</a>
        <a href="report-found-item.php" class="btn-profile-action action-found">Upload Found</a>
      </div>
    </div>

    <!-- ── Activity Stats Summary ── -->
    <div class="profile-stats-grid">
      <div class="stat-box">
        <div class="stat-number">3</div>
        <div class="stat-name">Reports Filed</div>
      </div>
      <div class="stat-box highlight">
        <div class="stat-number">2</div>
        <div class="stat-name">AI Matches</div>
      </div>
      <div class="stat-box">
        <div class="stat-number">1</div>
        <div class="stat-name">Claims Verified</div>
      </div>
      <div class="stat-box success">
        <div class="stat-number">1</div>
        <div class="stat-name">Reunited Item</div>
      </div>
    </div>
  </section>

  <!-- ── Profile Tabs Navigation ── -->
  <div class="profile-tabs-wrap">
    <button type="button" class="profile-tab-btn active" data-tab="personal">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Personal Info
    </button>
    <button type="button" class="profile-tab-btn" data-tab="activity">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
      My Reports (3)
    </button>
    <button type="button" class="profile-tab-btn" data-tab="security">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      Security
    </button>
    <button type="button" class="profile-tab-btn" data-tab="notifications">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      Preferences
    </button>
  </div>

  <!-- ── Tab Panels Container ── -->
  <div class="tab-panels-wrapper">

    <!-- ── TAB 1: Personal Details Form ── -->
    <div class="profile-tab-panel active" id="tabPanelPersonal">
      <div class="panel-card">
        <div class="panel-header">
          <h2>Student Information</h2>
          <p>Update your public contact details to facilitate safe returns.</p>
        </div>

        <form id="profileDetailsForm" class="profile-form">
          <div class="form-grid-2">
            <!-- Full Name -->
            <div class="form-group">
              <label for="profName">Full Name</label>
              <input type="text" id="profName" name="name" value="<?php echo htmlspecialchars($user_name); ?>" required />
            </div>

            <!-- College PIN (Protected) -->
            <div class="form-group">
              <label for="profPin">College PIN (ID)</label>
              <input type="text" id="profPin" value="<?php echo htmlspecialchars($user_pin); ?>" disabled class="input-disabled" title="College PIN cannot be modified" />
              <span class="field-hint">Unique institutional ID tag</span>
            </div>

            <!-- Email Address -->
            <div class="form-group">
              <label for="profEmail">College Email Address</label>
              <input type="email" id="profEmail" name="email" value="<?php echo htmlspecialchars($user_email); ?>" required />
            </div>

            <!-- Contact Phone -->
            <div class="form-group">
              <label for="profPhone">Contact Phone Number</label>
              <input type="text" id="profPhone" name="phone" value="<?php echo htmlspecialchars($user_phone); ?>" placeholder="e.g. +91 98765 43210" />
            </div>

            <!-- College Name -->
            <div class="form-group">
              <label for="profCollege">College / Polytechnic</label>
              <input type="text" id="profCollege" name="college" value="<?php echo htmlspecialchars($user_college); ?>" required />
            </div>

            <!-- Branch / Department -->
            <div class="form-group">
              <label for="profBranch">Branch / Department</label>
              <input type="text" id="profBranch" name="branch" value="<?php echo htmlspecialchars($user_branch_display); ?>" required />
            </div>
          </div>

          <div class="form-actions-row">
            <button type="submit" class="btn-save-profile" id="btnSaveProfile">
              <span>Save Changes</span>
            </button>
            <div id="profileSaveStatus" class="save-status-msg"></div>
          </div>
        </form>
      </div>
    </div>

    <!-- ── TAB 2: My Reports & Items ── -->
    <div class="profile-tab-panel" id="tabPanelActivity">
      <div class="panel-card">
        <div class="panel-header-row">
          <div>
            <h2>My Activity &amp; Reports</h2>
            <p>Track all lost item inquiries and found belongings submitted under your account.</p>
          </div>
          <div class="activity-filter-chips">
            <button type="button" class="activity-chip active" data-filter="all">All (3)</button>
            <button type="button" class="activity-chip" data-filter="lost">Lost (2)</button>
            <button type="button" class="activity-chip" data-filter="found">Found (1)</button>
          </div>
        </div>

        <div class="user-reports-list">
          <!-- Item 1: Lost Phone (with match!) -->
          <div class="user-report-card lost" data-type="lost">
            <div class="user-report-thumb">
              <img src="https://images.unsplash.com/photo-1591337676887-a217a6970a8a?w=200&h=200&fit=crop" alt="iPhone 13" />
            </div>
            <div class="user-report-info">
              <div class="user-report-header">
                <span class="report-badge badge-lost">Lost Item</span>
                <span class="report-date">Reported Yesterday</span>
              </div>
              <h3 class="user-report-title">iPhone 13 with Translucent Anime Case</h3>
              <p class="user-report-desc">Midnight black iPhone 13 lost in Cafeteria area near Table 14.</p>
              <div class="user-report-match-alert">
                <span class="match-pulse"></span>
                <strong>✨ 94% Match Found:</strong> A found item matching this description is in Central Library desk!
              </div>
            </div>
            <div class="user-report-actions">
              <a href="search.php" class="btn-report-view">View Match &rarr;</a>
            </div>
          </div>

          <!-- Item 2: Lost Wallet -->
          <div class="user-report-card lost" data-type="lost">
            <div class="user-report-thumb">
              <img src="https://images.unsplash.com/photo-1627123424574-724758594e93?w=200&h=200&fit=crop" alt="Blue Wallet" />
            </div>
            <div class="user-report-info">
              <div class="user-report-header">
                <span class="report-badge badge-lost">Lost Item</span>
                <span class="report-date">3 days ago</span>
              </div>
              <h3 class="user-report-title">Navy Blue Leather Bifold Wallet</h3>
              <p class="user-report-desc">Tommy Hilfiger leather wallet with ID card and transit pass inside.</p>
            </div>
            <div class="user-report-actions">
              <a href="search.php" class="btn-report-view">View In Catalog &rarr;</a>
            </div>
          </div>

          <!-- Item 3: Found Calculator (Reunited) -->
          <div class="user-report-card found" data-type="found">
            <div class="user-report-thumb">
              <img src="https://images.unsplash.com/photo-1594980596870-8aa52a78d8cd?w=200&h=200&fit=crop" alt="Scientific Calculator" />
            </div>
            <div class="user-report-info">
              <div class="user-report-header">
                <span class="report-badge badge-reunited">Reunited</span>
                <span class="report-date">Last Week</span>
              </div>
              <h3 class="user-report-title">Casio fx-991EX Scientific Calculator</h3>
              <p class="user-report-desc">Found in Computer Science Lab 3 and successfully returned to owner.</p>
            </div>
            <div class="user-report-actions">
              <span class="badge-resolved">✅ Handed Off</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── TAB 3: Security & Password ── -->
    <div class="profile-tab-panel" id="tabPanelSecurity">
      <div class="panel-card">
        <div class="panel-header">
          <h2>Security &amp; Credentials</h2>
          <p>Manage your account password and active session credentials.</p>
        </div>

        <form id="passwordChangeForm" class="profile-form">
          <div class="form-group-single">
            <label for="currPassword">Current Password</label>
            <input type="password" id="currPassword" name="current_password" placeholder="Enter current password" required />
          </div>

          <div class="form-grid-2">
            <div class="form-group">
              <label for="newPassword">New Password</label>
              <input type="password" id="newPassword" name="new_password" placeholder="Minimum 8 characters" required />
            </div>
            <div class="form-group">
              <label for="confirmNewPassword">Confirm New Password</label>
              <input type="password" id="confirmNewPassword" name="confirm_new_password" placeholder="Re-enter new password" required />
            </div>
          </div>

          <div class="form-actions-row">
            <button type="submit" class="btn-save-profile" id="btnChangePassword">
              <span>Update Password</span>
            </button>
            <div id="passwordStatusMsg" class="save-status-msg"></div>
          </div>
        </form>
      </div>
    </div>

    <!-- ── TAB 4: Notification Preferences ── -->
    <div class="profile-tab-panel" id="tabPanelNotifications">
      <div class="panel-card">
        <div class="panel-header">
          <h2>Notification Preferences</h2>
          <p>Choose when and how Reunite notifies you regarding match updates and claim handoffs.</p>
        </div>

        <div class="pref-toggles-list">
          <div class="pref-toggle-item">
            <div>
              <div class="pref-title">Instant AI Match Notifications</div>
              <div class="pref-desc">Receive instant alerts when a found item matches your lost report with &gt; 85% confidence.</div>
            </div>
            <label class="switch">
              <input type="checkbox" checked />
              <span class="slider"></span>
            </label>
          </div>

          <div class="pref-toggle-item">
            <div>
              <div class="pref-title">Claim Status Updates</div>
              <div class="pref-desc">Get notified when a student submits or verifies a claim for an item you found.</div>
            </div>
            <label class="switch">
              <input type="checkbox" checked />
              <span class="slider"></span>
            </label>
          </div>

          <div class="pref-toggle-item">
            <div>
              <div class="pref-title">Community Board Digest</div>
              <div class="pref-desc">Weekly summary of new found items in your department and campus hubs.</div>
            </div>
            <label class="switch">
              <input type="checkbox" />
              <span class="slider"></span>
            </label>
          </div>
        </div>
      </div>
    </div>

  </div>

</main>

<script src="js/profile.js?v=<?php echo time(); ?>"></script>
</body>
</html>
