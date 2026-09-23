/**
 * Reunite — Global Microinteractions & Utilities
 * Provides Toast Notifications, Sticky Nav Elevation, Button Loading States,
 * Bookmark Interactions, and Accessible Motion Support.
 */

(function (window, document) {
  'use strict';

  // ── 1. Toast Notification System ──────────────────────────────────
  let toastContainer = null;

  function ensureToastContainer() {
    if (!toastContainer) {
      toastContainer = document.querySelector('.reunite-toast-container');
      if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'reunite-toast-container';
        toastContainer.setAttribute('aria-live', 'polite');
        toastContainer.setAttribute('aria-atomic', 'true');
        document.body.appendChild(toastContainer);
      }
    }
    return toastContainer;
  }

  const Toast = {
    /**
     * Shows a toast notification.
     * @param {Object} options
     * @param {string} options.title - Toast title
     * @param {string} options.message - Toast description
     * @param {'success'|'error'|'info'|'warning'} [options.type='info'] - Toast type
     * @param {number} [options.duration=4500] - Duration in ms before auto-dismiss
     */
    show: function (options) {
      const container = ensureToastContainer();
      const type = options.type || 'info';
      const duration = options.duration || 4500;
      const title = options.title || '';
      const message = options.message || '';

      const icons = {
        success: '✓',
        error: '✕',
        info: 'ℹ',
        warning: '⚠'
      };

      const toast = document.createElement('div');
      toast.className = `reunite-toast toast-${type}`;
      toast.setAttribute('role', 'status');

      toast.innerHTML = `
        <div class="toast-icon-wrap" aria-hidden="true">${icons[type] || 'ℹ'}</div>
        <div class="toast-content">
          ${title ? `<div class="toast-title">${title}</div>` : ''}
          ${message ? `<div class="toast-msg">${message}</div>` : ''}
        </div>
        <button type="button" class="toast-close-btn" aria-label="Dismiss notification">&times;</button>
        <div class="toast-progress-bar" style="animation-duration: ${duration}ms;"></div>
      `;

      const closeBtn = toast.querySelector('.toast-close-btn');
      let dismissTimeout = null;

      const dismiss = () => {
        if (dismissTimeout) clearTimeout(dismissTimeout);
        toast.classList.add('toast-hiding');
        toast.addEventListener('animationend', () => {
          if (toast.parentElement) toast.parentElement.removeChild(toast);
        }, { once: true });
      };

      closeBtn.addEventListener('click', dismiss);
      dismissTimeout = setTimeout(dismiss, duration);

      container.appendChild(toast);
      return { dismiss };
    },
    success: function (title, message, duration) {
      return this.show({ title, message, type: 'success', duration });
    },
    error: function (title, message, duration) {
      return this.show({ title, message, type: 'error', duration });
    },
    info: function (title, message, duration) {
      return this.show({ title, message, type: 'info', duration });
    },
    warning: function (title, message, duration) {
      return this.show({ title, message, type: 'warning', duration });
    }
  };

  window.ReuniteToast = Toast;

  // ── 2. Sticky Navbar Elevation on Scroll ──────────────────────────
  function initStickyNav() {
    const nav = document.querySelector('nav');
    if (!nav) return;

    let ticking = false;
    const checkScroll = () => {
      if (window.scrollY > 15) {
        nav.classList.add('nav-scrolled');
      } else {
        nav.classList.remove('nav-scrolled');
      }
      ticking = false;
    };

    window.addEventListener('scroll', () => {
      if (!ticking) {
        window.requestAnimationFrame(checkScroll);
        ticking = true;
      }
    }, { passive: true });

    checkScroll();
  }

  // ── 3. Bookmark / Save Item Manager ──────────────────────────────
  const BOOKMARKS_KEY = 'reunite_saved_items';

  function getSavedItems() {
    try {
      return JSON.parse(localStorage.getItem(BOOKMARKS_KEY)) || [];
    } catch (e) {
      return [];
    }
  }

  function toggleSaveItem(itemId, itemTitle) {
    let saved = getSavedItems();
    const index = saved.indexOf(itemId);
    let isSaved = false;

    if (index > -1) {
      saved.splice(index, 1);
      isSaved = false;
      Toast.info('Item Removed', `Removed ${itemTitle || 'item'} from saved bookmarks.`, 3000);
    } else {
      saved.push(itemId);
      isSaved = true;
      Toast.success('Item Saved', `Saved ${itemTitle || 'item'} for quick reference.`, 3000);
    }

    try {
      localStorage.setItem(BOOKMARKS_KEY, JSON.stringify(saved));
    } catch (e) {}

    // Update all matching bookmark buttons on page
    document.querySelectorAll(`.btn-bookmark[data-id="${itemId}"]`).forEach(btn => {
      btn.classList.toggle('active', isSaved);
      btn.setAttribute('aria-pressed', isSaved ? 'true' : 'false');
      btn.setAttribute('title', isSaved ? 'Remove from saved' : 'Save this item');
    });

    return isSaved;
  }

  function isItemSaved(itemId) {
    return getSavedItems().includes(itemId);
  }

  window.ReuniteBookmarks = {
    get: getSavedItems,
    toggle: toggleSaveItem,
    isSaved: isItemSaved
  };

  // ── 4. Button Async Loading State Helpers ────────────────────────
  function setButtonLoading(btn, isLoading, loadingText) {
    if (!btn) return;
    if (isLoading) {
      btn.dataset.originalHtml = btn.innerHTML;
      btn.classList.add('btn-loading');
      btn.disabled = true;
      btn.setAttribute('aria-busy', 'true');
      btn.innerHTML = `
        <span class="btn-spinner" aria-hidden="true"></span>
        <span class="btn-loading-label">${loadingText || 'Processing...'}</span>
      `;
    } else {
      btn.classList.remove('btn-loading');
      btn.disabled = false;
      btn.removeAttribute('aria-busy');
      if (btn.dataset.originalHtml) {
        btn.innerHTML = btn.dataset.originalHtml;
        delete btn.dataset.originalHtml;
      }
    }
  }

  window.setButtonLoading = setButtonLoading;

  // ── 5. Form Field Shake Trigger ──────────────────────────────────
  function shakeElement(el) {
    if (!el) return;
    el.classList.remove('animate-shake');
    // Force reflow
    void el.offsetWidth;
    el.classList.add('animate-shake');
    el.addEventListener('animationend', () => {
      el.classList.remove('animate-shake');
    }, { once: true });
  }

  window.shakeElement = shakeElement;

  // ── 6. DOM Initialization ─────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    initStickyNav();

    // Global active button press effect listener
    document.addEventListener('pointerdown', (e) => {
      const btn = e.target.closest('button, .btn, .btn-nav-primary, .btn-ghost, .btn-empty-primary, .btn-empty-secondary');
      if (btn) {
        btn.classList.add('is-pressed');
      }
    });

    document.addEventListener('pointerup', () => {
      document.querySelectorAll('.is-pressed').forEach(el => el.classList.remove('is-pressed'));
    });

    document.addEventListener('pointercancel', () => {
      document.querySelectorAll('.is-pressed').forEach(el => el.classList.remove('is-pressed'));
    });
  });

})(window, document);
