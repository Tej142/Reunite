<!-- =========================================================
     REUNITE: OWNER LIVE HANDOVER & OTP MODAL (Claim v2)
     Modal A: Owner live OTP display with drift-free server countdown
     Modal B: Founder / Admin OTP verification input
     ========================================================= -->
<style>
.reunite-handover-overlay {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 100000;
  background: rgba(0, 0, 0, 0.82);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  padding: 1rem;
  overflow-y: auto;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
}
.reunite-handover-overlay.open {
  display: flex !important;
}
.reunite-handover-card {
  position: relative;
  width: 100%;
  max-width: 480px;
  background: #191410 !important;
  color: #fbf7f4 !important;
  border: 1px solid rgba(255, 138, 92, 0.28);
  border-radius: 24px;
  box-shadow: 0 25px 60px -12px rgba(0, 0, 0, 0.9);
  padding: 2rem 1.75rem;
  margin: auto;
  box-sizing: border-box;
}
.reunite-handover-close {
  position: absolute;
  top: 1.15rem;
  right: 1.15rem;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: none;
  background: rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.75);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}
.reunite-handover-close:hover {
  background: rgba(255, 255, 255, 0.16);
  color: #ffffff;
}
.handover-otp-grid {
  display: flex;
  gap: 0.5rem;
  justify-content: center;
  margin: 1.5rem 0 1.25rem;
}
.handover-otp-box {
  width: 50px;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: 'JetBrains Mono', monospace;
  font-size: 2rem;
  font-weight: 700;
  color: #ff8a5c;
  background: rgba(255, 138, 92, 0.09);
  border: 1.5px solid rgba(255, 138, 92, 0.45);
  border-radius: 14px;
  box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.3);
}
.handover-countdown-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1rem;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 12px;
  font-size: 0.85rem;
  color: rgba(255, 255, 255, 0.75);
  margin-bottom: 1.25rem;
}
.handover-progress-track {
  width: 100%;
  height: 4px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
  margin-top: 0.5rem;
  overflow: hidden;
}
.handover-progress-fill {
  height: 100%;
  width: 100%;
  background: #ff8a5c;
  transition: width 1s linear;
}
.handover-input-otp {
  width: 100%;
  text-align: center;
  letter-spacing: 0.65rem;
  font-family: 'JetBrains Mono', monospace;
  font-size: 2rem;
  font-weight: 700;
  padding: 0.85rem 1rem;
  background: #241d18;
  border: 1.5px solid rgba(255, 255, 255, 0.2);
  border-radius: 14px;
  color: #ffffff;
  outline: none;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.handover-input-otp:focus {
  border-color: #ff8a5c;
  box-shadow: 0 0 0 3px rgba(255, 138, 92, 0.25);
}
.handover-msg-box {
  padding: 0.75rem 1rem;
  border-radius: 12px;
  font-size: 0.825rem;
  line-height: 1.45;
  margin-bottom: 1rem;
}
.handover-msg-error {
  background: rgba(239, 68, 68, 0.12);
  border: 1px solid rgba(239, 68, 68, 0.35);
  color: #fca5a5;
}
.handover-msg-success {
  background: rgba(34, 197, 94, 0.12);
  border: 1px solid rgba(34, 197, 94, 0.35);
  color: #86efac;
}
.handover-btn-primary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.85rem 1.5rem;
  border-radius: 14px;
  background: #ff8a5c;
  color: #1a1511;
  font-weight: 700;
  font-size: 0.95rem;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
}
.handover-btn-primary:hover:not(:disabled) {
  background: #ff9a72;
  transform: translateY(-1px);
}
.handover-btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>

<!-- ── 1. OWNER LIVE OTP DISPLAY MODAL ── -->
<div id="ownerHandoverDisplayModal" class="reunite-handover-overlay" role="dialog" aria-modal="true" aria-hidden="true" onclick="if(event.target===this)closeOwnerHandoverModal()">
  <div class="reunite-handover-card" onclick="event.stopPropagation()">
    <button type="button" class="reunite-handover-close" onclick="closeOwnerHandoverModal()" aria-label="Close dialog">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
    </button>

    <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.35rem;">
      <span style="font-size: 1.5rem;">🤝</span>
      <h3 style="margin: 0; font-size: 1.3rem; font-weight: 700; color: #fff;">Live Handover Code (OTP)</h3>
    </div>
    <p style="font-size: 0.85rem; color: rgba(255, 255, 255, 0.68); line-height: 1.5; margin: 0 0 1rem 0;">
      Show or read this 6-digit code to the finder or desk staff upon inspecting and receiving your item.
    </p>

    <div id="ownerHandoverFeedback" class="handover-msg-box handover-msg-error" style="display: none;"></div>

    <div id="ownerHandoverDigits" class="handover-otp-grid">
      <div class="handover-otp-box">-</div>
      <div class="handover-otp-box">-</div>
      <div class="handover-otp-box">-</div>
      <div class="handover-otp-box">-</div>
      <div class="handover-otp-box">-</div>
      <div class="handover-otp-box">-</div>
    </div>

    <div class="handover-countdown-bar">
      <div>
        <span>⏱️ Valid for: <strong id="ownerOtpTimerDigits" style="color: #ff8a5c; font-family: 'JetBrains Mono', monospace;">05:00</strong></span>
        <div class="handover-progress-track">
          <div id="ownerOtpProgressBar" class="handover-progress-fill" style="width: 100%;"></div>
        </div>
      </div>
      <button type="button" id="btnRegenerateOtp" onclick="regenerateOwnerOtpAction()" style="background: none; border: none; color: #ff8a5c; font-size: 0.825rem; font-weight: 600; cursor: pointer; text-decoration: underline; padding: 0.25rem 0.5rem;">
        Refresh Code
      </button>
    </div>

    <div style="font-size: 0.775rem; color: rgba(255, 255, 255, 0.45); line-height: 1.45; text-align: center;">
      🔒 <strong>Cryptographic Verification:</strong> Once entered and verified, this item will be officially recorded as <strong>Reunited</strong> and trust points will be awarded.
    </div>
  </div>
