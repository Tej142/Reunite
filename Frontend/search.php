<?php
require_once __DIR__ . '/includes/auth.php';
$logged_in = is_logged_in();
$user = get_current_user_data();

// Forward direct item links (e.g. search.php?item=RF-00002 or search.php?match_id=1) to dedicated item.php page
if (!empty($_GET['item']) || !empty($_GET['id']) || !empty($_GET['item_id']) || !empty($_GET['match_id'])) {
    $itemId = $_GET['item'] ?? $_GET['id'] ?? $_GET['item_id'] ?? '';
    $matchId = $_GET['match_id'] ?? '';
    $params = [];
    if (!empty($itemId)) $params['id'] = $itemId;
    if (!empty($matchId)) $params['match_id'] = $matchId;
    header("Location: item.php?" . http_build_query($params));
    exit;
}
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
      <span id="searchBarSpinner" class="search-bar-spinner" aria-hidden="true"></span>
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
        <div class="ai-drawer-title">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 4px;"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
          AI Smart Match
        </div>
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
    <button type="button" class="cat-chip" data-category="Electronics">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -1px; margin-right: 3px;"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><line x1="12" x2="12.01" y1="18" y2="18"/></svg>Electronics
    </button>
    <button type="button" class="cat-chip" data-category="Wallets &amp; Bags">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -1px; margin-right: 3px;"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>Wallets &amp; Bags
    </button>
    <button type="button" class="cat-chip" data-category="Keys &amp; Fobs">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -1px; margin-right: 3px;"><circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/></svg>Keys &amp; Fobs
    </button>
    <button type="button" class="cat-chip" data-category="Books &amp; Stationeries">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -1px; margin-right: 3px;"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M6 6h10"/><path d="M6 10h10"/></svg>Stationeries
    </button>
    <button type="button" class="cat-chip" data-category="Accessories">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -1px; margin-right: 3px;"><circle cx="6" cy="15" r="4"/><circle cx="18" cy="15" r="4"/><path d="M14 15a2 2 0 0 0-4 0"/><path d="M2.5 13 5 7c.7-1.3 1.4-2 3-2"/><path d="M21.5 13 19 7c-.7-1.3-1.4-2-3-2"/></svg>Accessories
    </button>
    <button type="button" class="cat-chip" data-category="Clothing">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -1px; margin-right: 3px;"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"/></svg>Clothing
    </button>
    <button type="button" class="cat-chip" data-category="ID Cards">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -1px; margin-right: 3px;"><rect width="18" height="13" x="3" y="5" rx="2"/><path d="M7 15h4"/><path d="M15 15h2"/><circle cx="9" cy="10" r="2"/></svg>ID Cards
    </button>
    <button type="button" class="cat-chip" data-category="Others">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -1px; margin-right: 3px;"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>Others
    </button>
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
        <div class="tip-icon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>
        </div>
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

<!-- ── Item Details Slide-Over Drawer & Overlay ── -->
<div id="itemDrawerOverlay" class="drawer-overlay" role="presentation"></div>
<aside id="itemDrawer" class="item-slideover-drawer" role="dialog" aria-labelledby="modalItemTitle" aria-hidden="true" aria-modal="false"></aside>

<!-- ── Scripts ── -->
<script src="js/flask-ai-service.js?v=<?php echo time(); ?>"></script>
<script src="js/search.js?v=<?php echo time(); ?>"></script>

</body>
</html>
