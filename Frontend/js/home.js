let ITEMS = [];
let currentFilter = 'all';
let isFetchingHome = true;

async function fetchLiveHomeItems() {
  const grid = document.getElementById('itemsGrid');
  if (grid && ITEMS.length === 0) {
    const orbiEl = window.Orbi ? window.Orbi.create({ state: 'loading', size: 48, label: 'Loading campus listings' }) : null;
    const orbiHtml = orbiEl ? orbiEl.outerHTML : '';
    grid.innerHTML = `
      <div class="orbi-section-loader" role="status" aria-live="polite" style="grid-column: 1 / -1;">
        ${orbiHtml}
        <span class="orbi-section-loader-text">Loading campus listings...</span>
      </div>
    `;
  }

  try {
    const res = await fetch('../Backend/reports.php?action=list&type=lost&limit=20');
    if (!res.ok) return;
    const json = await res.json();
    const rows = (json.data && json.data.reports) ? json.data.reports : (json.reports || []);

    if (Array.isArray(rows)) {
      ITEMS = rows
        .filter(r => String(r.report_type || 'lost').toLowerCase() === 'lost')
        .map(r => {
          let imgUrl = 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=480&h=320&fit=crop&auto=format';
          if (r.image_path && typeof r.image_path === 'string' && r.image_path.trim() !== '') {
            const p = r.image_path.trim();
            imgUrl = (p.startsWith('http') || p.startsWith('data:')) ? p : (p.startsWith('/') ? p : '../' + p);
          }

          return {
            id: r.id,
            reportId: `RL-${String(r.id).padStart(5, '0')}`,
            img: imgUrl,
            title: r.title || r.category || 'Lost Item',
            desc: r.description || 'Reported on campus via Reunite.',
            city: r.location || 'Campus Hub',
            when: formatTimeAgo(r.created_at || r.date_lost || r.lost_date || r.date_found || r.found_date),
            status: inArray(String(r.status || '').toLowerCase(), ['matched', 'claimed', 'closed', 'recovered', 'returned']) ? 'matched' : 'active',
            type: 'lost'
          };
        });

    }
  } catch (err) {
    console.warn('[Home] Live items sync notice:', err);
  } finally {
    isFetchingHome = false;
    renderCards();
  }
}

function inArray(val, arr) {
  return arr.indexOf(val) !== -1;
}

function formatTimeAgo(dateStr) {
  if (!dateStr) return 'Recently';
  try {
    const diff = (new Date() - new Date(dateStr.replace(/-/g, '/'))) / 1000;
    if (isNaN(diff) || diff < 0) return 'Recently';
    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
  } catch (e) {
    return 'Recently';
  }
}