</div>

<!-- ── 2. FINDER / DESK OTP VERIFICATION MODAL ── -->
<div id="finderHandoverVerifyModal" class="reunite-handover-overlay" role="dialog" aria-modal="true" aria-hidden="true" onclick="if(event.target===this)closeFinderVerifyModal()">
  <div class="reunite-handover-card" onclick="event.stopPropagation()">
    <button type="button" class="reunite-handover-close" onclick="closeFinderVerifyModal()" aria-label="Close dialog">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
    </button>

    <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.35rem;">
      <span style="font-size: 1.5rem;">🤝</span>
      <h3 style="margin: 0; font-size: 1.3rem; font-weight: 700; color: #fff;">Confirm Physical Handover</h3>
    </div>
    <p style="font-size: 0.85rem; color: rgba(255, 255, 255, 0.68); line-height: 1.5; margin: 0 0 1rem 0;">
      Ask the owner for their 6-digit verification code. Entering it will verify the reunion and award you <strong>+10 Trust Points</strong>.
    </p>

    <div id="finderVerifyFeedback" class="handover-msg-box" style="display: none;"></div>

    <form id="finderVerifyForm" onsubmit="handleFinderVerifySubmit(event)">
      <div style="margin-bottom: 1.25rem;">
        <label for="finderInputOtpCode" style="display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: rgba(255, 255, 255, 0.6); margin-bottom: 0.5rem;">
          Enter 6-Digit Owner Code
        </label>
        <input type="text" id="finderInputOtpCode" class="handover-input-otp" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="••••••" autocomplete="one-time-code" required />
      </div>

      <button type="submit" id="btnSubmitFinderVerify" class="handover-btn-primary">
        <span>Verify &amp; Complete Handover &rarr;</span>
      </button>
    </form>
  </div>
</div>

