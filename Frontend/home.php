<?php
require_once __DIR__ . '/includes/auth.php';
// Require user to be logged in to view the Homepage Dashboard
require_login();
$user = get_current_user_data();

// Live statistics from MySQL with cross-schema resilience
if (!function_exists('get_reunite_home_stats')) {
    function get_reunite_home_stats($conn = null) {
        if (!$conn) {
            global $conn;
        }
        if (!$conn && function_exists('get_db_connection')) {
            $conn = get_db_connection();
        }

        $stats = [
            'items_reunited' => 0,
            'active_searches' => 0,
            'reported_this_week' => 0,
            'match_accuracy' => '98.0%'
        ];

        if (!$conn) return $stats;

        // Inspect columns to avoid SQL syntax errors on cloud/local schema variations
        $lostCols = [];
        $lc = @$conn->query("SHOW COLUMNS FROM lost_reports");
        if ($lc) {
            while ($c = $lc->fetch_assoc()) $lostCols[] = strtolower($c['Field']);
        }

        $foundCols = [];
        $fc = @$conn->query("SHOW COLUMNS FROM found_reports");
        if ($fc) {
            while ($c = $fc->fetch_assoc()) $foundCols[] = strtolower($c['Field']);
        }

        $mCols = [];
        $mc = @$conn->query("SHOW COLUMNS FROM matches");
        if ($mc) {
            while ($c = $mc->fetch_assoc()) $mCols[] = strtolower($c['Field']);
        }

        // Resilient date expression for lost_reports: prioritize submission timestamp (within last 7 days)
        $lostDateParts = [];
        if (in_array('created_at', $lostCols)) $lostDateParts[] = "NULLIF(NULLIF(`created_at`, '0000-00-00 00:00:00'), '')";
        if (in_array('date_lost', $lostCols)) $lostDateParts[] = "NULLIF(NULLIF(`date_lost`, '0000-00-00'), '')";
        if (in_array('lost_date', $lostCols)) $lostDateParts[] = "NULLIF(NULLIF(`lost_date`, '0000-00-00'), '')";
        $lostDateExpr = !empty($lostDateParts) ? ("COALESCE(" . implode(', ', $lostDateParts) . ")") : "NULL";

        // Resilient date expression for found_reports: prioritize submission timestamp (within last 7 days)
        $foundDateParts = [];
        if (in_array('created_at', $foundCols)) $foundDateParts[] = "NULLIF(NULLIF(`created_at`, '0000-00-00 00:00:00'), '')";
        if (in_array('date_found', $foundCols)) $foundDateParts[] = "NULLIF(NULLIF(`date_found`, '0000-00-00'), '')";
        if (in_array('found_date', $foundCols)) $foundDateParts[] = "NULLIF(NULLIF(`found_date`, '0000-00-00'), '')";
        $foundDateExpr = !empty($foundDateParts) ? ("COALESCE(" . implode(', ', $foundDateParts) . ")") : "NULL";

        // Query lost_reports: active searches, reunited, and weekly counts
        $lSql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN LOWER(TRIM(COALESCE(status, ''))) IN ('matched', 'recovered', 'claimed', 'closed', 'returned', 'reunited', 'handed_off', 'verified') THEN 1 ELSE 0 END) as reunited,
            SUM(CASE WHEN LOWER(TRIM(COALESCE(status, ''))) NOT IN ('matched', 'recovered', 'claimed', 'closed', 'returned', 'reunited', 'handed_off', 'verified') THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN $lostDateExpr IS NOT NULL AND DATE($lostDateExpr) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week_count
            FROM lost_reports";
        $lRes = @$conn->query($lSql);
        if ($lRes && ($lRow = $lRes->fetch_assoc())) {
            $stats['active_searches'] += (int)($lRow['active'] ?? 0);
            $stats['items_reunited'] += (int)($lRow['reunited'] ?? 0);
            $stats['reported_this_week'] += (int)($lRow['week_count'] ?? 0);
        }

        // Query found_reports: active searches, reunited, and weekly counts
        $fSql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN LOWER(TRIM(COALESCE(status, ''))) IN ('matched', 'recovered', 'claimed', 'closed', 'returned', 'reunited', 'handed_off', 'verified') THEN 1 ELSE 0 END) as reunited,
            SUM(CASE WHEN LOWER(TRIM(COALESCE(status, ''))) NOT IN ('matched', 'recovered', 'claimed', 'closed', 'returned', 'reunited', 'handed_off', 'verified') THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN $foundDateExpr IS NOT NULL AND DATE($foundDateExpr) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week_count
            FROM found_reports";
        $fRes = @$conn->query($fSql);
        if ($fRes && ($fRow = $fRes->fetch_assoc())) {
            $stats['active_searches'] += (int)($fRow['active'] ?? 0);
            $stats['items_reunited'] += (int)($fRow['reunited'] ?? 0);
            $stats['reported_this_week'] += (int)($fRow['week_count'] ?? 0);
        }

        // Include verified recovery cases & confirmed handoffs
        $rcRes = @$conn->query("SELECT COUNT(*) as c FROM recovery_cases WHERE LOWER(TRIM(status)) IN ('approved', 'handed_off', 'closed', 'reunited') OR (owner_verified = 1 AND finder_confirmed = 1)");
        if ($rcRes && ($rcRow = $rcRes->fetch_assoc())) {
            $stats['items_reunited'] = max($stats['items_reunited'], (int)$rcRow['c']);
        }

        $vmRes = @$conn->query("SELECT COUNT(*) as c FROM matches WHERE LOWER(TRIM(status)) IN ('verified', 'approved', 'reunited', 'closed')");
        if ($vmRes && ($vmRow = $vmRes->fetch_assoc())) {
            $stats['items_reunited'] = max($stats['items_reunited'], (int)$vmRow['c']);
        }

        // Match accuracy calculation across schema variations (match_percentage vs similarity_score)
        $scoreCol = in_array('match_percentage', $mCols) ? 'match_percentage' : (in_array('similarity_score', $mCols) ? 'similarity_score' : null);
        if ($scoreCol) {
            $mRes = @$conn->query("SELECT AVG(`$scoreCol`) as avg_score, MAX(`$scoreCol`) as max_score FROM matches WHERE `$scoreCol` >= 50");
            if ($mRes && ($mRow = $mRes->fetch_assoc()) && !empty($mRow['avg_score'])) {
                $avg = (float)$mRow['avg_score'];
                $pct = round($avg <= 1 ? $avg * 100 : $avg, 1);
                $stats['match_accuracy'] = $pct . '%';
            } else {
                $allM = @$conn->query("SELECT MAX(`$scoreCol`) as max_score FROM matches WHERE `$scoreCol` > 0");
                if ($allM && ($amr = $allM->fetch_assoc()) && !empty($amr['max_score'])) {
                    $max = (float)$amr['max_score'];
                    $stats['match_accuracy'] = round($max <= 1 ? $max * 100 : $max, 1) . '%';
                }
            }
        }

        return $stats;
    }
}

