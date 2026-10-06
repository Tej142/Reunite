/**
 * Reunite — Search & Discovery Controller
 * Handles live keyword searching, multi-faceted filtering, AI smart match scoring,
 * dynamic item card rendering, and claim verification modals.
 */

// Real-Time Campus Lost & Found Item Database (Populated from Backend/Database)
let ITEMS_DATABASE = [];

// Active State
let currentSearchQuery = '';
let currentTypeFilter = 'found'; // Default to Found Items so only found items display on search
let currentCategory = 'all';
let currentLocation = 'all';
let currentSort = 'newest';
let isAiMatchActive = false;
let aiMatchScores = {};
let isListView = false;
let isFetchingBackend = false;

// DOM Elements
let searchInput, clearBtn, searchSpinner, resultsGrid, resultsCount, catChips, typeSelect, categorySelect, locationSelect, sortSelect;
let modalOverlay, aiDrawer, aiMatchBtn, aiTextarea, aiTagsDetected;

document.addEventListener('DOMContentLoaded', () => {
  initDomElements();
  bindEvents();
  renderResults();
  fetchBackendReports();
});

async function fetchBackendReports() {
  if (isFetchingBackend) return;
  isFetchingBackend = true;

  try {
    const resp = await fetch('../Backend/reports.php?action=list&type=all&limit=200');
    if (!resp.ok) return;
    const json = await resp.json();
    const rows = (json.data && json.data.reports) ? json.data.reports : (json.reports || []);

    if (!Array.isArray(rows)) return;

    ITEMS_DATABASE = [];
    rows.forEach(r => {
      const repType = String(r.report_type || 'found').toLowerCase();
      const dbId = r.id;
      const formattedId = dbId ? `R${repType === 'found' ? 'F' : 'L'}-${String(dbId).padStart(5, '0')}` : (r.report_id || `R-${Math.random()}`);

      let imgUrl = 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=640&h=420&fit=crop&auto=format';
      if (r.image_path && typeof r.image_path === 'string' && r.image_path.trim() !== '') {
        const p = r.image_path.trim();
        imgUrl = (p.startsWith('http') || p.startsWith('data:')) ? p : (p.startsWith('/') ? p : '../' + p);
      } else if (repType === 'found') {
        imgUrl = 'https://images.unsplash.com/photo-1584438784894-089d6a62b8fa?w=640&h=420&fit=crop&auto=format';
      }

      ITEMS_DATABASE.push({
        id: formattedId,
        rawId: dbId,
        title: r.title || r.category || (repType === 'found' ? 'Found Item' : 'Lost Item'),
        category: r.category || 'General',
        type: repType,
        status: r.status || 'active',
        img: imgUrl,
        desc: r.description || 'Reported on campus via Reunite.',
        location: r.location || 'Campus',
        date: r.created_at ? r.created_at.split(' ')[0] : '2026-09-28',
        timeAgo: formatTimeAgo(r.created_at),
        dna: {
          category: r.category || 'Item',
          features: [r.category || 'Indexed Item', r.location || 'Campus']
        }
      });
    });

    renderResults();
  } catch (err) {
    console.warn('[Search] Backend reports sync notice:', err);
  } finally {
    isFetchingBackend = false;
  }
}

function formatTimeAgo(dateStr) {
  if (!dateStr) return 'Recently';
  try {
    const diff = (new Date() - new Date(dateStr.replace(/-/g, '/'))) / 1000;
    if (isNaN(diff) || diff < 0) return 'Recently';
    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return `${Math.floor(diff / 604800)}w ago`;
  } catch(e) {
    return 'Recently';
  }
}

// Active Drawer & Filtered Items State
let currentFilteredItems = [];
let currentlyOpenItemId = null;
let lastFocusedCardElement = null;
let drawerOverlay = null;
let itemDrawer = null;

function initDomElements() {
  searchInput   = document.getElementById('searchInput');
  clearBtn      = document.getElementById('clearSearchBtn');
  searchSpinner = document.getElementById('searchBarSpinner');
  resultsGrid   = document.getElementById('searchResultsGrid');
  resultsCount  = document.getElementById('resultsCount');
  catChips      = document.querySelectorAll('.cat-chip');
  typeSelect    = document.getElementById('typeSelect');
  categorySelect= document.getElementById('categorySelect');
  locationSelect= document.getElementById('locationSelect');
  sortSelect    = document.getElementById('sortSelect');
  drawerOverlay = document.getElementById('itemDrawerOverlay');
  itemDrawer    = document.getElementById('itemDrawer');
  aiDrawer      = document.getElementById('aiMatchDrawer');
  aiMatchBtn    = document.getElementById('aiMatchToggleBtn');
  aiTextarea    = document.getElementById('aiSmartQuery');
  aiTagsDetected= document.getElementById('aiTagsDetected');

  // Read URL query parameters or select value on page load
  const urlParams = new URLSearchParams(window.location.search);
  const paramType = urlParams.get('type');
  if (paramType && ['all', 'found', 'lost', 'reunited'].includes(paramType.toLowerCase())) {
    currentTypeFilter = paramType.toLowerCase();
    if (typeSelect) typeSelect.value = currentTypeFilter;
  } else if (typeSelect && typeSelect.value) {
    currentTypeFilter = typeSelect.value;
  } else {
    currentTypeFilter = 'found';
  }

  const paramCategory = urlParams.get('category');
  if (paramCategory) {
    currentCategory = paramCategory;
    if (categorySelect) categorySelect.value = currentCategory;
    catChips.forEach(c => c.classList.toggle('active', c.dataset.category === currentCategory));
  }
}

