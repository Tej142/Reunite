document.addEventListener('DOMContentLoaded', () => {
  const isFoundPage = !!document.getElementById('foundForm') || window.location.pathname.includes('found');
  const reportType = isFoundPage ? 'found' : 'lost';

  // 1. Mode Switching Logic
  const modeCards = document.querySelectorAll('.mode-card');
  const modeContainers = document.querySelectorAll('.mode-container');
  const aiStatusText = document.getElementById('aiStatusText');

  const modeDescriptions = {
    mode1: '⚡ <strong>AI Vision & Text Parser:</strong> Upload photos & describe your item directly. AI will parse details and index tags automatically.',
    mode2: '🎯 <strong>Guided Choice Assistant:</strong> Step-by-step interactive questionnaire with AI smart prompts tailored for rapid input.',
    mode3: '⚠️ <strong>Talk to AI (No Backend Connected):</strong> <em>No AI server backend is connected yet. Running in client interactive simulation mode.</em>'
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
      detectedTags.push('📱 Electronics');
    }
    if (lower.includes('wallet') || lower.includes('bag') || lower.includes('purse') || lower.includes('backpack')) {
      detectedTags.push('💼 Wallets & Bags');
    }
    if (lower.includes('key') || lower.includes('fob')) {
      detectedTags.push('🔑 Keys');
    }
    if (lower.includes('black')) detectedTags.push('🎨 Color: Black');
    if (lower.includes('blue')) detectedTags.push('🎨 Color: Blue');
    if (lower.includes('brown') || lower.includes('leather')) detectedTags.push('🎨 Material: Leather');
    if (lower.includes('subway') || lower.includes('park') || lower.includes('train') || lower.includes('street')) {
      detectedTags.push('📍 Location detail detected');
    }

    if (detectedTags.length === 0) {
      detectedTags.push('🏷️ Standard Item', '✨ Auto-Indexed');
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
      btnNext.textContent = currentStep === totalSteps ? 'Submit Report' : 'Next Step →';
    }
  }

  function submitGuidedReport() {
    const whereInput = document.getElementById('guidedWhere');
    const whenInput = document.getElementById('guidedWhen');
    const emailInput = document.getElementById('guidedEmail');
    const verificationInput = document.getElementById('guidedVerification');

    if (whereInput) guidedState.where = whereInput.value;
    if (whenInput) guidedState.when = whenInput.value;
    if (emailInput) guidedState.email = emailInput.value;
    if (verificationInput) guidedState.verification = verificationInput.value;

    const summaryText = `${guidedState.color} ${guidedState.category || 'item'}. Condition: ${guidedState.condition || 'good'}. ${guidedState.brand ? 'Brand: ' + guidedState.brand : ''}`;
    
    showSuccessState(summaryText);
  }

  // 4. Mode 3 Talk to AI Chat Logic
  const chatMessages = document.getElementById('chatMessages');
  const chatInput = document.getElementById('chatInput');
  const btnSendChat = document.getElementById('btnSendChat');
  const promptChips = document.querySelectorAll('.prompt-chip');

  const draftData = {
    title: '',
    category: '',
    description: '',
    location: '',
    time: '',
    verification: '',
    email: ''
  };

  if (btnSendChat && chatInput) {
    btnSendChat.addEventListener('click', handleUserChat);
    chatInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') handleUserChat();
    });
  }

  promptChips.forEach(chip => {
    chip.addEventListener('click', () => {
      const text = chip.textContent.replace(/^"|"$/g, '');
      if (chatInput) chatInput.value = text;
      handleUserChat();
    });
  });

  function handleUserChat() {
    const msg = chatInput ? chatInput.value.trim() : '';
    if (!msg) return;

    addChatBubble(msg, 'user');
    if (chatInput) chatInput.value = '';

    // Simulate AI response & extraction
    setTimeout(() => {
      processAiResponse(msg);
    }, 500);
  }

  function addChatBubble(text, sender) {
    if (!chatMessages) return;
    const bubble = document.createElement('div');
    bubble.className = `chat-bubble ${sender}`;
    bubble.innerHTML = text;
    chatMessages.appendChild(bubble);
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  function processAiResponse(userMsg) {
    const lower = userMsg.toLowerCase();

    // Extract item details
    if (lower.includes('wallet') || lower.includes('phone') || lower.includes('iphone') || lower.includes('keys') || lower.includes('bag') || lower.includes('jacket') || lower.includes('watch')) {
      if (lower.includes('wallet')) { draftData.title = 'Wallet'; draftData.category = 'Wallets & Bags'; }
      else if (lower.includes('phone') || lower.includes('iphone')) { draftData.title = 'Smartphone'; draftData.category = 'Electronics'; }
      else if (lower.includes('keys')) { draftData.title = 'Set of Keys'; draftData.category = 'Keys'; }
      else if (lower.includes('bag')) { draftData.title = 'Bag / Backpack'; draftData.category = 'Wallets & Bags'; }
      else if (lower.includes('jacket')) { draftData.title = 'Jacket'; draftData.category = 'Clothing'; }
      else if (lower.includes('watch')) { draftData.title = 'Watch'; draftData.category = 'Electronics'; }
      
      draftData.description = userMsg;
    }

    if (lower.includes('park') || lower.includes('subway') || lower.includes('street') || lower.includes('cafe') || lower.includes('bus') || lower.includes('station') || lower.includes('train') || lower.includes('library')) {
      draftData.location = userMsg;
    }

    if (lower.includes('today') || lower.includes('yesterday') || lower.includes('pm') || lower.includes('am') || lower.includes('morning') || lower.includes('afternoon') || lower.includes('night')) {
      draftData.time = userMsg;
    }

    if (lower.includes('@') && lower.includes('.')) {
      const emailMatch = userMsg.match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/);
      if (emailMatch) draftData.email = emailMatch[0];
    }

    if (lower.includes('sticker') || lower.includes('code') || lower.includes('photo') || lower.includes('card') || lower.includes('wallpaper') || lower.includes('scratch') || lower.includes('initials')) {
      draftData.verification = userMsg;
    }

    updateDraftCard();

    // Build reply explicitly stating no backend is connected
    const disclaimerHeader = `<div class="backend-notice-badge">⚠️ No AI Backend Connected (Demo Mode)</div>`;
    
    let aiReply = '';
    if (lower === 'hi' || lower === 'hello' || lower === 'hey') {
      aiReply = `${disclaimerHeader}Backend Connect cheyyi ayya!`
    } else if (!draftData.category) {
      aiReply = `${disclaimerHeader}Got it! <em>(Simulated AI Response)</em> Could you tell me what specific item you ${isFoundPage ? 'found' : 'lost'} (e.g., iPhone, Wallet, Keys)?`;
    } else if (!draftData.location) {
      aiReply = `${disclaimerHeader}Recorded <strong>${draftData.title}</strong> in your draft! Where did you ${isFoundPage ? 'find' : 'lose'} it? (e.g., subway, park, cafe)`;
    } else if (!draftData.verification) {
      aiReply = `${disclaimerHeader}Location updated! What is a ${isFoundPage ? 'verification question for the owner' : 'private detail only you know'}?`;
    } else if (!draftData.email) {
      aiReply = `${disclaimerHeader}Almost complete! Please enter your email address so we can register this report draft.`;
    } else {
      aiReply = `${disclaimerHeader}Awesome! All report details have been extracted into your Live Report Draft on the right panel. Click <strong>Submit Report</strong> to finish!`;
    }

    addChatBubble(aiReply, 'ai');
  }

  function updateDraftCard() {
    const elTitle = document.getElementById('draftTitleVal');
    const elCat = document.getElementById('draftCatVal');
    const elDesc = document.getElementById('draftDescVal');
    const elLoc = document.getElementById('draftLocVal');
    const elVerif = document.getElementById('draftVerifVal');
    const elEmail = document.getElementById('draftEmailVal');
    const btnDraftSubmit = document.getElementById('btnDraftSubmit');

    if (elTitle) updateDraftField(elTitle, draftData.title || draftData.description);
    if (elCat) updateDraftField(elCat, draftData.category);
    if (elDesc) updateDraftField(elDesc, draftData.description);
    if (elLoc) updateDraftField(elLoc, draftData.location);
    if (elVerif) updateDraftField(elVerif, draftData.verification);
    if (elEmail) updateDraftField(elEmail, draftData.email);

    if (btnDraftSubmit) {
      const isReady = (draftData.category || draftData.title) && (draftData.email || draftData.location);
      btnDraftSubmit.disabled = !isReady;
    }
  }

  function updateDraftField(element, value) {
    if (!element) return;
    if (value && value.trim() !== '') {
      element.textContent = value;
      element.classList.remove('empty');
      element.parentElement.classList.add('updated');
    } else {
      element.textContent = 'Not specified yet';
      element.classList.add('empty');
    }
  }

  const btnDraftSubmit = document.getElementById('btnDraftSubmit');
  if (btnDraftSubmit) {
    btnDraftSubmit.addEventListener('click', () => {
      showSuccessState('Talk to AI report auto-extracted and submitted.');
    });
  }

  function showSuccessState(info) {
    const id = (reportType === 'found' ? 'RF-' : 'RL-') + Math.random().toString(36).slice(2, 8).toUpperCase();
    
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

    if (reportId) {
      reportId.textContent = 'Report ID ' + id;
    }
    if (successState) {
      successState.classList.add('show');
      successState.style.display = 'block';
    }
  }
});
