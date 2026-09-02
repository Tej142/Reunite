<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Student Registration — Reunite</title>
<link rel="stylesheet" href="css/variables.css?v=<?php echo time(); ?>" />
<link rel="stylesheet" href="css/signup.css?v=<?php echo time(); ?>" />
</head>
<body>

<?php include 'components/nav.php'; ?>

<div class="signup-container">
  <div class="signup-card">
    <div class="signup-header">
      <h1 class="serif">Join <em>Reunite</em></h1>
      <p>Register as a student to trace and recover lost college belongings.</p>
    </div>

    <form id="signupForm" novalidate>
      <!-- Name -->
      <div class="field">
        <label for="name">Full Name</label>
        <input type="text" id="name" placeholder="e.g. John Doe" required />
        <span class="error-msg" id="nameError"></span>
      </div>

      <!-- Date of Birth & PIN -->
      <div class="field-group-2">
        <div class="field">
          <label for="dob">Date of Birth</label>
          <input type="date" id="dob" required />
          <span class="error-msg" id="dobError"></span>
        </div>
        <div class="field">
          <label for="pin">College PIN</label>
          <input type="text" id="pin" placeholder="e.g. 00000-AB-000" required />
          <span class="error-msg" id="pinError"></span>
        </div>
      </div>

      <!-- College Select -->
      <div class="field">
        <label for="college">College / University</label>
        <select id="college" required>
          <option value="" disabled selected>Select your college...</option>
          <option value="svgp">Sri Venkateswara Government polytechnic, Tirupati</option>
          <option value="plpt">Government polytechinc pillaripattu, Puttur.</option>
          <option value="vkpt">Venkata Perumal</option>
          <option value="berkeley">University of California, Berkeley</option>
          <option value="columbia">Columbia University</option>
          <option value="gatech">Georgia Institute of Technology (Georgia Tech)</option>
          <option value="cmu">Carnegie Mellon University (CMU)</option>
          <option value="other">Other Academic Institution</option>
        </select>
        <span class="error-msg" id="collegeError"></span>
      </div>

      <!-- Branch Select -->
      <div class="field">
        <label for="branch">Academic Branch / Department</label>
        <select id="branch" required>
          <option value="" disabled selected>Select your branch...</option>
          <option value="cs">Computer Science &amp; Engineering</option>
          <option value="ee">Electrical &amp; Electronics Engineering</option>
          <option value="me">Mechanical Engineering</option>
          <option value="ce">Civil Engineering</option>
          <option value="che">Chemical Engineering</option>
          <option value="ae">Aerospace Engineering</option>
          <option value="other">Other / General Studies</option>
        </select>
        <span class="error-msg" id="branchError"></span>
      </div>

      <!-- Password -->
      <div class="field">
        <label for="password">Password</label>
        <div class="password-wrapper">
          <input type="password" id="password" placeholder="Min. 8 characters" required />
          <button type="button" class="toggle-password-btn" id="togglePassword" aria-label="Toggle password visibility">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
              <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5" />
            </svg>
          </button>
        </div>
        <span class="error-msg" id="passwordError"></span>
      </div>

      <!-- Confirm Password -->
      <div class="field">
        <label for="confirmPassword">Confirm Password</label>
        <div class="password-wrapper">
          <input type="password" id="confirmPassword" placeholder="Re-enter password" required />
          <button type="button" class="toggle-password-btn" id="toggleConfirmPassword" aria-label="Toggle password visibility">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
              <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5" />
            </svg>
          </button>
        </div>
        <span class="error-msg" id="confirmPasswordError"></span>
      </div>

      <div class="form-footer">
        <button type="submit" class="btn-submit" id="submitBtn" disabled>Create account</button>
        <p class="signin-prompt">Already have an account?<a href="login.php">Signin</a></p>
      </div>
    </form>

    <div class="success" id="successState">
      <div class="success-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
        </svg>
      </div>
      <h2 class="serif">Account created!</h2>
      <p>Registration successful. You can now use your credentials to sign in and trace your missing belongings.</p>
      <a href="home.php" class="btn-home">Go to Homepage</a>
    </div>
  </div>
</div>

<footer>
  <div class="footer-inner">
    <span>&copy; 2024 Reunite &middot; A community-powered lost &amp; found platform.</span>
  </div>
</footer>

<script src="js/signup.js?v=<?php echo time(); ?>"></script>
</body>
</html>
