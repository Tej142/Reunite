document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('loginForm');
  const submitBtn = document.getElementById('submitBtn');
  const successState = document.getElementById('successState');
  const topAlert = document.getElementById('loginTopAlert');
  const topAlertText = document.getElementById('loginTopAlertText');

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
      // Trigger reflow to restart CSS animation
      void topAlert.offsetWidth;
      topAlert.classList.add('show');
    }
  }

  function hideTopAlert() {
    if (topAlert) {
      topAlert.classList.remove('show');
    }
  }

  // ── Check for Password Reset Success Params ─────────────
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('reset') === 'success') {
    showTopAlert('Password reset successful! Please sign in with your new password.', true);
    const pinParam = urlParams.get('pin');
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

      // Toggle SVG Icon (Eye vs Eye-slash)
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

  // ── Individual Validators ──────────────────────────────
  function validatePin() {
    const val = fields.pin ? fields.pin.value.trim() : '';
    const pinRegex = /^[a-zA-Z0-9-]{4,15}$/; // e.g. 24155-cm-002
    if (!val) {
      return setInvalid('pin', 'College PIN is required.');
    } else if (!pinRegex.test(val)) {
      return setInvalid('pin', 'PIN must be 4 to 15 characters, numbers, or hyphens (e.g. 24155-cm-002).');
    }
    clearFieldWarning('pin');
    return true;
  }

  function validatePassword() {
    const val = fields.password ? fields.password.value : '';
    if (!val) {
      return setInvalid('password', 'Password is required.');
    } else if (val.length < 8) {
      return setInvalid('password', 'Password must be at least 8 characters long.');
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

      // Validate all fields on submit
      const isPinValid = validatePin();
      const isPassValid = validatePassword();

      if (!isPinValid || !isPassValid) {
        return;
      }

      // Authenticate via API endpoint
      submitBtn.disabled = true;
      submitBtn.textContent = 'Signing in...';

      fetch('api/auth.php?action=login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          identifier: fields.pin.value.trim(),
          password: fields.password.value
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
          submitBtn.textContent = 'Sign in';
          showTopAlert(text ? 'Server Error: ' + text.substring(0, 100) : 'Empty response from server.');
          return;
        }

        if (data.success) {
          hideTopAlert();
          form.classList.add('hide');
          form.style.display = 'none';
          if (successState) {
            successState.classList.add('show');
          }
          setTimeout(() => {
            window.location.href = data.redirect || 'home.php';
          }, 800);
        } else {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Sign in';
          const errMsg = data.message || 'Invalid credentials. Please try again.';
          
          // Display dynamically above College PIN
          showTopAlert(errMsg);

          // Highlight the relevant field
          if (errMsg.toLowerCase().includes('pin') || errMsg.toLowerCase().includes('account')) {
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
        submitBtn.textContent = 'Sign in';
        showTopAlert(err.message || 'Unable to reach the server. Please check your connection.');
      });
    });
  }
});
