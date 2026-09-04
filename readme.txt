=== Consent Banner ===
Contributors: katsarovdesign
Tags: cookies, gdpr, consent, privacy
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GDPR/ePrivacy consent banner with categories, essential cookies always required, and EN/BG/DE support.

== Description ==

Consent Banner provides:

- Accept all / Reject all / Customize consent flow.
- Essential category always enabled.
- Admin-adjustable categories.
- Locale-aware EN/BG/DE texts.
- Automatic consent versioning for material configuration changes.
- WP-CLI JSON settings import/export support.
- Synchronous Advanced Consent Mode v2 bootstrap.
- Consent-gated service registry with revocation teardown.
- Transparent service disclosures and pseudonymous 12-month receipts.
- Accessible dialogs with focus trapping and focus return.
- Optional, vendor-neutral browser commerce events for WooCommerce and Bricks.
- Optional paid-order and refund dispatch through Action Scheduler.

== Installation ==

1. Upload `consent-banner` to `/wp-content/plugins/`.
2. Activate the plugin through the `Plugins` menu in WordPress.
3. Open `Settings -> Consent Banner` and configure categories/texts.

== Frequently Asked Questions ==

= Does this block scripts automatically? =

Yes, for services declared through the service registry. Only explicitly allowlisted script URLs are activated after the matching optional purpose is granted.

= Can users change consent later? =

Yes. Use shortcode `[kdconsent_preferences]` or trigger `.kdconsent-open-preferences` to reopen preferences.

== Changelog ==

= 0.5.0 =

- Added a validated runtime mode for live and local debug transports.
- Added opt-in browser commerce events with consent-derived destinations and positive-list redaction.
- Added opt-in paid-order and refund dispatch with exact value math and idempotent Action Scheduler jobs.
- Unified material consent-definition fingerprinting without reacting to appearance changes.
- Preserved no-reconsent imports while synchronizing effective definitions.
- Clarified that pseudonymous receipts never store IP addresses, user agents, or PII.

= 0.4.0 =

- Added cache-safe expiry and automatic consent-version handling.
- Added synchronous Advanced Consent Mode v2 defaults and updates.
- Added a declarative allowlisted service registry with teardown on revocation.
- Added complete service transparency fields and accessible preference dialogs.
- Replaced IP/user-agent hashes with pseudonymous receipts retained for 12 months.
- Added PHP, REST, JavaScript, GTM-template, and Playwright release checks.

= 0.3.0 =

- Optimized frontend loading with a deferred lazy loader for config, CSS, and UI assets.
- Added German banner/admin translations and German site-locale support.

= 0.2.0 =

- Added WP-CLI JSON settings import/export support.
- Renamed plugin, package, and public APIs to Consent Banner.
- Added migration and compatibility shims for legacy data and integrations.

= 0.1.0 =

- Initial release.
- Consent banner with categories and essential lock.
- EN/BG localization structure.
- Admin settings page and REST endpoints.
