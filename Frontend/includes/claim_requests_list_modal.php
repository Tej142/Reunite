<?php
/**
 * REUNITE — CLAIM REQUESTS LIST MODAL
 * Dedicated interface for found-item reporters to review multiple competing or incoming claims.
 * Allows comparing claimant proofs, inspecting AI match confidence, approving the rightful owner,
 * or coordinating physical handovers with OTP verification.
 */
?>
<style>
/* ── Overlay Backdrop ─────────────────────────────────────── */
#claimRequestsListOverlay {
  display: none;
  position: fixed;
  inset: 0;
  width: 100vw;
  height: 100vh;
  z-index: 99998;
  background: rgba(15, 12, 10, 0.82);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  padding: 1.25rem;
  overflow-y: auto;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  transition: opacity 0.25s ease-out;
}

#claimRequestsListOverlay.active,
#claimRequestsListOverlay.flex {
  display: flex !important;
}

/* ── Modal Dialog Window ─────────────────────────────────── */
#claimRequestsListModal {
  position: relative;
  width: 100%;
  max-width: 740px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #E7E3DC);
  border-radius: var(--radius-xl, 1.25rem);
  box-shadow: 0 24px 64px -12px rgba(28, 25, 23, 0.32);
  color: var(--ink, #1C1917);
  margin: auto;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-sizing: border-box;
  transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease;
}

