<?php
/**
 * Reunite — 404 Error Page
 * Features Orbi in NOT FOUND state with curious head tilt,
 * dark mode harmony, and direct navigation CTAs.
 */
http_response_code(404);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/orbi.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>404 — Page Not Found · Reunite</title>
  <meta name="description" content="The page or report you are looking for was not found on Reunite campus lost &amp; found." />
  <script>
    (function(){
      var t = localStorage.getItem('reunite_theme');
      if (t === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    })();
  </script>
  <link rel="stylesheet" href="css/variables.css?v=<?php echo time(); ?>" />
  <link rel="stylesheet" href="css/orbi.css?v=<?php echo time(); ?>" />
  <style>
    .page-404-main {
      min-height: calc(100vh - 180px);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 3rem 1.5rem;
    }
    .page-404-card {
      max-width: 560px;
      width: 100%;
      text-align: center;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 1.5rem;
      padding: 3.5rem 2rem;
      box-shadow: var(--shadow-md);
      transition: background var(--transition-base), border-color var(--transition-base);
    }
    .page-404-eyebrow {
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      color: var(--accent);
      margin-bottom: 0.5rem;
    }
    .page-404-title {
      font-family: var(--font-serif, 'Fraunces', Georgia, serif);
      font-size: 2.15rem;
      font-weight: 500;
      color: var(--ink);
      margin: 0 0 0.85rem;
      line-height: 1.2;
    }
    .page-404-desc {
      color: var(--muted);
      font-size: 0.9375rem;
      line-height: 1.6;
      margin: 0 auto 2.25rem;
      max-width: 44ch;
    }
    .page-404-ctas {
      display: flex;
      gap: 0.75rem;
      justify-content: center;
      flex-wrap: wrap;
    }
    @media (max-width: 640px) {
      .page-404-card {
        padding: 2.5rem 1.25rem;
      }
      .page-404-title {
        font-size: 1.75rem;
      }
      .page-404-ctas {
        flex-direction: column;
      }
      .page-404-ctas .btn {
        width: 100%;
        justify-content: center;
      }
    }
  </style>
</head>
<body>

<?php include 'components/nav.php'; ?>

<main class="page-404-main" role="main">
  <div class="page-404-card" role="region" aria-labelledby="pageNotFoundTitle">
    <div class="page-404-orbi" style="display: flex; justify-content: center; margin-bottom: 1.5rem;">
      <?php echo orbi('notfound', 140, 'Orbi Puzzled Mascot'); ?>
    </div>
    <div class="page-404-eyebrow">
      Error 404 &bull; Page Not Found
    </div>
    <h1 class="page-404-title" id="pageNotFoundTitle">
      Lost in the Digital Ether?
    </h1>
    <p class="page-404-desc">
      We couldn't track down the page or report you're looking for. It may have been relocated, resolved, or returned to its owner.
    </p>
    <div class="page-404-ctas">
      <a href="search.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <span>Search Reports</span>
      </a>
      <a href="report-lost-item.php" class="btn btn-ghost" style="display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none;">
        <span>Report Missing Item</span>
      </a>
      <a href="home.php" class="btn btn-ghost" style="display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none;">
        <span>Go to Homepage</span>
      </a>
    </div>
  </div>
</main>

<footer>
  <div class="footer-inner">
    <span>&copy; 2024 Reunite &middot; A community-powered lost &amp; found platform.</span>
  </div>
</footer>

</body>
</html>
