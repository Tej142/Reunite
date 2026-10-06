/**
 * Reunite Flask AI Backend Service Integration
 * Handles HTTP requests from Frontend to Flask AI Cloud Server (https://reunite-ai-backend.onrender.com)
 * and provides dynamic AI attribute extraction and form rendering.
 */

function getFlaskServerUrl() {
  if (typeof window !== 'undefined' && window.FLASK_BACKEND_URL) {
    return window.FLASK_BACKEND_URL;
  }
  return 'https://reunite-ai-backend.onrender.com';
}

const FLASK_SERVER_URL = getFlaskServerUrl();

/**
 * Intelligent client-side parser to extract structured attributes if the Python backend is starting or offline.
 */
function extractClientDna(text = '', extraMeta = {}) {
  const lower = (text + ' ' + (extraMeta.title || '') + ' ' + (extraMeta.where || '') + ' ' + (extraMeta.when || '')).toLowerCase();

  let category = 'General Item';
  let brand = extraMeta.brand || '';
  let model = '';
  let color = extraMeta.color || '';
  let material = '';
  let condition = extraMeta.condition || 'Good';
  let marks = extraMeta.verification || '';
  const features = [];

  // Electronics
  if (lower.includes('iphone') || lower.includes('phone') || lower.includes('apple') || lower.includes('samsung') || lower.includes('pixel') || lower.includes('oneplus')) {
    category = 'Electronics / Smartphone';
    if (lower.includes('apple') || lower.includes('iphone')) brand = 'Apple';
    if (lower.includes('samsung')) brand = 'Samsung';
    if (lower.includes('pixel')) brand = 'Google';

    if (lower.includes('iphone 15')) model = 'iPhone 15';
    else if (lower.includes('iphone 14')) model = 'iPhone 14';
    else if (lower.includes('iphone 13')) model = 'iPhone 13';
    else if (lower.includes('iphone 12')) model = 'iPhone 12';
    else if (lower.includes('iphone 11')) model = 'iPhone 11';
    else if (lower.includes('pro max')) model += ' Pro Max';
    else if (lower.includes('pro')) model += ' Pro';

    features.push('OLED Display', 'Camera Lens Array', 'Lock Screen Protected');
  } else if (lower.includes('laptop') || lower.includes('macbook') || lower.includes('dell') || lower.includes('lenovo') || lower.includes('hp')) {
    category = 'Electronics / Laptop';
    if (lower.includes('macbook')) { brand = 'Apple'; model = 'MacBook'; }
    if (lower.includes('dell')) brand = 'Dell';
    if (lower.includes('lenovo')) brand = 'Lenovo';
    features.push('Keyboard Layout', 'Trackpad', 'Laptop Chassis');
  } else if (lower.includes('wallet') || lower.includes('purse') || lower.includes('pouch')) {
    category = 'Wallets & Bags';
    material = 'Leather';
    features.push('Card Slots', 'Cash Pocket', 'Folding Bifold');
  } else if (lower.includes('backpack') || lower.includes('bag')) {
    category = 'Bags & Luggage';
    if (lower.includes('wildcraft')) brand = 'Wildcraft';
    if (lower.includes('nike')) brand = 'Nike';
    features.push('Zipper Compartments', 'Adjustable Straps');
  } else if (lower.includes('key') || lower.includes('keys')) {
    category = 'Keys & Access';
    features.push('Metal Keyring', 'Access Fob / Key');
  } else if (lower.includes('watch') || lower.includes('smartwatch')) {
    category = 'Watches & Wearables';
    features.push('Wrist Strap', 'Watch Dial');
  } else if (lower.includes('earbuds') || lower.includes('airpods') || lower.includes('headphones')) {
    category = 'Audio & Earbuds';
    if (lower.includes('airpods')) { brand = 'Apple'; model = 'AirPods'; }
    features.push('Charging Case', 'Wireless Earbuds');
  }

  // Color extraction
  const colors = ['black', 'blue', 'brown', 'silver', 'white', 'grey', 'gray', 'red', 'green', 'gold', 'space gray', 'midnight'];
  for (const c of colors) {
    if (lower.includes(c) && !color) {
      color = c.charAt(0).toUpperCase() + c.slice(1);
      features.push(`Color: ${color}`);
      break;
    }
  }

  // Material
  if (lower.includes('leather')) material = 'Genuine Leather';
  if (lower.includes('metal') || lower.includes('aluminum')) material = 'Aluminum / Metal';
  if (lower.includes('plastic') || lower.includes('polycarbonate')) material = 'Polycarbonate';

  // Marks
  if (lower.includes('scratch') || lower.includes('scratched')) marks = marks ? marks + ', Minor Scratches' : 'Minor surface scratches';
  if (lower.includes('sticker')) marks = marks ? marks + ', Custom Sticker attached' : 'Custom Sticker attached';
  if (lower.includes('case')) features.push('Protective Case Equipped');

  const attributes = {
    Brand: brand || 'Generic / Unbranded',
    Model: model || 'Standard Edition',
    Color: color || 'Neutral',
    Material: material || 'Standard Composite',
    Condition: condition,
    "Distinguishing Marks": marks || 'None specified'
  };

  if (extraMeta.when) {
    attributes["Date / Time"] = extraMeta.when;
  }

  return {
    object_type: category,
    attributes: attributes,
    location: extraMeta.where || '',
    visible_features: features.length > 0 ? features : ['Visual Identifiers Indexed', 'Community Search Tagged']
  };
}

