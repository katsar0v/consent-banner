# Changelog

## Unreleased

## 0.5.0

- Added an opt-in, vendor-neutral browser commerce module with a redacted local event API, consent-derived service destinations, optional WooCommerce/Bricks context, sourcebuster cleanup, and no remote transport.
- Added opt-in paid-order and refund dispatch with consent snapshots, exact WooCommerce value math, idempotent Action Scheduler jobs, explicit delivery acknowledgements, capped retries, and a redacted server debug transport.
- Added a generic validated runtime mode for live and local debug transports.
- Unified effective category, consent-text, lifetime, and service fingerprinting so definition changes request consent exactly once without reacting to appearance changes.
- Preserved no-reconsent settings imports by synchronizing definitions without incrementing the consent version.
- Corrected consent-receipt copy to state that IP addresses, user agents, and PII are not stored.
- Aligned translation metadata and hardened release provenance, timezone-independent packaging, and checksum verification.

## 0.4.0

- Added deterministic expiry, automatic consent versioning, and cache-neutral public configuration.
- Added a synchronous Advanced Consent Mode v2 bootstrap with canonical ready/change events.
- Added an allowlisted service registry with idempotent activation and revocation teardown.
- Added provider, purpose, data, cookie, duration, recipient, transfer, and privacy disclosures.
- Added pseudonymous receipts retained for twelve months without IP, user-agent, or PII storage.
- Added accessible dialog semantics, labels, focus trapping, Escape close, and focus return.
- Added PHP, REST, JavaScript, GTM-template, and Playwright verification.

## 0.3.0

- Added deferred frontend loading, German translations, and admin appearance controls.