function renderCards() {
  const grid = document.getElementById('itemsGrid');
  if (!grid) return;

  // Filter strictly to lost items
  let filtered = ITEMS.filter(i => i.type === 'lost');
  if (currentFilter === 'active') {
    filtered = filtered.filter(i => i.status === 'active');
  } else if (currentFilter === 'matched') {
    filtered = filtered.filter(i => i.status === 'matched');
  }

  if (filtered.length === 0) {
    if (isFetchingHome && ITEMS.length === 0) {
      const orbiEl = window.Orbi ? window.Orbi.create({ state: 'loading', size: 48, label: 'Loading campus listings' }) : null;
      const orbiHtml = orbiEl ? orbiEl.outerHTML : '';
      grid.innerHTML = `
        <div class="orbi-section-loader" role="status" aria-live="polite" style="grid-column: 1 / -1;">
          ${orbiHtml}
          <span class="orbi-section-loader-text">Loading campus listings...</span>
        </div>
      `;
      return;
    }

    const emptyMsg = currentFilter === 'matched' 
      ? 'No reunited items recorded yet.' 
      : 'No active missing item searches right now.';

    const orbiNotFoundEl = window.Orbi ? window.Orbi.create({ state: 'notfound', size: 84, label: emptyMsg }) : null;
    const orbiNotFoundHtml = orbiNotFoundEl
      ? `<div style="display: flex; justify-content: center; margin-bottom: 0.75rem;">${orbiNotFoundEl.outerHTML}</div>`
      : `<div style="display: flex; justify-content: center; margin-bottom: 0.75rem; color: var(--muted, #8C827A);">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </div>`;

    grid.innerHTML = `
      <div style="grid-column: 1 / -1; text-align: center; padding: 3rem 1.5rem; background: var(--surface, #fff); border: 1px solid var(--border, #E4DCD3); border-radius: 1rem;">
        ${orbiNotFoundHtml}
        <h3 style="font-size: 1.15rem; font-weight: 600; color: var(--ink, #1C1917); margin-bottom: 0.35rem;">${emptyMsg}</h3>
        <p style="font-size: 0.875rem; color: var(--muted, #8C827A); margin-bottom: 1.25rem; max-width: 40ch; margin-left: auto; margin-right: auto;">
          When students report missing belongings on campus, they will appear live right here.
        </p>
        <div style="display: flex; gap: 0.65rem; justify-content: center; flex-wrap: wrap;">
          <a href="report-lost-item.php" class="btn btn-primary" style="font-size: 0.85rem; padding: 0.55rem 1.15rem;">+ Report Missing Item</a>
          <a href="search.php" class="btn btn-ghost" style="font-size: 0.85rem; padding: 0.55rem 1.15rem;">Search Found Belongings</a>
        </div>
      </div>
    `;
    return;
  }

  grid.innerHTML = filtered.map(item => {
    const isSaved = window.ReuniteBookmarks ? window.ReuniteBookmarks.isSaved(`item-${item.id}`) : false;
    return `
      <article class="item-card search-item-card" data-id="${item.reportId}" onclick="window.location.href='item.php?id=${item.reportId}'" style="cursor: pointer;">
        <div class="item-card-img-wrap">
          <img src="${item.img}" alt="${item.title}" class="item-card-img card-img" loading="lazy" decoding="async" onerror="this.src='https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=480&h=320&fit=crop'" onload="this.classList.add('loaded')" />
          <div class="item-card-overlay"></div>
          <button type="button" class="btn-bookmark card-bookmark-btn ${isSaved ? 'active' : ''}" data-id="${item.reportId}" onclick="handleHomeBookmark(event, '${item.reportId}', '${item.title.replace(/'/g, "\\'")}')" aria-label="Save item" title="${isSaved ? 'Remove from saved' : 'Save this item'}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg>
          </button>
          <span class="item-badge ${item.status === 'matched' ? 'badge-matched' : 'badge-active'}">
            ${item.status === 'matched' ? '&#10003; Reunited' : 'Lost Item'}
          </span>
        </div>
        <div class="item-card-body">
          <h3 class="item-card-title">${item.title}</h3>
          <p class="item-card-desc">${item.desc}</p>
          <div class="item-card-foot">
            <span class="item-loc">
              <svg width="10" height="10" viewBox="0 0 10 10" fill="none" aria-hidden="true">
                <path d="M5 1C3.343 1 2 2.343 2 4c0 2.5 3 5 3 5s3-2.5 3-5c0-1.657-1.343-3-3-3z" fill="#A8A29E"/>
                <circle cx="5" cy="4" r="1" fill="white"/>
              </svg>
              ${item.city}
            </span>
            <span class="item-when">${item.when}</span>
          </div>
        </div>
      </article>
    `;
  }).join('');
}

window.handleHomeBookmark = function(e, itemId, itemTitle) {
  e.stopPropagation();
  if (window.ReuniteBookmarks) {
    window.ReuniteBookmarks.toggle(itemId, itemTitle);
  }
};

function initFilters() {
  const pills = document.querySelectorAll('.filter-pill');
  pills.forEach(pill => {
    pill.addEventListener('click', () => {
      currentFilter = pill.dataset.filter;
      pills.forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      
      const grid = document.getElementById('itemsGrid');
      if (grid) {
        grid.style.opacity = '0.5';
        setTimeout(() => {
          renderCards();
          grid.style.opacity = '1';
        }, 120);
      } else {
        renderCards();
      }
    });
  });
}

/* ── Real-Time Homepage Statistics Engine ───────────────────────── */
let currentStats = {
  items_reunited: null,
  active_searches: null,
  reported_this_week: null,
  match_accuracy: null
};

function animateNumber(element, start, end, suffix = '', duration = 650) {
  if (!element) return;
  start = Number(start) || 0;
  end = Number(end) || 0;
  if (start === end) {
    element.textContent = end.toLocaleString() + suffix;
    return;
  }
  const startTime = performance.now();
  function update(now) {
    const elapsed = now - startTime;
    const progress = Math.min(elapsed / duration, 1);
    const ease = 1 - Math.pow(1 - progress, 3);
    const current = Math.round(start + (end - start) * ease);
    element.textContent = current.toLocaleString() + suffix;
    if (progress < 1) {
      requestAnimationFrame(update);
    } else {
      element.textContent = end.toLocaleString() + suffix;
    }
  }
  requestAnimationFrame(update);
}