function bindEvents() {
  // Keyword Search with debounce & inline spinner
  if (searchInput) {
    let debounceTimer;
    searchInput.addEventListener('input', (e) => {
      clearTimeout(debounceTimer);
      const query = e.target.value.trim();
      if (clearBtn) clearBtn.classList.toggle('visible', query.length > 0);
      if (searchSpinner) searchSpinner.classList.add('active');

      debounceTimer = setTimeout(() => {
        currentSearchQuery = query.toLowerCase();
        if (isAiMatchActive) calculateLocalAiScores(currentSearchQuery);
        renderResults();
        if (searchSpinner) searchSpinner.classList.remove('active');
      }, 250);
    });
  }

  // Clear Search
  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      if (searchInput) searchInput.value = '';
      clearBtn.classList.remove('visible');
      currentSearchQuery = '';
      if (searchSpinner) searchSpinner.classList.remove('active');
      renderResults();
    });
  }

  // Listing Type (sidebar dropdown)
  if (typeSelect) {
    typeSelect.addEventListener('change', (e) => {
      currentTypeFilter = e.target.value;
      renderResults();
    });
  }

  // Category Chips (top bar)
  catChips.forEach(chip => {
    chip.addEventListener('click', () => {
      catChips.forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      const cat = chip.dataset.category;
      currentCategory = cat;
      if (categorySelect) {
        categorySelect.value = cat === 'all' ? 'all' : (categorySelect.querySelector(`option[value="${cat}"]`) ? cat : 'all');
      }
      renderResults();
    });
  });

  // Category Select (sidebar dropdown)
  if (categorySelect) {
    categorySelect.addEventListener('change', (e) => {
      currentCategory = e.target.value;
      catChips.forEach(c => c.classList.toggle('active', c.dataset.category === currentCategory));
      renderResults();
    });
  }

  // Location Select
  if (locationSelect) {
    locationSelect.addEventListener('change', (e) => {
      currentLocation = e.target.value;
      renderResults();
    });
  }

  // Sort Select
  if (sortSelect) {
    sortSelect.addEventListener('change', (e) => {
      currentSort = e.target.value;
      renderResults();
    });
  }

  // Reset Filters
  const resetBtn = document.getElementById('resetFiltersBtn');
  if (resetBtn) resetBtn.addEventListener('click', resetAllFilters);

  // View Toggle (Grid / List)
  const btnGrid = document.getElementById('viewGrid');
  const btnList = document.getElementById('viewList');
  if (btnGrid && btnList && resultsGrid) {
    btnGrid.addEventListener('click', () => {
      isListView = false;
      resultsGrid.classList.remove('list-view');
      btnGrid.classList.add('active');
      btnList.classList.remove('active');
    });
    btnList.addEventListener('click', () => {
      isListView = true;
      resultsGrid.classList.add('list-view');
      btnList.classList.add('active');
      btnGrid.classList.remove('active');
    });
  }

  // Smart AI Match Toggle
  if (aiMatchBtn) {
    aiMatchBtn.addEventListener('click', () => {
      isAiMatchActive = !isAiMatchActive;
      aiMatchBtn.classList.toggle('active', isAiMatchActive);
      if (aiDrawer) aiDrawer.classList.toggle('open', isAiMatchActive);
      if (!isAiMatchActive) {
        aiMatchScores = {};
        renderResults();
      }
    });
  }

  // AI Smart Description Match Submit
  const aiSubmitBtn = document.getElementById('btnSubmitAiMatch');
  if (aiSubmitBtn) {
    aiSubmitBtn.addEventListener('click', () => {
      if (aiTextarea) {
        const text = aiTextarea.value.trim();
        if (text) {
          calculateLocalAiScores(text.toLowerCase());
          currentSort = 'match';
          if (sortSelect) sortSelect.value = 'match';
          renderResults();
        }
      }
    });
  }

  // Overlay Click to Close Drawer
  if (drawerOverlay) {
    drawerOverlay.addEventListener('click', () => {
      closeItemDrawer();
    });
  }

  // Browser Back/Forward Support (popstate)
  window.addEventListener('popstate', (e) => {
    const urlParams = new URLSearchParams(window.location.search);
    const targetId = urlParams.get('item') || urlParams.get('item_id') || urlParams.get('id');
    if (targetId) {
      openItemDrawer(targetId, { pushHistory: false });
    } else {
      closeItemDrawer({ pushHistory: false });
    }
  });

  // Global Keyboard Shortcuts (Esc, Arrow Keys / J / K)
  document.addEventListener('keydown', (e) => {
    // Check if lightbox is open
    const lightbox = document.getElementById('reuniteLightboxOverlay');
    if (lightbox && lightbox.classList.contains('active')) {
      if (e.key === 'Escape') closeLightbox();
      return;
    }

    // If drawer is open
    if (itemDrawer && itemDrawer.classList.contains('open')) {
      const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
      const isTyping = activeTag === 'input' || activeTag === 'textarea' || activeTag === 'select';

      if (e.key === 'Escape') {
        closeItemDrawer();
        return;
      }

      if (!isTyping) {
        if (e.key === 'ArrowUp' || e.key === 'ArrowLeft' || e.key === 'k' || e.key === 'K') {
          e.preventDefault();
          navigateDrawerItem(-1);
        } else if (e.key === 'ArrowDown' || e.key === 'ArrowRight' || e.key === 'j' || e.key === 'J') {
          e.preventDefault();
          navigateDrawerItem(1);
        }
      }
    }
  });
}