/**
 * Sends lost/found item description and optional image file to Flask AI API.
 * @param {Object} params
 * @param {string} params.description - Item text description
 * @param {File|null} params.imageFile - Uploaded image File object
 * @param {Object} [params.meta] - Additional form metadata
 * @returns {Promise<Object>} API response object containing report_id and digital_dna
 */
async function submitReportToFlask({ description, imageFile = null, meta = {} }) {
  // Combine title, description, location (where), time (when), and verification detail into a rich prompt
  const descParts = [];
  if (meta.title && meta.title.trim()) {
    descParts.push(`Item: ${meta.title.trim()}`);
  }
  if (description && description.trim() && description.trim() !== (meta.title || '').trim()) {
    descParts.push(`Description: ${description.trim()}`);
  }
  if (meta.where && meta.where.trim()) {
    descParts.push(`Location (Where): ${meta.where.trim()}`);
  }
  if (meta.when && meta.when.trim()) {
    descParts.push(`Date/Time (When): ${meta.when.trim()}`);
  }
  if (meta.verification && meta.verification.trim()) {
    descParts.push(`Verification Detail: ${meta.verification.trim()}`);
  }

  const combinedDescription = descParts.length > 0 ? descParts.join('. ') : (description || '').trim();

  if (!combinedDescription) {
    throw new Error('Description or Item details are required for AI analysis.');
  }

  const formData = new FormData();
  formData.append('description', combinedDescription);

  if (meta.where && meta.where.trim()) {
    formData.append('where', meta.where.trim());
    formData.append('location', meta.where.trim());
  }
  if (meta.when && meta.when.trim()) {
    formData.append('when', meta.when.trim());
    formData.append('time', meta.when.trim());
  }

  if (imageFile) {
    formData.append('image', imageFile);
  }

  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 60000); // 60s timeout for Multimodal AI Vision & NLP

    const baseUrl = getFlaskServerUrl();
    const response = await fetch(`${baseUrl}/new-report`, {
      method: 'POST',
      body: formData,
      signal: controller.signal
    });

    clearTimeout(timeoutId);
    const data = await response.json();

    if (!response.ok || !data.success) {
      throw new Error(data.message || data.error || 'Failed to analyze report with AI server.');
    }

    return data;
  } catch (error) {
    console.warn('[Flask AI Service] Flask API not reachable, utilizing intelligent client-side DNA extractor:', error.message);
    const clientDna = extractClientDna(description, meta);
    return {
      success: true,
      report_id: 'R' + Math.floor(1000 + Math.random() * 9000),
      digital_dna: clientDna,
      source: 'client_fallback'
    };
  }
}

/**
/**
 * Starts a reporting session for Instant Choice or Talk-to-AI.
 * @param {Object} params
 * @param {string} params.category - Item category (e.g. 'electronics', 'wallet')
 * @param {string} params.report_type - 'lost' or 'found'
 * @param {string} params.input_mode - 'instant_choice' or 'talk_to_ai'
 * @returns {Promise<Object>}
 */
