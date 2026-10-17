/**
 * Reunite — Dedicated Item Detail View Controller
 * Handles image gallery zooming, lightboxes, copy ID, sharing,
 * claim verification modal, and keyboard accessibility.
 */

document.addEventListener('DOMContentLoaded', () => {
  initKeyboardListeners();
});

/**
 * Copy Report ID with tooltip / toast feedback
 */
function copyItemReportId(id, btn) {
  const textToCopy = id.startsWith('#') ? id : `#${id}`;
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(textToCopy);
  }

  if (btn) {
    btn.classList.add('copied');
    const label = btn.querySelector('.copy-label-text');
    if (label) label.textContent = 'Copied!';
    setTimeout(() => {
      btn.classList.remove('copied');
      if (label) label.textContent = 'Copy';
    }, 2000);
  }

  if (window.ReuniteToast) {
    window.ReuniteToast.info('Copied', `Report ID ${textToCopy} copied to clipboard.`);
  }
}

/**
 * Fullscreen Lightbox Modal
 */
let currentLightboxImgSrc = '';

function openItemLightbox(imgSrc, title) {
  currentLightboxImgSrc = imgSrc;
  const overlay = document.getElementById('itemLightboxOverlay');
  const img = document.getElementById('itemLightboxImg');
  const caption = document.getElementById('itemLightboxCaption');

  if (overlay && img) {
    img.src = imgSrc;
    if (caption) caption.textContent = title || 'Item Photo';
    overlay.classList.add('active');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }
}

function closeItemLightbox() {
  const overlay = document.getElementById('itemLightboxOverlay');
  if (overlay) {
    overlay.classList.remove('active');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }
}

/**
 * Thumbnail Switcher
 */
function switchMainPhoto(thumbBtn, imgSrc) {
  document.querySelectorAll('.item-thumb-btn').forEach(btn => btn.classList.remove('active'));
  if (thumbBtn) thumbBtn.classList.add('active');

  const mainImg = document.getElementById('mainStagePhoto');
  if (mainImg) {
    mainImg.src = imgSrc;
  }
}

/**
 * Native Web Share or Link Copy
 */
function shareCurrentItem(itemId, title) {
  const shareUrl = window.location.href;
  if (navigator.share) {
    navigator.share({
      title: `Reunite: ${title}`,
      text: `Check out this item report (${itemId}) on Reunite Campus Lost & Found:`,
      url: shareUrl
    }).catch(() => {});
  } else {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(shareUrl);
    }
    if (window.ReuniteToast) {
      window.ReuniteToast.success('Link Copied', `Direct share link for #${itemId} copied to clipboard.`);
    }
  }
}

/**
 * Report / Sighting Flagging
 */
function flagItemReport(itemId) {
  if (window.ReuniteToast) {
    window.ReuniteToast.info('Campus Security', `To report a sighting or flag report #${itemId}, please reach out to the campus Lost & Found coordinator.`);
  }
}

/**
 * Claim Verification Modal
 */
function openClaimVerificationModal() {
  const modal = document.getElementById('claimVerificationModal');
  if (modal) {
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    const firstInput = modal.querySelector('input, textarea');
    if (firstInput) setTimeout(() => firstInput.focus(), 50);
  }
}

function closeClaimVerificationModal() {
  const modal = document.getElementById('claimVerificationModal');
  if (modal) {
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }
}

