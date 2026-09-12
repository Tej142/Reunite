/**
 * Reunite — Search & Discovery Controller
 * Handles live keyword searching, multi-faceted filtering, AI smart match scoring,
 * dynamic item card rendering, and claim verification modals.
 */

// Comprehensive Campus Lost & Found Item Database
const ITEMS_DATABASE = [
  {
    id: 'RF-8042',
    title: 'Blue Leather Bifold Wallet',
    category: 'Wallets & Bags',
    type: 'found',
    status: 'found',
    img: 'https://images.unsplash.com/photo-1627123424574-724758594e93?w=640&h=420&fit=crop&auto=format',
    desc: 'Navy blue leather wallet with white stitching along borders. Contains student ID card with initials A.K., library card, and transit pass.',
    location: 'Central Library (2nd Floor Desk)',
    date: '2026-09-11',
    timeAgo: '2 hours ago',
    dna: {
      category: 'Wallet',
      brand: 'Tommy Hilfiger',
      color: 'Navy Blue',
      material: 'Genuine Leather',
      features: ['White contrast stitching', 'Inner coin zipper', 'ID slot visible', 'Transit pass inside']
    }
  },
  {
    id: 'RF-8041',
    title: 'iPhone 13 with Translucent Case',
    category: 'Electronics',
    type: 'found',
    status: 'found',
    img: 'https://images.unsplash.com/photo-1591337676887-a217a6970a8a?w=640&h=420&fit=crop&auto=format',
    desc: 'Midnight black iPhone 13 in a transparent bumper case with an astronaut sticker on the back. Screen lock pattern has an anime wallpaper.',
    location: 'Cafeteria Table #14',
    date: '2026-09-11',
    timeAgo: '4 hours ago',
    dna: {
      category: 'Smartphone',
      brand: 'Apple',
      model: 'iPhone 13',
      color: 'Midnight Black',
      features: ['Astronaut sticker on back', 'Diagonal dual rear cameras', 'Clear silicone shockproof case']
    }
  },
  {
    id: 'RF-8039',
    title: 'Casio fx-991EX Scientific Calculator',
    category: 'Books & Stationeries',
    type: 'found',
    status: 'found',
    img: 'https://images.unsplash.com/photo-1594980596870-8aa52a78d8cd?w=640&h=420&fit=crop&auto=format',
    desc: 'ClassWiz black & white scientific calculator with small scratch on top right solar panel and "ECE" written in marker inside the cover.',
    location: 'Computer Science Lab 3',
    date: '2026-09-10',
    timeAgo: '1 day ago',
    dna: {
      category: 'Calculator',
      brand: 'Casio',
      model: 'fx-991EX ClassWiz',
      color: 'Black & White',
      features: ['ECE written in silver marker inside slip-cover', 'Solar panel scratch', 'High-res screen']
    }
  },
  {
    id: 'RF-8038',
    title: 'Honda Bike Key with Red Metal Tag',
    category: 'Keys & Fobs',
    type: 'found',
    status: 'found',
    img: 'https://images.unsplash.com/photo-1582139329536-e7284fece509?w=640&h=420&fit=crop&auto=format',
    desc: 'Single Honda motorcycle key attached to a red anodized aluminum "REMOVE BEFORE FLIGHT" fabric keychain tag.',
    location: 'Student Parking Bay 2',
    date: '2026-09-10',
    timeAgo: '1 day ago',
    dna: {
      category: 'Key',
      brand: 'Honda',
      color: 'Silver key / Red Tag',
      features: ['Red Remove Before Flight ribbon tag', 'Black plastic key head with wing logo']
    }
  },
  {
    id: 'RF-8035',
    title: 'Ray-Ban Wayfarer Matte Sunglasses',
    category: 'Accessories',
    type: 'found',
    status: 'found',
    img: 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=640&h=420&fit=crop&auto=format',
    desc: 'Black matte acetate sunglasses, polarized green lenses, left arm has faint silver Ray-Ban lettering.',
    location: 'Sports Ground Pavilion',
    date: '2026-09-09',
    timeAgo: '2 days ago',
    dna: {
      category: 'Eyewear',
      brand: 'Ray-Ban',
      color: 'Matte Black',
      features: ['Polarized G-15 dark green lenses', 'Silver rivet accents on corners', 'No protective case']
    }
  },
  {
    id: 'RF-8032',
    title: 'Apple AirPods Pro 2nd Gen Case',
    category: 'Electronics',
    type: 'found',
    status: 'found',
    img: 'https://images.unsplash.com/photo-1600294037681-c80b4cb5b434?w=640&h=420&fit=crop&auto=format',
    desc: 'White MagSafe charging case with both earbuds inside. Small red lanyard loop attached to the side.',
    location: 'Main Block Auditorium (Row F)',
    date: '2026-09-08',
    timeAgo: '3 days ago',
    dna: {
      category: 'Audio',
      brand: 'Apple',
      model: 'AirPods Pro (2nd Gen)',
      color: 'Glossy White',
      features: ['Lanyard loop installed', 'Speaker holes at bottom', 'Silicone ear tips medium size']
    }
  },
  {
    id: 'RL-1092',
    title: 'Dell Inspiron Laptop Charger (65W)',
    category: 'Electronics',
    type: 'lost',
    status: 'lost',
    img: 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=640&h=420&fit=crop&auto=format',
    desc: 'Original 65W Dell barrel-pin charger with blue LED indicator ring on tip. Wrapped with black Velcro cable tie.',
    location: 'Electrical Workshop Desk 4',
    date: '2026-09-09',
    timeAgo: '2 days ago',
    dna: {
      category: 'Power Adapter',
      brand: 'Dell',
      color: 'Black',
      features: ['Blue illuminated tip', 'Velcro strap attached', '3-prong power cable']
    }
  },
  {
    id: 'RR-0519',
    title: 'Black Wildcraft College Backpack',
    category: 'Wallets & Bags',
    type: 'reunited',
    status: 'reunited',
    img: 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=640&h=420&fit=crop&auto=format',
    desc: '3-compartment black and grey water-resistant backpack with a water bottle mesh pocket and engineering notebook inside.',
    location: 'Civil Department Lobby',
    date: '2026-09-06',
    timeAgo: '5 days ago',
    dna: {
      category: 'Backpack',
      brand: 'Wildcraft',
      color: 'Black & Slate Grey',
      features: ['Orange embroidered logo', 'Laptop padding inside', 'Engineering textbooks']
    }
  }
];

