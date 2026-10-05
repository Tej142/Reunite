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
    return `${Math.floor(diff / 86400)}d ago`;
  } catch (e) {
    return 'Recently';
  }
}

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
  modalOverlay  = document.getElementById('itemModalOverlay');
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
      // Sync sidebar category select
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

  // Modal Close Events
  if (modalOverlay) {
    modalOverlay.addEventListener('click', (e) => {
      if (e.target === modalOverlay || e.target.closest('.modal-close-btn')) {
        closeModal();
      }
    });
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modalOverlay && modalOverlay.classList.contains('active')) {
      closeModal();
    }
  });
}

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
      if (!tags.includes(`🎨 Color: ${item.dna.color}`)) tags.push(`🎨 Color: ${item.dna.color}`);
    }
    if (item.dna && item.dna.brand && description.includes(item.dna.brand.toLowerCase())) {
      score += 25;
      if (!tags.includes(`🏷️ Brand: ${item.dna.brand}`)) tags.push(`🏷️ Brand: ${item.dna.brand}`);
    }

    // Clamp score
    score = Math.min(99, Math.max(25, score));
    aiMatchScores[item.id] = score;
  });

  if (aiTagsDetected && tags.length > 0) {
    aiTagsDetected.innerHTML = tags.map(t => `<span class="modal-dna-chip">${t}</span>`).join('');
  }
}

/**
 * Filter, sort, and render items
 */
