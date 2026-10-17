<!-- =========================================================
     REUNITE HANDOVER & RECOVERY MODALS (M1 / M2 / M3)
     Includes:
     - M2: Owner OTP Display Modal
     - M2: Finder OTP Verification Modal
     - M3: Campus Admin Office Drop-Off Modal
     ========================================================= -->
<style>
.reunite-otp-overlay {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 100000;
  background: rgba(0, 0, 0, 0.78);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  padding: 1rem;
  overflow-y: auto;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
}
.reunite-otp-overlay.open {
  display: flex !important;
}
.reunite-otp-card {
  position: relative;
  width: 100%;
  max-width: 480px;
  background: var(--card-bg, #1a1511);
  color: var(--text, #ffffff);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 20px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
  padding: 1.75rem;
  margin: auto;
  box-sizing: border-box;
}
.otp-display-digits {
  display: flex;
  gap: 0.5rem;
  justify-content: center;
  margin: 1.25rem 0;
}
.otp-digit-box {
  width: 48px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: 'JetBrains Mono', monospace;
  font-size: 1.75rem;
  font-weight: 700;
  color: #ff8a5c;
  background: rgba(255, 138, 92, 0.08);
  border: 1.5px solid rgba(255, 138, 92, 0.4);
  border-radius: 12px;
}
.otp-input-field {
  width: 100%;
  text-align: center;
  letter-spacing: 0.6rem;
  font-family: 'JetBrains Mono', monospace;
  font-size: 1.85rem;
  font-weight: 700;
  padding: 0.75rem 1rem;
  background: #241d18;
  border: 1.5px solid rgba(255, 255, 255, 0.18);
  border-radius: 14px;
  color: #ffffff;
  outline: none;
  transition: border-color 0.2s ease;
}
.otp-input-field:focus {
  border-color: #ff8a5c;
  box-shadow: 0 0 0 3px rgba(255, 138, 92, 0.25);
}
.otp-timer-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.65rem 0.85rem;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 10px;
  font-size: 0.825rem;
  color: rgba(255, 255, 255, 0.7);
  margin-bottom: 1.25rem;
}
.otp-btn-close {
  position: absolute;
  top: 1rem;
  right: 1rem;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  border: none;
  background: rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.7);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}
.otp-btn-close:hover {
  background: rgba(255, 255, 255, 0.15);
  color: #fff;
}
</style>

<!-- ── 1. OWNER OTP DISPLAY MODAL (M2) ── -->
<div id="ownerOtpDisplayModal" class="reunite-otp-overlay" role="dialog" aria-modal="true" aria-hidden="true" onclick="if(event.target===this)closeOwnerOtpModal()">
  <div class="reunite-otp-card" onclick="event.stopPropagation()">
    <button type="button" class="otp-btn-close" onclick="closeOwnerOtpModal()" aria-label="Close">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
    </button>

    <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.4rem;">
      <span style="font-size:1.5rem;">🤝</span>
      <h3 style="margin:0; font-size:1.25rem; font-weight:700; color:#fff;">Handover Code (OTP)</h3>
    </div>
    <p style="font-size:0.85rem; color:rgba(255,255,255,0.65); margin:0 0 1rem 0;">
      Show or read this 6-digit verification code to the finder when you physically meet to inspect and collect your item.
    </p>

    <div id="ownerOtpDigitsBox" class="otp-display-digits">
      <div class="otp-digit-box">-</div>
      <div class="otp-digit-box">-</div>
      <div class="otp-digit-box">-</div>
      <div class="otp-digit-box">-</div>
      <div class="otp-digit-box">-</div>
      <div class="otp-digit-box">-</div>
    </div>

    <div class="otp-timer-bar">
      <span>⏱️ Valid for: <strong id="ownerOtpTimerText" style="color:#ff8a5c;">05:00</strong></span>
      <button type="button" onclick="regenerateOwnerOtp()" style="background:none; border:none; color:#ff8a5c; font-size:0.8rem; font-weight:600; cursor:pointer; text-decoration:underline;">
        Refresh Code
      </button>
    </div>

    <div style="font-size:0.75rem; color:rgba(255,255,255,0.45); line-height:1.4; text-align:center;">
      🔒 <strong>Mode 2 Security:</strong> Once the finder enters this code, the handover is permanently recorded, both reports are resolved, and trust scores are rewarded.
    </div>
  </div>
</div>

