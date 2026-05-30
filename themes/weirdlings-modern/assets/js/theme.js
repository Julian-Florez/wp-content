(function () {
  const header = document.querySelector('.wl-site-header');
  const toggle = document.querySelector('[data-menu-toggle]');
  const mobileMenu = document.querySelector('[data-mobile-menu]');

  if (header) {
    const onScroll = () => {
      header.classList.toggle('is-scrolled', window.scrollY > 12);
    };

    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  if (toggle && mobileMenu) {
    toggle.addEventListener('click', () => {
      const isOpen = mobileMenu.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(isOpen));
    });
  }

  document.addEventListener('click', (event) => {
    const control = event.target.closest('.wl-qty-btn');

    if (!control) {
      return;
    }

    const cartControls = control.closest('.wl-cart-controls');
    const quantityField = cartControls ? cartControls.querySelector('input.qty') : null;

    if (!quantityField) {
      return;
    }

    event.preventDefault();

    const currentValue = Number.parseFloat(quantityField.value || '0');
    const step = Number.parseFloat(quantityField.step || '1') || 1;
    const min = quantityField.min !== '' ? Number.parseFloat(quantityField.min) : step;
    const max = quantityField.max !== '' ? Number.parseFloat(quantityField.max) : Number.POSITIVE_INFINITY;
    const nextValue = control.classList.contains('wl-qty-btn--minus')
      ? Math.max(min, currentValue - step)
      : Math.min(max, currentValue + step);

    quantityField.value = Number.isFinite(nextValue) ? String(nextValue) : String(min);
    quantityField.dispatchEvent(new Event('input', { bubbles: true }));
    quantityField.dispatchEvent(new Event('change', { bubbles: true }));
  });
})();

/* Ajustar posición del chat sticky para no tapar el footer */
(function () {
  function adjustChatSticky() {
    try {
      var chat = document.querySelector('.wl-chatbot-sticky');
      var footer = document.querySelector('.wl-footer');
      if (!chat || !footer) return;

      var footerRect = footer.getBoundingClientRect();
      var overlap = Math.max(0, window.innerHeight - footerRect.top);
      var baseGap = Math.max(12, Math.min(24, window.innerWidth * 0.02));
      if (overlap > 0) {
        chat.style.bottom = (overlap + baseGap) + 'px';
      } else {
        chat.style.bottom = '';
      }
    } catch (e) {
      // silent
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { adjustChatSticky(); });
  } else {
    adjustChatSticky();
  }

  window.addEventListener('scroll', adjustChatSticky, { passive: true });
  window.addEventListener('resize', adjustChatSticky);
})();

/* Añadir al carrito con botón + desde tarjetas de producto (AJAX) */
(function () {
  function updateFragments() {
    return fetch('?wc-ajax=get_refreshed_fragments', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.fragments) return;
        Object.keys(data.fragments).forEach(function (selector) {
          try {
            var el = document.querySelector(selector);
            if (el) el.innerHTML = data.fragments[selector];
          } catch (e) {
            // ignore invalid selectors
          }
        });
      })
      .catch(function () {});
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.wl-product-card__action');
    if (!btn) return;
    e.preventDefault();
    var pid = btn.dataset.productId || btn.getAttribute('data-product-id');
    if (!pid || Number(pid) === 0) {
      return;
    }

    btn.classList.add('is-loading');

    var body = new FormData();
    body.append('product_id', pid);
    body.append('quantity', '1');

    fetch('?wc-ajax=add_to_cart', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        btn.classList.remove('is-loading');
        btn.classList.add('is-added');
        setTimeout(function () { btn.classList.remove('is-added'); }, 1200);
        // refresh fragments (mini-cart, cart count)
        updateFragments();
      })
      .catch(function () {
        btn.classList.remove('is-loading');
      });
  });
})();

/* Inicializar Select2 (o SelectWoo) en selects del checkout si está disponible */
(function () {
  function initCheckoutSelects($) {
    var selectors = '#billing_country, #shipping_country, #billing_state, #shipping_state, select.country_to_state, select.state_select';
    var fn = $.fn.select2 ? 'select2' : ($.fn.selectWoo ? 'selectWoo' : null);
    if (!fn) {
      return;
    }

    $(selectors).each(function () {
      var $s = $(this);
      if ($s.next('.select2-container').length || $s.data('select2')) {
        return;
      }
      try {
        $s[fn]({ width: 'resolve' });
      } catch (e) {
        // ignore
      }
    });
  }

  if (window.jQuery) {
    window.jQuery(function ($) {
      initCheckoutSelects($);
      // Re-run after a short delays in case WC initializes later
      setTimeout(function () { initCheckoutSelects(window.jQuery); }, 800);
      setTimeout(function () { initCheckoutSelects(window.jQuery); }, 1600);

      // Re-initialize when WooCommerce updates the checkout via AJAX
      $(document.body).on('updated_checkout', function () {
        initCheckoutSelects(window.jQuery);
      });
    });
  }
})();

