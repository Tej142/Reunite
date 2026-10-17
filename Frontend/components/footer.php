<?php
// Reusable Professional Footer Component for Reunite Platform with Support Hub
$is_user_logged = function_exists('is_logged_in') ? is_logged_in() : (isset($_SESSION['user_id']) || isset($_SESSION['user_pin']));
?>
<link rel="stylesheet" href="css/footer.css?v=<?php echo time(); ?>">
<footer class="site-footer" role="contentinfo">
  <div class="footer-container">
    
    <!-- Top Grid: Brand, Navigation, AI Tech, and Support Hub -->
    <div class="footer-top-grid">
      
      <!-- Brand Column -->
      <div class="footer-brand-col">
        <a href="<?php echo $is_user_logged ? 'home.php' : 'index.php'; ?>" class="footer-brand-logo" aria-label="Reunite Home">
          <svg width="34" height="34" viewBox="0 0 28 28" fill="none" aria-hidden="true" style="border-radius: 8px;">
            <rect width="28" height="28" rx="8" fill="#E06D38"/>
            <path d="M 14 6 Q 14 14 20.5 14 Q 14 14 14 22 Q 14 14 7.5 14 Q 14 14 14 6 Z" fill="#FDFBF7"/>
          </svg>
          <span class="footer-brand-title">Reunite</span>
        </a>

        <p class="footer-tagline">
          Smart, AI-powered lost &amp; found and missing person recovery platform connecting communities and reuniting belongings through cutting-edge visual recognition.
        </p>

        <!-- Live Cloud AI Status -->
        <div class="footer-status-badge">
          <span class="status-dot-pulse"></span>
          <span>Cloud AI Matching Engine &bull; Active</span>
        </div>
      </div>

      <!-- Column 1: Navigation -->
      <div>
        <h4 class="footer-col-title">Navigation</h4>
        <ul class="footer-nav-list">
          <li><a href="<?php echo $is_user_logged ? 'home.php' : 'index.php'; ?>">Home Dashboard</a></li>
          <li><a href="search.php">Search Directory</a></li>
          <li><a href="report-lost-item.php">Report Missing Item</a></li>
          <li><a href="report-found-item.php">Upload Found Item</a></li>
          <li><a href="home.php#board">Community Feed</a></li>
        </ul>
      </div>

      <!-- Column 2: AI Capabilities -->
      <div>
        <h4 class="footer-col-title">AI Technology</h4>
        <ul class="footer-nav-list">
          <li><a href="search.php">Facial Recognition <span class="footer-nav-badge">AI</span></a></li>
          <li><a href="search.php">Visual DNA Matcher</a></li>
          <li><a href="search.php?action=voice">Voice Copilot Mode <span class="footer-nav-badge">Live</span></a></li>
          <li><a href="home.php#how">How Matching Works</a></li>
          <li><a href="search.php">Real-Time Geotagging</a></li>
        </ul>
      </div>

      <!-- Column 3: Help & Contact Hub (Phone, Email, Support Button) -->
      <div class="footer-support-card">
        <h4 class="footer-col-title">Help &amp; Contact</h4>
        
        <!-- Phone Helpline Row -->
        <a href="tel:+18007386483" class="contact-item-row" title="Call Reunite Helpline">
          <div class="contact-icon-box">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
          </div>
          <div class="contact-text-wrap">
            <span class="contact-label">24/7 Helpline Phone</span>
            <span class="contact-val">+1 (800) 738-6483</span>
          </div>
        </a>

        <!-- Support Email Row -->
        <a href="mailto:support@reunite.site" class="contact-item-row" title="Email Reunite Support">
          <div class="contact-icon-box">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
          </div>
          <div class="contact-text-wrap">
            <span class="contact-label">Support Email</span>
            <span class="contact-val">support@reunite.site</span>
          </div>
        </a>

        <!-- Direct Get Support Button -->
        <button type="button" class="btn-footer-support" onclick="openReuniteSupportModal()" aria-label="Open support helpdesk">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
          <span>Get Instant Support</span>
        </button>
      </div>

    </div>

    <!-- Divider Line -->
    <div class="footer-divider"></div>

    <!-- Bottom Row: Copyright & Legal Links -->
    <div class="footer-bottom-row">
      <div>
        &copy; <?php echo date('Y'); ?> <strong>Reunite</strong> &middot; Empowering communities to reconnect people and belongings.
      </div>
      <ul class="footer-legal-links">
        <li><a href="home.php#how">Privacy Policy</a></li>
        <li><a href="home.php#how">Terms of Service</a></li>
        <li><a href="home.php#how">Security &amp; Ethics</a></li>
        <li><a href="mailto:support@reunite.site">Contact Support</a></li>
      </ul>
    </div>

  </div>
