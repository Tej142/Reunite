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

  if (!secretProof) {
    if (window.ReuniteToast) window.ReuniteToast.error('Missing Proof', 'Please describe ownership details.');
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
        proof_details: secretProof,
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
            <button type="button" class="btn-claim-primary" style="width: 100%; justify-content: center;" onclick="closeClaimVerificationModal()">Done</button>
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
    }
  });
}