<script>
(function() {
  let activeCountdownTimer = null;
  let activeMatchId = null;
  let serverExpiryTimestamp = 0;
  let clientServerOffset = 0;

  // 1. OPEN OWNER OTP MODAL & FETCH LIVE OTP
  window.openOwnerHandoverModal = async function(matchId) {
    activeMatchId = matchId;
    const modal = document.getElementById('ownerHandoverDisplayModal');
    if (!modal) return;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');

    await fetchAndRenderLiveOtp(matchId);
  };

  window.closeOwnerHandoverModal = function() {
    const modal = document.getElementById('ownerHandoverDisplayModal');
    if (modal) {
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
    }
    if (activeCountdownTimer) clearInterval(activeCountdownTimer);
  };

  async function fetchAndRenderLiveOtp(matchId) {
    const fb = document.getElementById('ownerHandoverFeedback');
    const digitsContainer = document.getElementById('ownerHandoverDigits');
    const regenBtn = document.getElementById('btnRegenerateOtp');

    if (fb) fb.style.display = 'none';
    if (regenBtn) regenBtn.disabled = true;

    try {
      const res = await fetch('api/generate_otp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'match_id=' + encodeURIComponent(matchId)
      });
      const data = await res.json();

      if (regenBtn) regenBtn.disabled = false;

      if (!res.ok || !data.success) {
        if (fb) {
          fb.className = 'handover-msg-box handover-msg-error';
          fb.textContent = data.message || 'Could not generate handover code.';
          fb.style.display = 'block';
        }
        return;
      }

      // Render 6 digits
      const otpStr = String(data.otp);
      if (digitsContainer) {
        digitsContainer.innerHTML = otpStr.split('').map(d => `<div class="handover-otp-box">${d}</div>`).join('');
      }

      // Calculate server-client clock offset
      const clientNow = Math.floor(Date.now() / 1000);
      const serverNow = data.server_now || clientNow;
      clientServerOffset = clientNow - serverNow;
      serverExpiryTimestamp = data.expires_at_timestamp;

      startCountdown(serverExpiryTimestamp, data.validity_seconds || 300);

    } catch (err) {
      if (regenBtn) regenBtn.disabled = false;
      if (fb) {
        fb.className = 'handover-msg-box handover-msg-error';
        fb.textContent = 'Network error while contacting server. Please retry.';
        fb.style.display = 'block';
      }
    }
  }

  function startCountdown(expiryTs, totalSeconds) {
    if (activeCountdownTimer) clearInterval(activeCountdownTimer);

    function tick() {
      const currentServerTime = Math.floor(Date.now() / 1000) - clientServerOffset;
      const remaining = expiryTs - currentServerTime;

      const timerDisplay = document.getElementById('ownerOtpTimerDigits');
      const progressBar = document.getElementById('ownerOtpProgressBar');

      if (remaining <= 0) {
        if (timerDisplay) timerDisplay.textContent = '00:00 (Expired)';
        if (progressBar) progressBar.style.width = '0%';
        clearInterval(activeCountdownTimer);

        const fb = document.getElementById('ownerHandoverFeedback');
        if (fb) {
          fb.className = 'handover-msg-box handover-msg-error';
          fb.textContent = 'Code has expired. Tap "Refresh Code" for a new code.';
          fb.style.display = 'block';
        }
        return;
      }

      const m = Math.floor(remaining / 60);
      const s = remaining % 60;
      if (timerDisplay) {
        timerDisplay.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
      }

      const pct = Math.max(0, Math.min(100, (remaining / totalSeconds) * 100));
      if (progressBar) {
        progressBar.style.width = pct + '%';
        progressBar.style.background = (pct < 20) ? '#ef4444' : '#ff8a5c';
      }
    }

    tick();
    activeCountdownTimer = setInterval(tick, 1000);
  }

  window.regenerateOwnerOtpAction = function() {
    if (activeMatchId) fetchAndRenderLiveOtp(activeMatchId);
  };

  // 2. FINDER OTP VERIFICATION MODAL
  window.openFinderVerifyModal = function(matchId) {
    activeMatchId = matchId;
    const modal = document.getElementById('finderHandoverVerifyModal');
    if (!modal) return;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');

    const input = document.getElementById('finderInputOtpCode');
    if (input) {
      input.value = '';
      setTimeout(() => input.focus(), 150);
    }
    const fb = document.getElementById('finderVerifyFeedback');
    if (fb) fb.style.display = 'none';
  };

  window.closeFinderVerifyModal = function() {
    const modal = document.getElementById('finderHandoverVerifyModal');
    if (modal) {
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
    }
  };

  window.handleFinderVerifySubmit = async function(e) {
    e.preventDefault();
    const input = document.getElementById('finderInputOtpCode');
    const submitBtn = document.getElementById('btnSubmitFinderVerify');
    const fb = document.getElementById('finderVerifyFeedback');

    const code = input ? input.value.trim() : '';
    if (!code || code.length !== 6) {
      if (fb) {
        fb.className = 'handover-msg-box handover-msg-error';
        fb.textContent = 'Please enter all 6 digits of the code.';
        fb.style.display = 'block';
      }
      return;
    }

    if (submitBtn) submitBtn.disabled = true;
    if (fb) fb.style.display = 'none';

    try {
      const res = await fetch('api/verify_otp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'match_id=' + encodeURIComponent(activeMatchId) + '&otp=' + encodeURIComponent(code)
      });
      const data = await res.json();

      if (submitBtn) submitBtn.disabled = false;

      if (!res.ok || !data.success) {
        if (fb) {
          fb.className = 'handover-msg-box handover-msg-error';
          fb.textContent = data.message || 'Verification failed.';
          fb.style.display = 'block';
        }
        return;
      }

      if (fb) {
        fb.className = 'handover-msg-box handover-msg-success';
        fb.textContent = '🎉 Handover confirmed! Both reports are marked Reunited.';
        fb.style.display = 'block';
      }

      if (window.ReuniteToast) {
        window.ReuniteToast.show({
          title: 'Handover Completed! 🎉',
          message: 'Item officially marked Reunited (+10 Trust Points awarded).',
          type: 'success',
          orbiState: 'happy',
          duration: 4000
        });
      }

      setTimeout(() => {
        window.location.reload();
      }, 1800);

    } catch (err) {
      if (submitBtn) submitBtn.disabled = false;
      if (fb) {
        fb.className = 'handover-msg-box handover-msg-error';
        fb.textContent = 'Could not verify code. Network error.';
        fb.style.display = 'block';
      }
    }
  };

  // Auto-submit on 6th digit entered
  document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('finderInputOtpCode');
    if (input) {
      input.addEventListener('input', () => {
        if (input.value.length === 6) {
          const form = document.getElementById('finderVerifyForm');
          if (form) form.requestSubmit();
        }
      });
    }
  });

})();
</script>
