/**
 * Student Profile Management Controller
 */
document.addEventListener('DOMContentLoaded', () => {

  // 1. Tab Switching Logic
  const tabBtns = document.querySelectorAll('.profile-tab-btn');
  const tabPanels = {
    personal: document.getElementById('tabPanelPersonal'),
    activity: document.getElementById('tabPanelActivity'),
    security: document.getElementById('tabPanelSecurity'),
    notifications: document.getElementById('tabPanelNotifications')
  };

  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const tabId = btn.getAttribute('data-tab');
      
      // Update tab button active states
      tabBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      // Update tab panels
      Object.keys(tabPanels).forEach(key => {
        if (tabPanels[key]) {
          tabPanels[key].classList.toggle('active', key === tabId);
        }
      });
    });
  });

  // 2. Activity Feed Filter Chips
  const activityChips = document.querySelectorAll('.activity-chip');
  const reportCards = document.querySelectorAll('.user-report-card');

  activityChips.forEach(chip => {
    chip.addEventListener('click', () => {
      activityChips.forEach(c => c.classList.remove('active'));
      chip.classList.add('active');

      const filter = chip.getAttribute('data-filter');
      reportCards.forEach(card => {
        if (filter === 'all' || card.getAttribute('data-type') === filter) {
          card.style.display = 'flex';
        } else {
          card.style.display = 'none';
        }
      });
    });
  });

  // 3. Profile Information Form Update (AJAX)
  const profileForm = document.getElementById('profileDetailsForm');
  const saveBtn = document.getElementById('btnSaveProfile');
  const saveStatus = document.getElementById('profileSaveStatus');
  const profileDisplayName = document.getElementById('profileDisplayName');

  if (profileForm) {
    profileForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const name = document.getElementById('profName').value.trim();
      const email = document.getElementById('profEmail').value.trim();
      const phone = document.getElementById('profPhone').value.trim();
      const college = document.getElementById('profCollege').value.trim();
      const branch = document.getElementById('profBranch').value.trim();

      if (!name || !email) {
        if (!name) window.shakeElement(document.getElementById('profName'));
        if (!email) window.shakeElement(document.getElementById('profEmail'));
        if (window.ReuniteToast) {
          window.ReuniteToast.error('Validation Error', 'Name and Email are required.');
        }
        showStatus(saveStatus, 'Name and Email are required.', 'error');
        return;
      }

      if (saveBtn && window.setButtonLoading) {
        window.setButtonLoading(saveBtn, true, 'Saving...');
      }
      if (saveStatus) saveStatus.textContent = '';

      try {
        const response = await fetch('api/auth.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'update_profile',
            name,
            email,
            phone,
            college,
            branch
          })
        });

        const data = await response.json();

        if (data.success) {
          showStatus(saveStatus, '✓ Profile updated successfully!', 'success');
          if (window.ReuniteToast) {
            window.ReuniteToast.success('Profile Updated', 'Your profile details were saved successfully.');
          }
          if (profileDisplayName) {
            profileDisplayName.textContent = name;
          }
          // Update Avatar Letter in hero & navbar
          const newInitial = name.charAt(0).toUpperCase();
          document.querySelectorAll('.avatar-letter, .avatar-initial').forEach(el => {
            el.textContent = newInitial;
          });
        } else {
          showStatus(saveStatus, data.message || 'Error updating profile.', 'error');
          if (window.ReuniteToast) {
            window.ReuniteToast.error('Update Failed', data.message || 'Error updating profile.');
          }
        }
      } catch (err) {
        showStatus(saveStatus, 'Network error. Please try again.', 'error');
        if (window.ReuniteToast) {
          window.ReuniteToast.error('Network Error', 'Could not reach server. Please try again.');
        }
      } finally {
        if (saveBtn && window.setButtonLoading) {
          window.setButtonLoading(saveBtn, false);
        }
      }
    });
  }

  // 4. Password Change Form (AJAX)
  const passForm = document.getElementById('passwordChangeForm');
  const passBtn = document.getElementById('btnChangePassword');
  const passStatus = document.getElementById('passwordStatusMsg');

  if (passForm) {
    passForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const currPass = document.getElementById('currPassword').value.trim();
      const newPass = document.getElementById('newPassword').value.trim();
      const confirmPass = document.getElementById('confirmNewPassword').value.trim();

      if (!currPass || !newPass || !confirmPass) {
        window.shakeElement(passForm);
        showStatus(passStatus, 'Please fill in all password fields.', 'error');
        if (window.ReuniteToast) {
          window.ReuniteToast.error('Form Incomplete', 'Please fill in all password fields.');
        }
        return;
      }

      if (newPass !== confirmPass) {
        window.shakeElement(document.getElementById('confirmNewPassword'));
        showStatus(passStatus, 'New passwords do not match.', 'error');
        if (window.ReuniteToast) {
          window.ReuniteToast.error('Mismatch', 'New passwords do not match.');
        }
        return;
      }

      if (newPass.length < 8) {
        window.shakeElement(document.getElementById('newPassword'));
        showStatus(passStatus, 'Password must be at least 8 characters.', 'error');
        if (window.ReuniteToast) {
          window.ReuniteToast.error('Weak Password', 'Password must be at least 8 characters.');
        }
        return;
      }

      if (passBtn && window.setButtonLoading) {
        window.setButtonLoading(passBtn, true, 'Updating...');
      }
      if (passStatus) passStatus.textContent = '';

      try {
        const response = await fetch('api/auth.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'change_password',
            current_password: currPass,
            new_password: newPass
          })
        });

        const data = await response.json();

        if (data.success) {
          showStatus(passStatus, '✓ Password changed successfully!', 'success');
          if (window.ReuniteToast) {
            window.ReuniteToast.success('Password Changed', 'Your password has been securely updated.');
          }
          passForm.reset();
        } else {
          showStatus(passStatus, data.message || 'Error changing password.', 'error');
          if (window.ReuniteToast) {
            window.ReuniteToast.error('Password Update Failed', data.message || 'Current password incorrect.');
          }
        }
      } catch (err) {
        showStatus(passStatus, 'Network error. Please try again.', 'error');
      } finally {
        if (passBtn && window.setButtonLoading) {
          window.setButtonLoading(passBtn, false);
        }
      }
    });
  }

  function showStatus(element, text, type) {
    if (!element) return;
    element.textContent = text;
    element.className = `save-status-msg ${type}`;
    setTimeout(() => {
      element.textContent = '';
      element.className = 'save-status-msg';
    }, 4000);
  }

});