async function handleClaimProofSubmit(e, itemId) {
  e.preventDefault();
  const form = document.getElementById('claimProofForm');
  const submitBtn = document.getElementById('btnSubmitProof');
  const dialog = document.getElementById('claimModalDialog');
  const secretProof = document.getElementById('claimSecretProof')?.value.trim();
  const studentName = document.getElementById('claimStudentName')?.value.trim();
  const studentEmail = document.getElementById('claimStudentEmail')?.value.trim();

  // Gather category questions answers (M1)
  const catAnswers = [];
  document.querySelectorAll('.dynamic-cat-q').forEach((input, idx) => {
    const qText = input.getAttribute('data-question') || `Question ${idx + 1}`;
    const ans = input.value.trim();
    if (ans) {
      catAnswers.push(`Q${idx + 1}: ${qText}\nA: ${ans}`);
    }
  });

  let fullProof = '';
  if (catAnswers.length > 0) {
    fullProof += catAnswers.join('\n\n') + '\n\n';
  }
  if (secretProof) {
    fullProof += `Additional Private Proof:\n${secretProof}`;
  }
  fullProof = fullProof.trim();

  if (!fullProof) {
    if (window.ReuniteToast) window.ReuniteToast.error('Missing Proof', 'Please answer the verification questions.');
    return;
  }

  const rawId = parseInt(String(itemId).replace(/\D/g, ''), 10);
  if (!rawId) {
    if (window.ReuniteToast) window.ReuniteToast.error('Invalid Item', 'Could not determine report ID.');
    return;
  }

  const origBtnContent = submitBtn ? submitBtn.innerHTML : '';
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span>Verifying Proof...</span>';
  }

  try {
    const res = await fetch('../Backend/claims.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'submit',
        report_id: rawId,
        proof_details: fullProof,
        return_type: 'in_person',
        contact_info: studentName ? `${studentName} (${studentEmail || 'N/A'})` : studentEmail
      })
    });

    const data = await res.json();
    if (data.success) {
      if (dialog) {
        const orbiEl = window.Orbi ? window.Orbi.create({ state: 'happy', size: 68, label: 'Ownership claim submitted' }) : null;
        const iconHtml = orbiEl
          ? `<div class="claim-orbi-wrap">${orbiEl.outerHTML}</div>`
          : `<div style="width: 58px; height: 58px; border-radius: 50%; background: #DCFCE7; border: 2px solid #86EFAC; color: #15803D; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.25rem;">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
            </div>`;

        dialog.innerHTML = `
          <div style="text-align: center; padding: 1.5rem 0.5rem;">
            ${iconHtml}
            <h3 style="font-family: var(--font-serif); font-size: 1.45rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">Verification Submitted</h3>
            <p style="font-size: 0.875rem; color: var(--muted); line-height: 1.6; margin-bottom: 1.5rem;">
              Your ownership proof for <strong>#${itemId}</strong> has been logged. The finder or campus coordinator will review the hidden details and reach out to your student email.
            </p>
            <button type="button" class="btn-claim-primary" style="width: 100%; justify-content: center;" onclick="closeClaimVerificationModal(); window.location.reload();">Done</button>
          </div>
        `;

        if (window.Orbi) {
          const happyEl = dialog.querySelector('.orbi');
          if (happyEl) window.Orbi.happy(happyEl, 3000);
        }
      }
      if (window.ReuniteToast) {
        window.ReuniteToast.show({
          title: 'Claim Logged',
          message: `Proof of ownership for #${itemId} submitted successfully.`,
          type: 'success',
          orbiState: 'happy',
          duration: 4000
        });
      }
    } else {
      if (data.code === 'UNAUTHORIZED') {
        if (window.ReuniteToast) {
          window.ReuniteToast.error('Login Required', 'You must log in to submit an ownership claim.', 4000);
        }
        setTimeout(() => {
          window.location.href = 'login.php';
        }, 1500);
      } else {
        if (window.ReuniteToast) {
          window.ReuniteToast.error('Claim Error', data.error || 'Failed to submit ownership claim.', 4000);
        }
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = origBtnContent;
        }
      }
    }
  } catch (err) {
    if (window.ReuniteToast) {
      window.ReuniteToast.error('Network Error', 'Could not reach server to submit claim.', 4000);
    }
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = origBtnContent;
    }
  }
}

/* ══════════════════════════════════════════════════════════════
   MODE 2: OTP HANDOVER CONTROLLERS
   ══════════════════════════════════════════════════════════════ */
let currentHandoverMatchId = 0;
let ownerOtpCountdownTimer = null;
let ownerOtpTimeRemaining = 300;