/**
 * Filter, sort, and render items
 */
function renderResults() {
  if (!resultsGrid) return;

  let filtered = ITEMS_DATABASE.filter(item => {
    if (currentTypeFilter !== 'all' && item.type !== currentTypeFilter) return false;
    if (currentCategory !== 'all' && item.category !== currentCategory) return false;
    if (currentLocation !== 'all' && !item.location.toLowerCase().includes(currentLocation.toLowerCase())) return false;
    if (currentSearchQuery) {
      const matchText = `${item.title} ${item.desc} ${item.category} ${item.location} ${item.id} ${JSON.stringify(item.dna || {})}`.toLowerCase();
      if (!matchText.includes(currentSearchQuery)) return false;
    }
    return true;
  });

  filtered.sort((a, b) => {
    if (currentSort === 'match') {
      const scoreA = aiMatchScores[a.id] || 0;
      const scoreB = aiMatchScores[b.id] || 0;
      return scoreB - scoreA;
    } else if (currentSort === 'newest') {
      return new Date(b.date) - new Date(a.date);
    } else if (currentSort === 'oldest') {
      return new Date(a.date) - new Date(b.date);
    } else if (currentSort === 'location') {
      return a.location.localeCompare(b.location);
    }
    return 0;
  });

  currentFilteredItems = filtered;

  if (resultsCount) {
    const typeLabel = currentTypeFilter === 'found' ? 'found' : currentTypeFilter === 'lost' ? 'lost' : 'community';
    resultsCount.innerHTML = `Showing <strong class="results-count-strong">${filtered.length}</strong> ${typeLabel} item${filtered.length === 1 ? '' : 's'}`;
  }

  if (filtered.length === 0) {
    resultsGrid.innerHTML = `
      <div class="search-empty-state">
        <div class="empty-icon"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div>
        <div class="empty-title">No matching items found</div>
        <p class="empty-sub">
          We couldn't find any reports matching your search. Try adjusting your filters, searching for broader keywords, or report your missing item so our AI can notify you when found.
        </p>
        <div class="empty-ctas">
          <a href="report-lost-item.php" class="btn-empty-primary">+ Report Missing Item</a>
          <button type="button" class="btn-empty-secondary" onclick="resetAllFilters()">Clear All Filters</button>
        </div>
      </div>
    `;
    return;
  }

  resultsGrid.innerHTML = filtered.map(item => {
    const matchScore = aiMatchScores[item.id];
    const matchBadgeHtml = (isAiMatchActive && matchScore) 
      ? `<span class="card-badge-match">${matchScore}% Match</span>` 
      : '';

    const scoreBarHtml = (isAiMatchActive && matchScore) ? `
      <div class="match-score-bar-wrap" title="AI Match Confidence Score: ${matchScore}%">
        <span class="match-score-text">${matchScore}% Confidence</span>
        <div class="match-score-track">
          <div class="match-score-fill" style="width: ${matchScore}%;"></div>
        </div>
      </div>
    ` : '';

    const isFound = item.type === 'found';
    const isReunited = item.status === 'claimed' || item.status === 'reunited' || item.status === 'closed';

    let badgeClass = 'badge-found';
    let badgeLabel = 'Found Item';
    if (isReunited) {
      badgeClass = 'badge-reunited';
      badgeLabel = 'Reunited';
    } else if (!isFound) {
      badgeClass = 'badge-lost';
      badgeLabel = 'Lost Item';
    }

    const miniTags = (item.dna && item.dna.features) 
      ? item.dna.features.slice(0, 2).map(f => `<span class="dna-mini-tag">${f}</span>`).join('') 
      : '';

    const isSaved = window.ReuniteBookmarks ? window.ReuniteBookmarks.isSaved(item.id) : false;
    const isActive = (currentlyOpenItemId === item.id);

    return `
      <article class="search-item-card ${isActive ? 'active-drawer-card' : ''}" data-id="${item.id}" onclick="window.location.href='item.php?id=${item.id}'" tabindex="0" aria-label="View details for ${item.title}">
        <div class="card-img-wrap">
          <img src="${item.img}" alt="${item.title}" class="card-img" loading="lazy" onload="this.classList.add('loaded')" />
          <span class="card-badge-status ${badgeClass}">${badgeLabel}</span>
          <button type="button" class="btn-bookmark card-bookmark-btn ${isSaved ? 'active' : ''}" data-id="${item.id}" onclick="handleCardBookmark(event, '${item.id}', '${item.title.replace(/'/g, "\\'")}')" aria-label="Save item" title="${isSaved ? 'Remove from saved' : 'Save this item'}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg>
          </button>
          ${matchBadgeHtml}
        </div>
        <div class="card-body">
          <span class="card-category">${item.category}</span>
          <h3 class="card-title">${item.title}</h3>
          <p class="card-desc">${item.desc}</p>
          ${scoreBarHtml}
          <div class="card-dna-tags">${miniTags}</div>
          <div class="card-foot">
            <div class="card-foot-meta">
              <span class="card-loc">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                ${item.location}
              </span>
              <span class="card-time">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                ${item.timeAgo}
              </span>
            </div>
            <button type="button" class="btn-card-action" aria-hidden="true">View details &rarr;</button>
          </div>
        </div>
      </article>
    `;
  }).join('');
}

