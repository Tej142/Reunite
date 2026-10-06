document.addEventListener('DOMContentLoaded', () => {
  const isFoundPage = !!document.getElementById('foundForm') || window.location.pathname.includes('found');
  const reportType = isFoundPage ? 'found' : 'lost';

  // 1. Mode Switching Logic
  const modeCards = document.querySelectorAll('.mode-card');
  const modeContainers = document.querySelectorAll('.mode-container');
  const aiStatusText = document.getElementById('aiStatusText');

  const modeDescriptions = {
    mode1: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> <strong>AI Vision & Text Parser:</strong> Upload photos & describe your item directly. AI will parse details and index tags automatically.',
    mode2: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg> <strong>Guided Choice Assistant:</strong> Step-by-step interactive questionnaire with AI smart prompts tailored for rapid input.',
    mode3: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg> <strong>Talk to AI Live Copilot:</strong> Natural voice & chat intake assistant. Dynamically understands natural slang, asks tailored item-specific questions, and extracts attributes in real time.'
  };

  modeCards.forEach(card => {
    card.addEventListener('click', () => {
      const selectedMode = card.getAttribute('data-mode');
      
      // Update Tab Selection
      modeCards.forEach(c => c.classList.remove('active'));
      card.classList.add('active');

      // Update Active Container
      modeContainers.forEach(container => {
        if (container.id === `${selectedMode}Container`) {
          container.classList.add('active');
        } else {
          container.classList.remove('active');
        }
      });

      // Update AI Status Banner
      if (aiStatusText && modeDescriptions[selectedMode]) {
        aiStatusText.innerHTML = modeDescriptions[selectedMode];
      }

      if (selectedMode === 'mode3' && typeof checkAiServerHealth === 'function') {
        checkAiServerHealth();
      }
    });
  });

  // 2. Mode 1 AI Vision & Text Analyzer
  const descInput = document.querySelector('#mode1Container textarea, #description');
  const aiInsightsBox = document.getElementById('aiInsightsBox');
  const aiTagsList = document.getElementById('aiTagsList');

  if (descInput) {
    let debounceTimer;
    descInput.addEventListener('input', (e) => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        analyzeText(e.target.value);
      }, 500);
    });
  }

  function analyzeText(text) {
    if (!text || text.trim().length < 8) {
      if (aiInsightsBox) aiInsightsBox.classList.remove('show');
      return;
    }

    const lower = text.toLowerCase();
    const detectedTags = [];

    if (lower.includes('iphone') || lower.includes('phone') || lower.includes('laptop') || lower.includes('airpods') || lower.includes('ipad')) {
      detectedTags.push('Electronics');
    }
    if (lower.includes('wallet') || lower.includes('bag') || lower.includes('purse') || lower.includes('backpack')) {
      detectedTags.push('Wallets & Bags');
    }
    if (lower.includes('key') || lower.includes('fob')) {
      detectedTags.push('Keys');
    }
    if (lower.includes('black')) detectedTags.push('Color: Black');
    if (lower.includes('blue')) detectedTags.push('Color: Blue');
    if (lower.includes('brown') || lower.includes('leather')) detectedTags.push('Material: Leather');
    if (lower.includes('subway') || lower.includes('park') || lower.includes('train') || lower.includes('street')) {
      detectedTags.push('Location detail detected');
    }

    if (detectedTags.length === 0) {
      detectedTags.push('Standard Item', 'Auto-Indexed');
    }

    if (aiTagsList) {
      aiTagsList.innerHTML = detectedTags.map(tag => `<span class="ai-tag-chip">${tag}</span>`).join('');
    }

    if (aiInsightsBox) {
      aiInsightsBox.classList.add('show');
    }
  }

  // 3. Mode 2 Guided Choice Wizard Logic
  let currentStep = 1;
  const totalSteps = 3;
  const guidedState = {
    category: '',
    color: '',
    condition: '',
    brand: '',
    where: '',
    when: '',
    verification: '',
    email: ''
  };

  const stepDots = document.querySelectorAll('.wizard-step-dot');
  const wizardSteps = document.querySelectorAll('.wizard-step-content');
  const btnPrev = document.getElementById('btnWizardPrev');
  const btnNext = document.getElementById('btnWizardNext');

  // Category Option Tiles click
  document.querySelectorAll('.option-tile').forEach(tile => {
    tile.addEventListener('click', () => {
      const parent = tile.parentElement;
      parent.querySelectorAll('.option-tile').forEach(t => t.classList.remove('selected'));
      tile.classList.add('selected');
      guidedState.category = tile.getAttribute('data-value') || tile.querySelector('.option-tile-label').textContent;
      const catSelect = document.getElementById('category');
      if (catSelect && guidedState.category) {
        catSelect.value = guidedState.category.toLowerCase();
      }
    });
  });

  // Choice Pills click
  document.querySelectorAll('.choice-pill').forEach(pill => {
    pill.addEventListener('click', () => {
      const group = pill.parentElement;
      group.querySelectorAll('.choice-pill').forEach(p => p.classList.remove('selected'));
      pill.classList.add('selected');
      const attr = group.getAttribute('data-group');
      if (attr) {
        guidedState[attr] = pill.textContent.trim();
      }
    });
  });

  if (btnNext) {
    btnNext.addEventListener('click', () => {
      if (currentStep < totalSteps) {
        currentStep++;
        updateWizardUI();
      } else {
        submitGuidedReport();
      }
    });
  }

  if (btnPrev) {
    btnPrev.addEventListener('click', () => {
      if (currentStep > 1) {
        currentStep--;
        updateWizardUI();
      }
    });
  }

  function updateWizardUI() {
    stepDots.forEach((dot, index) => {
      const stepNum = index + 1;
      dot.classList.remove('active', 'completed');
      if (stepNum === currentStep) {
        dot.classList.add('active');
      } else if (stepNum < currentStep) {
        dot.classList.add('completed');
      }
    });

    wizardSteps.forEach(step => {
      if (parseInt(step.getAttribute('data-step')) === currentStep) {
        step.style.display = 'block';
      } else {
        step.style.display = 'none';
      }
    });

    if (btnPrev) {
      btnPrev.style.visibility = currentStep === 1 ? 'hidden' : 'visible';
    }

    if (btnNext) {
      btnNext.textContent = currentStep === totalSteps ? 'Process & Extract Features' : 'Next Step →';
    }
  }

  async function submitGuidedReport() {
    const whereInput = document.getElementById('guidedWhere');
    const whenInput = document.getElementById('guidedWhen');
    const emailInput = document.getElementById('guidedEmail');
    const verificationInput = document.getElementById('guidedVerification');

    if (whereInput) guidedState.where = whereInput.value;
    if (whenInput) guidedState.when = whenInput.value;
    if (emailInput) guidedState.email = emailInput.value;
    if (verificationInput) guidedState.verification = verificationInput.value;

    const summaryText = `${guidedState.color ? guidedState.color + ' ' : ''}${guidedState.category || 'item'}. Condition: ${guidedState.condition || 'good'}. ${guidedState.brand ? 'Brand: ' + guidedState.brand : ''}`;
    
    if (btnNext && window.setButtonLoading) {
      window.setButtonLoading(btnNext, true, 'Synthesizing Digital DNA...');
    }

    const meta = {
      title: `${guidedState.color ? guidedState.color + ' ' : ''}${guidedState.category || 'Item'}`,
      where: guidedState.where || '',
      when: guidedState.when || '',
      verification: guidedState.verification || ''
    };

    try {
      let dna = null;
      if (window.FlaskAIService) {
        const aiResult = await window.FlaskAIService.submitReportToFlask({
          description: summaryText,
          meta: meta
        });
        if (aiResult && aiResult.digital_dna) {
          dna = aiResult.digital_dna;
        } else {
          dna = window.FlaskAIService.extractClientDna(summaryText, meta);
        }
      }

      if (typeof window.transitionToReview === 'function') {
        window.transitionToReview(dna, {
          title: meta.title,
          description: summaryText,
          where: meta.where,
          when: meta.when,
          verification: meta.verification
        });
      } else {
        showSuccessState(summaryText, { ...meta, dna });
      }
    } catch (e) {
      console.error('Error extracting DNA from guided state:', e);
      const dna = window.FlaskAIService ? window.FlaskAIService.extractClientDna(summaryText, meta) : null;
      if (typeof window.transitionToReview === 'function') {
        window.transitionToReview(dna, {
          title: meta.title,
          description: summaryText,
          where: meta.where,
          when: meta.when,
          verification: meta.verification
        });
      } else {
        showSuccessState(summaryText, { ...meta, dna });
      }
    } finally {
      if (btnNext && window.setButtonLoading) {
        window.setButtonLoading(btnNext, false);
      }
    }
  }

  // 4. Mode 3 Talk to AI Chat & Dynamic Attribute Extractor Logic
  const chatMessages = document.getElementById('chatMessages');
  const chatInput = document.getElementById('chatInput');
  const btnSendChat = document.getElementById('btnSendChat');
  const promptChips = document.querySelectorAll('.prompt-chip');
  const dynamicDraftContainer = document.getElementById('dynamicDraftContainer');
  const btnDraftSubmit = document.getElementById('btnDraftSubmit');
  const btnVoiceToggle = document.getElementById('btnVoiceToggle');
  const btnMicInline = document.getElementById('btnMicInline');
  const voiceLiveBadge = document.getElementById('voiceLiveBadge');
  const voiceBadgeText = document.getElementById('voiceBadgeText');
  const voiceVisualizer = document.getElementById('voiceVisualizer');
  const voiceToggleLabel = document.getElementById('voiceToggleLabel');

  let chatHistory = [];
  let isAiResponding = false;
  let isVoiceModeActive = false;
  let isAiSpeaking = false;
  let currentDraft = {
    title: '',
    category: '',
    location: '',
    time: '',
    dynamic_attributes: {},
    verification_secret: '',
    contact: '',
    raw_summary: ''
  };

  if (btnSendChat && chatInput) {
    btnSendChat.addEventListener('click', () => handleUserChat());
    chatInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') handleUserChat();
    });
  }

  promptChips.forEach(chip => {
    chip.addEventListener('click', () => {
      const text = chip.textContent.replace(/^"|"$/g, '').trim();
      if (chatInput) chatInput.value = text;
      handleUserChat(text);
    });
  });

  let isAiServerOffline = false;

  function checkAiServerHealth() {
    const flaskBaseUrl = window.FLASK_BACKEND_URL || 'https://reunite-ai-backend.onrender.com';
    const subStatus = document.getElementById('talkAiSubStatus');
    fetch(`${flaskBaseUrl}/`, { method: 'GET', mode: 'cors' })
      .then(res => {
        if (res.ok) {
          isAiServerOffline = false;
          if (subStatus) subStatus.textContent = 'Powered by Google Gemini Live Voice';
          updateVoiceBadgeState();
        } else {
          isAiServerOffline = true;
          if (subStatus) subStatus.innerHTML = '<span style="color:#F59E0B; font-weight:600;">⚠️ AI Server Waking Up (Wait ~30s)</span>';
          updateVoiceBadgeState();
        }
      })
      .catch(() => {
        isAiServerOffline = true;
        if (subStatus) subStatus.innerHTML = '<span style="color:#EF4444; font-weight:600;">⚠️ AI Server Offline / Waking Up</span>';
        updateVoiceBadgeState();
      });
  }

  async function handleUserChat(directMessage) {
    if (isAiResponding) return;
    const msg = directMessage || (chatInput ? chatInput.value.trim() : '');
    if (!msg) return;

    // Temporarily pause recognition while processing/speaking AI reply
    pauseRecognition();

    // Add user bubble
    addChatBubble(msg, 'user');
    chatHistory.push({ role: 'user', text: msg });
    if (chatInput) chatInput.value = '';

    // Show AI thinking bubble
    const thinkingBubble = showThinkingIndicator();
    isAiResponding = true;
    updateVoiceBadgeState();

    const flaskBaseUrl = window.FLASK_BACKEND_URL || 'https://reunite-ai-backend.onrender.com';

    try {
      const response = await fetch(`${flaskBaseUrl}/report/talk_to_ai/chat`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          message: msg,
          history: chatHistory,
          report_type: isFoundPage ? 'found' : 'lost',
          current_draft: currentDraft
        })
      });

      if (!response.ok) {
        throw new Error(`Server returned HTTP ${response.status} (${response.statusText || 'Offline or waking up'})`);
      }

      const result = await response.json();
      removeThinkingIndicator(thinkingBubble);
      isAiResponding = false;
      isAiServerOffline = false;

      const subStatus = document.getElementById('talkAiSubStatus');
      if (subStatus) subStatus.textContent = 'Powered by Google Gemini Live Voice';

      if (result.success && result.reply) {
        addChatBubble(result.reply, 'ai');
        chatHistory.push({ role: 'assistant', text: result.reply });

        if (result.draft) {
          currentDraft = {
            ...currentDraft,
            ...result.draft,
            dynamic_attributes: {
              ...(currentDraft.dynamic_attributes || {}),
              ...(result.draft.dynamic_attributes || {})
            }
          };
          renderDynamicDraft(currentDraft, result.is_ready_to_submit);
        }

        // If Voice Mode is active, speak the reply aloud and resume listening after
        if (isVoiceModeActive) {
          speakAiReply(result.reply);
        } else {
          updateVoiceBadgeState();
        }
      } else {
        const fallbackReply = result.error || "I've noted that! Could you tell me more about the item or where you last saw it?";
        addChatBubble(fallbackReply, 'ai');
        if (isVoiceModeActive) {
          speakAiReply(fallbackReply);
        } else {
          updateVoiceBadgeState();
        }
      }
    } catch (err) {
      console.error('Error connecting to Talk to AI endpoint:', err);
      removeThinkingIndicator(thinkingBubble);
      isAiResponding = false;
      isAiServerOffline = true;

      const subStatus = document.getElementById('talkAiSubStatus');
      if (subStatus) {
        subStatus.innerHTML = '<span style="color:#EF4444; font-weight:600;">⚠️ Server unreachable (waking up or offline)</span>';
      }

      const errReply = '⚠️ <strong>Unable to reach the AI cloud server:</strong> The service may be waking up from cold sleep (takes ~30–40s on Render) or is temporarily offline. Please wait a few seconds and try again.';
      addChatBubble(errReply, 'ai', true, () => handleUserChat(msg));

      if (isVoiceModeActive) {
        speakAiReply("Unable to reach the AI server right now. Please try again in a few seconds.");
      } else {
        updateVoiceBadgeState();
      }
    }
  }

  function addChatBubble(text, sender, isError = false, retryFn = null) {
    if (!chatMessages) return;
    const bubble = document.createElement('div');
    bubble.className = `chat-bubble ${sender}${isError ? ' error-bubble' : ''}`;
    if (isError) {
      bubble.style.border = '1px solid rgba(239, 68, 68, 0.4)';
      bubble.style.backgroundColor = 'rgba(239, 68, 68, 0.1)';
      bubble.style.color = '#FCA5A5';
    }
    bubble.innerHTML = text;

    if (retryFn) {
      const retryBtn = document.createElement('button');
      retryBtn.type = 'button';
      retryBtn.innerHTML = '🔄 Retry Message';
      retryBtn.style.cssText = 'display:inline-block; margin-top:8px; padding:4px 10px; font-size:12px; font-weight:600; color:#fff; background:#C4622D; border:none; border-radius:4px; cursor:pointer;';
      retryBtn.addEventListener('click', (e) => {
        e.preventDefault();
        retryBtn.remove();
        retryFn();
      });
      bubble.appendChild(document.createElement('br'));
      bubble.appendChild(retryBtn);
    }

    chatMessages.appendChild(bubble);
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  function showThinkingIndicator() {
    if (!chatMessages) return null;
    const thinking = document.createElement('div');
    thinking.className = 'chat-bubble thinking';
    thinking.innerHTML = '<span class="dot"></span><span class="dot"></span><span class="dot"></span>';
    chatMessages.appendChild(thinking);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    return thinking;
  }

  function removeThinkingIndicator(bubble) {
    if (bubble && bubble.parentNode) {
      bubble.parentNode.removeChild(bubble);
    }
  }

  // Fully dynamic right-side attribute card renderer
  function renderDynamicDraft(draft, isReady) {
    if (!dynamicDraftContainer) return;

    const attributes = draft.dynamic_attributes || {};
    const hasCore = Boolean(draft.title || draft.category || draft.location || draft.time || draft.verification_secret || draft.contact || Object.keys(attributes).length > 0);

    if (!hasCore) {
      dynamicDraftContainer.innerHTML = `
        <div class="draft-empty-state" id="draftEmptyState">
          <div class="draft-empty-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg></div>
          <div class="draft-empty-text"><strong>Live Attributes Extractor</strong></div>
          <div class="draft-empty-sub">Speak or type your conversation. The AI will dynamically extract and display all item attributes, location, and marks here in real time.</div>
        </div>
      `;
      if (btnDraftSubmit) btnDraftSubmit.disabled = true;
      return;
    }

    let html = '';

    // 1. Title / Item Type Card
    if (draft.title) {
      html += `
        <div class="draft-item updated">
          <div class="draft-item-header">
            <span class="draft-label"><span class="draft-label-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg></span> ${isFoundPage ? 'Found Item' : 'Lost Item'}</span>
            <span class="draft-tag-badge">Identified</span>
          </div>
          <div class="draft-value">${escapeHtml(draft.title)}</div>
        </div>
      `;
    }

    // 2. Category Card
    if (draft.category) {
      html += `
        <div class="draft-item">
          <div class="draft-item-header">
            <span class="draft-label"><span class="draft-label-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg></span> Category</span>
          </div>
          <div class="draft-value">${escapeHtml(draft.category)}</div>
        </div>
      `;
    }

    // 3. Location Card
    if (draft.location) {
      html += `
        <div class="draft-item updated">
          <div class="draft-item-header">
            <span class="draft-label"><span class="draft-label-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg></span> ${isFoundPage ? 'Found Location' : 'Lost Location'}</span>
            <span class="draft-tag-badge">Location</span>
          </div>
          <div class="draft-value">${escapeHtml(draft.location)}</div>
        </div>
      `;
    }

    // 4. Time Card
    if (draft.time) {
      html += `
        <div class="draft-item">
          <div class="draft-item-header">
            <span class="draft-label"><span class="draft-label-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span> Date / Time Context</span>
          </div>
          <div class="draft-value">${escapeHtml(draft.time)}</div>
        </div>
      `;
    }

    // 5. Dynamic Discovered Attributes (e.g. Brand, Dial Color, Strap, Scratches, Engravings, etc.)
    for (const [key, value] of Object.entries(attributes)) {
      if (value && String(value).trim() !== '') {
        const icon = getAttributeIcon(key);
        html += `
          <div class="draft-item updated">
            <div class="draft-item-header">
              <span class="draft-label"><span class="draft-label-icon">${icon}</span> ${escapeHtml(key)}</span>
              <span class="draft-tag-badge">Detail</span>
            </div>
            <div class="draft-value">${escapeHtml(String(value))}</div>
          </div>
        `;
      }
    }

    // 6. Verification Detail Card
    if (draft.verification_secret) {
      html += `
        <div class="draft-item updated">
          <div class="draft-item-header">
            <span class="draft-label"><span class="draft-label-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span> Private Verification Detail</span>
            <span class="draft-tag-badge">Private</span>
          </div>
          <div class="draft-value">${escapeHtml(draft.verification_secret)}</div>
        </div>
      `;
    }

    // 7. Contact Card
    if (draft.contact) {
      html += `
        <div class="draft-item">
          <div class="draft-item-header">
            <span class="draft-label"><span class="draft-label-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg></span> Contact Info</span>
          </div>
          <div class="draft-value">${escapeHtml(draft.contact)}</div>
        </div>
      `;
    }

    // 8. Summary Pill
    if (draft.raw_summary) {
      html += `
        <div class="draft-summary-pill">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px; margin-right:4px;"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg> <em>"${escapeHtml(draft.raw_summary)}"</em>
        </div>
      `;
    }

    dynamicDraftContainer.innerHTML = html;
    dynamicDraftContainer.scrollTop = dynamicDraftContainer.scrollHeight;

    // Enable submit if sufficient details captured
    if (btnDraftSubmit) {
      const ready = isReady || (draft.title && (draft.location || Object.keys(attributes).length > 0));
      btnDraftSubmit.disabled = !ready;
    }
  }

  function getAttributeIcon(key) {
    const k = (key || '').toLowerCase();
    if (k.includes('color') || k.includes('dial')) {
      return '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>';
    }
    if (k.includes('brand') || k.includes('model') || k.includes('make')) {
      return '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>';
    }
    if (k.includes('strap') || k.includes('case') || k.includes('material')) {
      return '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>';
    }
    if (k.includes('scratch') || k.includes('mark') || k.includes('dent') || k.includes('damage') || k.includes('engrav')) {
      return '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>';
    }
    if (k.includes('key') || k.includes('chain') || k.includes('tag')) {
      return '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21 2-2 2m-1.5 1.5L13 10l-4-4-5 5a5.5 5.5 0 1 0 7.78 7.78l5-5-2-2 2.5-2.5 2 2 2-2z"/><circle cx="7.5" cy="16.5" r=".5" fill="currentColor"/></svg>';
    }
    if (k.includes('serial') || k.includes('imei') || k.includes('number')) {
      return '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" x1="4" x2="20" y2="4"/><line x1="4" x1="4" x2="20" y2="20"/><line x1="10" y1="2" x2="6" y2="22"/><line x1="18" y1="2" x2="14" y2="22"/></svg>';
    }
    return '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>';
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // -------------------------------------------------------------
  // Continuous Voice Input & Speech Synthesis Loop
  // -------------------------------------------------------------
  let recognition = null;
  let isRecognitionRunning = false;
  let animFrameId = null;
  let restartTimeoutId = null;

  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (SpeechRecognition) {
    recognition = new SpeechRecognition();
    recognition.continuous = true;
    recognition.interimResults = false;
    recognition.lang = 'en-US';

    recognition.onstart = () => {
      isRecognitionRunning = true;
      updateVoiceBadgeState();
      startVisualizerAnimation();
    };

    recognition.onresult = (event) => {
      const lastResultIndex = event.results.length - 1;
      const transcript = event.results[lastResultIndex][0].transcript.trim();
      if (transcript) {
        if (chatInput) chatInput.value = transcript;
        handleUserChat(transcript);
      }
    };

    recognition.onerror = (event) => {
      console.warn('Speech recognition status:', event.error);
      if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
        stopVoiceMode();
        alert('Microphone access was blocked. Please allow microphone permissions in your browser.');
      }
    };

    recognition.onend = () => {
      isRecognitionRunning = false;
      // If voice mode is still enabled and AI is not speaking/processing, auto-restart
      if (isVoiceModeActive && !isAiResponding && !isAiSpeaking) {
        clearTimeout(restartTimeoutId);
        restartTimeoutId = setTimeout(() => {
          startRecognitionSafe();
        }, 300);
      } else {
        updateVoiceBadgeState();
        if (!isAiSpeaking) stopVisualizerAnimation();
      }
    };
  }

  function toggleVoiceMode() {
    if (!recognition) {
      alert('Speech Recognition is not supported in this browser. Please use Chrome, Edge, or Safari.');
      return;
    }

    if (isVoiceModeActive) {
      stopVoiceMode();
    } else {
      startVoiceMode();
    }
  }

  function startVoiceMode() {
    isVoiceModeActive = true;
    if (voiceToggleLabel) voiceToggleLabel.textContent = 'Stop Voice Mode';
    if (btnVoiceToggle) btnVoiceToggle.classList.add('active');
    startRecognitionSafe();
  }

  function stopVoiceMode() {
    isVoiceModeActive = false;
    clearTimeout(restartTimeoutId);
    if ('speechSynthesis' in window) {
      window.speechSynthesis.cancel();
    }
    isAiSpeaking = false;
    pauseRecognition();
    if (voiceToggleLabel) voiceToggleLabel.textContent = 'Start Voice Mode';
    if (btnVoiceToggle) btnVoiceToggle.classList.remove('active');
    updateVoiceBadgeState();
    stopVisualizerAnimation();
  }

  function startRecognitionSafe() {
    if (!recognition || !isVoiceModeActive || isRecognitionRunning || isAiResponding || isAiSpeaking) return;
    try {
      recognition.start();
    } catch (e) {
      // Ignore if already starting/active
    }
  }

  function pauseRecognition() {
    clearTimeout(restartTimeoutId);
    if (recognition && isRecognitionRunning) {
      try {
        recognition.stop();
      } catch (e) {}
    }
    isRecognitionRunning = false;
  }

  function speakAiReply(text) {
    if (!('speechSynthesis' in window)) {
      if (isVoiceModeActive) startRecognitionSafe();
      return;
    }

    window.speechSynthesis.cancel(); // Cancel any previous speech
    isAiSpeaking = true;
    updateVoiceBadgeState();
    startVisualizerAnimation();

    // Clean markdown/HTML if any from spoken text
    const cleanText = text.replace(/<[^>]*>?/gm, '').replace(/[*_#`~]/g, '');
    const utterance = new SpeechSynthesisUtterance(cleanText);
    utterance.rate = 1.0;
    utterance.pitch = 1.0;

    // Pick pleasant English voice if available
    const voices = window.speechSynthesis.getVoices();
    const naturalVoice = voices.find(v => v.lang.startsWith('en') && (v.name.includes('Natural') || v.name.includes('Google') || v.name.includes('Samantha')));
    if (naturalVoice) utterance.voice = naturalVoice;

    const onSpeechFinished = () => {
      isAiSpeaking = false;
      updateVoiceBadgeState();
      stopVisualizerAnimation();
      if (isVoiceModeActive) {
        // Automatically listen for user's next response!
        setTimeout(() => {
          startRecognitionSafe();
        }, 400);
      }
    };

    utterance.onend = onSpeechFinished;
    utterance.onerror = onSpeechFinished;

    window.speechSynthesis.speak(utterance);
  }

  function updateVoiceBadgeState() {
    if (!voiceBadgeText || !voiceLiveBadge) return;

    if (isAiServerOffline) {
      voiceBadgeText.textContent = 'Server Offline (Waking Up)';
      voiceLiveBadge.classList.remove('active');
    } else if (isAiSpeaking) {
      voiceBadgeText.textContent = 'AI Speaking...';
      voiceLiveBadge.classList.add('active');
    } else if (isAiResponding) {
      voiceBadgeText.textContent = 'AI Thinking...';
      voiceLiveBadge.classList.add('active');
    } else if (isVoiceModeActive && isRecognitionRunning) {
      voiceBadgeText.textContent = 'Listening (Voice Active)';
      voiceLiveBadge.classList.add('active');
    } else if (isVoiceModeActive) {
      voiceBadgeText.textContent = 'Voice Mode Ready';
      voiceLiveBadge.classList.add('active');
    } else {
      voiceBadgeText.textContent = 'Ready to Talk';
      voiceLiveBadge.classList.remove('active');
    }
  }

  if (btnVoiceToggle) btnVoiceToggle.addEventListener('click', toggleVoiceMode);
  if (btnMicInline) btnMicInline.addEventListener('click', toggleVoiceMode);

  function startVisualizerAnimation() {
    if (!voiceVisualizer || animFrameId) return;
    const ctx = voiceVisualizer.getContext('2d');
    const width = voiceVisualizer.width;
    const height = voiceVisualizer.height;

    function renderFrame() {
      ctx.clearRect(0, 0, width, height);
      const numBars = 16;
      const barWidth = 4;
      const gap = (width - numBars * barWidth) / (numBars - 1);

      for (let i = 0; i < numBars; i++) {
        const barHeight = Math.random() * (height - 4) + 4;
        const x = i * (barWidth + gap);
        const y = (height - barHeight) / 2;

        ctx.fillStyle = isAiSpeaking ? '#E27D60' : '#C4622D';
        ctx.beginPath();
        ctx.roundRect(x, y, barWidth, barHeight, 2);
        ctx.fill();
      }

      if (isRecognitionRunning || isAiSpeaking) {
        animFrameId = requestAnimationFrame(renderFrame);
      } else {
        animFrameId = null;
        ctx.clearRect(0, 0, width, height);
      }
    }

    renderFrame();
  }

  function stopVisualizerAnimation() {
    if (animFrameId) {
      cancelAnimationFrame(animFrameId);
      animFrameId = null;
    }
    if (voiceVisualizer) {
      const ctx = voiceVisualizer.getContext('2d');
      ctx.clearRect(0, 0, voiceVisualizer.width, voiceVisualizer.height);
    }
  }

  // -------------------------------------------------------------
  // Submit Final Report from Talk to AI
  // -------------------------------------------------------------
  if (btnDraftSubmit) {
    btnDraftSubmit.addEventListener('click', async () => {
      btnDraftSubmit.disabled = true;
      btnDraftSubmit.textContent = 'Synthesizing Digital DNA...';

      let combinedDescription = currentDraft.raw_summary || `${currentDraft.title || 'Item'} ${isFoundPage ? 'found' : 'lost'} at ${currentDraft.location || 'unknown'}.`;
      
      const attrList = [];
      for (const [k, v] of Object.entries(currentDraft.dynamic_attributes || {})) {
        attrList.push(`${k}: ${v}`);
      }
      if (attrList.length > 0) {
        combinedDescription += ` (${attrList.join(', ')})`;
      }

      const meta = {
        title: currentDraft.title || 'Reported Item',
        where: currentDraft.location || '',
        when: currentDraft.time || '',
        verification: currentDraft.verification_secret || ''
      };

      try {
        let dna = null;
        if (window.FlaskAIService) {
          const aiResult = await window.FlaskAIService.submitReportToFlask({
            description: combinedDescription,
            meta: meta
          });

          if (aiResult && aiResult.digital_dna) {
            dna = aiResult.digital_dna;
          } else {
            dna = window.FlaskAIService.extractClientDna(combinedDescription, meta);
          }
        }

        // If dynamic attributes were found in Talk to AI, merge them into the DNA
        if (dna && currentDraft.dynamic_attributes) {
          dna.attributes = { ...(dna.attributes || {}), ...currentDraft.dynamic_attributes };
        }

        if (typeof window.transitionToReview === 'function') {
          window.transitionToReview(dna, {
            title: currentDraft.title,
            description: combinedDescription,
            where: currentDraft.location,
            when: currentDraft.time,
            verification: currentDraft.verification_secret
          });
        } else {
          showSuccessState(combinedDescription, { ...meta, dna });
        }
      } catch (e) {
        console.error('Error synthesizing DNA from Talk to AI:', e);
        const dna = window.FlaskAIService ? window.FlaskAIService.extractClientDna(combinedDescription, meta) : null;
        if (typeof window.transitionToReview === 'function') {
          window.transitionToReview(dna, {
            title: currentDraft.title,
            description: combinedDescription,
            where: currentDraft.location,
            when: currentDraft.time,
            verification: currentDraft.verification_secret
          });
        } else {
          showSuccessState(combinedDescription, { ...meta, dna });
        }
      } finally {
        btnDraftSubmit.disabled = false;
        btnDraftSubmit.textContent = 'Process & Extract Features';
      }
    });
  }

  function showSuccessState(info, extraMeta = {}) {
    const id = extraMeta.reportId || ((reportType === 'found' ? 'RF-' : 'RL-') + Math.random().toString(36).slice(2, 8).toUpperCase());
    
    // Hide active forms/containers
    const mainForm = document.getElementById(reportType === 'found' ? 'foundForm' : 'lostForm');
    const selector = document.querySelector('.mode-selector-wrapper');
    const aiBanner = document.querySelector('.ai-status-banner');
    
    modeContainers.forEach(c => c.style.display = 'none');
    if (mainForm) mainForm.style.display = 'none';
    if (selector) selector.style.display = 'none';
    if (aiBanner) aiBanner.style.display = 'none';

    const successState = document.getElementById('successState');
    const reportId = document.getElementById('reportId');
    const aiDnaResultsContainer = document.getElementById('aiDnaResults');

    if (reportId) {
      reportId.textContent = 'Report ID ' + id;
    }

    if (window.FlaskAIService && aiDnaResultsContainer) {
      const dna = extraMeta.dna || window.FlaskAIService.extractClientDna(info, extraMeta);
      window.FlaskAIService.renderAiDnaCard(dna, aiDnaResultsContainer, { location: extraMeta.where || draftData?.location || currentDraft?.location });
    }

    if (successState) {
      successState.classList.add('show');
      successState.style.display = 'block';
    }
  }
});