/* Fallback: crear dropdowns estilizados si el select nativo presenta problemas de contraste */
(function () {
  var selectors = ['#billing_country', '#shipping_country', '#billing_state', '#shipping_state'];

  function createFakeSelect(select) {
    if (!select || select.dataset.wlFake) return;
    // If Select2/SelectWoo is available globally, prefer it over fake select
    if (window.jQuery && (window.jQuery.fn.select2 || window.jQuery.fn.selectWoo)) {
      return;
    }
      // don't create fake if select is already enhanced (Select2/SelectWoo) or hidden
      if (select.nextElementSibling && (select.nextElementSibling.classList && select.nextElementSibling.classList.contains('select2-container'))) {
        return;
      }
      if (select.style.display === 'none' || select.offsetParent === null) {
        return;
      }
    var wrapper = document.createElement('div');
    wrapper.className = 'wl-fake-select';
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'wl-fake-select__button';
    var display = document.createElement('span');
    display.className = 'wl-fake-select__value';
    var dropdown = document.createElement('ul');
    dropdown.className = 'wl-fake-select__dropdown';

    Array.prototype.forEach.call(select.options, function (opt) {
      var li = document.createElement('li');
      li.className = 'wl-fake-select__option';
      li.textContent = opt.textContent || opt.innerText;
      li.dataset.value = opt.value;
      if (opt.selected) {
        display.textContent = li.textContent;
        li.classList.add('is-selected');
      }
      dropdown.appendChild(li);
      li.addEventListener('click', function () {
        select.value = this.dataset.value;
        // trigger change for dependent state updates
        var ev = new Event('change', { bubbles: true });
        select.dispatchEvent(ev);
        display.textContent = this.textContent;
        var prev = dropdown.querySelector('.is-selected');
        if (prev) prev.classList.remove('is-selected');
        this.classList.add('is-selected');
        dropdown.classList.remove('is-open');
        button.classList.remove('is-focused');
      });
    });

    button.appendChild(display);
    wrapper.appendChild(button);
    // add search input for long lists (placed inside dropdown)
    var search = document.createElement('input');
    search.type = 'search';
    search.className = 'wl-fake-select__search';
    search.placeholder = 'Buscar…';
    dropdown.appendChild(search);
    dropdown.appendChild(document.createElement('hr'));
    wrapper.appendChild(dropdown);
    select.parentNode.insertBefore(wrapper, select);
    // hide original select accessibly
    select.classList.add('wl-hidden-select');
    select.dataset.wlFake = '1';
    select.dataset.wlFake = '1';

    button.addEventListener('click', function (e) {
      e.preventDefault();
      var open = dropdown.classList.toggle('is-open');
      button.classList.toggle('is-focused', open);
      if (open) {
        // focus search input for quick filtering
        setTimeout(function () { try { search.focus(); } catch (err) {} }, 10);
      }
    });

    document.addEventListener('click', function (e) {
      if (!wrapper.contains(e.target)) {
        dropdown.classList.remove('is-open');
        button.classList.remove('is-focused');
      }
    });

    // filter options when typing in search
    search.addEventListener('input', function () {
      var q = (this.value || '').toLowerCase();
      Array.prototype.forEach.call(dropdown.children, function (li) {
        if (!li.classList || !li.dataset) return;
        var text = (li.textContent || '').toLowerCase();
        li.style.display = text.indexOf(q) !== -1 ? '' : 'none';
      });
    });

    // Observe select class changes to mirror validation state
    try {
      var obs = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
          if (m.attributeName === 'class') {
            var cls = select.className || '';
            var invalid = cls.indexOf('woocommerce-invalid') !== -1 || cls.indexOf('is-invalid') !== -1 || select.getAttribute('aria-invalid') === 'true';
            button.classList.toggle('is-invalid', invalid);
          }
        });
      });
      obs.observe(select, { attributes: true, attributeFilter: ['class', 'aria-invalid'] });
    } catch (e) {
      // ignore
    }
  }

  function initFakeSelects() {
    selectors.forEach(function (sel) {
      var el = document.querySelector(sel);
      if (el) {
        // only create if browser displays native dropdown white-on-white
        createFakeSelect(el);
      }
    });
  }

  // Run after DOM ready and after other retries
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { setTimeout(initFakeSelects, 500); });
  } else {
    setTimeout(initFakeSelects, 500);
  }
})();

