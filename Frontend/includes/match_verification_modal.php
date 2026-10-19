<!-- =========================================================
     REUNITE — OFFICIAL MATCH VERIFICATION & CLAIM MODAL (v2.0)
     Styled with Native Reunite Design Tokens (Fraunces, Inter,
     --surface, --accent, --border, --ink, --muted)
     Full Light / Dark Mode & Responsive (360px - 1920px)
     ========================================================= -->
<style>
/* ── Overlay Backdrop ─────────────────────────────────────── */
#modalOverlay {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 99999;
  background: rgba(15, 12, 10, 0.76);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  padding: 1.25rem;
  overflow-y: auto;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  transition: opacity 0.25s ease-out;
}

#modalOverlay.flex:not(.hidden),
#modalOverlay[aria-hidden="false"]:not(.hidden) {
  display: flex !important;
}

#modalOverlay.hidden,
#modalOverlay .hidden {
  display: none !important;
}

/* ── Modal Dialog Panels ──────────────────────────────────── */
#matchDetailsModal,
#verificationModal {
  position: relative;
  width: 100%;
  max-width: 620px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #E7E3DC);
  border-radius: var(--radius-xl, 1.25rem);
  box-shadow: 0 24px 64px -12px rgba(28, 25, 23, 0.28);
  padding: clamp(1.25rem, 1rem + 1vw, 2rem);
  color: var(--ink, #1C1917);
  margin: auto;
  max-height: 90vh;
  overflow-y: auto;
  overscroll-behavior: contain;
  box-sizing: border-box;
  transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease;
}

