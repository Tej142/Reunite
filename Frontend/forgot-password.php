<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Forgot Password — Reunite</title>
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
    <div class="login-header">
      <h1 class="serif">Reset Your <em>Password</em></h1>
      <p>Enter your College PIN or registered email address to receive a secure reset link.</p>
    </div>

    <form id="forgotForm" novalidate>
      <!-- Top Dynamic Alert -->
      <div class="login-top-alert" id="forgotTopAlert" role="alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="alert-icon">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span id="forgotTopAlertText"></span>
      </div>

      <!-- Identifier (PIN or Email) -->
      <div class="field">
        <label for="identifier">College PIN or Email</label>
        <input type="text" id="identifier" placeholder="e.g. 24155-cm-002 or alex@college.edu" required autofocus />
        <span class="error-msg" id="identifierError"></span>
      </div>

      <div class="form-footer">
        <button type="submit" class="btn-submit" id="submitBtn">Send Reset Link</button>
        <p class="signup-prompt">Remember your password?<a href="login.php">Sign in</a></p>
      </div>
    </form>

    <!-- Success State -->
    <div class="success" id="successState">
      <div class="success-icon" style="background: rgba(196, 98, 45, 0.15); border-color: var(--accent);">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="4" width="20" height="16" rx="2"></rect>
          <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
        </svg>
      </div>
      <h2 class="serif">Reset Link Sent!</h2>
      <p id="successDesc">A secure password reset link has been dispatched to your email address. It will expire in <strong>10 minutes</strong>.</p>
      <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
        <a href="login.php" class="btn-home">Return to Login</a>
        <button type="button" class="btn-resend-inline" id="btnResendLink">Resend Link</button>
      </div>
    </div>
  </div>
</div>

<footer>
  <div class="footer-inner">
    <span>&copy; 2024 Reunite &middot; A community-powered lost &amp; found platform.</span>
  </div>
</footer>

<script src="js/forgot-password.js?v=<?php echo time(); ?>"></script>
</body>
</html>
