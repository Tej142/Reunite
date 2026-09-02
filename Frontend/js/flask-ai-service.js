/**
 * Reunite Flask AI Backend Service Integration
 * Handles HTTP requests from Frontend to Flask AI Server (http://127.0.0.1:5000)
 */

const FLASK_SERVER_URL = (typeof window !== 'undefined' && window.FLASK_BACKEND_URL) 
  ? window.FLASK_BACKEND_URL 
  : 'http://127.0.0.1:5000';

/**
 * Sends lost/found item description and optional image file to Flask AI API.
 * @param {Object} params
 * @param {string} params.description - Item text description
 * @param {File|null} params.imageFile - Uploaded image File object
 * @returns {Promise<Object>} API response object containing report_id and digital_dna
 */
async function submitReportToFlask({ description, imageFile = null }) {
  if (!description || !description.trim()) {
    throw new Error('Description is required for AI analysis.');
  }

  const formData = new FormData();
  formData.append('description', description.trim());

  if (imageFile) {
    formData.append('image', imageFile);
  }

  try {
    const response = await fetch(`${FLASK_SERVER_URL}/new-report`, {
      method: 'POST',
      body: formData,
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      throw new Error(data.message || data.error || 'Failed to analyze report with AI server.');
    }

    return data;
  } catch (error) {
    console.warn('[Flask AI Service] API Request Failed:', error.message);
    throw error;
  }
}

/**
 * Sends current Digital DNA and existing reports to Flask AI comparison API.
 * @param {string} reportId - Current report ID
 * @param {Array<Object>} existingDnas - Array of existing Digital DNA objects
 * @returns {Promise<Object>} Comparison result with match percentage scores
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
 * Renders the AI Digital DNA results card into a target DOM container.
 * @param {Object} dna - Digital DNA object returned from AI server
 * @param {HTMLElement} container - DOM container element to populate
 */
function renderAiDnaCard(dna, container) {
  if (!container || !dna) return;

  const objType = dna.object_type || 'Item';
  const attrs = dna.attributes || {};
  const brand = attrs.Brand || attrs.brand || 'N/A';
  const model = attrs.Model || attrs.model || '';
  const color = attrs.Color || attrs.color || 'N/A';

  const features = Array.isArray(dna.visible_features) ? dna.visible_features : [];

  let gridHtml = `
    <div class="ai-dna-card-item">
      <div class="ai-dna-label">Category</div>
      <div class="ai-dna-val">${objType}</div>
    </div>
    <div class="ai-dna-card-item">
      <div class="ai-dna-label">Brand / Model</div>
      <div class="ai-dna-val">${brand} ${model}</div>
    </div>
    <div class="ai-dna-card-item">
      <div class="ai-dna-label">Color</div>
      <div class="ai-dna-val">${color}</div>
    </div>
  `;

  let featuresHtml = '';
  if (features.length > 0) {
    featuresHtml = `
      <div class="ai-dna-section-title">AI Extracted Visual Features</div>
      <div class="ai-dna-chips">
        ${features.map(f => `<span class="ai-dna-chip">🔍 ${f}</span>`).join('')}
      </div>
    `;
  }

  container.innerHTML = `
    <div class="ai-dna-header">
      <div class="ai-dna-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#C4622D" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
        AI Analysis & Digital DNA
      </div>
      <span class="ai-dna-badge">Active Index</span>
    </div>
    <div class="ai-dna-grid">
      ${gridHtml}
    </div>
    ${featuresHtml}
  `;

  container.style.display = 'block';
}

// Export for module/script usage
window.FlaskAIService = {
  submitReportToFlask,
  compareReportsWithFlask,
  renderAiDnaCard,
  SERVER_URL: FLASK_SERVER_URL,
};