(function () {
  var tablist = document.querySelector('[data-account-tabs]');
  if (!tablist) {
    return;
  }

  var tabButtons = Array.prototype.slice.call(tablist.querySelectorAll('[data-account-tab]'));
  var actionButtons = Array.prototype.slice.call(document.querySelectorAll('[data-account-tab-target]'));
  var logoutButton = document.querySelector('[data-account-logout-url]');
  var panels = Array.prototype.slice.call(document.querySelectorAll('[data-account-panel]'));

  function setActiveTab(tabId, updateHash) {
    if (!tabId) {
      tabId = tabButtons.length ? tabButtons[0].dataset.accountTab : 'dashboard';
    }

    tabButtons.forEach(function (button) {
      var isActive = button.dataset.accountTab === tabId;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-selected', String(isActive));
    });

    panels.forEach(function (panel) {
      var isActive = panel.dataset.accountPanel === tabId;
      panel.classList.toggle('is-active', isActive);
      panel.hidden = !isActive;
    });

    if (updateHash && window.history && window.history.replaceState) {
      window.history.replaceState(null, '', '#' + tabId);
    }
  }

  tablist.addEventListener('click', function (event) {
    var button = event.target.closest('[data-account-tab]');
    if (!button) {
      return;
    }

    event.preventDefault();
    setActiveTab(button.dataset.accountTab, true);
  });

  actionButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      setActiveTab(button.dataset.accountTabTarget, true);
    });
  });

  if (logoutButton) {
    logoutButton.addEventListener('click', function () {
      window.location.href = logoutButton.dataset.accountLogoutUrl;
    });
  }

  var initialTab = window.location.hash ? window.location.hash.replace('#', '') : '';
  if (!initialTab || !document.querySelector('[data-account-panel="' + initialTab + '"]')) {
    initialTab = tabButtons.length ? tabButtons[0].dataset.accountTab : 'dashboard';
  }

  setActiveTab(initialTab, false);
})();

