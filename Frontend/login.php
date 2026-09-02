<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Login — Reunite</title>
  <link rel="stylesheet" href="css/variables.css?v=<?php echo time(); ?>" />
  <link rel="stylesheet" href="css/login.css?v=<?php echo time(); ?>" />
</head>
<body>

<?php include 'components/nav.php'; ?>

<div class="login-container">
  <div class="login-card">
    <div class="login-header">
      <h1 class="serif">Welcome Back to <em>Reunite</em></h1>
      <p>Log in with your college credentials to search for and trace your belongings.</p>
    </div>

    <form id="loginForm" novalidate>
      <!-- College PIN -->
      <div class="field">
        <label for="pin">College PIN</label>
        <input type="text" id="pin" placeholder="e.g. 24155-cm-002" required />
        <span class="error-msg" id="pinError"></span>
      </div>

      <!-- Password -->
      <div class="field">
        <label for="password">Password</label>
        <div class="password-wrapper">
          <input type="password" id="password" placeholder="Enter your password" required />
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
        <button type="submit" class="btn-submit" id="submitBtn" disabled>Sign in</button>
        <p class="signup-prompt">Don't have an account?<a href="signup.php">Register</a></p>
      </div>
    </form>

    <div class="success" id="successState">
      <div class="success-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
        </svg>
      </div>
      <h2 class="serif">Successfully logged in!</h2>
      <p>Redirecting you to the homepage...</p>
      <a href="home.php" class="btn-home">Go to Homepage</a>
    </div>
  </div>
</div>

<footer>
  <div class="footer-inner">
    <span>&copy; 2024 Reunite &middot; A community-powered lost &amp; found platform.</span>
  </div>
</footer>

<script src="js/login.js?v=<?php echo time(); ?>"></script>
</body>
</html>
