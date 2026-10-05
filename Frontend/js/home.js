let ITEMS = [];
let currentFilter = 'all';

async function fetchLiveHomeItems() {
  try {
    const res = await fetch('../Backend/reports.php?action=list&type=all&limit=20');
    if (!res.ok) return;
    const json = await res.json();
    const rows = (json.data && json.data.reports) ? json.data.reports : (json.reports || []);

    if (Array.isArray(rows)) {
      ITEMS = rows.map(r => {
        const repType = String(r.report_type || 'lost').toLowerCase();
        let imgUrl = 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=480&h=320&fit=crop&auto=format';
        if (r.image_path && typeof r.image_path === 'string' && r.image_path.trim() !== '') {
          const p = r.image_path.trim();
          imgUrl = (p.startsWith('http') || p.startsWith('data:')) ? p : (p.startsWith('/') ? p : '../' + p);
        } else if (repType === 'found') {
          imgUrl = 'https://images.unsplash.com/photo-1584438784894-089d6a62b8fa?w=480&h=320&fit=crop&auto=format';
        }

        return {
          id: r.id,
          reportId: `R${repType === 'found' ? 'F' : 'L'}-${String(r.id).padStart(5, '0')}`,
          img: imgUrl,
          title: r.title || r.category || (repType === 'found' ? 'Found Item' : 'Lost Item'),
          desc: r.description || 'Reported on campus via Reunite.',
          city: r.location || 'Campus Hub',
          when: r.created_at ? formatTimeAgo(r.created_at) : 'Recently',
          status: inArray(r.status, ['matched', 'claimed', 'closed']) ? 'matched' : 'active',
          type: repType
        };
      });

      renderCards();
    }
  } catch (err) {
    console.warn('[Home] Live items sync notice:', err);
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
  const filtered = currentFilter === 'all' ? ITEMS : ITEMS.filter(i => i.status === currentFilter);

  if (filtered.length === 0) {
    grid.innerHTML = `
      <div style="grid-column: 1 / -1; text-align: center; padding: 3rem 1.5rem; background: var(--white, #fff); border: 1px solid var(--border, #E4DCD3); border-radius: 1rem;">
        <div style="font-size: 2.25rem; margin-bottom: 0.5rem;">🔍</div>
        <h3 style="font-size: 1.15rem; font-weight: 600; color: var(--ink, #1C1917); margin-bottom: 0.35rem;">No active listings in this category</h3>
        <p style="font-size: 0.875rem; color: var(--muted, #8C827A); margin-bottom: 1.25rem; max-width: 40ch; margin-left: auto; margin-right: auto;">
          New reports submitted by students will appear live right here.
        </p>
        <div style="display: flex; gap: 0.65rem; justify-content: center; flex-wrap: wrap;">
          <a href="report-lost-item.php" class="btn btn-primary" style="font-size: 0.85rem; padding: 0.55rem 1.15rem;">+ Report Missing</a>
          <a href="report-found-item.php" class="btn btn-ghost" style="font-size: 0.85rem; padding: 0.55rem 1.15rem;">+ Report Found</a>
        </div>
      </div>
    `;
    return;
  }

  grid.innerHTML = filtered.map(item => {
    const isSaved = window.ReuniteBookmarks ? window.ReuniteBookmarks.isSaved(`item-${item.id}`) : false;
    return `
      <article class="item-card search-item-card" data-id="${item.reportId}" onclick="window.location.href='search.php?id=${item.id}&type=${item.type}'" style="cursor: pointer;">
        <div class="item-card-img-wrap">
          <img src="${item.img}" alt="${item.title}" class="item-card-img card-img" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=480&h=320&fit=crop'" onload="this.classList.add('loaded')" />
          <div class="item-card-overlay"></div>
          <button type="button" class="btn-bookmark card-bookmark-btn ${isSaved ? 'active' : ''}" data-id="${item.reportId}" onclick="handleHomeBookmark(event, '${item.reportId}', '${item.title.replace(/'/g, "\\'")}')" aria-label="Save item" title="${isSaved ? 'Remove from saved' : 'Save this item'}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg>
          </button>
          <span class="item-badge ${item.status === 'matched' ? 'badge-matched' : 'badge-active'}">
            ${item.status === 'matched' ? '&#10003; Reunited' : (item.type === 'found' ? 'Found Item' : 'Lost Item')}
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

document.addEventListener('DOMContentLoaded', () => {
  renderCards();
  initFilters();
  fetchLiveHomeItems();
});