(function () {
  var widget = document.querySelector('[data-chatbot-widget]');
  if (!widget) {
    return;
  }

  var toggleButton = widget.querySelector('[data-chatbot-toggle]');
  var panel = widget.querySelector('[data-chatbot-panel]');
  var closeButton = widget.querySelector('[data-chatbot-close]');
  var messages = widget.querySelector('[data-chatbot-messages]');
  var optionsWrap = widget.querySelector('[data-chatbot-options]');
  var form = widget.querySelector('[data-chatbot-form]');
  var input = widget.querySelector('[data-chatbot-input]');
  var fileInput = widget.querySelector('[data-chatbot-file]');
  var attachments = widget.querySelector('[data-chatbot-attachments]');
  var status = widget.querySelector('[data-chatbot-status]');

  if (!toggleButton || !panel || !form || !input) {
    return;
  }

  function setState(nextState, message) {
    toggleButton.dataset.chatbotState = nextState;
    if (status) {
      if (message) {
        status.hidden = false;
        status.textContent = message;
      } else {
        status.hidden = true;
        status.textContent = '';
      }
    }
  }

  function appendMessage(text, type) {
    if (!messages) {
      return;
    }
    var item = document.createElement('article');
    item.className = 'wl-chatbot-message wl-chatbot-message--' + type;
    var paragraph = document.createElement('p');
    paragraph.textContent = text;
    item.appendChild(paragraph);
    messages.appendChild(item);
    messages.scrollTop = messages.scrollHeight;
  }

  // Force inline styles on autofilled inputs inside account forms to override Chrome's autofill background
  function fixAutofillAccountInputs() {
    try {
      var root = document.querySelector('.wl-account-form-shell') || document;
      if (!root) return;
      var inputs = root.querySelectorAll('input, textarea, select');
      inputs.forEach(function (input) {
        try {
          var val = (input.value || '').toString().trim();
          if (val.length > 0) {
            input.style.setProperty('background-image', 'none', 'important');
            input.style.setProperty('background-color', getComputedStyle(document.documentElement).getPropertyValue('--wl-bg') || '#09070d', 'important');
            input.style.setProperty('color', getComputedStyle(document.documentElement).getPropertyValue('--wl-text') || '#f2f0ea', 'important');
            input.style.setProperty('box-shadow', '0 0 0 1000px ' + (getComputedStyle(document.documentElement).getPropertyValue('--wl-bg') || '#09070d') + ' inset', 'important');
            input.style.setProperty('caret-color', getComputedStyle(document.documentElement).getPropertyValue('--wl-accent-2') || '#d775b7', 'important');
          }
        } catch (e) {
          // ignore per-input errors
        }
      });
    } catch (e) {
      // silent
    }
  }

  function fixPasswordHintAndStrength() {
    try {
      var hint = document.querySelector('.wl-account-form-shell .woocommerce-password-hint');
      if (hint) {
        hint.style.setProperty('color', 'rgb(242, 240, 234)', 'important');
        hint.style.setProperty('background-color', 'rgba(0, 0, 0, 0.45)', 'important');
        hint.style.setProperty('padding', '0.45rem 0.6rem', 'important');
        hint.style.setProperty('border-radius', '10px', 'important');
        hint.style.setProperty('display', 'inline-block', 'important');
        hint.style.setProperty('margin-top', '0.5rem', 'important');
        hint.style.setProperty('-webkit-text-fill-color', 'rgb(242, 240, 234)', 'important');
      }

      var strength = document.querySelector('.wl-account-form-shell #password_strength, .wl-account-form-shell .woocommerce-password-strength');
      if (strength) {
        strength.style.setProperty('color', 'rgb(17, 17, 17)', 'important');
        strength.style.setProperty('-webkit-text-fill-color', 'rgb(17, 17, 17)', 'important');
        strength.style.setProperty('background-color', 'rgb(246, 214, 194)', 'important');
        strength.style.setProperty('padding', '0.45rem 0.6rem', 'important');
        strength.style.setProperty('border-radius', '10px', 'important');
        strength.style.setProperty('display', 'inline-block', 'important');
        strength.style.setProperty('margin-top', '0.5rem', 'important');
        strength.style.setProperty('text-shadow', 'none', 'important');
        strength.style.setProperty('opacity', '1', 'important');
      }
    } catch (e) {
      // silent
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      setTimeout(fixAutofillAccountInputs, 120);
      setTimeout(fixAutofillAccountInputs, 600);
      setTimeout(fixAutofillAccountInputs, 1500);
      setTimeout(fixPasswordHintAndStrength, 120);
      setTimeout(fixPasswordHintAndStrength, 600);
      setTimeout(fixPasswordHintAndStrength, 1500);
    });
  } else {
    setTimeout(fixAutofillAccountInputs, 120);
    setTimeout(fixAutofillAccountInputs, 600);
    setTimeout(fixAutofillAccountInputs, 1500);
    setTimeout(fixPasswordHintAndStrength, 120);
    setTimeout(fixPasswordHintAndStrength, 600);
    setTimeout(fixPasswordHintAndStrength, 1500);
  }

  // Also apply on focus/input and poll briefly to catch late autofill
  (function () {
    var root = document.querySelector('.wl-account-form-shell') || document;
    if (!root) return;
    var inputs = root.querySelectorAll('input, textarea, select');
    function applyIfFilled(e) {
      var input = e && e.target ? e.target : this;
      try {
        if ((input.value || '').toString().trim().length > 0) {
          input.style.setProperty('background-image', 'none', 'important');
          input.style.setProperty('background-color', getComputedStyle(document.documentElement).getPropertyValue('--wl-bg') || '#09070d', 'important');
          input.style.setProperty('color', getComputedStyle(document.documentElement).getPropertyValue('--wl-text') || '#f2f0ea', 'important');
          input.style.setProperty('box-shadow', '0 0 0 1000px ' + (getComputedStyle(document.documentElement).getPropertyValue('--wl-bg') || '#09070d') + ' inset', 'important');
        }
      } catch (er) {}
    }
    inputs.forEach(function (inp) {
      inp.addEventListener('focus', applyIfFilled);
      inp.addEventListener('input', applyIfFilled);
      inp.addEventListener('change', applyIfFilled);
    });

    var tries = 0;
    var iid = setInterval(function () {
      tries++;
      fixAutofillAccountInputs();
      fixPasswordHintAndStrength();
      if (tries > 8) clearInterval(iid);
    }, 300);
  })();

  /* Detect autofill via CSS animationstart and mark inputs with .wl-autofilled as a robust fallback */
  (function () {
    function markAutofilled(input) {
      try {
        if (!input || !input.classList) return;
        if (!input.classList.contains('wl-autofilled')) {
          input.classList.add('wl-autofilled');
          // extra inline fallback for stubborn Chrome versions
          input.style.setProperty('background-image', 'none', 'important');
          input.style.setProperty('box-shadow', '0 0 0 1000px ' + (getComputedStyle(document.documentElement).getPropertyValue('--wl-bg') || '#09070d') + ' inset', 'important');
          input.style.setProperty('color', getComputedStyle(document.documentElement).getPropertyValue('--wl-text') || '#f2f0ea', 'important');
          input.style.setProperty('caret-color', getComputedStyle(document.documentElement).getPropertyValue('--wl-accent-2') || '#d775b7', 'important');
        }
      } catch (e) { /* ignore */ }
    }

    document.addEventListener('animationstart', function (e) {
      try {
        if (!e || !e.animationName) return;
        if (e.animationName === 'onAutoFillStart') {
          var tgt = e.target;
          if (tgt && (tgt.tagName === 'INPUT' || tgt.tagName === 'TEXTAREA' || tgt.tagName === 'SELECT')) {
            markAutofilled(tgt);
          }
        }
      } catch (err) { }
    }, true);

    function scanAndMark() {
      try {
        var root = document.querySelector('.wl-account-form-shell') || document;
        if (!root) return;
        var inputs = root.querySelectorAll('input, textarea, select');
        Array.prototype.forEach.call(inputs, function (inp) {
          try {
            if ((inp.value || '').toString().trim().length > 0) {
              markAutofilled(inp);
            }
          } catch (e) { }
        });
      } catch (e) { }
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () { setTimeout(scanAndMark, 50); setTimeout(scanAndMark, 400); });
    } else {
      setTimeout(scanAndMark, 50); setTimeout(scanAndMark, 400);
    }

    try {
      var observerRoot = document.querySelector('.wl-account-form-shell') || document;
      var mo = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
          if (m.type === 'attributes' && (m.attributeName === 'value' || m.attributeName === 'class' || m.attributeName === 'style')) {
            var el = m.target;
            if (el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') && (el.value || '').toString().trim().length > 0) {
              markAutofilled(el);
            }
          }
        });
      });
      mo.observe(observerRoot, { attributes: true, subtree: true, attributeFilter: ['value', 'class', 'style'] });
    } catch (e) { }
  })();

  function clearOptions() {
    if (!optionsWrap) {
      return;
    }

    optionsWrap.innerHTML = '';
    optionsWrap.hidden = true;
  }

  function normalizeChatOptions(rawOptions) {
    if (!rawOptions) {
      return [];
    }

    var list = Array.isArray(rawOptions) ? rawOptions : [rawOptions];

    return list.map(function (option) {
      if (typeof option === 'string') {
        return {
          label: option,
          value: option
        };
      }

      if (option && typeof option === 'object') {
        return {
          label: option.label || option.text || option.title || option.name || option.value || option.payload || '',
          value: option.value || option.payload || option.label || option.text || option.title || option.name || ''
        };
      }

      return {
        label: '',
        value: ''
      };
    }).filter(function (option) {
      return Boolean((option.label || '').trim()) && Boolean((option.value || '').trim());
    });
  }

  function renderOptions(rawOptions) {
    if (!optionsWrap) {
      return;
    }

    var options = normalizeChatOptions(rawOptions);

    optionsWrap.innerHTML = '';

    if (!options.length) {
      optionsWrap.hidden = true;
      return;
    }

    options.forEach(function (option) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'wl-chatbot-option';
      button.dataset.chatbotOption = option.value;
      button.textContent = option.label;
      optionsWrap.appendChild(button);
    });

    optionsWrap.hidden = false;
  }

  function appendAttachmentMessage(files) {
    if (!messages || !files || !files.length) {
      return;
    }

    var item = document.createElement('article');
    item.className = 'wl-chatbot-message wl-chatbot-message--user wl-chatbot-message--media';

    var caption = document.createElement('p');
    caption.textContent = files.length === 1 ? 'Adjuntaste 1 archivo.' : 'Adjuntaste ' + files.length + ' archivos.';
    item.appendChild(caption);

    var gallery = document.createElement('div');
    gallery.className = 'wl-chatbot-message__media';

    Array.prototype.forEach.call(files, function (file) {
      var figure = document.createElement('figure');
      figure.className = 'wl-chatbot-media-tile';

      if (file.type && file.type.indexOf('image/') === 0) {
        var image = document.createElement('img');
        var objectUrl = URL.createObjectURL(file);
        image.src = objectUrl;
        image.alt = file.name;
        image.addEventListener('load', function () {
          window.setTimeout(function () {
            URL.revokeObjectURL(objectUrl);
          }, 0);
        });
        figure.appendChild(image);
      } else {
        var placeholder = document.createElement('div');
        placeholder.className = 'wl-chatbot-media-tile__file';
        placeholder.textContent = file.name;
        figure.appendChild(placeholder);
      }

      var meta = document.createElement('figcaption');
      meta.textContent = file.name;
      figure.appendChild(meta);
      gallery.appendChild(figure);
    });

    item.appendChild(gallery);
    messages.appendChild(item);
    messages.scrollTop = messages.scrollHeight;
  }

  function openPanel() {
    panel.hidden = false;
    panel.classList.add('is-open');
    toggleButton.setAttribute('aria-expanded', 'true');
    window.setTimeout(function () {
      input.focus();
    }, 80);
  }

  function closePanel() {
    panel.classList.remove('is-open');
    panel.hidden = true;
    toggleButton.setAttribute('aria-expanded', 'false');
  }

  function updateAttachmentList() {
    if (!attachments || !fileInput) {
      return;
    }

    attachments.innerHTML = '';

    if (!fileInput.files || !fileInput.files.length) {
      attachments.hidden = true;
      return;
    }

    Array.prototype.forEach.call(fileInput.files, function (file) {
      var li = document.createElement('li');
      li.textContent = file.name;
      attachments.appendChild(li);
    });

    attachments.hidden = false;
  }

  function getSessionId() {
    var storageKey = 'weirdlings_session';
    var sessionId = null;

    try {
      sessionId = window.localStorage.getItem(storageKey);
    } catch (error) {
      sessionId = null;
    }

    if (!sessionId) {
      if (window.crypto && typeof window.crypto.randomUUID === 'function') {
        sessionId = window.crypto.randomUUID();
      } else {
        sessionId = 'wl-' + Math.random().toString(36).slice(2) + Date.now().toString(36);
      }

      try {
        window.localStorage.setItem(storageKey, sessionId);
      } catch (error) {
        // Si el almacenamiento no está disponible, igual seguimos con la sesión generada.
      }
    }

    return sessionId;
  }

  function buildPayload(messageText) {
    return {
      event: 'chatbot_message',
      session_id: getSessionId(),
      source: 'front_page',
      message: messageText,
      page_url: window.location.href,
      page_title: document.title,
      timestamp: new Date().toISOString(),
      user_agent: navigator.userAgent
    };
  }

  function submitFallbackPost(url, payload, files) {
    var frameName = 'wl-chatbot-n8n-target';
    var frame = document.querySelector('iframe[name="' + frameName + '"]');
    if (!frame) {
      frame = document.createElement('iframe');
      frame.name = frameName;
      frame.hidden = true;
      document.body.appendChild(frame);
    }

    var fallbackForm = document.createElement('form');
    fallbackForm.method = 'POST';
    fallbackForm.action = url;
    fallbackForm.target = frameName;
    fallbackForm.enctype = 'multipart/form-data';
    fallbackForm.hidden = true;

    var payloadInput = document.createElement('input');
    payloadInput.type = 'hidden';
    payloadInput.name = 'payload';
    payloadInput.value = JSON.stringify(payload);
    fallbackForm.appendChild(payloadInput);

    if (files && files.length) {
      var mediaNames = document.createElement('input');
      mediaNames.type = 'hidden';
      mediaNames.name = 'media_names';
      mediaNames.value = JSON.stringify(Array.prototype.map.call(files, function (file) { return file.name; }));
      fallbackForm.appendChild(mediaNames);
    }

    document.body.appendChild(fallbackForm);
    fallbackForm.submit();
    fallbackForm.remove();
  }

  function parseChatbotResponse(result) {
    var reply = '';
    var options = [];

    if (typeof result === 'string') {
      reply = result;
    } else if (result && typeof result === 'object') {
      if (Array.isArray(result) && result.length && typeof result[0] === 'object') {
        var first = result[0];
        reply = first.reply || first.message || first.response || first.output || first.text || '';
        options = first.options || first.buttons || first.quick_replies || first.quickReplies || first.choices || [];
      } else {
        reply = result.reply || result.message || result.response || result.output || result.answer || result.text || '';
        options = result.options || result.buttons || result.quick_replies || result.quickReplies || result.choices || [];

        if (!reply && result.data && typeof result.data === 'object') {
          reply = result.data.reply || result.data.message || result.data.response || result.data.output || result.data.answer || result.data.text || '';
          options = options.length ? options : (result.data.options || result.data.buttons || result.data.quick_replies || result.data.quickReplies || result.data.choices || []);
        }

        if (!options.length && Array.isArray(result.messages)) {
          result.messages.forEach(function (message) {
            if (!reply && message && typeof message === 'object') {
              reply = message.reply || message.message || message.response || message.output || message.text || reply;
            }
            if (!options.length && message && typeof message === 'object') {
              options = message.options || message.buttons || message.quick_replies || message.quickReplies || message.choices || options;
            }
          });
        }
      }
    }

    if (!reply || !String(reply).trim()) {
      reply = 'Recibimos tu mensaje, pero n8n no devolvió texto de respuesta.';
    }

    return {
      reply: String(reply),
      options: normalizeChatOptions(options)
    };
  }

  function sendMessage(messageText, files) {
    var webhookUrl = toggleButton.dataset.chatbotWebhook;
    if (!webhookUrl || toggleButton.dataset.chatbotState === 'loading') {
      return Promise.reject(new Error('Webhook no disponible o chat en estado de carga.'));
    }

    setState('loading', 'Enviando a la manada...');

    var payload = buildPayload(messageText);
    var formData = new FormData();
    formData.append('payload', JSON.stringify(payload));

    if (files && files.length) {
      Array.prototype.forEach.call(files, function (file) {
        formData.append('media[]', file, file.name);
      });
    }

    return fetch(webhookUrl, {
      method: 'POST',
      headers: {
        Accept: 'application/json, text/plain;q=0.9, */*;q=0.8'
      },
      body: formData
    }).then(function (response) {
      if (!response.ok) {
        throw new Error('Error HTTP ' + response.status);
      }

      var contentType = (response.headers.get('content-type') || '').toLowerCase();
      if (contentType.indexOf('application/json') !== -1) {
        return response.json();
      }

      return response.text().then(function (text) {
        return { text: text };
      });
    }).then(function (result) {
      var response = parseChatbotResponse(result);

      setState('done', 'Respuesta recibida.');
      window.setTimeout(function () {
        setState('idle', '');
      }, 2400);

      return response;
    }).catch(function () {
      try {
        submitFallbackPost(webhookUrl, payload, files || []);
        setState('done', 'Mensaje enviado.');
        window.setTimeout(function () {
          setState('idle', '');
        }, 2400);
        return {
          reply: 'No pudimos leer la respuesta en tiempo real. El mensaje sí fue enviado a n8n.',
          options: []
        };
      } catch (error) {
        setState('error', 'No se pudo enviar. Intenta de nuevo.');
        throw error;
      }
    });
  }

  toggleButton.addEventListener('click', function () {
    if (panel.hidden) {
      openPanel();
    } else {
      closePanel();
    }
  });

  if (closeButton) {
    closeButton.addEventListener('click', closePanel);
  }

  if (optionsWrap) {
    optionsWrap.addEventListener('click', function (event) {
      var optionButton = event.target.closest('[data-chatbot-option]');
      if (!optionButton) {
        return;
      }
      var value = (optionButton.dataset.chatbotOption || '').trim();
      if (!value) {
        return;
      }
      input.value = value;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      clearOptions();
      form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    });
  }

  if (fileInput) {
    fileInput.addEventListener('change', updateAttachmentList);
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();

    var text = (input.value || '').trim();
    var files = fileInput && fileInput.files ? fileInput.files : [];
    var fallbackMessage = 'Hola, soy WeirdBot. ¿En qué puedo ayudarte?';
    var finalMessage = text || fallbackMessage;

    clearOptions();
    appendMessage(finalMessage, 'user');
    if (files.length) {
      appendAttachmentMessage(files);
    }

    sendMessage(finalMessage, files).then(function (response) {
      appendMessage(response.reply, 'bot');
      renderOptions(response.options);
    }).catch(function () {
      appendMessage('No pudimos conectar con n8n. Revisa el webhook y vuelve a intentar.', 'bot');
      clearOptions();
    });

    input.value = '';
    if (fileInput) {
      fileInput.value = '';
      updateAttachmentList();
    }

  });
})();