window.handleCardBookmark = function(e, itemId, itemTitle) {
  e.stopPropagation();
  if (window.ReuniteBookmarks) {
    window.ReuniteBookmarks.toggle(itemId, itemTitle);
  }
};

// ══════════════════════════════════════════════════════
// RIGHT-SIDE SLIDE-OVER DRAWER CONTROLLER
// ══════════════════════════════════════════════════════

/**
 * Returns clean Lucide SVG line icon for a category (no emojis)
 */
function getCategoryLineIcon(category) {
  const cat = String(category || '').toLowerCase();
  if (cat.includes('watch') || cat.includes('wearable')) {
    return `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="7"/><polyline points="12 9 12 12 13.5 13.5"/><path d="M16.51 17.35l-.85 3.86a2 2 0 0 1-1.96 1.57H10.3a2 2 0 0 1-1.96-1.57l-.85-3.86"/><path d="M7.49 6.65l.85-3.86A2 2 0 0 1 10.3 1.22h3.4a2 2 0 0 1 1.96 1.57l.85 3.86"/></svg>`;
  }
  if (cat.includes('electr') || cat.includes('laptop') || cat.includes('phone') || cat.includes('earbud')) {
    return `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="12" x="3" y="4" rx="2"/><line x1="2" x2="22" y1="20" y2="20"/></svg>`;
  }
  if (cat.includes('wallet') || cat.includes('bag') || cat.includes('purse')) {
    return `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>`;
  }
  if (cat.includes('key')) {
    return `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/></svg>`;
  }
  if (cat.includes('doc') || cat.includes('id') || cat.includes('card')) {
    return `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="14" x="3" y="5" rx="2"/><path d="M7 15h4M7 11h2"/><circle cx="16" cy="11" r="2"/></svg>`;
  }
  if (cat.includes('cloth') || cat.includes('jacket') || cat.includes('apparel')) {
    return `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"/></svg>`;
  }
  if (cat.includes('book') || cat.includes('station')) {
    return `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M6 6h10M6 10h10"/></svg>`;
  }
  return `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>`;
}

/**
 * Derives a human, natural title for an item without repeating category
 */
function getSmartItemTitle(item) {
  if (item.title && item.title.trim() !== '' && 
      item.title.toLowerCase() !== item.category.toLowerCase() && 
      !item.title.toLowerCase().startsWith('found item') && 
      !item.title.toLowerCase().startsWith('lost item')) {
    return item.title;
  }

  const desc = item.desc || '';
  const dna = item.dna || {};
  const brand = dna.brand || '';
  const color = dna.color || '';

  const matchColor = desc.match(/\b(black|silver|white|gold|rose gold|blue|red|green|grey|gray|brown|matte black)\b/i);
  const foundColor = color || (matchColor ? matchColor[0] : '');

  const matchBrand = desc.match(/\b(peter england|apple|samsung|casio|fastrack|titan|fossil|boat|noise|dell|hp|lenovo|sony|nike|adidas|wildcraft)\b/i);
  const foundBrand = brand || (matchBrand ? matchBrand[0] : '');

  let baseNoun = 'Item';
  const cat = (item.category || '').toLowerCase();
  if (cat.includes('watch')) baseNoun = 'Wristwatch';
  else if (cat.includes('laptop')) baseNoun = 'Laptop';
  else if (cat.includes('phone')) baseNoun = 'Smartphone';
  else if (cat.includes('earbud')) baseNoun = 'Earbuds';
  else if (cat.includes('wallet')) baseNoun = 'Wallet';
  else if (cat.includes('bag')) baseNoun = 'Bag';
  else if (cat.includes('key')) baseNoun = 'Keys';
  else if (cat.includes('id') || cat.includes('card')) baseNoun = 'Identity Card';
  else if (item.category) baseNoun = item.category.replace(/&.*/, '').trim();

  const parts = [];
  if (foundColor) parts.push(foundColor.charAt(0).toUpperCase() + foundColor.slice(1));
  if (foundBrand) parts.push(foundBrand.charAt(0).toUpperCase() + foundBrand.slice(1));
  parts.push(baseNoun);

  return parts.join(' ');
}

/**
 * Extracts distinguishing details list
 */
function getDistinguishingDetails(item) {
  const details = [];
  const dna = item.dna || {};
  const desc = item.desc || '';

  if (dna.brand) details.push({ label: 'Brand', val: dna.brand });
  if (dna.color) details.push({ label: 'Color', val: dna.color });
  if (dna.model) details.push({ label: 'Model', val: dna.model });

  if (/strap/i.test(desc)) {
    const strapMatch = desc.match(/(metal|leather|silicone|chain|mesh)\s+strap/i);
    if (strapMatch) details.push({ label: 'Strap', val: strapMatch[0] });
  }
  if (/dial|face/i.test(desc)) {
    const dialMatch = desc.match(/(roman|analog|digital|chronograph|black|blue|white)\s+dial/i);
    if (dialMatch) details.push({ label: 'Dial', val: dialMatch[0] });
  }
  if (/scratch|mark|engrav/i.test(desc)) {
    details.push({ label: 'Markings', val: 'Has distinctive markings' });
  }

  if (dna.features && Array.isArray(dna.features)) {
    dna.features.forEach(f => {
      if (!details.some(d => d.val.toLowerCase() === f.toLowerCase())) {
        details.push({ label: 'Feature', val: f });
      }
    });
  }

  return details;
}

