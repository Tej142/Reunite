document.addEventListener('DOMContentLoaded', () => {
  const revealEls = document.querySelectorAll('.reveal');
  const io = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('in-view');
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.2 });

  revealEls.forEach(el => io.observe(el));

  // Handle Learn More scroll-cue expansion
  const scrollCue = document.querySelector('.scroll-cue');
  const highlights = document.getElementById('highlights');
  
  if (scrollCue && highlights) {
    // Reset max-height to none after transition so layout responds cleanly to window resizing
    highlights.addEventListener('transitionend', (e) => {
      if (e.propertyName === 'max-height') {
        if (highlights.classList.contains('expanded')) {
          highlights.style.maxHeight = 'none';
        }
      }
    });

    scrollCue.addEventListener('click', (e) => {
      e.preventDefault();
      
      const isExpanded = highlights.classList.contains('expanded');
      
      if (!isExpanded) {
        // Expand
        highlights.classList.add('expanded');
        scrollCue.classList.add('expanded');
        
        // Apply temporary padding to correctly measure scrollHeight
        highlights.style.padding = '100px 0 90px';
        const height = highlights.scrollHeight;
        
        // Start transition from 0
        highlights.style.maxHeight = '0px';
        highlights.offsetHeight; // force reflow
        highlights.style.maxHeight = height + 'px';
        
        // Force entrance animation on nested cards
        const internalReveals = highlights.querySelectorAll('.reveal');
        internalReveals.forEach(el => el.classList.add('in-view'));
      } else {
        // Collapse
        // Set max-height back from 'none' to actual current pixel height to trigger collapse transition
        highlights.style.maxHeight = highlights.offsetHeight + 'px';
        highlights.offsetHeight; // force reflow
        
        highlights.classList.remove('expanded');
        scrollCue.classList.remove('expanded');
        
        highlights.style.maxHeight = '0px';
        highlights.style.padding = '0';
      }
    });
  }

  // Handle footer "How it works" link pointing to #highlights
  const footerHighlightsLink = document.querySelector('.footer-links a[href="#highlights"]');
  if (footerHighlightsLink && highlights) {
    footerHighlightsLink.addEventListener('click', (e) => {
      if (!highlights.classList.contains('expanded')) {
        e.preventDefault();
        
        // Expand highlights
        highlights.classList.add('expanded');
        if (scrollCue) scrollCue.classList.add('expanded');
        
        highlights.style.padding = '100px 0 90px';
        const height = highlights.scrollHeight;
        highlights.style.maxHeight = '0px';
        highlights.offsetHeight; // force reflow
        highlights.style.maxHeight = height + 'px';
        
        const internalReveals = highlights.querySelectorAll('.reveal');
        internalReveals.forEach(el => el.classList.add('in-view'));
        
        // Scroll highlights into view smoothly after starting transition
        setTimeout(() => {
          highlights.scrollIntoView({ behavior: 'smooth' });
        }, 100);
      }
    });
  }
});
