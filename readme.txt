=== Cookie Banner ===
Contributors: katsarovdesign
Tags: cookies, gdpr, consent, privacy
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GDPR-focused cookie consent banner with categories, essential cookies always required, and EN/BG support.

== Description ==

Cookie Banner provides:

- Accept all / Reject all / Customize consent flow.
- Essential category always enabled.
- Admin-adjustable categories.
- Locale-aware EN/BG texts.
- Consent version bump support for re-prompting users.
- Optional hashed consent logging.

== Installation ==

1. Upload `cookie-banner` to `/wp-content/plugins/`.
2. Activate the plugin through the `Plugins` menu in WordPress.
3. Open `Settings -> Cookie Banner` and configure categories/texts.

== Frequently Asked Questions ==

= Does this block scripts automatically? =

No. Version 0.1.0 records consent and exposes APIs/hooks. Script auto-blocking is planned for a later version.

= Can users change consent later? =

Yes. Use shortcode `[kdcb_preferences]` or trigger `.kdcb-open-preferences` to reopen preferences.

== Changelog ==

= 0.1.0 =

- Initial release.
- Consent banner with categories and essential lock.
- EN/BG localization structure.
- Admin settings page and REST endpoints.