/**
 * Opens slide-over drawer for an item
 */
window.openItemDrawer = function(itemId, options = {}) {
  const { pushHistory = true } = options;
  lastFocusedCardElement = document.querySelector(`.search-item-card[data-id="${itemId}"]`);

  const item = ITEMS_DATABASE.find(i => i.id === itemId);
  if (!item || !itemDrawer) return;

  currentlyOpenItemId = itemId;

  // Highlight active card in grid
  document.querySelectorAll('.search-item-card').forEach(c => {
    c.classList.toggle('active-drawer-card', c.dataset.id === itemId);
  });

  // Update browser URL
  if (pushHistory) {
    const newUrl = new URL(window.location);
    newUrl.searchParams.set('item', itemId);
    history.replaceState({ itemId }, '', newUrl.toString());
  }

  // Find index in current filtered list for next/prev navigation
  const currentIndex = currentFilteredItems.findIndex(i => i.id === itemId);
  const hasPrev = currentIndex > 0;
  const hasNext = currentIndex >= 0 && currentIndex < currentFilteredItems.length - 1;

  // Preload neighboring images when idle
  if (hasPrev && currentFilteredItems[currentIndex - 1].img) {
    const imgPrev = new Image(); imgPrev.src = currentFilteredItems[currentIndex - 1].img;
  }
  if (hasNext && currentFilteredItems[currentIndex + 1].img) {
    const imgNext = new Image(); imgNext.src = currentFilteredItems[currentIndex + 1].img;
  }

  const isReunited = item.is_reunited || item.status === 'reunited' || item.status === 'claimed';
  const isFound = item.type === 'found' || item.status === 'found';
  
  let badgeClass = 'badge-found';
  let badgeLabel = 'Found';
  if (isReunited) {
    badgeClass = 'badge-reunited';
    badgeLabel = 'Reunited';
  } else if (!isFound) {
    badgeClass = 'badge-lost';
    badgeLabel = 'Lost';
  }

  const smartTitle = getSmartItemTitle(item);
  const categoryIcon = getCategoryLineIcon(item.category);
  const detailsList = getDistinguishingDetails(item);

  // AI Match Score check
  const matchScore = aiMatchScores[item.id] || (window.location.search.includes('match') ? 94 : null);
  const visualScore = matchScore ? Math.min(99, Math.round(matchScore * 0.96)) : null;
  const textScore = matchScore ? Math.min(99, Math.round(matchScore * 1.02)) : null;

  let aiMatchPanelHtml = '';
  if (matchScore) {
    aiMatchPanelHtml = `
      <div class="drawer-ai-match-panel" role="region" aria-label="AI Multimodal Match Breakdown">
        <div class="ai-match-panel-header">
          <div class="ai-match-panel-title">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
            AI Matchmaking Analysis
          </div>
          <span class="ai-match-total-badge">${matchScore}% Confidence</span>
        </div>
        <div class="ai-match-rings-grid">
          <div class="ai-match-ring-card">
            <div class="ring-circle-wrap">
              <svg viewBox="0 0 36 36" class="circular-chart terracotta">
                <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                <path class="circle" stroke-dasharray="${matchScore}, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
              </svg>
              <span class="ring-number">${matchScore}%</span>
            </div>
            <span class="ring-label">Overall Match</span>
          </div>
          <div class="ai-match-ring-card">
            <div class="ring-circle-wrap">
              <svg viewBox="0 0 36 36" class="circular-chart blue">
                <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                <path class="circle" stroke-dasharray="${visualScore}, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
              </svg>
              <span class="ring-number">${visualScore}%</span>
            </div>
            <span class="ring-label">Visual DINOv2</span>
          </div>
          <div class="ai-match-ring-card">
            <div class="ring-circle-wrap">
              <svg viewBox="0 0 36 36" class="circular-chart emerald">
                <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                <path class="circle" stroke-dasharray="${textScore}, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
              </svg>
              <span class="ring-number">${textScore}%</span>
            </div>
            <span class="ring-label">Semantic CLIP</span>
          </div>
        </div>
      </div>
    `;
  }

  itemDrawer.innerHTML = `
    <!-- Mobile Bottom Sheet Handle -->
    <div class="bottom-sheet-handle" aria-hidden="true"></div>

    <!-- ── 1. Sticky Header ── -->
    <header class="drawer-sticky-header">
      <div class="drawer-header-left">
        <div class="category-line-chip">
          ${categoryIcon}
          <span>${item.category}</span>
        </div>
        <div class="report-id-copy-wrap">
          <span class="report-id-text">#${item.id}</span>
          <button type="button" class="btn-copy-id" onclick="copyReportId('${item.id}', this)" aria-label="Copy item ID" title="Copy ID">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
            <span class="copy-feedback-label">Copy</span>
          </button>
        </div>
      </div>
      <div class="drawer-header-right">
        <div class="drawer-nav-group">
          <button type="button" class="drawer-nav-arrow" onclick="navigateDrawerItem(-1)" title="Previous item (K or Up)" ${!hasPrev ? 'disabled' : ''} aria-label="Previous item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <button type="button" class="drawer-nav-arrow" onclick="navigateDrawerItem(1)" title="Next item (J or Down)" ${!hasNext ? 'disabled' : ''} aria-label="Next item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>
        <button type="button" class="drawer-close-btn" onclick="closeItemDrawer()" aria-label="Close details drawer">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
      </div>
    </header>

    <!-- ── Scrollable Body Stage ── -->
    <div class="drawer-scroll-body">
      
      <!-- 2. Photo Stage (Uncropped, object-fit: contain) -->
      <div class="drawer-media-wrap">
        <div class="drawer-img-container" onclick="openLightbox('${item.img}', '${smartTitle.replace(/'/g, "\\'")}')" title="Click to zoom image">
          <div class="image-skeleton-shimmer" id="drawerImgSkeleton" aria-hidden="true"></div>
          <img 
            src="${item.img}" 
            alt="${smartTitle}" 
            class="drawer-main-img" 
            loading="eager"
            onload="document.getElementById('drawerImgSkeleton')?.remove(); this.classList.add('loaded');"
            onerror="document.getElementById('drawerImgSkeleton')?.remove(); this.src='https://images.unsplash.com/photo-1584438784894-089d6a62b8fa?w=640&h=420&fit=crop&auto=format';"
          />
          <span class="drawer-status-badge ${badgeClass}">${badgeLabel}</span>
          <button type="button" class="btn-drawer-zoom" onclick="event.stopPropagation(); openLightbox('${item.img}', '${smartTitle.replace(/'/g, "\\'")}')" aria-label="Zoom image">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
            <span>Zoom</span>
          </button>
        </div>
        <div class="drawer-thumb-strip">
          <button type="button" class="drawer-thumb-btn active" aria-label="Main photo preview">
            <img src="${item.img}" alt="Thumb" />
          </button>
        </div>
      </div>

      <!-- 3. Title in Serif Heading Font -->
      <div class="drawer-title-row">
        <h2 class="drawer-item-title" id="modalItemTitle">${smartTitle}</h2>
      </div>

      <!-- 4. Compact Meta Row (Location, Reported date with relative time) -->
      <div class="drawer-meta-row">
        <div class="meta-pill-item">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
          <span>${item.location}</span>
        </div>
        <div class="meta-row-bullet" aria-hidden="true">•</div>
        <div class="meta-pill-item">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <span>${item.date} <small>(${item.timeAgo})</small></span>
        </div>
      </div>

      <!-- 7. AI Match Panel (if match active) -->
      ${aiMatchPanelHtml}

      <!-- 5. Description Section -->
      <div class="drawer-section">
        <div class="drawer-section-heading">Description</div>
        <div class="drawer-desc-card">
          ${item.desc || 'No additional physical description recorded for this report.'}
        </div>
      </div>

      <!-- 6. Distinguishing Details Section -->
      ${detailsList.length > 0 ? `
        <div class="drawer-section">
          <div class="drawer-section-heading">Distinguishing Details</div>
          <div class="drawer-details-chips">
            ${detailsList.map(d => `
              <div class="drawer-detail-chip">
                <span class="chip-label">${d.label}:</span>
                <span class="chip-val">${d.val}</span>
              </div>
            `).join('')}
          </div>
        </div>
      ` : ''}

      <!-- Expandable Claim Verification Form -->
      <div class="claim-form-wrap" id="claimFormWrap" aria-live="polite">
        <div class="claim-form-header">
          <div class="claim-form-title">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Ownership Verification
          </div>
          <button type="button" class="btn-claim-close" onclick="toggleClaimForm('${item.id}')" aria-label="Cancel claim">&times;</button>
        </div>
        <p class="claim-form-sub">
          To claim this item, please provide a private detail only the rightful owner would know (e.g. wallpaper description, unique scratch, serial number, or exact contents).
        </p>
        <form id="itemClaimForm" onsubmit="handleClaimSubmit(event, '${item.id}')">
          <div class="claim-input-group">
            <label for="claimName">Full Name / Student PIN</label>
            <input type="text" id="claimName" class="claim-input" placeholder="e.g. Alex Rivera / 21CS042" required />
          </div>
          <div class="claim-input-group">
            <label for="claimEmail">College Email Address</label>
            <input type="email" id="claimEmail" class="claim-input" placeholder="e.g. alex.rivera@campus.edu" required />
          </div>
          <div class="claim-input-group">
            <label for="claimProof">Private Proof of Ownership</label>
            <textarea id="claimProof" class="claim-input" rows="3" placeholder="Describe lock-screen wallpaper, serial number, inner compartment contents, or unique scratches" required></textarea>
          </div>
          <button type="submit" id="btnClaimSubmitAction" class="btn-modal-claim" style="width: 100%;">
            Submit Verification Proof &rarr;
          </button>
        </form>
      </div>

    </div>

    <!-- ── 9. Sticky Footer (Never Scrolls Away) ── -->
    <footer class="drawer-sticky-footer">
      <div class="drawer-privacy-hint">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <span>Some details are hidden to verify the rightful owner.</span>
      </div>
      <div class="drawer-footer-actions">
        ${isFound && !isReunited ? `
          <button type="button" class="btn-drawer-claim-pill" onclick="toggleClaimForm('${item.id}')">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>This is mine, claim it</span>
          </button>
        ` : `
          <button type="button" class="btn-drawer-claim-pill secondary" onclick="closeItemDrawer()">
            <span>Close Details</span>
          </button>
        `}
        <div class="drawer-icon-buttons">
          <button type="button" class="btn-drawer-icon" onclick="shareItem('${item.id}', '${smartTitle.replace(/'/g, "\\'")}')" aria-label="Share report" title="Share report">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
          </button>
          <button type="button" class="btn-drawer-icon" onclick="flagItem('${item.id}')" aria-label="Report issue" title="Report issue / Sighting">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
          </button>
        </div>
      </div>
    </footer>
  `;

  itemDrawer.classList.add('open');
  if (drawerOverlay) drawerOverlay.classList.add('open');
  itemDrawer.setAttribute('aria-hidden', 'false');

  const firstFocusable = itemDrawer.querySelector('button, [href], input, select, textarea');
  if (firstFocusable) firstFocusable.focus();
};