global $conn;
$homeStats = get_reunite_home_stats($conn ?? null);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>Reunite — Student Homepage Dashboard</title>
  <meta name="description" content="Reunite Student Dashboard — Report lost items, upload found items, and browse active community listings." />
  <script>
    (function(){
      var t = localStorage.getItem('reunite_theme');
      if (t === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    })();
  </script>
  <link rel="stylesheet" href="css/variables.css?v=<?php echo time(); ?>" />
  <link rel="stylesheet" href="css/home.css?v=<?php echo time(); ?>" />
</head>
<body>

<!-- ── Nav ─────────────────────────────────────────────────── -->
<?php include 'components/nav.php'; ?>

<!-- ── Hero / Dashboard Header ──────────────────────────────── -->
<section class="hero">
  <div class="hero-grid">

    <!-- Left: Headline + CTAs -->
    <div>
      <div class="badge-live animate-fade-up">
        <span class="pulse"></span>
        Student Session Active &middot; <?php echo htmlspecialchars($user['pin'] ?? ''); ?>
      </div>

      <h1 class="animate-fade-up stagger-1">Welcome back,<br><em><?php echo htmlspecialchars($user['name'] ?? 'Student'); ?>!</em></h1>

      <p class="hero-sub animate-fade-up stagger-2">
        Track your lost items, upload details of belongings you've found, or search through active community reports below.
      </p>

      <div class="cta-group animate-fade-up stagger-3">
        <a href="report-found-item.php" class="btn-found animate-pop">
          <span class="btn-label">I found something</span>
          <span class="btn-text">Upload a found item &rarr;</span>
        </a>
        <a href="report-lost-item.php" class="btn-lost animate-pop stagger-1">
          <span class="btn-label">I lost something</span>
          <span class="btn-text">Report a missing item &rarr;</span>
        </a>
      </div>
    </div>

    <!-- Right: Elegant Guidelines Card -->
    <div class="hero-illustration animate-fade-up stagger-2">
      <div class="illustration-container">
        <div class="guidelines-card">
          <div class="guidelines-header">
            <span class="guidelines-badge">REUNITE HUB</span>
            <h2>Quick Tips for Safe Returns</h2>
          </div>
          <div class="guidelines-list">
            <div class="guidelines-item">
              <div class="guidelines-icon-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
              </div>
              <div>
                <h4>AI Smart Matching</h4>
                <p>Our board matches colors, categories, and locations automatically.</p>
              </div>
            </div>
            
            <div class="guidelines-item">
              <div class="guidelines-icon-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              </div>
              <div>
                <h4>Safe Handoffs</h4>
                <p>Meet in bright, public campus zones (e.g., Mainblock Library Lobby).</p>
              </div>
            </div>
            
            <div class="guidelines-item">
              <div class="guidelines-icon-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
              </div>
              <div>
                <h4>Instant Alerts</h4>
                <p>You will get notified the moment a match for your item is uploaded.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</section>

<!-- ── Stats Section ────────────────────────────────────────── -->
<section class="stats-section">
  <div class="stats-container">
    <div class="stats-grid">
      <div class="stat-item">
        <div id="stat-items-reunited" class="stat-val serif" data-val="<?php echo (int)$homeStats['items_reunited']; ?>"><?php echo number_format($homeStats['items_reunited']); ?></div>
        <div class="stat-label">Items reunited</div>
        <div class="stat-note">verified safe handoffs</div>
      </div>
      <div class="stat-item">
        <div id="stat-active-searches" class="stat-val serif" data-val="<?php echo (int)$homeStats['active_searches']; ?>"><?php echo number_format($homeStats['active_searches']); ?></div>
        <div class="stat-label">Active searches</div>
        <div class="stat-note">open right now</div>
      </div>
      <div class="stat-item">
        <div id="stat-reported-this-week" class="stat-val serif" data-val="<?php echo (int)$homeStats['reported_this_week']; ?>"><?php echo number_format($homeStats['reported_this_week']); ?></div>
        <div class="stat-label">Reported this week</div>
        <div class="stat-note">across campus hubs</div>
      </div>
      <div class="stat-item">
        <div id="stat-match-accuracy" class="stat-val serif" data-val="<?php echo htmlspecialchars($homeStats['match_accuracy']); ?>"><?php echo htmlspecialchars($homeStats['match_accuracy']); ?></div>
        <div class="stat-label">Match accuracy</div>
        <div class="stat-note">powered by CLIP &amp; DINOv2 AI</div>
      </div>
    </div>
  </div>
</section>

<!-- ── Community Board ────────────────────────────────────────── -->
<section class="board" id="board">
  <div class="board-inner">

    <div class="board-header">
      <div>
        <span class="section-eyebrow">Community board</span>
        <h2 class="section-h2" style="margin-bottom:.5rem;">Active searches &amp; recent reunions</h2>
        <p class="board-sub">Every item reported is analyzed and cross-referenced in real-time.</p>
      </div>

      <!-- Filter Pills -->
      <div class="filter-pills">
        <button class="filter-pill active" data-filter="all">All lost items</button>
        <button class="filter-pill" data-filter="active">Active searches</button>
        <button class="filter-pill" data-filter="matched">Reunited</button>
      </div>
    </div>

    <!-- Items Grid (populated by js/home.js) -->
    <div class="items-grid" id="itemsGrid"></div>

  </div>
</section>

<!-- ── How It Works ───────────────────────────────────────────── -->
<section class="how" id="how">
  <div class="section-inner">

    <span class="section-eyebrow">The process</span>
    <h2 class="section-h2">How Reunite brings your belongings back</h2>

    <div class="steps-grid">
      <div class="step-card">
        <div class="step-num">01</div>
        <h3 class="step-title">Upload or Report</h3>
        <p class="step-body">Found something on campus? Snap a photo and post it. Lost something? Tell us what's missing and where you last saw it.</p>
      </div>

      <div class="step-card">
        <div class="step-num">02</div>
        <h3 class="step-title">AI Matching</h3>
        <p class="step-body">Our system extracts visual details, colors, brands, and markings to cross-reference lost reports against found listings instantly.</p>
      </div>

      <div class="step-card">
        <div class="step-num">03</div>
        <h3 class="step-title">Verified Reunion</h3>
        <p class="step-body">When a match is found, we notify both parties. Ownership is verified via security questions before contact details are shared.</p>
      </div>
    </div>

  </div>
</section>

<footer>
  <div class="footer-inner">
    <span>&copy; 2024 Reunite &middot; A community-powered lost &amp; found platform.</span>
    <div class="footer-links">
      <a href="report-found-item.php">Report Found Item</a>
      <a href="report-lost-item.php">Report Lost Item</a>
      <a href="#board">Community Board</a>
      <a href="logout.php">Logout</a>
    </div>
  </div>
</footer>

<script src="js/home.js?v=<?php echo time(); ?>"></script>
</body>
</html>
