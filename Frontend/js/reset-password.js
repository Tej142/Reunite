document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('resetPasswordForm');
  const submitBtn = document.getElementById('submitBtn');
  const successState = document.getElementById('successState');
  const topAlert = document.getElementById('resetTopAlert');
  const topAlertText = document.getElementById('resetTopAlertText');
  const tokenInput = document.getElementById('resetToken');
  const pinPrefillInput = document.getElementById('studentPinPrefill');

  const newPassInput = document.getElementById('newPassword');
  const newPassError = document.getElementById('newPasswordError');
  const confirmPassInput = document.getElementById('confirmPassword');
  const confirmPassError = document.getElementById('confirmPasswordError');

  // ── Password Visibility Toggles ─────────────────────────
  const setupToggle = (btnId, inputId) => {
    const btn = document.getElementById(btnId);
    const input = document.getElementById(inputId);
    if (!btn || !input) return;

    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const isPass = input.type === 'password';
      input.type = isPass ? 'text' : 'password';

      if (isPass) {
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

  setupToggle('toggleNewPassword', 'newPassword');
  setupToggle('toggleConfirmPassword', 'confirmPassword');

  function showTopAlert(msg) {
    if (topAlert && topAlertText) {
      topAlertText.textContent = msg;
      topAlert.classList.remove('show');
      void topAlert.offsetWidth;
      topAlert.classList.add('show');
    }
  }

  function hideTopAlert() {
    if (topAlert) topAlert.classList.remove('show');
  }

  function clearError(input, errorEl) {
    if (input) {
      input.classList.remove('invalid');
      input.classList.remove('valid');
    }
    if (errorEl) {
      errorEl.classList.remove('show');
      errorEl.textContent = '';
    }
  }

  function setError(input, errorEl, msg) {
    if (input) {
      input.classList.remove('valid');
      input.classList.add('invalid');
      input.focus();
    }
    if (errorEl) {
      errorEl.textContent = msg;
      errorEl.classList.add('show');
    }
    return false;
  }

  if (newPassInput) {
    newPassInput.addEventListener('input', () => {
      clearError(newPassInput, newPassError);
      hideTopAlert();
    });
  }
  if (confirmPassInput) {
    confirmPassInput.addEventListener('input', () => {
      clearError(confirmPassInput, confirmPassError);
      hideTopAlert();
    });
  }

  async function handleResetSubmit(e) {
    if (e) e.preventDefault();
    hideTopAlert();
    clearError(newPassInput, newPassError);
    clearError(confirmPassInput, confirmPassError);

    const token = tokenInput ? tokenInput.value.trim() : '';
    const newPass = newPassInput ? newPassInput.value : '';
    const confirmPass = confirmPassInput ? confirmPassInput.value : '';

    if (!token) {
      return showTopAlert('Missing reset token. Please request a new link.');
    }

    if (!newPass) {
      return setError(newPassInput, newPassError, 'Password is required.');
    }
    if (newPass.length < 8) {
      return setError(newPassInput, newPassError, 'Password must be at least 8 characters long.');
    }

    if (!confirmPass) {
      return setError(confirmPassInput, confirmPassError, 'Please confirm your new password.');
    }
    if (newPass !== confirmPass) {
      return setError(confirmPassInput, confirmPassError, 'Passwords do not match.');
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Saving Password...';

    try {
      const response = await fetch('api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'perform_reset_password',
          token: token,
          new_password: newPass,
          confirm_password: confirmPass
        })
      });

      let data;
      const text = await response.text();
      try {
        data = JSON.parse(text);
      } catch (err) {
        console.error('Reset password parse error:', text);
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save New Password';
        showTopAlert('Server error occurred while resetting password.');
        return;
      }

      if (data.success) {
        form.style.display = 'none';
        if (successState) {
          successState.classList.add('show');
        }
        const pin = pinPrefillInput ? pinPrefillInput.value : '';
        setTimeout(() => {
          window.location.href = 'login.php?reset=success' + (pin ? '&pin=' + encodeURIComponent(pin) : '');
        }, 1500);
      } else {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save New Password';
        showTopAlert(data.message || 'Failed to reset password.');
      }
    } catch (netErr) {
      console.error('Network Error:', netErr);
      submitBtn.disabled = false;
      submitBtn.textContent = 'Save New Password';
      showTopAlert('Unable to reach server. Please check your network.');
    }
  }

  if (form) {
    form.addEventListener('submit', handleResetSubmit);
  }
});
