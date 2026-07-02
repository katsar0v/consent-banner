(function () {
  window.kdconsentInitBanner = function (loadedConfig, runtime) {
  var config = loadedConfig || window.kdconsentConfig || {};
  runtime = runtime || {};
  var root = ensureRoot();
  var categories = Array.isArray(config.categories) ? config.categories : [];
  var listeners = Array.isArray(runtime.listeners) ? runtime.listeners : [];
  var categoryInputs = {};
  var consentVersion = Number(config.consentVersion) || 1;
  var storage = window.kdconsentStorage || null;
  var storageOptions = buildStorageOptions();
  var storedConsent = readStoredConsent();
  var consent = null;
  var runtimeConsent = typeof runtime.getConsent === 'function' ? runtime.getConsent() : null;

  if (hasCurrentConsent(config.consent)) {
    consent = config.consent;
  } else if (hasCurrentConsent(runtimeConsent)) {
    consent = runtimeConsent;
  } else if (storedConsent) {
    consent = storedConsent;
  }

  if (!root || categories.length === 0) {
    return window.kdconsent || null;
  }

  var texts = config.texts || {};
  var labels = {
    bannerTitle: texts.bannerTitle || 'We use cookies',
    bannerBody:
      texts.bannerBody ||
      'We use cookies to improve your experience. You can accept all, reject non-essential cookies, or customize your choices.',
    acceptAllLabel: texts.acceptAllLabel || 'Accept all',
    rejectAllLabel: texts.rejectAllLabel || 'Reject all',
    customizeLabel: texts.customizeLabel || 'Customize',
    saveLabel: texts.saveLabel || 'Save preferences',
    closeLabel: texts.closeLabel || 'Close',
    preferencesTitle: texts.preferencesTitle || 'Cookie preferences'
  };

  var behavior = config.behavior || {};
  var showRejectButton = behavior.showRejectButton !== false;
  var styleSettings = behavior.styles && typeof behavior.styles === 'object' ? behavior.styles : {};
  var animationType = normalizeAnimation(behavior.animation);
  var showDelayMs = normalizeDelay(behavior.showDelayMs);
  var animationClasses = [
    'kdconsent-anim-fade-in',
    'kdconsent-anim-slide-in-up',
    'kdconsent-anim-slide-in-left',
    'kdconsent-anim-slide-in-right',
    'kdconsent-anim-slide-in-down',
    'kdconsent-anim-blur-in'
  ];

  var wrapper = document.createElement('div');
  wrapper.className =
    'kdconsent-banner kdconsent-position-' + (behavior.position || 'bottom');

  var title = document.createElement('h3');
  title.className = 'kdconsent-banner-title';
  title.textContent = labels.bannerTitle;

  var body = document.createElement('p');
  body.className = 'kdconsent-banner-body';
  body.textContent = labels.bannerBody;

  var actions = document.createElement('div');
  actions.className = 'kdconsent-banner-actions';

  var acceptButton = buildButton(labels.acceptAllLabel, 'kdconsent-btn kdconsent-btn-primary kdconsent-btn-accept');
  var rejectButton = buildButton(labels.rejectAllLabel, 'kdconsent-btn kdconsent-btn-secondary kdconsent-btn-reject');
  var customizeButton = buildButton(labels.customizeLabel, 'kdconsent-btn kdconsent-btn-tertiary kdconsent-btn-customize');

  actions.appendChild(acceptButton);
  if (showRejectButton) {
    actions.appendChild(rejectButton);
  }
  actions.appendChild(customizeButton);

  wrapper.appendChild(title);
  wrapper.appendChild(body);
  wrapper.appendChild(actions);
  wrapper.hidden = true;

  var bannerBackdrop = document.createElement('div');
  bannerBackdrop.className = 'kdconsent-banner-overlay';
  setBannerBackdropVisibility(false);

  var modalOverlay = document.createElement('div');
  modalOverlay.className = 'kdconsent-modal-overlay';
  setModalVisibility(false);

  var modal = document.createElement('div');
  modal.className = 'kdconsent-modal';

  var modalTitle = document.createElement('h3');
  modalTitle.className = 'kdconsent-modal-title';
  modalTitle.textContent = labels.preferencesTitle;

  var modalBody = document.createElement('div');
  modalBody.className = 'kdconsent-modal-body';

  var modalActions = document.createElement('div');
  modalActions.className = 'kdconsent-modal-actions';

  var saveButton = buildButton(labels.saveLabel, 'kdconsent-btn kdconsent-btn-primary kdconsent-btn-save');
  var closeButton = buildButton(labels.closeLabel, 'kdconsent-btn kdconsent-btn-tertiary kdconsent-btn-close');

  modalActions.appendChild(saveButton);
  modalActions.appendChild(closeButton);

  modal.appendChild(modalTitle);
  modal.appendChild(modalBody);
  modal.appendChild(modalActions);
  modalOverlay.appendChild(modal);

  root.appendChild(bannerBackdrop);
  root.appendChild(wrapper);
  root.appendChild(modalOverlay);

  applyAppearanceStyles(styleSettings, modalOverlay, bannerBackdrop);
  initializeBannerVisibility();

  categories.forEach(function (category) {
    var item = document.createElement('div');
    item.className = 'kdconsent-modal-item';

    var left = document.createElement('div');
    left.className = 'kdconsent-modal-item-text';

    var label = document.createElement('strong');
    label.textContent = category.label || category.id;

    var desc = document.createElement('span');
    desc.textContent = category.description || '';

    left.appendChild(label);
    left.appendChild(desc);

    var input = document.createElement('input');
    input.type = 'checkbox';
    input.checked = isCategoryChecked(category, consent);
    input.disabled = !!category.required || category.id === 'essential';

    categoryInputs[category.id] = input;

    item.appendChild(left);
    item.appendChild(input);
    modalBody.appendChild(item);
  });

  acceptButton.addEventListener('click', function () {
    var next = {};
    categories.forEach(function (category) {
      next[category.id] = true;
    });
    next.essential = true;
    submitConsent(next);
  });

  rejectButton.addEventListener('click', function () {
    var next = {};
    categories.forEach(function (category) {
      next[category.id] = !!category.required || category.id === 'essential' ? true : false;
    });
    next.essential = true;
    submitConsent(next);
  });

  customizeButton.addEventListener('click', openPreferences);
  closeButton.addEventListener('click', closePreferences);

  saveButton.addEventListener('click', function () {
    var next = {};
    categories.forEach(function (category) {
      var input = categoryInputs[category.id];
      next[category.id] = input ? !!input.checked : false;
      if (category.required || category.id === 'essential') {
        next[category.id] = true;
      }
    });
    next.essential = true;
    submitConsent(next);
  });

  document.addEventListener('click', function (event) {
    var target = event.target;
    if (!target || !target.closest) {
      return;
    }

    var trigger = target.closest('.kdconsent-open-preferences, .kdcb-open-preferences');
    if (!trigger) {
      return;
    }

    event.preventDefault();
    openPreferences();
  });

  window.kdconsent = {
    getConsent: function () {
      return consent ? JSON.parse(JSON.stringify(consent)) : null;
    },
    hasConsent: function (categoryId) {
      if (!consent || !consent.c) {
        return false;
      }
      return !!consent.c[categoryId];
    },
    openPreferences: openPreferences,
    onChange: function (callback) {
      if (typeof callback !== 'function') {
        return function () {};
      }
      listeners.push(callback);
      return function () {
        listeners = listeners.filter(function (item) {
          return item !== callback;
        });
      };
    }
  };
  window.kdcb = window.kdconsent;

  function buildButton(label, className) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = className;
    button.textContent = label;
    return button;
  }

  function isCategoryChecked(category, currentConsent) {
    if (category.required || category.id === 'essential') {
      return true;
    }

    if (currentConsent && currentConsent.c && typeof currentConsent.c[category.id] !== 'undefined') {
      return !!currentConsent.c[category.id];
    }

    return !!category.enabledByDefault;
  }

  function initializeBannerVisibility() {
    if (consent) {
      wrapper.hidden = true;
      setBannerBackdropVisibility(false);
      return;
    }

    if (showDelayMs > 0) {
      window.setTimeout(function () {
        showBanner();
      }, showDelayMs);
      return;
    }

    showBanner();
  }

  function showBanner() {
    wrapper.hidden = false;
    setBannerBackdropVisibility(true);
    applyBannerAnimation();
  }

  function applyBannerAnimation() {
    var className = 'kdconsent-anim-' + animationType;

    wrapper.classList.remove('kdconsent-anim-enter');
    animationClasses.forEach(function (item) {
      wrapper.classList.remove(item);
    });

    void wrapper.offsetWidth;

    wrapper.classList.add('kdconsent-anim-enter');
    wrapper.classList.add(className);

    wrapper.addEventListener(
      'animationend',
      function () {
        wrapper.classList.remove('kdconsent-anim-enter');
        wrapper.classList.remove(className);
      },
      { once: true }
    );
  }

  function openPreferences() {
    setBannerBackdropVisibility(false);
    setModalVisibility(true);
  }

  function closePreferences() {
    setModalVisibility(false);
    if (!wrapper.hidden && !consent) {
      setBannerBackdropVisibility(true);
    }
  }

  function setModalVisibility(isVisible) {
    modalOverlay.style.setProperty('display', isVisible ? 'grid' : 'none', 'important');
    modalOverlay.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
  }

  function setBannerBackdropVisibility(isVisible) {
    bannerBackdrop.style.setProperty('display', isVisible ? 'block' : 'none', 'important');
    bannerBackdrop.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
  }

  function applyAppearanceStyles(styles, modalOverlayElement, bannerOverlayElement) {
    if (!styles || typeof styles !== 'object') {
      return;
    }

    var backdrop = styles.backdrop && typeof styles.backdrop === 'object' ? styles.backdrop : {};
    var backdropColor = normalizeHexColor(backdrop.color);
    var backdropOpacity = normalizeOpacity(backdrop.opacity);
    var backdropValue = toRgba(backdropColor || '#000000', backdropOpacity);

    root.style.setProperty('--kdconsent-backdrop', backdropValue);
    if (modalOverlayElement && modalOverlayElement.style) {
      modalOverlayElement.style.setProperty('background', backdropValue, 'important');
    }
    if (bannerOverlayElement && bannerOverlayElement.style) {
      bannerOverlayElement.style.setProperty('background', backdropValue, 'important');
    }

    var buttonStyles = styles.buttons && typeof styles.buttons === 'object' ? styles.buttons : {};
    var colorKeyMap = {
      background: 'bg',
      text: 'text',
      border: 'border',
      hoverBackground: 'hover-bg',
      hoverText: 'hover-text',
      hoverBorder: 'hover-border'
    };

    Object.keys(buttonStyles).forEach(function (buttonKey) {
      var buttonConfig = buttonStyles[buttonKey];
      if (!buttonConfig || typeof buttonConfig !== 'object') {
        return;
      }

      Object.keys(colorKeyMap).forEach(function (settingKey) {
        var color = normalizeHexColor(buttonConfig[settingKey]);
        if (!color) {
          return;
        }

        root.style.setProperty('--kdconsent-btn-' + buttonKey + '-' + colorKeyMap[settingKey], color);
      });
    });
  }

  function normalizeHexColor(value) {
    if (typeof value !== 'string') {
      return null;
    }

    var color = value.trim();
    var shortMatch = /^#([0-9a-fA-F]{3})$/.exec(color);
    if (shortMatch) {
      var chars = shortMatch[1];
      return (
        '#' +
        chars.charAt(0) +
        chars.charAt(0) +
        chars.charAt(1) +
        chars.charAt(1) +
        chars.charAt(2) +
        chars.charAt(2)
      ).toUpperCase();
    }

    if (/^#([0-9a-fA-F]{6})$/.test(color)) {
      return color.toUpperCase();
    }

    return null;
  }

  function normalizeOpacity(value) {
    var numeric = Number(value);
    if (!isFinite(numeric)) {
      return 0.45;
    }

    if (numeric < 0) {
      return 0;
    }

    if (numeric > 1) {
      return 1;
    }

    return Math.round(numeric * 100) / 100;
  }

  function normalizeAnimation(value) {
    var allowed = {
      'fade-in': true,
      'slide-in-up': true,
      'slide-in-left': true,
      'slide-in-right': true,
      'slide-in-down': true,
      'blur-in': true
    };

    if (typeof value !== 'string') {
      return 'fade-in';
    }

    return allowed[value] ? value : 'fade-in';
  }

  function normalizeDelay(value) {
    var numeric = Number(value);

    if (!isFinite(numeric)) {
      return 0;
    }

    numeric = Math.round(numeric);

    if (numeric < 0) {
      return 0;
    }

    if (numeric > 10000) {
      return 10000;
    }

    return numeric;
  }

  function toRgba(hexColor, opacity) {
    var rgb = hexToRgb(hexColor);
    if (!rgb) {
      return 'rgba(0, 0, 0, ' + String(opacity) + ')';
    }

    return 'rgba(' + rgb.r + ', ' + rgb.g + ', ' + rgb.b + ', ' + String(opacity) + ')';
  }

  function hexToRgb(hexColor) {
    var normalized = normalizeHexColor(hexColor);
    if (!normalized) {
      return null;
    }

    return {
      r: parseInt(normalized.slice(1, 3), 16),
      g: parseInt(normalized.slice(3, 5), 16),
      b: parseInt(normalized.slice(5, 7), 16)
    };
  }

  function submitConsent(nextCategories) {
    fetch(String(config.restRoot || '') + 'consent', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Cache-Control': 'no-cache',
        Pragma: 'no-cache'
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        categories: nextCategories
      })
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Request failed');
        }
        return response.json();
      })
      .then(function (payload) {
        applyConsent(payload, true);
      })
      .catch(function () {
        applyConsent({
          v: consentVersion,
          t: Math.floor(Date.now() / 1000),
          c: nextCategories
        }, false);
      });
  }

  function applyConsent(nextConsent, shouldPersistLocalFallback) {
    consent = nextConsent;
    if (typeof runtime.setConsent === 'function') {
      runtime.setConsent(consent);
    }

    if (shouldPersistLocalFallback) {
      saveStoredConsent(consent);
    }

    wrapper.hidden = true;
    setBannerBackdropVisibility(false);
    closePreferences();

    listeners.forEach(function (listener) {
      try {
        listener(consent);
      } catch (error) {
        // Ignore callback errors from third-party code.
      }
    });

    document.dispatchEvent(
      new CustomEvent('kdconsent:consent-changed', {
        detail: consent
      })
    );
    document.dispatchEvent(
      new CustomEvent('kdcb:consent-changed', {
        detail: consent
      })
    );
  }

  function hasCurrentConsent(candidate) {
    if (storage && typeof storage.hasCurrentConsent === 'function') {
      return storage.hasCurrentConsent(candidate, storageOptions);
    }

    return !!(
      candidate &&
      typeof candidate === 'object' &&
      candidate.c &&
      Number(candidate.v) === consentVersion
    );
  }

  function readStoredConsent() {
    if (!storage || typeof storage.getCurrentConsent !== 'function') {
      return null;
    }

    return storage.getCurrentConsent(storageOptions);
  }

  function saveStoredConsent(nextConsent) {
    if (!storage || typeof storage.saveLocalConsent !== 'function') {
      return false;
    }

    return storage.saveLocalConsent(nextConsent, storageOptions);
  }

  function buildStorageOptions() {
    var behavior = config.behavior && typeof config.behavior === 'object' ? config.behavior : {};

    return {
      cookieName: config.cookieName || 'kdconsent_consent',
      legacyCookieName: config.legacyCookieName || 'kdcb_consent',
      storageKey: config.storageKey || 'kdconsent_consent_state',
      version: consentVersion,
      consentLifetimeDays: Number(behavior.consentLifetimeDays) || 180
    };
  }
  return window.kdconsent;
  };

  function ensureRoot() {
    var existing = document.getElementById('kdconsent-banner-root');
    if (existing) {
      return existing;
    }

    if (!document.body) {
      return null;
    }

    var root = document.createElement('div');
    root.id = 'kdconsent-banner-root';
    document.body.appendChild(root);

    return root;
  }
})();
