(function () {
  var bootstrap = window.kdconsentLoaderConfig || {};
  var listeners = [];
  var configPromise = null;
  var uiPromise = null;
  var consentVersion = Number(bootstrap.consentVersion) || 1;
  var storage = window.kdconsentStorage || null;
  var consent = readStoredConsent({ consentVersion: consentVersion });

  var runtime = {
    listeners: listeners,
    getConsent: function () {
      return consent;
    },
    setConsent: function (nextConsent) {
      consent = nextConsent || null;
    }
  };

  installApi();
  bindPreferenceTriggers();

  if (!consent) {
    maybeShowBanner();
  }

  function installApi() {
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
          var index = listeners.indexOf(callback);
          if (index !== -1) {
            listeners.splice(index, 1);
          }
        };
      }
    };

    window.kdcb = window.kdconsent;
  }

  function bindPreferenceTriggers() {
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
  }

  function openPreferences() {
    return loadConfig()
      .then(initializeUi)
      .then(function (api) {
        if (api && typeof api.openPreferences === 'function') {
          api.openPreferences();
        }
      });
  }

  function maybeShowBanner() {
    loadConfig()
      .then(function (config) {
        var storedConsent = readStoredConsent(config);
        if (storedConsent) {
          runtime.setConsent(storedConsent);
          return null;
        }

        if (hasCurrentConsent(config.consent, config.consentVersion)) {
          runtime.setConsent(config.consent);
          return null;
        }

        return initializeUi(config);
      })
      .catch(function () {
        return null;
      });
  }

  function loadConfig() {
    if (configPromise) {
      return configPromise;
    }

    configPromise = fetch(configEndpoint(), {
      method: 'GET',
      headers: {
        'Cache-Control': 'no-cache',
        Pragma: 'no-cache'
      },
      credentials: 'same-origin',
      cache: 'no-store'
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Request failed');
        }

        return response.json();
      })
      .then(function (config) {
        config = config && typeof config === 'object' ? config : {};
        config.loadedFromRest = true;
        return prepareConfig(config);
      })
      .catch(function () {
        var config = fallbackConfig();
        config.loadedFromRest = false;
        return prepareConfig(config);
      });

    return configPromise;
  }

  function prepareConfig(config) {
    config = config && typeof config === 'object' ? config : {};
    config.restRoot = bootstrap.restRoot || config.restRoot || '';
    config.cookieName = bootstrap.cookieName || config.cookieName || 'kdconsent_consent';
    config.legacyCookieName = bootstrap.legacyCookieName || config.legacyCookieName || 'kdcb_consent';
    config.storageKey = bootstrap.storageKey || config.storageKey || 'kdconsent_consent_state';
    config.consentVersion = Number(config.consentVersion) || consentVersion;
    consentVersion = config.consentVersion;

    var storedConsent = readStoredConsent(config);
    if (storedConsent) {
      runtime.setConsent(storedConsent);
    } else if (hasCurrentConsent(config.consent, config.consentVersion)) {
      runtime.setConsent(config.consent);
    }

    window.kdconsentConfig = config;

    return config;
  }

  function initializeUi(config) {
    if (uiPromise) {
      return uiPromise;
    }

    uiPromise = loadStyle()
      .then(loadScript)
      .then(function () {
        if (typeof window.kdconsentInitBanner !== 'function') {
          throw new Error('Banner UI failed to load');
        }

        return window.kdconsentInitBanner(config, runtime);
      })
      .catch(function () {
        return null;
      });

    return uiPromise;
  }

  function loadStyle() {
    var href = bootstrap.assets && bootstrap.assets.style ? bootstrap.assets.style : '';
    if (!href || document.getElementById('kdconsent-banner-style')) {
      return Promise.resolve();
    }

    return new Promise(function (resolve) {
      var link = document.createElement('link');
      link.id = 'kdconsent-banner-style';
      link.rel = 'stylesheet';
      link.href = href;
      link.onload = resolve;
      link.onerror = resolve;
      document.head.appendChild(link);
    });
  }

  function loadScript() {
    var src = bootstrap.assets && bootstrap.assets.script ? bootstrap.assets.script : '';
    if (typeof window.kdconsentInitBanner === 'function') {
      return Promise.resolve();
    }

    if (!src) {
      return Promise.reject(new Error('Missing banner UI script'));
    }

    return new Promise(function (resolve, reject) {
      var existing = document.getElementById('kdconsent-banner-ui-script');
      if (existing) {
        existing.addEventListener('load', resolve, { once: true });
        existing.addEventListener('error', reject, { once: true });
        return;
      }

      var script = document.createElement('script');
      script.id = 'kdconsent-banner-ui-script';
      script.src = src;
      script.async = true;
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });
  }

  function hasCurrentConsent(candidate, version) {
    return !!(
      candidate &&
      typeof candidate === 'object' &&
      candidate.c &&
      Number(candidate.v) === Number(version || consentVersion)
    );
  }

  function readStoredConsent(config) {
    if (!storage || typeof storage.getCurrentConsent !== 'function') {
      return null;
    }

    return storage.getCurrentConsent(storageOptions(config));
  }

  function storageOptions(config) {
    var source = config && typeof config === 'object' ? config : {};
    var bootstrapConfig = bootstrap.config && typeof bootstrap.config === 'object' ? bootstrap.config : {};
    var behavior = source.behavior && typeof source.behavior === 'object' ? source.behavior : {};
    var bootstrapBehavior =
      bootstrapConfig.behavior && typeof bootstrapConfig.behavior === 'object' ? bootstrapConfig.behavior : {};

    return {
      cookieName: source.cookieName || bootstrap.cookieName || 'kdconsent_consent',
      legacyCookieName: source.legacyCookieName || bootstrap.legacyCookieName || 'kdcb_consent',
      storageKey: source.storageKey || bootstrap.storageKey || 'kdconsent_consent_state',
      version: Number(source.consentVersion) || consentVersion || 1,
      consentLifetimeDays:
        Number(behavior.consentLifetimeDays) || Number(bootstrapBehavior.consentLifetimeDays) || 180
    };
  }

  function configEndpoint() {
    return cacheBust(String(bootstrap.restRoot || '') + 'config');
  }

  function cacheBust(url) {
    var separator = url.indexOf('?') === -1 ? '?' : '&';
    return url + separator + '_kdconsent=' + String(Date.now());
  }

  function fallbackConfig() {
    if (bootstrap.config && typeof bootstrap.config === 'object') {
      return bootstrap.config;
    }

    return {
      locale: 'en_US',
      texts: {},
      categories: [
        {
          id: 'essential',
          label: 'Essential',
          description: 'Required for basic website functionality.',
          required: true,
          enabledByDefault: true
        }
      ],
      behavior: {
        consentLifetimeDays: 180,
        position: 'bottom',
        showRejectButton: true,
        animation: 'fade-in',
        showDelayMs: 0,
        styles: {}
      },
      consentVersion: consentVersion,
      consent: consent
    };
  }

})();
