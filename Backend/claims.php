<?php
/**
 * Reunite - Legacy Claim Route Decommission Notice
 * HTTP 410 Gone with graceful neutral upgrade notice.
 * Old claim logic removed per official Claim & Handover module specification.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';

// Check if request expects JSON
if (isApiRequest() || (!empty($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
    http_response_code(410);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'code' => 'CLAIM_UPGRADE_IN_PROGRESS',
        'message' => 'Claiming is being upgraded and will be back soon.'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Browser direct visit fallback
http_response_code(410);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Claiming System Upgrade — Reunite</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../Frontend/css/variables.css">
  <link rel="stylesheet" href="../Frontend/css/style.css">
  <style>
    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      background: var(--bg, #fbf7f4);
      color: var(--ink, #1a1511);
      font-family: 'Inter', sans-serif;
    }
    .upgrade-card {
      max-width: 500px;
      width: 100%;
      background: var(--surface, #ffffff);
      border: 1px solid rgba(255, 138, 92, 0.25);
      border-radius: 20px;
      padding: 2.25rem;
      text-align: center;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.06);
    }
    .upgrade-icon {
      width: 64px;
      height: 64px;
      margin: 0 auto 1.25rem;
      border-radius: 50%;
      background: rgba(255, 138, 92, 0.12);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #ff8a5c;
    }
    .upgrade-title {
      font-size: 1.4rem;
      font-weight: 700;
      margin-bottom: 0.75rem;
      color: var(--ink, #1a1511);
    }
    .upgrade-desc {
      font-size: 0.95rem;
      color: var(--muted, #6b635b);
      line-height: 1.6;
      margin-bottom: 1.75rem;
    }
    .btn-return {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      padding: 0.75rem 1.75rem;
      border-radius: 12px;
      background: #ff8a5c;
      color: #1a1511;
      font-weight: 600;
      font-size: 0.925rem;
      text-decoration: none;
      transition: all 0.2s ease;
    }
    .btn-return:hover {
      background: #ff9a72;
      transform: translateY(-1px);
    }
  </style>
</head>
<body>
  <div class="upgrade-card">
    <div class="upgrade-icon">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        <path d="M12 8v4"/>
        <path d="M12 16h.01"/>
      </svg>
    </div>
    <h1 class="upgrade-title">Claiming is Being Upgraded</h1>
    <p class="upgrade-desc">
      Our ownership claim and verification system is being upgraded to a new secure verification and live physical handover engine. It will be back soon.
    </p>
    <a href="../Frontend/index.php" class="btn-return">
      &larr; Return to Reunite Home
    </a>
  </div>
</body>
</html>
