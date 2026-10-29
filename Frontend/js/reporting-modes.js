document.addEventListener('DOMContentLoaded', () => {
  const isFoundPage = !!document.getElementById('foundForm') || window.location.pathname.includes('found');
  const reportType = isFoundPage ? 'found' : 'lost';

  // 1. Mode Switching Logic
  const modeCards = document.querySelectorAll('.mode-card');
  const modeContainers = document.querySelectorAll('.mode-container');
  const aiStatusText = document.getElementById('aiStatusText');

  const modeDescriptions = {
    mode1: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> <strong>AI Vision &amp; Text Parser:</strong> Upload photos &amp; describe your item directly. AI extracts traits automatically.',
    mode2: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> <strong>Instant Choice:</strong> Dynamic AI questionnaire tailored specifically to your item category.',
    mode3: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg> <strong>Talk to AI Live Copilot:</strong> Speak or type details naturally — AI drafts your report in real time.'
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
          container.style.display = 'block';
        } else {
          container.classList.remove('active');
          container.style.display = 'none';
        }
      });

      // Update AI Status Banner
      if (aiStatusText && modeDescriptions[selectedMode]) {
        aiStatusText.innerHTML = modeDescriptions[selectedMode];
      }

      if (selectedMode === 'mode3' && typeof checkAiServerHealth === 'function') {
        checkAiServerHealth(1);
      }
    });
  });

  // If Mode 3 container is already active on initial page load, run health check
  const isMode3Init = document.getElementById('mode3Container')?.classList.contains('active');
  if (isMode3Init && typeof checkAiServerHealth === 'function') {
    checkAiServerHealth(1);
  }

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

  // 3. Mode 2 Instant Choice Wizard Logic (Dynamic & Smart)
  let currentStep = 1;
  const totalSteps = 3;

  const SMART_CLIENT_QUESTIONS = {
    electronics: [
      { id: "device_type", question: "What type of electronic device is this?", type: "choice", choices: ["Smartphone", "Laptop", "Wireless Earbuds", "Smartwatch", "Tablet", "Power Bank", "Headphones", "Scientific Calculator", "Other Device"], required: true },
      { id: "brand", question: "What is the brand / manufacturer?", type: "choice", choices: ["Apple", "Samsung", "Dell", "HP", "Lenovo", "Asus", "Acer", "Boat", "Noise", "Sony", "OnePlus", "Xiaomi / Redmi", "Realme", "Casio", "Other Brand", "I don't know / Not sure"], required: true },
      { id: "model", question: "Specific model name or series (if known)?", type: "text", placeholder: "e.g. iPhone 13, Inspiron 15, Galaxy S21", required: false },
      { id: "color", question: "What is the primary color of the device?", type: "choice", choices: ["Black", "Space Grey / Silver", "White", "Midnight Blue", "Gold", "Red", "Green", "Other"], required: true },
      { id: "screen_condition", question: "Physical condition of the item?", type: "choice", choices: ["Like New / Mint", "Good with minor scuffs", "Worn / Scratched", "Cracked screen / Damaged"], required: false },
      { id: "case_cover", question: "Was the device in a protective case, sleeve, or pouch?", type: "text", placeholder: "e.g. Matte black silicone case, transparent cover", required: false },
      { id: "lock_screen_wallpaper", question: "Private Verification: Lock screen wallpaper, stickers, or unique markings?", type: "text", placeholder: "e.g. Anime wallpaper, dog photo, initial sticker on back", required: false, private: true }
    ],
    wallets: [
      { id: "wallet_type", question: "What type of wallet, purse, or bag is this?", type: "choice", choices: ["Bifold Wallet", "Trifold Wallet", "Cardholder", "Purse / Handbag", "College Backpack", "Sling / Crossbody Pouch"], required: true },
      { id: "brand", question: "What brand is it?", type: "choice", choices: ["Wildcraft", "Woodland", "Fastrack", "Tommy Hilfiger", "Titan", "American Tourister", "Skybags", "Puma", "Local / Unbranded", "Other"], required: false },
      { id: "material", question: "What material is it made of?", type: "choice", choices: ["Genuine Leather", "Synthetic / Faux Leather", "Canvas / Fabric", "Nylon / Polyester", "Metal"], required: true },
      { id: "color", question: "Primary color of the wallet / bag?", type: "choice", choices: ["Black", "Dark Brown", "Tan / Light Brown", "Navy Blue", "Grey", "Red", "Olive Green", "Patterned"], required: true },
      { id: "distinguishing_marks", question: "Any unique exterior markings, zippers, or logos?", type: "text", placeholder: "e.g. White zipper, college keychain, embossed logo", required: false },
      { id: "private_contents_hint", question: "Private Verification: Non-sensitive card name initial or unique item inside?", type: "text", placeholder: "e.g. Library card with name initial S, old 10 rupee note, bus pass", required: false, private: true }
    ],
    keys: [
      { id: "key_type", question: "What kind of key or key set is this?", type: "choice", choices: ["Bike / Scooter Key", "Car Key with Remote Fob", "Hostel / Room Key", "House / Padlock Key", "Almirah / Drawer Key", "Key Bunch"], required: true },
      { id: "key_count", question: "How many keys are on the ring?", type: "choice", choices: ["Single key (1)", "2 to 3 keys", "4 to 6 keys", "Large bunch (7+ keys)"], required: true },
      { id: "key_brand", question: "Brand or emblem on the key / fob?", type: "choice", choices: ["Honda", "Hero", "Yamaha", "TVS", "Suzuki", "Royal Enfield", "Hyundai", "Maruti Suzuki", "Godrej", "Link", "Yale", "Unmarked / Standard"], required: false },
      { id: "keychain_design", question: "Keychain, lanyard, or accessory attached?", type: "choice", choices: ["Metal ring only (No keychain)", "Fabric / Campus Lanyard", "Rubber / Figurine Keychain", "Leather Fob", "Carabiner Clip", "Bottle Opener"], required: true },
      { id: "unique_key_mark", question: "Private Verification: Unique scratch, colored tape, or ribbon tag?", type: "text", placeholder: "e.g. Red electrical tape near head, bent tip, specific keychain engraving", required: false, private: true }
    ],
    clothing: [
      { id: "clothing_type", question: "What type of clothing or wearable item?", type: "choice", choices: ["Hoodie / Sweatshirt", "Jacket / Windbreaker", "Cap / Beanie", "T-Shirt / Shirt", "Shoes / Sneakers", "Wristwatch", "Glasses / Sunglasses", "Scarf"], required: true },
      { id: "brand", question: "What brand is it?", type: "choice", choices: ["Nike", "Adidas", "Puma", "Zara", "Decathlon", "Levi's", "H&M", "Fastrack", "Casio", "Other Brand", "Unbranded"], required: false },
      { id: "color", question: "Primary color or pattern?", type: "choice", choices: ["Black", "Navy Blue", "Grey", "White", "Red", "Green", "Maroon", "Striped / Checkered"], required: true },
      { id: "clothing_size", question: "Approximate size?", type: "choice", choices: ["S", "M", "L", "XL", "XXL", "Free Size / Adjustable", "Shoe Size"], required: false },
      { id: "unique_wear_mark", question: "Private Verification: Interior tag name, micro-tear, or unique badge?", type: "text", placeholder: "e.g. Stitched patch on left pocket, tailor name written on wash tag", required: false, private: true }
    ],
    documents: [
      { id: "document_type", question: "What type of document or academic record?", type: "choice", choices: ["College ID Card", "Hall Ticket / Exam Pass", "Library Card", "Govt ID (Aadhaar / DL)", "Marks Memo / Certificate", "Lab Manual / Project Notebook", "Textbook"], required: true },
      { id: "issuing_institution", question: "Issuing institution or college?", type: "choice", choices: ["Sri Venkateswara Govt Polytechnic, Tirupati", "Govt Polytechnic Pillaripattu, Puttur", "Venkata Perumal", "State Board of Technical Education", "Other Institution"], required: true },
      { id: "department", question: "Academic branch or department?", type: "choice", choices: ["Computer Engineering (CME)", "Electronics & Comm (ECE)", "Electrical & Electronics (EEE)", "Mechanical (ME)", "Civil (CE)", "General Studies"], required: false },
      { id: "cover_color", question: "Color of holder, lanyard, or notebook cover?", type: "choice", choices: ["Blue", "Red", "Transparent / Clear", "Black", "Green", "Yellow", "No cover"], required: false },
      { id: "holder_name_initial", question: "Private Verification: Student name initials or roll number prefix?", type: "text", placeholder: "e.g. First two letters of student name or last 3 digits of PIN", required: false, private: true }
    ],
    other: [
      { id: "item_title", question: "What is the general name of this item?", type: "text", placeholder: "e.g. Water bottle, Calculator, Umbrella, Scientific Tool", required: true },
      { id: "brand", question: "Brand or manufacturer (if visible)?", type: "choice", choices: ["Cello", "Milton", "Fastrack", "Casio", "Lenskart", "Quechua", "Wildcraft", "Unknown / Unbranded", "Other"], required: false },
      { id: "color", question: "Primary color of the item?", type: "choice", choices: ["Black", "Blue", "Silver / Grey", "Red", "Green", "White", "Transparent", "Multi-Color"], required: true },
      { id: "material", question: "What material is it primarily made of?", type: "choice", choices: ["Plastic", "Stainless Steel / Metal", "Glass", "Fabric / Canvas", "Rubber / Silicone", "Wood", "Other"], required: false },
      { id: "condition", question: "Physical condition?", type: "choice", choices: ["Brand New / Mint", "Gently Used", "Worn / Scratched", "Damaged"], required: false },
      { id: "distinguishing_marks", question: "Private Verification: Distinguishing scratches, engravings, or stickers?", type: "text", placeholder: "e.g. Scratch on base, name sticker, custom engraving", required: false, private: true }
    ]
  };

  const instantChoiceState = {
    category: '',
    reportType: reportType,
    questions: [],
    answers: {},
    loadedCategory: null,
    isLoading: false,
    where: '',
    when: '',
    photoFile: null
  };

  const stepDots = document.querySelectorAll('.wizard-step-dot');
  const wizardSteps = document.querySelectorAll('.wizard-step-content');
  const btnPrev = document.getElementById('btnWizardPrev');
  const btnNext = document.getElementById('btnWizardNext');
  const dynamicLoadingEl = document.getElementById('dynamicQuestionsLoading');
  const dynamicContainerEl = document.getElementById('dynamicQuestionsContainer');
  const step2SubTitleEl = document.getElementById('step2SubTitle');

  function formatCategoryName(cat) {
    if (!cat) return 'General Item';
    const c = cat.toLowerCase();
    if (c === 'electronics') return 'Electronics & Gadgets';
    if (c === 'wallets') return 'Wallets & Bags';
    if (c === 'keys') return 'Keys';
    if (c === 'clothing') return 'Clothing & Wearables';
    if (c === 'documents') return 'Documents & IDs';
    if (c === 'other') return 'General Item';
    return cat.charAt(0).toUpperCase() + cat.slice(1);
  }

  // Instant synchronous question lookup & rendering (0ms delay)
  function getCategoryQuestions(categoryKey) {
    if (!categoryKey) return SMART_CLIENT_QUESTIONS['other'];
    const cleanCat = categoryKey.toLowerCase().trim();
    for (const k of Object.keys(SMART_CLIENT_QUESTIONS)) {
      if (cleanCat.includes(k) || k.includes(cleanCat)) {
        return SMART_CLIENT_QUESTIONS[k];
      }
    }
    return SMART_CLIENT_QUESTIONS['other'];
  }

  function renderCategoryQuestionsInstant(categoryKey) {
    if (!categoryKey) return;
    const cleanCat = categoryKey.toLowerCase().trim();

    if (step2SubTitleEl) {
      step2SubTitleEl.textContent = `Smart questions tailored specifically for ${formatCategoryName(cleanCat)} (${reportType === 'found' ? 'Found' : 'Lost'}).`;
    }

    // If category changed, clear previous category-specific answers
    if (instantChoiceState.loadedCategory && instantChoiceState.loadedCategory !== cleanCat) {
      const preservedWhere = instantChoiceState.where;
      const preservedWhen = instantChoiceState.when;
      const preservedPhoto = instantChoiceState.photoFile;
      instantChoiceState.answers = {};
      instantChoiceState.where = preservedWhere;
      instantChoiceState.when = preservedWhen;
      instantChoiceState.photoFile = preservedPhoto;
    }

    const questions = getCategoryQuestions(cleanCat);
    instantChoiceState.questions = questions;
    instantChoiceState.loadedCategory = cleanCat;
    instantChoiceState.isLoading = false;

    renderDynamicQuestions(questions);

    if (dynamicLoadingEl) dynamicLoadingEl.style.display = 'none';
    if (dynamicContainerEl) dynamicContainerEl.style.display = 'flex';
  }

  // Dynamically render question cards
  function renderDynamicQuestions(questions) {
    if (!dynamicContainerEl) return;
    dynamicContainerEl.innerHTML = '';

    questions.forEach((q) => {
      const card = document.createElement('div');
      card.className = `dynamic-question-card ${q.private ? 'is-private' : ''}`;
      card.setAttribute('data-qid', q.id);

      const header = document.createElement('div');
      header.className = 'dynamic-question-header';

      const title = document.createElement('div');
      title.className = 'dynamic-question-title';
      title.innerHTML = `${escapeHtml(q.question)} ${q.required ? '<span style="color:#ef4444; margin-left:3px;" title="Required">*</span>' : ''}`;
      header.appendChild(title);

      if (q.private) {
        const badge = document.createElement('div');
        badge.className = 'dynamic-question-badge-private';
        badge.innerHTML = '🔒 Private — Claim Verification Only';
        header.appendChild(badge);
      }

      card.appendChild(header);

      if (q.private) {
        const hint = document.createElement('div');
        hint.style.fontSize = '0.78rem';
        hint.style.color = 'var(--muted, #78716C)';
        hint.style.marginBottom = '0.65rem';
        hint.textContent = 'This detail is shielded and will only be used to verify the genuine owner during claim requests.';
        card.appendChild(hint);
      }

      // Input Controls
      if (q.type === 'choice' || (Array.isArray(q.choices) && q.choices.length > 0)) {
        const pillsGroup = document.createElement('div');
        pillsGroup.className = 'pills-group';
        pillsGroup.setAttribute('data-group', q.id);

        const currentAns = instantChoiceState.answers[q.id];

        // Other text input
        const otherInput = document.createElement('input');
        otherInput.type = 'text';
        otherInput.className = 'dynamic-question-input-text dynamic-other-input';
        otherInput.placeholder = 'Please specify custom ' + (q.id.replace(/_/g, ' ') || 'detail') + '...';
        otherInput.style.display = 'none';
        otherInput.style.marginTop = '0.65rem';

        q.choices.forEach(choice => {
          const pill = document.createElement('span');
          pill.className = 'choice-pill';
          pill.textContent = choice;
          pill.setAttribute('data-value', choice);

          if (currentAns && (currentAns === choice || (choice.toLowerCase().includes('other') && !q.choices.includes(currentAns)))) {
            pill.classList.add('selected');
            if (choice.toLowerCase().includes('other')) {
              otherInput.style.display = 'block';
              otherInput.value = currentAns !== choice ? currentAns : '';
            }
          }

          pill.addEventListener('click', () => {
            pillsGroup.querySelectorAll('.choice-pill').forEach(p => p.classList.remove('selected'));
            pill.classList.add('selected');
            card.classList.remove('error-pulse');

            if (choice.toLowerCase().includes('other')) {
              otherInput.style.display = 'block';
              otherInput.focus();
              instantChoiceState.answers[q.id] = otherInput.value.trim() || choice;
            } else {
              otherInput.style.display = 'none';
              instantChoiceState.answers[q.id] = choice;
            }
          });

          pillsGroup.appendChild(pill);
        });

        otherInput.addEventListener('input', (e) => {
          instantChoiceState.answers[q.id] = e.target.value.trim() || 'Other';
          card.classList.remove('error-pulse');
        });

        card.appendChild(pillsGroup);
        card.appendChild(otherInput);

      } else if (q.type === 'yes_no') {
        const pillsGroup = document.createElement('div');
        pillsGroup.className = 'pills-group';
        pillsGroup.setAttribute('data-group', q.id);

        const currentAns = instantChoiceState.answers[q.id];
        const yesNoOptions = ['Yes', 'No', 'Not sure'];

        yesNoOptions.forEach(opt => {
          const pill = document.createElement('span');
          pill.className = 'choice-pill';
          pill.textContent = opt;
          pill.setAttribute('data-value', opt);

          if (currentAns === opt) {
            pill.classList.add('selected');
          }

          pill.addEventListener('click', () => {
            pillsGroup.querySelectorAll('.choice-pill').forEach(p => p.classList.remove('selected'));
            pill.classList.add('selected');
            instantChoiceState.answers[q.id] = opt;
            card.classList.remove('error-pulse');
          });

          pillsGroup.appendChild(pill);
        });

        card.appendChild(pillsGroup);

      } else {
        // Open text input
        const textInput = document.createElement('input');
        textInput.type = 'text';
        textInput.className = 'dynamic-question-input-text';
        textInput.setAttribute('data-qid', q.id);
        textInput.placeholder = q.placeholder || (q.private ? 'e.g. Secret marking, wallpaper, or unique scratch...' : 'Type details here...');
        textInput.value = instantChoiceState.answers[q.id] || '';

        textInput.addEventListener('input', (e) => {
          instantChoiceState.answers[q.id] = e.target.value;
          if (e.target.value.trim()) {
            card.classList.remove('error-pulse');
          }
        });

        card.appendChild(textInput);
      }

      dynamicContainerEl.appendChild(card);
    });
  }

  // Category Option Tiles click in Step 1
  document.querySelectorAll('.option-tile').forEach(tile => {
    tile.addEventListener('click', () => {
      const parent = tile.parentElement;
      parent.querySelectorAll('.option-tile').forEach(t => t.classList.remove('selected'));
      tile.classList.add('selected');
      const val = tile.getAttribute('data-value') || tile.querySelector('.option-tile-label').textContent;
      instantChoiceState.category = val.toLowerCase().trim();
      
      const catSelect = document.getElementById('category');
      if (catSelect && instantChoiceState.category) {
        catSelect.value = instantChoiceState.category;
      }

      // Render questions immediately with zero delay
      renderCategoryQuestionsInstant(instantChoiceState.category);
    });
  });

  // Wizard Navigation
  if (btnNext) {
    btnNext.addEventListener('click', () => {
      if (currentStep === 1) {
        if (!instantChoiceState.category) {
          if (window.ReuniteToast) {
            window.ReuniteToast.warning('Category Required', 'Please choose an item category to continue.');
          } else {
            alert('Please select an item category to continue.');
          }
          const grid = document.querySelector('.option-tiles-grid');
          if (grid) {
            grid.style.transform = 'scale(1.02)';
            setTimeout(() => { grid.style.transform = 'scale(1)'; }, 250);
          }
          return;
        }

        // Ensure questions are rendered for the selected category
        if (instantChoiceState.loadedCategory !== instantChoiceState.category || instantChoiceState.questions.length === 0) {
          renderCategoryQuestionsInstant(instantChoiceState.category);
        }

        currentStep = 2;
        updateWizardUI();

      } else if (currentStep === 2) {
        // Validate required questions
        const missing = (instantChoiceState.questions || []).filter(q => {
          if (!q.required) return false;
          const val = instantChoiceState.answers[q.id];
          return val === undefined || val === null || !String(val).trim();
        });

        if (missing.length > 0) {
          const firstMissing = missing[0];
          const card = dynamicContainerEl ? dynamicContainerEl.querySelector(`[data-qid="${firstMissing.id}"]`) : null;
          if (card) {
            card.classList.add('error-pulse');
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => card.classList.remove('error-pulse'), 3000);
          }

          const qLabel = firstMissing.question.replace(/\*/g, '').trim();
          if (window.ReuniteToast) {
            window.ReuniteToast.warning('Answer Required', `Please answer: "${qLabel}"`);
          } else {
            alert(`Please answer required question: "${qLabel}"`);
          }
          return;
        }

        currentStep = 3;
        updateWizardUI();

        const whereInput = document.getElementById('guidedWhere');
        if (whereInput) {
          setTimeout(() => whereInput.focus(), 150);
        }

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
      btnPrev.style.display = currentStep === 1 ? 'none' : 'inline-flex';
    }

    if (btnNext) {
      btnNext.textContent = currentStep === totalSteps ? 'Process & Extract Features' : 'Next Step →';
    }

    const mode2Card = document.querySelector('.guided-wizard-card') || document.getElementById('mode2Container');
    if (mode2Card) {
      mode2Card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }

  // Guided photo attachment setup
  const photoZone = document.getElementById('instantChoicePhotoZone');
  const photoInput = document.getElementById('guidedPhotoInput');
  const photoPrompt = document.getElementById('guidedPhotoPrompt');
  const photoPreview = document.getElementById('guidedPhotoPreview');
  const photoThumb = document.getElementById('guidedPhotoThumb');
  const photoName = document.getElementById('guidedPhotoName');
  const btnRemovePhoto = document.getElementById('btnRemoveGuidedPhoto');

  if (photoZone && photoInput) {
    photoZone.addEventListener('click', (e) => {
      if (e.target === btnRemovePhoto || (btnRemovePhoto && btnRemovePhoto.contains(e.target))) return;
      photoInput.click();
    });

    photoInput.addEventListener('change', (e) => {
      const file = e.target.files && e.target.files[0];
      if (!file) return;

      instantChoiceState.photoFile = file;
      if (photoName) photoName.textContent = file.name;

      const reader = new FileReader();
      reader.onload = (re) => {
        if (photoThumb) photoThumb.src = re.target.result;
        if (photoPrompt) photoPrompt.style.display = 'none';
        if (photoPreview) photoPreview.style.display = 'flex';
      };
      reader.readAsDataURL(file);
    });

    if (btnRemovePhoto) {
      btnRemovePhoto.addEventListener('click', (e) => {
        e.stopPropagation();
        instantChoiceState.photoFile = null;
        photoInput.value = '';
        if (photoThumb) photoThumb.src = '';
        if (photoPreview) photoPreview.style.display = 'none';
        if (photoPrompt) photoPrompt.style.display = 'flex';
      });
    }
  }

  // Synthesize answers into canonical Digital DNA & review card
  async function submitGuidedReport() {
    const whereInput = document.getElementById('guidedWhere');
    const whenInput = document.getElementById('guidedWhen');

    if (whereInput) instantChoiceState.where = whereInput.value.trim();
    if (whenInput) instantChoiceState.when = whenInput.value.trim();

    // Check location
    if (!instantChoiceState.where) {
      if (whereInput) {
        whereInput.focus();
        whereInput.style.borderColor = '#ef4444';
        whereInput.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.2)';
        setTimeout(() => {
          whereInput.style.borderColor = '';
          whereInput.style.boxShadow = '';
        }, 3000);
      }
      if (window.ReuniteToast) {
        window.ReuniteToast.warning('Location Required', `Please enter where the item was ${reportType === 'found' ? 'found' : 'lost'} (e.g. Library, IT Lab, Campus).`);
      } else {
        alert(`Please enter where the item was ${reportType === 'found' ? 'found' : 'lost'}.`);
      }
      return;
    }

    if (!instantChoiceState.when) {
      instantChoiceState.when = 'Recent / Today';
    }

    // Harvest answers
    const answers = { ...instantChoiceState.answers };
    const category = instantChoiceState.category || 'other';

    const color = answers['color'] || answers['cover_color'] || '';
    const brand = answers['brand'] || answers['device_brand'] || answers['key_brand'] || '';
    const model = answers['model'] || '';
    const condition = answers['screen_condition'] || answers['condition'] || 'Good';
    const subType = answers['device_type'] || answers['wallet_type'] || answers['key_type'] || answers['clothing_type'] || answers['document_type'] || answers['item_title'] || formatCategoryName(category);

    const titleParts = [color, brand, model || subType].filter(Boolean);
    const itemTitle = titleParts.length > 0 ? titleParts.join(' ') : formatCategoryName(category);

    // Build human readable summary
    const summaryLines = [
      `${itemTitle} (${formatCategoryName(category)}).`,
      brand ? `Brand: ${brand}.` : '',
      model ? `Model: ${model}.` : '',
      color ? `Color: ${color}.` : '',
      condition ? `Condition: ${condition}.` : '',
      `Reported Location: ${instantChoiceState.where}.`,
      `Date / Time: ${instantChoiceState.when}.`
    ].filter(Boolean);

    // Add extra answers as traits
    Object.entries(answers).forEach(([k, v]) => {
      if (['color', 'cover_color', 'brand', 'device_brand', 'key_brand', 'model', 'screen_condition', 'condition', 'where', 'when'].includes(k)) return;
      if (v && typeof v === 'string' && v.trim() && v.toLowerCase() !== 'unknown' && v.toLowerCase() !== 'not sure') {
        const cleanKey = k.replace(/_/g, ' ');
        summaryLines.push(`${cleanKey.charAt(0).toUpperCase() + cleanKey.slice(1)}: ${v.trim()}.`);
      }
    });

    const summaryText = summaryLines.join(' ');

    if (btnNext && window.setButtonLoading) {
      window.setButtonLoading(btnNext, true, 'Synthesizing Digital DNA...');
    }

    const thinkingContainer = document.querySelector('.guided-wizard-card') || document.getElementById('mode2Container');
    const thinkingHandle = window.Orbi && thinkingContainer ? window.Orbi.showThinking(thinkingContainer, { caption: 'Synthesizing Digital DNA...', size: 44, delay: 100 }) : null;

    try {
      // Extract visible search tags & private verification keys
      const visibleFeatures = [];
      let privMarks = '';
      let privSecret = '';
      let privSerial = answers['serial'] || '';

      (instantChoiceState.questions || []).forEach(q => {
        const val = answers[q.id];
        if (!val || typeof val !== 'string' || !val.trim() || val.toLowerCase() === 'unknown' || val.toLowerCase() === 'not sure') return;

        if (q.private) {
          if (/serial|imei|id_number/i.test(q.id)) {
            privSerial = privSerial ? `${privSerial}; ${val.trim()}` : val.trim();
          } else if (/wallpaper|mark|scratch|sticker|badge|initial/i.test(q.id)) {
            privMarks = privMarks ? `${privMarks}; ${val.trim()}` : val.trim();
          } else {
            privSecret = privSecret ? `${privSecret}; ${val.trim()}` : val.trim();
          }
        } else if (!['brand', 'model', 'color', 'device_type', 'wallet_type', 'key_type', 'clothing_type', 'document_type', 'item_title'].includes(q.id)) {
          visibleFeatures.push(`${q.question.replace(/\*/g, '').replace(/\?/g, '').trim()}: ${val.trim()}`);
        }
      });

      if (visibleFeatures.length === 0) {
        if (subType && subType !== formatCategoryName(category)) visibleFeatures.push(subType);
        if (color) visibleFeatures.push(`Color: ${color}`);
        if (brand) visibleFeatures.push(`Brand: ${brand}`);
      }

      // Canonical Digital DNA structure
      const dna = {
        object_type: itemTitle,
        category: formatCategoryName(category),
        attributes: {
          Brand: brand || 'Unbranded',
          Model: model || subType || 'Standard',
          Color: color || 'Neutral',
          Material: answers['material'] || 'Standard',
          Condition: condition,
          "Date / Time": instantChoiceState.when,
          "Reported Location": instantChoiceState.where,
          "Serial Number": privSerial,
          "Distinguishing Marks": privMarks,
          "Private Verification Keys": privSecret
        },
        location: instantChoiceState.where,
        visible_features: visibleFeatures,
        private_verification: {
          serial: privSerial,
          marks: privMarks,
          secret_contents: privSecret
        }
      };

      // Brief 250ms pleasant synthesis pause so the user perceives AI intelligence
      await new Promise(r => setTimeout(r, 250));

      // Hand off to AI Review Card
      if (typeof window.transitionToReview === 'function') {
        window.transitionToReview(dna, {
          title: itemTitle,
          description: summaryText,
          where: instantChoiceState.where,
          when: instantChoiceState.when,
          verification: privMarks || privSecret || privSerial || '',
          sourceMode: 'mode2',
          imageFile: instantChoiceState.photoFile || null
        });
      } else {
        showSuccessState(summaryText, {
          title: itemTitle,
          category: formatCategoryName(category),
          where: instantChoiceState.where,
          when: instantChoiceState.when,
          dna
        });
      }

    } catch (e) {
      console.error('[InstantChoice] Error synthesizing DNA:', e);
      if (window.ReuniteToast) {
        window.ReuniteToast.error('Synthesis Issue', 'Could not synthesize report traits. Please try again.');
      }
    } finally {
      if (thinkingHandle) thinkingHandle.dismiss();
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
  const voiceLiveBar = document.getElementById('voiceLiveBar');
  const btnMicInline = document.getElementById('btnMicInline');
  const voiceLiveBadge = document.getElementById('voiceLiveBadge');
  const voiceBadgeText = document.getElementById('voiceBadgeText');
  const voiceVisualizer = document.getElementById('voiceVisualizer');
  const voiceToggleLabel = document.getElementById('voiceToggleLabel');
  const voiceLiveText = document.getElementById('voiceLiveText');
  const btnVoiceSend = document.getElementById('btnVoiceSend');

  if (voiceLiveBar) {
    voiceLiveBar.style.display = 'none';
    voiceLiveBar.classList.remove('active');
  }

  // Photo Upload & Lightbox Elements
  const btnAttachPhoto = document.getElementById('btnAttachPhoto');
  const chatPhotoInput = document.getElementById('chatPhotoInput');
  const chatDropOverlay = document.getElementById('chatDropOverlay');
  const chatAttachmentBar = document.getElementById('chatAttachmentBar');
  const attachmentThumbImg = document.getElementById('attachmentThumbImg');
  const attachmentProgressBar = document.getElementById('attachmentProgressBar');
  const attachmentFileName = document.getElementById('attachmentFileName');
  const attachmentFileSize = document.getElementById('attachmentFileSize');
  const attachmentCounterBadge = document.getElementById('attachmentCounterBadge');
  const btnRemoveAttachment = document.getElementById('btnRemoveAttachment');
  const chatSuggestions = document.getElementById('chatSuggestions');
  const draftPhotosSection = document.getElementById('draftPhotosSection');
  const draftPhotosList = document.getElementById('draftPhotosList');

  const chatLightboxModal = document.getElementById('chatLightboxModal');
  const chatLightboxImg = document.getElementById('chatLightboxImg');
  const chatLightboxCaption = document.getElementById('chatLightboxCaption');
  const chatLightboxClose = document.getElementById('chatLightboxClose');

  let chatHistory = [];
  const VoiceState = {
    OFF: 'OFF',
    LISTENING: 'LISTENING',
    WORKING: 'WORKING',
    SPEAKING: 'SPEAKING'
  };
  let currentVoiceState = VoiceState.OFF;
  let isAiResponding = false;
  let isVoiceModeActive = false;
  let isAiSpeaking = false;
  let attachedPhotos = []; // [{ file, dataUrl, serverUrl, name, size }]
  let currentStagedPhoto = null; // { file, dataUrl, name, size }

  let currentDraft = {
    title: '',
    category: '',
    location: '',
    time: '',
    dynamic_attributes: {},
    verification_secret: '',
    contact: '',
    raw_summary: '',
    from_photo_keys: []
  };

  // -------------------------------------------------------------
  // Lightbox Modal Controls
  // -------------------------------------------------------------
  function openLightbox(src, caption) {
    if (!chatLightboxModal || !chatLightboxImg) return;
    chatLightboxImg.src = src;
    if (chatLightboxCaption) {
      chatLightboxCaption.textContent = caption || 'Uploaded Photo';
    }
    chatLightboxModal.style.display = 'flex';
    chatLightboxModal.setAttribute('aria-hidden', 'false');
    if (chatLightboxClose) chatLightboxClose.focus();
  }

  function closeLightbox() {
    if (!chatLightboxModal) return;
    chatLightboxModal.style.display = 'none';
    chatLightboxModal.setAttribute('aria-hidden', 'true');
  }

  if (chatLightboxClose) {
    chatLightboxClose.addEventListener('click', closeLightbox);
  }
  if (chatLightboxModal) {
    chatLightboxModal.addEventListener('click', (e) => {
      if (e.target === chatLightboxModal) closeLightbox();
    });
  }
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && chatLightboxModal && chatLightboxModal.style.display === 'flex') {
      closeLightbox();
    }
  });

  // -------------------------------------------------------------
  // Client-Side Image Validation, Resizing & Compression (Max 1600px, 8MB)
  // -------------------------------------------------------------
  function formatBytes(bytes) {
    if (!bytes || bytes <= 0) return '0 B';
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + sizes[i];
  }

  async function processAndCompressImage(file) {
    const validMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    const ext = (file.name.split('.').pop() || '').toLowerCase();
    const validExts = ['jpg', 'jpeg', 'png', 'webp'];

    if (!validMimes.includes(file.type.toLowerCase()) && !validExts.includes(ext)) {
      throw new Error('Please choose a JPG, PNG, or WebP photo.');
    }

    const maxBytes = 8 * 1024 * 1024; // 8 MB limit
    if (file.size > maxBytes) {
      throw new Error('Photo size exceeds 8 MB. Please choose a smaller photo.');
    }

    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onerror = () => reject(new Error('Could not read image file.'));
      reader.onload = (e) => {
        const img = new Image();
        img.onerror = () => reject(new Error('Invalid image file data.'));
        img.onload = () => {
          const MAX_DIM = 1600;
          let width = img.width;
          let height = img.height;

          if (width > MAX_DIM || height > MAX_DIM) {
            if (width > height) {
              height = Math.round((height * MAX_DIM) / width);
              width = MAX_DIM;
            } else {
              width = Math.round((width * MAX_DIM) / height);
              height = MAX_DIM;
            }
          }

          const canvas = document.createElement('canvas');
          canvas.width = width;
          canvas.height = height;
          const ctx = canvas.getContext('2d');
          ctx.drawImage(img, 0, 0, width, height);

          const isPng = file.type === 'image/png' || ext === 'png';
          const outType = isPng ? 'image/png' : 'image/jpeg';
          const outQuality = isPng ? 0.92 : 0.88;

          canvas.toBlob((blob) => {
            if (!blob) {
              resolve({ file, dataUrl: e.target.result });
              return;
            }
            const cleanName = file.name.replace(/\.[^.]+$/, '') + (isPng ? '.png' : '.jpg');
            const compressedFile = new File([blob], cleanName, {
              type: outType,
              lastModified: Date.now()
            });
            const dataUrl = canvas.toDataURL(outType, outQuality);
            resolve({ file: compressedFile, dataUrl });
          }, outType, outQuality);
        };
        img.src = e.target.result;
      };
      reader.readAsDataURL(file);
    });
  }

  // -------------------------------------------------------------
  // Photo Staging Chip & Input Handlers
  // -------------------------------------------------------------
  async function stagePhoto(file) {
    if (!file) return;

    if (attachedPhotos.length >= 3) {
      const msg = 'Maximum of 3 photos reached for this report.';
      if (window.ReuniteToast) window.ReuniteToast.info('Photo Limit', msg);
      else alert(msg);
      return;
    }

    try {
      if (attachmentProgressBar) attachmentProgressBar.style.width = '25%';

      const { file: processedFile, dataUrl } = await processAndCompressImage(file);
      currentStagedPhoto = {
        file: processedFile,
        dataUrl: dataUrl,
        name: file.name,
        size: formatBytes(processedFile.size)
      };

      if (chatAttachmentBar) chatAttachmentBar.style.display = 'flex';
      if (attachmentThumbImg) attachmentThumbImg.src = dataUrl;
      if (attachmentFileName) attachmentFileName.textContent = file.name;
      if (attachmentFileSize) attachmentFileSize.textContent = currentStagedPhoto.size;
      if (attachmentCounterBadge) {
        attachmentCounterBadge.textContent = `${attachedPhotos.length + 1}/3 photos`;
      }
      if (attachmentProgressBar) {
        attachmentProgressBar.style.width = '100%';
        setTimeout(() => {
          if (attachmentProgressBar) attachmentProgressBar.style.width = '0%';
        }, 300);
      }

      if (btnAttachPhoto) btnAttachPhoto.classList.remove('pulse-highlight');
    } catch (err) {
      console.warn('Photo processing notice:', err);
      if (window.ReuniteToast) window.ReuniteToast.error('Invalid Photo', err.message);
      else alert(err.message);
      clearStagedPhoto();
    }
  }

  function clearStagedPhoto() {
    currentStagedPhoto = null;
    if (chatAttachmentBar) chatAttachmentBar.style.display = 'none';
    if (chatPhotoInput) chatPhotoInput.value = '';
    if (attachmentProgressBar) attachmentProgressBar.style.width = '0%';
  }

  if (btnRemoveAttachment) {
    btnRemoveAttachment.addEventListener('click', clearStagedPhoto);
  }

  if (btnAttachPhoto && chatPhotoInput) {
    btnAttachPhoto.addEventListener('click', () => {
      chatPhotoInput.click();
    });

    chatPhotoInput.addEventListener('change', () => {
      if (chatPhotoInput.files && chatPhotoInput.files[0]) {
        stagePhoto(chatPhotoInput.files[0]);
      }
    });
  }

  // Drag and drop onto chat panel
  const chatPanel = document.querySelector('.chat-panel') || document.getElementById('mode3Container');
  if (chatPanel) {
    ['dragenter', 'dragover'].forEach(eventName => {
      chatPanel.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (chatDropOverlay) chatDropOverlay.classList.add('active');
      });
    });

    ['dragleave', 'dragend'].forEach(eventName => {
      chatPanel.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.relatedTarget && chatPanel.contains(e.relatedTarget)) return;
        if (chatDropOverlay) chatDropOverlay.classList.remove('active');
      });
    });

    chatPanel.addEventListener('drop', (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (chatDropOverlay) chatDropOverlay.classList.remove('active');
      if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
        stagePhoto(e.dataTransfer.files[0]);
      }
    });
  }

  // Paste from clipboard (Ctrl+V)
  window.addEventListener('paste', (e) => {
    const mode3 = document.getElementById('mode3Container');
    if (!mode3 || !mode3.classList.contains('active')) return;

    if (e.clipboardData && e.clipboardData.items) {
      for (const item of e.clipboardData.items) {
        if (item.type && item.type.indexOf('image') !== -1) {
          const file = item.getAsFile();
          if (file) {
            e.preventDefault();
            stagePhoto(file);
            break;
          }
        }
      }
    }
  });

  // -------------------------------------------------------------
  // Chat Actions & Input Bindings
  // -------------------------------------------------------------
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
  let activeFlaskUrl = 'https://reunite-ai-backend.onrender.com';

  function getFlaskEndpoints() {
    if (typeof window !== 'undefined' && window.FLASK_BACKEND_URL) {
      return { primary: window.FLASK_BACKEND_URL, alternate: null };
    }
    const isLocal = typeof window !== 'undefined' && (
      window.location.hostname === 'localhost' ||
      window.location.hostname === '127.0.0.1' ||
      window.location.hostname === ''
    );
    if (isLocal) {
      return {
        primary: 'http://127.0.0.1:5000',
        alternate: 'https://reunite-ai-backend.onrender.com'
      };
    }
    return {
      primary: 'https://reunite-ai-backend.onrender.com',
      alternate: null
    };
  }

  function getFlaskBaseUrl() {
    if (activeFlaskUrl) return activeFlaskUrl;
    return getFlaskEndpoints().primary;
  }

  function checkAiServerHealth() {
    const { primary } = getFlaskEndpoints();
    activeFlaskUrl = primary || 'https://reunite-ai-backend.onrender.com';
    isAiServerOffline = false;

    const subStatus = document.getElementById('talkAiSubStatus');
    if (subStatus) {
      subStatus.innerHTML = '<span class="copilot-live-dot ready"></span> Powered by Google Gemini Live Voice';
    }
    updateVoiceBadgeState();

    // Silent background pre-warm ping
    try {
      fetch(`${activeFlaskUrl}/`, { method: 'GET', mode: 'no-cors', cache: 'no-store' }).catch(() => {});
    } catch (e) {}
  }

  // Client-side fallback turn processor if both Python endpoints are sleeping/unreachable
  function generateClientSideChatTurn(userMsg, photoObj) {
    const text = (userMsg || '').trim();
    const lower = text.toLowerCase();

    let reply = "";

    // 1. Check greeting
    const isGreeting = /^(hi|hello|hey|good morning|good afternoon|good evening)\b/i.test(lower);
    if (isGreeting && !currentDraft.title) {
      reply = isFoundPage
        ? "Hello! I'm your AI Found Item Copilot. What item did you find, and where did you find it?"
        : "Hello! I'm your AI Lost Item Copilot. What item did you lose, and where did you last see it?";
      return {
        success: true,
        reply: reply,
        draft: currentDraft,
        is_ready_to_submit: false
      };
    }

    // 2. Extract item
    let detectedItem = "";
    let detectedCategory = "";
    if (lower.includes('wallet') || lower.includes('purse')) {
      detectedItem = "Leather Wallet";
      detectedCategory = "Wallets & Bags";
    } else if (lower.includes('iphone') || lower.includes('phone') || lower.includes('mobile') || lower.includes('samsung') || lower.includes('pixel')) {
      detectedItem = lower.includes('iphone') ? "iPhone" : "Smartphone";
      detectedCategory = "Electronics";
    } else if (lower.includes('key')) {
      detectedItem = "Keys / Keychain";
      detectedCategory = "Keys & Identification";
    } else if (lower.includes('watch')) {
      detectedItem = "Watch";
      detectedCategory = "Accessories";
    } else if (lower.includes('laptop') || lower.includes('macbook')) {
      detectedItem = "Laptop";
      detectedCategory = "Electronics";
    } else if (lower.includes('bag') || lower.includes('backpack')) {
      detectedItem = "Backpack";
      detectedCategory = "Wallets & Bags";
    } else if (lower.includes('bottle')) {
      detectedItem = "Water Bottle";
      detectedCategory = "Personal Items";
    }

    if (detectedItem && !currentDraft.title) {
      currentDraft.title = detectedItem;
      currentDraft.category = detectedCategory;
    }

    // 3. Location extraction
    const locationMatch = lower.match(/(?:at|in|near|around|on|outside|inside)\s+([a-zA-Z0-9\s]{3,35})(?:yesterday|today|morning|afternoon|evening|\.|\,|$)/i);
    if (locationMatch && locationMatch[1] && !currentDraft.location) {
      currentDraft.location = locationMatch[1].trim();
    }

    // 4. Time extraction
    if (!currentDraft.time) {
      if (lower.includes('yesterday')) currentDraft.time = "Yesterday";
      else if (lower.includes('today')) currentDraft.time = "Today";
      else if (lower.includes('morning')) currentDraft.time = "Morning";
    }

    // 5. Brand & Color extraction with 'I don't know' acceptance
    const knownBrands = ['dell', 'lenovo', 'hp', 'apple', 'samsung', 'asus', 'acer', 'wildcraft', 'titan', 'fastrack'];
    for (const b of knownBrands) {
      if (lower.includes(b) && !currentDraft.brand) {
        currentDraft.brand = b.charAt(0).toUpperCase() + b.slice(1);
        break;
      }
    }
    const knownColors = ['black', 'blue', 'silver', 'grey', 'gray', 'white', 'red', 'green', 'brown', 'yellow', 'gold'];
    for (const c of knownColors) {
      if (lower.includes(c) && !currentDraft.color) {
        currentDraft.color = c.charAt(0).toUpperCase() + c.slice(1);
        break;
      }
    }
    const isUnknown = /(i don\'?t know|dont know|not sure|no idea|unknown|cant tell|can\'?t tell)/i.test(lower);
    if (isUnknown) {
      if (!currentDraft.brand) currentDraft.brand = 'unknown';
      if (!currentDraft.color) currentDraft.color = 'unknown';
    }

    // 6. Generate conversational reply
    if (photoObj) {
      reply = "Thank you for sharing the photo! I've attached it to your report dossier. Could you share where you found/lost it and any unique marks?";
    } else if (!currentDraft.title) {
      reply = isFoundPage
        ? "Could you tell me what specific item you found (e.g. phone, keys, wallet)?"
        : "Could you describe the exact item you lost so I can match it?";
    } else if (!currentDraft.location) {
      reply = `Got it, I noted your ${currentDraft.title}. Where was it ${isFoundPage ? 'found' : 'last seen'}?`;
    } else if (!currentDraft.brand && !currentDraft.color && !isUnknown) {
      reply = `Understood! I've recorded the ${currentDraft.title} at ${currentDraft.location}. What is the brand and color? (If you don't know, just say "I don't know").`;
    } else {
      reply = `All set! I have recorded your ${currentDraft.title} with the details provided. Ready to process your report?`;
    }

    const isReady = Boolean(currentDraft.title && (currentDraft.location || currentDraft.category));

    return {
      success: true,
      reply: reply,
      draft: currentDraft,
      is_ready_to_submit: isReady
    };
  }

  let isChatRequestInFlight = false;

  async function handleUserChat(directMessage, audioBlob = null) {
    if (isChatRequestInFlight) return;
    const msg = directMessage || (chatInput ? chatInput.value.trim() : '');
    const photoToSend = currentStagedPhoto;

    if (!msg && !photoToSend && !audioBlob) return;

    isChatRequestInFlight = true;

    // Immediately transition Voice FSM to WORKING state: hard-stops mic immediately
    if (isVoiceModeActive) {
      setVoiceState(VoiceState.WORKING, 'handleUserChat initiated');
    } else {
      hardStopMic();
    }

    let userBubbleEl = null;

    // Add user bubble (with photo thumbnail if attached, or voice note indicator)
    if (photoToSend) {
      addUserPhotoBubble(photoToSend.dataUrl, msg, photoToSend.name);
      chatHistory.push({ role: 'user', text: msg || 'Attached photo of the item.' });
    } else if (audioBlob && !msg) {
      userBubbleEl = addChatBubble('🎙️ <em>Transcribing voice message with Gemini AI...</em>', 'user');
    } else {
      addChatBubble(msg, 'user');
      chatHistory.push({ role: 'user', text: msg });
    }

    if (chatInput) chatInput.value = '';
    clearStagedPhoto();
    if (chatSuggestions) chatSuggestions.style.display = 'none';
    if (btnVoiceSend) btnVoiceSend.style.display = 'none';

    // Show AI thinking bubble
    const thinkingBubble = showThinkingIndicator(photoToSend ? 'Looking at your photo...' : (audioBlob ? 'Listening to voice message...' : null));
    isAiResponding = true;
    updateDraftSyncStatus(true);

    const { primary, alternate } = getFlaskEndpoints();
    const candidateEndpoints = [activeFlaskUrl || primary];
    if (alternate && !candidateEndpoints.includes(alternate)) {
      candidateEndpoints.push(alternate);
    }

    try {
      let response = null;
      let usedFallback = false;

      async function postToEndpoint(endpointUrl) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 18000); // 18s timeout

        try {
          if (audioBlob) {
            const formData = new FormData();
            formData.append('audio', audioBlob, 'speech.webm');
            if (msg) formData.append('message', msg);
            formData.append('history', JSON.stringify(chatHistory));
            formData.append('report_type', isFoundPage ? 'found' : 'lost');
            formData.append('current_draft', JSON.stringify(currentDraft));
            const existingUrls = attachedPhotos.map(p => p.serverUrl).filter(Boolean);
            if (existingUrls.length > 0) {
              formData.append('image_urls', JSON.stringify(existingUrls));
            }
            const res = await fetch(`${endpointUrl}/report/talk_to_ai/chat`, {
              method: 'POST',
              body: formData,
              signal: controller.signal
            });
            clearTimeout(timeoutId);
            return res;
          } else if (photoToSend) {
            const formData = new FormData();
            formData.append('image', photoToSend.file);
            formData.append('message', msg || 'Here is a photo of the item.');
            formData.append('history', JSON.stringify(chatHistory));
            formData.append('report_type', isFoundPage ? 'found' : 'lost');
            formData.append('current_draft', JSON.stringify(currentDraft));
            const existingUrls = attachedPhotos.map(p => p.serverUrl).filter(Boolean);
            if (existingUrls.length > 0) {
              formData.append('image_urls', JSON.stringify(existingUrls));
            }
            const res = await fetch(`${endpointUrl}/report/talk_to_ai/chat`, {
              method: 'POST',
              body: formData,
              signal: controller.signal
            });
            clearTimeout(timeoutId);
            return res;
          } else {
            const res = await fetch(`${endpointUrl}/report/talk_to_ai/chat`, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                message: msg,
                history: chatHistory,
                report_type: isFoundPage ? 'found' : 'lost',
                current_draft: currentDraft,
                image_urls: attachedPhotos.map(p => p.serverUrl).filter(Boolean)
              }),
              signal: controller.signal
            });
            clearTimeout(timeoutId);
            return res;
          }
        } catch (fetchErr) {
          clearTimeout(timeoutId);
          throw fetchErr;
        }
      }

      for (const ep of candidateEndpoints) {
        try {
          const res = await postToEndpoint(ep);
          if (res.ok) {
            response = res;
            activeFlaskUrl = ep;
            isAiServerOffline = false;
            break;
          } else {
            console.warn(`Talk to AI endpoint ${ep} returned HTTP ${res.status}. Trying next candidate...`);
          }
        } catch (netErr) {
          console.warn(`Talk to AI endpoint ${ep} network error / timeout. Trying next candidate...`, netErr);
        }
      }

      let result = null;
      if (response && response.ok) {
        result = await response.json();
      } else {
        console.warn('All remote AI endpoints failed or waking up. Falling back seamlessly to in-browser AI intake engine.');
        result = generateClientSideChatTurn(msg, photoToSend);
        usedFallback = true;
      }

      removeThinkingIndicator(thinkingBubble);
      isAiResponding = false;
      isChatRequestInFlight = false;
      updateDraftSyncStatus(false);

      const subStatus = document.getElementById('talkAiSubStatus');
      if (subStatus) {
        subStatus.innerHTML = '<span class="copilot-live-dot ready"></span> Powered by Google Gemini Live Voice';
      }

      if (result && result.transcribed_user_message && userBubbleEl) {
        userBubbleEl.innerHTML = `🎙️ ${escapeHtml(result.transcribed_user_message)}`;
        chatHistory.push({ role: 'user', text: result.transcribed_user_message });
      }

      if (result && result.success && result.reply) {
        if (photoToSend) {
          const returnedUrls = result.image_urls || [];
          const serverUrl = returnedUrls.length > 0 ? returnedUrls[returnedUrls.length - 1] : null;
          attachedPhotos.push({
            file: photoToSend.file,
            dataUrl: photoToSend.dataUrl,
            serverUrl: serverUrl,
            name: photoToSend.name
          });
          renderDraftPhotos();
        }

        addChatBubble(result.reply, 'ai');
        chatHistory.push({ role: 'assistant', text: result.reply });

        // Merge attributes tagged "From photo"
        if (Array.isArray(result.from_photo_keys) && result.from_photo_keys.length > 0) {
          currentDraft.from_photo_keys = Array.from(new Set([
            ...(currentDraft.from_photo_keys || []),
            ...result.from_photo_keys
          ]));
        }

        if (result.draft) {
          currentDraft = {
            ...currentDraft,
            ...result.draft,
            from_photo_keys: currentDraft.from_photo_keys || [],
            dynamic_attributes: {
              ...(currentDraft.dynamic_attributes || {}),
              ...(result.draft.dynamic_attributes || {})
            }
          };
          renderDynamicDraft(currentDraft, result.is_ready_to_submit);
        }

        // Handle photo request quick reply chips
        handlePhotoRequestPrompt(result.photo_requested);

        // If Voice Mode is active, speak the reply aloud and resume listening after
        if (isVoiceModeActive && currentVoiceState !== VoiceState.OFF) {
          speakAiReply(result.reply);
        } else {
          updateVoiceBadgeState();
        }
      } else {
        const fallbackReply = (result && result.error) || "I've noted that! Could you tell me more about the item or where you last saw it?";
        addChatBubble(fallbackReply, 'ai');
        if (isVoiceModeActive && currentVoiceState !== VoiceState.OFF) {
          speakAiReply(fallbackReply);
        } else {
          updateVoiceBadgeState();
        }
      }
    } catch (err) {
      console.warn('Network issue reaching Talk to AI endpoint. Falling back smoothly to local intake:', err);
      removeThinkingIndicator(thinkingBubble);
      isAiResponding = false;
      isChatRequestInFlight = false;
      isAiServerOffline = true;
      updateDraftSyncStatus(false);

      const subStatus = document.getElementById('talkAiSubStatus');
      if (subStatus) {
        subStatus.innerHTML = '<span class="copilot-live-dot ready"></span> Powered by Google Gemini Live Voice';
      }

      const fallbackResult = generateClientSideChatTurn(msg, photoToSend);
      if (fallbackResult && fallbackResult.reply) {
        if (photoToSend) {
          attachedPhotos.push({
            file: photoToSend.file,
            dataUrl: photoToSend.dataUrl,
            serverUrl: null,
            name: photoToSend.name
          });
          renderDraftPhotos();
        }
        addChatBubble(fallbackResult.reply, 'ai');
        chatHistory.push({ role: 'assistant', text: fallbackResult.reply });

        if (fallbackResult.draft) {
          currentDraft = {
            ...currentDraft,
            ...fallbackResult.draft
          };
          renderDynamicDraft(currentDraft, fallbackResult.is_ready_to_submit);
        }

        if (isVoiceModeActive && currentVoiceState !== VoiceState.OFF) {
          speakAiReply(fallbackResult.reply);
        } else {
          updateVoiceBadgeState();
        }
      }
    }
  }

  function addUserPhotoBubble(dataUrl, captionText, fileName) {
    if (!chatMessages) return;
    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble user image-bubble';

    const card = document.createElement('div');
    card.className = 'chat-image-card';
    card.title = 'Click to view enlarged photo';

    const img = document.createElement('img');
    img.src = dataUrl;
    img.alt = fileName || 'Uploaded item photo';
    img.addEventListener('click', () => {
      openLightbox(dataUrl, fileName || 'Uploaded Photo');
    });
    card.appendChild(img);
    bubble.appendChild(card);

    if (captionText) {
      const caption = document.createElement('div');
      caption.className = 'chat-image-caption';
      caption.textContent = captionText;
      bubble.appendChild(caption);
    }

    chatMessages.appendChild(bubble);
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  function handlePhotoRequestPrompt(isRequested) {
    if (!chatSuggestions) return;

    if (isRequested && attachedPhotos.length < 3) {
      chatSuggestions.innerHTML = `
        <div class="prompt-chip photo-chip-action" id="chipUploadPhoto">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;">
            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
            <circle cx="12" cy="13" r="4"/>
          </svg> Upload a photo
        </div>
        <div class="prompt-chip photo-chip-skip" id="chipSkipPhoto">Skip for now</div>
      `;

      const uploadChip = document.getElementById('chipUploadPhoto');
      const skipChip = document.getElementById('chipSkipPhoto');

      if (uploadChip && chatPhotoInput) {
        uploadChip.addEventListener('click', () => {
          chatPhotoInput.click();
          if (btnAttachPhoto) btnAttachPhoto.classList.remove('pulse-highlight');
        });
      }

      if (skipChip) {
        skipChip.addEventListener('click', () => {
          if (btnAttachPhoto) btnAttachPhoto.classList.remove('pulse-highlight');
          handleUserChat('Skip photo for now');
        });
      }

      if (btnAttachPhoto) {
        btnAttachPhoto.classList.add('pulse-highlight');
      }
    } else {
      if (btnAttachPhoto) {
        btnAttachPhoto.classList.remove('pulse-highlight');
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

  function showThinkingIndicator(customText = null) {
    if (!chatMessages) return null;
    const thinking = document.createElement('div');
    thinking.className = 'chat-bubble thinking orbi-thinking-bubble';
    thinking.setAttribute('role', 'status');
    thinking.setAttribute('aria-live', 'polite');
    const caption = customText || 'Thinking...';
    const orbiEl = window.Orbi ? window.Orbi.create({ state: 'thinking', size: 36, label: caption }) : null;
    const orbiHtml = orbiEl ? orbiEl.outerHTML : '<span class="dot"></span><span class="dot"></span><span class="dot"></span>';
    thinking.innerHTML = `
      <div class="chat-thinking-content">
        ${orbiHtml}
        <span class="chat-thinking-label">${escapeHtml(caption)}</span>
      </div>
    `;
    chatMessages.appendChild(thinking);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    return thinking;
  }

  function removeThinkingIndicator(bubble) {
    if (bubble && bubble.parentNode) {
      bubble.parentNode.removeChild(bubble);
    }
  }

  function updateDraftSyncStatus(isSyncing) {
    const liveDraftCard = document.querySelector('.live-draft-card');
    const badge = liveDraftCard ? liveDraftCard.querySelector('.live-badge') : null;
    if (badge) {
      if (isSyncing) {
        badge.innerHTML = '<span class="live-dot" style="animation:pulse 0.8s infinite;"></span> AI Syncing...';
      } else {
        badge.innerHTML = '<span class="live-dot"></span> Live';
      }
    }
  }

  function renderDraftPhotos() {
    if (!draftPhotosSection || !draftPhotosList) return;

    if (attachedPhotos.length === 0) {
      draftPhotosSection.style.display = 'none';
      draftPhotosList.innerHTML = '';
      return;
    }

    draftPhotosSection.style.display = 'block';
    draftPhotosList.innerHTML = '';

    attachedPhotos.forEach((photo, idx) => {
      const card = document.createElement('div');
      card.className = 'draft-photo-thumb';
      card.title = `${photo.name || 'Photo'} - Click to enlarge`;

      const img = document.createElement('img');
      img.src = photo.dataUrl;
      img.alt = photo.name || 'Photo';
      img.addEventListener('click', () => {
        openLightbox(photo.dataUrl, photo.name || 'Evidence Photo');
      });

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'btn-draft-photo-remove';
      removeBtn.title = 'Remove photo';
      removeBtn.setAttribute('aria-label', 'Remove photo');
      removeBtn.innerHTML = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

      removeBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        attachedPhotos.splice(idx, 1);
        if (attachmentCounterBadge) {
          attachmentCounterBadge.textContent = `${attachedPhotos.length}/3 photos`;
        }

        // If no photos remain, clear photo-derived attributes
        if (attachedPhotos.length === 0) {
          const photoKeys = currentDraft.from_photo_keys || [];
          photoKeys.forEach(k => {
            if (currentDraft.dynamic_attributes && currentDraft.dynamic_attributes[k]) {
              delete currentDraft.dynamic_attributes[k];
            }
          });
          currentDraft.from_photo_keys = [];
        }

        renderDraftPhotos();
        renderDynamicDraft(currentDraft, false);
        addChatBubble('<em>Photo removed from report draft.</em>', 'ai');
      });

      card.appendChild(img);
      card.appendChild(removeBtn);
      draftPhotosList.appendChild(card);
    });
  }

  // Fully dynamic right-side attribute card renderer
  function renderDynamicDraft(draft, isReady) {
    if (!dynamicDraftContainer) return;

    const attributes = draft.dynamic_attributes || {};
    const hasCore = Boolean(
      draft.title ||
      draft.category ||
      draft.location ||
      draft.time ||
      draft.verification_secret ||
      draft.contact ||
      Object.keys(attributes).length > 0 ||
      attachedPhotos.length > 0
    );

    if (!hasCore) {
      dynamicDraftContainer.innerHTML = `
        <div class="draft-empty-state" id="draftEmptyState">
          <div class="draft-empty-header">
            <span class="draft-empty-badge">AI Intake Dossier</span>
            <div class="draft-empty-sub">Speak, type, or attach photos. The AI extracts features into this dossier in real time.</div>
          </div>
          <div class="draft-blueprint-grid">
            <div class="draft-blueprint-card">
              <div class="draft-blueprint-icon">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>
              </div>
              <div class="draft-blueprint-text">
                <div class="draft-blueprint-label">Item Identification</div>
                <div class="draft-blueprint-sub"><em>Waiting for item title or brand...</em></div>
              </div>
            </div>

            <div class="draft-blueprint-card">
              <div class="draft-blueprint-icon">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>
              </div>
              <div class="draft-blueprint-text">
                <div class="draft-blueprint-label">Category &amp; Type</div>
                <div class="draft-blueprint-sub"><em>Auto-categorizes belongings...</em></div>
              </div>
            </div>

            <div class="draft-blueprint-card">
              <div class="draft-blueprint-icon">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
              </div>
              <div class="draft-blueprint-text">
                <div class="draft-blueprint-label">Location &amp; Date</div>
                <div class="draft-blueprint-sub"><em>Detects campus locations &amp; time...</em></div>
              </div>
            </div>

            <div class="draft-blueprint-card">
              <div class="draft-blueprint-icon">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              </div>
              <div class="draft-blueprint-text">
                <div class="draft-blueprint-label">Distinctive Marks</div>
                <div class="draft-blueprint-sub"><em>Private serials, stickers, colors...</em></div>
              </div>
            </div>
          </div>
        </div>
      `;
      if (btnDraftSubmit) btnDraftSubmit.disabled = true;
      return;
    }

    const fromPhotoKeys = (draft.from_photo_keys || []).map(k => String(k).toLowerCase());
    const isFromPhoto = (key) => fromPhotoKeys.includes(String(key).toLowerCase());

    const photoBadgeHtml = '<span class="draft-tag-badge from-photo"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg> From photo</span>';

    let html = '';

    // 1. Title / Item Type Card
    if (draft.title) {
      const isPhotoItem = isFromPhoto('title') || isFromPhoto('item type') || isFromPhoto('object_type');
      html += `
        <div class="draft-item updated">
          <div class="draft-item-header">
            <span class="draft-label"><span class="draft-label-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg></span> ${isFoundPage ? 'Found Item' : 'Lost Item'}</span>
            ${isPhotoItem ? photoBadgeHtml : '<span class="draft-tag-badge">Identified</span>'}
          </div>
          <div class="draft-value" contenteditable="true" spellcheck="false" data-field="title" title="Click to edit">${escapeHtml(draft.title)}</div>
        </div>
      `;
    }

    // 2. Category Card
    if (draft.category) {
      const isPhotoCat = isFromPhoto('category');
      html += `
        <div class="draft-item">
          <div class="draft-item-header">
            <span class="draft-label"><span class="draft-label-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg></span> Category</span>
            ${isPhotoCat ? photoBadgeHtml : ''}
          </div>
          <div class="draft-value" contenteditable="true" spellcheck="false" data-field="category" title="Click to edit">${escapeHtml(draft.category)}</div>
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
          <div class="draft-value" contenteditable="true" spellcheck="false" data-field="location" title="Click to edit">${escapeHtml(draft.location)}</div>
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
          <div class="draft-value" contenteditable="true" spellcheck="false" data-field="time" title="Click to edit">${escapeHtml(draft.time)}</div>
        </div>
      `;
    }

    // 5. Dynamic Discovered Attributes (Color, Brand, Strap, Scratches, Engravings, etc.)
    for (const [key, value] of Object.entries(attributes)) {
      if (value && String(value).trim() !== '') {
        const icon = getAttributeIcon(key);
        const photoTagged = isFromPhoto(key);
        html += `
          <div class="draft-item updated">
            <div class="draft-item-header">
              <span class="draft-label"><span class="draft-label-icon">${icon}</span> ${escapeHtml(key)}</span>
              ${photoTagged ? photoBadgeHtml : '<span class="draft-tag-badge">Detail</span>'}
            </div>
            <div class="draft-value" contenteditable="true" spellcheck="false" data-field="attr_${escapeHtml(key)}" title="Click to edit">${escapeHtml(String(value))}</div>
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
          <div class="draft-value" contenteditable="true" spellcheck="false" data-field="verification_secret" title="Click to edit">${escapeHtml(draft.verification_secret)}</div>
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
          <div class="draft-value" contenteditable="true" spellcheck="false" data-field="contact" title="Click to edit">${escapeHtml(draft.contact)}</div>
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

    // Attach editable listeners to persist changes in currentDraft
    dynamicDraftContainer.querySelectorAll('.draft-value[contenteditable="true"]').forEach(el => {
      const field = el.getAttribute('data-field');
      const updateValue = () => {
        const newVal = el.innerText.trim();
        if (field === 'title') currentDraft.title = newVal;
        else if (field === 'category') currentDraft.category = newVal;
        else if (field === 'location') currentDraft.location = newVal;
        else if (field === 'time') currentDraft.time = newVal;
        else if (field === 'verification_secret') currentDraft.verification_secret = newVal;
        else if (field === 'contact') currentDraft.contact = newVal;
        else if (field && field.startsWith('attr_')) {
          const attrKey = field.substring(5);
          if (!currentDraft.dynamic_attributes) currentDraft.dynamic_attributes = {};
          currentDraft.dynamic_attributes[attrKey] = newVal;
        }
      };
      el.addEventListener('blur', updateValue);
      el.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          el.blur();
        }
      });
    });

    // Enable submit button if minimum details or photo captured
    if (btnDraftSubmit) {
      const ready = isReady || Boolean(draft.title && (draft.location || Object.keys(attributes).length > 0 || attachedPhotos.length > 0));
      btnDraftSubmit.disabled = !ready;
      if (ready) {
        btnDraftSubmit.textContent = isFoundPage ? 'Submit Found Report' : 'Submit Lost Report';
        btnDraftSubmit.style.cursor = 'pointer';
      } else {
        btnDraftSubmit.textContent = 'Complete Details to Submit';
        btnDraftSubmit.style.cursor = 'not-allowed';
      }
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
  // Finite State Machine (FSM) for Voice Mode
  // Loop: LISTENING -> (input complete) -> WORKING -> (ai reply) -> SPEAKING -> (done) -> LISTENING
  // -------------------------------------------------------------
  let recognition = null;
  let isRecognitionRunning = false;
  let animFrameId = null;
  let restartTimeoutId = null;
  let speechDebounceTimer = null;
  let speechSafetyTimeout = null;
  let accumulatedSpeech = '';
  let cachedNaturalVoice = null;
  let activeUtterance = null;

  let micMediaStream = null;
  let micAudioContext = null;
  let micAnalyser = null;
  let micFreqData = null;
  let mediaRecorder = null;
  let recordedAudioChunks = [];
  let isMediaRecorderRecording = false;
  let useMediaRecorderAudio = false;

  // Preload and cache natural sounding TTS voice
  function initSpeechSynthesisVoices() {
    if (!('speechSynthesis' in window)) return;
    const load = () => {
      const voices = window.speechSynthesis.getVoices();
      if (!voices || voices.length === 0) return;
      cachedNaturalVoice = voices.find(v => v.lang.startsWith('en') && (
        v.name.includes('Natural') ||
        v.name.includes('Google') ||
        v.name.includes('Samantha') ||
        v.name.includes('Jenny') ||
        v.name.includes('Guy') ||
        v.name.includes('Aria')
      )) || voices.find(v => v.lang.startsWith('en')) || voices[0];
    };
    load();
    if (window.speechSynthesis.onvoiceschanged !== undefined) {
      window.speechSynthesis.onvoiceschanged = load;
    }
  }
  initSpeechSynthesisVoices();

  // Central State Transition Manager for Voice FSM
  function setVoiceState(newState, detail = '') {
    const prevState = currentVoiceState;
    currentVoiceState = newState;
    isVoiceModeActive = (newState !== VoiceState.OFF);
    isAiResponding = (newState === VoiceState.WORKING);
    isAiSpeaking = (newState === VoiceState.SPEAKING);

    console.log(`[VoiceFSM] ${prevState} -> ${newState} ${detail ? `(${detail})` : ''}`);

    switch (newState) {
      case VoiceState.OFF: {
        clearTimeout(speechDebounceTimer);
        clearTimeout(restartTimeoutId);
        clearTimeout(speechSafetyTimeout);
        speechDebounceTimer = null;
        restartTimeoutId = null;
        speechSafetyTimeout = null;
        activeUtterance = null;
        accumulatedSpeech = '';

        if ('speechSynthesis' in window) {
          try { window.speechSynthesis.cancel(); } catch (e) {}
        }

        hardStopMic();
        releaseMicHardware();

        if (voiceToggleLabel) voiceToggleLabel.textContent = 'Start Voice Mode';
        if (btnVoiceToggle) btnVoiceToggle.classList.remove('active');
        if (btnMicInline) btnMicInline.classList.remove('active');
        if (voiceLiveBar) {
          voiceLiveBar.style.display = 'none';
          voiceLiveBar.className = 'voice-live-bar';
        }
        if (voiceLiveBadge) {
          voiceLiveBadge.style.display = 'none';
          voiceLiveBadge.className = 'voice-header-badge';
        }
        if (btnVoiceSend) btnVoiceSend.style.display = 'none';
        stopVisualizerAnimation();
        break;
      }

      case VoiceState.LISTENING: {
        clearTimeout(speechDebounceTimer);
        clearTimeout(restartTimeoutId);
        clearTimeout(speechSafetyTimeout);
        speechDebounceTimer = null;
        restartTimeoutId = null;
        speechSafetyTimeout = null;
        accumulatedSpeech = '';

        if (voiceToggleLabel) voiceToggleLabel.textContent = 'Stop Voice Mode';
        if (btnVoiceToggle) btnVoiceToggle.classList.add('active');
        if (btnMicInline) btnMicInline.classList.add('active');

        if (voiceLiveBar) {
          voiceLiveBar.style.display = 'flex';
          voiceLiveBar.className = 'voice-live-bar active state-listening';
        }
        if (voiceLiveBadge) {
          voiceLiveBadge.style.display = 'inline-flex';
          voiceLiveBadge.className = 'voice-header-badge live';
        }
        if (voiceBadgeText) voiceBadgeText.textContent = 'Listening (Mic Active)';
        if (voiceLiveText) voiceLiveText.innerHTML = 'Listening &bull; Speak naturally';
        if (btnVoiceSend) btnVoiceSend.style.display = 'none';

        startVisualizerAnimation();
        startRecognitionSafe();
        break;
      }

      case VoiceState.WORKING: {
        clearTimeout(speechDebounceTimer);
        clearTimeout(restartTimeoutId);
        speechDebounceTimer = null;
        restartTimeoutId = null;

        // Immediately hard-stop the microphone so AI never hears echo or itself
        hardStopMic();

        if (voiceLiveBar) {
          voiceLiveBar.style.display = 'flex';
          voiceLiveBar.className = 'voice-live-bar active state-working';
        }
        if (voiceLiveBadge) {
          voiceLiveBadge.style.display = 'inline-flex';
          voiceLiveBadge.className = 'voice-header-badge working';
        }
        if (voiceBadgeText) voiceBadgeText.textContent = 'AI Thinking...';
        if (voiceLiveText) voiceLiveText.innerHTML = 'AI Thinking &bull; Processing Details';
        if (btnVoiceSend) btnVoiceSend.style.display = 'none';

        startVisualizerAnimation();
        break;
      }

      case VoiceState.SPEAKING: {
        clearTimeout(speechDebounceTimer);
        clearTimeout(restartTimeoutId);
        speechDebounceTimer = null;
        restartTimeoutId = null;

        // Ensure mic remains completely stopped while AI is speaking
        hardStopMic();

        if (voiceLiveBar) {
          voiceLiveBar.style.display = 'flex';
          voiceLiveBar.className = 'voice-live-bar active state-speaking';
        }
        if (voiceLiveBadge) {
          voiceLiveBadge.style.display = 'inline-flex';
          voiceLiveBadge.className = 'voice-header-badge speaking';
        }
        if (voiceBadgeText) voiceBadgeText.textContent = 'AI Speaking...';
        if (voiceLiveText) voiceLiveText.innerHTML = 'AI Speaking &bull; Gemini Live Voice';
        if (btnVoiceSend) btnVoiceSend.style.display = 'none';

        startVisualizerAnimation();
        break;
      }
    }
  }

  function hardStopMic() {
    isRecognitionRunning = false;
    if (recognition) {
      try {
        recognition.abort();
      } catch (e) {
        try { recognition.stop(); } catch (e2) {}
      }
    }
    if (mediaRecorder && isMediaRecorderRecording) {
      try { mediaRecorder.stop(); } catch (e) {}
      isMediaRecorderRecording = false;
    }
  }

  function releaseMicHardware() {
    if (micMediaStream) {
      micMediaStream.getTracks().forEach(track => {
        try { track.stop(); } catch (e) {}
      });
      micMediaStream = null;
    }
    if (micAudioContext) {
      try { micAudioContext.close(); } catch (e) {}
      micAudioContext = null;
      micAnalyser = null;
      micFreqData = null;
    }
  }

  function initSpeechRecognition() {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) return null;

    try {
      const rec = new SpeechRecognition();
      rec.continuous = true;
      rec.interimResults = true;
      rec.lang = navigator.language || 'en-US';
      rec.maxAlternatives = 1;

      rec.onstart = () => {
        isRecognitionRunning = true;
        if (currentVoiceState === VoiceState.LISTENING) {
          setVoiceState(VoiceState.LISTENING, 'rec.onstart');
        }
      };

      rec.onresult = (event) => {
        if (currentVoiceState !== VoiceState.LISTENING || !isVoiceModeActive) return;

        let fullTranscript = '';
        for (let i = 0; i < event.results.length; ++i) {
          fullTranscript += event.results[i][0].transcript + ' ';
        }
        fullTranscript = fullTranscript.trim();

        if (fullTranscript) {
          accumulatedSpeech = fullTranscript;
          if (chatInput) chatInput.value = fullTranscript;

          if (voiceLiveText) {
            const snippet = fullTranscript.length > 28 ? '...' + fullTranscript.slice(-28) : fullTranscript;
            voiceLiveText.innerHTML = `Heard: "${escapeHtml(snippet)}"`;
          }

          if (btnVoiceSend) {
            btnVoiceSend.style.display = 'inline-flex';
          }

          // User speaking -> debounce timer for silence (850ms)
          clearTimeout(speechDebounceTimer);
          speechDebounceTimer = setTimeout(() => {
            if (accumulatedSpeech && currentVoiceState === VoiceState.LISTENING && isVoiceModeActive) {
              const finalPhrase = accumulatedSpeech;
              accumulatedSpeech = '';
              if (chatInput) chatInput.value = '';
              if (btnVoiceSend) btnVoiceSend.style.display = 'none';

              // User finished speaking! Transition immediately to WORKING:
              setVoiceState(VoiceState.WORKING, 'silence debounce completed');
              handleUserChat(finalPhrase);
            }
          }, 850);
        }
      };

      rec.onerror = (event) => {
        console.warn('Speech recognition notice:', event.error);
        if (event.error === 'no-speech') {
          if (voiceLiveText && currentVoiceState === VoiceState.LISTENING && !accumulatedSpeech) {
            voiceLiveText.innerHTML = 'Listening &bull; Speak clearly into mic';
          }
          return;
        }

        if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
          setVoiceState(VoiceState.OFF, 'permission denied');
          alert('Microphone access was blocked. Please click the lock or settings icon in your browser address bar and allow microphone permissions for Reunite.');
          return;
        }

        if (event.error === 'network') {
          console.warn('Web Speech API network error detected. Switching automatically to Gemini Direct Audio Voice Engine.');
          useMediaRecorderAudio = true;
          if (voiceLiveText && currentVoiceState === VoiceState.LISTENING) {
            voiceLiveText.innerHTML = 'Gemini Direct Voice &bull; Speak and tap Send';
          }
          if (btnVoiceSend && currentVoiceState === VoiceState.LISTENING) {
            btnVoiceSend.style.display = 'inline-flex';
          }
        }
      };

      rec.onend = () => {
        isRecognitionRunning = false;
        // Strict FSM condition: ONLY restart if state is LISTENING!
        // If state is WORKING, SPEAKING, or OFF, mic remains OFF.
        if (currentVoiceState === VoiceState.LISTENING && isVoiceModeActive && !useMediaRecorderAudio) {
          clearTimeout(restartTimeoutId);
          restartTimeoutId = setTimeout(() => {
            if (currentVoiceState === VoiceState.LISTENING && isVoiceModeActive) {
              startRecognitionSafe();
            }
          }, 200);
        }
      };

      return rec;
    } catch (err) {
      console.warn('SpeechRecognition initialization error:', err);
      return null;
    }
  }

  recognition = initSpeechRecognition();

  function setupMediaRecorder() {
    if (!micMediaStream || typeof MediaRecorder === 'undefined') return;
    try {
      recordedAudioChunks = [];
      let mime = 'audio/webm';
      if (!MediaRecorder.isTypeSupported('audio/webm')) {
        if (MediaRecorder.isTypeSupported('audio/mp4')) mime = 'audio/mp4';
        else if (MediaRecorder.isTypeSupported('audio/wav')) mime = 'audio/wav';
        else mime = '';
      }
      mediaRecorder = mime ? new MediaRecorder(micMediaStream, { mimeType: mime }) : new MediaRecorder(micMediaStream);
      mediaRecorder.ondataavailable = (e) => {
        if (e.data && e.data.size > 0) {
          recordedAudioChunks.push(e.data);
        }
      };
      mediaRecorder.onstop = () => {
        if (recordedAudioChunks.length > 0 && useMediaRecorderAudio && isVoiceModeActive && currentVoiceState === VoiceState.WORKING) {
          const blobMime = mediaRecorder.mimeType || 'audio/webm';
          const audioBlob = new Blob(recordedAudioChunks, { type: blobMime });
          recordedAudioChunks = [];
          if (audioBlob.size > 800) {
            if (voiceLiveText) voiceLiveText.innerHTML = 'Sending voice note to Gemini...';
            handleUserChat(null, audioBlob);
          }
        }
        isMediaRecorderRecording = false;
      };
    } catch (e) {
      console.warn('MediaRecorder setup notice:', e);
    }
  }

  function toggleVoiceMode() {
    if (isVoiceModeActive && currentVoiceState !== VoiceState.OFF) {
      stopVoiceMode();
    } else {
      startVoiceMode();
    }
  }

  async function startVoiceMode() {
    accumulatedSpeech = '';
    clearTimeout(speechDebounceTimer);
    clearTimeout(restartTimeoutId);

    if (voiceLiveBar) {
      voiceLiveBar.style.display = 'flex';
      voiceLiveBar.classList.add('active');
    }
    if (voiceLiveText) {
      voiceLiveText.innerHTML = 'Accessing microphone...';
    }

    // 1. Explicitly request live microphone access
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
      try {
        if (!micMediaStream) {
          micMediaStream = await navigator.mediaDevices.getUserMedia({
            audio: {
              echoCancellation: true,
              noiseSuppression: true,
              autoGainControl: true
            }
          });
        }

        // Setup real audio frequency analyser
        if (!micAudioContext) {
          try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (AudioCtx) {
              micAudioContext = new AudioCtx();
              if (micAudioContext.state === 'suspended') {
                await micAudioContext.resume();
              }
              const src = micAudioContext.createMediaStreamSource(micMediaStream);
              micAnalyser = micAudioContext.createAnalyser();
              micAnalyser.fftSize = 64;
              micAnalyser.smoothingTimeConstant = 0.5;
              src.connect(micAnalyser);
              micFreqData = new Uint8Array(micAnalyser.frequencyBinCount);
            }
          } catch (actxErr) {
            console.warn('AudioContext setup notice:', actxErr);
          }
        }

        setupMediaRecorder();

      } catch (micErr) {
        console.warn('Microphone permission request error:', micErr);
        setVoiceState(VoiceState.OFF, 'permission error');
        alert('Microphone access was blocked. Please click the lock or settings icon in your browser address bar and enable Microphone for Reunite.');
        return;
      }
    } else if (!recognition) {
      setVoiceState(VoiceState.OFF, 'unsupported browser');
      alert('Microphone and speech recognition are not supported in this browser. Please use Chrome, Edge, or Safari.');
      return;
    }

    // Enter initial LISTENING state!
    setVoiceState(VoiceState.LISTENING, 'startVoiceMode success');
  }

  function stopVoiceMode() {
    setVoiceState(VoiceState.OFF, 'stopVoiceMode called');
  }

  function startRecognitionSafe() {
    if (currentVoiceState !== VoiceState.LISTENING || !isVoiceModeActive) return;

    if (recognition && !isRecognitionRunning && !useMediaRecorderAudio) {
      try {
        recognition.start();
      } catch (e) {
        // Ignore if already active
      }
    }

    // Also start MediaRecorder chunk collection
    if (mediaRecorder && !isMediaRecorderRecording && isVoiceModeActive) {
      try {
        recordedAudioChunks = [];
        mediaRecorder.start(250);
        isMediaRecorderRecording = true;
      } catch (e) {}
    }
  }

  function speakAiReply(text) {
    if (!('speechSynthesis' in window)) {
      if (isVoiceModeActive && currentVoiceState !== VoiceState.OFF) {
        setTimeout(() => {
          if (isVoiceModeActive && currentVoiceState !== VoiceState.OFF) {
            setVoiceState(VoiceState.LISTENING, 'tts unsupported fallback');
          }
        }, 500);
      }
      return;
    }

    // Transition to SPEAKING state: stops mic and updates UI
    setVoiceState(VoiceState.SPEAKING, 'speaking ai reply');

    try {
      window.speechSynthesis.cancel();
    } catch (e) {}

    const cleanText = (text || '')
      .replace(/<[^>]*>?/gm, '')
      .replace(/[*_#`~]/g, '')
      .replace(/\s+/g, ' ')
      .trim();

    if (!cleanText) {
      setTimeout(() => {
        if (isVoiceModeActive && currentVoiceState === VoiceState.SPEAKING) {
          setVoiceState(VoiceState.LISTENING, 'empty tts string');
        }
      }, 350);
      return;
    }

    const utterance = new SpeechSynthesisUtterance(cleanText);
    activeUtterance = utterance; // Prevent garbage collection mid-speech
    utterance.rate = 1.02;
    utterance.pitch = 1.0;

    if (cachedNaturalVoice) {
      utterance.voice = cachedNaturalVoice;
    } else {
      const voices = window.speechSynthesis.getVoices();
      if (voices && voices.length > 0) {
        const found = voices.find(v => v.lang.startsWith('en') && (
          v.name.includes('Natural') ||
          v.name.includes('Google') ||
          v.name.includes('Samantha') ||
          v.name.includes('Jenny')
        )) || voices.find(v => v.lang.startsWith('en')) || voices[0];
        if (found) utterance.voice = found;
      }
    }

    let speechDone = false;
    const finishSpeechCycle = (reason) => {
      if (speechDone) return;
      speechDone = true;
      activeUtterance = null;
      clearTimeout(speechSafetyTimeout);
      speechSafetyTimeout = null;

      // Small 350ms echo buffer so room speaker reverb doesn't trigger speech recognition!
      setTimeout(() => {
        if (isVoiceModeActive && currentVoiceState === VoiceState.SPEAKING) {
          setVoiceState(VoiceState.LISTENING, `speech ended via ${reason}`);
        }
      }, 350);
    };

    utterance.onend = () => finishSpeechCycle('onend');
    utterance.onerror = (e) => finishSpeechCycle(`onerror: ${e.error || 'unknown'}`);

    // Safety timeout: calculate estimated duration based on words + 4000ms buffer
    const wordCount = cleanText.split(/\s+/).length;
    const estimatedMs = Math.max(3000, Math.min(25000, (wordCount / 2.2) * 1000 + 4000));
    speechSafetyTimeout = setTimeout(() => {
      console.warn('[VoiceFSM] TTS safety timeout triggered after', estimatedMs, 'ms');
      finishSpeechCycle('safety-timeout');
    }, estimatedMs);

    window.speechSynthesis.speak(utterance);
  }

  function updateVoiceBadgeState() {
    if (!voiceBadgeText || !voiceLiveBadge) return;

    if (currentVoiceState === VoiceState.OFF || !isVoiceModeActive) {
      voiceLiveBadge.style.display = 'none';
      voiceLiveBadge.className = 'voice-header-badge';
      return;
    }

    voiceLiveBadge.style.display = 'inline-flex';

    if (currentVoiceState === VoiceState.SPEAKING) {
      voiceBadgeText.textContent = 'AI Speaking...';
      voiceLiveBadge.className = 'voice-header-badge speaking';
    } else if (currentVoiceState === VoiceState.WORKING) {
      voiceBadgeText.textContent = 'AI Thinking...';
      voiceLiveBadge.className = 'voice-header-badge working';
    } else {
      voiceBadgeText.textContent = 'Listening (Mic Active)';
      voiceLiveBadge.className = 'voice-header-badge live';
    }
  }

  if (btnVoiceToggle) btnVoiceToggle.addEventListener('click', toggleVoiceMode);
  if (btnMicInline) btnMicInline.addEventListener('click', toggleVoiceMode);

  if (btnVoiceSend) {
    btnVoiceSend.addEventListener('click', () => {
      if (currentVoiceState !== VoiceState.LISTENING || !isVoiceModeActive) return;
      clearTimeout(speechDebounceTimer);

      const phrase = accumulatedSpeech || (chatInput ? chatInput.value.trim() : '');
      if (phrase) {
        accumulatedSpeech = '';
        if (chatInput) chatInput.value = '';
        btnVoiceSend.style.display = 'none';
        setVoiceState(VoiceState.WORKING, 'user clicked send button');
        handleUserChat(phrase);
      } else if (mediaRecorder && isMediaRecorderRecording && recordedAudioChunks.length > 0) {
        useMediaRecorderAudio = true;
        btnVoiceSend.style.display = 'none';
        setVoiceState(VoiceState.WORKING, 'sending recorded audio blob');
        try { mediaRecorder.stop(); } catch (e) {}
      }
    });
  }

  function startVisualizerAnimation() {
    if (!voiceVisualizer || animFrameId) return;
    const ctx = voiceVisualizer.getContext('2d');
    const width = voiceVisualizer.width;
    const height = voiceVisualizer.height;
    let waveOffset = 0;

    function renderFrame() {
      ctx.clearRect(0, 0, width, height);
      const numBars = 16;
      const barWidth = 4;
      const gap = (width - numBars * barWidth) / (numBars - 1);
      waveOffset += 0.08;

      let currentAvgVol = 0;
      if (micAnalyser && micFreqData) {
        micAnalyser.getByteFrequencyData(micFreqData);
        let sum = 0;
        for (let j = 0; j < micFreqData.length; j++) sum += micFreqData[j];
        currentAvgVol = sum / micFreqData.length;
      }

      for (let i = 0; i < numBars; i++) {
        let barHeight;
        if (currentVoiceState === VoiceState.SPEAKING) {
          // Dynamic expressive speech wave
          const base = Math.sin(waveOffset * 1.5 + i * 0.4) * 0.5 + 0.5;
          barHeight = Math.max(4, base * (height - 4) + Math.random() * 6);
          ctx.fillStyle = '#C4622D';
        } else if (currentVoiceState === VoiceState.WORKING) {
          // Subtle breathing undulation while thinking
          const wave = Math.sin(waveOffset + i * 0.5) * 0.5 + 0.5;
          barHeight = 4 + wave * 8;
          ctx.fillStyle = '#F59E0B';
        } else if (currentVoiceState === VoiceState.LISTENING) {
          if (currentAvgVol > 8) {
            // Real voice detected from microphone!
            const binIdx = Math.min(i * 2, (micFreqData ? micFreqData.length - 1 : 0));
            const binVal = micFreqData ? micFreqData[binIdx] / 255 : (currentAvgVol / 255);
            barHeight = Math.max(5, binVal * (height - 2));
            ctx.fillStyle = '#10B981';
          } else {
            // Calm listening state
            barHeight = 4;
            ctx.fillStyle = 'rgba(16, 185, 129, 0.45)';
          }
        } else {
          barHeight = 4;
          ctx.fillStyle = 'rgba(16, 185, 129, 0.2)';
        }

        const x = i * (barWidth + gap);
        const y = (height - barHeight) / 2;

        ctx.beginPath();
        if (typeof ctx.roundRect === 'function') {
          ctx.roundRect(x, y, barWidth, barHeight, 2);
        } else {
          ctx.rect(x, y, barWidth, barHeight);
        }
        ctx.fill();
      }

      // Update text in real time if hearing voice
      if (currentVoiceState === VoiceState.LISTENING && !accumulatedSpeech) {
        if (currentAvgVol > 12) {
          if (voiceLiveText) voiceLiveText.innerHTML = 'Hearing voice &bull; Speak naturally';
        } else {
          if (voiceLiveText && voiceLiveText.innerHTML.includes('Hearing')) {
            voiceLiveText.innerHTML = 'Listening &bull; Speak naturally';
          }
        }
      }

      if (isVoiceModeActive && currentVoiceState !== VoiceState.OFF) {
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

      const thinkingContainer = document.querySelector('.live-draft-card') || dynamicDraftContainer;
      const thinkingHandle = window.Orbi && thinkingContainer ? window.Orbi.showThinking(thinkingContainer, { caption: 'Extracting details...', size: 44, delay: 300 }) : null;

      const primaryPhotoFile = attachedPhotos.length > 0 ? attachedPhotos[0].file : null;
      const primaryPhotoUrl = attachedPhotos.length > 0 ? attachedPhotos[0].serverUrl : null;

      try {
        let dna = null;
        if (window.FlaskAIService) {
          const aiResult = await window.FlaskAIService.submitReportToFlask({
            description: combinedDescription,
            imageFile: primaryPhotoFile,
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
            verification: currentDraft.verification_secret,
            imageFile: primaryPhotoFile,
            imagePath: primaryPhotoUrl
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
            verification: currentDraft.verification_secret,
            imageFile: primaryPhotoFile,
            imagePath: primaryPhotoUrl
          });
        } else {
          showSuccessState(combinedDescription, { ...meta, dna });
        }
      } finally {
        if (thinkingHandle) {
          thinkingHandle.dismiss();
        }
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