[data-theme="dark"] #claimRequestsListModal {
  background: var(--surface, #1C1814) !important;
  border-color: var(--border, #2C2520) !important;
  color: var(--ink, #F7F3EE) !important;
  box-shadow: 0 24px 64px -12px rgba(0, 0, 0, 0.75) !important;
}

/* ── Modal Header ────────────────────────────────────────── */
.claim-list-header {
  padding: 1.5rem 1.75rem 1.25rem;
  border-bottom: 1px solid var(--border, #E7E3DC);
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  background: var(--surface, #ffffff);
}

[data-theme="dark"] .claim-list-header {
  background: var(--surface, #1C1814);
  border-color: var(--border, #2C2520);
}

.claim-list-title-wrap {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.claim-list-title-row {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  flex-wrap: wrap;
}

.claim-list-title {
  font-family: var(--font-display, 'Fraunces', serif);
  font-size: 1.35rem;
  font-weight: 700;
  color: var(--ink, #1C1917);
  margin: 0;
  line-height: 1.25;
}

[data-theme="dark"] .claim-list-title {
  color: var(--ink, #F7F3EE);
}

.claim-badge-count {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.25rem 0.65rem;
  border-radius: 999px;
  background: rgba(196, 98, 45, 0.12);
  color: var(--accent, #C4622D);
  border: 1px solid rgba(196, 98, 45, 0.3);
}

.claim-list-sub {
  font-size: 0.85rem;
  color: var(--muted, #78716C);
  margin: 0;
}

.claim-list-close-btn {
  background: var(--bg-alt, #F5F2EE);
  border: 1px solid var(--border, #E7E3DC);
  color: var(--ink, #1C1917);
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  transition: all 0.2s ease;
  flex-shrink: 0;
}

.claim-list-close-btn:hover {
  background: var(--surface-alt, #FAFAF9);
  color: var(--accent, #C4622D);
  border-color: var(--accent, #C4622D);
}

[data-theme="dark"] .claim-list-close-btn {
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(255, 255, 255, 0.12);
  color: #fff;
}

/* ── Competing Claims Warning Notice ─────────────────────── */
.competing-claims-notice {
  margin: 1.25rem 1.75rem 0.5rem;
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.3);
  border-radius: 0.85rem;
  padding: 0.85rem 1.15rem;
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  font-size: 0.825rem;
  line-height: 1.45;
  color: var(--ink, #1C1917);
}

[data-theme="dark"] .competing-claims-notice {
  color: var(--ink, #F7F3EE);
  background: rgba(245, 158, 11, 0.12);
  border-color: rgba(245, 158, 11, 0.4);
}

.competing-claims-notice-icon {
  font-size: 1.2rem;
  flex-shrink: 0;
  line-height: 1;
}

/* ── Scrollable Body ─────────────────────────────────────── */
.claim-list-body {
  padding: 1.25rem 1.75rem 1.75rem;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 1.15rem;
  overscroll-behavior: contain;
}

/* ── Individual Claim Card ───────────────────────────────── */
.claim-request-card {
  background: var(--surface-alt, #FAFAF9);
  border: 1.5px solid var(--border, #E7E3DC);
  border-radius: 1rem;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  transition: all 0.2s ease;
  position: relative;
}

.claim-request-card:hover {
  border-color: rgba(196, 98, 45, 0.4);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
}

[data-theme="dark"] .claim-request-card {
  background: rgba(255, 255, 255, 0.03);
  border-color: rgba(255, 255, 255, 0.08);
}

[data-theme="dark"] .claim-request-card:hover {
  border-color: rgba(196, 98, 45, 0.6);
  background: rgba(255, 255, 255, 0.05);
}

/* Card State Accent Strips */
.claim-request-card.status-pending {
  border-left: 4px solid #F59E0B;
}

.claim-request-card.status-ready {
  border-left: 4px solid #10B981;
}

.claim-request-card.status-verified {
  border-left: 4px solid #059669;
}

.claim-request-card.status-declined {
  border-left: 4px solid #94A3B8;
  opacity: 0.85;
}

/* ── Card Header (Claimant Info) ─────────────────────────── */
.claim-card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.claimant-profile-group {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.claimant-avatar {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--accent, #C4622D), #E06D38);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1rem;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(196, 98, 45, 0.25);
}

.claimant-meta-wrap {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.claimant-name-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.claimant-name {
  font-weight: 700;
  font-size: 0.975rem;
  color: var(--ink, #1C1917);
}

[data-theme="dark"] .claimant-name {
  color: var(--ink, #F7F3EE);
}

.claimant-pin-badge {
  font-family: var(--font-mono, monospace);
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.15rem 0.5rem;
  border-radius: 6px;
  background: var(--bg-alt, #F5F2EE);
  color: var(--ink, #1C1917);
  border: 1px solid var(--border, #E7E3DC);
}

[data-theme="dark"] .claimant-pin-badge {
  background: rgba(255, 255, 255, 0.08);
  color: var(--ink, #F7F3EE);
  border-color: rgba(255, 255, 255, 0.12);
}

.claimant-academic-info {
  font-size: 0.8rem;
  color: var(--muted, #78716C);
}

/* ── Badges Group ────────────────────────────────────────── */
.claim-badges-col {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.4rem;
}

.match-score-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.25rem 0.65rem;
  border-radius: 999px;
  background: rgba(16, 185, 129, 0.12);
  color: #059669;
  border: 1px solid rgba(16, 185, 129, 0.35);
}

.claim-status-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.25rem 0.65rem;
  border-radius: 999px;
}

.claim-status-pill.pending {
  background: rgba(245, 158, 11, 0.14);
  color: #B45309;
  border: 1px solid rgba(245, 158, 11, 0.35);
}

[data-theme="dark"] .claim-status-pill.pending {
  color: #FBBF24;
}

.claim-status-pill.ready {
  background: rgba(16, 185, 129, 0.14);
  color: #059669;
  border: 1px solid rgba(16, 185, 129, 0.35);
}

.claim-status-pill.verified {
  background: rgba(5, 150, 105, 0.15);
  color: #047857;
  border: 1px solid rgba(5, 150, 105, 0.35);
}

.claim-status-pill.declined {
  background: rgba(148, 163, 184, 0.14);
  color: #64748B;
  border: 1px solid rgba(148, 163, 184, 0.3);
}

/* ── Associated Lost Item Snippet ────────────────────────── */
.claim-lost-reference {
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #E7E3DC);
  border-radius: 0.75rem;
  padding: 0.75rem 1rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

[data-theme="dark"] .claim-lost-reference {
  background: rgba(0, 0, 0, 0.25);
  border-color: rgba(255, 255, 255, 0.08);
}

.claim-lost-info {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  min-width: 0;
}

.claim-lost-icon {
  width: 2rem;
  height: 2rem;
  border-radius: 8px;
  background: rgba(196, 98, 45, 0.1);
  color: var(--accent, #C4622D);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.95rem;
  flex-shrink: 0;
}

.claim-lost-texts {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.claim-lost-title {
  font-weight: 600;
  font-size: 0.85rem;
  color: var(--ink, #1C1917);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

[data-theme="dark"] .claim-lost-title {
  color: var(--ink, #F7F3EE);
}

.claim-lost-details {
  font-size: 0.775rem;
  color: var(--muted, #78716C);
}

.claim-lost-link {
  font-size: 0.775rem;
  font-weight: 600;
  color: var(--accent, #C4622D);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  flex-shrink: 0;
}

.claim-lost-link:hover {
  text-decoration: underline;
}

/* ── Submitted Proof Box ─────────────────────────────────── */
.claim-proof-box {
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #E7E3DC);
  border-radius: 0.75rem;
  padding: 0.95rem 1.15rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

[data-theme="dark"] .claim-proof-box {
  background: rgba(0, 0, 0, 0.35);
  border-color: rgba(255, 255, 255, 0.08);
}

.claim-proof-label {
  font-size: 0.775rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--muted, #78716C);
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.claim-proof-text {
  font-size: 0.9rem;
  line-height: 1.55;
  color: var(--ink, #1C1917);
  white-space: pre-wrap;
  word-break: break-word;
}

[data-theme="dark"] .claim-proof-text {
  color: var(--ink, #F7F3EE);
}

.claim-proof-photo-row {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  margin-top: 0.25rem;
}

.claim-proof-thumbnail {
  width: 64px;
  height: 64px;
  border-radius: 8px;
  object-fit: cover;
  border: 1px solid var(--border, #E7E3DC);
  cursor: pointer;
  transition: transform 0.15s ease;
}

.claim-proof-thumbnail:hover {
  transform: scale(1.05);
}

.claim-proof-photo-hint {
  font-size: 0.775rem;
  color: var(--muted, #78716C);
}

/* ── Handover Details Row (When Approved) ─────────────────── */
.claim-handover-info-strip {
  background: rgba(16, 185, 129, 0.08);
  border: 1px solid rgba(16, 185, 129, 0.25);
  border-radius: 0.65rem;
  padding: 0.65rem 0.95rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.825rem;
  color: #065F46;
}

[data-theme="dark"] .claim-handover-info-strip {
  color: #34D399;
  background: rgba(16, 185, 129, 0.12);
  border-color: rgba(16, 185, 129, 0.3);
}

/* ── Card Actions Row ────────────────────────────────────── */
.claim-card-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.65rem;
  flex-wrap: wrap;
  padding-top: 0.25rem;
}

.btn-approve-claim {
  background: var(--accent, #C4622D);
  color: #ffffff;
  border: none;
  border-radius: 999px;
  padding: 0.65rem 1.25rem;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  transition: all 0.2s ease;
  box-shadow: 0 2px 10px rgba(196, 98, 45, 0.28);
}

.btn-approve-claim:hover {
  background: var(--accent-hover, #AA5223);
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(196, 98, 45, 0.38);
}

.btn-decline-claim {
  background: transparent;
  color: var(--muted, #78716C);
  border: 1px solid var(--border, #E7E3DC);
  border-radius: 999px;
  padding: 0.65rem 1.15rem;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  transition: all 0.15s ease;
}

.btn-decline-claim:hover {
  background: rgba(239, 68, 68, 0.08);
  color: #DC2626;
  border-color: rgba(239, 68, 68, 0.3);
}

.btn-verify-otp-claim {
  background: #059669;
  color: #ffffff;
  border: none;
  border-radius: 999px;
  padding: 0.65rem 1.25rem;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  transition: all 0.2s ease;
  box-shadow: 0 2px 10px rgba(5, 150, 105, 0.28);
}

.btn-verify-otp-claim:hover {
  background: #047857;
  transform: translateY(-1px);
}

/* ── Empty State ─────────────────────────────────────────── */
.claim-list-empty {
  padding: 3rem 1.5rem;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
}

.claim-list-empty-icon {
  width: 3.5rem;
  height: 3.5rem;
  border-radius: 50%;
  background: var(--surface-alt, #FAFAF9);
  border: 1px solid var(--border, #E7E3DC);
  color: var(--muted, #78716C);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  margin-bottom: 0.25rem;
}

.claim-list-empty-title {
  font-weight: 700;
  font-size: 1.05rem;
  color: var(--ink, #1C1917);
  margin: 0;
}

[data-theme="dark"] .claim-list-empty-title {
  color: var(--ink, #F7F3EE);
}

.claim-list-empty-text {
  font-size: 0.85rem;
  color: var(--muted, #78716C);
  max-width: 380px;
  line-height: 1.5;
  margin: 0;
}

/* ── Responsive Rules ────────────────────────────────────── */
@media (max-width: 640px) {
  #claimRequestsListOverlay {
    padding: 0.75rem;
  }
  .claim-list-header {
    padding: 1.25rem 1.25rem 1rem;
  }
  .claim-list-body {
    padding: 1rem 1.25rem 1.5rem;
  }
  .competing-claims-notice {
    margin: 1rem 1.25rem 0.25rem;
  }
  .claim-badges-col {
    align-items: flex-start;
    width: 100%;
    flex-direction: row;
    justify-content: space-between;
  }
  .claim-card-actions {
    justify-content: stretch;
  }
  .claim-card-actions button {
    flex: 1;
    justify-content: center;
  }
}
</style>

<!-- ── Claim Requests List Modal Dialog ────────────────────── -->
<div id="claimRequestsListOverlay" role="dialog" aria-modal="true" aria-labelledby="claimRequestsModalTitle" aria-hidden="true" onclick="if(event.target===this) closeClaimRequestsListModal();">
  <div id="claimRequestsListModal" onclick="event.stopPropagation()">
    
    <!-- Header -->
    <div class="claim-list-header">
      <div class="claim-list-title-wrap">
        <div class="claim-list-title-row">
          <h2 class="claim-list-title" id="claimRequestsModalTitle">Claim Requests</h2>
          <span class="claim-badge-count" id="claimListModalCountBadge">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/></svg>
            <span id="claimListModalCountNum"><?= count($founderClaimsList ?? []) ?></span> Claims
          </span>
        </div>
        <p class="claim-list-sub">
          Found Item <strong>#<?= htmlspecialchars($formattedId ?? '') ?></strong> &bull; <?= htmlspecialchars($smartTitle ?? 'Found Item') ?>
        </p>
      </div>

      <button type="button" class="claim-list-close-btn" onclick="closeClaimRequestsListModal()" aria-label="Close dialog">
        &times;
      </button>
    </div>

    <!-- Competing Claims Notice (visible if multiple claims exist) -->
    <?php if (count($founderClaimsList ?? []) > 1): ?>
      <div class="competing-claims-notice">
        <span class="competing-claims-notice-icon">⚠️</span>
        <div>
          <strong>Multiple Claim Requests:</strong>
          Two or more students have claimed this item. Compare their unique distinguishing marks, serial numbers, scratches, or proof images below. Approving one rightful owner will schedule the handover with them and decline competing requests.
        </div>
      </div>
    <?php endif; ?>

    <!-- Scrollable Claims List -->
    <div class="claim-list-body" id="claimRequestsListContent">
      <?php if (!empty($founderClaimsList)): ?>
        <?php foreach ($founderClaimsList as $claim): ?>
          <?php 
            $cMatchId = (int)($claim['match_id'] ?? 0);
            $cStatus = strtolower($claim['match_status'] ?? 'pending');
            $cClaimantName = !empty($claim['claimant_name']) ? htmlspecialchars($claim['claimant_name']) : 'Student Claimant';
            $cPin = !empty($claim['claimant_pin']) ? htmlspecialchars($claim['claimant_pin']) : 'Student';
            $cCollege = !empty($claim['claimant_college']) ? htmlspecialchars($claim['claimant_college']) : 'Sri Venkateswara Govt Polytechnic';
            $cBranch = !empty($claim['claimant_branch']) ? htmlspecialchars($claim['claimant_branch']) : 'Campus Student';
            $cTrust = (int)($claim['claimant_trust_score'] ?? 100);
            $cMatchScore = round((float)($claim['match_percentage'] ?? 95));
            $cProofText = trim((string)($claim['claim_details'] ?? ''));
            $cProofImage = trim((string)($claim['proof_image'] ?? ''));
            $cLostId = (int)($claim['lost_id'] ?? 0);
            $cLostCode = 'RL-' . str_pad($cLostId, 5, '0', STR_PAD_LEFT);
            $cLostTitle = !empty($claim['lost_title']) ? htmlspecialchars($claim['lost_title']) : 'Matching Lost Item';
            $cLostLocation = !empty($claim['lost_location']) ? htmlspecialchars($claim['lost_location']) : 'Campus';
            $cLostDate = !empty($claim['lost_date']) ? htmlspecialchars($claim['lost_date']) : '';

            $initials = strtoupper(substr($cClaimantName, 0, 1));
            if (strpos($cClaimantName, ' ') !== false) {
                $initials .= strtoupper(substr($cClaimantName, strpos($cClaimantName, ' ') + 1, 1));
            }

            $cardStatusClass = 'status-pending';
            $statusLabel = 'Pending Your Review';
            $statusPillClass = 'pending';
            if ($cStatus === 'ready_for_handover') {
                $cardStatusClass = 'status-ready';
                $statusLabel = 'Handover Ready';
                $statusPillClass = 'ready';
            } elseif ($cStatus === 'verified') {
                $cardStatusClass = 'status-verified';
                $statusLabel = 'Reunited & Verified';
                $statusPillClass = 'verified';
            } elseif ($cStatus === 'declined_competition') {
                $cardStatusClass = 'status-declined';
                $statusLabel = 'Declined (Competition)';
                $statusPillClass = 'declined';
            } elseif ($cStatus === 'rejected') {
                $cardStatusClass = 'status-declined';
                $statusLabel = 'Declined';
                $statusPillClass = 'declined';
            }
          ?>

          <div class="claim-request-card <?= $cardStatusClass ?>" id="claimCard_<?= $cMatchId ?>" data-match-id="<?= $cMatchId ?>" data-proof-text="<?= htmlspecialchars($cProofText, ENT_QUOTES, 'UTF-8') ?>" data-proof-img="<?= htmlspecialchars($cProofImage, ENT_QUOTES, 'UTF-8') ?>">
            
            <!-- Card Top: Claimant and Badges -->
            <div class="claim-card-top">
              <div class="claimant-profile-group">
                <div class="claimant-avatar"><?= $initials ?: 'ST' ?></div>
                <div class="claimant-meta-wrap">
                  <div class="claimant-name-row">
                    <span class="claimant-name"><?= $cClaimantName ?></span>
                    <span class="claimant-pin-badge"><?= $cPin ?></span>
                  </div>
                  <span class="claimant-academic-info">
                    <?= $cBranch ?> &bull; <?= $cCollege ?> &bull; <strong style="color:var(--accent);">&#9733; <?= $cTrust ?> Trust</strong>
                  </span>
                </div>
              </div>

              <div class="claim-badges-col">
                <span class="match-score-pill">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                  <?= $cMatchScore ?>% Match
                </span>
                <span class="claim-status-pill <?= $statusPillClass ?>">
                  <?= $statusLabel ?>
                </span>
              </div>
            </div>

            <!-- Associated Lost Report Reference -->
            <div class="claim-lost-reference">
              <div class="claim-lost-info">
                <div class="claim-lost-icon">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/></svg>
                </div>
                <div class="claim-lost-texts">
                  <span class="claim-lost-title">#<?= $cLostCode ?> &bull; <?= $cLostTitle ?></span>
                  <span class="claim-lost-details">Reported lost at <?= $cLostLocation ?><?= $cLostDate ? ' on ' . $cLostDate : '' ?></span>
                </div>
              </div>
              <a href="item.php?type=lost&id=<?= $cLostId ?>" target="_blank" class="claim-lost-link" title="Open lost report in new tab">
                View Report &nearr;
              </a>
            </div>

            <!-- Submitted Proof Box -->
            <div class="claim-proof-box">
              <div class="claim-proof-label">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Submitted Proof of Ownership
              </div>
              <div class="claim-proof-text">
                <?= !empty($cProofText) ? htmlspecialchars($cProofText) : '<em style="color:var(--muted);">(No written proof text provided)</em>' ?>
              </div>

              <!-- Proof Photo Preview if uploaded -->
              <?php if (!empty($cProofImage)): ?>
                <?php 
                  $displayProofSrc = $cProofImage;
                  if (!str_starts_with($displayProofSrc, 'http') && !str_starts_with($displayProofSrc, '/') && !str_starts_with($displayProofSrc, '../')) {
                      $displayProofSrc = '../' . $displayProofSrc;
                  }
                ?>
                <div class="claim-proof-photo-row">
                  <img src="<?= htmlspecialchars($displayProofSrc) ?>" alt="Claimant Proof Photo" class="claim-proof-thumbnail" onclick="if(typeof openItemLightbox==='function'){openItemLightbox('<?= htmlspecialchars($displayProofSrc) ?>', 'Ownership Proof Photo — <?= addslashes($cClaimantName) ?>');}" />
                  <span class="claim-proof-photo-hint">Click photo to zoom</span>
                </div>
              <?php endif; ?>
            </div>

            <!-- Scheduled Meeting Strip if Handover Ready -->
            <?php if ($cStatus === 'ready_for_handover' && !empty($claim['meeting_location'])): ?>
              <div class="claim-handover-info-strip">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                <span>
                  <strong>Handover Scheduled:</strong> Meeting at <?= htmlspecialchars($claim['meeting_location']) ?><?= !empty($claim['meeting_time']) ? ' on ' . htmlspecialchars($claim['meeting_time']) : '' ?>
                </span>
              </div>
            <?php endif; ?>

            <!-- Card Action Buttons -->
            <div class="claim-card-actions">
              <?php if ($cStatus === 'pending_founder_approval'): ?>
                <button type="button" class="btn-decline-claim" onclick="declineClaimFromList(<?= $cMatchId ?>, '<?= addslashes($cClaimantName) ?>')">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                  Decline
                </button>
                <button type="button" class="btn-approve-claim" onclick="approveClaimFromList(<?= $cMatchId ?>)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                  Approve &amp; Set Handover &rarr;
                </button>
              <?php elseif ($cStatus === 'ready_for_handover'): ?>
                <button type="button" class="btn-verify-otp-claim" onclick="verifyOtpFromList(<?= $cMatchId ?>)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                  🤝 Verify Owner OTP
                </button>
              <?php elseif ($cStatus === 'verified'): ?>
                <span style="font-size:0.825rem; font-weight:700; color:#059669; display:inline-flex; align-items:center; gap:0.35rem;">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                  Handover Completed &amp; Reunited
                </span>
              <?php elseif ($cStatus === 'declined_competition'): ?>
                <span style="font-size:0.8rem; color:var(--muted); font-style:italic;">
                  Auto-declined (another claim was approved for this item)
                </span>
              <?php elseif ($cStatus === 'rejected'): ?>
                <span style="font-size:0.8rem; color:#DC2626; font-weight:600;">
                  Claim declined by you
                </span>
              <?php endif; ?>
            </div>

          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="claim-list-empty">
          <div class="claim-list-empty-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          </div>
          <h3 class="claim-list-empty-title">No Claim Requests Yet</h3>
          <p class="claim-list-empty-text">
            When a student reports a matching lost item and submits proof of ownership, their claim request will appear here for your review and handover coordination.
          </p>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
/**
 * REUNITE CLAIM REQUESTS LIST CONTROLLER
 */
(function() {
  const overlay = document.getElementById('claimRequestsListOverlay');
  const modal = document.getElementById('claimRequestsListModal');

  function openClaimRequestsListModal(targetMatchId = null) {
    if (!overlay) return;
    overlay.classList.add('flex');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    // If a specific match ID is targeted, highlight & scroll to it
    if (targetMatchId) {
      setTimeout(() => {
        const targetCard = document.getElementById(`claimCard_${targetMatchId}`);
        if (targetCard) {
          targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
          targetCard.style.outline = '2px solid var(--accent)';
          targetCard.style.boxShadow = '0 0 20px rgba(196, 98, 45, 0.35)';
          setTimeout(() => {
            targetCard.style.outline = '';
            targetCard.style.boxShadow = '';
          }, 3000);
        }
      }, 150);
    }
  }

  function closeClaimRequestsListModal() {
    if (!overlay) return;
    overlay.classList.remove('flex');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  function approveClaimFromList(matchId, proofText, proofImg) {
    const card = document.getElementById(`claimCard_${matchId}`);
    const resolvedProofText = (proofText !== undefined && proofText !== null) 
      ? proofText 
      : (card ? card.getAttribute('data-proof-text') || '' : '');
    const resolvedProofImg = (proofImg !== undefined && proofImg !== null) 
      ? proofImg 
      : (card ? card.getAttribute('data-proof-img') || '' : '');

    closeClaimRequestsListModal();
    setTimeout(() => {
      if (typeof window.openFounderApprovalModal === 'function') {
        window.openFounderApprovalModal(matchId, resolvedProofText, resolvedProofImg);
      } else {
        console.error('Founder approval modal not loaded');
      }
    }, 200);
  }

  function verifyOtpFromList(matchId) {
    closeClaimRequestsListModal();
    setTimeout(() => {
      if (typeof window.openFinderVerifyModal === 'function') {
        window.openFinderVerifyModal(matchId);
      } else {
        console.error('Finder verify modal not loaded');
      }
    }, 200);
  }

  async function declineClaimFromList(matchId, claimantName) {
    if (!confirm(`Are you sure you want to decline the claim request from ${claimantName}?\n\nThis will apply a 24-hour cooldown and notify the student.`)) {
      return;
    }

    const card = document.getElementById(`claimCard_${matchId}`);
    if (card) {
      card.style.opacity = '0.5';
      card.style.pointerEvents = 'none';
    }

    try {
      const formData = new FormData();
      formData.append('match_id', matchId);
      formData.append('action', 'reject');

      const res = await fetch('api/founder_approval.php', {
        method: 'POST',
        body: formData
      });

      const data = await res.json();
      if (data.success) {
        if (window.ReuniteToast) {
          window.ReuniteToast.info('Claim Declined', data.message || 'Claim declined successfully.');
        } else {
          alert(data.message || 'Claim declined.');
        }
        setTimeout(() => {
          window.location.reload();
        }, 800);
      } else {
        alert(data.message || 'Could not decline claim.');
        if (card) {
          card.style.opacity = '';
          card.style.pointerEvents = '';
        }
      }
    } catch (err) {
      alert('Network error while declining claim: ' + err.message);
      if (card) {
        card.style.opacity = '';
        card.style.pointerEvents = '';
      }
    }
  }

  // Keyboard Escape listener
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && overlay && overlay.classList.contains('flex')) {
      closeClaimRequestsListModal();
    }
  });

  // Expose methods to global window
  window.openClaimRequestsListModal = openClaimRequestsListModal;
  window.closeClaimRequestsListModal = closeClaimRequestsListModal;
  window.approveClaimFromList = approveClaimFromList;
  window.verifyOtpFromList = verifyOtpFromList;
  window.declineClaimFromList = declineClaimFromList;
})();
</script>
