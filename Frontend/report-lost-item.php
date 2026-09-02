<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Report a lost item — Reunite</title>
  <link rel="stylesheet" href="css/variables.css?v=<?php echo time(); ?>" />
  <link rel="stylesheet" href="css/report-lost-item.css?v=<?php echo time(); ?>" />
  <link rel="stylesheet" href="css/reporting-modes.css?v=<?php echo time(); ?>" />
</head>
<body>

<?php include 'components/nav.php'; ?>

<main>
  <div class="header-row">
    <div>
      <span class="eyebrow">
        <svg width="8" height="8" viewBox="0 0 8 8" fill="none" aria-hidden="true"><circle cx="4" cy="4" r="4" fill="#C4622D"/></svg>
        Report a lost item
      </span>
      <h1 class="page-title serif">Tell us what<br/><em>you're missing.</em></h1>
      <p class="page-sub">Choose your preferred reporting method below. We'll match it against everything reported found, and notify you the moment something lines up.</p>
    </div>
  </div>

  <!-- Mode Selector -->
  <div class="mode-selector-wrapper">
    <div class="mode-selector-title">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 1v12M1 7h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      Select Reporting Method
    </div>
    <div class="mode-selector-grid">
      
      <!-- Option 1: Image + Description -->
      <div class="mode-card active" data-mode="mode1" id="tabMode1">
        <div class="mode-card-header">
          <div class="mode-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
          </div>
          <span class="mode-badge">Quick & Direct</span>
        </div>
        <div>
          <div class="mode-card-title">Image + Description</div>
          <div class="mode-card-desc">Upload photos & type details. AI auto-extracts tags & key attributes.</div>
        </div>
      </div>

      <!-- Option 2: Guided Choice -->
      <div class="mode-card" data-mode="mode2" id="tabMode2">
        <div class="mode-card-header">
          <div class="mode-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>
          <span class="mode-badge">Step-by-Step</span>
        </div>
        <div>
          <div class="mode-card-title">Guided Choice</div>
          <div class="mode-card-desc">Interactive visual wizard for quick category, color, and location selection.</div>
        </div>
      </div>

      <!-- Option 3: Talk to AI -->
      <div class="mode-card" data-mode="mode3" id="tabMode3">
        <div class="mode-card-header">
          <div class="mode-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          </div>
          <span class="mode-badge">Conversational AI</span>
        </div>
        <div>
          <div class="mode-card-title">Talk to AI</div>
          <div class="mode-card-desc">Chat naturally with Reunite AI Copilot. It drafts the report in real-time.</div>
        </div>
      </div>

    </div>
  </div>

  <!-- AI Status Banner -->
  <div class="ai-status-banner">
    <div class="ai-status-left">
      <div class="ai-pulse-dot"></div>
      <div class="ai-status-text" id="aiStatusText">
        ⚡ <strong>AI Vision & Text Parser:</strong> Upload photos & describe your item directly. AI will parse details and index tags automatically.
      </div>
    </div>
  </div>

  <!-- ───────────────────────────────────────────────────────────── -->
  <!-- MODE 1: IMAGE + DESCRIPTION CONTAINER -->
  <!-- ───────────────────────────────────────────────────────────── -->
  <div class="mode-container active" id="mode1Container">
    <form id="lostForm">
      <div class="field">
        <label for="title">Item title</label>
        <input type="text" id="title" placeholder="e.g. Brown bifold wallet" required />
      </div>

      <div class="field">
        <label for="description">Description <span class="opt">— be specific</span></label>
        <textarea id="description" placeholder="Brand, color, material, size, any scuffs or stickers, what was inside — the more detail, the easier it is to match." required></textarea>
        <p class="hint">Include anything a stranger could see and confirm at a glance. Skip anything only the true owner would know — that goes in the private section below.</p>
        
        <!-- AI Insights Box -->
        <div class="ai-insights-box" id="aiInsightsBox">
          <div class="ai-insights-header">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
            AI Auto-Extracted Tags & Attributes
          </div>
          <div class="ai-tags-list" id="aiTagsList">
            <!-- Dynamically populated tags -->
          </div>
        </div>
      </div>

      <div class="row-2">
        <div class="field">
          <label for="where">Where you lost it</label>
          <input type="text" id="where" placeholder="e.g. Washington Square Park" required />
        </div>
        <div class="field">
          <label for="when">When</label>
          <input type="text" id="when" placeholder="e.g. Tuesday afternoon" required />
        </div>
      </div>

      <div class="field">
        <label>Photo <span class="opt">— optional, if you have one of the item or a similar one</span></label>
        <div class="dropzone" id="dropzone">
          <input type="file" id="fileInput" accept="image/*" multiple />
          <div class="dz-icon">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
              <path d="M9 3v9M5 7l4-4 4 4" stroke="#C4622D" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M3 13v1a2 2 0 002 2h8a2 2 0 002-2v-1" stroke="#C4622D" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="dz-title">Drop a photo here, or <b style="color:#C4622D">browse</b></div>
          <div class="dz-sub">PNG or JPG, up to 10MB each</div>
        </div>
        <div class="preview-grid" id="previewGrid"></div>
      </div>

      <div class="form-footer">
        <p class="footer-note">By submitting, your item enters our active search — matched automatically against new found reports.</p>
        <button type="submit" class="btn-submit">Submit report</button>
      </div>
    </form>
  </div>

  <!-- ───────────────────────────────────────────────────────────── -->
  <!-- MODE 2: GUIDED CHOICE WIZARD CONTAINER -->
  <!-- ───────────────────────────────────────────────────────────── -->
  <div class="mode-container" id="mode2Container">
    <div class="guided-wizard-card">
      
      <!-- Step Progress Bar -->
      <div class="wizard-progress-bar">
        <div class="wizard-step-dot active"></div>
        <div class="wizard-step-dot"></div>
        <div class="wizard-step-dot"></div>
      </div>

      <!-- Step 1: Category Selection -->
      <div class="wizard-step-content" data-step="1">
        <div class="wizard-step-title">Step 1: What did you lose?</div>
        <div class="wizard-step-sub">Select the category that best describes your missing item.</div>
        
        <div class="option-tiles-grid">
          <div class="option-tile" data-value="electronics">
            <div class="option-tile-icon">📱</div>
            <div class="option-tile-label">Electronics</div>
          </div>
          <div class="option-tile" data-value="keys">
            <div class="option-tile-icon">🔑</div>
            <div class="option-tile-label">Keys</div>
          </div>
          <div class="option-tile" data-value="wallets">
            <div class="option-tile-icon">💼</div>
            <div class="option-tile-label">Wallets & Bags</div>
          </div>
          <div class="option-tile" data-value="clothing">
            <div class="option-tile-icon">🧥</div>
            <div class="option-tile-label">Clothing</div>
          </div>
          <div class="option-tile" data-value="documents">
            <div class="option-tile-icon">📄</div>
            <div class="option-tile-label">Documents</div>
          </div>
          <div class="option-tile" data-value="other">
            <div class="option-tile-icon">📦</div>
            <div class="option-tile-label">Other Item</div>
          </div>
        </div>
      </div>

      <!-- Step 2: Key Attributes -->
      <div class="wizard-step-content" data-step="2" style="display: none;">
        <div class="wizard-step-title">Step 2: Key visual traits</div>
        <div class="wizard-step-sub">Select color and condition to help AI match finder photos.</div>

        <label>Primary Color</label>
        <div class="pills-group" data-group="color">
          <span class="choice-pill">Black</span>
          <span class="choice-pill">Brown</span>
          <span class="choice-pill">Blue</span>
          <span class="choice-pill">Silver / Grey</span>
          <span class="choice-pill">Red / Pink</span>
          <span class="choice-pill">White / Beige</span>
          <span class="choice-pill">Gold / Yellow</span>
          <span class="choice-pill">Green</span>
        </div>

        <label style="margin-top: 1.5rem;">Physical Condition</label>
        <div class="pills-group" data-group="condition">
          <span class="choice-pill">Like New / Mint</span>
          <span class="choice-pill">Slight Scuffs</span>
          <span class="choice-pill">Worn / Used</span>
          <span class="choice-pill">Damaged / Cracked</span>
        </div>
      </div>

      <!-- Step 3: Location & Time -->
      <div class="wizard-step-content" data-step="3" style="display: none;">
        <div class="wizard-step-title">Step 3: Where and when was it lost?</div>
        <div class="wizard-step-sub">Provide location info so we can search nearby found reports.</div>

        <div class="field">
          <label for="guidedWhere">Lost Location</label>
          <input type="text" id="guidedWhere" placeholder="e.g. Washington Square Park, or Bus Route 7" />
        </div>

        <div class="field">
          <label for="guidedWhen">Approximate Time</label>
          <input type="text" id="guidedWhen" placeholder="e.g. Yesterday afternoon" />
        </div>
      </div>

      <!-- Wizard Navigation Footer -->
      <div class="wizard-nav-footer">
        <button type="button" class="btn-secondary" id="btnWizardPrev" style="visibility: hidden;">← Back</button>
        <button type="button" class="btn-submit" id="btnWizardNext">Next Step →</button>
      </div>

    </div>
  </div>

  <!-- ───────────────────────────────────────────────────────────── -->
  <!-- MODE 3: TALK TO AI CONTAINER -->
  <!-- ───────────────────────────────────────────────────────────── -->
  <div class="mode-container" id="mode3Container">
    <div class="talk-ai-layout">
      
      <!-- Left: Interactive Chat Card -->
      <div class="chat-card">
        <div class="chat-header">
          <div class="chat-avatar-title">
            <div class="chat-avatar">🤖</div>
            <div>
              <div class="chat-name">Reunite AI Assistant</div>
              <div class="chat-sub">Demo Mode &middot; No backend connected yet</div>
            </div>
          </div>
        </div>

        <div class="chat-messages" id="chatMessages">
          <div class="chat-bubble ai">
            Hello! 👋 I'm your AI Lost Item Assistant. <em>(Note: No AI backend server is connected yet — operating in interactive client mode).</em> Tell me what you're missing, where you might have lost it, and I'll draft your report automatically!
          </div>
        </div>

        <div class="chat-suggestions">
          <div class="prompt-chip">"I lost my brown wallet at Washington Square Park"</div>
          <div class="prompt-chip">"Lost my blue iPhone on the train yesterday"</div>
          <div class="prompt-chip">"Lost house keys with a yellow keychain on subway"</div>
        </div>

        <div class="chat-input-bar">
          <input type="text" id="chatInput" placeholder="Type or describe what you lost..." />
          <button type="button" class="btn-send" id="btnSendChat" aria-label="Send message">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          </button>
        </div>
      </div>

      <!-- Right: Live Report Draft Sidebar -->
      <div class="live-draft-card">
        <div>
          <div class="draft-header">
            <div class="draft-title">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              Live Report Draft
            </div>
            <span class="mode-badge">AI Syncing</span>
          </div>

          <div class="draft-fields-list">
            <div class="draft-item">
              <div class="draft-label">Item Title</div>
              <div class="draft-value empty" id="draftTitleVal">Not specified yet</div>
            </div>
            <div class="draft-item">
              <div class="draft-label">Category</div>
              <div class="draft-value empty" id="draftCatVal">Not specified yet</div>
            </div>
            <div class="draft-item">
              <div class="draft-label">Lost Location</div>
              <div class="draft-value empty" id="draftLocVal">Not specified yet</div>
            </div>
            <div class="draft-item">
              <div class="draft-label">Private Verification Secret</div>
              <div class="draft-value empty" id="draftVerifVal">Not specified yet</div>
            </div>
            <div class="draft-item">
              <div class="draft-label">Contact Email</div>
              <div class="draft-value empty" id="draftEmailVal">Not specified yet</div>
            </div>
          </div>
        </div>

        <div class="draft-footer">
          <button type="button" class="btn-draft-submit" id="btnDraftSubmit" disabled>Submit Report</button>
        </div>
      </div>

    </div>
  </div>

  <!-- ───────────────────────────────────────────────────────────── -->
  <!-- SUCCESS STATE -->
  <!-- ───────────────────────────────────────────────────────────── -->
  <div class="success" id="successState">
    <div class="success-icon">
      <svg width="22" height="22" viewBox="0 0 22 22" fill="none" aria-hidden="true">
        <path d="M4 11l5 5 9-10" stroke="#065F46" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
    <h2 class="serif">Report submitted</h2>
    <p>We're searching active found items now. You'll get an email the moment something matches.</p>
    <div class="id" id="reportId"></div>
    <div class="ai-dna-results" id="aiDnaResults" style="display:none;"></div>
  </div>
</main>

<footer>
  <div class="footer-inner">
    <span>&copy; 2024 Reunite &middot; A community-powered lost &amp; found platform.</span>
  </div>
</footer>

<script src="js/flask-ai-service.js"></script>
<script src="js/report-lost-item.js"></script>
<script src="js/reporting-modes.js"></script>

</body>
</html>
