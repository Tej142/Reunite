<?php
require_once __DIR__ . '/../includes/auth.php';
$current_page = basename($_SERVER['PHP_SELF']);
$logged_in = is_logged_in();
?>
<script>
  // Global Flask AI Backend URL Configuration
  // Set this to your public cloud API URL (e.g. 'https://your-app.onrender.com') or tunnel URL when sharing
  window.FLASK_BACKEND_URL = window.FLASK_BACKEND_URL || 'http://127.0.0.1:5000';
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

    <div class="nav-links">
      <?php if ($logged_in): ?>
        <a href="home.php#board">Community board</a>
        <a href="home.php#how">How it works</a>
      <?php else: ?>
        <a href="index.php#highlights">How it works</a>
        <a href="login.php">Login</a>
        <a href="signup.php">Register</a>
      <?php endif; ?>
    </div>

    <div class="nav-actions" style="display: flex; gap: 0.65rem; align-items: center;">
      <?php if ($logged_in): ?>
        <a href="logout.php" class="btn-ghost" style="text-decoration: none; padding: 0.4rem 0.75rem; font-size: 0.85rem;">Logout</a>
      <?php else: ?>
        <a href="login.php" class="btn-ghost" style="text-decoration: none;">Login</a>
        <a href="signup.php" class="btn-nav-primary" style="text-decoration: none;">Register</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
