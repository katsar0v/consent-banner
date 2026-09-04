<h1 align="center">Consent Banner</h1>

<p align="center">
  GDPR/ePrivacy consent management for WordPress with category-level controls, essential cookies always on, and EN/BG/DE support.
</p>

<p align="center">
  <img alt="Version 0.4.0" src="https://img.shields.io/badge/version-0.4.0-1f6feb?style=for-the-badge">
  <img alt="WordPress 6.8+" src="https://img.shields.io/badge/WordPress-6.8%2B-21759b?style=for-the-badge&logo=wordpress&logoColor=white">
  <img alt="PHP 8.1+" src="https://img.shields.io/badge/PHP-8.1%2B-777bb4?style=for-the-badge&logo=php&logoColor=white">
  <img alt="License GPL-2.0-or-later" src="https://img.shields.io/badge/license-GPL--2.0--or--later-0f766e?style=for-the-badge">
</p>

## Overview

Consent Banner adds a configurable consent banner to WordPress with **Accept all**, **Reject all**, and **Customize** flows. Categories are managed in admin, essential cookies stay enabled by design, and consent can be revisited from a shortcode or theme trigger.

Version 0.4.0 adds a synchronous Consent Mode v2 bootstrap, an allowlisted service registry, teardown on revocation, accessible preferences, and data-minimal twelve-month receipts.

## Highlights

| Area | What it gives you |
| --- | --- |
| Consent UX | Accept all, reject all, and per-category customization in a modal. |
| Category model | Essential category is enforced as required; custom categories can be added in admin. |
| Admin settings | Categories, EN/BG/DE texts, lifetime, position, theme, uninstall behavior, and version bumping. |
| REST API | Public consent submission/config endpoint + admin settings endpoint. |
| WP-CLI | JSON settings import/export for deployments and backups. |
| Integrations | JS API (`window.kdconsent`) + PHP helper (`kdconsent_has_consent`) + WP hooks/filters. |
| Internationalization | English, Bulgarian, and German text packs (site locale based). |
| Audit option | Optional pseudonymous receipts without IP, user agent, or PII. |
| Service control | Purpose-gated URL allowlists with cookie/storage teardown on revocation. |
| Commerce events | Optional, transport-free WooCommerce/Bricks browser event contract with positive-list redaction. |

## Requirements

| Requirement | Version |
| --- | --- |
| WordPress | 6.8 or newer |
| PHP | 8.1 or newer |

## Installation

Place the plugin directory in WordPress:

```bash
wp-content/plugins/consent-banner
```

Install dependencies when installing from source:

```bash
composer install --no-dev
```

Activate from WP-CLI inside the Docker php container:

```bash
docker exec -w /var/www/html php wp plugin activate consent-banner --allow-root
```

## First-Time Setup

1. Open `Settings -> Consent Banner` in wp-admin.
2. Confirm categories and keep `essential` required.
3. Review EN/BG/DE texts.
4. Set banner behavior (position, theme, lifetime).
5. Save. Optionally bump consent version to force re-consent.

## Frontend Behavior

- A deferred loader checks the consent cookie first; full config, CSS, and UI load only when consent is missing, stale, or preferences are opened.
- `Accept all`: enables all categories.
- `Reject all`: enables only required categories.
- `Customize`: opens modal with category toggles (essential locked on).
- `[kdconsent_preferences]` shortcode renders a button to reopen preferences.
- Any element with class `.kdconsent-open-preferences` reopens preferences.
- Effective categories, consent texts, consent lifetime, and service definitions share one fingerprint. A change to any of them invalidates prior consent exactly once; style, position, animation, and delay changes do not.

### Runtime mode

The frontend defaults to `live`. Integrations can select the local, non-loading debug transport with a filter:

```php
add_filter( 'kdconsent_runtime_mode', static fn(): string => 'debug' );
```

`debug` logs Consent Mode commands and planned service activations in the browser console. `live` writes Consent Mode commands to the data layer and may load consented, allowlisted service scripts. Any value other than `live` or `debug` falls back to `live`.

### Optional browser commerce events

The generic commerce module is disabled by default and has no remote transport. Enable it from an integration or theme after plugins load:

```php
add_filter( 'kdconsent_commerce_enabled', '__return_true' );
```

It emits `page_view`, `view_item_list`, `select_item`, `view_item`, `add_to_cart`, `remove_from_cart`, `view_cart`, `begin_checkout`, `add_shipping_info`, `add_payment_info`, and `search`. Every `kdconsent:commerce` DOM event uses the same redacted envelope exposed by `window.kdconsentCommerce`; in debug mode that envelope is also logged with `[kdconsent-commerce]`. No search term, contact data, address, network identifier, click identifier, or attribution cookie value is exposed.

`planned_destinations` contains only sanitized IDs from `kdconsent_services` whose declared purpose is currently granted. The module never hardcodes a vendor or sends an HTTP request.

When WooCommerce is active, product/variation IDs, SKU, product name, post-discount net unit price, quantity, currency, and cart value are provided. Woo Order Attribution remains off in cacheable HTML, follows marketing consent in the browser, and removes known `sbjs_*` first-party state after denial or revocation. The WooCommerce and Bricks integrations are optional and guarded by runtime checks.