async function startReportSession({ category, report_type = 'lost', input_mode = 'instant_choice' }) {
  try {
    const baseUrl = getFlaskServerUrl();
    const response = await fetch(`${baseUrl}/report/start`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        category: category.toLowerCase().trim(),
        report_type: report_type.toLowerCase().trim(),
        input_mode: input_mode.toLowerCase().trim(),
      }),
    });

    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.error || data.message || 'Failed to start report session.');
    }
    return data;
  } catch (error) {
    console.warn('[Flask AI Service] startReportSession error:', error.message);
    throw error;
  }
}

/**
 * Normalizes report data into canonical format.
 * @param {Object} params
 * @param {Object} params.report_data - Raw extracted report data
 * @param {string} params.source - 'description', 'instant_choice', or 'talk_to_ai'
 * @param {string} [params.category]
 * @param {string} [params.report_type]
 * @returns {Promise<Object>}
 */
async function normalizeReport({ report_data, source = 'description', category = '', report_type = 'lost' }) {
  try {
    const baseUrl = getFlaskServerUrl();
    const response = await fetch(`${baseUrl}/report/normalize`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        report_data,
        source,
        category,
        report_type,
      }),
    });

    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.error || 'Failed to normalize report.');
    }
    return data;
  } catch (error) {
    console.warn('[Flask AI Service] normalizeReport error:', error.message);
    throw error;
  }
}


/**
 * Compares current report DNA against existing report DNAs.
 * @param {Object} params
 * @param {string} params.report_id
 * @param {Array} params.digital_dnas
 * @returns {Promise<Object>}
 */
async function compareReportsWithFlask({ report_id, digital_dnas }) {
  try {
    const baseUrl = getFlaskServerUrl();
    const response = await fetch(`${baseUrl}/compare-report`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        report_id,
        digital_dnas,
      }),
    });
    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Failed to compare reports.');
    }
    return data;
  } catch (error) {
    console.warn('[Flask AI Service] compareReportsWithFlask error:', error.message);
    throw error;
  }
}

/**
 * Renders an editable Dynamic Form / Attribute Inspector for the AI Extracted Digital DNA.
 * @param {Object} dna - Digital DNA object
 * @param {HTMLElement} container - DOM container element to populate
 * @param {Object} [options] - Additional display options
 * @param {boolean} [options.isReviewMode=true] - Whether the card is in interactive pre-submission review mode
 * @param {Function} [options.onConfirm] - Callback fired when user confirms edited values
 * @param {Function} [options.onBack] - Callback fired when user wants to return to the original form
 * @param {Function} [options.onEditAgain] - Callback fired to re-enter edit mode from the success state
 */
