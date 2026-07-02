const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const helperPath = path.resolve(__dirname, '../../assets/js/consent-storage.js');
const helperSource = fs.readFileSync(helperPath, 'utf8');
const storageKey = 'kdconsent_consent_state';
const now = Math.floor(Date.now() / 1000);
const options = {
  cookieName: 'kdconsent_consent',
  legacyCookieName: 'kdcb_consent',
  storageKey,
  version: 1,
  consentLifetimeDays: 180
};

const tests = [];

function test(name, callback) {
  tests.push({ name, callback });
}

function createStorage(initialValues) {
  const values = Object.assign({}, initialValues || {});

  return {
    getItem(key) {
      return Object.prototype.hasOwnProperty.call(values, key) ? values[key] : null;
    },
    setItem(key, value) {
      values[key] = String(value);
    },
    removeItem(key) {
      delete values[key];
    },
    dump() {
      return Object.assign({}, values);
    }
  };
}

function loadHelper({ cookie = '', storage = createStorage(), disabledStorage = false } = {}) {
  const browserWindow = {};

  if (disabledStorage) {
    Object.defineProperty(browserWindow, 'localStorage', {
      get() {
        throw new Error('localStorage disabled');
      }
    });
  } else {
    browserWindow.localStorage = storage;
  }

  const context = {
    window: browserWindow,
    document: { cookie },
    atob(value) {
      return Buffer.from(value, 'base64').toString('binary');
    },
    Date,
    Array,
    JSON,
    Math,
    Number,
    Object,
    RegExp,
    String,
    decodeURIComponent,
    encodeURIComponent,
    isFinite
  };

  vm.createContext(context);
  vm.runInContext(helperSource, context, { filename: helperPath });

  return {
    api: context.window.kdconsentStorage,
    storage
  };
}

function cookieValue(state) {
  const encoded = encodedPayload(state);
  return encodeURIComponent(`${encoded}.signature`);
}

function encodedPayload(state) {
  return Buffer.from(JSON.stringify(state), 'utf8')
    .toString('base64')
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/, '');
}

function stateWithBase64Remainder(remainder) {
  for (let index = 0; index < 20; index += 1) {
    const categories = { essential: true };
    for (let categoryIndex = 0; categoryIndex < index; categoryIndex += 1) {
      categories[`category_${categoryIndex}`] = categoryIndex % 2 === 0;
    }

    const state = {
      v: 1,
      t: now,
      c: categories
    };

    if (encodedPayload(state).length % 4 === remainder) {
      return state;
    }
  }

  throw new Error(`Unable to build payload with remainder ${remainder}`);
}

function plain(value) {
  return JSON.parse(JSON.stringify(value));
}

test('reads unpadded base64url cookie payloads', () => {
  const state = stateWithBase64Remainder(2);
  const { api } = loadHelper({
    cookie: `kdconsent_consent=${cookieValue(state)}`
  });

  assert.deepStrictEqual(plain(api.getCurrentConsent(options)), state);
});

test('reads the configured legacy cookie name', () => {
  const state = { v: 1, t: now, c: { essential: true, analytics: false } };
  const { api } = loadHelper({
    cookie: `kdcb_consent=${cookieValue(state)}`
  });

  assert.deepStrictEqual(plain(api.getCurrentConsent(options)), state);
});

test('uses current legacy cookie when primary cookie is stale', () => {
  const stale = { v: 2, t: now, c: { essential: true, analytics: true } };
  const current = { v: 1, t: now, c: { essential: true, analytics: false } };
  const { api } = loadHelper({
    cookie: `kdconsent_consent=${cookieValue(stale)}; kdcb_consent=${cookieValue(current)}`
  });

  assert.deepStrictEqual(plain(api.getCurrentConsent(options)), current);
});

test('ignores malformed cookie values', () => {
  const { api } = loadHelper({
    cookie: 'kdconsent_consent=not-a-signed-payload'
  });

  assert.strictEqual(api.getCurrentConsent(options), null);
});

test('ignores malformed URI cookie encoding', () => {
  const { api } = loadHelper({
    cookie: 'kdconsent_consent=%E0%A4%A'
  });

  assert.strictEqual(api.getCurrentConsent(options), null);
});

test('ignores consent with a stale version', () => {
  const state = { v: 2, t: now, c: { essential: true } };
  const { api } = loadHelper({
    cookie: `kdconsent_consent=${cookieValue(state)}`
  });

  assert.strictEqual(api.getCurrentConsent(options), null);
});

test('uses localStorage fallback when no current cookie exists', () => {
  const state = { v: 1, t: now, c: { essential: true, analytics: true } };
  const storage = createStorage({
    [storageKey]: JSON.stringify(state)
  });
  const { api } = loadHelper({ storage });

  assert.deepStrictEqual(plain(api.getCurrentConsent(options)), state);
});

test('clears expired localStorage fallback', () => {
  const state = {
    v: 1,
    t: now - 181 * 86400,
    c: { essential: true, analytics: true }
  };
  const storage = createStorage({
    [storageKey]: JSON.stringify(state)
  });
  const { api } = loadHelper({ storage });

  assert.strictEqual(api.getCurrentConsent(options), null);
  assert.strictEqual(storage.dump()[storageKey], undefined);
});

test('handles disabled localStorage without throwing', () => {
  const { api } = loadHelper({ disabledStorage: true });

  assert.strictEqual(api.getCurrentConsent(options), null);
  assert.strictEqual(api.saveLocalConsent({ v: 1, t: now, c: { essential: true } }, options), false);
});

test('saves only current-version localStorage fallback', () => {
  const state = { v: 1, t: now, c: { essential: true, marketing: false } };
  const stale = { v: 2, t: now, c: { essential: true, marketing: true } };
  const { api, storage } = loadHelper();

  assert.strictEqual(api.saveLocalConsent(stale, options), false);
  assert.strictEqual(storage.dump()[storageKey], undefined);
  assert.strictEqual(api.saveLocalConsent(state, options), true);
  assert.deepStrictEqual(JSON.parse(storage.dump()[storageKey]), state);
});

for (const { name, callback } of tests) {
  callback();
  console.log(`ok - ${name}`);
}
