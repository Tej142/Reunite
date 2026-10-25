<!-- =========================================================
     REUNITE — FOUNDER APPROVAL MODAL (Claim Lifecycle v2)
     Styled with Native Reunite Design Tokens (Fraunces, Inter,
     --surface, --accent, --border, --ink, --muted)
     Full Light / Dark Mode & Responsive (360px - 1920px)
     ========================================================= -->
<style>
/* ── Overlay Backdrop ─────────────────────────────────────── */
#founderApprovalOverlay {
  display: none;
  position: fixed;
  inset: 0;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  width: 100vw;
  height: 100vh;
  z-index: 99999;
  background: rgba(15, 12, 10, 0.78);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  padding: 1.25rem;
  overflow-y: auto;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  transition: opacity 0.25s ease-out;
}

#founderApprovalOverlay.flex:not(.hidden),
#founderApprovalOverlay[aria-hidden="false"]:not(.hidden) {
  display: flex !important;
}

#founderApprovalOverlay.hidden,
#founderApprovalOverlay .hidden {
  display: none !important;
}

/* ── Modal Dialog Panel ───────────────────────────────────── */
#founderApprovalModal {
  position: relative;
  width: 100%;
  max-width: 580px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #E7E3DC);
  border-radius: var(--radius-xl, 1.25rem);
  box-shadow: 0 24px 64px -12px rgba(28, 25, 23, 0.32);
  color: var(--ink, #1C1917);
  margin: auto;
  max-height: 90vh;
  overflow-y: auto;
  overscroll-behavior: contain;
  box-sizing: border-box;
  padding: 0 !important;
  transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease;
}