function flashStatUpdate(el) {
  if (!el) return;
  el.style.transition = 'transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), color 0.25s ease';
  el.style.transform = 'scale(1.1)';
  setTimeout(() => {
    el.style.transform = 'scale(1)';
  }, 260);
}

async function fetchRealtimeHomeStats() {
  try {
    const res = await fetch('../Backend/reports.php?action=stats');
    if (!res.ok) return;
    const json = await res.json();
    const stats = json?.data?.stats || json?.stats;
    if (!stats) return;

    // 1. Items Reunited
    const elReunited = document.getElementById('stat-items-reunited');
    if (elReunited) {
      const newVal = parseInt(stats.items_reunited, 10) || 0;
      const prevVal = currentStats.items_reunited !== null ? currentStats.items_reunited : (parseInt(elReunited.getAttribute('data-val'), 10) || 0);
      if (currentStats.items_reunited === null || prevVal !== newVal) {
        animateNumber(elReunited, prevVal, newVal);
        if (currentStats.items_reunited !== null && prevVal !== newVal) flashStatUpdate(elReunited);
      }
      currentStats.items_reunited = newVal;
      elReunited.setAttribute('data-val', newVal);
    }

    // 2. Active Searches
    const elActive = document.getElementById('stat-active-searches');
    if (elActive) {
      const newVal = parseInt(stats.active_searches, 10) || 0;
      const prevVal = currentStats.active_searches !== null ? currentStats.active_searches : (parseInt(elActive.getAttribute('data-val'), 10) || 0);
      if (currentStats.active_searches === null || prevVal !== newVal) {
        animateNumber(elActive, prevVal, newVal);
        if (currentStats.active_searches !== null && prevVal !== newVal) flashStatUpdate(elActive);
      }
      currentStats.active_searches = newVal;
      elActive.setAttribute('data-val', newVal);
    }

    // 3. Reported This Week
    const elWeek = document.getElementById('stat-reported-this-week');
    if (elWeek) {
      const newVal = parseInt(stats.reported_this_week, 10) || 0;
      const prevVal = currentStats.reported_this_week !== null ? currentStats.reported_this_week : (parseInt(elWeek.getAttribute('data-val'), 10) || 0);
      if (currentStats.reported_this_week === null || prevVal !== newVal) {
        animateNumber(elWeek, prevVal, newVal);
        if (currentStats.reported_this_week !== null && prevVal !== newVal) flashStatUpdate(elWeek);
      }
      currentStats.reported_this_week = newVal;
      elWeek.setAttribute('data-val', newVal);
    }

    // 4. Match Accuracy
    const elAccuracy = document.getElementById('stat-match-accuracy');
    if (elAccuracy && stats.match_accuracy) {
      const strVal = String(stats.match_accuracy).trim();
      const numVal = parseFloat(strVal.replace(/[^0-9.]/g, '')) || 99.4;
      const prevVal = currentStats.match_accuracy !== null ? currentStats.match_accuracy : (parseFloat(String(elAccuracy.getAttribute('data-val') || '').replace(/[^0-9.]/g, '')) || 0);
      if (currentStats.match_accuracy === null || prevVal !== numVal) {
        const startTime = performance.now();
        const duration = 650;
        function updateAcc(now) {
          const progress = Math.min((now - startTime) / duration, 1);
          const ease = 1 - Math.pow(1 - progress, 3);
          const cur = (prevVal + (numVal - prevVal) * ease).toFixed(1);
          elAccuracy.textContent = cur + '%';
          if (progress < 1) requestAnimationFrame(updateAcc);
          else elAccuracy.textContent = numVal.toFixed(1) + '%';
        }
        requestAnimationFrame(updateAcc);
        if (currentStats.match_accuracy !== null && prevVal !== numVal) flashStatUpdate(elAccuracy);
      }
      currentStats.match_accuracy = numVal;
      elAccuracy.setAttribute('data-val', strVal);
    }
  } catch (e) {
    console.debug('[Home] Realtime stats sync notice:', e);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  renderCards();
  initFilters();
  fetchLiveHomeItems();
  
  // Real-time statistics: immediate sync + continuous polling
  fetchRealtimeHomeStats();
  setInterval(fetchRealtimeHomeStats, 8000); // Poll every 8 seconds

  // Auto-sync on visibility change & window focus
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      fetchRealtimeHomeStats();
      fetchLiveHomeItems();
    }
  });
  window.addEventListener('focus', () => {
    fetchRealtimeHomeStats();
  });
});