// Active State
let currentSearchQuery = '';
let currentTypeFilter = 'found';
let currentCategory = 'all';
let currentLocation = 'all';
let currentSort = 'newest';
let isAiMatchActive = false;
let aiMatchScores = {};
let isListView = false;

// DOM Elements
let searchInput, clearBtn, resultsGrid, resultsCount, catChips, typeSelect, categorySelect, locationSelect, sortSelect;
let modalOverlay, aiDrawer, aiMatchBtn, aiTextarea, aiTagsDetected;

document.addEventListener('DOMContentLoaded', () => {
  initDomElements();
  bindEvents();
  renderResults();
});

function initDomElements() {
  searchInput   = document.getElementById('searchInput');
  clearBtn      = document.getElementById('clearSearchBtn');
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
}

function bindEvents() {
  // Keyword Search with debounce
  if (searchInput) {
    let debounceTimer;
    searchInput.addEventListener('input', (e) => {
      clearTimeout(debounceTimer);
      const query = e.target.value.trim();
      if (clearBtn) clearBtn.classList.toggle('visible', query.length > 0);
      debounceTimer = setTimeout(() => {
        currentSearchQuery = query.toLowerCase();
        if (isAiMatchActive) calculateLocalAiScores(currentSearchQuery);
        renderResults();
      }, 250);
    });
  }

  // Clear Search
  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      if (searchInput) searchInput.value = '';
      clearBtn.classList.remove('visible');
      currentSearchQuery = '';
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
  currentTypeFilter = 'all';
  currentCategory = 'all';
  currentLocation = 'all';
  currentSort = 'newest';
  isAiMatchActive = false;
  aiMatchScores = {};

  if (searchInput) searchInput.value = '';
  if (clearBtn) clearBtn.classList.remove('visible');
  if (typeSelect) typeSelect.value = 'all';
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

  // Render Grid Cards
  resultsGrid.innerHTML = filtered.map(item => {
    const matchScore = aiMatchScores[item.id];
    const matchBadgeHtml = (isAiMatchActive && matchScore) 
      ? `<span class="card-badge-match">${matchScore}% Match</span>` 
      : '';

    const badgeClass = item.status === 'found' ? 'badge-found' : item.status === 'lost' ? 'badge-lost' : 'badge-reunited';
    const badgeLabel = item.status === 'found' ? 'Found Item' : item.status === 'lost' ? 'Lost Item' : 'Reunited';

    const miniTags = (item.dna && item.dna.features) 
      ? item.dna.features.slice(0, 2).map(f => `<span class="dna-mini-tag">✨ ${f}</span>`).join('') 
      : '';

    return `
      <article class="search-item-card" data-id="${item.id}" onclick="openItemModal('${item.id}')">
        <div class="card-img-wrap">
          <img src="${item.img}" alt="${item.title}" class="card-img" loading="lazy" />
          <span class="card-badge-status ${badgeClass}">${badgeLabel}</span>
          ${matchBadgeHtml}
        </div>
        <div class="card-body">
          <span class="card-category">${item.category}</span>
          <h3 class="card-title">${item.title}</h3>
          <p class="card-desc">${item.desc}</p>
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
        <img src="${item.img}" alt="${item.title}" />
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

        <div class="claim-form-wrap" id="claimFormWrap">
          <div class="claim-form-title">🛡️ Submit Ownership Verification</div>
          <p class="claim-form-sub">To claim this item, please provide a unique detail only the owner would know (e.g. wallpaper description, unique scratch, serial number, or exact contents).</p>
          <form id="itemClaimForm" onsubmit="handleClaimSubmit(event, '${item.id}')">
            <input type="text" class="claim-input" placeholder="Your Full Name / Student PIN" required />
            <input type="email" class="claim-input" placeholder="Your College Email Address" required />
            <textarea class="claim-input" rows="3" placeholder="Provide proof of ownership (e.g. specific scratches, passcode hint, unique items inside)" required></textarea>
            <button type="submit" class="btn-modal-claim" style="width: 100%;">Submit Claim Request</button>
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
  const actionsRow = document.getElementById('modalActionsRow');
  if (formWrap) {
    formWrap.classList.toggle('active');
  }
};

window.handleClaimSubmit = function(e, itemId) {
  e.preventDefault();
  const formWrap = document.getElementById('claimFormWrap');
  if (formWrap) {
    formWrap.innerHTML = `
      <div style="text-align: center; padding: 1.5rem 0;">
        <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🎉</div>
        <h4 style="font-size: 1.15rem; margin-bottom: 0.5rem; color: var(--ink);">Claim Request Submitted!</h4>
        <p style="font-size: 0.875rem; color: var(--muted); line-height: 1.5; margin-bottom: 1rem;">
          Your verification details for <strong>${itemId}</strong> have been forwarded to the campus lost & found coordinator. You will receive an email confirmation for collection at the designated campus hub.
        </p>
        <button type="button" class="btn-empty-primary" onclick="closeModal()">Done</button>
      </div>
    `;
  }
};

window.resetAllFilters = resetAllFilters;