/**
 * Closes the slide-over drawer and cleans URL state
 */
window.closeItemDrawer = function(options = {}) {
  const { pushHistory = true } = options;

  if (itemDrawer) {
    itemDrawer.classList.remove('open');
    itemDrawer.setAttribute('aria-hidden', 'true');
  }
  if (drawerOverlay) {
    drawerOverlay.classList.remove('open');
  }

  currentlyOpenItemId = null;
  document.querySelectorAll('.search-item-card').forEach(c => c.classList.remove('active-drawer-card'));

  if (pushHistory) {
    const newUrl = new URL(window.location);
    newUrl.searchParams.delete('item');
    newUrl.searchParams.delete('item_id');
    newUrl.searchParams.delete('id');
    history.replaceState({}, '', newUrl.toString());
  }

  if (lastFocusedCardElement && typeof lastFocusedCardElement.focus === 'function') {
    lastFocusedCardElement.focus();
  }
};

/**
 * Navigate through filtered items list
 */
window.navigateDrawerItem = function(direction) {
  if (!currentlyOpenItemId || currentFilteredItems.length === 0) return;
  const currentIndex = currentFilteredItems.findIndex(i => i.id === currentlyOpenItemId);
  if (currentIndex === -1) return;

  const targetIndex = currentIndex + direction;
  if (targetIndex >= 0 && targetIndex < currentFilteredItems.length) {
    openItemDrawer(currentFilteredItems[targetIndex].id);
  }
};