function openHandoverOtpDisplayModal(matchId) {
  currentHandoverMatchId = matchId || 1;
  const modal = document.getElementById('ownerOtpDisplayModal');
  if (modal) {
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    regenerateOwnerOtp();
  }
}

function closeOwnerOtpModal() {
  const modal = document.getElementById('ownerOtpDisplayModal');
  if (modal) {
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }
  if (ownerOtpCountdownTimer) {
    clearInterval(ownerOtpCountdownTimer);
    ownerOtpCountdownTimer = null;
  }
}

async function regenerateOwnerOtp() {
  const digitsBox = document.getElementById('ownerOtpDigitsBox');
  const timerText = document.getElementById('ownerOtpTimerText');
  if (digitsBox) {
    digitsBox.innerHTML = '<div style="color:var(--accent); font-size:1rem; padding:1rem;"><i class="fa-solid fa-spinner fa-spin"></i> Generating secure code...</div>';
  }

  try {
    const formData = new FormData();
    formData.append('match_id', currentHandoverMatchId);

    const res = await fetch('api/generate_otp.php', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();

    if (data.success && data.otp) {
      const otpStr = String(data.otp);
      if (digitsBox) {
        digitsBox.innerHTML = otpStr.split('').map(d => `<div class="otp-digit-box">${d}</div>`).join('');
      }

      ownerOtpTimeRemaining = data.expires_in_seconds || 300;
      if (ownerOtpCountdownTimer) clearInterval(ownerOtpCountdownTimer);
      ownerOtpCountdownTimer = setInterval(() => {
        ownerOtpTimeRemaining--;
        if (ownerOtpTimeRemaining <= 0) {
          clearInterval(ownerOtpCountdownTimer);
          if (timerText) timerText.textContent = 'Expired (Click Refresh)';
        } else {
          const m = String(Math.floor(ownerOtpTimeRemaining / 60)).padStart(2, '0');
          const s = String(ownerOtpTimeRemaining % 60).padStart(2, '0');
          if (timerText) timerText.textContent = `${m}:${s}`;
        }
      }, 1000);
    } else {
      if (digitsBox) {
        digitsBox.innerHTML = `<div style="color:#ef4444; font-size:0.85rem; padding:1rem;">${data.message || 'Error generating OTP'}</div>`;
      }
    }
  } catch (err) {
    if (digitsBox) {
      digitsBox.innerHTML = '<div style="color:#ef4444; font-size:0.85rem; padding:1rem;">Network error generating OTP</div>';
    }
  }
}

function openHandoverOtpInputModal(matchId) {
  currentHandoverMatchId = matchId || 1;
  const modal = document.getElementById('finderOtpVerifyModal');
  if (modal) {
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    const input = document.getElementById('finderInputOtp');
    if (input) {
      input.value = '';
      setTimeout(() => input.focus(), 80);
    }
    const fb = document.getElementById('finderOtpFeedback');
    if (fb) fb.style.display = 'none';
  }
}

function closeFinderOtpModal() {
  const modal = document.getElementById('finderOtpVerifyModal');
  if (modal) {
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }
}

async function handleFinderOtpSubmit(e) {
  e.preventDefault();
  const input = document.getElementById('finderInputOtp');
  const btn = document.getElementById('btnSubmitFinderOtp');
  const fb = document.getElementById('finderOtpFeedback');
  const otpVal = input ? input.value.trim() : '';

  if (!otpVal || !/^[0-9]{6}$/.test(otpVal)) {
    if (fb) {
      fb.style.display = 'block';
      fb.style.background = 'rgba(239, 68, 68, 0.15)';
      fb.style.border = '1px solid rgba(239, 68, 68, 0.4)';
      fb.style.color = '#f87171';
      fb.textContent = 'Please enter exactly 6 numeric digits.';
    }
    return;
  }

  const origBtnHtml = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span>Verifying OTP...</span>';
  }

  try {
    const formData = new FormData();
    formData.append('match_id', currentHandoverMatchId);
    formData.append('otp', otpVal);

    const res = await fetch('api/verify_otp.php', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();

    if (data.success) {
      if (fb) {
        fb.style.display = 'block';
        fb.style.background = 'rgba(16, 185, 129, 0.15)';
        fb.style.border = '1px solid rgba(16, 185, 129, 0.4)';
        fb.style.color = '#34d399';
        fb.innerHTML = `🎉 <strong>Handover Confirmed!</strong> Item reunited successfully. +10 Trust Points awarded!`;
      }
      if (window.Orbi && window.Orbi.happy) {
        window.Orbi.happy(null, 3000);
      }
      setTimeout(() => {
        window.location.reload();
      }, 2000);
    } else {
      if (fb) {
        fb.style.display = 'block';
        fb.style.background = 'rgba(239, 68, 68, 0.15)';
        fb.style.border = '1px solid rgba(239, 68, 68, 0.4)';
        fb.style.color = '#f87171';
        fb.textContent = data.message || 'Invalid or expired OTP code.';
      }
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = origBtnHtml;
      }
    }
  } catch (err) {
    if (fb) {
      fb.style.display = 'block';
      fb.style.background = 'rgba(239, 68, 68, 0.15)';
      fb.style.border = '1px solid rgba(239, 68, 68, 0.4)';
      fb.style.color = '#f87171';
      fb.textContent = 'Network error while verifying OTP.';
    }
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = origBtnHtml;
    }
  }
}

