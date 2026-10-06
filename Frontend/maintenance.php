<?php
/**
 * Reunite — Scheduled System Maintenance
 * Full-screen professional page. Noindex. No cache. Polls status every 20s.
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../Backend/config/config.php';
require_once __DIR__ . '/../Backend/functions.php';

// Check current maintenance state
$settings    = getMaintenanceSettings();
$isMaint     = $settings['enabled'];
$maintMsg    = $settings['message'] ?: null;
$maintEta    = $settings['eta'] ?: null;
$maintEmail  = $settings['support_email'] ?: null;

// If maintenance off, send users home
if (!$isMaint) {
    header('Location: home.php');
    exit;
}

$isAdmin  = !empty($_SESSION['reunite_admin_auth']) && $_SESSION['reunite_admin_auth'] === true;
$etaIso   = $maintEta ? date('c', strtotime($maintEta)) : null;
$etaHuman = $maintEta ? date('M j, Y · H:i T', strtotime($maintEta)) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <title>Scheduled Maintenance — Reunite</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,400&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/variables.css">
  <style>
    /* ── Page Shell ─────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --m-duration: 0.22s;
      --m-ease:     cubic-bezier(0.16, 1, 0.3, 1);
    }

    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after {
        animation-duration: 0.001ms !important;
        transition-duration: 0.001ms !important;
      }
    }

    html { scroll-behavior: smooth; }

    body {
      font-family: 'Inter', system-ui, sans-serif;
      background: var(--bg);
      color: var(--ink);
      min-height: 100svh;
      display: grid;
      grid-template-rows: auto 1fr auto;
      overflow-x: hidden;
      -webkit-font-smoothing: antialiased;
    }

    /* ── Animated Gradient Background ──────────────────── */
    .maint-bg {
      position: fixed;
      inset: 0;
      z-index: 0;
      overflow: hidden;
      pointer-events: none;
    }

    .maint-bg-gradient {
      position: absolute;
      inset: 0;
      background:
        radial-gradient(ellipse 80% 60% at 15% 20%, rgba(196, 98, 45, 0.12) 0%, transparent 60%),
        radial-gradient(ellipse 60% 70% at 85% 75%, rgba(196, 98, 45, 0.08) 0%, transparent 60%),
        var(--bg);
      animation: bgDrift 18s ease-in-out infinite alternate;
    }

    [data-theme="dark"] .maint-bg-gradient {
      background:
        radial-gradient(ellipse 80% 60% at 15% 20%, rgba(196, 98, 45, 0.16) 0%, transparent 60%),
        radial-gradient(ellipse 60% 70% at 85% 75%, rgba(224, 112, 64, 0.10) 0%, transparent 60%),
        var(--bg);
    }

    @keyframes bgDrift {
      0%   { background-size: 100% 100%, 100% 100%; background-position: 15% 20%, 85% 75%; }
      50%  { background-size: 110% 110%, 90% 100%; background-position: 20% 30%, 80% 65%; }
      100% { background-size: 95% 105%, 105% 95%; background-position: 12% 18%, 88% 80%; }
    }

    /* ── Floating Particles (pure CSS) ─────────────────── */
    .maint-particles {
      position: absolute;
      inset: 0;
    }

    .maint-particle {
      position: absolute;
      border-radius: 50%;
      background: var(--accent);
      opacity: 0;
      animation: particleFloat linear infinite;
    }

    @keyframes particleFloat {
      0%   { opacity: 0; transform: translateY(0) scale(0); }
      10%  { opacity: 0.25; }
      90%  { opacity: 0.12; }
      100% { opacity: 0; transform: translateY(-120px) scale(1.2) rotate(180deg); }
    }

    /* ── Admin Banner ───────────────────────────────────── */
    .admin-maint-banner {
      position: sticky;
      top: 0;
      z-index: 100;
      background: linear-gradient(90deg, rgba(196,98,45,0.95) 0%, rgba(224,112,64,0.95) 100%);
      color: #fff;
      font-size: 0.8125rem;
      font-weight: 600;
      padding: 0.6rem 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      backdrop-filter: blur(8px);
    }

    .admin-maint-banner a {
      color: #fff;
      text-decoration: underline;
      text-underline-offset: 3px;
    }

    /* ── Top Nav ────────────────────────────────────────── */
    .maint-topnav {
      position: relative;
      z-index: 10;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.25rem 2rem;
      max-width: 72rem;
      width: 100%;
      margin: 0 auto;
    }

    .maint-brand {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      text-decoration: none;
      color: var(--ink);
    }

    .maint-brand-name {
      font-family: 'Fraunces', serif;
      font-weight: 600;
      font-size: 1.125rem;
      letter-spacing: -0.02em;
    }

    .maint-theme-btn {
      background: none;
      border: 1px solid var(--border);
      color: var(--ink);
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all var(--m-duration) var(--m-ease);
    }
    .maint-theme-btn:hover {
      border-color: var(--accent);
      background: var(--accent-tint);
      color: var(--accent);
      transform: scale(1.06);
    }
    [data-theme="dark"] .maint-theme-btn .sun-icon { display: block; }
    [data-theme="dark"] .maint-theme-btn .moon-icon { display: none; }
    .maint-theme-btn .sun-icon { display: none; }
    .maint-theme-btn .moon-icon { display: block; }

    /* ── Main Stage ─────────────────────────────────────── */
    .maint-stage {
      position: relative;
      z-index: 10;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 2rem 1.5rem 3rem;
      text-align: center;
    }

    /* ── Animated Logo Core ─────────────────────────────── */
    .maint-logo-wrap {
      position: relative;
      width: 136px;
      height: 136px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 2.25rem;
      opacity: 0;
      transform: translateY(20px);
      animation: slideUp 0.6s var(--m-ease) 0.05s forwards;
    }

    /* Glow ring */
    .maint-logo-glow {
      position: absolute;
      inset: -4px;
      border-radius: 50%;
      background: var(--accent);
      opacity: 0.15;
      filter: blur(16px);
      animation: logoGlow 3.5s ease-in-out infinite alternate;
    }
    @keyframes logoGlow {
      0%  { opacity: 0.10; transform: scale(0.92); }
      100%{ opacity: 0.22; transform: scale(1.12); }
    }

    /* Outer orbit ring (dashed) */
    .maint-ring-outer {
      position: absolute;
      inset: 0;
      border: 1.5px dashed rgba(196,98,45,0.4);
      border-radius: 50%;
      animation: spin 22s linear infinite;
    }

    /* Spinning accent ring */
    .maint-ring-accent {
      position: absolute;
      inset: 10px;
      border: 2px solid transparent;
      border-top-color: var(--accent);
      border-radius: 50%;
      animation: spin 7s linear infinite reverse;
    }

    /* Orbiting sparkle dot */
    .maint-orbiter {
      position: absolute;
      width: 8px;
      height: 8px;
      background: var(--accent);
      border-radius: 50%;
      box-shadow: 0 0 8px var(--accent);
      top: 8px;
      left: 50%;
      transform-origin: 0 60px;
      animation: orbit 7s linear infinite;
    }
    @keyframes orbit {
      100% { transform: rotate(360deg); }
    }

    /* Inner logo badge */
    .maint-logo-core {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: var(--accent-tint);
      border: 2px solid rgba(196,98,45,0.3);
      display: flex;
      align-items: center;
      justify-content: center;
      animation: logoCorePulse 3.5s ease-in-out infinite alternate;
    }
    @keyframes logoCorePulse {
      0%  { box-shadow: 0 0 0 0 rgba(196,98,45,0.2); }
      100%{ box-shadow: 0 0 0 16px rgba(196,98,45,0); }
    }

    @keyframes spin {
      100% { transform: rotate(360deg); }
    }

    /* ── Status Pill ────────────────────────────────────── */
    .maint-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: var(--accent-tint);
      border: 1px solid rgba(196,98,45,0.3);
      color: var(--accent);
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      padding: 0.35rem 0.9rem;
      border-radius: 9999px;
      margin-bottom: 1.25rem;
      opacity: 0;
      animation: slideUp 0.5s var(--m-ease) 0.2s forwards;
    }
    .maint-pill-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--accent);
      box-shadow: 0 0 8px var(--accent);
      animation: blink 1.6s ease-in-out infinite;
    }
    @keyframes blink {
      0%,100%{ opacity:1; transform:scale(1); }
      50%    { opacity:0.4; transform:scale(0.75); }
    }

    /* ── Headline & Sub ─────────────────────────────────── */
    .maint-headline {
      font-family: 'Fraunces', serif;
      font-size: clamp(2rem, 5vw, 3rem);
      font-weight: 600;
      letter-spacing: -0.04em;
      line-height: 1.15;
      color: var(--ink);
      margin-bottom: 1rem;
      opacity: 0;
      animation: slideUp 0.55s var(--m-ease) 0.3s forwards;
    }

    .maint-subtext {
      font-size: clamp(0.9375rem, 2vw, 1.0625rem);
      line-height: 1.65;
      color: var(--muted);
      max-width: 520px;
      margin-bottom: 2rem;
      opacity: 0;
      animation: slideUp 0.55s var(--m-ease) 0.4s forwards;
    }

    /* ── Custom Message ──────────────────────────────────── */
    .maint-custom-msg {
      background: var(--surface-alt);
      border: 1px solid var(--border);
      border-left: 3px solid var(--accent);
      border-radius: 10px;
      padding: 0.85rem 1.1rem;
      font-size: 0.9375rem;
      color: var(--ink-soft);
      max-width: 520px;
      text-align: left;
      margin-bottom: 1.75rem;
      line-height: 1.6;
      opacity: 0;
      animation: slideUp 0.55s var(--m-ease) 0.45s forwards;
    }
    .maint-custom-msg-label {
      font-size: 0.6875rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--accent);
      margin-bottom: 0.35rem;
    }

    /* ── Countdown ──────────────────────────────────────── */
    .maint-countdown-wrap {
      display: flex;
      gap: 1rem;
      margin-bottom: 2rem;
      opacity: 0;
      animation: slideUp 0.55s var(--m-ease) 0.5s forwards;
    }
    .maint-countdown-unit {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.2rem;
      min-width: 60px;
    }
    .maint-countdown-num {
      font-family: 'JetBrains Mono', monospace;
      font-size: 1.875rem;
      font-weight: 600;
      color: var(--ink);
      line-height: 1;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 10px;
      width: 64px;
      padding: 0.6rem 0;
      text-align: center;
      box-shadow: var(--shadow-sm);
    }
    .maint-countdown-label {
      font-size: 0.625rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: var(--muted-2);
    }
    .maint-countdown-sep {
      font-size: 1.875rem;
      font-weight: 700;
      color: var(--muted-2);
      padding-top: 0.5rem;
    }

    /* ── ETA Card (when no countdown) ──────────────────── */
    .maint-eta-label {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.875rem;
      color: var(--muted);
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 9999px;
      padding: 0.35rem 0.9rem;
      margin-bottom: 2rem;
      opacity: 0;
      animation: slideUp 0.55s var(--m-ease) 0.5s forwards;
    }

    /* ── Progress Track ─────────────────────────────────── */
    .maint-progress-box {
      width: 100%;
      max-width: 500px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 1.25rem 1.5rem;
      margin-bottom: 2rem;
      text-align: left;
      opacity: 0;
      animation: slideUp 0.55s var(--m-ease) 0.55s forwards;
    }
    .maint-progress-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.8125rem;
      font-weight: 600;
      color: var(--ink);
      margin-bottom: 0.75rem;
    }
    .maint-progress-tag {
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.75rem;
      color: var(--accent);
    }
    .maint-progress-track {
      height: 6px;
      background: var(--border-soft);
      border-radius: 9999px;
      overflow: hidden;
    }
    .maint-progress-fill {
      height: 100%;
      width: 0;
      border-radius: 9999px;
      background: linear-gradient(90deg, var(--accent), #E07040);
      transition: width 1.2s var(--m-ease);
    }
    .maint-progress-fill.animated {
      animation: progressWave 3.5s ease-in-out infinite alternate;
    }
    @keyframes progressWave {
      0%  { width: 28%; margin-left: 0; }
      50% { width: 60%; margin-left: 10%; }
      100%{ width: 35%; margin-left: 40%; }
    }

    /* ── Actions ────────────────────────────────────────── */
    .maint-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      justify-content: center;
      opacity: 0;
      animation: slideUp 0.55s var(--m-ease) 0.65s forwards;
    }

    .btn-maint {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.75rem 1.5rem;
      border-radius: 9999px;
      font-size: 0.9375rem;
      font-weight: 600;
      cursor: pointer;
      border: none;
      text-decoration: none;
      transition: all var(--m-duration) var(--m-ease);
      font-family: inherit;
    }
    .btn-maint-primary {
      background: var(--accent);
      color: #fff;
      box-shadow: 0 4px 16px rgba(196,98,45,0.35);
    }
    .btn-maint-primary:hover, .btn-maint-primary:focus-visible {
      filter: brightness(1.06);
      transform: translateY(-2px);
      box-shadow: 0 6px 22px rgba(196,98,45,0.45);
    }
    .btn-maint-primary:active { transform: translateY(0); }
    .btn-maint-secondary {
      background: var(--surface);
      color: var(--ink);
      border: 1px solid var(--border);
    }
    .btn-maint-secondary:hover, .btn-maint-secondary:focus-visible {
      background: var(--bg-alt);
      border-color: var(--muted-2);
      transform: translateY(-2px);
    }
    .btn-maint:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none !important;
    }

    /* ── Support Email ──────────────────────────────────── */
    .maint-support {
      margin-top: 1.5rem;
      font-size: 0.875rem;
      color: var(--muted);
      opacity: 0;
      animation: slideUp 0.5s var(--m-ease) 0.75s forwards;
    }
    .maint-support a {
      color: var(--accent);
      text-decoration: underline;
      text-underline-offset: 3px;
    }

    /* ── Footer ─────────────────────────────────────────── */
    .maint-footer {
      position: relative;
      z-index: 10;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.25rem 2rem;
      max-width: 72rem;
      width: 100%;
      margin: 0 auto;
      font-size: 0.75rem;
      color: var(--muted-2);
      border-top: 1px solid var(--border-soft);
      opacity: 0;
      animation: slideUp 0.5s var(--m-ease) 0.85s forwards;
    }
    .maint-footer-admin {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      color: var(--muted-2);
      text-decoration: none;
      font-weight: 500;
      transition: color 0.15s;
    }
    .maint-footer-admin:hover { color: var(--accent); }

    /* ── Live status chip ───────────────────────────────── */
    .maint-live-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      font-size: 0.75rem;
      color: var(--muted);
      background: var(--surface-alt);
      border: 1px solid var(--border);
      border-radius: 9999px;
      padding: 0.25rem 0.65rem;
    }
    .maint-live-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #10B981;
      box-shadow: 0 0 6px #10B981;
      animation: blink 2s ease-in-out infinite;
    }

    /* ── Shared slide-up ────────────────────────────────── */
    @keyframes slideUp {
      from { opacity: 0; transform: translateY(18px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── ARIA Live Region ───────────────────────────────── */
    .sr-only {
      position: absolute;
      width: 1px;
      height: 1px;
      overflow: hidden;
      clip: rect(0 0 0 0);
      white-space: nowrap;
    }

    /* ── Mobile ─────────────────────────────────────────── */
    @media (max-width: 640px) {
      .maint-topnav, .maint-footer { padding: 1rem; }
      .maint-countdown-wrap { gap: 0.5rem; }
      .maint-countdown-num { width: 52px; font-size: 1.5rem; }
      .maint-footer { flex-direction: column; gap: 0.5rem; text-align: center; }
    }
  </style>
</head>
<body>
  <?php
  // Detect theme from cookie/session for PHP-set attribute
  $theme = '';
  ?>
  <script>
    (function(){
      var t = localStorage.getItem('reunite_theme');
      if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.setAttribute('data-theme','dark');
      }
    })();
  </script>

  <!-- Ambient animated background -->
  <div class="maint-bg" aria-hidden="true">
    <div class="maint-bg-gradient"></div>
    <div class="maint-particles" id="maintParticles"></div>
  </div>

  <!-- ARIA live region for auto-recovery announcement -->
  <div role="status" aria-live="polite" aria-atomic="true" class="sr-only" id="maintAriaStatus"></div>

  <?php if ($isAdmin): ?>
  <!-- Admin-only banner: visible only to logged-in admins -->
  <div class="admin-maint-banner" role="alert">
    <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg> Maintenance Mode is ON — only admins can access the site.</span>
    <a href="admin.php">Admin Console &rarr;</a>
  </div>
  <?php endif; ?>

  <!-- Top Nav -->
  <header>
    <nav class="maint-topnav">
      <a href="<?= $isAdmin ? 'home.php' : '#' ?>" class="maint-brand" aria-label="Reunite home">
        <svg width="26" height="26" viewBox="0 0 28 28" fill="none" aria-hidden="true">
          <circle cx="14" cy="14" r="14" fill="#C4622D"/>
          <path d="M14 7c-3.866 0-7 3.134-7 7 0 2.21 1.03 4.183 2.645 5.474L8 21h12l-1.645-1.526C19.97 18.183 21 16.21 21 14c0-3.866-3.134-7-7-7z" fill="white" fill-opacity="0.25"/>
          <circle cx="14" cy="14" r="3" fill="white"/>
          <path d="M14 8v3M14 17v3M8 14H5M23 14h-3" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-opacity="0.5"/>
        </svg>
        <span class="maint-brand-name">Reunite</span>
      </a>
      <button type="button" class="maint-theme-btn" id="maintThemeBtn" aria-label="Toggle colour theme" title="Toggle theme">
        <svg class="sun-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
        <svg class="moon-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>
    </nav>
  </header>

  <!-- Main Stage -->
  <main class="maint-stage">

    <!-- Animated Logo -->
    <div class="maint-logo-wrap" aria-hidden="true">
      <div class="maint-logo-glow"></div>
      <div class="maint-ring-outer"></div>
      <div class="maint-ring-accent"></div>
      <div class="maint-orbiter"></div>
      <div class="maint-logo-core">
        <svg width="36" height="36" viewBox="0 0 28 28" fill="none" aria-hidden="true">
          <circle cx="14" cy="14" r="14" fill="#C4622D"/>
          <path d="M14 7c-3.866 0-7 3.134-7 7 0 2.21 1.03 4.183 2.645 5.474L8 21h12l-1.645-1.526C19.97 18.183 21 16.21 21 14c0-3.866-3.134-7-7-7z" fill="white" fill-opacity="0.25"/>
          <circle cx="14" cy="14" r="3" fill="white"/>
          <path d="M14 8v3M14 17v3M8 14H5M23 14h-3" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-opacity="0.5"/>
        </svg>
      </div>
    </div>

    <!-- Status Pill -->
    <div class="maint-pill" role="status">
      <span class="maint-pill-dot" aria-hidden="true"></span>
      Maintenance in progress
    </div>

    <!-- Headline -->
    <h1 class="maint-headline">We'll be right back.</h1>

    <!-- Subtext -->
    <p class="maint-subtext">
      The Reunite platform is undergoing scheduled maintenance and optimization.
      We'll be back up shortly — thank you for your patience.
    </p>

    <!-- Admin custom message -->
    <?php if ($maintMsg): ?>
    <div class="maint-custom-msg" role="note" aria-label="Message from our team">
      <div class="maint-custom-msg-label">Message from our team</div>
      <?= htmlspecialchars($maintMsg) ?>
    </div>
    <?php endif; ?>

    <!-- Countdown if ETA is set -->
    <?php if ($etaIso): ?>
    <div class="maint-countdown-wrap" id="maintCountdownWrap" aria-label="Time remaining">
      <div class="maint-countdown-unit">
        <div class="maint-countdown-num" id="cdHours">--</div>
        <div class="maint-countdown-label">Hours</div>
      </div>
      <div class="maint-countdown-sep" aria-hidden="true">:</div>
      <div class="maint-countdown-unit">
        <div class="maint-countdown-num" id="cdMinutes">--</div>
        <div class="maint-countdown-label">Minutes</div>
      </div>
      <div class="maint-countdown-sep" aria-hidden="true">:</div>
      <div class="maint-countdown-unit">
        <div class="maint-countdown-num" id="cdSeconds">--</div>
        <div class="maint-countdown-label">Seconds</div>
      </div>
    </div>
    <div class="maint-eta-label">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Estimated: <?= htmlspecialchars($etaHuman) ?>
    </div>
    <?php endif; ?>

    <!-- Progress simulation -->
    <div class="maint-progress-box">
      <div class="maint-progress-header">
        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg> System Restoration</span>
        <span class="maint-progress-tag" id="maintProgressTag">Optimizing...</span>
      </div>
      <div class="maint-progress-track" role="progressbar" aria-label="Maintenance progress" aria-valuemin="0" aria-valuemax="100">
        <div class="maint-progress-fill animated" id="maintProgressFill"></div>
      </div>
    </div>

    <!-- Action buttons -->
    <div class="maint-actions">
      <button type="button" class="btn-maint btn-maint-primary" id="btnMaintCheck" aria-label="Check if site is back online">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.1-8.24"/></svg>
        Check Status
      </button>
      <?php if ($isAdmin): ?>
      <a href="admin.php" class="btn-maint btn-maint-secondary" aria-label="Go to Admin Console">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Admin Console
      </a>
      <?php endif; ?>
    </div>

    <!-- Support email -->
    <?php if ($maintEmail): ?>
    <p class="maint-support">
      Need help? <a href="mailto:<?= htmlspecialchars($maintEmail) ?>"><?= htmlspecialchars($maintEmail) ?></a>
    </p>
    <?php endif; ?>

  </main>

  <!-- Footer -->
  <footer class="maint-footer">
    <span>
      <span class="maint-live-chip">
        <span class="maint-live-dot" aria-hidden="true"></span>
        Auto-checking every 20 s
      </span>
    </span>
    <a href="admin.php" class="maint-footer-admin" aria-label="Admin clearance login">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      Admin Clearance
    </a>
  </footer>

  <script>
  (function () {
    'use strict';

    /* ── Theme toggle ─────────────────────────────────── */
    var themeBtn = document.getElementById('maintThemeBtn');
    if (themeBtn) {
      themeBtn.addEventListener('click', function () {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
          document.documentElement.removeAttribute('data-theme');
          localStorage.setItem('reunite_theme', 'light');
        } else {
          document.documentElement.setAttribute('data-theme', 'dark');
          localStorage.setItem('reunite_theme', 'dark');
        }
      });
    }

    /* ── Floating particles ───────────────────────────── */
    var pContainer = document.getElementById('maintParticles');
    if (pContainer && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      for (var i = 0; i < 18; i++) {
        (function (i) {
          var p = document.createElement('div');
          p.className = 'maint-particle';
          var size = Math.random() * 5 + 3;
          p.style.cssText = [
            'width:' + size + 'px',
            'height:' + size + 'px',
            'left:' + (Math.random() * 100) + '%',
            'bottom:' + (Math.random() * 40) + '%',
            'animation-duration:' + (Math.random() * 14 + 10) + 's',
            'animation-delay:' + (Math.random() * -16) + 's',
          ].join(';');
          pContainer.appendChild(p);
        })(i);
      }
    }

    /* ── Progress tag cycling ─────────────────────────── */
    var tags = [
      'Optimizing databases…',
      'Rebuilding vector index…',
      'Flushing cache…',
      'Running diagnostics…',
      'Validating integrity…',
      'Almost there…',
    ];
    var tagEl = document.getElementById('maintProgressTag');
    var ti = 0;
    if (tagEl) {
      setInterval(function () {
        ti = (ti + 1) % tags.length;
        tagEl.textContent = tags[ti];
      }, 3500);
    }

    /* ── ETA countdown ────────────────────────────────── */
    var etaIso = <?= $etaIso ? json_encode($etaIso) : 'null' ?>;
    if (etaIso) {
      var etaTs = new Date(etaIso).getTime();
      function updateCountdown() {
        var diff = etaTs - Date.now();
        if (diff <= 0) {
          document.getElementById('cdHours').textContent   = '00';
          document.getElementById('cdMinutes').textContent = '00';
          document.getElementById('cdSeconds').textContent = '00';
          return;
        }
        var h = Math.floor(diff / 3600000);
        var m = Math.floor((diff % 3600000) / 60000);
        var s = Math.floor((diff % 60000) / 1000);
        document.getElementById('cdHours').textContent   = String(h).padStart(2,'0');
        document.getElementById('cdMinutes').textContent = String(m).padStart(2,'0');
        document.getElementById('cdSeconds').textContent = String(s).padStart(2,'0');
      }
      updateCountdown();
      setInterval(updateCountdown, 1000);
    }

    /* ── Status polling & auto-redirect ──────────────── */
    var ariaStatus = document.getElementById('maintAriaStatus');
    var pollInterval = null;

    function checkStatus(isManual) {
      var btn = document.getElementById('btnMaintCheck');
      if (isManual && btn) { btn.disabled = true; btn.innerHTML = 'Checking…'; }

      fetch('api/maintenance-status.php', { cache: 'no-store' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.enabled) {
            // Maintenance is OFF — redirect home
            if (ariaStatus) ariaStatus.textContent = 'Maintenance is complete. Redirecting to the homepage.';
            if (pollInterval) clearInterval(pollInterval);
            if (btn) btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px; margin-right:4px;"><path d="M20 6 9 17l-5-5"/></svg> Back online! Redirecting…';
            setTimeout(function () { window.location.href = 'home.php'; }, 800);
          } else {
            if (isManual && btn) {
              btn.disabled = false;
              btn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.1-8.24"/></svg> Check Status';
            }
          }
        })
        .catch(function () {
          if (isManual && btn) {
            btn.disabled = false;
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px; margin-right:4px;"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.1-8.24"/></svg> Retry Check';
          }
        });
    }

    // Manual check button
    var checkBtn = document.getElementById('btnMaintCheck');
    if (checkBtn) {
      checkBtn.addEventListener('click', function () { checkStatus(true); });
    }

    // Auto-poll every 20 seconds
    pollInterval = setInterval(function () { checkStatus(false); }, 20000);

    // Also check once after 3s (in case admin just turned it off)
    setTimeout(function () { checkStatus(false); }, 3000);

  })();
  </script>
</body>
</html>
