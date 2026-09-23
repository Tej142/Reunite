const ITEMS = [
  {
    id: 1,
    img: 'https://images.unsplash.com/photo-1637486069202-b1163268c240?w=480&h=320&fit=crop&auto=format',
    title: 'Brown bifold wallet',
    desc: 'Open bi-fold, tan pebbled leather, multiple card slots visible, possible Coach branding, no cash inside',
    city: 'New York, NY', when: '2 hrs ago', status: 'active',
  },
  {
    id: 2,
    img: 'https://images.unsplash.com/photo-1604445415362-2a9840bd5ff6?w=480&h=320&fit=crop&auto=format',
    title: 'House key set — 4 keys',
    desc: '4 keys on plain steel ring: 2 Medeco, 1 Yale, 1 small padlock key; no label or tag attached',
    city: 'Atlanta, GA', when: '5 hrs ago', status: 'active',
  },
  {
    id: 3,
    img: 'https://images.unsplash.com/photo-1567473810954-507d59716c25?w=480&h=320&fit=crop&auto=format',
    title: 'Gold aviator sunglasses',
    desc: 'Ray-Ban style aviators, gold metal frame, green-tinted lenses, spring hinges, no case included',
    city: 'Chicago, IL', when: '1 day ago', status: 'matched',
  },
  {
    id: 4,
    img: 'https://images.unsplash.com/photo-1633818807431-14d29e583bcb?w=480&h=320&fit=crop&auto=format',
    title: 'Car key fob + house key',
    desc: 'Toyota smart key fob (black), 1 door key on rubber band, small grocery loyalty card clipped to ring',
    city: 'Houston, TX', when: '1 day ago', status: 'active',
  },
  {
    id: 5,
    img: 'https://images.unsplash.com/photo-1587310311582-aa7610e90826?w=480&h=320&fit=crop&auto=format',
    title: 'Black square-frame glasses',
    desc: 'Thick black acetate frame, strong prescription lenses, no brand markings visible, no case',
    city: 'Boston, MA', when: '2 days ago', status: 'matched',
  },
  {
    id: 6,
    img: 'https://images.unsplash.com/photo-1781751594989-ac3ad190db3c?w=480&h=320&fit=crop&auto=format',
    title: 'Black leather backpack',
    desc: 'Medium size, black leather with silver chain accent, 2 compartments, empty laptop sleeve inside',
    city: 'Los Angeles, CA', when: '2 days ago', status: 'active',
  },
];

let currentFilter = 'all';

function renderCards() {
  const grid = document.getElementById('itemsGrid');
  if (!grid) return;
  const filtered = currentFilter === 'all' ? ITEMS : ITEMS.filter(i => i.status === currentFilter);

  grid.innerHTML = filtered.map(item => {
    const isSaved = window.ReuniteBookmarks ? window.ReuniteBookmarks.isSaved(`home-${item.id}`) : false;
    return `
      <article class="item-card search-item-card" data-id="home-${item.id}">
        <div class="item-card-img-wrap">
          <img src="${item.img}" alt="${item.title}" class="item-card-img card-img" loading="lazy" onload="this.classList.add('loaded')" />
          <div class="item-card-overlay"></div>
          <button type="button" class="btn-bookmark card-bookmark-btn ${isSaved ? 'active' : ''}" data-id="home-${item.id}" onclick="handleHomeBookmark(event, 'home-${item.id}', '${item.title.replace(/'/g, "\\'")}')" aria-label="Save item" title="${isSaved ? 'Remove from saved' : 'Save this item'}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg>
          </button>
          <span class="item-badge ${item.status === 'matched' ? 'badge-matched' : 'badge-active'}">
            ${item.status === 'matched' ? '&#10003; Reunited' : 'Searching'}
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
});

