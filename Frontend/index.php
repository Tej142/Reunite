<?php
require_once __DIR__ . '/includes/auth.php';
// If already logged in, redirect to the main student Homepage Dashboard (home.php)
if (is_logged_in()) {
    header("Location: home.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Welcome to Reunite — Community Lost &amp; Found</title>
<script>
  (function(){
    var t = localStorage.getItem('reunite_theme');
    if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.setAttribute('data-theme', 'dark');
    }
  })();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/intro.css?v=<?php echo time(); ?>">
</head>
<body>

<header class="nav">
  <div class="nav-row">
    <a class="brand" href="index.php">
      <svg width="28" height="28" viewBox="0 0 28 28" fill="none" aria-hidden="true">
        <circle cx="14" cy="14" r="14" fill="#C4622D"/>
        <path d="M14 7c-3.866 0-7 3.134-7 7 0 2.21 1.03 4.183 2.645 5.474L8 21h12l-1.645-1.526C19.97 18.183 21 16.21 21 14c0-3.866-3.134-7-7-7z" fill="white" fill-opacity="0.25"/>
        <circle cx="14" cy="14" r="3" fill="white"/>
        <path d="M14 8v3M14 17v3M8 14H5M23 14h-3" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-opacity="0.5"/>
      </svg>
      <span class="serif">Reunite</span>
    </a>
    <div class="nav-actions">
      <!-- Theme Toggle Button -->
      <button type="button" class="nav-theme-btn" id="themeToggleBtn" aria-label="Toggle dark mode" title="Toggle theme">
        <svg class="sun-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="5"></circle>
          <line x1="12" y1="1" x2="12" y2="3"></line>
          <line x1="12" y1="21" x2="12" y2="23"></line>
          <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
          <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
          <line x1="1" y1="12" x2="3" y2="12"></line>
          <line x1="21" y1="12" x2="23" y2="12"></line>
          <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
          <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
        </svg>
        <svg class="moon-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
        </svg>
      </button>
      <a class="link-plain" href="login.php">Login</a>
      <a class="btn btn-primary" href="signup.php">Create account</a>
    </div>
  </div>
</header>

<!-- ================= INTRO / SPLASH HERO ================= -->
<section class="intro" id="top">
  <div class="intro-bg-blob blob-1"></div>
  <div class="intro-bg-blob blob-2"></div>

  <div class="converge-stage">
    <span class="orbit-icon" style="--sx:0px; --sy:-230px; --d:0s;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="7" width="18" height="12" rx="2"/><path d="M7 7V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2"/><circle cx="16" cy="13" r="1.6"/></svg>
    </span>
    <span class="orbit-icon" style="--sx:218px; --sy:-71px; --d:0.12s;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="16" r="3"/><circle cx="16" cy="16" r="3"/><path d="M11 16h2M13 13l3-9 4 2"/></svg>
    </span>
    <span class="orbit-icon" style="--sx:135px; --sy:186px; --d:0.24s;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M7 10V7a5 5 0 0 1 10 0v3"/><rect x="5" y="10" width="14" height="10" rx="2"/></svg>
    </span>
    <span class="orbit-icon" style="--sx:-135px; --sy:186px; --d:0.36s;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="6" width="20" height="13" rx="2"/><circle cx="12" cy="12.5" r="3.2"/></svg>
    </span>
    <span class="orbit-icon" style="--sx:-218px; --sy:-71px; --d:0.48s;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="6" y="4" width="12" height="16" rx="3"/><circle cx="12" cy="8" r="2"/></svg>
    </span>

    <div class="mark-center">
      <div class="mark-glow"></div>
      <div class="mark-ring"></div>
      <svg viewBox="0 0 24 24" fill="none"><path d="M12 2L14 10L22 12L14 14L12 22L10 14L2 12L10 10L12 2Z" fill="#F5F1E7"/></svg>
    </div>
  </div>

  <div class="intro-eyebrow"><span>👋</span> New here? Welcome to the community.</div>

  <h1>
    <span class="line"><span>Things get lost.</span></span>
    <span class="line"><span>Communities bring them back.</span></span>
  </h1>

  <p class="lede">Reunite connects people who've lost something with neighbors who've found it — matched automatically, reunited safely.</p>

  <div class="intro-ctas">
    <a class="btn btn-primary btn-lg" href="signup.php">Create free account
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </a>
    <a class="btn btn-ghost btn-lg" href="login.php">Log in</a>
  </div>

  <p class="intro-trust"><strong>14,200+</strong> items reunited &middot; a new match roughly every 6 hours</p>

  <a class="scroll-cue" href="#highlights">
    <span class="cue-text-more">Learn more</span>
    <span class="cue-text-less">Show less</span>
    <svg class="cue-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
  </a>
</section>

<!-- ================= HIGHLIGHTS ================= -->
<section class="highlights wrap" id="highlights">
  <div class="section-head reveal">
    <div class="section-eyebrow">Why people join</div>
    <h2>Everything you need to get something back.</h2>
    <p>No account fees, no hunting through classifieds — just a fast way to report, match, and reconnect.</p>
  </div>

  <div class="highlight-grid">
    <div class="h-card reveal reveal-1">
      <div class="h-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
      </div>
      <h3>Post in under a minute</h3>
      <p>Snap a photo of what you lost or found, drop a pin near where it happened, and you're live on the board.</p>
    </div>
    <div class="h-card reveal reveal-2">
      <div class="h-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a4 4 0 0 1 4 4v1a4 4 0 0 1-8 0V6a4 4 0 0 1 4-4z"/><path d="M6 11v2a6 6 0 0 0 12 0v-2M12 19v3M8 22h8"/></svg>
      </div>
      <h3>AI matches for you</h3>
      <p>We read the color, brand, and location from every post and quietly check it against the board — no manual searching.</p>
    </div>
    <div class="h-card reveal reveal-3">
      <div class="h-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 1 4 4H5a4 4 0 0 1-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <h3>Reunite safely</h3>
      <p>Message through Reunite without sharing your number, and follow our checklist for a safe, verified handoff.</p>
    </div>
  </div>
</section>

<!-- ================= RECENT SUCCESS STORIES ================= -->
<section class="success-stories wrap">
  <div class="section-head reveal">
    <div class="section-eyebrow">Real matches</div>
    <h2>Recent Success Stories</h2>
    <p>See how SVGP Poly students are getting their belongings back.</p>
  </div>
  
  <div class="stories-grid">
    <div class="story-card reveal reveal-1">
      <div class="story-image-wrap">
        <img src="https://images.unsplash.com/photo-1610945265064-0e34e5519bbf?w=500&fit=crop&q=80" alt="Lost Samsung Mobile" class="story-image" />
        <span class="story-badge">Reunited in 3h</span>
      </div>
      <div class="story-content">
        <h3>Lost samsung mobile</h3>
        <p class="story-location">SVGP Library</p>
        <p class="story-quote">"I thought my mobile was gone forever! SVGP's Reunite matched my lost post with Vignesh's found post instantly. Safe handoff next morning."</p>
        <div class="story-author">
          <div class="author-avatar" style="background-image: url('https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&h=100&fit=crop&q=80')"></div>
          <div>
            <div class="author-name">Pujitha</div>
            <div class="author-meta">CME Student</div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="story-card reveal reveal-2">
      <div class="story-image-wrap">
        <img src="https://images.unsplash.com/photo-1629654297299-c8506221ca97?w=500&fit=crop&q=80" alt="Python Programming Textbook" class="story-image" />
        <span class="story-badge">Reunited in 1d</span>
      </div>
      <div class="story-content">
        <h3>Python Textbook</h3>
        <p class="story-location">Block B Labs</p>
        <p class="story-quote">"Left my book in the lab. A junior found it and posted it here. Within hours, I was notified and got it back before my exam!"</p>
        <div class="story-author">
          <div class="author-avatar" style="background-image: url('https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&h=100&fit=crop&q=80')"></div>
          <div>
            <div class="author-name">R. Charan</div>
            <div class="author-meta">CME Student</div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="story-card reveal reveal-3">
      <div class="story-image-wrap">
        <img src="https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?w=500&fit=crop&q=80" alt="Lost Firebolt Smartwatch" class="story-image" />
        <span class="story-badge">Reunited in 6h</span>
      </div>
      <div class="story-content">
        <h3>Firebolt watch</h3>
        <p class="story-location">Mainblock IT Lab</p>
        <p class="story-quote">"Dropped my watch somewhere near the It lab in Mainblock. Spotted them on Reunite within 15 minutes of posting. Lifesaver app!"</p>
        <div class="story-author">
          <div class="author-avatar" style="background-image: url('https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=100&h=100&fit=crop&q=80')"></div>
          <div>
            <div class="author-name">Riya M.</div>
            <div class="author-meta">MEC Student</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ================= CTA BANNER ================= -->
<section class="cta-banner">
  <div class="cta-inner reveal">
    <div class="cta-dots"></div>
    <h2>Ready to see what's already been found nearby?</h2>
    <p>Create your free account and get notified the moment something matches.</p>
    <div class="intro-ctas">
      <a class="btn btn-primary btn-lg" href="signup.php">Create free account</a>
      <a class="btn btn-ghost btn-lg" href="login.php">I already have an account</a>
    </div>
  </div>
</section>

<footer class="footer wrap">
  <div class="footer-row">
    <div class="footer-brand">
      <span class="brand-mark-sm">
        <svg viewBox="0 0 24 24" fill="none"><path d="M12 2L14 10L22 12L14 14L12 22L10 14L2 12L10 10L12 2Z" fill="#F5F1E7"/></svg>
      </span>
      &copy; 2026 Reunite. Built by the community, for the community.
    </div>
    <div class="footer-links">
      <a href="#highlights">How it works</a>
      <a href="login.php">Login</a>
      <a href="signup.php">Register</a>
    </div>
  </div>
</footer>

<script src="js/intro.js?v=<?php echo time(); ?>"></script>
<script>
  (function() {
    const themeBtn = document.getElementById('themeToggleBtn');
    if (themeBtn) {
      themeBtn.addEventListener('click', function() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        if (newTheme === 'dark') {
          document.documentElement.setAttribute('data-theme', 'dark');
          localStorage.setItem('reunite_theme', 'dark');
        } else {
          document.documentElement.removeAttribute('data-theme');
          localStorage.setItem('reunite_theme', 'light');
        }
      });
    }
  })();
</script>
</body>
</html>