</footer>

<!-- ── Interactive Support Helpdesk Modal ──────────────────────────── -->
<div class="support-modal-backdrop" id="reuniteSupportModal" role="dialog" aria-modal="true" aria-labelledby="supportModalHeading">
  <div class="support-modal-card">
    <button type="button" class="support-modal-close" onclick="closeReuniteSupportModal()" aria-label="Close modal">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
    </button>

    <div class="support-modal-header">
      <h3 id="supportModalHeading">Reunite Support &amp; Help Desk</h3>
      <p>How can our team or AI Assistant assist you today?</p>
    </div>

    <div class="support-quick-grid">
      <!-- Option 1: AI Copilot -->
      <a href="search.php?action=voice" class="support-tile">
        <div class="support-tile-icon">🎙️</div>
        <div class="support-tile-title">Voice AI Copilot</div>
        <div class="support-tile-desc">Speak naturally to find lost belongings or ask questions.</div>
      </a>

      <!-- Option 2: Report Missing -->
      <a href="report-lost-item.php" class="support-tile">
        <div class="support-tile-icon">🚨</div>
        <div class="support-tile-title">Urgent Report Assistance</div>
        <div class="support-tile-desc">File an expedited listing with AI visual matching.</div>
      </a>

      <!-- Option 3: Direct Email Ticket -->
      <a href="mailto:support@reunite.site?subject=Support%20Request%20-%20Reunite" class="support-tile">
        <div class="support-tile-icon">✉️</div>
        <div class="support-tile-title">Submit Support Ticket</div>
        <div class="support-tile-desc">Send detailed query directly to our support engineers.</div>
      </a>

      <!-- Option 4: Call Helpline -->
      <a href="tel:+18007386483" class="support-tile">
        <div class="support-tile-icon">📞</div>
        <div class="support-tile-title">Direct Helpline</div>
        <div class="support-tile-desc">Call +1 (800) 738-6483 for immediate 24/7 staff help.</div>
      </a>
    </div>

    <!-- Direct Contact Banner inside modal -->
    <div class="support-direct-box">
      <div class="support-direct-info">
        <strong>Need immediate verification assistance?</strong>
        <span>Campus Lost Hub is active &bull; Mon-Sun 24/7</span>
      </div>
      <a href="mailto:support@reunite.site" class="btn-footer-support" style="width: auto; padding: 8px 16px; font-size: 0.82rem;">Email Us</a>
    </div>
  </div>
</div>

<script>
  function openReuniteSupportModal() {
    const modal = document.getElementById('reuniteSupportModal');
    if (modal) modal.classList.add('open');
  }

  function closeReuniteSupportModal() {
    const modal = document.getElementById('reuniteSupportModal');
    if (modal) modal.classList.remove('open');
  }

  // Close modal when clicking on backdrop
  document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('reuniteSupportModal');
    if (modal) {
      modal.addEventListener('click', function(e) {
        if (e.target === modal) {
          closeReuniteSupportModal();
        }
      });
    }
  });
</script>
