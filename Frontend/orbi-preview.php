<?php
/**
 * Reunite — Orbi Animated Mascot Review & Inspector Dashboard
 * Phase 2: Visual preview across all states, sizes, light/dark modes, and motion preferences.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/orbi.php';

$states = [
    'idle' => [
        'name' => 'IDLE',
        'desc' => 'Default state. Calm floating, soft shadow pulsing, periodic natural eye blink.',
        'badge' => 'Default Pose'
    ],
    'loading' => [
        'name' => 'LOADING',
        'desc' => 'Waiting for data or network response. Eyes open with 3 rhythmic orange dots bouncing beneath.',
        'badge' => 'Page & Section Loaders'
    ],
    'thinking' => [
        'name' => 'THINKING',
        'desc' => 'AI processing queries or parsing photos. Eyes hidden, 3 illuminated dots pulsating inside visor.',
        'badge' => 'Talk to AI & Neural NLP'
    ],
    'happy' => [
        'name' => 'HAPPY',
        'desc' => 'Joyful outcome! Upward curved happy eyes, twinkling sparkles, and 3 energetic vertical hops.',
        'badge' => 'Matches & Claim Approvals'
    ],
    'notfound' => [
        'name' => 'NOT FOUND',
        'desc' => 'Puzzled / empty state. Question mark illuminated on visor with curious -6° tilted head angle.',
        'badge' => '404 & Empty Results'
    ]
];

$sizes = [32, 48, 64, 120, 200];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>Orbi Mascot Preview &amp; State Inspector — Reunite</title>
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
    body {
      background: var(--bg);
      color: var(--ink);
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      margin: 0;
      padding: 0;
      line-height: 1.5;
    }
    .preview-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2.5rem 1.5rem 5rem;
    }
    .preview-header {
      margin-bottom: 2.5rem;
      border-bottom: 1px solid var(--border);
      padding-bottom: 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .preview-eyebrow {
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 0.25rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .preview-title {
      font-family: 'Fraunces', serif;
      font-size: 2.25rem;
      font-weight: 500;
      margin: 0 0 0.5rem;
      color: var(--ink);
      letter-spacing: -0.02em;
    }
    .preview-sub {
      color: var(--muted);
      font-size: 0.95rem;
      max-width: 60ch;
      margin: 0;
    }
    .header-actions {
      display: flex;
      gap: 0.75rem;
      align-items: center;
    }
    .btn-theme-switch {
      background: var(--surface);
      border: 1px solid var(--border);
      color: var(--ink);
      padding: 0.55rem 1rem;
      border-radius: 9999px;
      font-size: 0.825rem;
      font-weight: 500;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.2s ease;
      box-shadow: var(--shadow-sm);
    }
    .btn-theme-switch:hover {
      border-color: var(--accent);
      color: var(--accent);
    }

    /* Section Cards */
    .section-wrap {
      margin-bottom: 3.5rem;
    }
    .section-title {
      font-family: 'Fraunces', serif;
      font-size: 1.45rem;
      font-weight: 500;
      margin: 0 0 0.35rem;
      color: var(--ink);
    }
    .section-desc {
      font-size: 0.875rem;
      color: var(--muted);
      margin: 0 0 1.25rem;
    }

    /* Interactive Live Sandbox Card */
    .sandbox-hero {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 1.25rem;
      padding: 2rem;
      display: grid;
      grid-template-columns: 280px 1fr;
      gap: 2rem;
      align-items: center;
      box-shadow: var(--shadow-md);
      position: relative;
      overflow: hidden;
    }
    @media (max-width: 820px) {
      .sandbox-hero {
        grid-template-columns: 1fr;
      }
    }
    .sandbox-stage {
      background: var(--bg-alt);
      border: 1px solid var(--border-soft);
      border-radius: 1rem;
      height: 240px;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
    }
    .sandbox-controls {
      display: flex;
      flex-direction: column;
      gap: 1.25rem;
    }
    .state-btn-group {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
    }
    .btn-state {
      background: var(--bg);
      border: 1px solid var(--border);
      color: var(--ink);
      padding: 0.5rem 0.9rem;
      border-radius: 0.5rem;
      font-size: 0.8125rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.15s ease;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
    }
    .btn-state:hover {
      border-color: var(--accent);
      background: var(--surface-alt);
    }
    .btn-state.active {
      background: var(--accent);
      border-color: var(--accent);
      color: #fff;
      box-shadow: 0 2px 8px rgba(196, 98, 45, 0.3);
    }
    .size-slider-row {
      display: flex;
      align-items: center;
      gap: 1rem;
      font-size: 0.8125rem;
      color: var(--muted);
    }
    .size-slider {
      flex: 1;
      max-width: 240px;
      accent-color: var(--accent);
    }

    /* States Showcase Grid */
    .states-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
      gap: 1.25rem;
    }
    .state-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 1rem;
      padding: 1.5rem 1.25rem;
      text-align: center;
      display: flex;
      flex-direction: column;
      align-items: center;
      box-shadow: var(--shadow-sm);
      transition: transform 0.2s ease, border-color 0.2s ease;
    }
    .state-card:hover {
      transform: translateY(-2px);
      border-color: var(--accent);
    }
    .state-card-preview {
      height: 140px;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      margin-bottom: 0.75rem;
    }
    .state-card-badge {
      font-size: 0.6875rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      padding: 0.2rem 0.55rem;
      border-radius: 9999px;
      background: var(--accent-tint);
      color: var(--accent);
      margin-bottom: 0.4rem;
    }
    .state-card-title {
      font-size: 1.05rem;
      font-weight: 600;
      margin: 0 0 0.35rem;
      color: var(--ink);
    }
    .state-card-desc {
      font-size: 0.78125rem;
      color: var(--muted);
      line-height: 1.4;
      margin: 0;
    }

    /* Sizes Comparison Row */
    .sizes-showcase {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 1rem;
      padding: 2rem 1.5rem;
      display: flex;
      align-items: flex-end;
      justify-content: space-around;
      flex-wrap: wrap;
      gap: 2rem;
      box-shadow: var(--shadow-sm);
    }
    .size-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.75rem;
    }
    .size-item-label {
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--muted);
      font-family: monospace;
    }

    /* Light vs Dark Comparison Split */
    .dual-theme-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
    }
    @media (max-width: 768px) {
      .dual-theme-grid {
        grid-template-columns: 1fr;
      }
    }
    .theme-preview-box {
      border-radius: 1rem;
      padding: 2rem 1.5rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1.5rem;
      box-shadow: var(--shadow-sm);
    }
    .theme-preview-box.light-theme-box {
      background: #FAF8F5;
      color: #1C1917;
      border: 1px solid #E7E3DC;
    }
    .theme-preview-box.dark-theme-box {
      background: #12100E;
      color: #F7F3EE;
      border: 1px solid #2C2520;
    }
    .theme-box-label {
      font-size: 0.8125rem;
      font-weight: 700;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      opacity: 0.75;
    }
    .theme-icons-row {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 1.25rem;
      flex-wrap: wrap;
    }

    /* Reduced Motion Section */
    .reduced-motion-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-left: 4px solid var(--accent);
      border-radius: 1rem;
      padding: 1.75rem;
      box-shadow: var(--shadow-sm);
    }
    .motion-toggle-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
      margin-bottom: 1rem;
    }
    .steps-box {
      background: var(--bg-alt);
      border: 1px solid var(--border);
      border-radius: 0.75rem;
      padding: 1rem 1.25rem;
      font-size: 0.84rem;
      color: var(--ink);
    }
    .steps-box ol {
      margin: 0.5rem 0 0;
      padding-left: 1.25rem;
    }
    .steps-box li {
      margin-bottom: 0.35rem;
    }
    kbd {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 4px;
      padding: 2px 6px;
      font-size: 0.75rem;
      font-family: monospace;
      color: var(--ink);
    }
  </style>
