<?php
require_once __DIR__ . '/../includes/auth.php';
init_session();

// If already authenticated as admin, redirect to Admin Command Center
if (is_admin()) {
    header("Location: index.php");
    exit();
}

$errorMsg = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reunite Platform — Administrative Control Center</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    :root {
      --bg: #12100E;
      --card-bg: #1B1714;
      --surface: #221D18;
      --border: rgba(255, 255, 255, 0.08);
      --border-focus: #E06D38;
      --accent: #E06D38;
      --accent-hover: #C45524;
      --accent-glow: rgba(224, 109, 56, 0.25);
      --ink: #F7F3EE;
      --muted: #A89D94;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      background: var(--bg);
      color: var(--ink);
      font-family: 'Inter', sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      position: relative;
      overflow-x: hidden;
    }

    /* Ambient Background Glow */
    body::before {
      content: '';
      position: absolute;
      width: 600px;
      height: 600px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(224, 109, 56, 0.12) 0%, transparent 70%);
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      pointer-events: none;
    }

    .admin-login-card {
      width: 100%;
      max-width: 400px;
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 32px 28px;
      position: relative;
      z-index: 2;
    }

    .admin-header {
      text-align: center;
      margin-bottom: 24px;
    }

    .admin-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 10px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 6px;
      color: var(--accent);
      font-size: 0.72rem;
      font-weight: 600;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      margin-bottom: 14px;
    }

    .brand-logo-wrap {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 6px;
    }

    .brand-logo-wrap svg {
      width: 30px;
      height: 30px;
      border-radius: 8px;
    }

    .brand-title {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 1.45rem;
      font-weight: 700;
      letter-spacing: -0.02em;
      color: var(--ink);
    }

    .admin-sub {
      color: var(--muted);
      font-size: 0.82rem;
      line-height: 1.4;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-label {
      display: block;
      font-size: 0.82rem;
      font-weight: 600;
      margin-bottom: 8px;
      color: var(--ink);
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .input-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }

    .input-wrap i.prefix-icon {
      position: absolute;
      left: 16px;
      color: var(--muted);
      font-size: 0.95rem;
    }

    .form-input {
      width: 100%;
      padding: 14px 16px 14px 44px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 14px;
      color: var(--ink);
      font-size: 0.95rem;
      font-family: inherit;
      transition: all 0.25s ease;
      outline: none;
    }

    .form-input:focus {
      border-color: var(--border-focus);
      box-shadow: 0 0 0 3px var(--accent-glow);
      background: #28221D;
    }

    .pwd-toggle {
      position: absolute;
      right: 14px;
      background: none;
      border: none;
      color: var(--muted);
      cursor: pointer;
      font-size: 0.9rem;
      padding: 4px;
    }

    .pwd-toggle:hover { color: var(--ink); }

    .btn-admin-login {
      width: 100%;
      padding: 14px;
      border-radius: 14px;
      background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
      color: #FFFFFF;
      font-weight: 600;
      font-size: 1rem;
      border: none;
      cursor: pointer;
      box-shadow: 0 6px 20px rgba(224, 109, 56, 0.35);
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-top: 10px;
    }

    .btn-admin-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 26px rgba(224, 109, 56, 0.5);
    }

    .btn-admin-login:active { transform: translateY(0); }

    .btn-admin-login:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    .alert-banner {
      padding: 12px 16px;
      border-radius: 12px;
      font-size: 0.86rem;
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 20px;
    }

    .alert-error {
      background: rgba(239, 68, 68, 0.12);
      border: 1px solid rgba(239, 68, 68, 0.3);
      color: #F87171;
    }

    .security-notice {
      margin-top: 28px;
      padding-top: 20px;
      border-top: 1px solid var(--border);
      text-align: center;
      font-size: 0.76rem;
      color: var(--muted);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }

    .spinner-sm {
      width: 18px;
      height: 18px;
      border: 2px solid rgba(255, 255, 255, 0.3);
      border-top-color: #FFFFFF;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
      display: none;
    }

    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>

  <div class="admin-login-card">
    <div class="admin-header">
      <div class="admin-badge">
        <i class="fa-solid fa-shield-halved"></i> Management Console
      </div>
      <div class="brand-logo-wrap">
        <svg width="36" height="36" viewBox="0 0 28 28" fill="none" aria-hidden="true">
          <rect width="28" height="28" rx="8" fill="#E06D38"/>
          <path d="M 14 6 Q 14 14 20.5 14 Q 14 14 14 22 Q 14 14 7.5 14 Q 14 14 14 6 Z" fill="#FDFBF7"/>
        </svg>
        <span class="brand-title">Reunite</span>
      </div>
      <p class="admin-sub">Platform Administration &amp; Intelligence Hub</p>
    </div>

    <!-- Error Banner -->
    <div id="errorBanner" class="alert-banner alert-error" style="<?php echo empty($errorMsg) ? 'display: none;' : ''; ?>">
      <i class="fa-solid fa-circle-exclamation"></i>
      <span id="errorText"><?php echo htmlspecialchars($errorMsg); ?></span>
    </div>

    <!-- Login Form -->
    <form id="adminLoginForm">
      <div class="form-group">
        <label for="adminId" class="form-label">Admin Identifier / Email</label>
        <div class="input-wrap">
          <i class="fa-solid fa-user-shield prefix-icon"></i>
          <input type="text" id="adminId" name="identifier" class="form-input" placeholder="admin" required autocomplete="username" autofocus />
        </div>
      </div>

      <div class="form-group">
        <label for="adminPass" class="form-label">Master Password</label>
        <div class="input-wrap">
          <i class="fa-solid fa-lock prefix-icon"></i>
          <input type="password" id="adminPass" name="password" class="form-input" placeholder="••••••••••••" required autocomplete="current-password" />
          <button type="button" class="pwd-toggle" id="pwdToggle" aria-label="Toggle password visibility">
            <i class="fa-regular fa-eye" id="eyeIcon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-admin-login" id="submitBtn">
        <span id="btnText">Authenticate</span>
        <div class="spinner-sm" id="btnSpinner"></div>
      </button>
    </form>

    <div class="security-notice">
      <i class="fa-solid fa-lock"></i> Restricted Area &bull; All administrative access attempts are audited.
    </div>
  </div>

  <script>
    // Password toggle
    const pwdInput = document.getElementById('adminPass');
    const pwdToggle = document.getElementById('pwdToggle');
    const eyeIcon = document.getElementById('eyeIcon');

    pwdToggle.addEventListener('click', () => {
      const isPass = (pwdInput.type === 'password');
      pwdInput.type = isPass ? 'text' : 'password';
      eyeIcon.className = isPass ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
    });

    // Submit as a direct form POST — most reliable for shared hosting session persistence
    const form = document.getElementById('adminLoginForm');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');
    const errorBanner = document.getElementById('errorBanner');
    const errorText = document.getElementById('errorText');

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      errorBanner.style.display = 'none';

      const identifier = document.getElementById('adminId').value.trim();
      const password = document.getElementById('adminPass').value;

      if (!identifier || !password) {
        errorText.textContent = 'Please enter both identifier and password.';
        errorBanner.style.display = 'flex';
        return;
      }

      submitBtn.disabled = true;
      btnText.style.display = 'none';
      btnSpinner.style.display = 'block';

      // Build and submit a traditional HTML form POST for maximum session reliability
      const postForm = document.createElement('form');
      postForm.method = 'POST';
      postForm.action = '../../Backend/admin_auth.php';

      const idField = document.createElement('input');
      idField.type = 'hidden';
      idField.name = 'identifier';
      idField.value = identifier;
      postForm.appendChild(idField);

      const passField = document.createElement('input');
      passField.type = 'hidden';
      passField.name = 'password';
      passField.value = password;
      postForm.appendChild(passField);

      document.body.appendChild(postForm);
      postForm.submit();
    });
  </script>

</body>
</html>
