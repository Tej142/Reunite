document.addEventListener('DOMContentLoaded', () => {
  const lostForm = document.getElementById('lostForm');
  const dropzone = document.getElementById('dropzone');
  const fileInput = document.getElementById('fileInput');
  const previewGrid = document.getElementById('previewGrid');
  const successState = document.getElementById('successState');
  const reportId = document.getElementById('reportId');

  // In-memory array of selected File objects
  let uploadedFiles = [];

  // Dropzone drag-and-drop
  if (dropzone && fileInput) {
    dropzone.addEventListener('click', () => fileInput.click());

    ['dragenter', 'dragover'].forEach(evt => {
      dropzone.addEventListener(evt, e => {
        e.preventDefault();
        dropzone.classList.add('dragover');
      });
    });

    ['dragleave', 'drop'].forEach(evt => {
      dropzone.addEventListener(evt, e => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
      });
    });

    dropzone.addEventListener('drop', e => {
      const dt = e.dataTransfer;
      if (dt && dt.files && dt.files.length) {
        handleFiles(dt.files);
      }
    });

    fileInput.addEventListener('change', e => {
      if (e.target.files && e.target.files.length) {
        handleFiles(e.target.files);
      }
    });
  }

  function handleFiles(files) {
    Array.from(files).forEach(file => {
      if (!file.type.startsWith('image/')) return;
      uploadedFiles.push(file);

      const reader = new FileReader();
      reader.onload = ev => {
        createThumbnail(ev.target.result, uploadedFiles.length - 1);
      };
      reader.readAsDataURL(file);
    });
  }

  function createThumbnail(dataUrl, index) {
    const thumb = document.createElement('div');
    thumb.className = 'thumb dz-thumb';
    thumb.style.cssText = 'position: relative; width: 6.5rem; height: 6.5rem; max-width: 104px; max-height: 104px; border-radius: 0.75rem; overflow: hidden; border: 1.5px solid var(--border, #332B25); background: var(--bg-alt, #1B1714); flex-shrink: 0; display: inline-block; box-shadow: 0 1px 4px rgba(0,0,0,0.2);';
    thumb.innerHTML = `
      <img src="${dataUrl}" alt="Item photo" style="width: 100%; height: 100%; object-fit: cover; display: block;" />
      <button type="button" aria-label="Remove photo" style="position: absolute; top: 0.35rem; right: 0.35rem; width: 1.5rem; height: 1.5rem; border-radius: 50%; border: none; background: rgba(28, 25, 23, 0.85); color: #fff; font-size: 0.875rem; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 2;">&times;</button>
    `;
    thumb.querySelector('button').addEventListener('click', () => {
      thumb.remove();
      uploadedFiles.splice(index, 1);
    });
    if (previewGrid) {
      previewGrid.style.display = 'flex';
      previewGrid.style.flexWrap = 'wrap';
      previewGrid.style.gap = '0.75rem';
      previewGrid.style.marginTop = '0.75rem';
      previewGrid.appendChild(thumb);
    }
  }

  function transitionToSuccess(reportIdVal, dnaData, locationVal, whenVal) {
    const selector = document.querySelector('.mode-selector-wrapper');
    const aiBanner = document.querySelector('.ai-status-banner');
    const containers = document.querySelectorAll('.mode-container');
    
    if (selector) selector.style.display = 'none';
    if (aiBanner) aiBanner.style.display = 'none';
    containers.forEach(c => c.style.display = 'none');
    
    if (lostForm) {
      lostForm.classList.add('hide');
      lostForm.style.display = 'none';
    }

    const aiDnaResultsContainer = document.getElementById('aiDnaResults');
    if (aiDnaResultsContainer && window.FlaskAIService && dnaData) {
      window.FlaskAIService.renderAiDnaCard(dnaData, aiDnaResultsContainer, { location: locationVal, when: whenVal });
    }

    if (reportId) {
      reportId.textContent = 'Report ID ' + reportIdVal;
    }

    if (successState) {
      successState.classList.add('show');
      successState.style.display = 'block';
      successState.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  if (lostForm) {
    lostForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      const submitBtn = lostForm.querySelector('button[type="submit"]');
      const originalBtnHtml = submitBtn ? submitBtn.innerHTML : 'Submit Report';

      const descEl = document.getElementById('description') || lostForm.querySelector('textarea');
      const descriptionText = descEl ? descEl.value.trim() : '';
      const imageFile = uploadedFiles.length > 0 ? uploadedFiles[0] : null;

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '⚡ Analyzing with Gemini &amp; Mistral AI...';
      }

      const titleEl = document.getElementById('title');
      const whereEl = document.getElementById('where');
      const whenEl = document.getElementById('when');

      const meta = {
        title: titleEl ? titleEl.value.trim() : '',
        where: whereEl ? whereEl.value.trim() : '',
        when: whenEl ? whenEl.value.trim() : ''
      };

      try {
        let finalReportId = 'RL-' + Math.random().toString(36).slice(2, 8).toUpperCase();
        let dna = null;

        if (window.FlaskAIService && (descriptionText || meta.title)) {
          const aiResult = await window.FlaskAIService.submitReportToFlask({
            description: descriptionText || meta.title,
            imageFile: imageFile,
            meta: meta
          });

          if (aiResult && aiResult.report_id) {
            finalReportId = aiResult.report_id;
          }
          if (aiResult && aiResult.digital_dna) {
            dna = aiResult.digital_dna;
          }
        } else if (window.FlaskAIService) {
          dna = window.FlaskAIService.extractClientDna(descriptionText, meta);
        }

        transitionToSuccess(finalReportId, dna, meta.where, meta.when);
      } catch (err) {
        console.error('Error in AI report processing:', err);
        const fallbackId = 'RL-' + Math.random().toString(36).slice(2, 8).toUpperCase();
        const dna = window.FlaskAIService ? window.FlaskAIService.extractClientDna(descriptionText, meta) : null;
        transitionToSuccess(fallbackId, dna, meta.where, meta.when);
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalBtnHtml;
        }
      }
    });
  }
});
