document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('signupForm');
  const submitBtn = document.getElementById('submitBtn');
  const successState = document.getElementById('successState');

  // Input Elements
  const fields = {
    name: document.getElementById('name'),
    dob: document.getElementById('dob'),
    pin: document.getElementById('pin'),
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
    college: document.getElementById('collegeError'),
    branch: document.getElementById('branchError'),
    password: document.getElementById('passwordError'),
    confirmPassword: document.getElementById('confirmPasswordError')
  };

  // State
  const validationState = {
    name: false,
    dob: false,
    pin: false,
    college: false,
    branch: false,
    password: false,
    confirmPassword: false
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
  function setValid(fieldKey) {
    fields[fieldKey].classList.remove('invalid');
    fields[fieldKey].classList.add('valid');
    errors[fieldKey].classList.remove('show');
    errors[fieldKey].textContent = '';
    validationState[fieldKey] = true;
    checkFormValidity();
  }

  function setInvalid(fieldKey, message) {
    fields[fieldKey].classList.remove('valid');
    fields[fieldKey].classList.add('invalid');
    errors[fieldKey].textContent = message;
    errors[fieldKey].classList.add('show');
    validationState[fieldKey] = false;
    checkFormValidity();
  }

  function checkFormValidity() {
    const allValid = Object.values(validationState).every(val => val === true);
    submitBtn.disabled = !allValid;
  }

  // ── Individual Validators ──────────────────────────────
  function validateName() {
    const val = fields.name.value.trim();
    if (!val) {
      setInvalid('name', 'Name is required.');
    } else if (val.length < 2) {
      setInvalid('name', 'Name must be at least 2 characters long.');
    } else {
      setValid('name');
    }
  }

  function validateDob() {
    const val = fields.dob.value;
    if (!val) {
      setInvalid('dob', 'Date of birth is required.');
      return;
    }

    const birthDate = new Date(val);
    const today = new Date();
    if (birthDate >= today) {
      setInvalid('dob', 'Date of birth must be in the past.');
      return;
    }

    // Minimum age check (e.g. 15 years old for college)
    let age = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
      age--;
    }

    if (age < 15) {
      setInvalid('dob', 'You must be at least 15 years old to register.');
    } else {
      setValid('dob');
    }
  }

  function validatePin() {
    const val = fields.pin.value.trim();
    const pinRegex = /^[a-zA-Z0-9-]{4,15}$/; // e.g. 24155-cm-002
    if (!val) {
      setInvalid('pin', 'College PIN is required.');
    } else if (!pinRegex.test(val)) {
      setInvalid('pin', 'PIN must be 4 to 15 characters, numbers, or hyphens (e.g. 24155-cm-002).');
    } else {
      setValid('pin');
    }
  }

  function validateCollege() {
    const val = fields.college.value;
    if (!val) {
      setInvalid('college', 'Please select your college.');
    } else {
      setValid('college');
    }
  }

  function validateBranch() {
    const val = fields.branch.value;
    if (!val) {
      setInvalid('branch', 'Please select your academic branch.');
    } else {
      setValid('branch');
    }
  }

  function validatePassword() {
    const val = fields.password.value;
    if (!val) {
      setInvalid('password', 'Password is required.');
    } else if (val.length < 8) {
      setInvalid('password', 'Password must be at least 8 characters long.');
    } else {
      setValid('password');
      // Revalidate confirm password if it contains text
      if (fields.confirmPassword.value) {
        validateConfirmPassword();
      }
    }
  }

  function validateConfirmPassword() {
    const val = fields.confirmPassword.value;
    const pass = fields.password.value;
    if (!val) {
      setInvalid('confirmPassword', 'Please confirm your password.');
    } else if (val !== pass) {
      setInvalid('confirmPassword', 'Passwords do not match.');
    } else {
      setValid('confirmPassword');
    }
  }

  // ── Input Action Bindings ──────────────────────────────
  // Real-time dynamic checks as user types
  fields.name.addEventListener('input', validateName);
  fields.dob.addEventListener('change', validateDob);
  fields.pin.addEventListener('input', validatePin);
  fields.college.addEventListener('change', validateCollege);
  fields.branch.addEventListener('change', validateBranch);
  fields.password.addEventListener('input', validatePassword);
  fields.confirmPassword.addEventListener('input', validateConfirmPassword);

  // Checks on focus out / blur
  fields.name.addEventListener('blur', validateName);
  fields.dob.addEventListener('blur', validateDob);
  fields.pin.addEventListener('blur', validatePin);
  fields.college.addEventListener('blur', validateCollege);
  fields.branch.addEventListener('blur', validateBranch);
  fields.password.addEventListener('blur', validatePassword);
  fields.confirmPassword.addEventListener('blur', validateConfirmPassword);

  // ── Form Submission Handler ────────────────────────────
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();

      // Final checks
      validateName();
      validateDob();
      validatePin();
      validateCollege();
      validateBranch();
      validatePassword();
      validateConfirmPassword();

      const allValid = Object.values(validationState).every(val => val === true);
      if (!allValid) return;

      // Simulate registration submission
      submitBtn.disabled = true;
      submitBtn.textContent = 'Creating account...';

      setTimeout(() => {
        form.classList.add('hide');
        form.style.display = 'none';
        if (successState) {
          successState.classList.add('show');
        }
      }, 1200);
    });
  }
});