[data-theme="dark"] #founderApprovalModal {
  background: var(--surface, #1C1814) !important;
  border-color: var(--border, #2C2520) !important;
  color: var(--ink, #F7F3EE) !important;
  box-shadow: 0 24px 64px -12px rgba(0, 0, 0, 0.75) !important;
}

/* ── Modal Close Button ───────────────────────────────────── */
.founder-close-btn {
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

.founder-close-btn:hover {
  background: var(--border, #E7E3DC);
  color: var(--ink, #1C1917);
  transform: rotate(90deg);
}

[data-theme="dark"] .founder-close-btn {
  background: var(--surface-alt, #231E1A);
  border-color: var(--border, #2C2520);
  color: var(--muted-2, #756B63);
}

[data-theme="dark"] .founder-close-btn:hover {
  color: #ffffff;
  background: rgba(255, 255, 255, 0.12);
}

/* ── Modal Content Padding & Elements ─────────────────────── */
.founder-modal-body {
  padding: clamp(1.1rem, 0.85rem + 0.8vw, 1.6rem);
  box-sizing: border-box;
}

.founder-icon-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: rgba(196, 98, 45, 0.12);
  color: var(--accent, #C4622D);
  font-size: 1.25rem;
  margin-bottom: 0.75rem;
}

[data-theme="dark"] .founder-icon-badge {
  background: rgba(224, 112, 64, 0.18);
  color: #FF9A6B;
}

.founder-title {
  font-family: 'Fraunces', Georgia, serif;
  font-size: clamp(1.25rem, 1.1rem + 0.5vw, 1.55rem);
  font-weight: 700;
  color: var(--ink, #1C1917);
  margin: 0 0 0.35rem 0;
  line-height: 1.25;
}

[data-theme="dark"] .founder-title {
  color: var(--ink, #F7F3EE);
}

.founder-subtitle {
  font-size: 0.85rem;
  color: var(--muted, #78716C);
  margin: 0 0 1rem 0;
  line-height: 1.45;
}

[data-theme="dark"] .founder-subtitle {
  color: var(--muted, #A89D94);
}

/* ── Section Label ────────────────────────────────────────── */
.founder-section-label {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  font-size: 0.725rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--muted, #78716C);
  margin-bottom: 0.45rem;
}

[data-theme="dark"] .founder-section-label {
  color: var(--muted, #A89D94);
}

/* ── Proof Description Card ───────────────────────────────── */
.founder-proof-card {
  width: 100%;
  border-radius: 12px;
  background: var(--bg-alt, #F5F2EE);
  border: 1.5px solid var(--border, #E7E3DC);
  border-left: 4px solid var(--accent, #C4622D);
  padding: 0.85rem 1.05rem;
  font-size: 0.9rem;
  line-height: 1.55;
  color: var(--ink, #1C1917);
  white-space: pre-wrap;
  word-break: break-word;
  min-height: 60px;
  box-sizing: border-box;
}

[data-theme="dark"] .founder-proof-card {
  background: var(--surface-alt, #231E1A);
  border-color: var(--border, #2C2520);
  border-left-color: var(--accent, #E07040);
  color: var(--ink, #F7F3EE);
}

/* ── Proof Image Attachment Box ───────────────────────────── */
.founder-image-box {
  margin-top: 0.75rem;
  border-radius: 12px;
  border: 1.5px solid var(--border, #E7E3DC);
  background: var(--bg-alt, #F5F2EE);
  padding: 0.65rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

[data-theme="dark"] .founder-image-box {
  background: var(--surface-alt, #231E1A);
  border-color: var(--border, #2C2520);
}

.founder-image-box img {
  max-height: 180px;
  max-width: 100%;
  border-radius: 10px;
  object-fit: contain;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  cursor: zoom-in;
  transition: transform 0.2s ease;
}

.founder-image-box img:hover {
  transform: scale(1.02);
}

/* ── Action Buttons ───────────────────────────────────────── */
.founder-actions-row {
  display: flex;
  gap: 0.75rem;
  margin-top: 1.15rem;
  flex-wrap: wrap;
}

.btn-founder-reject {
  flex: 1;
  min-width: 130px;
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.65rem 1.15rem;
  border-radius: 12px;
  border: 1.5px solid rgba(239, 68, 68, 0.45);
  background: transparent;
  color: #DC2626;
  font-size: 0.9rem;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  transition: all 0.2s ease;
  box-sizing: border-box;
}

.btn-founder-reject:hover:not(:disabled) {
  background: rgba(239, 68, 68, 0.1);
  border-color: #EF4444;
}

[data-theme="dark"] .btn-founder-reject {
  color: #F87171;
  border-color: rgba(239, 68, 68, 0.5);
}

[data-theme="dark"] .btn-founder-reject:hover:not(:disabled) {
  background: rgba(239, 68, 68, 0.2);
}

.btn-founder-approve {
  flex: 1;
  min-width: 150px;
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.65rem 1.15rem;
  border-radius: 12px;
  border: none;
  background: var(--accent, #C4622D);
  color: #ffffff;
  font-size: 0.9rem;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(196, 98, 45, 0.35);
  transition: all 0.2s ease;
  box-sizing: border-box;
}

.btn-founder-approve:hover:not(:disabled) {
  filter: brightness(1.08);
  transform: translateY(-1px);
}

.btn-founder-approve:active:not(:disabled),
.btn-founder-reject:active:not(:disabled) {
  transform: scale(0.98);
}

.btn-founder-approve:disabled,
.btn-founder-reject:disabled {
  opacity: 0.55;
  cursor: not-allowed;
  pointer-events: none;
}

/* ── Return Logistics Chooser Cards ───────────────────────── */
.founder-logistics-box {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border-soft, #F0EDE8);
}

[data-theme="dark"] .founder-logistics-box {
  border-top-color: var(--border, #2C2520);
}

.founder-logistics-title {
  font-size: 0.925rem;
  font-weight: 700;
  color: var(--ink, #1C1917);
  margin: 0 0 0.85rem 0;
}

[data-theme="dark"] .founder-logistics-title {
  color: var(--ink, #F7F3EE);
}

.return-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 0.75rem;
  margin-bottom: 1.15rem;
}

.return-card-label {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: 1rem 1.15rem;
  border-radius: 14px;
  border: 2px solid var(--border, #E7E3DC);
  background: var(--bg-alt, #F5F2EE);
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  box-sizing: border-box;
}

.return-card-label:hover {
  border-color: var(--accent, #C4622D);
}

[data-theme="dark"] .return-card-label {
  background: var(--surface-alt, #231E1A);
  border-color: var(--border, #2C2520);
}

.return-card-radio {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}

.return-card-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--ink, #1C1917);
}

[data-theme="dark"] .return-card-header {
  color: var(--ink, #F7F3EE);
}

.return-card-desc {
  font-size: 0.775rem;
  line-height: 1.4;
  color: var(--muted, #78716C);
}

[data-theme="dark"] .return-card-desc {
  color: var(--muted, #A89D94);
}

.return-card-radio:checked + .return-card-inner {
  color: var(--accent, #C4622D);
}

.return-card-label:has(.return-card-radio:checked) {
  border-color: var(--accent, #C4622D) !important;
  background: rgba(196, 98, 45, 0.08) !important;
  box-shadow: 0 0 0 1px var(--accent, #C4622D);
}

[data-theme="dark"] .return-card-label:has(.return-card-radio:checked) {
  border-color: var(--accent, #E07040) !important;
  background: rgba(224, 112, 64, 0.14) !important;
  box-shadow: 0 0 0 1px var(--accent, #E07040);
}

/* ── In-Person Logistics Inputs ───────────────────────────── */
.meet-fields-card {
  padding: 1rem 1.15rem;
  border-radius: 14px;
  background: var(--bg-alt, #F5F2EE);
  border: 1.5px solid var(--border, #E7E3DC);
  margin-bottom: 1.15rem;
  box-sizing: border-box;
}

[data-theme="dark"] .meet-fields-card {
  background: var(--surface-alt, #231E1A);
  border-color: var(--border, #2C2520);
}

.meet-field-group {
  margin-bottom: 0.85rem;
}

.meet-field-group:last-child {
  margin-bottom: 0;
}

.meet-field-label {
  display: block;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--ink, #1C1917);
  margin-bottom: 0.35rem;
}

[data-theme="dark"] .meet-field-label {
  color: var(--ink, #F7F3EE);
}

.meet-field-input {
  width: 100%;
  min-height: 44px;
  padding: 0.65rem 0.95rem;
  font-size: 0.95rem;
  font-family: inherit;
  color: var(--ink, #1C1917);
  background: var(--surface, #ffffff);
  border: 1.5px solid var(--border, #E7E3DC);
  border-radius: 10px;
  outline: none;
  box-sizing: border-box;
  transition: border-color 0.2s, box-shadow 0.2s;
}

[data-theme="dark"] .meet-field-input {
  background: var(--bg, #12100E);
  border-color: var(--border, #2C2520);
  color: var(--ink, #F7F3EE);
}

.meet-field-input:focus {
  border-color: var(--accent, #C4622D);
  box-shadow: 0 0 0 3px rgba(196, 98, 45, 0.15);
}

/* ── Feedback Message Banner ──────────────────────────────── */
.founder-feedback-msg {
  padding: 0.85rem 1.15rem;
  border-radius: 12px;
  font-size: 0.85rem;
  line-height: 1.5;
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
  gap: 0.65rem;
  box-sizing: border-box;
}

.founder-feedback-msg.success {
  background: rgba(16, 185, 129, 0.12);
  border: 1.5px solid rgba(16, 185, 129, 0.4);
  color: #065F46;
}

[data-theme="dark"] .founder-feedback-msg.success {
  background: rgba(5, 150, 105, 0.2);
  border-color: rgba(52, 211, 153, 0.35);
  color: #6EE7B7;
}

.founder-feedback-msg.error {
  background: rgba(239, 68, 68, 0.1);
  border: 1.5px solid rgba(239, 68, 68, 0.35);
  color: #B91C1C;
}

[data-theme="dark"] .founder-feedback-msg.error {
  background: rgba(220, 38, 38, 0.2);
  border-color: rgba(248, 113, 113, 0.35);
  color: #FCA5A5;
}

/* ── CSS Micro Spinner ────────────────────────────────────── */
@keyframes founderSpin {
  to { transform: rotate(360deg); }
}

.founder-spin {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 2px solid currentColor;
  border-right-color: transparent;
  border-radius: 50%;
  animation: founderSpin 0.7s linear infinite;
  vertical-align: middle;
}

/* ── Mobile Layout (< 640px) ──────────────────────────────── */
@media (max-width: 640px) {
  #founderApprovalOverlay {
    padding: 0;
    align-items: flex-end;
  }
  #founderApprovalModal {
    max-width: 100%;
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
    max-height: 94vh;
    padding: 1.25rem 1rem env(safe-area-inset-bottom, 1rem) 1rem;
  }
  .founder-actions-row {
    flex-direction: column-reverse;
  }
  .btn-founder-reject,
  .btn-founder-approve {
    width: 100%;
  }
  .return-cards-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<div id="founderApprovalOverlay"
     class="hidden"
     aria-hidden="true">

    <div id="founderApprovalModal"
         role="dialog"
         aria-modal="true"
         aria-labelledby="founderApprovalTitle">

        <!-- Close Button -->
        <button id="closeFounderApprovalBtn"
                type="button"
                class="founder-close-btn"
                aria-label="Close founder approval modal">
            &times;
        </button>

        <div class="founder-modal-body">

            <!-- Icon Header -->
            <div class="founder-icon-badge">
                👀
            </div>

            <h2 id="founderApprovalTitle" class="founder-title">
                Review Ownership Proof
            </h2>

            <p class="founder-subtitle">
                Review the specific details and evidence provided by the student claiming to own this item before coordinating return.
            </p>

            <!-- PROOF DETAILS -->
            <div style="margin-bottom: 1.15rem;">
                <div class="founder-section-label">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <span>Claimant's Submitted Details</span>
                </div>

                <div id="displayProofText" class="founder-proof-card"></div>
            </div>

            <!-- OPTIONAL ATTACHED PROOF IMAGE -->
            <div id="proofImageContainer" class="hidden" style="margin-bottom: 1.15rem;">
                <div class="founder-section-label">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    <span>Attached Proof Photo</span>
                </div>

                <div class="founder-image-box">
                    <img id="displayProofImage"
                         src=""
                         alt="Ownership proof attachment"
                         title="Click to view full photo">
                </div>
            </div>

            <!-- SHARED FEEDBACK MESSAGE -->
            <div id="approvalMessage"
                 class="founder-feedback-msg hidden"
                 role="status"
                 aria-live="polite"></div>

            <!-- INITIAL ACTION BUTTONS -->
            <div id="initialActionButtons" class="founder-actions-row">
                <button id="btnRejectClaim"
                        type="button"
                        class="btn-founder-reject">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    <span>Reject Claim</span>
                </button>

                <button id="btnConfirmFraud" type="button" class="btn-founder-reject" title="Use only when intentional fraud is confirmed with a documented reason">Confirm Fraud</button>

                <button id="btnApproveClaim"
                        type="button"
                        class="btn-founder-approve">
                    <span>Accept &amp; Coordinate Handover &rarr;</span>
                </button>
            </div>

            <!-- HANDOVER LOGISTICS STEP -->
            <form id="handoverForm" class="hidden founder-logistics-box" novalidate>

                <h3 class="founder-logistics-title">
                    How will you return the item?
                </h3>

                <div class="return-cards-grid">
                    <label class="return-card-label">
                        <input type="radio"
                               name="return_type"
                               value="hand_on_hand"
                               class="return-card-radio"
                               required
                               checked>
                        <div class="return-card-inner">
                            <div class="return-card-header">
                                <span>🤝</span>
                                <span>Meet in Person</span>
                            </div>
                            <div class="return-card-desc">
                                Coordinate a safe, public meetup on campus (e.g. Library, Cafeteria).
                            </div>
                        </div>
                    </label>

                    <label class="return-card-label">
                        <input type="radio"
                               name="return_type"
                               value="admin_office"
                               class="return-card-radio">
                        <div class="return-card-inner">
                            <div class="return-card-header">
                                <span>🏢</span>
                                <span>Campus Admin Desk</span>
                            </div>
                            <div class="return-card-desc">
                                Drop off at Lost &amp; Found Admin Desk (Room 104) for owner pickup.
                            </div>
                        </div>
                    </label>
                </div>

                <!-- IN-PERSON MEETING FIELDS -->
                <div id="handOnHandDetails" class="meet-fields-card">
                    <div class="meet-field-group">
                        <label for="meetLocation" class="meet-field-label">
                            Meeting Location (Public Campus Area)
                        </label>
                        <input type="text"
                               name="meet_location"
                               id="meetLocation"
                               maxlength="255"
                               placeholder="e.g. Central Library Lobby, Ground Floor"
                               class="meet-field-input"
                               required>
                    </div>

                    <div class="meet-field-group">
                        <label for="meetTime" class="meet-field-label">
                            Meeting Date &amp; Time
                        </label>
                        <input type="datetime-local"
                               name="meet_time"
                               id="meetTime"
                               class="meet-field-input"
                               required>
                    </div>
                </div>

                <!-- ADMIN DESK NOTICE (shown when admin_office is chosen) -->
                <div id="adminDeskNotice" class="meet-fields-card hidden" style="border-left: 4px solid var(--accent, #C4622D);">
                    <div style="font-size: 0.85rem; line-height: 1.5; color: var(--ink);">
                        <strong>Campus Administrative Desk (Room 104)</strong><br>
                        Drop the item off during campus hours (9:00 AM - 5:00 PM). The student will be notified to collect it upon verifying their Student ID.
                    </div>
                </div>

                <!-- API CONTRACT FIELDS -->
                <input type="hidden" name="match_id" id="approvalMatchId">
                <input type="hidden" name="action" id="approvalAction" value="approve">
                <input type="hidden" name="csrf_token" id="founderCsrfToken" value="">

                <div class="founder-actions-row">
                    <button type="button"
                            id="btnCancelLogistics"
                            class="btn-founder-reject"
                            style="border-color: var(--border); color: var(--muted);">
                        &larr; Back
                    </button>

                    <button type="submit"
                            id="btnConfirmApproval"
                            class="btn-founder-approve">
                        Confirm Handover Plan
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<script>
(function() {
    const founderApprovalOverlay = document.getElementById('founderApprovalOverlay');
    const closeFounderApprovalBtn = document.getElementById('closeFounderApprovalBtn');

    const displayProofText = document.getElementById('displayProofText');
    const displayProofImage = document.getElementById('displayProofImage');
    const proofImageContainer = document.getElementById('proofImageContainer');

    const initialActionButtons = document.getElementById('initialActionButtons');
    const handoverForm = document.getElementById('handoverForm');
    const handOnHandDetails = document.getElementById('handOnHandDetails');
    const adminDeskNotice = document.getElementById('adminDeskNotice');

    const approvalMatchId = document.getElementById('approvalMatchId');
    const approvalAction = document.getElementById('approvalAction');
    const founderCsrfToken = document.getElementById('founderCsrfToken');
    const approvalMessage = document.getElementById('approvalMessage');

    const btnApproveClaim = document.getElementById('btnApproveClaim');
    const btnRejectClaim = document.getElementById('btnRejectClaim');
    const btnConfirmApproval = document.getElementById('btnConfirmApproval');
    const btnCancelLogistics = document.getElementById('btnCancelLogistics');

    const meetLocationInput = document.getElementById('meetLocation');
    const meetTimeInput = document.getElementById('meetTime');

    // 1. Strict CSRF Source
    const pageCsrfToken = <?= json_encode(
        $_SESSION['csrf_token'] ?? '',
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;

    // Minimum meeting date/time (at least 30 minutes in future)
    function setMinDateTime() {
        const now = new Date();
        now.setMinutes(now.getMinutes() + 30 - now.getTimezoneOffset());
        if (meetTimeInput) {
            meetTimeInput.min = now.toISOString().slice(0, 16);
            if (!meetTimeInput.value) {
                // Default to +2 hours
                const defaultDate = new Date();
                defaultDate.setHours(defaultDate.getHours() + 2 - defaultDate.getTimezoneOffset());
                meetTimeInput.value = defaultDate.toISOString().slice(0, 16);
            }
        }
    }

    // Enable/disable interactive controls during request
    function setFormLoadingState(isLoading) {
        if (btnApproveClaim) btnApproveClaim.disabled = isLoading;
        if (btnRejectClaim) btnRejectClaim.disabled = isLoading;
        if (btnConfirmApproval) btnConfirmApproval.disabled = isLoading;
        if (btnCancelLogistics) btnCancelLogistics.disabled = isLoading;
        if (closeFounderApprovalBtn) closeFounderApprovalBtn.disabled = isLoading;

        document.querySelectorAll('input[name="return_type"]').forEach(radio => {
            radio.disabled = isLoading;
        });

        if (meetLocationInput) meetLocationInput.disabled = isLoading;
        if (meetTimeInput) meetTimeInput.disabled = isLoading;

        if (isLoading) {
            if (btnConfirmApproval) {
                btnConfirmApproval.innerHTML = '<span class="founder-spin"></span> Processing...';
            }
            if (btnRejectClaim) {
                btnRejectClaim.innerHTML = '<span class="founder-spin"></span> Rejecting...';
            }
        } else {
            if (btnConfirmApproval) btnConfirmApproval.textContent = 'Confirm Handover Plan';
            if (btnRejectClaim) btnRejectClaim.textContent = 'Reject Claim';
        }
    }

    function showFeedbackMessage(message, isSuccess) {
        if (!approvalMessage) return;
        approvalMessage.className = isSuccess
            ? 'founder-feedback-msg success'
            : 'founder-feedback-msg error';

        if (isSuccess && window.Orbi && typeof window.Orbi.create === 'function') {
            approvalMessage.innerHTML = '';
            try {
                const orbiEl = window.Orbi.create({ state: 'happy', size: 36, label: 'Success' });
                if (orbiEl) {
                    approvalMessage.appendChild(orbiEl);
                    if (typeof window.Orbi.happy === 'function') {
                        window.Orbi.happy(orbiEl, 2500);
                    }
                }
            } catch (e) {
                console.warn('Orbi initialization bypassed', e);
            }
            const msgSpan = document.createElement('span');
            msgSpan.textContent = message || 'Operation completed successfully.';
            approvalMessage.appendChild(msgSpan);
        } else {
            approvalMessage.textContent = message || (
                isSuccess ? 'Operation completed successfully.' : 'An error occurred.'
            );
        }

        approvalMessage.classList.remove('hidden');
    }

    // Synchronize return method fields
    function syncReturnTypeFields() {
        const selected = document.querySelector('input[name="return_type"]:checked');
        const isInPerson = selected ? selected.value === 'hand_on_hand' : true;

        if (handOnHandDetails) handOnHandDetails.classList.toggle('hidden', !isInPerson);
        if (adminDeskNotice) adminDeskNotice.classList.toggle('hidden', isInPerson);

        if (meetLocationInput) {
            meetLocationInput.required = isInPerson;
            if (!isInPerson) meetLocationInput.value = '';
        }
        if (meetTimeInput) {
            meetTimeInput.required = isInPerson;
            if (!isInPerson) meetTimeInput.value = '';
        }
    }

    document.querySelectorAll('input[name="return_type"]').forEach(radio => {
        radio.addEventListener('change', syncReturnTypeFields);
    });

    // OPEN FOUNDER APPROVAL MODAL
    function openFounderApprovalModal(matchId, proofText, proofImagePath = null) {
        // Fallback reading from embedded JSON if available
        let resolvedMatchId = matchId;
        let resolvedProofText = proofText;
        let resolvedProofImage = proofImagePath;

        const embeddedScript = document.getElementById('founderPendingClaimData');
        if (embeddedScript) {
            try {
                const embeddedData = JSON.parse(embeddedScript.textContent);
                if (embeddedData) {
                    if (!resolvedMatchId && embeddedData.match_id) resolvedMatchId = embeddedData.match_id;
                    if (!resolvedProofText && embeddedData.claim_details) resolvedProofText = embeddedData.claim_details;
                    if (!resolvedProofImage && embeddedData.proof_image) resolvedProofImage = embeddedData.proof_image;
                }
            } catch (err) {
                console.warn('Error reading embedded claim JSON', err);
            }
        }

        const parsedMatchId = Number(resolvedMatchId);
        if (!Number.isSafeInteger(parsedMatchId) || parsedMatchId <= 0) {
            console.error('Invalid Match ID provided to founder approval modal:', resolvedMatchId);
            return;
        }

        if (handoverForm) handoverForm.reset();

        if (approvalMatchId) approvalMatchId.value = String(parsedMatchId);
        if (founderCsrfToken) founderCsrfToken.value = pageCsrfToken;
        if (approvalAction) approvalAction.value = 'approve';

        if (displayProofText) {
            displayProofText.textContent = resolvedProofText && resolvedProofText.trim().length > 0 
                ? resolvedProofText 
                : '(No additional text provided by claimant)';
        }

        const normalizedImagePath = typeof resolvedProofImage === 'string' ? resolvedProofImage.trim() : '';
        const isTrustedImagePath =
            (normalizedImagePath.startsWith('uploads/proofs/') ||
             normalizedImagePath.startsWith('media_vault/proofs/') ||
             normalizedImagePath.startsWith('../media_vault/proofs/')) &&
            !normalizedImagePath.includes('\\') &&
            !normalizedImagePath.startsWith('//');

        if (isTrustedImagePath && displayProofImage && proofImageContainer) {
            let displaySrc = normalizedImagePath;
            if (displaySrc.startsWith('media_vault/')) {
                displaySrc = '../' + displaySrc;
            }
            proofImageContainer.classList.remove('hidden');
            displayProofImage.src = displaySrc;
        } else if (proofImageContainer && displayProofImage) {
            proofImageContainer.classList.add('hidden');
            displayProofImage.removeAttribute('src');
        }

        setMinDateTime();
        setFormLoadingState(false);
        syncReturnTypeFields();

        if (initialActionButtons) initialActionButtons.classList.remove('hidden');
        if (handoverForm) handoverForm.classList.add('hidden');
        if (approvalMessage) approvalMessage.classList.add('hidden');

        if (founderApprovalOverlay) {
            founderApprovalOverlay.setAttribute('aria-hidden', 'false');
            founderApprovalOverlay.classList.remove('hidden');
            founderApprovalOverlay.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }
    }

    if (displayProofImage) {
        displayProofImage.addEventListener('error', () => {
            if (proofImageContainer) proofImageContainer.classList.add('hidden');
            displayProofImage.removeAttribute('src');
        });
    }

    function closeFounderModal() {
        if (closeFounderApprovalBtn && closeFounderApprovalBtn.disabled) return;
        if (founderApprovalOverlay) {
            founderApprovalOverlay.setAttribute('aria-hidden', 'true');
            founderApprovalOverlay.classList.add('hidden');
            founderApprovalOverlay.classList.remove('flex');
            document.body.style.overflow = '';
        }
    }

    if (closeFounderApprovalBtn) {
        closeFounderApprovalBtn.addEventListener('click', closeFounderModal);
    }

    if (founderApprovalOverlay) {
        founderApprovalOverlay.addEventListener('click', (e) => {
            if (e.target === founderApprovalOverlay) {
                closeFounderModal();
            }
        });
    }

    // Cancel Logistics -> Go Back to Proof
    if (btnCancelLogistics) {
        btnCancelLogistics.addEventListener('click', () => {
            if (handoverForm) handoverForm.classList.add('hidden');
            if (initialActionButtons) initialActionButtons.classList.remove('hidden');
            if (approvalMessage) approvalMessage.classList.add('hidden');
        });
    }

    // Routine proof rejection and confirmed fraud are separate actions.
    let confirmedFraudReason = '';
    const btnConfirmFraud = document.getElementById('btnConfirmFraud');
    if (btnConfirmFraud) btnConfirmFraud.addEventListener('click', () => {
        const reason = prompt('Document evidence supporting confirmed intentional fraud (at least 10 characters):');
        if (!reason || reason.trim().length < 10) { showFeedbackMessage('A reason of at least 10 characters is required.', false); return; }
        if (!confirm('Confirm intentional fraud based on this evidence? An audit record and possible account restriction will follow.')) return;
        confirmedFraudReason = reason.trim();
        if (btnRejectClaim) btnRejectClaim.click();
    });
    if (btnRejectClaim) {
        btnRejectClaim.addEventListener('click', async () => {
            if (!approvalMatchId.value) {
                showFeedbackMessage('Invalid match ID.', false);
                return;
            }

            const confirmed = confirm(
                confirmedFraudReason ? 'Confirm rejection as documented intentional fraud? This will apply the review policy.' : 'Decline this claim because the proof is insufficient or unclear? No fraud sanction will be applied. The claimant may clarify and retry if the match remains open.'
            );
            if (!confirmed) return;

            const formData = new FormData();
            formData.append('match_id', approvalMatchId.value);
            formData.append('action', 'reject');
            formData.append('csrf_token', pageCsrfToken);
            if (confirmedFraudReason) { formData.append('fraud_confirmed', '1'); formData.append('rejection_reason', confirmedFraudReason); }

            setFormLoadingState(true);
            if (approvalMessage) approvalMessage.classList.add('hidden');

            try {
                const response = await fetch('api/founder_approval.php', {
                    method: 'POST',
                    body: formData
                });

                let data = null;
                try {
                    data = await response.json();
                } catch (parseError) {}

                if (!response.ok || !data || !data.success) {
                    throw new Error(data?.message || 'Failed to reject claim.');
                }

                confirmedFraudReason = '';
                showFeedbackMessage(data.message || 'Claim rejected successfully.', true);

                setTimeout(() => {
                    location.reload();
                }, 1500);

            } catch (error) {
                showFeedbackMessage(error.message || 'A server error occurred.', false);
                setFormLoadingState(false);
            }
        });
    }

    // ACCEPT CLAIM -> REVEAL HANDOVER FORM
    if (btnApproveClaim) {
        btnApproveClaim.addEventListener('click', () => {
            if (initialActionButtons) initialActionButtons.classList.add('hidden');
            if (handoverForm) handoverForm.classList.remove('hidden');
            if (approvalMessage) approvalMessage.classList.add('hidden');
            setMinDateTime();
            syncReturnTypeFields();

            const modalBox = document.getElementById('founderApprovalModal');
            if (modalBox) {
                setTimeout(() => {
                    modalBox.scrollTo({ top: modalBox.scrollHeight, behavior: 'smooth' });
                }, 50);
            }
        });
    }

    // SUBMIT APPROVAL / HANDOVER DETAILS
    if (handoverForm) {
        handoverForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            setMinDateTime();

            if (!handoverForm.checkValidity()) {
                handoverForm.reportValidity();
                return;
            }

            const formData = new FormData(handoverForm);
            setFormLoadingState(true);
            if (approvalMessage) approvalMessage.classList.add('hidden');

            try {
                const response = await fetch('api/founder_approval.php', {
                    method: 'POST',
                    body: formData
                });

                let data = null;
                try {
                    data = await response.json();
                } catch (parseError) {}

                if (!response.ok || !data || !data.success) {
                    throw new Error(data?.message || 'Server error occurred during approval.');
                }

                showFeedbackMessage(data.message || 'Claim approved successfully!', true);

                if (window.ReuniteToast && typeof window.ReuniteToast.show === 'function') {
                    window.ReuniteToast.show({
                        title: 'Claim Approved!',
                        message: data.message || 'Handover details logged successfully.',
                        type: 'success',
                        orbiState: 'happy',
                        duration: 3500
                    });
                }

                setTimeout(() => {
                    location.reload();
                }, 1500);

            } catch (error) {
                showFeedbackMessage(error.message || 'An error occurred during approval.', false);
                setFormLoadingState(false);
            }
        });
    }

    window.openFounderApprovalModal = openFounderApprovalModal;
    window.closeFounderApprovalModal = closeFounderModal;
})();
</script>
