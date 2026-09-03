# Consent Mode v2 template contract

Create a GTM custom template with two inputs:

- `command`: `default` or `update`
- `state`: the seven Consent Mode v2 storage fields emitted by Consent Banner

Use `consent-mode-v2.js` as the sandboxed JavaScript body. The default command must run on Consent Initialization; update commands run on the `kdconsent:changed` bridge event. Functionality and security storage always remain granted.
