(function () {
  var DEFAULT_COOKIE_NAME = 'kdconsent_consent';
  var DEFAULT_LEGACY_COOKIE_NAME = 'kdcb_consent';
  var DEFAULT_STORAGE_KEY = 'kdconsent_consent_state';
  var DAY_MS = 86400000;

  window.kdconsentStorage = {
    getCurrentConsent: getCurrentConsent,
    readCookieConsent: readCookieConsent,
    readLocalConsent: readLocalConsent,
    saveLocalConsent: saveLocalConsent,
    clearLocalConsent: clearLocalConsent,
    hasCurrentConsent: hasCurrentConsent,
    decodeCookieValue: decodeCookieValue
  };

  function getCurrentConsent(options) {
    var opts = normalizeOptions(options);
    var cookieConsent = readCookieConsent(opts);

    if (hasCurrentConsent(cookieConsent, opts)) {
      return cookieConsent;
    }

    var localConsent = readLocalConsent(opts);
    if (hasCurrentConsent(localConsent, opts)) {
      return localConsent;
    }

    if (localConsent) {
      clearLocalConsent(opts);
    }

    return null;
  }

  function readCookieConsent(options) {
    var opts = normalizeOptions(options);
    var names = [opts.cookieName, opts.legacyCookieName];
    var firstConsent = null;

    for (var i = 0; i < names.length; i++) {
      var name = names[i];
      if (!name) {
        continue;
      }

      var raw = readCookie(name);
      if (!raw) {
        continue;
      }

      var consent = decodeCookieValue(raw);
      if (!consent) {
        continue;
      }

      if (hasCurrentConsent(consent, opts)) {
        return consent;
      }

      firstConsent = firstConsent || consent;
    }

    return firstConsent;
  }

  function readLocalConsent(options) {
    var opts = normalizeOptions(options);
    var storage = localStorageSafe();

    if (!storage) {
      return null;
    }

    try {
      return normalizeConsent(JSON.parse(storage.getItem(opts.storageKey) || 'null'));
    } catch (e) {
      return null;
    }
  }

  function saveLocalConsent(consent, options) {
    var opts = normalizeOptions(options);
    var storage = localStorageSafe();
    var normalized = normalizeConsent(consent);

    if (!storage || !hasCurrentConsent(normalized, opts)) {
      return false;
    }

    try {
      storage.setItem(opts.storageKey, JSON.stringify(normalized));
      return true;
    } catch (e) {
      return false;
    }
  }

  function clearLocalConsent(options) {
    var opts = normalizeOptions(options);
    var storage = localStorageSafe();

    if (!storage) {
      return;
    }

    try {
      storage.removeItem(opts.storageKey);
    } catch (e) {
      // Ignore storage errors.
    }
  }

  function hasCurrentConsent(candidate, options) {
    var opts = normalizeOptions(options);
    var consent = normalizeConsent(candidate);

    return !!(
      consent &&
      Number(consent.v) === Number(opts.version) &&
      !isExpired(consent, opts)
    );
  }

  function decodeCookieValue(rawValue) {
    if (typeof rawValue !== 'string') {
      return null;
    }

    var parts = rawValue.split('.');
    if (parts.length !== 2) {
      return null;
    }

    var decoded = decodeBase64Url(parts[0]);
    if (decoded === null) {
      return null;
    }

    try {
      return normalizeConsent(JSON.parse(decoded));
    } catch (e) {
      return null;
    }
  }

  function decodeBase64Url(value) {
    var base64 = String(value).replace(/-/g, '+').replace(/_/g, '/');
    var remainder = base64.length % 4;

    if (remainder === 1) {
      return null;
    }

    if (remainder > 0) {
      base64 += new Array(5 - remainder).join('=');
    }

    try {
      return atob(base64);
    } catch (e) {
      return null;
    }
  }

  function normalizeConsent(candidate) {
    if (!candidate || typeof candidate !== 'object' || Array.isArray(candidate)) {
      return null;
    }

    var version = Number(candidate.v);
    var timestamp = Number(candidate.t);
    var categories = candidate.c;

    if (
      !isFinite(version) ||
      version < 1 ||
      !isFinite(timestamp) ||
      timestamp <= 0 ||
      !categories ||
      typeof categories !== 'object' ||
      Array.isArray(categories)
    ) {
      return null;
    }

    var normalizedCategories = {};
    Object.keys(categories).forEach(function (key) {
      normalizedCategories[key] = !!categories[key];
    });

    if (typeof normalizedCategories.essential === 'undefined') {
      normalizedCategories.essential = true;
    }

    return {
      v: Math.floor(version),
      t: Math.floor(timestamp),
      c: normalizedCategories
    };
  }

  function isExpired(consent, options) {
    var lifetimeDays = Number(options.consentLifetimeDays);

    if (!isFinite(lifetimeDays) || lifetimeDays <= 0) {
      return false;
    }

    return Date.now() > consent.t * 1000 + lifetimeDays * DAY_MS;
  }

  function readCookie(name) {
    if (typeof document === 'undefined' || typeof document.cookie !== 'string') {
      return null;
    }

    var match = document.cookie.match(new RegExp('(?:^|; )' + escapeRegExp(name) + '=([^;]*)'));
    if (!match) {
      return null;
    }

    try {
      return decodeURIComponent(match[1]);
    } catch (e) {
      return null;
    }
  }

  function localStorageSafe() {
    try {
      if (!window.localStorage) {
        return null;
      }

      return window.localStorage;
    } catch (e) {
      return null;
    }
  }

  function normalizeOptions(options) {
    options = options && typeof options === 'object' ? options : {};

    return {
      cookieName: String(options.cookieName || DEFAULT_COOKIE_NAME),
      legacyCookieName: String(options.legacyCookieName || DEFAULT_LEGACY_COOKIE_NAME),
      storageKey: String(options.storageKey || DEFAULT_STORAGE_KEY),
      version: Number(options.version) || 1,
      consentLifetimeDays: Number(options.consentLifetimeDays) || 0
    };
  }

  function escapeRegExp(value) {
    return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }
})();
