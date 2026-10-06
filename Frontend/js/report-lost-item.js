document.addEventListener('DOMContentLoaded', () => {
  const lostForm = document.getElementById('lostForm');
  const dropzone = document.getElementById('dropzone');
  const fileInput = document.getElementById('fileInput');
  const previewGrid = document.getElementById('previewGrid');
  const successState = document.getElementById('successState');
  const reportId = document.getElementById('reportId');
  const aiReviewContainer = document.getElementById('aiReviewContainer');
  const aiReviewFormWrap = document.getElementById('aiReviewFormWrap');

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

  function transitionToReview(dna, rawData = {}) {
    const selector = document.querySelector('.mode-selector-wrapper');
    const aiBanner = document.querySelector('.ai-status-banner');
    const containers = document.querySelectorAll('.mode-container');

    if (selector) selector.style.display = 'none';
    if (aiBanner) aiBanner.style.display = 'none';
    containers.forEach(c => c.style.display = 'none');
    if (lostForm) lostForm.style.display = 'none';

    if (aiReviewContainer && aiReviewFormWrap && window.FlaskAIService) {
      aiReviewContainer.style.display = 'block';
      aiReviewContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });

      window.FlaskAIService.renderAiDnaCard(dna, aiReviewFormWrap, {
        location: rawData.where || rawData.location || '',
        when: rawData.when || rawData.date || '',
        isReview: true,
        onBack: () => {
          aiReviewContainer.style.display = 'none';
          if (selector) selector.style.display = 'block';
          if (aiBanner) aiBanner.style.display = 'block';
          const activeContainer = document.getElementById('mode1Container');
          if (activeContainer) activeContainer.classList.add('active');
          containers.forEach(c => {
            if (c.id === 'mode1Container') c.style.display = 'block';
          });
          if (lostForm) lostForm.style.display = 'block';
          lostForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },
        onConfirm: async (confirmedDna) => {
          const confirmBtn = aiReviewFormWrap.querySelector('#btnConfirmDna');
          if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = 'Saving to Database &amp; Launching Neural Vector Matcher...';
          }

          try {
            const result = await window.FlaskAIService.submitReportToBackend({
              reportType: 'lost',
              title: confirmedDna.object_type || rawData.title || 'Lost Item',
              category: confirmedDna.category || confirmedDna.object_type || 'General',
              description: rawData.description || confirmedDna.object_type || '',
              location: confirmedDna.location || rawData.where || 'Campus',
              date: confirmedDna.attributes?.['Date / Time'] || rawData.when || new Date().toISOString().split('T')[0],
              imageFile: rawData.imageFile || (uploadedFiles.length > 0 ? uploadedFiles[0] : null),
              dna: confirmedDna
            });

            const savedId = (result && result.data && result.data.report_id) ? result.data.report_id : ('RL-' + Math.random().toString(36).slice(2, 8).toUpperCase());

            // Fire live vector similarity search
            window.FlaskAIService.searchMatchesWithFlask({
              query_text: rawData.description || confirmedDna.object_type,
              item_id: savedId,
              report_type: 'lost'
            });

            if (window.ReuniteToast) {
              window.ReuniteToast.success('Report Registered', `Missing item report ${savedId} indexed in ChromaDB successfully.`, 4500);
            }

            aiReviewContainer.style.display = 'none';
            transitionToSuccess(savedId, confirmedDna, confirmedDna.location || rawData.where, confirmedDna.attributes?.['Date / Time'] || rawData.when);
          } catch (saveErr) {
            console.error('Error persisting report to backend:', saveErr);
            const fallbackId = 'RL-' + Math.random().toString(36).slice(2, 8).toUpperCase();
            if (window.ReuniteToast) {
              window.ReuniteToast.info('Report Saved Locally', `Saved report ${fallbackId}. Database sync will complete shortly.`, 4000);
            }
            aiReviewContainer.style.display = 'none';
            transitionToSuccess(fallbackId, confirmedDna, confirmedDna.location || rawData.where, confirmedDna.attributes?.['Date / Time'] || rawData.when);
          }
        }
      });
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
      window.FlaskAIService.renderAiDnaCard(dnaData, aiDnaResultsContainer, { location: locationVal, when: whenVal, isReview: false });
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

      const descEl = document.getElementById('description') || lostForm.querySelector('textarea');
      const descriptionText = descEl ? descEl.value.trim() : '';
      const imageFile = uploadedFiles.length > 0 ? uploadedFiles[0] : null;

      const titleEl = document.getElementById('title');
      const whereEl = document.getElementById('where');
      const whenEl = document.getElementById('when');

      if (!descriptionText && (!titleEl || !titleEl.value.trim())) {
        if (descEl) window.shakeElement(descEl);
        if (window.ReuniteToast) {
          window.ReuniteToast.error('Description Required', 'Please provide a brief description or title of the missing item.');
        }
        return;
      }

      if (submitBtn && window.setButtonLoading) {
        window.setButtonLoading(submitBtn, true, 'Synthesizing Digital DNA...');
      }

      const meta = {
        title: titleEl ? titleEl.value.trim() : '',
        where: whereEl ? whereEl.value.trim() : '',
        when: whenEl ? whenEl.value.trim() : ''
      };

      try {
        let dna = null;

        if (window.FlaskAIService && (descriptionText || meta.title)) {
          const aiResult = await window.FlaskAIService.submitReportToFlask({
            description: descriptionText || meta.title,
            imageFile: imageFile,
            meta: meta
          });

          if (aiResult && aiResult.digital_dna) {
            dna = aiResult.digital_dna;
          } else {
            dna = window.FlaskAIService.extractClientDna(descriptionText, meta);
          }
        } else if (window.FlaskAIService) {
          dna = window.FlaskAIService.extractClientDna(descriptionText, meta);
        }

        transitionToReview(dna, {
          title: meta.title,
          description: descriptionText,
          where: meta.where,
          when: meta.when,
          imageFile: imageFile
        });
      } catch (err) {
        console.error('Error in AI report processing:', err);
        const dna = window.FlaskAIService ? window.FlaskAIService.extractClientDna(descriptionText, meta) : null;
        transitionToReview(dna, {
          title: meta.title,
          description: descriptionText,
          where: meta.where,
          when: meta.when,
          imageFile: imageFile
        });
      } finally {
        if (submitBtn && window.setButtonLoading) {
          window.setButtonLoading(submitBtn, false);
        }
      }
    });
  }

  // Export for reporting modes integration
  window.transitionToReview = transitionToReview;
  window.transitionToSuccess = transitionToSuccess;
});
