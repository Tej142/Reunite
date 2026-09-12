<?php
require_once __DIR__ . '/includes/auth.php';
$logged_in = is_logged_in();
$user = get_current_user_data();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Reunite — Search Lost &amp; Found Items</title>
  <meta name="description" content="Search across all found items, lost reports, and community listings on campus with instant filters and AI smart matching." />
  <script>
    (function(){
      var t = localStorage.getItem('reunite_theme');
      if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    })();
  </script>
  <link rel="stylesheet" href="css/variables.css?v=<?php echo time(); ?>" />
  <link rel="stylesheet" href="css/search.css?v=<?php echo time(); ?>" />
</head>
<body>

<!-- ── Navigation ──────────────────────────────────────── -->
<?php include 'components/nav.php'; ?>

<!-- ── Main Search Container ──────────────────────────── -->
<main class="search-page-main">

  <!-- ── Hero ── -->
  <section class="search-hero">
    <h1>Find <em>Items</em></h1>
    <p class="search-hero-sub">
      Search reported items across campus hubs or use AI Smart Match<br>to find what you're looking for.
    </p>

    <!-- Search Bar -->
    <div class="search-bar-wrap">
      <svg class="search-icon-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
      </svg>
      <input
        type="text"
        id="searchInput"
        class="search-input"
        placeholder="Search by keyword, brand, or location..."
        autocomplete="off"
        aria-label="Search items"
      />
      <button type="button" id="clearSearchBtn" class="btn-clear-search" title="Clear" aria-label="Clear search">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
      <button type="button" id="aiMatchToggleBtn" class="btn-ai-toggle" title="AI Smart Match">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
        AI Match
      </button>
    </div>

    <!-- Smart AI Match Drawer -->
    <div id="aiMatchDrawer" class="ai-match-drawer">
      <div class="ai-drawer-header">
        <div class="ai-drawer-title">🧬 AI Smart Match</div>
      </div>
      <p class="ai-drawer-desc">Describe your item to calculate similarity confidence against active reports.</p>
      <div class="ai-drawer-input-row">
        <textarea id="aiSmartQuery" class="ai-drawer-textarea" placeholder="e.g. Navy blue leather Tommy Hilfiger wallet near Central Library..."></textarea>
        <button type="button" id="btnSubmitAiMatch" class="btn-ai-match-submit">Calculate Match Scores</button>
      </div>
      <div id="aiTagsDetected" class="ai-tags-detected"></div>
    </div>
  </section>

  <!-- ── Category Chips ── -->
  <div class="category-chips-bar">
    <button type="button" class="cat-chip active" data-category="all">All</button>
    <button type="button" class="cat-chip" data-category="Electronics">📱 Electronics</button>
    <button type="button" class="cat-chip" data-category="Wallets &amp; Bags">👜 Wallets &amp; Bags</button>
    <button type="button" class="cat-chip" data-category="Keys &amp; Fobs">🔑 Keys &amp; Fobs</button>
    <button type="button" class="cat-chip" data-category="Books &amp; Stationeries">📚 Stationeries</button>
    <button type="button" class="cat-chip" data-category="Accessories">👓 Accessories</button>
    <button type="button" class="cat-chip" data-category="Clothing">👕 Clothing</button>
    <button type="button" class="cat-chip" data-category="ID Cards">🪪 ID Cards</button>
    <button type="button" class="cat-chip" data-category="Others">··· Others</button>
  </div>

  <!-- ── Content Area: Sidebar + Grid ── -->
  <div class="search-content-area">

    <!-- ── Left Sidebar Filters ── -->
    <aside class="search-sidebar">
      <div class="sidebar-header">
        <div class="sidebar-title">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
          Filters
        </div>
        <button type="button" id="resetFiltersBtn" class="btn-reset-all">Reset all</button>
      </div>

      <!-- Listing Type -->
      <div class="filter-group">
        <label class="filter-group-label" for="typeSelect">Listing Type</label>
        <div class="filter-select-wrap">
          <select id="typeSelect" class="filter-select" aria-label="Filter by type">
            <option value="all">All Listings</option>
            <option value="found" selected>Found Items</option>
            <option value="lost">Lost Reports</option>
            <option value="reunited">Reunited</option>
          </select>
          <svg class="select-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
      </div>

      <!-- Category -->
      <div class="filter-group">
        <label class="filter-group-label" for="categorySelect">Category</label>
        <div class="filter-select-wrap">
          <select id="categorySelect" class="filter-select" aria-label="Filter by category">
            <option value="all">All Categories</option>
            <option value="Electronics">Electronics</option>
            <option value="Wallets &amp; Bags">Wallets &amp; Bags</option>
            <option value="Keys &amp; Fobs">Keys &amp; Fobs</option>
            <option value="Books &amp; Stationeries">Books &amp; Stationeries</option>
            <option value="Accessories">Accessories</option>
            <option value="Clothing">Clothing</option>
            <option value="ID Cards">ID Cards</option>
            <option value="Others">Others</option>
          </select>
          <svg class="select-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
      </div>

      <!-- Campus Hub -->
      <div class="filter-group">
        <label class="filter-group-label" for="locationSelect">Campus Hub</label>
        <div class="filter-select-wrap">
          <select id="locationSelect" class="filter-select" aria-label="Filter by campus hub">
            <option value="all">All Campus Hubs</option>
            <option value="Library">Central Library</option>
            <option value="Cafeteria">Cafeteria</option>
            <option value="Lab">Computer Labs</option>
            <option value="Auditorium">Main Block / Auditorium</option>
            <option value="Sports">Sports Complex</option>
            <option value="Parking">Student Parking</option>
            <option value="Workshop">Department Workshops</option>
          </select>
          <svg class="select-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
      </div>

      <!-- Sort By -->
      <div class="filter-group">
        <label class="filter-group-label" for="sortSelect">Sort By</label>
        <div class="filter-select-wrap">
          <select id="sortSelect" class="filter-select" aria-label="Sort results">
            <option value="newest">Newest First</option>
            <option value="oldest">Oldest First</option>
            <option value="match">Highest AI Match</option>
            <option value="location">Location (A–Z)</option>
          </select>
          <svg class="select-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
      </div>

      <!-- Tip Card -->
      <div class="sidebar-tip">
        <div class="tip-icon">💡</div>
        <div>
          <div class="tip-title">Tip</div>
          <p class="tip-body">Use specific keywords like brand, color, or location to get better results with AI Match.</p>
        </div>
      </div>
    </aside>

    <!-- ── Right: Results ── -->
    <div class="search-results-col">

      <!-- Results Meta Bar -->
      <div class="results-meta-bar">
        <span id="resultsCount" class="results-count-text">Loading items...</span>
        <div class="results-view-actions">
          <button type="button" class="view-toggle-btn active" id="viewGrid" title="Grid view" aria-label="Grid view">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
          </button>
          <button type="button" class="view-toggle-btn" id="viewList" title="List view" aria-label="List view">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
          </button>
        </div>
      </div>

      <!-- Items Grid -->
      <div id="searchResultsGrid" class="search-grid"></div>
    </div>
  </div>

</main>

<!-- ── Item Details Modal ── -->
<div id="itemModalOverlay" class="modal-overlay" role="dialog" aria-modal="true"></div>

<!-- ── Scripts ── -->
<script src="js/flask-ai-service.js?v=<?php echo time(); ?>"></script>
<script src="js/search.js?v=<?php echo time(); ?>"></script>

</body>
</html>