/* Productos relacionados en carrusel horizontal */
(function () {
  function getScrollStep(track) {
    var card = track ? track.querySelector('li.product') : null;
    if (!card) {
      return Math.max(240, Math.round(window.innerWidth * 0.75));
    }

    var rect = card.getBoundingClientRect();
    var gap = 16;
    try {
      var styles = window.getComputedStyle(track);
      var columnGap = parseFloat(styles.columnGap || styles.gap || '0');
      if (Number.isFinite(columnGap) && columnGap > 0) {
        gap = columnGap;
      }
    } catch (e) {
      // ignore
    }

    return Math.max(220, Math.round(rect.width + gap));
  }

  function updateControls(root, track) {
    var prev = root.querySelector('[data-related-scroll="prev"]');
    var next = root.querySelector('[data-related-scroll="next"]');
    if (!prev || !next || !track) {
      return;
    }

    var maxScroll = Math.max(0, track.scrollWidth - track.clientWidth - 1);
    prev.disabled = track.scrollLeft <= 0;
    next.disabled = track.scrollLeft >= maxScroll;
  }

  function applyRelatedCarouselLayout(root, track) {
    if (!root || !track) {
      return;
    }

    try {
      var viewport = root.querySelector('.wl-related-carousel__viewport');
      if (viewport) {
        viewport.style.setProperty('overflow', 'visible', 'important');
        viewport.style.setProperty('padding-top', '0.6rem', 'important');
        viewport.style.setProperty('padding-bottom', '0.6rem', 'important');
        viewport.style.setProperty('width', '100%', 'important');
        viewport.style.setProperty('max-width', '100%', 'important');
        viewport.style.setProperty('min-width', '0', 'important');
      }

      track.style.setProperty('display', 'flex', 'important');
      track.style.setProperty('flex-wrap', 'nowrap', 'important');
      track.style.setProperty('grid-template-columns', 'none', 'important');
      track.style.setProperty('grid-auto-flow', 'column', 'important');
      track.style.setProperty('grid-auto-columns', '290px', 'important');
      track.style.setProperty('align-items', 'stretch', 'important');
      track.style.setProperty('gap', '1rem', 'important');
      track.style.setProperty('width', '100%', 'important');
      track.style.setProperty('overflow-x', 'auto', 'important');
      track.style.setProperty('overflow-y', 'visible', 'important');
      track.style.setProperty('padding', '0.6rem clamp(0.75rem, 1.6vw, 1.2rem) 0.85rem', 'important');
      track.style.setProperty('margin', '0', 'important');
      track.style.setProperty('scroll-padding-inline', 'clamp(0.75rem, 1.6vw, 1.2rem)', 'important');

      var cards = track.querySelectorAll('li.product');
      cards.forEach(function (card) {
        card.style.setProperty('display', 'block', 'important');
        card.style.setProperty('flex', '0 0 290px', 'important');
        card.style.setProperty('width', '290px', 'important');
        card.style.setProperty('max-width', 'none', 'important');
        card.style.setProperty('margin', '0', 'important');
        card.style.setProperty('position', 'relative', 'important');
      });
    } catch (e) {
      // ignore
    }
  }

  function initRelatedCarousel(root) {
    var track = root.querySelector('.wl-related-carousel__track');
    if (!track) {
      return;
    }

    var prev = root.querySelector('[data-related-scroll="prev"]');
    var next = root.querySelector('[data-related-scroll="next"]');
    if (!prev || !next) {
      return;
    }

    var rafId = 0;
    function scheduleUpdate() {
      window.cancelAnimationFrame(rafId);
      rafId = window.requestAnimationFrame(function () {
        applyRelatedCarouselLayout(root, track);
        updateControls(root, track);
      });
    }

    prev.addEventListener('click', function () {
      track.scrollBy({ left: -getScrollStep(track), behavior: 'smooth' });
    });

    next.addEventListener('click', function () {
      track.scrollBy({ left: getScrollStep(track), behavior: 'smooth' });
    });

    track.addEventListener('scroll', scheduleUpdate, { passive: true });
    window.addEventListener('resize', scheduleUpdate);
    applyRelatedCarouselLayout(root, track);
    scheduleUpdate();
  }

  function boot() {
    document.querySelectorAll('[data-related-carousel]').forEach(initRelatedCarousel);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
