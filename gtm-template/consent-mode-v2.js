// Consent Banner custom-template contract for a GTM sandboxed JavaScript template.
const setDefaultConsentState = require('setDefaultConsentState');
const updateConsentState = require('updateConsentState');

const state = data.state || {};
if (data.command === 'default') {
  setDefaultConsentState(state);
} else if (data.command === 'update') {
  updateConsentState(state);
}

data.gtmOnSuccess();
