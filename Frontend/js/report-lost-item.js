document.addEventListener('DOMContentLoaded', () => {
  const dropzone = document.getElementById('dropzone');
  const fileInput = document.getElementById('fileInput');
  const previewGrid = document.getElementById('previewGrid');
  const lostForm = document.getElementById('lostForm');
  const successState = document.getElementById('successState');
  const reportId = document.getElementById('reportId');
  
  let uploadedFiles = [];

  if (dropzone && fileInput) {
    dropzone.addEventListener('click', () => fileInput.click());

    ['dragenter', 'dragover'].forEach(evt => {
      dropzone.addEventListener(evt, e => {
        e.preventDefault();
        dropzone.classList.add('drag');
      });
    });

    ['dragleave', 'drop'].forEach(evt => {
      dropzone.addEventListener(evt, e => {
        e.preventDefault();
        dropzone.classList.remove('drag');
      });
    });

    dropzone.addEventListener('drop', e => {
      if (e.dataTransfer && e.dataTransfer.files) {
        handleFiles(e.dataTransfer.files);
      }
    });

    fileInput.addEventListener('change', e => {
      if (e.target && e.target.files) {
        handleFiles(e.target.files);
      }
    });
  }

  function handleFiles(fileList) {
    Array.from(fileList).forEach(file => {
      if (!file.type.startsWith('image/')) return;
      uploadedFiles.push(file);
      const reader = new FileReader();
      reader.onload = e => {
        if (e.target && e.target.result) {
          addThumb(e.target.result, uploadedFiles.length - 1);
        }
      };
      reader.readAsDataURL(file);
    });
  }

  function addThumb(src, index) {
    const thumb = document.createElement('div');
    thumb.className = 'thumb';
    thumb.innerHTML = `
      <img src="${src}" alt="Uploaded photo preview" />
      <button type="button" aria-label="Remove photo">&times;</button>
    `;
    thumb.querySelector('button').addEventListener('click', () => {
      thumb.remove();
      // Remove file from memory
      uploadedFiles.splice(index, 1);
    });
    if (previewGrid) {
      previewGrid.appendChild(thumb);
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
        submitBtn.innerHTML = '⚡ Analyzing with AI Vision...';
      }

      try {
        let finalReportId = 'RL-' + Math.random().toString(36).slice(2, 8).toUpperCase();

        if (window.FlaskAIService && descriptionText) {
          const aiResult = await window.FlaskAIService.submitReportToFlask({
            description: descriptionText,
            imageFile: imageFile
          });

          if (aiResult && aiResult.report_id) {
            finalReportId = aiResult.report_id;
          }

          if (aiResult && aiResult.digital_dna) {
            const dna = aiResult.digital_dna;
            const aiDnaResultsContainer = document.getElementById('aiDnaResults');
            if (aiDnaResultsContainer && window.FlaskAIService && window.FlaskAIService.renderAiDnaCard) {
              window.FlaskAIService.renderAiDnaCard(dna, aiDnaResultsContainer);
            }
          }
        }

        if (reportId) {
          reportId.textContent = 'Report ID ' + finalReportId;
        }
        lostForm.classList.add('hide');
        if (successState) {
          successState.classList.add('show');
        }
      } catch (err) {
        console.error('Error submitting report to AI Flask backend:', err);
        const fallbackId = 'RL-' + Math.random().toString(36).slice(2, 8).toUpperCase();
        if (reportId) reportId.textContent = 'Report ID ' + fallbackId;
        lostForm.classList.add('hide');
        if (successState) successState.classList.add('show');
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalBtnHtml;
        }
      }
    });
  }
});
