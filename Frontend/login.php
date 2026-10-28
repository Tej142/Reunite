<?php
require_once __DIR__ . '/includes/auth.php';

// If already authenticated as admin, go straight to Command Center
if (is_admin()) {
    header("Location: admin/index.php");
    exit();
} elseif (is_logged_in()) {
    header("Location: home.php");
    exit();
}

$initialRole = strtolower($_GET['role'] ?? ($_GET['tab'] ?? ''));
$isCampusAdminInitial = ($initialRole === 'campus_admin' || $initialRole === 'admin' || isset($_GET['admin']));
$isLoggedOut = isset($_GET['logged_out']) && $_GET['logged_out'] == '1';
$errorParam = htmlspecialchars($_GET['error'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title id="pageTitle"><?php echo $isCampusAdminInitial ? 'Campus Admin Login — Reunite' : 'Student Login — Reunite'; ?></title>
  <script>
    (function(){
      var t = localStorage.getItem('reunite_theme');
      if (t === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    })();
  </script>
  <link rel="stylesheet" href="css/variables.css?v=<?php echo time(); ?>" />
  <link rel="stylesheet" href="css/login.css?v=<?php echo time(); ?>" />
  <?php include __DIR__ . '/includes/head_icons.php'; ?>
</head>
<body>

<?php include 'components/nav.php'; ?>

<div class="login-container">
  <div class="login-card">

    <!-- Segmented Role Selector -->
    <div class="login-role-tabs" role="tablist" aria-label="Sign in mode">
      <button type="button" class="role-tab <?php echo !$isCampusAdminInitial ? 'active' : ''; ?>" id="tabStudent" role="tab" aria-selected="<?php echo !$isCampusAdminInitial ? 'true' : 'false'; ?>" data-role="student">
        <svg class="tab-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
          <path d="M6 12v5c3 3 9 3 12 0v-5"/>
        </svg>
        <span>Student</span>
      </button>
      <button type="button" class="role-tab <?php echo $isCampusAdminInitial ? 'active' : ''; ?>" id="tabCampusAdmin" role="tab" aria-selected="<?php echo $isCampusAdminInitial ? 'true' : 'false'; ?>" data-role="campus_admin">
        <svg class="tab-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          <path d="M9 12l2 2 4-4"/>
        </svg>
        <span>Campus Admin</span>
      </button>
    </div>

    <div class="login-header">
      <h1 class="serif" id="loginHeaderTitle">
        <?php echo $isCampusAdminInitial ? 'Campus Admin <em>Portal</em>' : 'Welcome Back to <em>Reunite</em>'; ?>
      </h1>
      <p id="loginHeaderSubtitle">
        <?php echo $isCampusAdminInitial ? 'Sign in with your campus administrator credentials to manage your institution.' : 'Log in with your college credentials to search for and trace your belongings.'; ?>
      </p>
    </div>

    <!-- Campus Admin Notice Banner -->
    <div class="campus-admin-badge" id="campusAdminBadge" style="<?php echo $isCampusAdminInitial ? 'display:flex;' : 'display:none;'; ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        <path d="M12 8v4"/>
        <path d="M12 16h.01"/>
      </svg>
      <span>Campus Staff &amp; Administrator Access</span>
    </div>

    <form id="loginForm" novalidate>
      <input type="hidden" id="loginRole" name="role" value="<?php echo $isCampusAdminInitial ? 'campus_admin' : 'student'; ?>">

      <!-- Top Dynamic Alert (Above College PIN / Username) -->
      <?php if ($isLoggedOut): ?>
      <div class="login-top-alert show success" id="loginTopAlert" role="alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="alert-icon">
          <circle cx="12" cy="12" r="10"></circle>
          <path d="M9 12l2 2 4-4"></path>
        </svg>
        <span id="loginTopAlertText">You have been signed out successfully.</span>
      </div>
      <?php elseif (!empty($errorParam)): ?>
      <div class="login-top-alert show" id="loginTopAlert" role="alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="alert-icon">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span id="loginTopAlertText"><?php echo $errorParam; ?></span>
      </div>
      <?php else: ?>
      <div class="login-top-alert" id="loginTopAlert" role="alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="alert-icon">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span id="loginTopAlertText"></span>
      </div>
      <?php endif; ?>

      <!-- College PIN or Admin Identifier -->
      <div class="field">
        <label for="pin" id="pinLabel"><?php echo $isCampusAdminInitial ? 'Admin Username or Email' : 'College PIN or Email'; ?></label>
        <?php $rememberedIdentifier = htmlspecialchars($_GET['pin'] ?? $_GET['email'] ?? ($_COOKIE['reunite_remembered_pin'] ?? '')); ?>
        <input type="text" id="pin" name="identifier" placeholder="<?php echo $isCampusAdminInitial ? 'e.g. ramesh_svgp or admin@campus.edu' : 'e.g. 24155-cm-002 or name@example.com'; ?>" value="<?php echo $rememberedIdentifier; ?>" autocomplete="username" required <?php echo !empty($rememberedIdentifier) ? '' : 'autofocus'; ?> />
        <span class="error-msg" id="pinError"></span>
      </div>

      <!-- Password -->
      <div class="field">
        <div class="field-label-row">
          <label for="password">Password</label>
          <a href="forgot-password.php" class="forgot-password-link" id="forgotPasswordLink" style="<?php echo $isCampusAdminInitial ? 'display:none;' : ''; ?>">Forgot password?</a>
          <span class="campus-admin-pwd-note" id="campusAdminPwdNote" style="<?php echo $isCampusAdminInitial ? 'display:inline;' : 'display:none;'; ?>">Managed by Master Admin</span>
        </div>
        <div class="password-wrapper">
          <input type="password" id="password" placeholder="Enter your password" required autocomplete="current-password" />
          <button type="button" class="toggle-password-btn" id="togglePassword" aria-label="Toggle password visibility">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
              <circle cx="12" cy="12" r="3" fill="white"/>
            </svg>
          </button>
        </div>
        <span class="error-msg" id="passwordError"></span>
      </div>

      <div class="form-footer">
        <button type="submit" class="btn-submit" id="submitBtn">
          <?php echo $isCampusAdminInitial ? 'Sign in as Campus Admin' : 'Sign in'; ?>
        </button>
        <p class="signup-prompt" id="signupPrompt" style="<?php echo $isCampusAdminInitial ? 'display:none;' : ''; ?>">
          Don't have an account?<a href="signup.php">Register</a>
        </p>
        <p class="campus-admin-prompt" id="campusAdminPrompt" style="<?php echo $isCampusAdminInitial ? 'display:block;' : 'display:none;'; ?>">
          Need a campus admin account? <span class="muted-text">Contact your institution's Master Administrator.</span>
        </p>
      </div>

      <!-- Quick Role Switcher Helper -->
      <div class="role-switch-helper" id="roleSwitchHelper">
        <span id="roleSwitchPromptText"><?php echo $isCampusAdminInitial ? 'Student at this college?' : 'Campus staff or administrator?'; ?></span>
        <a href="javascript:void(0)" id="roleSwitchLink"><?php echo $isCampusAdminInitial ? 'Switch to Student Login &larr;' : 'Log in as Campus Admin &rarr;'; ?></a>
      </div>
    </form>

    <div class="success" id="successState">
      <div class="success-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
        </svg>
      </div>
      <h2 class="serif" id="successTitle">Successfully logged in!</h2>
      <p id="successSubtitle">Redirecting you to the portal...</p>
      <a href="home.php" class="btn-home" id="successBtnHome">Go to Homepage</a>
    </div>
  </div>
</div>

<footer>
  <div class="footer-inner">
    <span>&copy; <?php echo date('Y'); ?> Reunite &middot; A community-powered lost &amp; found platform.</span>
  </div>
</footer>

<script src="js/login.js?v=<?php echo time(); ?>"></script>
</body>
</html>
