/**
 * Reunite — Orbi Mascot JavaScript Controller
 * 
 * Exposes window.Orbi API:
 *   - Orbi.setState(el, state, [label])
 *   - Orbi.create({ state, size, label })
 *   - Orbi.happy(el, [ms])
 *   - Orbi.mount(container, options)
 */

(function (window, document) {
  'use strict';

  const VALID_STATES = ['idle', 'loading', 'thinking', 'happy', 'notfound'];

  function resolveOrbiElement(target) {
    if (!target) return null;
    if (target.nodeType === Node.ELEMENT_NODE && (target.classList.contains('orbi') || target.tagName.toLowerCase() === 'svg')) {
      return target;
    }
    return target.querySelector ? target.querySelector('.orbi') : null;
  }

  const Orbi = {
    /**
     * Valid states constant list
     */
    STATES: VALID_STATES,

    /**
     * Set state on an existing Orbi SVG element.
     * Updates data-state and accessibility attributes appropriately.
     * 
     * @param {Element|string} el - The SVG element or container/selector
     * @param {'idle'|'loading'|'thinking'|'happy'|'notfound'} state
     * @param {string|null} [label] - Optional accessible label
     * @returns {Element|null} The resolved Orbi element
     */
    setState: function (el, state, label) {
      const target = resolveOrbiElement(typeof el === 'string' ? document.querySelector(el) : el);
      if (!target) return null;

      const normState = String(state || 'idle').toLowerCase().trim();
      const finalState = VALID_STATES.includes(normState) ? normState : 'idle';

      target.setAttribute('data-state', finalState);

      // Handle accessibility attributes
      if (typeof label === 'string' && label.trim() !== '') {
        target.setAttribute('aria-label', label.trim());
        target.setAttribute('role', 'img');
        target.removeAttribute('aria-hidden');
      } else {
        target.setAttribute('aria-hidden', 'true');
        target.removeAttribute('aria-label');
        target.removeAttribute('role');
      }

      return target;
    },

    /**
     * Clone an Orbi element from <template id="orbi-template"> and configure it.
     * 
     * @param {Object} [options]
     * @param {'idle'|'loading'|'thinking'|'happy'|'notfound'} [options.state='idle']
     * @param {number} [options.size=64]
     * @param {string} [options.label='']
     * @returns {Element} Cloned and initialized Orbi SVG element
     */
    create: function (options) {
      const opts = options || {};
      const state = opts.state || 'idle';
      const size = opts.size || 64;
      const label = opts.label || '';

      const template = document.getElementById('orbi-template');
      let clone = null;

      if (template) {
        if (template.content && template.content.firstElementChild) {
          clone = template.content.firstElementChild.cloneNode(true);
        } else if (template.firstElementChild) {
          clone = template.firstElementChild.cloneNode(true);
        }
      }

      if (!clone) {
        console.warn('[Orbi] <template id="orbi-template"> not found in DOM.');
        return null;
      }

      // Configure dimensions
      clone.setAttribute('width', size);
      clone.style.setProperty('--orbi-size', size + 'px');

      // Configure state & accessibility
      this.setState(clone, state, label);

      return clone;
    },

    /**
     * Temporarily trigger the HAPPY state (hops and sparkles) then return to IDLE.
     * 
     * @param {Element|string} el
     * @param {number} [ms=2500]
     * @returns {Promise<void>} Resolves when returning to idle
     */
    happy: function (el, ms) {
      const duration = typeof ms === 'number' ? ms : 2500;
      const target = this.setState(el, 'happy');
      if (!target) return Promise.resolve();

      if (target._orbiHappyTimer) {
        clearTimeout(target._orbiHappyTimer);
      }

      return new Promise((resolve) => {
        target._orbiHappyTimer = setTimeout(() => {
          Orbi.setState(target, 'idle');
          target._orbiHappyTimer = null;
          resolve();
        }, duration);
      });
    },

    /**
     * Create an Orbi instance and mount it directly into a container element.
     * 
     * @param {Element|string} container - Parent container or selector
     * @param {Object} [options]
     * @returns {Element|null} The mounted Orbi element
     */
    mount: function (container, options) {
      const parent = typeof container === 'string' ? document.querySelector(container) : container;
      if (!parent) return null;

      const el = this.create(options);
      if (el) {
        parent.appendChild(el);
      }
      return el;
    },

    /**
     * Show a thinking indicator inside target container with a 300ms debounce.
     * If dismiss() is called before 300ms, the indicator never mounts (no flicker).
     * 
     * @param {Element|string} container - Container to mount into
     * @param {Object} [options]
     * @param {string} [options.caption='Thinking...']
     * @param {number} [options.size=40]
     * @param {number} [options.delay=300]
     * @param {string} [options.className='']
     * @returns {{ dismiss: () => void, isMounted: () => boolean }}
     */
    showThinking: function (container, options) {
      const opts = options || {};
      const caption = opts.caption || 'Thinking...';
      const size = opts.size || 40;
      const delay = typeof opts.delay === 'number' ? opts.delay : 300;
      const className = opts.className || '';

      const parent = typeof container === 'string' ? document.querySelector(container) : container;
      let element = null;
      let isDismissed = false;

      const timer = setTimeout(() => {
        if (isDismissed || !parent) return;
        element = document.createElement('div');
        element.className = `orbi-thinking-banner ${className}`.trim();
        element.setAttribute('role', 'status');
        element.setAttribute('aria-live', 'polite');

        const orbiEl = Orbi.create({ state: 'thinking', size: size, label: caption });
        const orbiHtml = orbiEl ? orbiEl.outerHTML : '';

        element.innerHTML = `
          <div class="orbi-thinking-inner">
            ${orbiHtml}
            <span class="orbi-thinking-caption">${caption}</span>
          </div>
        `;

        parent.appendChild(element);
      }, delay);

      return {
        dismiss: function () {
          isDismissed = true;
          clearTimeout(timer);
          if (element && element.parentNode) {
            element.parentNode.removeChild(element);
            element = null;
          }
        },
        isMounted: function () {
          return Boolean(element);
        }
      };
    }
  };

  window.Orbi = Orbi;
})(window, document);
