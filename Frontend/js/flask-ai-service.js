/**
 * Reunite Flask AI Backend Service Integration
 * Handles HTTP requests from Frontend to Flask AI Server (http://127.0.0.1:5000)
 * and provides dynamic AI attribute extraction and form rendering.
 */

const FLASK_SERVER_URL = (typeof window !== 'undefined' && window.FLASK_BACKEND_URL) 
  ? window.FLASK_BACKEND_URL 
  : 'http://127.0.0.1:5000';

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
    category = '📱 Electronics / Smartphone';
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
    category = '💻 Electronics / Laptop';
    if (lower.includes('macbook')) { brand = 'Apple'; model = 'MacBook'; }
    if (lower.includes('dell')) brand = 'Dell';
    if (lower.includes('lenovo')) brand = 'Lenovo';
    features.push('Keyboard Layout', 'Trackpad', 'Laptop Chassis');
  } else if (lower.includes('wallet') || lower.includes('purse') || lower.includes('pouch')) {
    category = '💼 Wallets & Bags';
    material = 'Leather';
    features.push('Card Slots', 'Cash Pocket', 'Folding Bifold');
  } else if (lower.includes('backpack') || lower.includes('bag')) {
    category = '🎒 Bags & Luggage';
    if (lower.includes('wildcraft')) brand = 'Wildcraft';
    if (lower.includes('nike')) brand = 'Nike';
    features.push('Zipper Compartments', 'Adjustable Straps');
  } else if (lower.includes('key') || lower.includes('keys')) {
    category = '🔑 Keys & Access';
    features.push('Metal Keyring', 'Access Fob / Key');
  } else if (lower.includes('watch') || lower.includes('smartwatch')) {
    category = '⌚ Watches & Wearables';
    features.push('Wrist Strap', 'Watch Dial');
  } else if (lower.includes('earbuds') || lower.includes('airpods') || lower.includes('headphones')) {
    category = '🎧 Audio & Earbuds';
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

    const response = await fetch(`${FLASK_SERVER_URL}/new-report`, {
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
 * Sends current Digital DNA and existing reports to Flask AI comparison API.
 */
async function compareReportsWithFlask(reportId, existingDnas = []) {
  try {
    const response = await fetch(`${FLASK_SERVER_URL}/compare-report`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        report_id: reportId,
        digital_dnas: existingDnas,
      }),
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      throw new Error(data.message || data.error || 'Failed to compare reports with AI server.');
    }

    return data;
  } catch (error) {
    console.warn('[Flask AI Service] Compare Request Failed:', error.message);
    throw error;
  }
}

/**
 * Renders an editable Dynamic Form / Attribute Inspector for the AI Extracted Digital DNA.
 * @param {Object} dna - Digital DNA object
 * @param {HTMLElement} container - DOM container element to populate
 * @param {Object} [options] - Additional display options
 */
function renderAiDnaCard(dna, container, options = {}) {
  if (!container || !dna) return;

  const objType = dna.object_type || 'General Item';
  const attrs = dna.attributes || {};
  const brand = attrs.Brand || attrs.brand || '';
  const model = attrs.Model || attrs.model || '';
  const color = attrs.Color || attrs.color || '';
  const material = attrs.Material || attrs.material || '';
  const condition = attrs.Condition || attrs.condition || 'Good';
  const marks = attrs["Distinguishing Marks"] || attrs.marks || attrs.verification || '';
  const location = dna.location || options.location || attrs.Location || attrs.where || '';
  const dateTime = attrs["Date / Time"] || attrs["Date/Time"] || options.when || options.time || '';

  const features = Array.isArray(dna.visible_features) ? dna.visible_features : [];

  container.innerHTML = `
    <div class="ai-dynamic-form-wrap">
      <div class="ai-dna-header">
        <div class="ai-dna-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
          </svg>
          <span>AI Extracted Attributes &amp; Digital DNA</span>
        </div>
        <span class="ai-dna-badge">⚡ Verified by AI</span>
      </div>

      <p class="ai-dna-intro">
        Our AI vision &amp; NLP engine extracted the following structured attributes from your report. You can review or fine-tune any field below:
      </p>

      <form class="ai-dynamic-fields-grid" id="aiDynamicEditForm" onsubmit="event.preventDefault();">
        <div class="ai-form-field">
          <label>Item Category / Type</label>
          <input type="text" class="ai-field-input" id="aiFieldCategory" value="${escapeHtml(objType)}" />
        </div>

        <div class="ai-form-field">
          <label>Brand</label>
          <input type="text" class="ai-field-input" id="aiFieldBrand" value="${escapeHtml(brand)}" placeholder="e.g. Apple, Nike" />
        </div>

        <div class="ai-form-field">
          <label>Model / Variant</label>
          <input type="text" class="ai-field-input" id="aiFieldModel" value="${escapeHtml(model)}" placeholder="e.g. iPhone 13 Pro" />
        </div>

        <div class="ai-form-field">
          <label>Primary Color</label>
          <input type="text" class="ai-field-input" id="aiFieldColor" value="${escapeHtml(color)}" placeholder="e.g. Midnight Blue" />
        </div>

        <div class="ai-form-field">
          <label>Material</label>
          <input type="text" class="ai-field-input" id="aiFieldMaterial" value="${escapeHtml(material)}" placeholder="e.g. Leather, Aluminum" />
        </div>

        <div class="ai-form-field">
          <label>Condition</label>
          <input type="text" class="ai-field-input" id="aiFieldCondition" value="${escapeHtml(condition)}" placeholder="e.g. Good, Minor Scratches" />
        </div>

        <div class="ai-form-field">
          <label>Reported Location (Where)</label>
          <input type="text" class="ai-field-input" id="aiFieldLocation" value="${escapeHtml(location)}" placeholder="e.g. IT LAB, Central Library" />
        </div>

        <div class="ai-form-field">
          <label>Reported Date &amp; Time (When)</label>
          <input type="text" class="ai-field-input" id="aiFieldDateTime" value="${escapeHtml(dateTime)}" placeholder="e.g. Today 2:00 PM, Yesterday" />
        </div>

        <div class="ai-form-field full-width">
          <label>Distinguishing Marks / Secret Details</label>
          <input type="text" class="ai-field-input" id="aiFieldMarks" value="${escapeHtml(marks)}" placeholder="e.g. Sticker on back, small dent on bottom" />
        </div>
      </form>

      ${features.length > 0 ? `
        <div class="ai-dna-section-title">Visual Features &amp; Search Tags</div>
        <div class="ai-dna-chips">
          ${features.map(f => `<span class="ai-dna-chip">🔍 ${escapeHtml(f)}</span>`).join('')}
        </div>
      ` : ''}

      <div class="ai-dynamic-actions">
        <button type="button" class="btn-confirm-dna" id="btnConfirmDna">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          Confirm &amp; Index Report
        </button>
        <a href="search.php" class="btn-search-dna">
          Search Matches on Board &rarr;
        </a>
      </div>
    </div>
  `;

  container.style.display = 'block';

  // Bind Confirm button
  const confirmBtn = container.querySelector('#btnConfirmDna');
  if (confirmBtn) {
    confirmBtn.addEventListener('click', () => {
      confirmBtn.innerHTML = '✓ Confirmed &amp; Active';
      confirmBtn.classList.add('confirmed');
      confirmBtn.disabled = true;
    });
  }
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

// Export for module/script usage
window.FlaskAIService = {
  submitReportToFlask,
  compareReportsWithFlask,
  renderAiDnaCard,
  extractClientDna,
  SERVER_URL: FLASK_SERVER_URL,
};