/* ══════════════════════════════════════════════════════════════
   MODE 3: CAMPUS ADMIN OFFICE RETURN CONTROLLERS
   ══════════════════════════════════════════════════════════════ */
function openAdminOfficeModal() {
  const modal = document.getElementById('adminOfficeReturnModal');
  if (modal) {
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }
}

function closeAdminOfficeModal() {
  const modal = document.getElementById('adminOfficeReturnModal');
  if (modal) {
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }
}

async function handleAdminOfficeSubmit(e) {
  e.preventDefault();
  const nameInput = document.getElementById('adminOfficeStudentName');
  const notesInput = document.getElementById('adminOfficeNotes');
  const btn = document.getElementById('btnSubmitAdminOffice');

  const nameVal = nameInput ? nameInput.value.trim() : '';
  const notesVal = notesInput ? notesInput.value.trim() : '';

  const rawId = parseInt(String(window.location.search).replace(/\D/g, ''), 10) || 1;

  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span>Routing to Admin Block...</span>';
  }

  try {
    const res = await fetch('../Backend/claims.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'submit',
        report_id: rawId,
        proof_details: `[Mode 3: Campus Admin Office Drop-Off]\nStudent: ${nameVal}\nNotes: ${notesVal}`,
        return_type: 'admin_office',
        contact_info: nameVal
      })
    });
    const data = await res.json();
    if (data.success) {
      if (window.ReuniteToast) {
        window.ReuniteToast.success('Admin Drop-Off Routed', 'Case logged for Campus Lost & Found Desk (Admin Block Room 104).');
      }
      closeAdminOfficeModal();
      setTimeout(() => window.location.reload(), 1500);
    } else {
      if (window.ReuniteToast) {
        window.ReuniteToast.error('Submission Notice', data.error || 'Failed to route case to admin desk.');
      }
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<span>Route to Campus Admin Office &rarr;</span>';
      }
    }
  } catch (err) {
    if (window.ReuniteToast) {
      window.ReuniteToast.error('Network Notice', 'Could not reach server to submit desk request.');
    }
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<span>Route to Campus Admin Office &rarr;</span>';
    }
  }
}

/**
 * Keyboard Navigation (Escape for Modals and Lightbox)
 */
function initKeyboardListeners() {
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const lightbox = document.getElementById('itemLightboxOverlay');
      if (lightbox && lightbox.classList.contains('active')) {
        closeItemLightbox();
        return;
      }

      const claimModal = document.getElementById('claimVerificationModal');
      if (claimModal && claimModal.classList.contains('active')) {
        closeClaimVerificationModal();
        return;
      }

      closeOwnerOtpModal();
      closeFinderOtpModal();
      closeAdminOfficeModal();
    }
  });
}