// Aliases for backward compatibility
window.openItemModal = window.openItemDrawer;
window.closeModal = window.closeItemDrawer;

/**
 * Copy Report ID with tooltip feedback
 */
window.copyReportId = function(id, btn) {
  const textToCopy = id.startsWith('#') ? id : `#${id}`;
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(textToCopy);
  }
  
  if (btn) {
    btn.classList.add('copied');
    const label = btn.querySelector('.copy-feedback-label');
    if (label) label.textContent = 'Copied!';
    setTimeout(() => {
      btn.classList.remove('copied');
      if (label) label.textContent = 'Copy';
    }, 2000);
  }

  if (window.ReuniteToast) {
    window.ReuniteToast.info('Copied', `Report ID ${textToCopy} copied to clipboard.`);
  }
};

/**
 * Opens image in full-screen Lightbox
 */
window.openLightbox = function(imgSrc, title) {
  let lightbox = document.getElementById('reuniteLightboxOverlay');
  if (!lightbox) {
    lightbox = document.createElement('div');
    lightbox.id = 'reuniteLightboxOverlay';
    lightbox.className = 'lightbox-overlay';
    lightbox.setAttribute('role', 'dialog');
    lightbox.setAttribute('aria-modal', 'true');
    lightbox.innerHTML = `
      <div class="lightbox-container" onclick="event.stopPropagation()">
        <button type="button" class="lightbox-close-btn" onclick="closeLightbox()" aria-label="Close lightbox">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
        <img src="" alt="Full view" id="lightboxImg" class="lightbox-img" />
        <div class="lightbox-caption" id="lightboxCaption"></div>
      </div>
    `;
    lightbox.addEventListener('click', closeLightbox);
    document.body.appendChild(lightbox);
  }

  const img = document.getElementById('lightboxImg');
  const caption = document.getElementById('lightboxCaption');
  if (img) img.src = imgSrc;
  if (caption) caption.textContent = title || 'Item Photo';

  lightbox.classList.add('active');
};

window.closeLightbox = function() {
  const lightbox = document.getElementById('reuniteLightboxOverlay');
  if (lightbox) {
    lightbox.classList.remove('active');
  }
};

/**
 * Share item link or native Web Share
 */
window.shareItem = function(itemId, title) {
  const shareUrl = `${window.location.origin}${window.location.pathname}?item=${itemId}`;
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
      window.ReuniteToast.success('Link Copied', `Direct link to #${itemId} copied to clipboard.`);
    }
  }
};

/**
 * Report / Flag item
 */
window.flagItem = function(itemId) {
  if (window.ReuniteToast) {
    window.ReuniteToast.info('Report Sighting', `To submit a sighting or flag #${itemId}, visit the Campus Hub or contact Lost & Found security.`);
  }
};

/**
 * Toggle claim verification form inside drawer
 */
window.toggleClaimForm = function(itemId) {
  const formWrap = document.getElementById('claimFormWrap');
  if (formWrap) {
    formWrap.classList.toggle('active');
    if (formWrap.classList.contains('active')) {
      formWrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      const input = formWrap.querySelector('input');
      if (input) input.focus();
    }
  }
};

/**
 * Handle claim submission
 */