</head>
<body>

<!-- Global Nav Bar & Orbi Template -->
<?php include 'components/nav.php'; ?>

<main class="preview-container">

  <!-- ── Header ─────────────────────────────────────────── -->
  <header class="preview-header">
    <div>
      <div class="preview-eyebrow">
        <span>●</span> Phase 2 Preview &middot; Mascot Design System
      </div>
      <h1 class="preview-title">Meet Orbi</h1>
      <p class="preview-sub">
        The interactive student companion for Reunite. Powered by a single SVG source of truth with 5 distinct states, responsive vector scaling, and full dark-mode and reduced-motion compliance.
      </p>
    </div>
    <div class="header-actions">
      <button type="button" class="btn-theme-switch" id="toggleThemeLocalBtn" aria-label="Toggle light/dark theme">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
        <span>Toggle Theme</span>
      </button>
      <a href="home.php" class="btn-state" style="text-decoration: none;">&larr; Return Home</a>
    </div>
  </header>

  <!-- ── Section 1: Interactive Live Sandbox ─────────────── -->
  <section class="section-wrap">
    <h2 class="section-title">1. Live State &amp; Size Playground</h2>
    <p class="section-desc">Click any state button to test real-time state switching via <code>Orbi.setState(el, state)</code> and <code>Orbi.happy(el)</code>.</p>

    <div class="sandbox-hero">
      <div class="sandbox-stage" id="sandboxStage">
        <!-- Rendered via PHP helper -->
        <?php echo orbi('idle', 140, 'Orbi Playground Mascot'); ?>
      </div>

      <div class="sandbox-controls">
        <div>
          <label style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--muted); margin-bottom: 0.5rem; display: block;">Select Mascot State:</label>
          <div class="state-btn-group" id="stateBtnGroup">
            <button type="button" class="btn-state active" data-state="idle">IDLE</button>
            <button type="button" class="btn-state" data-state="loading">LOADING</button>
            <button type="button" class="btn-state" data-state="thinking">THINKING</button>
            <button type="button" class="btn-state" data-state="happy">HAPPY (Hop &amp; Sparkles)</button>
            <button type="button" class="btn-state" data-state="notfound">NOT FOUND (?)</button>
          </div>
        </div>

        <div>
          <label style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--muted); margin-bottom: 0.5rem; display: block;">Dynamic Scale:</label>
          <div class="size-slider-row">
            <span>32px</span>
            <input type="range" class="size-slider" id="sandboxSizeSlider" min="32" max="220" value="140" />
            <span id="sandboxSizeVal" style="font-weight: 600; min-width: 48px;">140px</span>
          </div>
        </div>

        <div style="font-size: 0.8125rem; color: var(--muted); line-height: 1.4;">
          <strong>Active State Attributes:</strong>
          <code id="sandboxAttrCode" style="display: block; background: var(--bg-alt); padding: 0.5rem 0.75rem; border-radius: 0.5rem; margin-top: 0.25rem; border: 1px solid var(--border);">data-state="idle" width="140" aria-label="Orbi Playground Mascot"</code>
        </div>
      </div>
    </div>
  </section>

  <!-- ── Section 2: Every State Matrix ───────────────────── -->
  <section class="section-wrap">
    <h2 class="section-title">2. All 5 States Side-by-Side</h2>
    <p class="section-desc">Every state layer is controlled entirely within the single SVG via CSS <code>[data-state]</code> rules.</p>

    <div class="states-grid">
      <?php foreach ($states as $st => $info): ?>
        <div class="state-card">
          <span class="state-card-badge"><?php echo htmlspecialchars($info['badge']); ?></span>
          <div class="state-card-preview">
            <?php echo orbi($st, 100); ?>
          </div>
          <h3 class="state-card-title"><?php echo htmlspecialchars($info['name']); ?></h3>
          <p class="state-card-desc"><?php echo htmlspecialchars($info['desc']); ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ── Section 3: All 5 Sizes Matrix ───────────────────── -->
  <section class="section-wrap">
    <h2 class="section-title">3. Scaling Matrix (32px, 48px, 64px, 120px, 200px)</h2>
    <p class="section-desc">Vector geometry scales cleanly from compact inline badges to hero illustration size.</p>

    <div class="sizes-showcase">
      <?php foreach ($sizes as $sz): ?>
        <div class="size-item">
          <div style="min-height: 200px; display: flex; align-items: flex-end; justify-content: center;">
            <?php echo orbi('idle', $sz); ?>
          </div>
          <span class="size-item-label"><?php echo $sz; ?>px</span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ── Section 4: Light & Dark Theme Backgrounds ───────── -->
  <section class="section-wrap">
    <h2 class="section-title">4. Background Contrast Harmony</h2>
    <p class="section-desc">Orbi's cream body (<code>#FFF3E6</code>), visor (<code>#2B1A12</code>), terracotta highlights (<code>#C4592A</code>), and dark-mode shadow adaptation provide high readability across surfaces.</p>

    <div class="dual-theme-grid">
      <!-- Light Canvas Box -->
      <div class="theme-preview-box light-theme-box">
        <span class="theme-box-label">Light Background (#FAF8F5 / #FFFFFF)</span>
        <div class="theme-icons-row">
          <?php echo orbi('idle', 72); ?>
          <?php echo orbi('loading', 72); ?>
          <?php echo orbi('thinking', 72); ?>
          <?php echo orbi('happy', 72); ?>
          <?php echo orbi('notfound', 72); ?>
        </div>
      </div>

      <!-- Dark Canvas Box -->
      <div class="theme-preview-box dark-theme-box" data-theme="dark">
        <span class="theme-box-label" style="color: #A89D94;">Dark Background (#12100E / #1C1814)</span>
        <div class="theme-icons-row">
          <?php echo orbi('idle', 72); ?>
          <?php echo orbi('loading', 72); ?>
          <?php echo orbi('thinking', 72); ?>
          <?php echo orbi('happy', 72); ?>
          <?php echo orbi('notfound', 72); ?>
        </div>
      </div>
    </div>
  </section>

  <!-- ── Section 5: Reduced Motion & Accessibility ───────── -->
  <section class="section-wrap">
    <h2 class="section-title">5. Reduced Motion &amp; Accessibility</h2>
    <p class="section-desc">When a student prefers reduced motion, all keyframe floats, pod rotations, blinks, and hops halt completely into a serene static pose without layout shifts.</p>

    <div class="reduced-motion-card">
      <div class="motion-toggle-row">
        <div>
          <h4 style="margin: 0 0 0.25rem; font-size: 1rem; color: var(--ink);">Simulate Reduced Motion in CSS:</h4>
          <p style="margin: 0; font-size: 0.8125rem; color: var(--muted);">You can toggle this preview class or emulate it natively in your browser tools.</p>
        </div>
        <button type="button" class="btn-state" id="simulateMotionBtn">
          Toggle Reduced Motion Preview
        </button>
      </div>

      <div class="steps-box">
        <strong>How to test natively in DevTools:</strong>
        <ol>
          <li>Open Browser DevTools (<kbd>F12</kbd> or <kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>I</kbd>).</li>
          <li>Press <kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>P</kbd> to open the Command Menu.</li>
          <li>Type <code>Rendering</code> and select <strong>Show Rendering</strong>.</li>
          <li>Find <strong>Emulate CSS media feature prefers-reduced-motion</strong> and choose <strong>prefers-reduced-motion: reduce</strong>.</li>
          <li>Observe that Orbi stays completely static while state switching remains instant and functional!</li>
        </ol>
      </div>
    </div>
  </section>

  <!-- ── Section 6: Phase 5 HAPPY State & Toast Live Tester ── -->
  <section class="section-wrap">
    <h2 class="section-title">6. Phase 5: HAPPY State &amp; Toast Integrations</h2>
    <p class="section-desc">Test real interactive triggers that invoke Orbi's celebration hops, twinkling sparkles, and toast integration.</p>

    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem;">
      <button type="button" class="btn-state" id="testMatchToastBtn" style="padding: 0.65rem 1.25rem;">
        🎉 Trigger Match Alert Toast
      </button>
      <button type="button" class="btn-state" id="testReportSuccessBtn" style="padding: 0.65rem 1.25rem;">
        ✨ Trigger Report Celebration
      </button>
      <button type="button" class="btn-state" id="testClaimToastBtn" style="padding: 0.65rem 1.25rem;">
        🤝 Trigger Claim Logged Toast
      </button>
    </div>
  </section>

  <!-- ── Section 7: Phase 6 NOT FOUND & 404 Pages ─────────── -->
  <section class="section-wrap">
    <h2 class="section-title">7. Phase 6: NOT FOUND State &amp; 404 Navigation</h2>
    <p class="section-desc">Verify Orbi's inquisitive -6° head tilt and illuminated question mark across error pages and empty containers.</p>

    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem;">
      <a href="404.php" class="btn-state" target="_blank" style="padding: 0.65rem 1.25rem; text-decoration: none;">
        🔎 View Dedicated 404.php Page &rarr;
      </a>
      <a href="item.php?id=999999" class="btn-state" target="_blank" style="padding: 0.65rem 1.25rem; text-decoration: none;">
        📦 View Missing Item 404 (`item.php?id=999999`) &rarr;
      </a>
      <a href="search.php" class="btn-state" target="_blank" style="padding: 0.65rem 1.25rem; text-decoration: none;">
        🔍 View Search Empty State (`search.php`) &rarr;
      </a>
      <a href="profile.php" class="btn-state" target="_blank" style="padding: 0.65rem 1.25rem; text-decoration: none;">
        👤 View Profile Empty Activity (`profile.php`) &rarr;
      </a>
    </div>
  </section>

</main>

<template id="orbi-template"><?php echo orbi('idle', 120); ?></template>
<script src="js/orbi.js?v=<?php echo time(); ?>"></script>
<script src="js/microinteractions.js?v=<?php echo time(); ?>"></script>

<script>
  (function() {
    // 1. Sandbox Live Controls
    const stage = document.getElementById('sandboxStage');
    const orbiEl = stage ? stage.querySelector('.orbi') : null;
    const stateBtns = document.querySelectorAll('#stateBtnGroup .btn-state');
    const sizeSlider = document.getElementById('sandboxSizeSlider');
    const sizeVal = document.getElementById('sandboxSizeVal');
    const attrCode = document.getElementById('sandboxAttrCode');

    function updateCodeView() {
      if (!orbiEl || !attrCode) return;
      const st = orbiEl.getAttribute('data-state') || 'idle';
      const w = orbiEl.getAttribute('width') || '140';
      const label = orbiEl.getAttribute('aria-label') || '';
      attrCode.textContent = `data-state="${st}" width="${w}" style="--orbi-size: ${w}px;" ${label ? `aria-label="${label}" role="img"` : 'aria-hidden="true"'}`;
    }

    stateBtns.forEach(btn => {
      btn.addEventListener('click', function() {
        const state = this.getAttribute('data-state');
        stateBtns.forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        if (state === 'happy') {
          // Test Orbi.happy() helper
          if (window.Orbi) {
            window.Orbi.happy(orbiEl, 2500).then(() => {
              // Return active button state back to idle after happy finishes
              stateBtns.forEach(b => b.classList.remove('active'));
              const idleBtn = document.querySelector('#stateBtnGroup [data-state="idle"]');
              if (idleBtn) idleBtn.classList.add('active');
              updateCodeView();
            });
          }
        } else {
          if (window.Orbi) {
            window.Orbi.setState(orbiEl, state, 'Orbi Playground Mascot');
          }
        }
        updateCodeView();
      });
    });

    if (sizeSlider && orbiEl) {
      sizeSlider.addEventListener('input', function() {
        const val = this.value;
        if (sizeVal) sizeVal.textContent = val + 'px';
        orbiEl.setAttribute('width', val);
        orbiEl.style.setProperty('--orbi-size', val + 'px');
        updateCodeView();
      });
    }

    // 2. Local Theme Switcher
    const localThemeBtn = document.getElementById('toggleThemeLocalBtn');
    if (localThemeBtn) {
      localThemeBtn.addEventListener('click', function() {
        const doc = document.documentElement;
        const isDark = doc.getAttribute('data-theme') === 'dark';
        if (isDark) {
          doc.removeAttribute('data-theme');
          localStorage.setItem('reunite_theme', 'light');
        } else {
          doc.setAttribute('data-theme', 'dark');
          localStorage.setItem('reunite_theme', 'dark');
        }
      });
    }

    // 3. Simulated Reduced Motion Toggle
    const motionBtn = document.getElementById('simulateMotionBtn');
    let isMotionSimulated = false;
    let motionStyleTag = null;

    if (motionBtn) {
      motionBtn.addEventListener('click', function() {
        isMotionSimulated = !isMotionSimulated;
        if (isMotionSimulated) {
          motionStyleTag = document.createElement('style');
          motionStyleTag.id = 'simulatedReducedMotion';
          motionStyleTag.textContent = `
            .orbi *,
            .orbi .orbi-float,
            .orbi .orbi-shadow,
            .orbi .orbi-pod,
            .orbi .orbi-glow,
            .orbi .orbi-blink,
            .orbi .orbi-dot,
            .orbi .orbi-tw,
            .orbi .orbi-tilt {
              animation: none !important;
              transition: none !important;
            }
          `;
          document.head.appendChild(motionStyleTag);
          this.textContent = 'Disable Reduced Motion Simulation';
          this.classList.add('active');
        } else {
          if (motionStyleTag) motionStyleTag.remove();
          this.textContent = 'Toggle Reduced Motion Preview';
          this.classList.remove('active');
        }
      });
    }

    // 4. Phase 5 Happy & Toast Test Triggers
    const testMatchBtn = document.getElementById('testMatchToastBtn');
    if (testMatchBtn) {
      testMatchBtn.addEventListener('click', () => {
        if (window.ReuniteToast) {
          window.ReuniteToast.show({
            title: 'Match Alert!',
            message: 'Found item #RF-882 matches your Lost AirPods Pro!',
            type: 'success',
            orbiState: 'happy',
            duration: 5000
          });
        }
      });
    }

    const testReportBtn = document.getElementById('testReportSuccessBtn');
    if (testReportBtn) {
      testReportBtn.addEventListener('click', () => {
        if (window.ReuniteToast) {
          window.ReuniteToast.show({
            title: 'Report Registered',
            message: 'Your report has been indexed and matching checks are live.',
            type: 'success',
            orbiState: 'happy',
            duration: 4500
          });
        }
        if (orbiEl && window.Orbi) {
          window.Orbi.happy(orbiEl, 3000);
        }
      });
    }

    const testClaimBtn = document.getElementById('testClaimToastBtn');
    if (testClaimBtn) {
      testClaimBtn.addEventListener('click', () => {
        if (window.ReuniteToast) {
          window.ReuniteToast.show({
            title: 'Claim Logged',
            message: 'Proof of ownership for #RF-409 submitted successfully.',
            type: 'success',
            orbiState: 'happy',
            duration: 4000
          });
        }
      });
    }

  })();
</script>
</body>
</html>
