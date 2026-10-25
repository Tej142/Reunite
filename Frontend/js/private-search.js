document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('privateMatchForm');
  if (!form) return;
  const input = document.getElementById('lostReportCode');
  const submit = document.getElementById('privateMatchSubmit');
  const message = document.getElementById('privateSearchMessage');
  const results = document.getElementById('privateMatchResults');
  const grid = document.getElementById('privateMatchGrid');
  const summary = document.getElementById('privateMatchSummary');
  const escapeHtml = value => String(value).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  const placeholder = '<svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="8.5" cy="10" r="1.5"/><path d="m21 15-5-5L5 20"/></svg>';

  form.addEventListener('submit', async event => {
    event.preventDefault();
    let reportId = input.value.trim().toUpperCase().replace(/^#/, '');
    results.hidden = true;
    grid.replaceChildren();
    message.textContent = '';

    // Auto-normalize numeric ID (e.g. 42 -> RL-00042, RL-42 -> RL-00042)
    if (/^\d+$/.test(reportId)) {
      reportId = 'RL-' + reportId.padStart(5, '0');
    } else if (/^RL-\d+$/.test(reportId)) {
      const num = reportId.replace('RL-', '');
      reportId = 'RL-' + num.padStart(5, '0');
    }

    if (!/^RL-\d{1,10}$/.test(reportId)) {
      message.textContent = 'Enter a Lost Report ID such as RL-00042.';
      input.focus();
      return;
    }

    submit.disabled = true;
    submit.textContent = 'Checking…';
    message.textContent = 'Checking live database for matches…';

    try {
      const csrfToken = document.getElementById('privateSearchCsrf')?.value || '';
      const response = await fetch('api/search_matches.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ report_id: reportId })
      });

      const body = await response.json();
      if (response.status === 401) {
        message.innerHTML = 'Your session expired. <a href="login.php">Sign in again</a> to see your matches.';
        return;
      }
      if (!response.ok || !body.success) {
        message.textContent = body.message || 'Report not found in database. Try again shortly.';
        return;
      }

      const matches = body.data?.matches || [];
      summary.textContent = body.message || (matches.length
        ? `We found ${matches.length} possible match${matches.length === 1 ? '' : 'es'}. Review them and verify ownership privately.`
        : 'No matches are available for that report yet.');

      if (matches.length > 0) {
        grid.innerHTML = matches.map((item, index) => {
          let imgSrc = item.thumbnail || '';
          if (imgSrc && !imgSrc.startsWith('http') && !imgSrc.startsWith('/') && !imgSrc.startsWith('../')) {
            imgSrc = '../' + imgSrc;
          }
          const image = imgSrc ? `<img src="${escapeHtml(imgSrc)}" alt="Privacy-safe item thumbnail" loading="lazy" onerror="this.parentNode.innerHTML='${placeholder}'" />` : placeholder;
          return `<article class="search-match-card"><div class="search-match-thumb">${image}</div><div class="search-match-info"><span class="search-match-tag">POSSIBLE MATCH</span><small>Match ${String(index+1).padStart(2,'0')}</small><h4>Possible found item</h4><p>Approximate area</p><div class="search-match-meta"><span>⌖ ${escapeHtml(item.location || 'Campus area')}</span><span class="search-match-score">AI similarity ${Number(item.score) || 0}%</span></div><a href="item.php?type=found&id=${encodeURIComponent(item.report_id)}&match_id=${encodeURIComponent(item.match_id)}">Start ownership check</a></div></article>`;
        }).join('');
      } else {
        grid.innerHTML = '';
      }

      results.hidden = false;
      message.textContent = '';
    } catch (error) {
      message.textContent = 'Search is temporarily unavailable. Try again shortly.';
    } finally {
      submit.disabled = false;
      submit.innerHTML = 'Show my matches <span aria-hidden="true">→</span>';
    }
  });
});
