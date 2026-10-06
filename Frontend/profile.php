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

$collegeList = [
    'svgp'     => 'Sri Venkateswara Government polytechnic, Tirupati',
    'plpt'     => 'Government polytechinc pillaripattu, Puttur.',
    'vkpt'     => 'Venkata Perumal',
    'berkeley' => 'University of California, Berkeley',
    'columbia' => 'Columbia University',
    'gatech'   => 'Georgia Institute of Technology (Georgia Tech)',
    'cmu'      => 'Carnegie Mellon University (CMU)',
    'other'    => 'Other Academic Institution'
];

$branchList = [
    'cme'  => 'Computer Engineering',
    'cse'  => 'Computer Science & Engineering',
    'ece'  => 'Electronics & Communication Engineering',
    'eee'  => 'Electrical & Electronics Engineering',
    'me'   => 'Mechanical Engineering',
    'ce'   => 'Civil Engineering',
    'che'  => 'Chemical Engineering',
    'ae'   => 'Aerospace Engineering',
    'aiml' => 'AI & Machine Learning',
    'it'   => 'Information Technology',
    'oth'  => 'Other / General Studies'
];

function resolve_profile_college_code($val, $list) {
    if (empty($val)) return '';
    $valLower = strtolower(trim((string)$val));
    if (isset($list[$valLower])) {
        return $valLower;
    }
    foreach ($list as $k => $name) {
        if (strtolower($name) === $valLower || stripos($valLower, strtolower($k)) !== false || stripos(strtolower($name), $valLower) !== false) {
            return $k;
        }
    }
    return 'other';
}

function resolve_profile_branch_code($val, $list) {
    if (empty($val)) return '';
    $valLower = strtolower(trim((string)$val));
    if (isset($list[$valLower])) {
        return $valLower;
    }
    $aliasMap = [
        'cs' => 'cse',
        'ec' => 'ece',
        'ee' => 'eee',
        'mec' => 'me',
        'civ' => 'ce',
        'other' => 'oth'
    ];
    if (isset($aliasMap[$valLower])) {
        return $aliasMap[$valLower];
    }
    foreach ($list as $k => $name) {
        if (strtolower($name) === $valLower || stripos(strtolower($name), $valLower) !== false) {
            return $k;
        }
    }
    return 'oth';
}

$user_college_code = resolve_profile_college_code($user_college, $collegeList);
$user_college_display = $collegeList[$user_college_code] ?? ($user_college ?: 'University Campus');
$user_branch_code = resolve_profile_branch_code($user_branch, $branchList);
$user_branch_display = $branchList[$user_branch_code] ?? (function_exists('get_branch_name') ? get_branch_name($user_branch) : $user_branch);
$user_initial = !empty(trim($user_name)) ? strtoupper(substr(trim($user_name), 0, 1)) : 'S';

// Fetch current user's real database reports & statistics
$userId = $user['user_id'] ?? ($_SESSION['user_id'] ?? null);

$userReports = [];
$userStats = [
    'reports_filed' => 0,
    'lost_count' => 0,
    'found_count' => 0,
    'ai_matches' => 0,
    'claims_verified' => 0,
    'reunited_items' => 0
];

