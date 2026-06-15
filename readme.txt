=== Consent Banner ===
Contributors: katsarovdesign
Tags: cookies, gdpr, consent, privacy
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GDPR/ePrivacy consent banner with categories, essential cookies always required, and EN/BG/DE support.

== Description ==

Consent Banner provides:

- Accept all / Reject all / Customize consent flow.
- Essential category always enabled.
- Admin-adjustable categories.
- Locale-aware EN/BG/DE texts.
- Consent version bump support for re-prompting users.
- WP-CLI JSON settings import/export support.
- Optional hashed consent logging.

== Installation ==

1. Upload `consent-banner` to `/wp-content/plugins/`.
2. Activate the plugin through the `Plugins` menu in WordPress.
3. Open `Settings -> Consent Banner` and configure categories/texts.

== Frequently Asked Questions ==

= Does this block scripts automatically? =

No. Version 0.3.0 records consent and exposes APIs/hooks. Script auto-blocking is planned for a later version.

= Can users change consent later? =

Yes. Use shortcode `[kdconsent_preferences]` or trigger `.kdconsent-open-preferences` to reopen preferences.

== Changelog ==

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
