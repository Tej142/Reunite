document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('forgotForm');
  const submitBtn = document.getElementById('submitBtn');
  const successState = document.getElementById('successState');
  const successDesc = document.getElementById('successDesc');
  const topAlert = document.getElementById('forgotTopAlert');
  const topAlertText = document.getElementById('forgotTopAlertText');
  const identifierInput = document.getElementById('identifier');
  const identifierError = document.getElementById('identifierError');
  const btnResendLink = document.getElementById('btnResendLink');

  function showTopAlert(msg, isSuccess = false) {
    if (topAlert && topAlertText) {
      topAlertText.textContent = msg;
      topAlert.style.borderColor = isSuccess ? 'rgba(34, 197, 94, 0.4)' : 'rgba(239, 68, 68, 0.4)';
      topAlert.style.backgroundColor = isSuccess ? 'rgba(34, 197, 94, 0.1)' : 'rgba(239, 68, 68, 0.1)';
      topAlert.style.color = isSuccess ? '#22C55E' : '#EF4444';
      const icon = topAlert.querySelector('.alert-icon');
      if (icon) icon.style.stroke = isSuccess ? '#22C55E' : '#EF4444';
      topAlert.classList.remove('show');
      void topAlert.offsetWidth;
      topAlert.classList.add('show');
    }
  }

  function hideTopAlert() {
    if (topAlert) topAlert.classList.remove('show');
  }

  function clearErrors() {
    if (identifierInput) {
      identifierInput.classList.remove('invalid');
      identifierInput.classList.remove('valid');
    }
    if (identifierError) {
      identifierError.classList.remove('show');
      identifierError.textContent = '';
    }
  }

  function setInvalid(msg) {
    if (identifierInput) {
      identifierInput.classList.remove('valid');
      identifierInput.classList.add('invalid');
      identifierInput.focus();
    }
    if (identifierError) {
      identifierError.textContent = msg;
      identifierError.classList.add('show');
    }
    return false;
  }

  if (identifierInput) {
    identifierInput.addEventListener('input', () => {
      clearErrors();
      hideTopAlert();
    });
  }

  async function handleSendReset(e) {
    if (e) e.preventDefault();
    hideTopAlert();
    clearErrors();

    const val = identifierInput ? identifierInput.value.trim() : '';
    if (!val) {
      return setInvalid('Please enter your College PIN or registered email address.');
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Sending link...';

    try {
      const response = await fetch('api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'send_reset_link',
          identifier: val
        })
      });

      let data;
      const text = await response.text();
      try {
        data = JSON.parse(text);
      } catch (err) {
        console.error('Server response parse error:', text);
        submitBtn.disabled = false;
        submitBtn.textContent = 'Send Reset Link';
        showTopAlert('Server error. Please check your connection.');
        return;
      }

      if (data.success) {
        form.style.display = 'none';
        if (successState) {
          if (successDesc && data.message) {
            successDesc.innerHTML = data.message + '<br><br><em>Note: Link expires in exactly 10 minutes.</em>';
          }
          successState.classList.add('show');
        }
      } else {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Send Reset Link';
        showTopAlert(data.message || 'Failed to send reset link.');
      }
    } catch (netErr) {
      console.error('Network Error:', netErr);
      submitBtn.disabled = false;
      submitBtn.textContent = 'Send Reset Link';
      showTopAlert('Unable to reach server. Please check your network connection.');
    }
  }

  if (form) {
    form.addEventListener('submit', handleSendReset);
  }

  if (btnResendLink) {
    btnResendLink.addEventListener('click', () => {
      if (successState) successState.classList.remove('show');
      if (form) {
        form.style.display = 'block';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Send Reset Link';
        if (identifierInput) identifierInput.focus();
      }
    });
  }
});
