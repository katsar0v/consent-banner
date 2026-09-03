const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const sourcePath = path.resolve(__dirname, '../../assets/js/service-registry.js');
const source = fs.readFileSync(sourcePath, 'utf8');

function makeStorage(initial) {
  const values = Object.assign({}, initial || {});
  return {
    removeItem(key) {
      delete values[key];
    },
    dump() {
      return Object.assign({}, values);
    }
  };
}

function loadRegistry(mode) {
  const handlers = {};
  const elements = {};
  const appended = [];
  const logs = [];
  const cookieWrites = [];
  const localStorage = makeStorage({ clarity: 'value', keep: 'value' });
  const sessionStorage = makeStorage({ meta: 'value' });
  let reloads = 0;
  const document = {
    head: {
      appendChild(element) {
        element.parentNode = this;
        elements[element.id] = element;
        appended.push(element);
      },
      removeChild(element) {
        delete elements[element.id];
      }
    },
    addEventListener(name, callback) {
      handlers[name] = handlers[name] || [];
      handlers[name].push(callback);
    },
    dispatchEvent(event) {
      (handlers[event.type] || []).forEach((callback) => callback(event));
      return true;
    },
    createElement(tag) {
      return { tagName: tag.toUpperCase(), dataset: {} };
    },
    getElementById(id) {
      return elements[id] || null;
    }
  };
  Object.defineProperty(document, 'cookie', {
    set(value) {
      cookieWrites.push(value);
    }
  });
  const browserWindow = {
    document,
    localStorage,
    sessionStorage,
    location: {
      reload() {
        reloads += 1;
      }
    },
    console: {
      log(prefix, payload) {
        logs.push({ prefix, payload });
      }
    }
  };

  function CustomEvent(type, options) {
    this.type = type;
    this.detail = options && options.detail;
  }

  const context = { window: browserWindow, document, CustomEvent, Array, Object, String, encodeURIComponent };
  vm.createContext(context);
  vm.runInContext(source, context, { filename: sourcePath });

  const service = {
    id: 'clarity',
    purpose: 'analytics',
    allowedUrls: ['https://cdn.example.test/clarity.js'],
    scripts: [
      { handle: 'loader', src: 'https://cdn.example.test/clarity.js', async: true },
      { handle: 'blocked', src: 'https://attacker.example/script.js' }
    ],
    cookies: ['_clck'],
    localStorageKeys: ['clarity'],
    sessionStorageKeys: ['meta'],
    teardown: { event: 'clarity-stopped' }
  };

  browserWindow.kdconsentServices.init([service], null, { mode });
  return {
    window: browserWindow,
    document,
    service,
    appended,
    logs,
    cookieWrites,
    localStorage,
    sessionStorage,
    reloads: () => reloads,
    change(categories) {
      document.dispatchEvent(new CustomEvent('kdconsent:changed', {
        detail: { v: 1, t: 1800000000, c: categories }
      }));
    }
  };
}

{
  const result = loadRegistry('live');
  assert.strictEqual(result.appended.length, 0);
  result.change({ essential: true, analytics: true });
  assert.strictEqual(result.appended.length, 1);
  assert.strictEqual(result.appended[0].src, 'https://cdn.example.test/clarity.js');
  result.change({ essential: true, analytics: true });
  assert.strictEqual(result.appended.length, 1);
  console.log('ok - services activate once and only from the URL allowlist');

  result.change({ essential: true, analytics: false });
  assert.strictEqual(result.reloads(), 1);
  assert.strictEqual(result.cookieWrites.length, 1);
  assert.strictEqual(result.localStorage.dump().clarity, undefined);
  assert.strictEqual(result.localStorage.dump().keep, 'value');
  assert.strictEqual(result.sessionStorage.dump().meta, undefined);
  console.log('ok - revocation tears down known first-party state and reloads');
}

{
  const result = loadRegistry('debug');
  result.change({ essential: true, analytics: true });
  assert.strictEqual(result.appended.length, 0);
  assert.strictEqual(result.logs.length, 1);
  assert.strictEqual(result.logs[0].payload.action, 'activate');
  console.log('ok - debug mode logs planned activation without loading a script');
}
