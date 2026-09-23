document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('signupForm');
  const submitBtn = document.getElementById('submitBtn');
  const successState = document.getElementById('successState');

  // Email Verification UI Elements
  const emailVerifiedBadge = document.getElementById('emailVerifiedBadge');
  const btnSendOtpInline = document.getElementById('btnSendOtpInline');
  const otpSection = document.getElementById('otpSection');
  const otpCode = document.getElementById('otpCode');
  const btnVerifyOtp = document.getElementById('btnVerifyOtp');
  const btnResendOtp = document.getElementById('btnResendOtp');
  const otpTimerText = document.getElementById('otpTimerText');
  const otpTargetEmail = document.getElementById('otpTargetEmail');
  const otpError = document.getElementById('otpError');
  const passwordFieldsWrapper = document.getElementById('passwordFieldsWrapper');

  // State
  let isEmailVerified = false;
  let otpCountdownTimer = null;
  let resendCooldown = 0;

  // Input Elements
  const fields = {
    name: document.getElementById('name'),
    dob: document.getElementById('dob'),
    pin: document.getElementById('pin'),
    email: document.getElementById('email'),
    phone: document.getElementById('phone'),
    college: document.getElementById('college'),
    branch: document.getElementById('branch'),
    password: document.getElementById('password'),
    confirmPassword: document.getElementById('confirmPassword')
  };

  // Error Containers
  const errors = {
    name: document.getElementById('nameError'),
    dob: document.getElementById('dobError'),
    pin: document.getElementById('pinError'),
    email: document.getElementById('emailError'),
    phone: document.getElementById('phoneError'),
    college: document.getElementById('collegeError'),
    branch: document.getElementById('branchError'),
    password: document.getElementById('passwordError'),
    confirmPassword: document.getElementById('confirmPasswordError')
  };

  // ── Password Visibility Toggles ─────────────────────────
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
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5" />
          </svg>
        `;
      }
    });
  };

  setupPasswordToggle('togglePassword', 'password');
  setupPasswordToggle('toggleConfirmPassword', 'confirmPassword');

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
    if (errors[fieldKey]) {
      errors[fieldKey].textContent = message;
      errors[fieldKey].classList.add('show');
    }
    return false;
  }

  // ── Individual Validators ──────────────────────────────
  function validateName() {
    const val = fields.name ? fields.name.value.trim() : '';
    if (!val) {
      return setInvalid('name', 'Name is required.');
    } else if (val.length < 2) {
      return setInvalid('name', 'Name must be at least 2 characters long.');
    }
    clearFieldWarning('name');
    return true;
  }

  function validateDob() {
    const val = fields.dob ? fields.dob.value : '';
    if (!val) {
      return setInvalid('dob', 'Date of birth is required.');
    }

    const birthDate = new Date(val);
    const today = new Date();
    if (birthDate > today) {
      return setInvalid('dob', 'Date of birth cannot be in the future.');
    }

    // Minimum age check (15 years)
    let age = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
      age--;
    }

    if (age < 15) {
      return setInvalid('dob', 'You must be at least 15 years old to register.');
    }
    clearFieldWarning('dob');
    return true;
  }

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

  function validateEmail() {
    const val = fields.email ? fields.email.value.trim() : '';
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!val) {
      return setInvalid('email', 'Email address is required.');
    } else if (!emailRegex.test(val)) {
      return setInvalid('email', 'Please enter a valid email address.');
    }
    clearFieldWarning('email');
    return true;
  }

  function validatePhone() {
    const val = fields.phone ? fields.phone.value.trim() : '';
    const phoneRegex = /^[0-9+\-\s()]{10,15}$/;
    if (!val) {
      return setInvalid('phone', 'Phone number is required.');
    } else if (!phoneRegex.test(val) || val.replace(/\D/g, '').length < 10) {
      return setInvalid('phone', 'Please enter a valid phone number (at least 10 digits).');
    }
    clearFieldWarning('phone');
    return true;
  }

  function validateCollege() {
    const val = fields.college ? fields.college.value : '';
    if (!val) {
      return setInvalid('college', 'Please select your college.');
    }
    clearFieldWarning('college');
    return true;
  }

  function validateBranch() {
    const val = fields.branch ? fields.branch.value : '';
    if (!val) {
      return setInvalid('branch', 'Please select your academic branch.');
    }
    clearFieldWarning('branch');
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

  function validateConfirmPassword() {
    const val = fields.confirmPassword ? fields.confirmPassword.value : '';
    const passVal = fields.password ? fields.password.value : '';
    if (!val) {
      return setInvalid('confirmPassword', 'Please confirm your password.');
    } else if (val !== passVal) {
      return setInvalid('confirmPassword', 'Passwords do not match.');
    }
    clearFieldWarning('confirmPassword');
    return true;
  }

  // Clear warnings on input
  Object.keys(fields).forEach(key => {
    const el = fields[key];
    if (el) {
      el.addEventListener('input', () => clearFieldWarning(key));
    }
  });

  // ── Step 1: Send OTP ───────────────────────────────────
  async function handleSendOtp() {
    const isNameOk = validateName();
    const isDobOk = validateDob();
    const isPinOk = validatePin();
    const isEmailOk = validateEmail();
    const isPhoneOk = validatePhone();
    const isCollegeOk = validateCollege();
    const isBranchOk = validateBranch();

    if (!isNameOk || !isDobOk || !isPinOk || !isEmailOk || !isPhoneOk || !isCollegeOk || !isBranchOk) {
      return;
    }

    const emailVal = fields.email.value.trim();
    const nameVal = fields.name.value.trim();
    const pinVal = fields.pin.value.trim();

    if (btnSendOtpInline) {
      btnSendOtpInline.disabled = true;
      btnSendOtpInline.textContent = 'Sending...';
    }
    submitBtn.disabled = true;
    submitBtn.textContent = 'Sending Code...';

    try {
      const response = await fetch('api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'send_otp',
          email: emailVal,
          name: nameVal,
          pin: pinVal
        })
      });

      let data;
      const textResponse = await response.text();
      try {
        data = JSON.parse(textResponse);
      } catch (parseErr) {
        console.error('Server Raw Response:', textResponse);
        throw new Error(textResponse ? 'Server returned invalid response. Please check server logs.' : 'Empty response from server.');
      }

      if (data.success) {
        // Open OTP Section
        if (otpSection) {
          otpSection.style.display = 'block';
          if (otpTargetEmail) otpTargetEmail.textContent = emailVal;
          if (otpCode) {
            otpCode.value = data.dev_otp || '';
            otpCode.focus();
          }
        }
        if (otpError) {
          if (data.simulated && data.dev_otp) {
            otpError.style.color = 'var(--accent)';
            otpError.textContent = `Testing mode: verification code is ${data.dev_otp}`;
            otpError.classList.add('show');
          } else {
            otpError.classList.remove('show');
            otpError.textContent = '';
          }
        }

        startOtpCountdown(600); // 10 mins
        submitBtn.disabled = true;
        submitBtn.textContent = 'Enter 6-Digit Code Above';
        if (btnSendOtpInline) {
          btnSendOtpInline.textContent = 'Code Sent';
        }
      } else {
        if (btnSendOtpInline) {
          btnSendOtpInline.disabled = false;
          btnSendOtpInline.textContent = 'Send Code';
        }
        submitBtn.disabled = false;
        submitBtn.textContent = 'Verify Email & Continue';
        setInvalid('email', data.message || 'Failed to send verification code.');
        if (fields.email) fields.email.focus();
      }
    } catch (err) {
      console.error('Send OTP Error:', err);
      if (btnSendOtpInline) {
        btnSendOtpInline.disabled = false;
        btnSendOtpInline.textContent = 'Send Code';
      }
      submitBtn.disabled = false;
      submitBtn.textContent = 'Verify Email & Continue';
      setInvalid('email', err.message || 'Unable to reach the server. Please check your connection.');
    }
  }

  // ── Step 2: Verify OTP ─────────────────────────────────
  async function handleVerifyOtp() {
    const emailVal = fields.email.value.trim();
    const codeVal = otpCode ? otpCode.value.trim() : '';

    if (!codeVal || codeVal.length < 6) {
      if (otpError) {
        otpError.style.color = '';
        otpError.textContent = 'Please enter the full 6-digit verification code.';
        otpError.classList.add('show');
      }
      if (otpCode) otpCode.focus();
      return;
    }

    if (btnVerifyOtp) {
      btnVerifyOtp.disabled = true;
      btnVerifyOtp.textContent = 'Verifying...';
    }

    try {
      const response = await fetch('api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'verify_otp',
          email: emailVal,
          code: codeVal
        })
      });

      const data = await response.json();

      if (data.success) {
        isEmailVerified = true;
        clearInterval(otpCountdownTimer);

        // Hide OTP box smoothly
        if (otpSection) {
          otpSection.style.display = 'none';
        }

        // Show Green Verified Badge
        if (emailVerifiedBadge) {
          emailVerifiedBadge.style.display = 'inline-flex';
        }

        // Lock email field
        if (fields.email) {
          fields.email.readOnly = true;
          fields.email.classList.add('valid');
        }
        if (btnSendOtpInline) {
          btnSendOtpInline.style.display = 'none';
        }

        // Reveal Password Fields smoothly
        if (passwordFieldsWrapper) {
          passwordFieldsWrapper.style.display = 'block';
        }

        submitBtn.disabled = false;
        submitBtn.textContent = 'Create account';

        // Focus on password
        if (fields.password) {
          fields.password.focus();
        }
      } else {
        if (btnVerifyOtp) {
          btnVerifyOtp.disabled = false;
          btnVerifyOtp.textContent = 'Verify Code';
        }
        if (otpError) {
          otpError.style.color = '';
          otpError.textContent = data.message || 'Incorrect verification code. Please try again.';
          otpError.classList.add('show');
        }
      }
    } catch (err) {
      if (btnVerifyOtp) {
        btnVerifyOtp.disabled = false;
        btnVerifyOtp.textContent = 'Verify Code';
      }
      if (otpError) {
        otpError.style.color = '';
        otpError.textContent = 'Connection error. Please try again.';
        otpError.classList.add('show');
      }
    }
  }

  // ── OTP Timer & Resend Countdown ───────────────────────
  function startOtpCountdown(durationSeconds) {
    clearInterval(otpCountdownTimer);
    let timeLeft = durationSeconds;

    function updateDisplay() {
      const minutes = Math.floor(timeLeft / 60);
      const seconds = timeLeft % 60;
      if (otpTimerText) {
        otpTimerText.textContent = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
      }
      if (timeLeft <= 0) {
        clearInterval(otpCountdownTimer);
        if (otpTimerText) otpTimerText.textContent = 'Expired';
        if (btnResendOtp) btnResendOtp.disabled = false;
      }
      timeLeft--;
    }

    updateDisplay();
    otpCountdownTimer = setInterval(updateDisplay, 1000);
  }

  // ── Event Listeners ────────────────────────────────────
  if (btnSendOtpInline) {
    btnSendOtpInline.addEventListener('click', (e) => {
      e.preventDefault();
      handleSendOtp();
    });
  }

  if (btnVerifyOtp) {
    btnVerifyOtp.addEventListener('click', (e) => {
      e.preventDefault();
      handleVerifyOtp();
    });
  }

  if (otpCode) {
    otpCode.addEventListener('keyup', (e) => {
      if (e.key === 'Enter' || (otpCode.value.trim().length === 6 && !isNaN(otpCode.value.trim()))) {
        handleVerifyOtp();
      }
    });
    // Auto paste / clean non-digits
    otpCode.addEventListener('input', () => {
      otpCode.value = otpCode.value.replace(/\D/g, '').slice(0, 6);
    });
  }

  if (btnResendOtp) {
    btnResendOtp.addEventListener('click', (e) => {
      e.preventDefault();
      handleSendOtp();
    });
  }

  // ── Final Form Submission Handler ──────────────────────
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      // If email is not yet verified, send OTP instead
      if (!isEmailVerified) {
        handleSendOtp();
        return;
      }

      // If email is verified, validate passwords & create account
      const isPassValid = validatePassword();
      const isConfirmValid = validateConfirmPassword();

      if (!isPassValid || !isConfirmValid) {
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Creating account...';

      const collegeSelect = fields.college;
      const branchSelect = fields.branch;
      const collegeText = collegeSelect ? (collegeSelect.options[collegeSelect.selectedIndex]?.text || collegeSelect.value) : '';
      const branchCode = branchSelect ? branchSelect.value : 'cme';

      try {
        const response = await fetch('api/auth.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'signup',
            name: fields.name.value.trim(),
            dob: fields.dob.value,
            pin: fields.pin.value.trim(),
            email: fields.email.value.trim(),
            phone: fields.phone.value.trim(),
            college: collegeText,
            branch: branchCode,
            password: fields.password.value
          })
        });

        const data = await response.json();

        if (data && data.success) {
          form.classList.add('hide');
          form.style.display = 'none';
          if (successState) {
            successState.classList.add('show');
          }
          setTimeout(() => {
            window.location.href = data.redirect || 'home.php';
          }, 1000);
        } else {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Create account';
          if (data.message) {
            alert(data.message);
          }
        }
      } catch (err) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Create account';
        alert('Network error while creating account. Please try again.');
      }
    });
  }
});
