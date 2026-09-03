const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const sourcePath = path.resolve(__dirname, '../../assets/js/consent-mode-v2.js');
const source = fs.readFileSync(sourcePath, 'utf8');

function run({ consent = null, transport = 'debug' } = {}) {
  const handlers = {};
  const logs = [];
  const browserWindow = {
    kdconsentBootstrapConfig: {
      transport,
      consentVersion: 3,
      consentLifetimeDays: 180
    },
    kdconsentStorage: {
      getCurrentConsent() {
        return consent;
      }
    },
    console: {
      log(prefix, payload) {
        logs.push({ prefix, payload: JSON.parse(JSON.stringify(payload)) });
      }
    }
  };
  const document = {
    addEventListener(name, callback) {
      handlers[name] = handlers[name] || [];
      handlers[name].push(callback);
    },
    dispatchEvent(event) {
      (handlers[event.type] || []).forEach((callback) => callback(event));
      return true;
    }
  };

  function CustomEvent(type, options) {
    this.type = type;
    this.detail = options && options.detail;
  }

  const context = {
    window: browserWindow,
    document,
    CustomEvent,
    Array,
    JSON,
    Number,
    Object
  };
  vm.createContext(context);
  vm.runInContext(source, context, { filename: sourcePath });

  return {
    window: browserWindow,
    document,
    logs,
    change(detail) {
      document.dispatchEvent(new CustomEvent('kdconsent:changed', { detail }));
    }
  };
}

{
  const result = run();
  assert.strictEqual(result.window.kdconsent.isReady, true);
  assert.strictEqual(result.window.kdconsent.getConsent(), null);
  assert.strictEqual(result.logs.length, 1);
  assert.strictEqual(result.logs[0].payload.command, 'default');
  assert.deepStrictEqual(result.logs[0].payload.state, {
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
    analytics_storage: 'denied',
    personalization_storage: 'denied',
    functionality_storage: 'granted',
    security_storage: 'granted'
  });
  assert.strictEqual(result.window.dataLayer, undefined);
  console.log('ok - debug bootstrap defaults optional storage to denied without dataLayer');
}

{
  const consent = {
    v: 3,
    t: 1800000000,
    c: { essential: true, preferences: true, analytics: true, marketing: true }
  };
  const result = run({ consent });
  const state = result.logs[0].payload.state;
  assert.strictEqual(state.analytics_storage, 'granted');
  assert.strictEqual(state.ad_storage, 'granted');
  assert.strictEqual(state.ad_user_data, 'granted');
  assert.strictEqual(state.ad_personalization, 'granted');
  assert.strictEqual(state.personalization_storage, 'granted');
  console.log('ok - synchronous bootstrap maps an existing consent state');
}

{
  const result = run();
  result.change({ v: 3, t: 1800000000, c: { essential: true, marketing: true } });
  assert.strictEqual(result.logs.length, 2);
  assert.strictEqual(result.logs[1].payload.command, 'update');
  assert.strictEqual(result.logs[1].payload.state.ad_storage, 'granted');
  assert.strictEqual(result.logs[1].payload.state.analytics_storage, 'denied');
  console.log('ok - changed events emit an update using the same mapping');
}

{
  const result = run({ transport: 'dataLayer' });
  assert.strictEqual(result.logs.length, 0);
  assert.strictEqual(result.window.dataLayer.length, 1);
  assert.strictEqual(result.window.dataLayer[0][0], 'consent');
  assert.strictEqual(result.window.dataLayer[0][1], 'default');
  console.log('ok - production transport uses the GTM consent command contract');
}
