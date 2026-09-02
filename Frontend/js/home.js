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

  grid.innerHTML = filtered.map(item => `
    <article class="item-card">
      <div class="item-card-img-wrap">
        <img src="${item.img}" alt="${item.title}" class="item-card-img" loading="lazy" />
        <div class="item-card-overlay"></div>
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
  `).join('');
}

function initFilters() {
  const pills = document.querySelectorAll('.filter-pill');
  pills.forEach(pill => {
    pill.addEventListener('click', () => {
      currentFilter = pill.dataset.filter;
      pills.forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      renderCards();
    });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  renderCards();
  initFilters();
});
