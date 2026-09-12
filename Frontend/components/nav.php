<?php
require_once __DIR__ . '/../includes/auth.php';
$current_page = basename($_SERVER['PHP_SELF']);
$logged_in = is_logged_in();
$is_auth_page = in_array($current_page, ['login.php', 'signup.php']);
$user = get_current_user_data();
$user_initial = !empty($user['name']) ? strtoupper(substr(trim($user['name']), 0, 1)) : 'U';

$is_search = ($current_page === 'search.php');
$is_home = ($current_page === 'home.php');
$is_profile = ($current_page === 'profile.php');
?>
<script>
  // Global Flask AI Backend URL Configuration
  // Set this to your public cloud API URL (e.g. 'https://your-app.onrender.com') or tunnel URL when sharing
  window.FLASK_BACKEND_URL = window.FLASK_BACKEND_URL || 'https://reunite-ai-backend.onrender.com';
</script>
<nav>
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

        <?php if ($logged_in): ?>
          <!-- Notification Bell with Dropdown -->
          <div class="nav-notif-wrap">
            <button type="button" class="nav-notif-btn" id="navNotifBtn" aria-label="Notifications" title="Notifications">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
              </svg>
              <span class="notif-badge-dot"></span>
            </button>
            <div class="nav-notif-dropdown" id="navNotifDropdown">
              <div class="notif-dropdown-header">
                <span>Notifications</span>
                <span class="notif-count-badge">2 New</span>
              </div>
              <div class="notif-dropdown-list">
                <a href="search.php" class="notif-dropdown-item unread">
                  <span class="notif-item-icon">✨</span>
                  <div class="notif-item-info">
                    <p class="notif-item-title">New AI Match Detected!</p>
                    <p class="notif-item-desc">A Blue Leather Wallet was reported in Central Library.</p>
                    <span class="notif-item-time">10m ago</span>
                  </div>
                </a>
                <a href="search.php" class="notif-dropdown-item unread">
                  <span class="notif-item-icon">📱</span>
                  <div class="notif-item-info">
                    <p class="notif-item-title">Item Verified in Directory</p>
                    <p class="notif-item-desc">Your report for iPhone 13 has been indexed.</p>
                    <span class="notif-item-time">1h ago</span>
                  </div>
                </a>
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

        // Notification popover
        if (notifBtn && notifDropdown) {
          notifBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (drawer) {
              drawer.classList.remove('open');
              if (btn) btn.classList.remove('active');
            }
            notifDropdown.classList.toggle('open');
          });
          document.addEventListener('click', function(e) {
            if (!notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
              notifDropdown.classList.remove('open');
            }
          });
        }
      })();
    </script>
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