function renderResults() {
  if (!resultsGrid) return;

  let filtered = ITEMS_DATABASE.filter(item => {
    // 1. Type Filter
    if (currentTypeFilter !== 'all' && item.type !== currentTypeFilter) {
      return false;
    }

    // 2. Category Filter
    if (currentCategory !== 'all' && item.category !== currentCategory) {
      return false;
    }

    // 3. Location Filter
    if (currentLocation !== 'all' && !item.location.toLowerCase().includes(currentLocation.toLowerCase())) {
      return false;
    }

    // 4. Keyword Query Filter
    if (currentSearchQuery) {
      const matchText = `${item.title} ${item.desc} ${item.category} ${item.location} ${item.id} ${JSON.stringify(item.dna || {})}`.toLowerCase();
      if (!matchText.includes(currentSearchQuery)) {
        return false;
      }
    }

    return true;
  });

  // Sorting
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

  // Update Results Counter
  if (resultsCount) {
    const typeLabel = currentTypeFilter === 'found' ? 'found' : currentTypeFilter === 'lost' ? 'lost' : 'community';
    resultsCount.innerHTML = `Showing <strong class="results-count-strong">${filtered.length}</strong> ${typeLabel} item${filtered.length === 1 ? '' : 's'}`;
  }

  // Handle Empty State
  if (filtered.length === 0) {
    resultsGrid.innerHTML = `
      <div class="search-empty-state">
        <div class="empty-icon">🔎</div>
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

  // Render Grid Cards with Microinteractions
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
    const isLost = item.type === 'lost';
    const isReunited = item.status === 'claimed' || item.status === 'reunited' || item.status === 'closed';

    let badgeClass = 'badge-found';
    let badgeLabel = 'Found Item';
    if (isReunited) {
      badgeClass = 'badge-reunited';
      badgeLabel = 'Reunited';
    } else if (isFound) {
      badgeClass = 'badge-found';
      badgeLabel = 'Found Item';
    } else if (isLost) {
      badgeClass = 'badge-lost';
      badgeLabel = 'Lost Item';
    }

    const miniTags = (item.dna && item.dna.features) 
      ? item.dna.features.slice(0, 2).map(f => `<span class="dna-mini-tag">✨ ${f}</span>`).join('') 
      : '';

    const isSaved = window.ReuniteBookmarks ? window.ReuniteBookmarks.isSaved(item.id) : false;

    return `
      <article class="search-item-card" data-id="${item.id}" onclick="openItemModal('${item.id}')">
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
            <button type="button" class="btn-card-action">View details &rarr;</button>
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

/**
 * Opens detailed modal for an item
 */
window.openItemModal = function(itemId) {
  const item = ITEMS_DATABASE.find(i => i.id === itemId);
  if (!item || !modalOverlay) return;

  const dna = item.dna || {};
  const features = dna.features || [];

  const dnaHtml = `
    <div class="modal-dna-box">
      <div class="modal-dna-title">
        <span>🧬</span> AI Verified Digital DNA
      </div>
      <div class="modal-dna-chips">
        ${dna.brand ? `<span class="modal-dna-chip"><strong>Brand:</strong> ${dna.brand}</span>` : ''}
        ${dna.color ? `<span class="modal-dna-chip"><strong>Color:</strong> ${dna.color}</span>` : ''}
        ${dna.model ? `<span class="modal-dna-chip"><strong>Model:</strong> ${dna.model}</span>` : ''}
        ${features.map(f => `<span class="modal-dna-chip">🔍 ${f}</span>`).join('')}
      </div>
    </div>
  `;

  modalOverlay.innerHTML = `
    <div class="modal-card">
      <button type="button" class="modal-close-btn" aria-label="Close modal">&times;</button>
      <div class="modal-img-wrap">
        <img src="${item.img}" alt="${item.title}" class="modal-img" />
      </div>
      <div class="modal-content">
        <div class="modal-header-meta">
          <span class="card-category">${item.category}</span>
          <span class="modal-report-id">${item.id}</span>
        </div>
        <h2 class="modal-title">${item.title}</h2>
        
        <div class="modal-grid-details">
          <div class="modal-detail-item">
            <span class="modal-detail-label">Location Found</span>
            <span class="modal-detail-val">${item.location}</span>
          </div>
          <div class="modal-detail-item">
            <span class="modal-detail-label">Date Reported</span>
            <span class="modal-detail-val">${item.date} (${item.timeAgo})</span>
          </div>
          <div class="modal-detail-item">
            <span class="modal-detail-label">Status</span>
            <span class="modal-detail-val" style="text-transform: capitalize;">${item.status}</span>
          </div>
        </div>

        <div class="modal-desc-heading">Detailed Physical Description</div>
        <p class="modal-desc-text">${item.desc}</p>

        ${dnaHtml}

        <div class="modal-actions" id="modalActionsRow">
          <button type="button" class="btn-empty-secondary modal-close-btn-action" onclick="closeModal()">Close</button>
          ${item.status === 'found' ? `<button type="button" class="btn-modal-claim" onclick="toggleClaimForm('${item.id}')">Claim This Item &rarr;</button>` : ''}
        </div>

        <div class="claim-form-wrap" id="claimFormWrap" aria-live="polite">
          <div class="claim-form-title">🛡️ Submit Ownership Verification</div>
          <p class="claim-form-sub">To claim this item, please provide a unique detail only the owner would know (e.g. wallpaper description, unique scratch, serial number, or exact contents).</p>
          <form id="itemClaimForm" onsubmit="handleClaimSubmit(event, '${item.id}')">
            <input type="text" id="claimName" class="claim-input" placeholder="Your Full Name / Student PIN" required />
            <input type="email" id="claimEmail" class="claim-input" placeholder="Your College Email Address" required />
            <textarea id="claimProof" class="claim-input" rows="3" placeholder="Provide proof of ownership (e.g. specific scratches, passcode hint, unique items inside)" required></textarea>
            <button type="submit" id="btnClaimSubmitAction" class="btn-modal-claim" style="width: 100%;">Submit Claim Request</button>
          </form>
        </div>
      </div>
    </div>
  `;

  modalOverlay.classList.add('active');
  document.body.style.overflow = 'hidden';
};

window.closeModal = function() {
  if (modalOverlay) {
    modalOverlay.classList.remove('active');
  }
  document.body.style.overflow = '';
};

window.toggleClaimForm = function(itemId) {
  const formWrap = document.getElementById('claimFormWrap');
  if (formWrap) {
    formWrap.classList.toggle('active');
    if (formWrap.classList.contains('active')) {
      const input = formWrap.querySelector('input');
      if (input) input.focus();
    }
  }
};

window.handleClaimSubmit = function(e, itemId) {
  e.preventDefault();
  const formWrap = document.getElementById('claimFormWrap');
  const submitBtn = document.getElementById('btnClaimSubmitAction');
  
  if (submitBtn && window.setButtonLoading) {
    window.setButtonLoading(submitBtn, true, 'Verifying claim...');
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
          <h4 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--ink);">Claim Request Submitted</h4>
          <p style="font-size: 0.875rem; color: var(--muted); line-height: 1.55; margin-bottom: 1.25rem;">
            Your verification details for <strong>${itemId}</strong> have been forwarded to the campus lost &amp; found coordinator. You will receive an email confirmation for collection at the designated campus hub.
          </p>
          <button type="button" class="btn-empty-primary" onclick="closeModal()">Done</button>
        </div>
      `;
    }
    if (window.ReuniteToast) {
      window.ReuniteToast.success('Claim Submitted', `Verification request for ${itemId} received.`, 4000);
    }
  }, 600);
};

window.resetAllFilters = resetAllFilters;