Classic cart and mini-cart removal links are enriched at render/fragment time with exact `data-kdconsent-commerce-*` item fields from the current cart line. Confirmed AJAX adds use the matching returned fragment's effective net unit price while retaining the quantity added by that action, rather than the cart line's accumulated quantity. Per-cart lookup data is localized only on the non-cacheable cart and checkout pages, and internal cart keys and parent/variation lookup fields never enter public event payloads. Woo Blocks confirmations that only expose `preserveCartData` are emitted only from the latest exact, unexpired product intent (Woo product-button/remove-link classes, `data-wc-context`, or `data-kdconsent-commerce-action`). A confirmation without exact event data or a recent captured intent is ignored rather than assigned to a guessed product.

Bricks WooCommerce lists receive semantic roles and `data-kdconsent-commerce-*` attributes. Integrations can supply a site-specific list identity without coupling it to the plugin:

```php
add_filter(
    'kdconsent_commerce_bricks_list_context',
    static function ( array $context, object $element, array $settings, string $attribute_key ): array {
        $context['list_id']    = 'featured-products';
        $context['list_name']  = 'Featured products';
        $context['list_group'] = 'featured';
        return $context;
    },
    10,
    4
);
```

## REST API

Namespace:

```text
/wp-json/kdconsent/v1
```

| Methods | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/config` | Public runtime config (texts, categories, behavior, current consent). |
| `POST` | `/consent` | Public consent submission endpoint (rate-limited). |
| `GET` | `/settings` | Admin-only settings read. |
| `PUT`, `PATCH` | `/settings` | Admin-only settings update. |

Admin endpoints require `X-WP-Nonce` and `manage_options` capability.

## WP-CLI

Export the same JSON payload available in the admin GUI:

```bash
docker exec -w /var/www/html php wp consent-banner export /tmp/consent-banner-settings.json --allow-root
```

Overwrite an existing export:

```bash
docker exec -w /var/www/html php wp consent-banner export /tmp/consent-banner-settings.json --force --allow-root
```

Print the export to stdout:

```bash
docker exec -w /var/www/html php wp consent-banner export - --allow-root
```

Import and merge settings. By default this bumps the consent version, matching the GUI:

```bash
docker exec -w /var/www/html php wp consent-banner import /tmp/consent-banner-settings.json --allow-root
```

Replace all settings instead of merging:

```bash
docker exec -w /var/www/html php wp consent-banner import /tmp/consent-banner-settings.json --replace --allow-root
```

Validate an import without changing settings or the consent version:

```bash
docker exec -w /var/www/html php wp consent-banner import /tmp/consent-banner-settings.json --dry-run --allow-root
```

Import without asking users for consent again:

```bash
docker exec -w /var/www/html php wp consent-banner import /tmp/consent-banner-settings.json --no-bump-version --allow-root
```

## Hooks and Helpers

| Type | Name | Purpose |
| --- | --- | --- |
| Filter | `kdconsent_default_categories` | Override install-time category defaults. |
| Filter | `kdconsent_categories` | Adjust runtime categories before use/render. |
| Filter | `kdconsent_services` | Register declarative, purpose-gated service definitions. |
| Filter | `kdconsent_runtime_mode` | Select validated `live` or `debug` frontend transport. |
| Filter | `kdconsent_commerce_enabled` | Opt into the transport-free browser commerce module; defaults to `false`. |
| Filter | `kdconsent_commerce_bricks_list_context` | Supply sanitized `list_id`, `list_name`, and `list_group` metadata; arguments are context, element, settings, and attribute key. |
| Action | `kdconsent_consent_recorded` | Runs when a consent decision is persisted. |
| PHP helper | `kdconsent_has_consent( string $category ): bool` | Check category consent in PHP templates/plugin logic. |

## Data Storage

| Key | Purpose |
| --- | --- |
| `kdconsent_settings` | Main plugin settings payload. |
| `kdconsent_consent_version` | Consent schema/version for re-prompting users. |
| `kdconsent_consent_definitions_hash` | Fingerprint of effective category and service definitions. |
| `kdconsent_db_version` | Plugin DB version state. |
| `kdconsent_remove_on_uninstall` | Opt-in uninstall cleanup flag. |
| `kdconsent_consent` cookie | Signed client consent payload (`v`, `t`, `c`). |
| `{prefix}kdconsent_consent_log` | Optional hashed consent proof entries. |

## Development

```bash
docker exec -w /var/www/html/wp-content/plugins/consent-banner php composer lint:syntax
docker exec -w /var/www/html/wp-content/plugins/consent-banner php composer lint:phpcs
```

## Architecture

```text
consent-banner.php                  Plugin bootstrap
includes/Plugin.php                Hook wiring
includes/Installer.php             Defaults, table creation, upgrades
includes/Admin/                    Admin menu, settings page, admin assets
includes/Frontend/                 Banner mount, frontend assets, shortcode
includes/Commerce/                 Optional WooCommerce/Bricks browser event module
includes/Rest/                     REST routes and consent/settings controller
includes/Repository/               Settings and optional consent log persistence
includes/Service/                  Consent and localization logic
includes/Domain/                   Consent/category value objects
assets/css/                        Admin and banner styles
assets/js/                         Admin and banner interactions
views/settings.php                 Admin settings template
languages/                         Translation files
uninstall.php                      Opt-in cleanup during uninstall
```

## Uninstall Behavior

By default, plugin data is preserved on uninstall. If `Remove plugin data on uninstall` is enabled, uninstall removes plugin options and consent log table.

## License

Consent Banner is licensed under `GPL-2.0-or-later`.

Copyright (C) 2026 Katsarov Design.
