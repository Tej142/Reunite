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
 * Keyboard Navigation (Escape for Lightbox and Modals)
 */
function initKeyboardListeners() {
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const lightbox = document.getElementById('itemLightboxOverlay');
      if (lightbox && lightbox.classList.contains('active')) {
        closeItemLightbox();
        return;
      }

      // Close match details / verification overlay if open
      const matchOverlay = document.getElementById('modalOverlay');
      if (matchOverlay && !matchOverlay.classList.contains('hidden')) {
        if (typeof closeAllModals === 'function') {
          closeAllModals();
        } else {
          matchOverlay.classList.add('hidden');
          matchOverlay.classList.remove('flex');
          document.body.classList.remove('overflow-hidden');
        }
        return;
      }

      // Close founder approval overlay if open
      const founderOverlay = document.getElementById('founderApprovalOverlay');
      if (founderOverlay && !founderOverlay.classList.contains('hidden')) {
        founderOverlay.classList.add('hidden');
        founderOverlay.classList.remove('flex');
        return;
      }

      // Close owner handover overlay if open
      const ownerOverlay = document.getElementById('ownerHandoverOverlay');
      if (ownerOverlay && !ownerOverlay.classList.contains('hidden')) {
        if (typeof closeHandoverModal === 'function') {
          closeHandoverModal();
        } else {
          ownerOverlay.classList.add('hidden');
          ownerOverlay.classList.remove('flex');
        }
      }
    }
  });
}