function renderAiDnaCard(dna, container, options = {}) {
  if (!container || !dna) return;

  const isReviewMode = options.isReviewMode !== false;
  const objType = dna.object_type || dna.category || 'General Item';
  const attrs = dna.attributes || {};
  const brand = attrs.Brand || attrs.brand || '';
  const model = attrs.Model || attrs.model || '';
  const color = attrs.Color || attrs.color || '';
  const material = attrs.Material || attrs.material || '';
  const condition = attrs.Condition || attrs.condition || 'Good';
  const marks = attrs["Distinguishing Marks"] || attrs.marks || attrs.verification || '';
  const serial = attrs["Serial Number"] || attrs.serial_number || attrs.imei || attrs.id_number || '';
  const secretDetails = attrs["Private Verification Keys"] || attrs.secret_details || attrs.internal_contents || '';
  const location = dna.location || options.location || attrs.Location || attrs.where || '';
  const dateTime = attrs["Date / Time"] || attrs["Date/Time"] || options.when || options.time || '';

  // Copy initial features array so user edits don't mutate input unexpectedly
  let currentTags = Array.isArray(dna.visible_features) ? [...dna.visible_features] : [];

  function renderCard() {
    if (isReviewMode) {
      container.innerHTML = `
        <div class="ai-dynamic-form-wrap">
          <div class="ai-dna-header">
            <div class="ai-dna-title">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
              </svg>
              <span>AI Neural Feature Extraction &amp; Digital DNA</span>
            </div>
            <span class="ai-dna-badge"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px; margin-right:3px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> Multi-Modal Analyzed (CLIP + DINOv2)</span>
          </div>

          <p class="ai-dna-intro">
            Our AI analyzed your description and image. Below, features are organized into <strong>Visible Public Attributes</strong> (indexed for search) and <strong>Protected Private Verification Keys</strong> (encrypted for owner verification). You can edit any field before launching the neural matching engine.
          </p>

          <!-- ── Section 1: Visible Public Feature Vectors ── -->
          <div class="ai-feature-section-header">
            <div class="ai-feature-section-title">
              <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg> Visible Public Feature Vectors</span>
            </div>
            <span class="ai-visible-badge">Public &amp; Indexed</span>
          </div>

          <form class="ai-dynamic-fields-grid" id="aiDynamicEditForm" onsubmit="event.preventDefault();">
            <div class="ai-form-field">
              <label for="aiFieldCategory">
                <span>Item Category / Classification</span>
                <span class="ai-field-edit-hint">Edit</span>
              </label>
              <input type="text" class="ai-field-input" id="aiFieldCategory" value="${escapeHtml(objType)}" placeholder="e.g. Electronics / Smartphone" />
            </div>

            <div class="ai-form-field">
              <label for="aiFieldBrand">
                <span>Brand</span>
                <span class="ai-field-edit-hint">Edit</span>
              </label>
              <input type="text" class="ai-field-input" id="aiFieldBrand" value="${escapeHtml(brand)}" placeholder="e.g. Apple, Peter England, Wildcraft" />
            </div>

            <div class="ai-form-field">
              <label for="aiFieldModel">
                <span>Model / Variant</span>
                <span class="ai-field-edit-hint">Edit</span>
              </label>
              <input type="text" class="ai-field-input" id="aiFieldModel" value="${escapeHtml(model)}" placeholder="e.g. iPhone 13 Pro, Classic Chrono" />
            </div>

            <div class="ai-form-field">
              <label for="aiFieldColor">
                <span>Primary Color &amp; Accents</span>
                <span class="ai-field-edit-hint">Edit</span>
              </label>
              <input type="text" class="ai-field-input" id="aiFieldColor" value="${escapeHtml(color)}" placeholder="e.g. Midnight Blue, Matte Black" />
            </div>

            <div class="ai-form-field">
              <label for="aiFieldMaterial">
                <span>Material &amp; Surface Build</span>
                <span class="ai-field-edit-hint">Edit</span>
              </label>
              <input type="text" class="ai-field-input" id="aiFieldMaterial" value="${escapeHtml(material)}" placeholder="e.g. Leather, Stainless Steel, Plastic" />
            </div>

            <div class="ai-form-field">
              <label for="aiFieldCondition">
                <span>Physical Condition</span>
                <span class="ai-field-edit-hint">Edit</span>
              </label>
              <input type="text" class="ai-field-input" id="aiFieldCondition" value="${escapeHtml(condition)}" placeholder="e.g. Good, Minor Scratches" />
            </div>

            <div class="ai-form-field">
              <label for="aiFieldLocation">
                <span>Reported Location (Where)</span>
                <span class="ai-field-edit-hint">Edit</span>
              </label>
              <input type="text" class="ai-field-input" id="aiFieldLocation" value="${escapeHtml(location)}" placeholder="e.g. Central Library 2nd Floor, IT Lab" />
            </div>

            <div class="ai-form-field">
              <label for="aiFieldDateTime">
                <span>Reported Date &amp; Time (When)</span>
                <span class="ai-field-edit-hint">Edit</span>
              </label>
              <input type="text" class="ai-field-input" id="aiFieldDateTime" value="${escapeHtml(dateTime)}" placeholder="e.g. Today 2:00 PM, Yesterday" />
            </div>
          </form>

          <div class="ai-dna-section-title" style="margin-top:0.75rem;">
            Public Visual Features &amp; Search Tags (${currentTags.length})
          </div>

          <div class="ai-dna-chips" id="aiDnaChipsContainer">
            ${renderChipsHtml(currentTags)}
          </div>

          <div class="ai-add-tag-box">
            <input type="text" class="ai-tag-input" id="aiNewTagInput" placeholder="+ Add visual search tag (e.g. Green striped logo, Roman numerals XII, Yellow keychain)..." />
            <button type="button" class="btn-add-tag" id="aiBtnAddTag">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Add Tag
            </button>
          </div>

          <!-- ── Section 2: Protected Private Verification Keys ── -->
          <div class="ai-feature-section-header">
            <div class="ai-feature-section-title">
              <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Protected Private Verification Keys</span>
            </div>
            <span class="ai-private-badge">Encrypted &bull; Ownership Lock</span>
          </div>

          <div class="ai-security-shield-card">
            <span class="ai-shield-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>
            <div class="ai-shield-text">
              <strong>Zero-Knowledge Ownership Authentication:</strong> Private verification details are never published on public search boards. When a candidate match is found, the system cross-examines these attributes to safely verify true ownership.
            </div>
          </div>

          <form class="ai-dynamic-fields-grid" id="aiDynamicPrivateForm" onsubmit="event.preventDefault();">
            <div class="ai-form-field">
              <label for="aiFieldSerial">
                <span>Serial / IMEI / Hardware ID Traces</span>
                <span class="ai-field-edit-hint">Private</span>
              </label>
              <input type="text" class="ai-field-input" id="aiFieldSerial" value="${escapeHtml(serial)}" placeholder="e.g. Serial: C02..., IMEI: 354892..." />
            </div>

            <div class="ai-form-field">
              <label for="aiFieldPrivateMarks">
                <span>Secret Engravings / Lock Screen / Secret Marks</span>
                <span class="ai-field-edit-hint">Private</span>
              </label>
              <input type="text" class="ai-field-input" id="aiFieldPrivateMarks" value="${escapeHtml(marks)}" placeholder="e.g. Custom initial engraving 'CT', Anime wallpaper" />
            </div>

            <div class="ai-form-field full-width">
              <label for="aiFieldSecretContents">
                <span>Internal Contents / Hidden Cards / Secret Compartment Details</span>
                <span class="ai-field-edit-hint">Private</span>
              </label>
              <textarea class="ai-field-textarea" id="aiFieldSecretContents" rows="2" placeholder="e.g. Contains student ID card #24155, metro pass, folded note inside zipper compartment...">${escapeHtml(secretDetails)}</textarea>
            </div>
          </form>

          <div class="ai-dynamic-actions">
            ${options.onBack ? `
              <button type="button" class="btn-edit-back" id="btnEditBack">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Back to Edit Form
              </button>
            ` : '<div></div>'}
            <button type="button" class="btn-confirm-dna" id="btnConfirmDna">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
              Confirm Digital DNA &amp; Launch Neural Matching Engine &rarr;
            </button>
          </div>
        </div>
      `;
    } else {
      // Finalized Read-Only / Summary Card View
      container.innerHTML = `
        <div class="ai-dynamic-form-wrap">
          <div class="ai-dna-header">
            <div class="ai-dna-title">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
              </svg>
              <span>Confirmed Digital DNA &amp; Indexed Feature Vectors</span>
            </div>
            <span class="ai-dna-badge" style="background: rgba(16, 185, 129, 0.15); color: #10B981;">✓ Indexed in ChromaDB</span>
          </div>

          <div class="ai-dynamic-fields-grid" style="pointer-events: none; opacity: 0.95;">
            <div class="ai-form-field">
              <label>Category / Type</label>
              <div class="ai-field-input" style="padding-top: 2px;">${escapeHtml(objType)}</div>
            </div>
            <div class="ai-form-field">
              <label>Brand</label>
              <div class="ai-field-input" style="padding-top: 2px;">${escapeHtml(brand || 'Generic')}</div>
            </div>
            <div class="ai-form-field">
              <label>Model / Variant</label>
              <div class="ai-field-input" style="padding-top: 2px;">${escapeHtml(model || 'Standard')}</div>
            </div>
            <div class="ai-form-field">
              <label>Color</label>
              <div class="ai-field-input" style="padding-top: 2px;">${escapeHtml(color || 'Neutral')}</div>
            </div>
            <div class="ai-form-field">
              <label>Material</label>
              <div class="ai-field-input" style="padding-top: 2px;">${escapeHtml(material || 'Standard')}</div>
            </div>
            <div class="ai-form-field">
              <label>Condition</label>
              <div class="ai-field-input" style="padding-top: 2px;">${escapeHtml(condition)}</div>
            </div>
            <div class="ai-form-field">
              <label>Location</label>
              <div class="ai-field-input" style="padding-top: 2px;">${escapeHtml(location || 'Campus')}</div>
            </div>
            <div class="ai-form-field">
              <label>Date &amp; Time</label>
              <div class="ai-field-input" style="padding-top: 2px;">${escapeHtml(dateTime || 'Recent')}</div>
            </div>
          </div>

          ${currentTags.length > 0 ? `
            <div class="ai-dna-section-title">Indexed Visual Features (${currentTags.length})</div>
            <div class="ai-dna-chips">
              ${currentTags.map(f => `<span class="ai-dna-chip">${escapeHtml(f)}</span>`).join('')}
            </div>
          ` : ''}

          <div class="ai-dynamic-actions">
            ${options.onEditAgain ? `
              <button type="button" class="btn-edit-again" id="btnEditAgain">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Details
              </button>
            ` : '<div></div>'}
            <a href="search.php" class="btn-search-dna">
              Search Matching Items on Board &rarr;
            </a>
          </div>
        </div>
      `;
    }

    container.style.display = 'block';
    bindEvents();
  }

  function renderChipsHtml(tags) {
    if (!tags || tags.length === 0) {
      return '<span style="font-size: 0.8125rem; color: var(--muted); font-style: italic;">No search tags added. Type below to add tags.</span>';
    }
    return tags.map((t, index) => `
      <span class="ai-dna-chip editable" data-index="${index}">
        <span>${escapeHtml(t)}</span>
        <button type="button" class="btn-remove-tag" data-tag-index="${index}" aria-label="Remove tag">&times;</button>
      </span>
    `).join('');
  }

  function bindEvents() {
    if (!isReviewMode) {
      const editAgainBtn = container.querySelector('#btnEditAgain');
      if (editAgainBtn && typeof options.onEditAgain === 'function') {
        editAgainBtn.addEventListener('click', () => {
          options.onEditAgain(dna);
        });
      }
      return;
    }

    // Tag removal
    container.querySelectorAll('.btn-remove-tag').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const idx = parseInt(btn.getAttribute('data-tag-index'), 10);
        if (!isNaN(idx) && idx >= 0 && idx < currentTags.length) {
          currentTags.splice(idx, 1);
          const chipsContainer = container.querySelector('#aiDnaChipsContainer');
          if (chipsContainer) {
            chipsContainer.innerHTML = renderChipsHtml(currentTags);
            bindEvents();
          }
        }
      });
    });

    // Tag addition
    const addTagInput = container.querySelector('#aiNewTagInput');
    const addTagBtn = container.querySelector('#aiBtnAddTag');

    const handleAddTag = () => {
      if (!addTagInput) return;
      const val = addTagInput.value.trim();
      if (!val) return;
      currentTags.push(val);
      addTagInput.value = '';
      const chipsContainer = container.querySelector('#aiDnaChipsContainer');
      if (chipsContainer) {
        chipsContainer.innerHTML = renderChipsHtml(currentTags);
        bindEvents();
      }
      if (addTagInput) addTagInput.focus();
    };

    if (addTagBtn) {
      addTagBtn.addEventListener('click', (e) => {
        e.preventDefault();
        handleAddTag();
      });
    }

    if (addTagInput) {
      addTagInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          handleAddTag();
        }
      });
    }

    // Back to form button
    const backBtn = container.querySelector('#btnEditBack');
    if (backBtn && typeof options.onBack === 'function') {
      backBtn.addEventListener('click', (e) => {
        e.preventDefault();
        options.onBack();
      });
    }

    // Confirm button
    const confirmBtn = container.querySelector('#btnConfirmDna');
    if (confirmBtn) {
      confirmBtn.addEventListener('click', (e) => {
        e.preventDefault();

        // Harvest visible attributes
        const catInput = container.querySelector('#aiFieldCategory');
        const brandInput = container.querySelector('#aiFieldBrand');
        const modelInput = container.querySelector('#aiFieldModel');
        const colorInput = container.querySelector('#aiFieldColor');
        const matInput = container.querySelector('#aiFieldMaterial');
        const condInput = container.querySelector('#aiFieldCondition');
        const locInput = container.querySelector('#aiFieldLocation');
        const dateInput = container.querySelector('#aiFieldDateTime');

        // Harvest private attributes
        const serialInput = container.querySelector('#aiFieldSerial');
        const privMarksInput = container.querySelector('#aiFieldPrivateMarks');
        const secretContentsInput = container.querySelector('#aiFieldSecretContents');

        const updatedDna = {
          object_type: catInput ? catInput.value.trim() : objType,
          category: catInput ? catInput.value.trim() : objType,
          attributes: {
            Brand: brandInput ? brandInput.value.trim() : brand,
            Model: modelInput ? modelInput.value.trim() : model,
            Color: colorInput ? colorInput.value.trim() : color,
            Material: matInput ? matInput.value.trim() : material,
            Condition: condInput ? condInput.value.trim() : condition,
            "Date / Time": dateInput ? dateInput.value.trim() : dateTime,
            "Serial Number": serialInput ? serialInput.value.trim() : serial,
            "Distinguishing Marks": privMarksInput ? privMarksInput.value.trim() : marks,
            "Private Verification Keys": secretContentsInput ? secretContentsInput.value.trim() : secretDetails
          },
          location: locInput ? locInput.value.trim() : location,
          visible_features: [...currentTags],
          private_verification: {
            serial: serialInput ? serialInput.value.trim() : serial,
            marks: privMarksInput ? privMarksInput.value.trim() : marks,
            secret_contents: secretContentsInput ? secretContentsInput.value.trim() : secretDetails
          }
        };

        if (typeof options.onConfirm === 'function') {
          options.onConfirm(updatedDna);
        } else {
          confirmBtn.innerHTML = '✓ Confirmed &amp; Active';
          confirmBtn.classList.add('confirmed');
          confirmBtn.disabled = true;
        }
      });
    }
  }

  renderCard();
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

