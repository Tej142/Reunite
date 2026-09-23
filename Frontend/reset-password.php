<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../Backend/config/config.php';
require_once __DIR__ . '/../Backend/functions.php';

if (is_logged_in()) {
    logout_user();
}

$token = trim($_GET['token'] ?? '');
$tokenData = !empty($token) ? verifyPasswordResetToken($token) : null;
$isTokenValid = ($tokenData !== null);
$studentName = $tokenData['full_name'] ?? 'Student';
$studentPin = $tokenData['pin'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Set New Password — Reunite</title>
  <script>
    (function(){
      var t = localStorage.getItem('reunite_theme');
      if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    })();
  </script>
  <link rel="stylesheet" href="css/variables.css?v=<?php echo time(); ?>" />
  <link rel="stylesheet" href="css/login.css?v=<?php echo time(); ?>" />
</head>
<body>

<?php include 'components/nav.php'; ?>

<div class="login-container">
  <div class="login-card">
    <?php if (!$isTokenValid): ?>
      <!-- Invalid or Expired Token View -->
      <div class="login-header">
        <div class="success-icon" style="background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.4); margin-bottom: 1.25rem;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
        </div>
        <h1 class="serif" style="color: #EF4444;">Link Expired or Invalid</h1>
        <p style="margin-top: 0.5rem; line-height: 1.5;">
          This password reset link is invalid or has expired (the <strong>10-minute validity limit</strong> has been exceeded).
        </p>
      </div>
      <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1.5rem;">
        <a href="forgot-password.php" class="btn-submit" style="text-align: center; text-decoration: none;">Request a New Reset Link</a>
        <a href="login.php" class="btn-resend-inline" style="text-align: center;">Return to Sign In</a>
      </div>

    <?php else: ?>
      <!-- Valid Token: New Password Form -->
      <div class="login-header">
        <h1 class="serif">Set New <em>Password</em></h1>
        <p>Creating a new password for <strong><?php echo htmlspecialchars($studentName); ?></strong><?php echo !empty($studentPin) ? ' (' . htmlspecialchars($studentPin) . ')' : ''; ?>.</p>
      </div>

      <form id="resetPasswordForm" novalidate>
        <input type="hidden" id="resetToken" value="<?php echo htmlspecialchars($token); ?>" />
        <input type="hidden" id="studentPinPrefill" value="<?php echo htmlspecialchars($studentPin); ?>" />

        <!-- Dynamic Alert -->
        <div class="login-top-alert" id="resetTopAlert" role="alert">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="alert-icon">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <span id="resetTopAlertText"></span>
        </div>

        <!-- New Password -->
        <div class="field">
          <label for="newPassword">New Password (at least 8 characters)</label>
          <div class="password-wrapper">
            <input type="password" id="newPassword" placeholder="Create a strong password" required autofocus />
            <button type="button" class="toggle-password-btn" id="toggleNewPassword" aria-label="Toggle password visibility">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                <circle cx="12" cy="12" r="3" fill="white"/>
              </svg>
            </button>
          </div>
          <span class="error-msg" id="newPasswordError"></span>
        </div>

        <!-- Confirm Password -->
        <div class="field">
          <label for="confirmPassword">Confirm New Password</label>
          <div class="password-wrapper">
            <input type="password" id="confirmPassword" placeholder="Re-enter your password" required />
            <button type="button" class="toggle-password-btn" id="toggleConfirmPassword" aria-label="Toggle password visibility">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                <circle cx="12" cy="12" r="3" fill="white"/>
              </svg>
            </button>
          </div>
          <span class="error-msg" id="confirmPasswordError"></span>
        </div>

        <div class="form-footer">
          <button type="submit" class="btn-submit" id="submitBtn">Save New Password</button>
          <p class="signup-prompt">Cancel and return to<a href="login.php">Sign in</a></p>
        </div>
      </form>

      <!-- Success State -->
      <div class="success" id="successState">
        <div class="success-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
          </svg>
        </div>
        <h2 class="serif">Password Reset Successfully!</h2>
        <p>Your password has been updated in the database. Redirecting you to the sign in page...</p>
        <a href="login.php" class="btn-home">Sign In Now</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<footer>
  <div class="footer-inner">
    <span>&copy; 2024 Reunite &middot; A community-powered lost &amp; found platform.</span>
  </div>
</footer>

<script src="js/reset-password.js?v=<?php echo time(); ?>"></script>
</body>
</html>
