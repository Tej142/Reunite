document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('loginForm');
  const submitBtn = document.getElementById('submitBtn');
  const successState = document.getElementById('successState');
  const topAlert = document.getElementById('loginTopAlert');
  const topAlertText = document.getElementById('loginTopAlertText');

  // Role Switcher Elements
  const tabStudent = document.getElementById('tabStudent');
  const tabCampusAdmin = document.getElementById('tabCampusAdmin');
  const loginRoleInput = document.getElementById('loginRole');
  const pageTitle = document.getElementById('pageTitle');
  const loginHeaderTitle = document.getElementById('loginHeaderTitle');
  const loginHeaderSubtitle = document.getElementById('loginHeaderSubtitle');
  const campusAdminBadge = document.getElementById('campusAdminBadge');
  const pinLabel = document.getElementById('pinLabel');
  const forgotPasswordLink = document.getElementById('forgotPasswordLink');
  const campusAdminPwdNote = document.getElementById('campusAdminPwdNote');
  const signupPrompt = document.getElementById('signupPrompt');
  const campusAdminPrompt = document.getElementById('campusAdminPrompt');
  const roleSwitchPromptText = document.getElementById('roleSwitchPromptText');
  const roleSwitchLink = document.getElementById('roleSwitchLink');
  const successTitle = document.getElementById('successTitle');
  const successSubtitle = document.getElementById('successSubtitle');
  const successBtnHome = document.getElementById('successBtnHome');

  // Input Elements
  const fields = {
    pin: document.getElementById('pin'),
    password: document.getElementById('password')
  };

  // Error Containers
  const errors = {
    pin: document.getElementById('pinError'),
    password: document.getElementById('passwordError')
  };

  // ── Top Dynamic Alert Helpers ───────────────────────────
  function showTopAlert(message, isSuccess = false) {
    if (topAlert && topAlertText) {
      topAlertText.textContent = message;
      topAlert.style.borderColor = isSuccess ? 'rgba(34, 197, 94, 0.4)' : 'rgba(239, 68, 68, 0.4)';
      topAlert.style.backgroundColor = isSuccess ? 'rgba(34, 197, 94, 0.1)' : 'rgba(239, 68, 68, 0.1)';
      topAlert.style.color = isSuccess ? '#22C55E' : '#EF4444';
      const icon = topAlert.querySelector('.alert-icon');
      if (icon) icon.style.stroke = isSuccess ? '#22C55E' : '#EF4444';
      topAlert.classList.remove('show');
      void topAlert.offsetWidth; // Trigger reflow
      topAlert.classList.add('show');
    }
  }

  function hideTopAlert() {
    if (topAlert) {
      topAlert.classList.remove('show');
    }
  }

  // ── Role Switching Engine ───────────────────────────────
  function getActiveRole() {
    return loginRoleInput ? loginRoleInput.value : 'student';
  }

  function setRole(role) {
    const isCampusAdmin = (role === 'campus_admin');
    const targetRole = isCampusAdmin ? 'campus_admin' : 'student';

    if (loginRoleInput) loginRoleInput.value = targetRole;

    // Clear active alerts and field warnings
    hideTopAlert();
    clearFieldWarning('pin');
    clearFieldWarning('password');

    // Update Tab Classes
    if (tabStudent) {
      tabStudent.classList.toggle('active', !isCampusAdmin);
      tabStudent.setAttribute('aria-selected', !isCampusAdmin ? 'true' : 'false');
    }
    if (tabCampusAdmin) {
      tabCampusAdmin.classList.toggle('active', isCampusAdmin);
      tabCampusAdmin.setAttribute('aria-selected', isCampusAdmin ? 'true' : 'false');
    }

    // Update Content & Headings
    if (pageTitle) {
      document.title = isCampusAdmin ? 'Campus Admin Login — Reunite' : 'Student Login — Reunite';
    }

    if (loginHeaderTitle) {
      loginHeaderTitle.innerHTML = isCampusAdmin
        ? 'Campus Admin <em>Portal</em>'
        : 'Welcome Back to <em>Reunite</em>';
    }

    if (loginHeaderSubtitle) {
      loginHeaderSubtitle.textContent = isCampusAdmin
        ? 'Sign in with your campus administrator credentials to manage your institution.'
        : 'Log in with your college credentials to search for and trace your belongings.';
    }

    if (campusAdminBadge) {
      campusAdminBadge.style.display = isCampusAdmin ? 'flex' : 'none';
    }

    if (pinLabel) {
      pinLabel.textContent = isCampusAdmin ? 'Admin Username or Email' : 'College PIN or Email';
    }

    if (fields.pin) {
      fields.pin.placeholder = isCampusAdmin
        ? 'e.g. ramesh_svgp or admin@campus.edu'
        : 'e.g. 24155-cm-002 or name@example.com';
      fields.pin.focus();
    }

    if (forgotPasswordLink) {
      forgotPasswordLink.style.display = isCampusAdmin ? 'none' : 'inline';
    }

    if (campusAdminPwdNote) {
      campusAdminPwdNote.style.display = isCampusAdmin ? 'inline' : 'none';
    }

    if (submitBtn) {
      submitBtn.textContent = isCampusAdmin ? 'Sign in as Campus Admin' : 'Sign in';
    }

    if (signupPrompt) {
      signupPrompt.style.display = isCampusAdmin ? 'none' : 'block';
    }

    if (campusAdminPrompt) {
      campusAdminPrompt.style.display = isCampusAdmin ? 'block' : 'none';
    }

    if (roleSwitchPromptText) {
      roleSwitchPromptText.textContent = isCampusAdmin ? 'Student at this college?' : 'Campus staff or administrator?';
    }

    if (roleSwitchLink) {
      roleSwitchLink.innerHTML = isCampusAdmin ? 'Switch to Student Login &larr;' : 'Log in as Campus Admin &rarr;';
    }

    // Update URL hash smoothly without page refresh
    if (history.replaceState) {
      const newUrl = isCampusAdmin
        ? window.location.pathname + '?role=campus_admin'
        : window.location.pathname;
      history.replaceState(null, '', newUrl);
    }
  }

  // Bind Tab Click Handlers
  if (tabStudent) {
    tabStudent.addEventListener('click', () => setRole('student'));
  }
  if (tabCampusAdmin) {
    tabCampusAdmin.addEventListener('click', () => setRole('campus_admin'));
  }
  if (roleSwitchLink) {
    roleSwitchLink.addEventListener('click', (e) => {
      e.preventDefault();
      setRole(getActiveRole() === 'student' ? 'campus_admin' : 'student');
    });
  }

  // ── URL Query & Hash Detection ──────────────────────────
  const urlParams = new URLSearchParams(window.location.search);
  const requestedRole = urlParams.get('role') || urlParams.get('tab');
  const isAdminParam = urlParams.get('admin') !== null || window.location.hash.includes('campus_admin') || window.location.hash.includes('admin');

  if (requestedRole === 'campus_admin' || requestedRole === 'admin' || isAdminParam) {
    setRole('campus_admin');
  }

  // ── Check for Password Reset Success Params ─────────────
  if (urlParams.get('reset') === 'success') {
    showTopAlert('Password reset successful! Please sign in with your new password.', true);
    const pinParam = urlParams.get('pin') || urlParams.get('email');
    if (pinParam && fields.pin) {
      fields.pin.value = pinParam;
      if (fields.password) fields.password.focus();
    }
  }

  // ── Password Visibility Toggle ─────────────────────────
  const setupPasswordToggle = (toggleBtnId, inputId) => {
    const btn = document.getElementById(toggleBtnId);
    const input = document.getElementById(inputId);
    if (!btn || !input) return;

    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';

      if (isPassword) {
        btn.innerHTML = `
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
          </svg>
        `;
      } else {
        btn.innerHTML = `
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
            <circle cx="12" cy="12" r="3" fill="white"/>
          </svg>
        `;
      }
    });
  };

  setupPasswordToggle('togglePassword', 'password');

  // ── Validation Helpers ─────────────────────────────────
  function clearFieldWarning(fieldKey) {
    if (fields[fieldKey]) {
      fields[fieldKey].classList.remove('invalid');
      fields[fieldKey].classList.remove('valid');
    }
    if (errors[fieldKey]) {
      errors[fieldKey].classList.remove('show');
      errors[fieldKey].textContent = '';
    }
  }

  function setInvalid(fieldKey, message) {
    if (fields[fieldKey]) {
      fields[fieldKey].classList.remove('valid');
      fields[fieldKey].classList.add('invalid');
    }
    if (errors[fieldKey] && message) {
      errors[fieldKey].textContent = message;
      errors[fieldKey].classList.add('show');
    }
    return false;
  }

  function validatePin() {
    const val = fields.pin ? fields.pin.value.trim() : '';
    const currentRole = getActiveRole();

    if (!val) {
      return setInvalid('pin', currentRole === 'campus_admin'
        ? 'Admin Username or Email is required.'
        : 'College PIN or Email is required.');
    }

    if (currentRole === 'campus_admin') {
      const adminUsernameRegex = /^[a-zA-Z0-9_\-\.]{3,50}$/;
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!adminUsernameRegex.test(val) && !emailRegex.test(val)) {
        return setInvalid('pin', 'Please enter a valid administrator username (e.g. ramesh_svgp) or email.');
      }
    } else {
      const pinRegex = /^[a-zA-Z0-9-]{4,20}$/; // e.g. 24155-cm-002
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!pinRegex.test(val) && !emailRegex.test(val)) {
        return setInvalid('pin', 'Please enter a valid College PIN (e.g. 24155-cm-002) or Email address.');
      }
    }

    clearFieldWarning('pin');
    return true;
  }

  function validatePassword() {
    const val = fields.password ? fields.password.value : '';
    if (!val) {
      return setInvalid('password', 'Password is required.');
    } else if (val.length < 4) {
      return setInvalid('password', 'Password must be at least 4 characters long.');
    }
    clearFieldWarning('password');
    return true;
  }

  // ── Clear warning as soon as user modifies/types in the field ──
  ['pin', 'password'].forEach(key => {
    const el = fields[key];
    if (el) {
      el.addEventListener('input', () => {
        clearFieldWarning(key);
        hideTopAlert();
      });
    }
  });

  // ── Form Submission Handler ────────────────────────────
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      hideTopAlert();

      const isPinValid = validatePin();
      const isPassValid = validatePassword();

      if (!isPinValid || !isPassValid) {
        return;
      }

      const activeRole = getActiveRole();

      submitBtn.disabled = true;
      submitBtn.textContent = (activeRole === 'campus_admin') ? 'Authenticating Admin...' : 'Signing in...';

      fetch('api/auth.php?action=login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          identifier: fields.pin.value.trim(),
          password: fields.password.value,
          role: activeRole
        })
      })
      .then(res => res.text())
      .then(text => {
        let data;
        try {
          data = JSON.parse(text);
        } catch(parseErr) {
          console.error('Login Raw Server Output:', text);
          submitBtn.disabled = false;
          submitBtn.textContent = (activeRole === 'campus_admin') ? 'Sign in as Campus Admin' : 'Sign in';
          showTopAlert(text ? 'Server Error: ' + text.substring(0, 100) : 'Empty response from server.');
          return;
        }

        if (data.success) {
          hideTopAlert();
          form.classList.add('hide');
          form.style.display = 'none';

          const isAdminLogin = (data.redirect && data.redirect.includes('admin')) || data.role === 'campus_admin' || data.role === 'master_admin';

          if (successState) {
            if (successTitle) {
              successTitle.textContent = isAdminLogin ? 'Admin Authenticated!' : 'Successfully logged in!';
            }
            if (successSubtitle) {
              successSubtitle.textContent = isAdminLogin
                ? 'Opening Campus Administrator Command Center...'
                : 'Redirecting you to the homepage...';
            }
            if (successBtnHome) {
              successBtnHome.textContent = isAdminLogin ? 'Go to Admin Portal' : 'Go to Homepage';
              successBtnHome.href = data.redirect || (isAdminLogin ? 'admin/index.php' : 'home.php');
            }

            let orbiWrap = successState.querySelector('.login-orbi-wrap');
            if (!orbiWrap) {
              orbiWrap = document.createElement('div');
              orbiWrap.className = 'login-orbi-wrap';
              const icon = successState.querySelector('.success-icon');
              if (icon) {
                icon.style.display = 'none';
                icon.insertAdjacentElement('afterend', orbiWrap);
              } else {
                successState.insertBefore(orbiWrap, successState.firstChild);
              }
            }
            if (window.Orbi) {
              window.Orbi.mount(orbiWrap, { state: 'happy', size: 68, label: isAdminLogin ? 'Admin Verified' : 'Welcome back' });
              const oEl = orbiWrap.querySelector('.orbi');
              if (oEl) window.Orbi.happy(oEl, 1200);
            }
            successState.classList.add('show');
          }

          setTimeout(() => {
            window.location.href = data.redirect || (isAdminLogin ? 'admin/index.php' : 'home.php');
          }, 850);
        } else {
          submitBtn.disabled = false;
          submitBtn.textContent = (activeRole === 'campus_admin') ? 'Sign in as Campus Admin' : 'Sign in';
          const errMsg = data.message || 'Invalid credentials. Please try again.';
          
          showTopAlert(errMsg);

          // Highlight the relevant field
          if (errMsg.toLowerCase().includes('pin') || errMsg.toLowerCase().includes('username') || errMsg.toLowerCase().includes('email') || errMsg.toLowerCase().includes('account')) {
            setInvalid('pin', '');
            if (fields.pin) fields.pin.focus();
          } else {
            setInvalid('password', '');
            if (fields.password) fields.password.focus();
          }
        }
      })
      .catch(err => {
        console.error('Login Network Error:', err);
        submitBtn.disabled = false;
        submitBtn.textContent = (activeRole === 'campus_admin') ? 'Sign in as Campus Admin' : 'Sign in';
        showTopAlert(err.message || 'Unable to reach the server. Please check your connection.');
      });
    });
  }
});