if ($userId && isset($conn) && $conn) {
    // 1. Fetch lost reports
    $lStmt = $conn->prepare("SELECT id, 'lost' as report_type, title, description, category, location, image_path, status, created_at FROM lost_reports WHERE user_id = ? ORDER BY created_at DESC");
    if ($lStmt) {
        $lStmt->bind_param("i", $userId);
        $lStmt->execute();
        $res = $lStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $userReports[] = $row;
            $userStats['lost_count']++;
            if (in_array($row['status'], ['matched', 'claimed', 'closed'])) {
                $userStats['reunited_items']++;
            }
        }
        $lStmt->close();
    }

    // 2. Fetch found reports
    $fStmt = $conn->prepare("SELECT id, 'found' as report_type, title, description, category, location, image_path, status, created_at FROM found_reports WHERE user_id = ? ORDER BY created_at DESC");
    if ($fStmt) {
        $fStmt->bind_param("i", $userId);
        $fStmt->execute();
        $res = $fStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $userReports[] = $row;
            $userStats['found_count']++;
            if (in_array($row['status'], ['matched', 'claimed', 'closed'])) {
                $userStats['reunited_items']++;
            }
        }
        $fStmt->close();
    }

    // Sort all combined reports by created_at DESC
    usort($userReports, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    $userStats['reports_filed'] = count($userReports);

    // 3. Count AI matches linked to user's lost reports
    $mStmt = $conn->prepare("SELECT COUNT(*) as match_cnt FROM matches m JOIN lost_reports lr ON m.lost_report_id = lr.id WHERE lr.user_id = ?");
    if ($mStmt) {
        $mStmt->bind_param("i", $userId);
        $mStmt->execute();
        $res = $mStmt->get_result();
        if ($mRow = $res->fetch_assoc()) {
            $userStats['ai_matches'] = (int)($mRow['match_cnt'] ?? 0);
        }
        $mStmt->close();
    }

    // 4. Count claims verified / recovery cases
    $cStmt = $conn->prepare("SELECT COUNT(*) as claim_cnt FROM recovery_cases WHERE claimant_id = ? AND status IN ('approved', 'handed_off')");
    if ($cStmt) {
        $cStmt->bind_param("i", $userId);
        $cStmt->execute();
        $res = $cStmt->get_result();
        if ($cRow = $res->fetch_assoc()) {
            $userStats['claims_verified'] = (int)($cRow['claim_cnt'] ?? 0);
        }
        $cStmt->close();
    }
}
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
          <span style="display: inline-flex; align-items: center; gap: 0.35rem;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 10h1"/><path d="M14 10h1"/><path d="M9 14h1"/><path d="M14 14h1"/><path d="M9 18h1"/><path d="M14 18h1"/></svg>
            <?php echo htmlspecialchars($user_college_display); ?>
          </span>
          &bull; 
          <span style="display: inline-flex; align-items: center; gap: 0.35rem;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="12" x="3" y="4" rx="2"/><line x1="2" x2="22" y1="20" y2="20"/></svg>
            <?php echo htmlspecialchars($user_branch_display); ?>
          </span>
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
        <div class="stat-number"><?php echo (int)$userStats['reports_filed']; ?></div>
        <div class="stat-name">Reports Filed</div>
      </div>
      <div class="stat-box highlight">
        <div class="stat-number"><?php echo (int)$userStats['ai_matches']; ?></div>
        <div class="stat-name">AI Matches</div>
      </div>
      <div class="stat-box">
        <div class="stat-number"><?php echo (int)$userStats['claims_verified']; ?></div>
        <div class="stat-name">Claims Verified</div>
      </div>
      <div class="stat-box success">
        <div class="stat-number"><?php echo (int)$userStats['reunited_items']; ?></div>
        <div class="stat-name">Reunited Items</div>
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
      My Reports (<?php echo (int)$userStats['reports_filed']; ?>)
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

            <!-- College / University Dropdown -->
            <div class="form-group">
              <label for="profCollege">College / University</label>
              <select id="profCollege" name="college" class="profile-select" required>
                <option value="" disabled <?php echo empty($user_college_code) ? 'selected' : ''; ?>>Select your college...</option>
                <?php foreach ($collegeList as $code => $cName): ?>
                  <option value="<?php echo htmlspecialchars($code); ?>" <?php echo ($user_college_code === $code) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cName); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Academic Branch / Department Dropdown -->
            <div class="form-group">
              <label for="profBranch">Academic Branch / Department</label>
              <select id="profBranch" name="branch" class="profile-select" required>
                <option value="" disabled <?php echo empty($user_branch_code) ? 'selected' : ''; ?>>Select your branch...</option>
                <?php foreach ($branchList as $code => $bName): ?>
                  <option value="<?php echo htmlspecialchars($code); ?>" <?php echo ($user_branch_code === $code) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($bName); ?>
                  </option>
                <?php endforeach; ?>
              </select>
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
            <button type="button" class="activity-chip active" data-filter="all">All (<?php echo (int)$userStats['reports_filed']; ?>)</button>
            <button type="button" class="activity-chip" data-filter="lost">Lost (<?php echo (int)$userStats['lost_count']; ?>)</button>
            <button type="button" class="activity-chip" data-filter="found">Found (<?php echo (int)$userStats['found_count']; ?>)</button>
          </div>
        </div>

        <div class="user-reports-list">
          <?php if (!empty($userReports)): ?>
            <?php foreach ($userReports as $rep): 
              $isLost = ($rep['report_type'] === 'lost');
              $status = $rep['status'] ?? 'active';
              $badgeClass = in_array($status, ['matched', 'claimed', 'closed']) ? 'badge-reunited' : ($isLost ? 'badge-lost' : 'badge-found');
              $badgeLabel = in_array($status, ['matched', 'claimed', 'closed']) ? 'Reunited' : ($isLost ? 'Lost Item' : 'Found Item');
              $dateStr = !empty($rep['created_at']) ? date('M d, Y', strtotime($rep['created_at'])) : 'Recent';
              $imgSrc = !empty($rep['image_path']) ? htmlspecialchars($rep['image_path']) : 'assets/placeholder.jpg';
              if (!empty($rep['image_path']) && !str_starts_with($rep['image_path'], 'http') && !str_starts_with($rep['image_path'], '/') && !str_starts_with($rep['image_path'], '../')) {
                  $imgSrc = '../' . $rep['image_path'];
              }
            ?>
              <div class="user-report-card <?php echo htmlspecialchars($rep['report_type']); ?>" data-type="<?php echo htmlspecialchars($rep['report_type']); ?>">
                <div class="user-report-thumb">
                  <img src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($rep['title']); ?>" onerror="this.onerror=null;this.src='data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%2280%22%20height%3D%2280%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2080%2080%22%3E%3Crect%20fill%3D%22%23e2e8f0%22%20width%3D%2280%22%20height%3D%2280%22%2F%3E%3Ctext%20fill%3D%22%2394a3b8%22%20font-family%3D%22sans-serif%22%20font-size%3D%2212%22%20dy%3D%22.3em%22%20x%3D%2250%25%22%20y%3D%2250%25%22%20text-anchor%3D%22middle%22%3EPhoto%3C%2Ftext%3E%3C%2Fsvg%3E';" />
                </div>
                <div class="user-report-info">
                  <div class="user-report-header">
                    <span class="report-badge <?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></span>
                    <span class="report-date"><?php echo $dateStr; ?></span>
                  </div>
                  <h3 class="user-report-title"><?php echo htmlspecialchars($rep['title']); ?></h3>
                  <p class="user-report-desc"><?php echo htmlspecialchars($rep['description'] ?? ''); ?></p>
                  <div class="user-report-meta" style="font-size: 0.75rem; color: var(--muted); margin-top: 0.35rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    <span style="display: inline-flex; align-items: center; gap: 0.25rem;">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                      <?php echo htmlspecialchars($rep['location'] ?? 'Campus Hub'); ?>
                    </span>
                    &bull; 
                    <span style="display: inline-flex; align-items: center; gap: 0.25rem;">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 2 9 9-9 9-9-9Z"/><path d="M16 8h.01"/></svg>
                      <?php echo htmlspecialchars($rep['category'] ?? 'General'); ?>
                    </span>
                  </div>
                </div>
                <div class="user-report-actions">
                  <?php 
                    $repCode = ($rep['report_type'] === 'found' ? 'RF-' : 'RL-') . str_pad($rep['id'], 5, '0', STR_PAD_LEFT);
                  ?>
                  <a href="item.php?id=<?php echo $repCode; ?>" class="btn-report-view">View Details &rarr;</a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty-user-reports" style="text-align: center; padding: 3rem 1.5rem; background: var(--surface); border: 1px dashed var(--border); border-radius: 1rem;">
              <div style="display: flex; justify-content: center; margin-bottom: 0.5rem; color: var(--muted-2);">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>
              </div>
              <h3 style="font-size: 1.1rem; color: var(--ink); margin-bottom: 0.35rem;">No Reports Filed Yet</h3>
              <p style="font-size: 0.85rem; color: var(--muted); max-width: 420px; margin: 0 auto 1.25rem;">
                You haven't submitted any missing item reports or found belongings under your account yet.
              </p>
              <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
                <a href="report-lost-item.php" class="btn btn-primary" style="font-size: 0.8125rem; padding: 0.5rem 1rem;">Report Missing Item</a>
                <a href="report-found-item.php" class="btn btn-ghost" style="font-size: 0.8125rem; padding: 0.5rem 1rem;">Upload Found Item</a>
              </div>
            </div>
          <?php endif; ?>
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
