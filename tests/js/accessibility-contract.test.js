const assert = require('assert');
const fs = require('fs');
const path = require('path');

const source = fs.readFileSync(path.resolve(__dirname, '../../assets/js/banner-ui.js'), 'utf8');

[
  "wrapper.setAttribute('role', 'dialog')",
  "modal.setAttribute('role', 'dialog')",
  "modal.setAttribute('aria-modal', 'true')",
  "modal.setAttribute('aria-labelledby', 'kdconsent-modal-title')",
  'label.htmlFor = inputId',
  "modal.addEventListener('keydown', trapModalFocus)",
  "event.key === 'Escape'",
  "event.key !== 'Tab'",
  'lastFocusedElement.focus()',
  "new CustomEvent('kdconsent:changed'"
].forEach((contract) => {
  assert.ok(source.includes(contract), `missing accessibility contract: ${contract}`);
});

console.log('ok - dialogs expose labels, focus trapping, Escape, and focus return');
