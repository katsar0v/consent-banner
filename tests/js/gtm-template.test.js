const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.resolve(__dirname, '../../gtm-template/consent-mode-v2.js'), 'utf8');

function execute(command) {
  const calls = [];
  let succeeded = false;
  const state = { analytics_storage: 'denied', security_storage: 'granted' };
  const context = {
    data: {
      command,
      state,
      gtmOnSuccess() {
        succeeded = true;
      }
    },
    require(name) {
      return (value) => calls.push({ name, value });
    }
  };

  vm.createContext(context);
  vm.runInContext(source, context);
  return { calls, succeeded, state };
}

const defaults = execute('default');
assert.deepStrictEqual(defaults.calls, [{ name: 'setDefaultConsentState', value: defaults.state }]);
assert.strictEqual(defaults.succeeded, true);

const update = execute('update');
assert.deepStrictEqual(update.calls, [{ name: 'updateConsentState', value: update.state }]);
assert.strictEqual(update.succeeded, true);

console.log('ok - GTM template routes default and update through sandbox consent APIs');