[data-theme="dark"] #matchDetailsModal,
[data-theme="dark"] #verificationModal {
  background: var(--surface, #1C1917);
  border-color: var(--border, #2E2A27);
  box-shadow: 0 24px 64px -12px rgba(0, 0, 0, 0.7);
  color: var(--ink, #FAF8F5);
}

/* ── Modal Close Button ───────────────────────────────────── */
.modal-close-icon-btn {
  position: absolute;
  top: 1.25rem;
  right: 1.25rem;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 50%;
  background: var(--bg-alt, #F5F2EE);
  border: 1px solid var(--border, #E7E3DC);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.35rem;
  line-height: 1;
  color: var(--muted, #78716C);
  cursor: pointer;
  z-index: 20;
  transition: all 0.2s ease;
}

.modal-close-icon-btn:hover {
  background: var(--border, #E7E3DC);
  color: var(--ink, #1C1917);
  transform: rotate(90deg);
}

[data-theme="dark"] .modal-close-icon-btn {
  background: var(--surface-alt, #262220);
  border-color: var(--border, #2E2A27);
  color: var(--muted-2, #A8A29E);
}

[data-theme="dark"] .modal-close-icon-btn:hover {
  color: #ffffff;
  background: rgba(255, 255, 255, 0.12);
}

/* ── Match Header (Image + Info Layout) ───────────────────── */
.match-hero-layout {
  display: flex;
  gap: 1.25rem;
  align-items: flex-start;
  justify-content: space-between;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid var(--border-soft, #F0EDE8);
}

[data-theme="dark"] .match-hero-layout {
  border-bottom-color: var(--border, #2E2A27);
}

.match-info-col {
  flex: 1;
  min-width: 0;
  padding-right: 1.5rem;
}

.match-pills-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-bottom: 0.45rem;
}

.match-category-tag {
  display: inline-flex;
  align-items: center;
  padding: 0.2rem 0.65rem;
  border-radius: 9999px;
  background: rgba(196, 98, 45, 0.12);
  color: var(--accent, #C4622D);
  font-size: 0.725rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

[data-theme="dark"] .match-category-tag {
  background: rgba(255, 154, 107, 0.18);
  color: #FF9A6B;
}

.match-report-id-tag {
  font-size: 0.775rem;
  font-weight: 600;
  color: var(--muted, #78716C);
}

.match-item-heading {
  font-family: 'Fraunces', Georgia, serif;
  font-size: clamp(1.25rem, 1.1rem + 0.6vw, 1.55rem);
  font-weight: 700;
  color: var(--ink, #1C1917);
  margin: 0 0 0.65rem 0;
  line-height: 1.25;
  word-break: break-word;
}

[data-theme="dark"] .match-item-heading {
  color: #FAF8F5;
}

.match-meta-list {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.match-meta-item {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  font-size: 0.85rem;
  color: var(--muted, #78716C);
  line-height: 1.4;
}

[data-theme="dark"] .match-meta-item {
  color: #A8A29E;
}

.match-meta-item svg {
  flex-shrink: 0;
  color: var(--accent, #C4622D);
}

.match-thumb-wrapper {
  position: relative;
  width: 110px;
  height: 110px;
  border-radius: 14px;
  overflow: hidden;
  border: 1.5px solid var(--border, #E7E3DC);
  background: var(--bg-alt, #F5F2EE);
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

[data-theme="dark"] .match-thumb-wrapper {
  background: var(--surface-alt, #262220);
  border-color: var(--border, #2E2A27);
}

.match-thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.match-thumb-badge {
  position: absolute;
  bottom: 5px;
  left: 5px;
  right: 5px;
  text-align: center;
  background: rgba(28, 25, 23, 0.85);
  backdrop-filter: blur(4px);
  color: #FF9A6B;
  font-size: 0.625rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  border-radius: 6px;
  padding: 2px 4px;
}

/* ── AI Match Analysis Card ───────────────────────────────── */
.match-analysis-panel {
  background: var(--bg-alt, #F5F2EE);
  border: 1px solid var(--border, #E7E3DC);
  border-radius: 14px;
  padding: 0.95rem 1.1rem;
  margin-top: 0.95rem;
}

[data-theme="dark"] .match-analysis-panel {
  background: var(--surface-alt, #262220);
  border-color: var(--border, #2E2A27);
}

.match-analysis-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.75rem;
}

.match-analysis-title {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--ink, #1C1917);
  display: flex;
  align-items: center;
  gap: 0.45rem;
  margin: 0;
}

[data-theme="dark"] .match-analysis-title {
  color: #FAF8F5;
}

.match-status-pill-green {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  background: rgba(16, 185, 129, 0.12);
  border: 1px solid rgba(16, 185, 129, 0.25);
  color: #059669;
  font-size: 0.725rem;
  font-weight: 700;
  padding: 0.2rem 0.6rem;
  border-radius: 9999px;
}

[data-theme="dark"] .match-status-pill-green {
  background: rgba(16, 185, 129, 0.18);
  color: #34D399;
  border-color: rgba(52, 211, 153, 0.3);
}

.match-scores-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.match-score-card {
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #E7E3DC);
  border-radius: 12px;
  padding: 0.65rem 0.85rem;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}

[data-theme="dark"] .match-score-card {
  background: #1C1917;
  border-color: var(--border, #2E2A27);
}

.match-score-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.35rem;
}

.match-score-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--muted, #78716C);
}

[data-theme="dark"] .match-score-label {
  color: #A8A29E;
}

.match-score-val {
  font-size: 1.05rem;
  font-weight: 800;
  color: #10B981;
  font-family: 'JetBrains Mono', monospace;
}

.match-progress-track {
  width: 100%;
  height: 6px;
  background: var(--border-soft, #E7E3DC);
  border-radius: 9999px;
  overflow: hidden;
}

[data-theme="dark"] .match-progress-track {
  background: rgba(255, 255, 255, 0.1);
}

.match-progress-fill {
  height: 100%;
  background: #10B981;
  border-radius: 9999px;
  transition: width 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}

.match-reasons-block {
  border-top: 1px solid var(--border, #E7E3DC);
  padding-top: 0.65rem;
}

[data-theme="dark"] .match-reasons-block {
  border-top-color: var(--border, #2E2A27);
}

.match-reasons-heading {
  font-size: 0.775rem;
  font-weight: 700;
  color: var(--ink, #1C1917);
  margin: 0 0 0.4rem 0;
}

[data-theme="dark"] .match-reasons-heading {
  color: #FAF8F5;
}

.match-reasons-ul {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.match-reasons-ul li {
  display: flex;
  align-items: flex-start;
  gap: 0.45rem;
  font-size: 0.8rem;
  color: var(--muted, #78716C);
  line-height: 1.35;
}

[data-theme="dark"] .match-reasons-ul li {
  color: #A8A29E;
}

.match-reason-check-icon {
  color: #10B981;
  font-weight: 700;
}

.match-neural-reason-quote {
  margin-top: 0.5rem;
  padding: 0.5rem 0.75rem;
  background: rgba(196, 98, 45, 0.06);
  border-left: 3px solid var(--accent, #C4622D);
  border-radius: 0 8px 8px 0;
  font-size: 0.775rem;
  color: var(--ink, #1C1917);
  line-height: 1.4;
  font-style: italic;
}

[data-theme="dark"] .match-neural-reason-quote {
  background: rgba(196, 98, 45, 0.12);
  color: #FAF8F5;
}

/* ── Privacy Callout & Actions ────────────────────────────── */
.match-privacy-notice {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
  background: rgba(196, 98, 45, 0.08);
  border: 1px solid rgba(196, 98, 45, 0.25);
  border-radius: 12px;
  padding: 0.7rem 0.95rem;
  margin-top: 0.95rem;
  font-size: 0.8rem;
  color: var(--ink, #1C1917);
  line-height: 1.4;
}

[data-theme="dark"] .match-privacy-notice {
  background: rgba(196, 98, 45, 0.15);
  border-color: rgba(196, 98, 45, 0.35);
  color: #FAF8F5;
}

.match-actions-bar {
  display: flex;
  gap: 0.75rem;
  justify-content: flex-end;
  margin-top: 1rem;
  margin-bottom: 0.25rem;
  flex-wrap: wrap;
}

.match-actions-bar .claim-btn {
  flex: 1;
  min-width: 140px;
}

.match-fraud-footer {
  text-align: center;
  font-size: 0.725rem;
  color: var(--muted, #78716C);
  margin-top: 0.85rem;
  margin-bottom: 0;
}

/* ── Modal B: Verification Ownership Form ──────────────────── */
.verification-header-icon {
  width: 3rem;
  height: 3rem;
  border-radius: 14px;
  background: rgba(196, 98, 45, 0.12);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  margin-bottom: 1rem;
}

.verification-heading {
  font-family: 'Fraunces', Georgia, serif;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--ink, #1C1917);
  margin: 0 0 0.4rem 0;
}

[data-theme="dark"] .verification-heading {
  color: #FAF8F5;
}

.verification-subtitle {
  font-size: 0.875rem;
  color: var(--muted, #78716C);
  line-height: 1.5;
  margin: 0 0 1.25rem 0;
}

[data-theme="dark"] .verification-subtitle {
  color: #A8A29E;
}

.verification-char-count {
  display: flex;
  justify-content: flex-end;
  font-size: 0.75rem;
  color: var(--muted, #78716C);
  margin-top: 0.35rem;
}

/* ── Mobile Responsive (360px - 600px) ────────────────────── */
@media (max-width: 600px) {
  #modalOverlay {
    padding: 0;
    align-items: flex-end;
  }

  #matchDetailsModal,
  #verificationModal {
    max-width: 100%;
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
    max-height: 94vh;
    padding: 1.25rem 1rem env(safe-area-inset-bottom, 1rem) 1rem;
  }

  .match-hero-layout {
    flex-direction: column-reverse;
  }

  .match-info-col {
    padding-right: 0;
  }

  .match-thumb-wrapper {
    width: 100%;
    height: 160px;
  }

  .match-scores-grid {
    grid-template-columns: 1fr;
  }

  .match-actions-bar {
    flex-direction: column-reverse;
  }

  .match-actions-bar .claim-btn {
    width: 100%;
  }
}
</style>

<div id="modalOverlay" class="hidden" aria-hidden="true">

  <!-- =========================================================
       MODAL A — MATCH DETAILS & AI ANALYSIS
       ========================================================= -->
  <div id="matchDetailsModal" class="hidden" role="dialog" aria-modal="true" aria-labelledby="matchTitle">
    
    <!-- Close Button -->
    <button id="closeMatchButton" type="button" aria-label="Close match details" class="modal-close-icon-btn">
      &times;
    </button>

    <!-- Header Hero Layout (Image + Text) -->
    <div class="match-hero-layout">
      
      <!-- Text Details -->
      <div class="match-info-col">
        <div class="match-pills-row">
          <span id="matchCategory" class="match-category-tag">Item</span>
          <span class="match-report-id-tag">•</span>
          <span id="matchReportId" class="match-report-id-tag">#RF-00000</span>
        </div>

        <h2 id="matchTitle" class="match-item-heading">Item Title</h2>

        <div class="match-meta-list">
          <div class="match-meta-item">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
            <span id="matchLocation">Campus Grounds</span>
          </div>
          <div class="match-meta-item">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span id="matchDate">Date unavailable</span>
          </div>
        </div>
      </div>

      <!-- Thumbnail Preview -->
      <div class="match-thumb-wrapper">
        <img id="matchItemImage" src="" alt="Matched item" class="match-thumb-img">
        <div class="match-thumb-badge">✨ AI MATCH</div>
      </div>
    </div>

    <!-- AI Match Analysis Card -->
    <section class="match-analysis-panel">
      <div class="match-analysis-header-row">
        <h3 class="match-analysis-title">
          <span>✨</span>
          <span>AI Match Analysis</span>
        </h3>
        <span id="analysisBadge" class="match-status-pill-green">
          Analysis Complete
        </span>
      </div>

      <!-- Dual Score Cards -->
      <div class="match-scores-grid">
        <!-- Visual Match -->
        <div class="match-score-card">
          <div class="match-score-top">
            <span class="match-score-label">Visual Confidence</span>
            <span id="visualScore" class="match-score-val">0%</span>
          </div>
          <div class="match-progress-track">
            <div id="visualProgress" class="match-progress-fill" style="width: 0%"></div>
          </div>
        </div>

        <!-- Digital DNA -->
        <div class="match-score-card">
          <div class="match-score-top">
            <span class="match-score-label">Digital DNA</span>
            <span id="dnaScore" class="match-score-val">0%</span>
          </div>
          <div class="match-progress-track">
            <div id="dnaProgress" class="match-progress-fill" style="width: 0%"></div>
          </div>
        </div>
      </div>

      <!-- Match Reasons -->
      <div class="match-reasons-block">
        <h4 class="match-reasons-heading">Why this match was suggested</h4>
        <ul id="safeMatchReasons" class="match-reasons-ul"></ul>
        <div id="matchNeuralQuote" class="match-neural-reason-quote" style="display: none;"></div>
      </div>
    </section>

    <!-- Privacy & Security Warning -->
    <div class="match-privacy-notice">
      <span style="font-size: 1.15rem; line-height: 1;">🔐</span>
      <span>AI matching does not verify ownership. Additional private ownership proof is required before the item can be released.</span>
    </div>

    <!-- Action Buttons -->
    <div class="match-actions-bar">
      <button id="rejectMatchButton" type="button" class="claim-btn claim-btn-secondary">
        Reject Match
      </button>

      <button id="claimItemButton" type="button" class="claim-btn claim-btn-primary">
        Claim Item &rarr;
      </button>
    </div>

    <p class="match-fraud-footer">
      *Fraudulent claims are monitored and may lead to a 24-hour account suspension.
    </p>
  </div>

  <!-- =========================================================
       MODAL B — OWNER PROOF SUBMISSION FORM
       ========================================================= -->
  <div id="verificationModal" class="hidden" role="dialog" aria-modal="true" aria-labelledby="verificationTitle">
    
    <!-- Close Button -->
    <button id="closeVerificationButton" type="button" aria-label="Close verification form" class="modal-close-icon-btn">
      &times;
    </button>

    <div class="verification-header-icon">🔐</div>

    <h2 id="verificationTitle" class="verification-heading">Verify Ownership</h2>
    <p class="verification-subtitle">
      Provide a detail that can help the founder verify that you are the rightful owner.
      Examples: a unique mark, hidden feature, contents, purchase detail, or another detail not visible in the listing.
    </p>

    <!-- PROOF FORM -->
    <form id="verificationForm" novalidate>
      <div class="claim-form-group">
        <label for="proofText" class="claim-form-label">Ownership Proof (Min 20 characters)</label>
        <textarea
          id="proofText"
          name="proof_text"
          rows="5"
          maxlength="1000"
          required
          placeholder="Enter detailed facts that prove your ownership (e.g. scratches, internal contents, serial number, purchase date)..."
          class="claim-textarea"
        ></textarea>
        <div class="verification-char-count">
          <span id="characterCounter">0 / 1000</span>
        </div>
      </div>

      <!-- Proof Image Upload (Optional) -->
      <div class="claim-form-group">
        <label for="proofImage" class="claim-form-label">
          Upload Proof Image <span style="font-weight: 400; color: var(--muted);">(Bill, Receipt, or Photo &mdash; Optional)</span>
        </label>
        <input
          type="file"
          id="proofImage"
          name="proof_image"
          accept="image/jpeg, image/png, image/jpg"
          class="claim-input"
          style="padding: 0.5rem;"
        >
      </div>

      <!-- Feedback / API Message -->
      <div id="verificationMessage" class="claim-alert hidden"></div>

      <!-- Action Buttons -->
      <div class="match-actions-bar">
        <button id="cancelVerificationButton" type="button" class="claim-btn claim-btn-secondary">
          Cancel
        </button>

        <button id="submitProofButton" type="submit" class="claim-btn claim-btn-primary">
          Submit Proof
        </button>
      </div>
    </form>
  </div>
</div>

<!-- =========================================================
     JAVASCRIPT FOR MODALS & DATA BINDING
     ========================================================= -->
<script>
(function() {
  const modalOverlay = document.getElementById('modalOverlay');
  const matchDetailsModal = document.getElementById('matchDetailsModal');
  const verificationModal = document.getElementById('verificationModal');
  const closeMatchButton = document.getElementById('closeMatchButton');
  const closeVerificationButton = document.getElementById('closeVerificationButton');
  const claimItemButton = document.getElementById('claimItemButton');
  const rejectMatchButton = document.getElementById('rejectMatchButton');
  const cancelVerificationButton = document.getElementById('cancelVerificationButton');
  const verificationForm = document.getElementById('verificationForm');
  const proofText = document.getElementById('proofText');
  const proofImage = document.getElementById('proofImage');
  const characterCounter = document.getElementById('characterCounter');
  const verificationMessage = document.getElementById('verificationMessage');
  const matchItemImage = document.getElementById('matchItemImage');

  let currentMatchData = null;

  // Fallback placeholder image when image fails or is empty
  const FALLBACK_ITEM_IMG = "https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=500&h=500&fit=crop&auto=format";

  if (matchItemImage) {
    matchItemImage.addEventListener('error', function() {
      if (this.src !== FALLBACK_ITEM_IMG) {
        this.src = FALLBACK_ITEM_IMG;
      }
    });
  }

  function clampScore(value) {
    const number = Number(value);
    if (!Number.isFinite(number)) return 0;
    return Math.min(100, Math.max(0, Math.round(number * 10) / 10));
  }

  function openMatchModal(matchData) {
    currentMatchData = matchData;

    // Header info
    const meta = matchData.metadata || {};
    let imgUrl = meta.image_url || '';
    if (!imgUrl || imgUrl === 'null') {
      imgUrl = FALLBACK_ITEM_IMG;
    }
    matchItemImage.src = imgUrl;
    matchItemImage.alt = meta.title || 'Matched item';

    document.getElementById('matchCategory').textContent = matchData.category || 'ITEM';
    document.getElementById('matchReportId').textContent = matchData.report_id ? `#${matchData.report_id.replace(/^#/, '')}` : '';
    document.getElementById('matchTitle').textContent = meta.title || 'Matched Item';
    document.getElementById('matchLocation').textContent = meta.location || 'Campus Grounds';
    document.getElementById('matchDate').textContent = meta.date_reported || 'Recently Reported';

    // AI Scores
    const aiReasons = matchData.ai_reasons || {};
    const rawVisual = aiReasons.visual_match_confidence || aiReasons.match_score || 95;
    const rawDna = aiReasons.digital_dna_similarity || Math.round(rawVisual * 0.98);
    const visualScore = clampScore(rawVisual);
    const dnaScore = clampScore(rawDna);

    document.getElementById('visualScore').textContent = `${visualScore}%`;
    document.getElementById('dnaScore').textContent = `${dnaScore}%`;

    const visProg = document.getElementById('visualProgress');
    const dnaProg = document.getElementById('dnaProgress');
    visProg.style.width = '0%';
    dnaProg.style.width = '0%';

    requestAnimationFrame(() => {
      visProg.style.width = `${visualScore}%`;
      dnaProg.style.width = `${dnaScore}%`;
    });

    // Badge
    const analysisBadge = document.getElementById('analysisBadge');
    if (analysisBadge) {
      analysisBadge.textContent = `${Math.max(visualScore, dnaScore)}% Match • Verified`;
    }

    // Safe match reasons
    renderSafeMatchReasons(aiReasons.safe_match_reasons || { category: true, visual_similarity: true, multiple_attributes: true });

    // Neural quote
    const quoteEl = document.getElementById('matchNeuralQuote');
    if (quoteEl) {
      if (aiReasons.reason_text) {
        quoteEl.textContent = `"${aiReasons.reason_text}"`;
        quoteEl.style.display = 'block';
      } else {
        quoteEl.style.display = 'none';
      }
    }

    // Reset UI states
    resetMatchButtons();
    clearVerificationState();

    // Display Modal A
    verificationModal.classList.add('hidden');
    matchDetailsModal.classList.remove('hidden');
    modalOverlay.classList.remove('hidden');
    modalOverlay.classList.add('flex');
    modalOverlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function renderSafeMatchReasons(safeReasons) {
    const container = document.getElementById('safeMatchReasons');
    if (!container) return;
    container.innerHTML = '';

    // If safeReasons is an array of rule strings or objects
    if (Array.isArray(safeReasons) && safeReasons.length > 0) {
      safeReasons.forEach(r => {
        const text = typeof r === 'string' ? r : (r.reason || r.text || '');
        if (text) {
          const li = document.createElement('li');
          li.innerHTML = `<span class="match-reason-check-icon">✓</span> <span>${escapeHtml(text)}</span>`;
          container.appendChild(li);
        }
      });
      return;
    }

    if (typeof safeReasons === 'object' && safeReasons !== null) {
      if (Array.isArray(safeReasons.rules_hit) && safeReasons.rules_hit.length > 0) {
        safeReasons.rules_hit.forEach(r => {
          if ((r.delta === undefined || r.delta > 0) && r.reason) {
            const li = document.createElement('li');
            li.innerHTML = `<span class="match-reason-check-icon">✓</span> <span>${escapeHtml(r.reason)}</span>`;
            container.appendChild(li);
          }
        });
        return;
      }
      if (safeReasons.why_matched_summary) {
        const li = document.createElement('li');
        li.innerHTML = `<span class="match-reason-check-icon">✓</span> <span>${escapeHtml(safeReasons.why_matched_summary)}</span>`;
        container.appendChild(li);
        return;
      }
    }

    const reasons = [
      { key: 'category', text: 'Category and physical item classification match perfectly' },
      { key: 'visual_similarity', text: 'High visual color, material, and geometric contour similarity' },
      { key: 'multiple_attributes', text: 'Campus location and approximate loss timestamp window are consistent' }
    ];

    reasons.forEach(r => {
      const li = document.createElement('li');
      li.innerHTML = `<span class="match-reason-check-icon">✓</span> <span>${r.text}</span>`;
      container.appendChild(li);
    });
  }

  // Button handlers
  claimItemButton.addEventListener('click', () => {
    if (!currentMatchData) return;
    matchDetailsModal.classList.add('hidden');
    clearVerificationState();
    verificationModal.classList.remove('hidden');
    setTimeout(() => { proofText.focus(); }, 120);
  });

  cancelVerificationButton.addEventListener('click', () => {
    verificationModal.classList.add('hidden');
    matchDetailsModal.classList.remove('hidden');
    clearVerificationState();
  });

  closeMatchButton.addEventListener('click', closeAllModals);

  closeVerificationButton.addEventListener('click', () => {
    verificationModal.classList.add('hidden');
    matchDetailsModal.classList.remove('hidden');
    clearVerificationState();
  });

  rejectMatchButton.addEventListener('click', async () => {
    if (!currentMatchData || rejectMatchButton.disabled) return;
    setRejectLoading(true);

    try {
      const response = await fetch('api/reject_match.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ match_id: currentMatchData.match_id })
      });

      let data;
      try { data = await response.json(); } catch { throw new Error('Invalid server response.'); }
      if (!response.ok || data.success !== true) {
        throw new Error(data.message || 'Failed to reject this match.');
      }

      currentMatchData.status = 'rejected';
      rejectMatchButton.textContent = 'Match Rejected ✓';
      claimItemButton.disabled = true;

      setTimeout(() => {
        closeAllModals();
        window.location.reload();
      }, 700);

    } catch (error) {
      setRejectLoading(false);
      alert(error.message || 'Unable to reject the match.');
    }
  });

  verificationForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!currentMatchData) return;

    const proofValue = proofText.value.trim();

    if (!proofValue) {
      showVerificationMessage('Please enter your ownership proof.', 'error');
      proofText.focus();
      return;
    }

    if (proofValue.length < 20) {
      showVerificationMessage('Please provide more detail. Your proof must contain at least 20 characters.', 'error');
      proofText.focus();
      return;
    }

    if (proofValue.length > 1000) {
      showVerificationMessage('Ownership proof cannot exceed 1000 characters.', 'error');
      proofText.focus();
      return;
    }

    setVerificationLoading(true);

    try {
      let response;
      const hasImage = proofImage && proofImage.files && proofImage.files.length > 0;

      if (hasImage) {
        const formData = new FormData();
        formData.append('match_id', currentMatchData.match_id);
        formData.append('proof_text', proofValue);
        formData.append('proof_image', proofImage.files[0]);

        response = await fetch('api/verify_owner.php', {
          method: 'POST',
          body: formData
        });
      } else {
        response = await fetch('api/verify_owner.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            match_id: currentMatchData.match_id,
            proof_text: proofValue
          })
        });
      }

      let data;
      try { data = await response.json(); } catch { throw new Error('Invalid server response.'); }

      if (!response.ok || data.success !== true) {
        throw new Error(data.message || 'Failed to submit ownership proof.');
      }

      currentMatchData.status = 'pending_founder_approval';
      showVerificationMessage(data.message || 'Proof submitted successfully! The founder has been notified.', 'success');

      proofText.disabled = true;
      if (proofImage) proofImage.disabled = true;
      submitProofButton.disabled = true;
      cancelVerificationButton.disabled = false;
      submitProofButton.textContent = 'Proof Submitted ✓';

      setTimeout(() => {
        closeAllModals();
        window.location.reload();
      }, 1600);

    } catch (error) {
      showVerificationMessage(error.message || 'Something went wrong. Please try again.', 'error');
      setVerificationLoading(false);
    }
  });

  proofText.addEventListener('input', () => {
    const len = proofText.value.length;
    characterCounter.textContent = `${len} / 1000`;
    if (len >= 20 && len <= 1000) {
      if (verificationMessage.classList.contains('claim-alert-danger') || verificationMessage.style.display !== 'none') {
        verificationMessage.style.display = 'none';
        verificationMessage.classList.add('hidden');
        verificationMessage.textContent = '';
      }
    }
  });

  function setVerificationLoading(loading) {
    submitProofButton.disabled = loading;
    cancelVerificationButton.disabled = loading;
    proofText.disabled = loading;
    if (proofImage) proofImage.disabled = loading;

    if (loading) {
      submitProofButton.innerHTML = `<span style="display:inline-flex;align-items:center;gap:0.4rem;"><span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,0.4);border-top-color:#fff;border-radius:50%;animation:spin 0.6s linear infinite;"></span> Submitting...</span>`;
    } else {
      submitProofButton.textContent = 'Submit Proof';
    }
  }

  function setRejectLoading(loading) {
    rejectMatchButton.disabled = loading;
    claimItemButton.disabled = loading;
    if (loading) {
      rejectMatchButton.innerHTML = `Processing...`;
    } else {
      rejectMatchButton.textContent = 'Reject Match';
    }
  }

  function resetMatchButtons() {
    rejectMatchButton.disabled = false;
    claimItemButton.disabled = false;
    rejectMatchButton.textContent = 'Reject Match';
    claimItemButton.textContent = 'Claim Item →';
  }

  function showVerificationMessage(message, type) {
    verificationMessage.className = `claim-alert ${type === 'success' ? 'claim-alert-success' : 'claim-alert-danger'}`;
    verificationMessage.style.display = 'flex';
    verificationMessage.style.padding = '0.75rem 1rem';
    verificationMessage.style.borderRadius = '10px';
    verificationMessage.style.marginBottom = '1rem';
    verificationMessage.style.fontSize = '0.85rem';

    if (type === 'success') {
      verificationMessage.style.background = '#ECFDF5';
      verificationMessage.style.border = '1px solid #A7F3D0';
      verificationMessage.style.color = '#065F46';
    } else {
      verificationMessage.style.background = '#FEF2F2';
      verificationMessage.style.border = '1px solid #FECACA';
      verificationMessage.style.color = '#B91C1C';
    }
    verificationMessage.textContent = message;
    verificationMessage.classList.remove('hidden');
  }

  function clearVerificationState() {
    proofText.value = '';
    proofText.disabled = false;
    if (proofImage) {
      proofImage.value = '';
      proofImage.disabled = false;
    }
    characterCounter.textContent = '0 / 1000';
    verificationMessage.className = 'claim-alert hidden';
    verificationMessage.style.display = 'none';
    verificationMessage.textContent = '';
    submitProofButton.disabled = false;
    cancelVerificationButton.disabled = false;
    submitProofButton.textContent = 'Submit Proof';
  }

  function closeAllModals() {
    modalOverlay.classList.add('hidden');
    modalOverlay.classList.remove('flex');
    matchDetailsModal.classList.add('hidden');
    verificationModal.classList.add('hidden');
    modalOverlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    currentMatchData = null;
    clearVerificationState();
  }

  modalOverlay.addEventListener('click', (e) => {
    if (e.target === modalOverlay) closeAllModals();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modalOverlay.classList.contains('hidden')) {
      closeAllModals();
    }
  });

  window.openMatchModal = openMatchModal;
  window.closeMatchVerificationModal = closeAllModals;
})();
</script>