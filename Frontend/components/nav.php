<?php
require_once __DIR__ . '/../includes/auth.php';
$current_page = basename($_SERVER['PHP_SELF']);
$logged_in = is_logged_in();
$is_auth_page = in_array($current_page, ['login.php', 'signup.php', 'forgot-password.php', 'reset-password.php']);
$user = get_current_user_data();
$u_name = $user['full_name'] ?? $user['name'] ?? ($_SESSION['full_name'] ?? '');
$user_initial = !empty(trim($u_name)) ? strtoupper(substr(trim($u_name), 0, 1)) : 'S';

$is_search = ($current_page === 'search.php');
$is_home = ($current_page === 'home.php');
$is_profile = ($current_page === 'profile.php');
?>
<script>
  // Local Flask AI Server Endpoint
  (function() {
    window.FLASK_BACKEND_URL = 'http://127.0.0.1:5000';
    console.log('[Reunite] Connected Local Flask Backend URL:', window.FLASK_BACKEND_URL);
  })();
</script>
<script src="js/microinteractions.js"></script>
<nav class="reunite-main-nav">
  <div class="nav-inner">
    <a href="<?php echo $logged_in ? 'home.php' : 'index.php'; ?>" class="brand">
      <svg width="28" height="28" viewBox="0 0 28 28" fill="none" aria-hidden="true">
        <circle cx="14" cy="14" r="14" fill="#C4622D"/>
        <path d="M14 7c-3.866 0-7 3.134-7 7 0 2.21 1.03 4.183 2.645 5.474L8 21h12l-1.645-1.526C19.97 18.183 21 16.21 21 14c0-3.866-3.134-7-7-7z" fill="white" fill-opacity="0.25"/>
        <circle cx="14" cy="14" r="3" fill="white"/>
        <path d="M14 8v3M14 17v3M8 14H5M23 14h-3" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-opacity="0.5"/>
      </svg>
      <span class="serif">Reunite</span>
    </a>

    <?php if (!$is_auth_page): ?>
      <div class="nav-links">
        <?php if ($logged_in): ?>
          <a href="search.php" id="navLinkSearch" class="<?php echo $is_search ? 'active' : ''; ?>">Search Items</a>
          <a href="home.php#board" id="navLinkBoard" class="<?php echo ($is_home && empty($_GET['tab'])) ? 'active' : ''; ?>">Community board</a>
          <a href="home.php#how" id="navLinkHow" class="">How it works</a>
        <?php endif; ?>
      </div>

      <div class="nav-actions" style="display: flex; gap: 0.75rem; align-items: center;">
        <!-- Theme Toggle Button -->
        <button type="button" class="nav-theme-btn" id="themeToggleBtn" aria-label="Toggle dark mode" title="Toggle theme">
          <svg class="sun-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="5"></circle>
            <line x1="12" y1="1" x2="12" y2="3"></line>
            <line x1="12" y1="21" x2="12" y2="23"></line>
            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
            <line x1="1" y1="12" x2="3" y2="12"></line>
            <line x1="21" y1="12" x2="23" y2="12"></line>
            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
          </svg>
          <svg class="moon-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
          </svg>
        </button>