<!-- ── 2. FINDER OTP VERIFICATION MODAL (M2) ── -->
<div id="finderOtpVerifyModal" class="reunite-otp-overlay" role="dialog" aria-modal="true" aria-hidden="true" onclick="if(event.target===this)closeFinderOtpModal()">
  <div class="reunite-otp-card" onclick="event.stopPropagation()">
    <button type="button" class="otp-btn-close" onclick="closeFinderOtpModal()" aria-label="Close">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
    </button>

    <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.4rem;">
      <span style="font-size:1.5rem;">🤝</span>
      <h3 style="margin:0; font-size:1.25rem; font-weight:700; color:#fff;">Confirm Physical Handover</h3>
    </div>
    <p style="font-size:0.85rem; color:rgba(255,255,255,0.65); margin:0 0 1rem 0;">
      Ask the owner for their 6-digit code during meetup. Entering it will verify the handover and award you <strong>+10 Trust Points</strong>.
    </p>

    <div id="finderOtpFeedback" style="display:none; padding:0.75rem; border-radius:10px; font-size:0.825rem; margin-bottom:1rem;"></div>

    <form id="finderOtpForm" onsubmit="handleFinderOtpSubmit(event)">
      <div style="margin-bottom:1.25rem;">
        <label for="finderInputOtp" style="display:block; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:rgba(255,255,255,0.6); margin-bottom:0.5rem;">
          Enter 6-Digit Code
        </label>
        <input type="text" id="finderInputOtp" class="otp-input-field" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="••••••" autocomplete="one-time-code" required />
      </div>

      <button type="submit" id="btnSubmitFinderOtp" class="btn-claim-primary" style="width:100%; justify-content:center; padding:0.85rem;">
        <span>Verify &amp; Complete Handover &rarr;</span>
      </button>
    </form>
  </div>
</div>

<!-- ── 3. CAMPUS ADMIN OFFICE DROP-OFF MODAL (M3) ── -->
<div id="adminOfficeReturnModal" class="reunite-otp-overlay" role="dialog" aria-modal="true" aria-hidden="true" onclick="if(event.target===this)closeAdminOfficeModal()">
  <div class="reunite-otp-card" onclick="event.stopPropagation()">
    <button type="button" class="otp-btn-close" onclick="closeAdminOfficeModal()" aria-label="Close">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
    </button>

    <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.4rem;">
      <span style="font-size:1.5rem;">🏢</span>
      <h3 style="margin:0; font-size:1.25rem; font-weight:700; color:#fff;">Campus Admin Office Return</h3>
    </div>
    <p style="font-size:0.85rem; color:rgba(255,255,255,0.65); margin:0 0 1rem 0;">
      Can't coordinate an in-person meetup? Drop off or collect the item safely at the <strong>Campus Lost &amp; Found Desk (Admin Block, Room 104)</strong>.
    </p>

    <form id="adminOfficeForm" onsubmit="handleAdminOfficeSubmit(event)">
      <div style="margin-bottom:0.85rem;">
        <label for="adminOfficeStudentName" style="display:block; font-size:0.75rem; font-weight:600; text-transform:uppercase; color:rgba(255,255,255,0.6); margin-bottom:0.4rem;">
          Your Name / Student PIN
        </label>
        <input type="text" id="adminOfficeStudentName" class="claim-text-input" style="width:100%; padding:0.7rem; background:#241d18; border:1px solid rgba(255,255,255,0.15); border-radius:10px; color:#fff;" value="<?php echo htmlspecialchars($current_user['name'] ?? ''); ?>" required />
      </div>

      <div style="margin-bottom:1.25rem;">
        <label for="adminOfficeNotes" style="display:block; font-size:0.75rem; font-weight:600; text-transform:uppercase; color:rgba(255,255,255,0.6); margin-bottom:0.4rem;">
          Drop-Off Reference / Preferred Collection Time
        </label>
        <textarea id="adminOfficeNotes" class="claim-text-input" rows="3" style="width:100%; padding:0.7rem; background:#241d18; border:1px solid rgba(255,255,255,0.15); border-radius:10px; color:#fff;" placeholder="e.g. Deposited at Security Desk on Thursday 2 PM. Student ID will be presented on pickup." required></textarea>
      </div>

      <button type="submit" id="btnSubmitAdminOffice" class="btn-claim-primary" style="width:100%; justify-content:center; padding:0.85rem;">
        <span>Route to Campus Admin Office &rarr;</span>
      </button>
    </form>
  </div>
</div>
