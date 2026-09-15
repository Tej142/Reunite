<?php
if (file_exists(__DIR__ . '/includes/auth.php')) {
    require_once __DIR__ . '/includes/auth.php';
} elseif (file_exists(__DIR__ . '/../Frontend/includes/auth.php')) {
    require_once __DIR__ . '/../Frontend/includes/auth.php';
}

// Keep the existing authentication behavior.
if (function_exists('is_logged_in') && is_logged_in()) {
    header("Location: ../Frontend/home.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Reunite — a safer, smarter campus lost and found community.">
<title>Reunite — Find. Report. Reunite.</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="css/intro.css?v=<?php echo time(); ?>">
</head>

<body>

<header class="site-nav">
  <div class="nav-inner">
    <a href="index.php" class="brand" aria-label="Reunite home">
      <span class="brand-mark">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M12 2L14 10L22 12L14 14L12 22L10 14L2 12L10 10L12 2Z" fill="currentColor"/>
        </svg>
      </span>
      <span>Reunite</span>
    </a>

    <nav class="desktop-nav" aria-label="Main navigation">
      <a href="#how">How it works</a>
      <a href="#stories">Success stories</a>
      <a href="#community">Community</a>
    </nav>

    <div class="nav-actions">
      <a href="login.php" class="nav-login">Login</a>
      <a href="signup.php" class="nav-register">Create account <span>→</span></a>
    </div>
  </div>
</header>

<main>

  <!-- HERO -->
  <section class="hero">
    <div class="hero-content">

      <div class="live-pill">
        <span class="live-dot"></span>
        Campus community active
      </div>

      <h1>
        Lost today,<br>
        <em>reunited tomorrow.</em>
      </h1>

      <p class="hero-copy">
        Reunite helps our campus community find lost items and return
        them to their rightful owners — faster, safer, and together.
      </p>

      <div class="hero-actions">
        <a href="signup.php" class="btn-primary">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="11" cy="11" r="7"></circle>
            <path d="m20 20-4-4"></path>
          </svg>
          Start searching
          <span>→</span>
        </a>

        <a href="signup.php" class="btn-secondary">
          <span>＋</span>
          Report a missing item
        </a>
      </div>

      <div class="hero-values">
        <div class="value">
          <span class="value-icon">
            <svg viewBox="0 0 24 24" fill="none">
              <circle cx="9" cy="8" r="3"></circle>
              <circle cx="17" cy="9" r="2.5"></circle>
              <path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"></path>
              <path d="M15 15c3 0 5 2 6 5"></path>
            </svg>
          </span>
          <div><strong>Trusted</strong><small>Campus community</small></div>
        </div>

        <span class="value-divider"></span>

        <div class="value">
          <span class="value-icon">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M12 3 20 6v5c0 5-3.3 8.5-8 10-4.7-1.5-8-5-8-10V6l8-3Z"></path>
              <path d="m9 12 2 2 4-4"></path>
            </svg>
          </span>
          <div><strong>Safer</strong><small>Verified handoffs</small></div>
        </div>

        <span class="value-divider"></span>

        <div class="value">
          <span class="value-icon">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M20.8 8.7c0 5.5-8.8 10.3-8.8 10.3S3.2 14.2 3.2 8.7A4.7 4.7 0 0 1 12 6.2a4.7 4.7 0 0 1 8.8 2.5Z"></path>
            </svg>
          </span>
          <div><strong>More</strong><small>Successful reunions</small></div>
        </div>
      </div>
    </div>

    <div class="hero-visual">
      <div class="hero-photo"></div>

      <div class="tips-card">
        <div class="tips-heading">
          <span class="tips-main-icon">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M9 18h6"></path>
              <path d="M10 22h4"></path>
              <path d="M8.5 14.5C7.6 13.6 7 12.4 7 11a5 5 0 0 1 10 0c0 1.4-.6 2.6-1.5 3.5-.8.8-1.5 1.5-1.5 2.5h-4c0-1-.7-1.7-1.5-2.5Z"></path>
            </svg>
          </span>
          <div>
            <h2>Quick tips</h2>
            <p>For a safer, smoother experience</p>
          </div>
        </div>

        <div class="tip-row">
          <span class="tip-icon">
            <svg viewBox="0 0 24 24" fill="none">
              <circle cx="11" cy="11" r="7"></circle>
              <path d="m20 20-4-4"></path>
            </svg>
          </span>
          <div>
            <strong>Use AI Smart Matching</strong>
            <p>We match colors, categories, descriptions, and locations automatically.</p>
          </div>
        </div>

        <div class="tip-row">
          <span class="tip-icon">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M12 3 20 6v5c0 5-3.3 8.5-8 10-4.7-1.5-8-5-8-10V6l8-3Z"></path>
            </svg>
          </span>
          <div>
            <strong>Meet in safe locations</strong>
            <p>Coordinate handoffs in public campus areas such as the library or admin block.</p>
          </div>
        </div>

        <div class="tip-row">
          <span class="tip-icon">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M18 8a6 6 0 0 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"></path>
              <path d="M10 20a2.5 2.5 0 0 0 4 0"></path>
            </svg>
          </span>
          <div>
            <strong>Get instant alerts</strong>
            <p>Receive updates when there is a possible match or new report.</p>
          </div>
        </div>

        <a href="#how" class="tips-link">Learn more <span>→</span></a>
      </div>
    </div>
  </section>

  <!-- STATS -->
  <section class="stats wrap" aria-label="Community statistics">
    <div class="stat">
      <span class="stat-icon">✦</span>
      <div><strong>250+</strong><small>Items reunited</small></div>
    </div>
    <div class="stat">
      <span class="stat-icon">♧</span>
      <div><strong>500+</strong><small>Active students</small></div>
    </div>
    <div class="stat">
      <span class="stat-icon">⌖</span>
      <div><strong>8</strong><small>Campus hubs</small></div>
    </div>
    <div class="stat">
      <span class="stat-icon">♡</span>
      <div><strong>95%</strong><small>Successful returns</small></div>
    </div>
  </section>

  <!-- HOW IT WORKS -->
  <section class="how" id="how">
    <div class="section-inner">
      <div class="section-title-row">
        <div>
          <span class="eyebrow">Simple by design</span>
          <h2>How it works <i></i></h2>
        </div>
        <a href="login.php" class="text-link">Learn more →</a>
      </div>

      <div class="steps">
        <article class="step">
          <div class="step-top">
            <span class="step-number">1</span>
            <span class="step-icon">↗</span>
          </div>
          <h3>Report or search</h3>
          <p>Upload details of a lost item or search through reported belongings.</p>
        </article>

        <span class="step-arrow">→</span>

        <article class="step">
          <div class="step-top">
            <span class="step-number">2</span>
            <span class="step-icon">✦</span>
          </div>
          <h3>AI matching</h3>
          <p>Our AI analyzes descriptions, images, categories, and locations to suggest matches.</p>
        </article>

        <span class="step-arrow">→</span>

        <article class="step">
          <div class="step-top">
            <span class="step-number">3</span>
            <span class="step-icon">♧</span>
          </div>
          <h3>Connect safely</h3>
          <p>Coordinate through the platform and meet in a safe public campus location.</p>
        </article>

        <span class="step-arrow">→</span>

        <article class="step">
          <div class="step-top">
            <span class="step-number">4</span>
            <span class="step-icon">✓</span>
          </div>
          <h3>Reunite!</h3>
          <p>Get your item back and help keep the campus community connected.</p>
        </article>
      </div>
    </div>
  </section>

  <!-- SUCCESS STORIES -->
  <section class="stories wrap" id="stories">
    <div class="section-title-row">
      <div>
        <span class="eyebrow">Real community moments</span>
        <h2>Recent success stories</h2>
        <p class="section-copy">Small finds can make a very big difference.</p>
      </div>
      <a href="signup.php" class="text-link">Join the community →</a>
    </div>

    <div class="story-grid">
      <article class="story-card">
        <div class="story-image">
          <img src="https://images.unsplash.com/photo-1610945265064-0e34e5519bbf?w=700&h=460&fit=crop&q=80" alt="Smartphone">
          <span>Reunited in 3h</span>
        </div>
        <div class="story-body">
          <h3>Lost smartphone</h3>
          <p class="story-place">SVGP Library</p>
          <p class="quote">“I thought my mobile was gone forever. Reunite matched my post with a found report almost instantly.”</p>
          <div class="story-person">
            <span class="person-avatar">P</span>
            <div><strong>Pujitha</strong><small>CME Student</small></div>
          </div>
        </div>
      </article>

      <article class="story-card">
        <div class="story-image">
          <img src="https://images.unsplash.com/photo-1629654297299-c8506221ca97?w=700&h=460&fit=crop&q=80" alt="Programming textbook">
          <span>Reunited in 1d</span>
        </div>
        <div class="story-body">
          <h3>Python textbook</h3>
          <p class="story-place">Block B Labs</p>
          <p class="quote">“A junior found my book and posted it here. I got it back before my exam.”</p>
          <div class="story-person">
            <span class="person-avatar">C</span>
            <div><strong>Charan</strong><small>CME Student</small></div>
          </div>
        </div>
      </article>

      <article class="story-card">
        <div class="story-image">
          <img src="https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?w=700&h=460&fit=crop&q=80" alt="Smartwatch">
          <span>Reunited in 6h</span>
        </div>
        <div class="story-body">
          <h3>Smartwatch</h3>
          <p class="story-place">Mainblock IT Lab</p>
          <p class="quote">“Spotted my watch on Reunite within minutes of posting. Lifesaver!”</p>
          <div class="story-person">
            <span class="person-avatar">R</span>
            <div><strong>Riya</strong><small>MEC Student</small></div>
          </div>
        </div>
      </article>
    </div>
  </section>

  <!-- COMMUNITY CTA -->
  <section class="community wrap" id="community">
    <div class="community-shape"></div>
    <div class="community-content">
      <span class="eyebrow">Together, we make a difference</span>
      <h2>Be part of a kinder campus.</h2>
      <p>One report can help someone find what they thought was gone for good.</p>
      <a href="signup.php" class="community-btn">Join the Reunite community <span>→</span></a>
    </div>
    <div class="community-art" aria-hidden="true">
      <span>⌂</span>
      <span>♧</span>
      <span>♡</span>
    </div>
  </section>

</main>

<footer class="footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <a href="index.php" class="brand">
        <span class="brand-mark">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M12 2L14 10L22 12L14 14L12 22L10 14L2 12L10 10L12 2Z" fill="currentColor"/>
          </svg>
        </span>
        <span>Reunite</span>
      </a>
      <p>Find. Report. Reunite.</p>
    </div>

    <div class="footer-links">
      <a href="#how">How it works</a>
      <a href="#stories">Success stories</a>
      <a href="login.php">Login</a>
      <a href="signup.php">Register</a>
    </div>

    <div class="footer-note">
      <span>Built by the community, for the community.</span>
      <strong>♥</strong>
    </div>
  </div>

  <div class="footer-bottom">
    © <?php echo date('Y'); ?> Reunite. All rights reserved.
  </div>
</footer>

<script src="js/intro.js?v=<?php echo time(); ?>"></script>
</body>
</html>