<?php
// Query dynamic notifications for the logged in user
$userNotifs = [];
$unreadNotifCount = 0;
if ($logged_in && isset($conn) && $conn) {
    $currUid = $user['user_id'] ?? ($_SESSION['user_id'] ?? 0);
    $nStmt = $conn->prepare("SELECT notification_id, title, message, link, is_read, created_at, type FROM notifications WHERE user_id = ? OR user_id = 0 ORDER BY created_at DESC LIMIT 8");
    if ($nStmt) {
        $nStmt->bind_param("i", $currUid);
        $nStmt->execute();
        $nRes = $nStmt->get_result();
        while ($nRow = $nRes->fetch_assoc()) {
            $userNotifs[] = $nRow;
            if (empty($nRow['is_read'])) {
                $unreadNotifCount++;
            }
        }
        $nStmt->close();
    }
}
?>
        <?php if ($logged_in): ?>
          <!-- Notification Bell with Dropdown -->
          <div class="nav-notif-wrap">
            <button type="button" class="nav-notif-btn" id="navNotifBtn" aria-label="Notifications" title="Notifications">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
              </svg>
              <?php if ($unreadNotifCount > 0): ?>
                <span class="notif-badge-dot" id="navNotifBadgeDot"></span>
              <?php else: ?>
                <span class="notif-badge-dot" id="navNotifBadgeDot" style="display:none;"></span>
              <?php endif; ?>
            </button>
            <div class="nav-notif-dropdown" id="navNotifDropdown">
              <div class="notif-dropdown-header" style="display:flex;justify-content:space-between;align-items:center;">
                <div style="display:flex;align-items:center;gap:0.5rem;">
                  <span style="font-weight:700;">Notifications</span>
                  <span class="notif-count-badge" id="navNotifCountBadge"><?php echo $unreadNotifCount > 0 ? (int)$unreadNotifCount . ' New' : 'All caught up'; ?></span>
                </div>
                <button type="button" id="navNotifMarkAllBtn" style="background:none;border:none;color:var(--brand);font-size:0.75rem;font-weight:600;cursor:pointer;display:<?php echo $unreadNotifCount > 0 ? 'inline-block' : 'none'; ?>;">Mark all read</button>
              </div>
              <div class="notif-dropdown-list" id="navNotifList">
                <?php if (!empty($userNotifs)): ?>
                  <?php foreach ($userNotifs as $notif): 
                    $isUnread = empty($notif['is_read']);
                    $iconSvg = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>';
                    if (stripos($notif['title'], 'match') !== false) {
                        $iconSvg = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>';
                    } elseif (stripos($notif['title'], 'claim') !== false) {
                        $iconSvg = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
                    } elseif (stripos($notif['title'], 'found') !== false) {
                        $iconSvg = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>';
                    }
                    $timeStr = !empty($notif['created_at']) ? date('M d, H:i', strtotime($notif['created_at'])) : 'Recently';
                    $linkUrl = !empty($notif['link']) ? htmlspecialchars($notif['link']) : 'search.php';
                  ?>
                    <a href="<?php echo $linkUrl; ?>" class="notif-dropdown-item <?php echo $isUnread ? 'unread' : ''; ?>" data-notif-id="<?php echo $notif['notification_id']; ?>">
                      <span class="notif-item-icon"><?php echo $iconSvg; ?></span>
                      <div class="notif-item-info">
                        <p class="notif-item-title"><?php echo htmlspecialchars($notif['title'] ?? 'Notification'); ?></p>
                        <p class="notif-item-desc"><?php echo htmlspecialchars($notif['message'] ?? ''); ?></p>
                        <span class="notif-item-time"><?php echo $timeStr; ?></span>
                      </div>
                    </a>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="notif-dropdown-empty" style="padding: 1.5rem 1rem; text-align: center; color: var(--muted); font-size: 0.8125rem;">
                    <div style="display: flex; justify-content: center; margin-bottom: 0.35rem; color: var(--muted-2);">
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                    </div>
                    No notifications yet
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Circular Profile Avatar Badge -->
          <a href="profile.php" id="navAvatarBtn" class="nav-avatar-btn <?php echo $is_profile ? 'active' : ''; ?>" title="My Profile (<?php echo htmlspecialchars($user['name'] ?? 'User'); ?>)">
            <span class="avatar-initial"><?php echo htmlspecialchars($user_initial); ?></span>
          </a>

          <!-- Logout Link -->
          <a href="logout.php" class="nav-logout-link" title="Sign out">Logout</a>

          <!-- Mobile Hamburger Toggle -->
          <button type="button" class="nav-hamburger-btn" id="navHamburgerBtn" aria-label="Toggle navigation menu" aria-expanded="false">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
          </button>
        <?php else: ?>
          <a href="login.php" class="btn-ghost" style="text-decoration: none;">Login</a>
          <a href="signup.php" class="btn-nav-primary" style="text-decoration: none;">Register</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!$is_auth_page && $logged_in): ?>
    <!-- Mobile Navigation Drawer -->
    <div class="mobile-nav-drawer" id="mobileNavDrawer" aria-hidden="true">
      <div class="mobile-nav-links">
        <a href="profile.php" class="mobile-nav-link <?php echo $is_profile ? 'active-mobile' : ''; ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <span>My Profile (<?php echo htmlspecialchars($user['name'] ?? 'Student'); ?>)</span>
        </a>
        <a href="search.php" class="mobile-nav-link <?php echo $is_search ? 'active-mobile' : ''; ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          <span>Search Items</span>
        </a>
        <a href="home.php#board" id="mobNavLinkBoard" class="mobile-nav-link <?php echo ($is_home && empty($_GET['tab'])) ? 'active-mobile' : ''; ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
          <span>Community Board</span>
        </a>
        <a href="home.php#how" id="mobNavLinkHow" class="mobile-nav-link">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
          <span>How It Works</span>
        </a>
        <div class="mobile-nav-divider"></div>
        <a href="logout.php" class="mobile-nav-link action-logout">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          <span>Logout</span>
        </a>
      </div>
    </div>
    <script>
      (function() {
        const btn = document.getElementById('navHamburgerBtn');
        const drawer = document.getElementById('mobileNavDrawer');
        const notifBtn = document.getElementById('navNotifBtn');
        const notifDropdown = document.getElementById('navNotifDropdown');

        const navLinkBoard = document.getElementById('navLinkBoard');
        const navLinkHow = document.getElementById('navLinkHow');
        const mobNavLinkBoard = document.getElementById('mobNavLinkBoard');
        const mobNavLinkHow = document.getElementById('mobNavLinkHow');

        // Dynamic active state handler for home sections (#board vs #how)
        function updateHomeNavActive() {
          const isHome = window.location.pathname.endsWith('home.php') || window.location.pathname.endsWith('/home') || (window.location.pathname.endsWith('/') && document.getElementById('how'));
          if (!isHome) return;

          const hash = window.location.hash;
          if (hash === '#how') {
            if (navLinkBoard) navLinkBoard.classList.remove('active');
            if (navLinkHow) navLinkHow.classList.add('active');
            if (mobNavLinkBoard) mobNavLinkBoard.classList.remove('active-mobile');
            if (mobNavLinkHow) mobNavLinkHow.classList.add('active-mobile');
          } else {
            if (navLinkHow) navLinkHow.classList.remove('active');
            if (navLinkBoard) navLinkBoard.classList.add('active');
            if (mobNavLinkHow) mobNavLinkHow.classList.remove('active-mobile');
            if (mobNavLinkBoard) mobNavLinkBoard.classList.add('active-mobile');
          }
        }

        // Link click listeners for instantaneous feedback
        if (navLinkHow) {
          navLinkHow.addEventListener('click', function() {
            if (navLinkBoard) navLinkBoard.classList.remove('active');
            navLinkHow.classList.add('active');
            if (mobNavLinkBoard) mobNavLinkBoard.classList.remove('active-mobile');
            if (mobNavLinkHow) mobNavLinkHow.classList.add('active-mobile');
          });
        }
        if (navLinkBoard) {
          navLinkBoard.addEventListener('click', function() {
            if (navLinkHow) navLinkHow.classList.remove('active');
            navLinkBoard.classList.add('active');
            if (mobNavLinkHow) mobNavLinkHow.classList.remove('active-mobile');
            if (mobNavLinkBoard) mobNavLinkBoard.classList.add('active-mobile');
          });
        }

        window.addEventListener('hashchange', updateHomeNavActive);
        window.addEventListener('DOMContentLoaded', updateHomeNavActive);
        updateHomeNavActive();

        // Scroll-spy observer for home page sections
        if (typeof IntersectionObserver !== 'undefined') {
          const howSec = document.getElementById('how');
          const boardSec = document.getElementById('board');
          if (howSec && boardSec) {
            const observer = new IntersectionObserver((entries) => {
              entries.forEach(entry => {
                if (entry.isIntersecting) {
                  if (entry.target.id === 'how') {
                    if (navLinkBoard) navLinkBoard.classList.remove('active');
                    if (navLinkHow) navLinkHow.classList.add('active');
                  } else if (entry.target.id === 'board' && window.scrollY < (howSec.offsetTop - 200)) {
                    if (navLinkHow) navLinkHow.classList.remove('active');
                    if (navLinkBoard) navLinkBoard.classList.add('active');
                  }
                }
              });
            }, { rootMargin: '-20% 0px -60% 0px', threshold: 0.1 });

            observer.observe(howSec);
            observer.observe(boardSec);
          }
        }

        // Hamburger drawer
        if (btn && drawer) {
          btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (notifDropdown) notifDropdown.classList.remove('open');
            const isOpen = drawer.classList.toggle('open');
            btn.classList.toggle('active', isOpen);
            btn.setAttribute('aria-expanded', isOpen);
            drawer.setAttribute('aria-hidden', !isOpen);
          });
          document.addEventListener('click', function(e) {
            if (!drawer.contains(e.target) && !btn.contains(e.target) && drawer.classList.contains('open')) {
              drawer.classList.remove('open');
              btn.classList.remove('active');
              btn.setAttribute('aria-expanded', 'false');
              drawer.setAttribute('aria-hidden', 'true');
            }
          });
          drawer.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
              drawer.classList.remove('open');
              btn.classList.remove('active');
              btn.setAttribute('aria-expanded', 'false');
              drawer.setAttribute('aria-hidden', 'true');
            });
          });
        }
      })();
    </script>
    <script src="js/notifications.js"></script>
  <?php endif; ?>

  <script>
    (function() {
      // Global Theme Toggle Functionality
      const themeBtn = document.getElementById('themeToggleBtn');
      if (themeBtn) {
        themeBtn.addEventListener('click', function() {
          const currentTheme = document.documentElement.getAttribute('data-theme');
          const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
          
          if (newTheme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('reunite_theme', 'dark');
          } else {
            document.documentElement.removeAttribute('data-theme');
            localStorage.setItem('reunite_theme', 'light');
          }
        });
      }
    })();
  </script>
</nav>