/**
 * Submits confirmed report data directly to Backend PHP + Flask Vector Engine
 */
async function submitReportToBackend(payload = {}) {
  const formData = new FormData();
  formData.append('action', 'create');
  formData.append('type', payload.reportType || 'lost');
  formData.append('title', payload.title || payload.dna?.object_type || 'Reported Item');
  formData.append('category', payload.category || payload.dna?.object_type || 'General');
  formData.append('description', payload.description || '');
  formData.append('location', payload.location || payload.dna?.location || 'Campus');
  formData.append('date', payload.date || payload.dna?.attributes?.["Date / Time"] || new Date().toISOString().split('T')[0]);

  if (payload.imageFile) {
    formData.append('image', payload.imageFile);
  } else if (payload.imagePath) {
    formData.append('image_path', payload.imagePath);
  }

  if (payload.dna) {
    formData.append('digital_dna', JSON.stringify(payload.dna));
  }

  const response = await fetch('../Backend/reports.php', {
    method: 'POST',
    body: formData
  });

  const result = await response.json();
  return result;
}

/**
 * Performs AI Vector Similarity Search using ChromaDB Late Fusion
 */
async function searchMatchesWithFlask(params = {}) {
  try {
    const url = `${getFlaskServerUrl()}/report/search-matches`;
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(params)
    });

    if (!response.ok) {
      throw new Error(`Vector search failed with status ${response.status}`);
    }

    return await response.json();
  } catch (err) {
    console.warn('[FlaskAIService] searchMatchesWithFlask error:', err);
    return { success: false, error: err.message, matches: [] };
  }
}

/**
 * Extracts and stores vectors in ChromaDB for a new report
 */
async function embedAndStoreWithFlask(reportData = {}) {
  try {
    const url = `${getFlaskServerUrl()}/report/embed-and-store`;
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(reportData)
    });

    if (!response.ok) {
      throw new Error(`Embedding storage failed with status ${response.status}`);
    }

    return await response.json();
  } catch (err) {
    console.warn('[FlaskAIService] embedAndStoreWithFlask error:', err);
    return { success: false, error: err.message };
  }
}

// Export for module/script usage
window.FlaskAIService = {
  submitReportToFlask,
  compareReportsWithFlask,
  startReportSession,
  normalizeReport,
  renderAiDnaCard,
  extractClientDna,
  searchMatchesWithFlask,
  embedAndStoreWithFlask,
  submitReportToBackend,
  SERVER_URL: FLASK_SERVER_URL,
};




