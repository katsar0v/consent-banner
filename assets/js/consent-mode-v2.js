(function () {
  var config = window.kdconsentBootstrapConfig || {};
  var storage = window.kdconsentStorage || null;
  var listeners = [];
  var options = {
    cookieName: config.cookieName || 'kdconsent_consent',
    legacyCookieName: config.legacyCookieName || 'kdcb_consent',
    storageKey: config.storageKey || 'kdconsent_consent_state',
    version: Number(config.consentVersion) || 1,
    consentLifetimeDays: Number(config.consentLifetimeDays) || 180
  };
  var consent = storage && storage.getCurrentConsent ? storage.getCurrentConsent(options) : null;

  function clone(value) {
    return value ? JSON.parse(JSON.stringify(value)) : null;
  }

  function consentModeState(currentConsent) {
    var categories = currentConsent && currentConsent.c ? currentConsent.c : {};
    var marketing = !!categories.marketing;

    return {
      ad_storage: marketing ? 'granted' : 'denied',
      ad_user_data: marketing ? 'granted' : 'denied',
      ad_personalization: marketing ? 'granted' : 'denied',
      analytics_storage: categories.analytics ? 'granted' : 'denied',
      personalization_storage: categories.preferences ? 'granted' : 'denied',
      functionality_storage: 'granted',
      security_storage: 'granted'
    };
  }

  function transmit(command, state) {
    if (config.transport === 'debug') {
      if (window.console && typeof window.console.log === 'function') {
        window.console.log('[kdconsent-consent-mode]', {
          command: command,
          state: clone(state)
        });
      }
      return;
    }

    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () {
      window.dataLayer.push(arguments);
    };
    window.gtag('consent', command, state);
  }

  function applyConsent(nextConsent) {
    consent = nextConsent || null;
  }

  window.kdconsentConsentMode = {
    stateFromConsent: consentModeState,
    defaultState: consentModeState(consent),
    update: function (nextConsent) {
      applyConsent(nextConsent);
      var state = consentModeState(consent);
      transmit('update', state);
      return clone(state);
    }
  };

  window.kdconsent = {
    isReady: true,
    _listeners: listeners,
    _setConsent: applyConsent,
    getConsent: function () {
      return clone(consent);
    },
    hasConsent: function (categoryId) {
      return !!(consent && consent.c && consent.c[categoryId]);
    },
    openPreferences: function () {
      document.dispatchEvent(new CustomEvent('kdconsent:open-preferences'));
    },
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

  transmit('default', window.kdconsentConsentMode.defaultState);

  document.addEventListener('kdconsent:changed', function (event) {
    window.kdconsentConsentMode.update(event.detail || null);
  });

  document.dispatchEvent(
    new CustomEvent('kdconsent:ready', {
      detail: {
        consent: clone(consent),
        consentMode: clone(window.kdconsentConsentMode.defaultState)
      }
    })
  );
})();
