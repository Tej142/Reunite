/**
 * Reunite Real-Time Notifications Engine
 * Fetches unread notification count, plays microinteractions on match alerts,
 * and maintains dynamic synchronized state across pages.
 */

(function () {
  'use strict';

  // Determine correct API endpoint based on current page path
  function getApiEndpoint() {
    if (window.NOTIF_API_ENDPOINT) return window.NOTIF_API_ENDPOINT;
    const path = window.location.pathname;
    if (path.includes('/Frontend/admin/')) {
      return '../../Backend/notifications.php';
    }
    if (path.includes('/Frontend/')) {
      return 'api/notifications.php';
    }
    return '../Backend/notifications.php';
  }

  // Audio Chime Generator using Native Web Audio API
  function playNotificationChime() {
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      const ctx = new AudioCtx();
      if (ctx.state === 'suspended') {
        ctx.resume().catch(() => {});
      }
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
      osc.frequency.exponentialRampToValueAtTime(880.00, ctx.currentTime + 0.15); // A5
      gain.gain.setValueAtTime(0.12, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 0.35);
    } catch (e) {
      // Audio autoplay policy or not supported
    }
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function initNotifications() {
    const notifBtn = document.getElementById('navNotifBtn');
    const notifBadgeDot = document.getElementById('navNotifBadgeDot') || document.querySelector('.notif-badge-dot');
    const notifCountBadge = document.getElementById('navNotifCountBadge') || document.querySelector('.notif-count-badge');
    const notifDropdown = document.getElementById('navNotifDropdown');
    const notifList = document.getElementById('navNotifList') || document.querySelector('.notif-dropdown-list');
    const notifMarkAllBtn = document.getElementById('navNotifMarkAllBtn');

    if (!notifBtn || !notifDropdown || !notifList) {
      return; // Element not present (e.g. guest mode or not logged in)
    }

    const apiUrl = getApiEndpoint();
    const seenNotificationIds = new Set();
    let isInitialLoad = true;
    let pollInterval = null;

    // Toggle Dropdown on Bell Click
    notifBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      const drawer = document.getElementById('navDrawer');
      const hamburgerBtn = document.getElementById('navHamburgerBtn');
      if (drawer && drawer.classList.contains('open')) {
        drawer.classList.remove('open');
        if (hamburgerBtn) hamburgerBtn.classList.remove('active');
      }
      const isOpen = notifDropdown.classList.toggle('open');
      if (isOpen) {
        // Fetch fresh list on dropdown open
        fetchNotifications();
      }
    });

    // Close Dropdown on outside click
    document.addEventListener('click', function (e) {
      if (!notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
        notifDropdown.classList.remove('open');
      }
    });

    // Mark All Read Button
    if (notifMarkAllBtn) {
      notifMarkAllBtn.addEventListener('click', async function (e) {
        e.preventDefault();
        e.stopPropagation();
        try {
          await fetch(apiUrl + '?action=mark_read&all=1', { method: 'POST' });
          if (notifBadgeDot) {
            notifBadgeDot.style.display = 'none';
            notifBadgeDot.classList.remove('pulse');
          }
          if (notifCountBadge) {
            notifCountBadge.textContent = 'All caught up';
          }
          notifMarkAllBtn.style.display = 'none';

          // Update local DOM list items
          const items = notifList.querySelectorAll('.notif-dropdown-item.unread');
          items.forEach(it => it.classList.remove('unread'));
        } catch (err) {
          console.error('[Notifications] Failed to mark all read:', err);
        }
      });
    }

    // Fetch notifications function
    async function fetchNotifications() {
      try {
        const resp = await fetch(apiUrl + '?action=get&t=' + Date.now());
        if (!resp.ok) return;
        const data = await resp.json();

        if (!data.success || !data.logged_in) {
          return;
        }

        const unreadCount = data.unread_count || 0;
        const notifications = data.notifications || [];

        // 1. Update Badge Dot
        if (notifBadgeDot) {
          if (unreadCount > 0) {
            notifBadgeDot.style.display = 'block';
            notifBadgeDot.classList.add('pulse');
          } else {
            notifBadgeDot.style.display = 'none';
            notifBadgeDot.classList.remove('pulse');
          }
        }

        // 2. Update Count Badge
        if (notifCountBadge) {
          if (unreadCount > 0) {
            notifCountBadge.textContent = unreadCount + ' New';
            if (notifMarkAllBtn) notifMarkAllBtn.style.display = 'inline-block';
          } else {
            notifCountBadge.textContent = 'All caught up';
            if (notifMarkAllBtn) notifMarkAllBtn.style.display = 'none';
          }
        }

        // 3. Detect Real-Time New Match Arrivals
        let hasNewAlert = false;
        let newestAlertItem = null;

        notifications.forEach(item => {
          if (!isInitialLoad && !seenNotificationIds.has(item.id) && item.is_read === 0) {
            hasNewAlert = true;
            newestAlertItem = item;
          }
          seenNotificationIds.add(item.id);
        });

        // Trigger Audio Chime + Bell Ring + Toast for Live Match Alerts
        if (hasNewAlert && newestAlertItem) {
          playNotificationChime();

          notifBtn.classList.add('bell-ring');
          setTimeout(() => notifBtn.classList.remove('bell-ring'), 900);

          if (window.ReuniteToast) {
            window.ReuniteToast.show({
              title: newestAlertItem.title || '🎯 Match Alert!',
              message: newestAlertItem.message,
              type: 'info',
              duration: 7000
            });
          }
        }

        isInitialLoad = false;

        // 4. Render Dropdown Items
        if (notifications.length === 0) {
          notifList.innerHTML = `
            <div class="notif-dropdown-empty" style="padding: 1.5rem 1rem; text-align: center; color: var(--muted); font-size: 0.8125rem;">
              <span style="font-size: 1.5rem; display: block; margin-bottom: 0.35rem;">🔔</span>
              No notifications yet
            </div>
          `;
        } else {
          notifList.innerHTML = notifications.map(item => `
            <a href="${item.link || 'search.php'}" class="notif-dropdown-item ${item.is_read ? '' : 'unread'}" data-notif-id="${item.id}">
              <span class="notif-item-icon">${item.icon || '✨'}</span>
              <div class="notif-item-info">
                <p class="notif-item-title">${escapeHtml(item.title)}</p>
                <p class="notif-item-desc">${escapeHtml(item.message)}</p>
                <span class="notif-item-time">${item.time_ago}</span>
              </div>
            </a>
          `).join('');

          // Attach item click handlers to mark read immediately
          const items = notifList.querySelectorAll('.notif-dropdown-item');
          items.forEach(el => {
            el.addEventListener('click', function (e) {
              const id = this.getAttribute('data-notif-id');
              if (this.classList.contains('unread')) {
                this.classList.remove('unread');
                // Asynchronously mark read
                fetch(apiUrl + '?action=mark_read&notification_id=' + encodeURIComponent(id), { method: 'POST' }).catch(() => {});
                // Decrement counter locally
                const currentBadge = notifCountBadge.textContent;
                const curNum = parseInt(currentBadge, 10);
                if (!isNaN(curNum) && curNum > 1) {
                  notifCountBadge.textContent = (curNum - 1) + ' New';
                } else {
                  notifCountBadge.textContent = 'All caught up';
                  if (notifBadgeDot) notifBadgeDot.style.display = 'none';
                  if (notifMarkAllBtn) notifMarkAllBtn.style.display = 'none';
                }
              }
            });
          });
        }

      } catch (e) {
        console.warn('[Notifications] Polling notice:', e.message);
      }
    }

    // Start initial fetch
    fetchNotifications();

    // Start Real-Time Polling every 3.5 seconds
    pollInterval = setInterval(fetchNotifications, 3500);

    // Fast poll when window regains visibility
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) {
        fetchNotifications();
      }
    });

    // Expose global manual refresh method
    window.refreshNotifications = fetchNotifications;
  }

  // Auto initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNotifications);
  } else {
    initNotifications();
  }
})();