window.handleClaimSubmit = function(e, itemId) {
  e.preventDefault();
  const formWrap = document.getElementById('claimFormWrap');
  const submitBtn = document.getElementById('btnClaimSubmitAction');
  
  if (submitBtn && window.setButtonLoading) {
    window.setButtonLoading(submitBtn, true, 'Verifying proof...');
  }

  setTimeout(() => {
    if (formWrap) {
      formWrap.innerHTML = `
        <div class="claim-success-box">
          <div class="claim-checkmark-circle" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
          </div>
          <h4 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--ink);">Verification Submitted</h4>
          <p style="font-size: 0.825rem; color: var(--muted); line-height: 1.5; margin-bottom: 1rem;">
            Your ownership proof for <strong>#${itemId}</strong> has been logged. The finder or campus coordinator will review the hidden details and reach out to your registered college email.
          </p>
          <button type="button" class="btn-drawer-claim-pill" style="width: auto; padding: 0.55rem 1.5rem;" onclick="closeItemDrawer()">Done</button>
        </div>
      `;
    }
    if (window.ReuniteToast) {
      window.ReuniteToast.success('Claim Logged', `Verification request for #${itemId} submitted successfully.`, 4000);
    }
  }, 500);
};

// Check for deep link query parameters (?item=... or ?match_id=...) on page load
window.addEventListener('load', () => {
  const urlParams = new URLSearchParams(window.location.search);
  const targetId = urlParams.get('item') || urlParams.get('item_id') || urlParams.get('id') || urlParams.get('match_id');
  if (targetId) {
    setTimeout(() => {
      const match = ITEMS_DATABASE.find(i => i.id.toLowerCase() === targetId.toLowerCase() || i.id.toLowerCase().includes(targetId.toLowerCase()));
      if (match) {
        openItemDrawer(match.id, { pushHistory: false });
      }
    }, 350);
  }
});

window.resetAllFilters = resetAllFilters;

function resetAllFilters() {
  currentSearchQuery = '';
  currentTypeFilter = 'found'; // Reset to default Found Items
  currentCategory = 'all';
  currentLocation = 'all';
  currentSort = 'newest';
  isAiMatchActive = false;
  aiMatchScores = {};

  if (searchInput) searchInput.value = '';
  if (clearBtn) clearBtn.classList.remove('visible');
  if (typeSelect) typeSelect.value = 'found';
  if (categorySelect) categorySelect.value = 'all';
  if (locationSelect) locationSelect.value = 'all';
  if (sortSelect) sortSelect.value = 'newest';

  catChips.forEach(c => c.classList.toggle('active', c.dataset.category === 'all'));

  if (aiMatchBtn) aiMatchBtn.classList.remove('active');
  if (aiDrawer) aiDrawer.classList.remove('open');
  if (aiTextarea) aiTextarea.value = '';

  renderResults();
}

/**
 * Calculates simulated AI Digital DNA similarity confidence for search results.
 */
function calculateLocalAiScores(description) {
  aiMatchScores = {};
  const tags = [];
  const words = description.split(/\s+/).filter(w => w.length > 2);

  ITEMS_DATABASE.forEach(item => {
    let score = 30; // base confidence
    const searchTarget = `${item.title} ${item.desc} ${item.category} ${item.location} ${JSON.stringify(item.dna || {})}`.toLowerCase();

    // Check keyword hits
    words.forEach(word => {
      if (searchTarget.includes(word)) {
        score += 15;
      }
    });

    // Color & Brand boost
    if (item.dna && item.dna.color && description.includes(item.dna.color.toLowerCase())) {
      score += 20;
      if (!tags.includes(`Color: ${item.dna.color}`)) tags.push(`Color: ${item.dna.color}`);
    }
    if (item.dna && item.dna.brand && description.includes(item.dna.brand.toLowerCase())) {
      score += 25;
      if (!tags.includes(`Brand: ${item.dna.brand}`)) tags.push(`Brand: ${item.dna.brand}`);
    }

    // Clamp score
    score = Math.min(99, Math.max(25, score));
    aiMatchScores[item.id] = score;
  });

  if (aiTagsDetected && tags.length > 0) {
    aiTagsDetected.innerHTML = tags.map(t => `<span class="modal-dna-chip">${t}</span>`).join('');
  }
}

// Mobile Touch Swipe-Down to Dismiss Bottom Sheet
let touchStartY = 0;
let touchCurrentY = 0;

if (itemDrawer) {
  itemDrawer.addEventListener('touchstart', (e) => {
    if (window.innerWidth > 768) return;
    const scrollBody = itemDrawer.querySelector('.drawer-scroll-body');
    if (scrollBody && scrollBody.scrollTop === 0) {
      touchStartY = e.touches[0].clientY;
      touchCurrentY = touchStartY;
    } else {
      touchStartY = 0;
    }
  }, { passive: true });

  itemDrawer.addEventListener('touchmove', (e) => {
    if (!touchStartY || window.innerWidth > 768) return;
    touchCurrentY = e.touches[0].clientY;
    const diff = touchCurrentY - touchStartY;
    if (diff > 0) {
      itemDrawer.style.transform = `translateY(${diff}px)`;
    }
  }, { passive: true });

  itemDrawer.addEventListener('touchend', () => {
    if (!touchStartY || window.innerWidth > 768) return;
    const diff = touchCurrentY - touchStartY;
    itemDrawer.style.transform = '';
    if (diff > 100) {
      closeItemDrawer();
    }
    touchStartY = 0;
    touchCurrentY = 0;
  });
}
