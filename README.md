<h1 align="center">Consent Banner</h1>

<p align="center">
  GDPR/ePrivacy consent management for WordPress with category-level controls, essential cookies always on, and EN/BG/DE support.
</p>

<p align="center">
  <img alt="Version 0.5.0" src="https://img.shields.io/badge/version-0.5.0-1f6feb?style=for-the-badge">
  <img alt="WordPress 6.8+" src="https://img.shields.io/badge/WordPress-6.8%2B-21759b?style=for-the-badge&logo=wordpress&logoColor=white">
  <img alt="PHP 8.1+" src="https://img.shields.io/badge/PHP-8.1%2B-777bb4?style=for-the-badge&logo=php&logoColor=white">
  <img alt="License GPL-2.0-or-later" src="https://img.shields.io/badge/license-GPL--2.0--or--later-0f766e?style=for-the-badge">
</p>

## Overview

Consent Banner adds a configurable consent banner to WordPress with **Accept all**, **Reject all**, and **Customize** flows. Categories are managed in admin, essential cookies stay enabled by design, and consent can be revisited from a shortcode or theme trigger.

Version 0.5.0 adds validated runtime modes and opt-in, vendor-neutral browser and server commerce contracts with consent-derived destinations, positive-list redaction, and no built-in remote transport.

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
- Effective categories, consent texts, consent lifetime, and service definitions share one fingerprint. A change to any of them invalidates prior consent exactly once; style, position, animation, delay, and automatic footer display changes do not.

### Automatic footer preferences control

**Settings → Consent Banner → Appearance → Display behavior** includes
**Automatically show Cookie settings in the footer** (`autoFooterPreferences`).
It defaults to enabled, including when upgrading settings that omit the key.
The plugin renders one `.kdconsent-footer-preferences` wrapper through `wp_footer`.
Disabling the checkbox removes the whole wrapper server-side, leaving no empty row.
Clear any full-page cache after changing it. Export/import and REST PATCH preserve
this setting; changing only this setting does not increment the consent version.

Provide a replacement in your own footer or another accessible location before
disabling automatic display. Use `[kdconsent_preferences]`, for example in a
Bricks Shortcode element, or a native link/button with `kdconsent-open-preferences`:

```html
<button type="button" class="kdconsent-open-preferences">Cookie settings</button>
```

The trigger class adds behavior only and leaves manually styled links/buttons intact,
including after the dialog stylesheet loads. Shortcode and automatic buttons use
the separate `kdconsent-preferences-button` class for their default appearance.

Custom JavaScript can also call `window.kdconsent.openPreferences()` after the
loader is ready. These manual controls remain independent of the checkbox and
work for returning visitors whose banner UI has not loaded yet.

**Theme migration:** Equanis's reported row came from
`Equanis\Consent\LiveTracking::preferences_link`, registered on `wp_footer`,
not from the 0.5.0 plugin. Remove that legacy automatic callback when adopting
the plugin renderer, otherwise it remains independent and can cause duplication.
Keep `wp_footer` and consent runtime scripts enabled. Theme-specific styling can
target `.kdconsent-footer-preferences .kdconsent-open-preferences`.

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

### Optional server commerce events

The same `kdconsent_commerce_enabled` filter opts into a WooCommerce server dispatcher for `purchase` and `refund`. WooCommerce and Action Scheduler remain optional dependencies: without WooCommerce the callbacks are inert, and without a working scheduler no delivery state is written.

Classic checkout and Store API checkout capture the normalized consent snapshot on the order. By default, only paid first orders created through `checkout` or `store-api` qualify. Subscription renewals are rejected when WooCommerce Subscriptions is available. A site integration can add stricter exclusions without replacing these defaults:

```php
add_filter(
    'kdconsent_commerce_order_qualifies',
    static function ( bool $qualifies, WC_Order $order ): bool {
        return $qualifies && '' === (string) $order->get_meta( '_my_import_id', true );
    },
    10,
    2
);
```

Purchase IDs are always `purchase:<order_id>` and refund IDs are `refund:<order_id>:<refund_id>`. The occurrence time comes from WooCommerce's paid/refund creation timestamp rather than job execution time. Merchandise value is the exact sum of post-discount line totals without tax or shipping; unit price is the corresponding line total divided by quantity. Purchase `paid_value` is the paid order total. Refund `value` contains only actual refund lines, so a shipping-only refund has merchandise value `0`; refund `paid_value` is the actual refund amount. When WooCommerce records a non-zero product refund line without a quantity, the canonical event preserves that affected product as one unit priced at the refunded line amount.

Every event is reduced to the positive-list contract before `kdconsent_commerce_event` runs, and its result is redacted again. Canonical IDs, timestamps, consent, value math, items, and privacy booleans cannot be replaced by a filter. A filter can suppress planned destinations, but the remaining set is intersected with the currently registered, consented services. Contact values, addresses, IP addresses, user agents, attribution cookies, and click IDs are never exposed. Only `has_email`, `has_phone`, and a click-ID boolean derived from actual `gclid`, `wbraid`, or `gbraid` order metadata can be present.

The plugin bundles no live transport. A live transport listens to the public delivery action and must explicitly acknowledge success through its `DeliveryConfirmation`; registration or a callback return value never counts as delivery:

```php
use KatsarovDesign\ConsentBanner\Commerce\DeliveryConfirmation;

add_action(
    'kdconsent_commerce_deliver_purchase',
    static function ( array $event, DeliveryConfirmation $confirmation ): void {
        if ( my_transport_send_redacted_event( $event ) ) {
            $confirmation->confirm();
        }
    },
    10,
    2
);
```

Refund transports use `kdconsent_commerce_deliver_refund` with the same two arguments. Unconfirmed or failed attempts stay pending and receive capped, unique backoff retries through Action Scheduler. A later paid/refund source hook can requeue work that remains pending after the cap. Transport exceptions are rethrown so Action Scheduler records the failed attempt; failed, timed-out, and unexpectedly terminated plugin jobs are also reconciled into the retry path.

In `debug` runtime mode, the dispatcher performs no HTTP request or live transport action. It writes only the twice-redacted event as `[kdconsent-commerce] <json>` to the PHP error log and marks the job `debug-delivered` after a successful write. Repeated callbacks for `delivered` or `debug-delivered` entities are no-ops.

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
| Filter | `kdconsent_commerce_enabled` | Opt into browser and WooCommerce server commerce modules; defaults to `false`. |
| Filter | `kdconsent_commerce_bricks_list_context` | Supply sanitized `list_id`, `list_name`, and `list_group` metadata; arguments are context, element, settings, and attribute key. |
| Filter | `kdconsent_commerce_order_qualifies` | Add site-specific order exclusions after the built-in checkout/Store API and renewal rules. |
| Filter | `kdconsent_commerce_event` | Inspect the already-redacted server envelope or suppress planned destinations; canonical event and commerce fields remain enforced. |
| Action | `kdconsent_consent_recorded` | Runs when a consent decision is persisted. |
| Action | `kdconsent_commerce_deliver_purchase` | Receive a redacted purchase and explicitly confirm successful live transport. |
| Action | `kdconsent_commerce_deliver_refund` | Receive a redacted refund and explicitly confirm successful live transport. |
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
| `_kdconsent_commerce_consent_snapshot` | Normalized purpose booleans captured on a WooCommerce order. |
| `_kdconsent_commerce_purchase_state` | Purchase scheduling/delivery state on the order. |
| `_kdconsent_commerce_refund_state` | Refund scheduling/delivery state on the refund. |

Server jobs use the `kdconsent-commerce` Action Scheduler group. The internal process hooks are `kdconsent_commerce_process_purchase` and `kdconsent_commerce_process_refund`; integrations should use the public delivery actions above rather than those queue callbacks.

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
includes/Commerce/                 Optional browser and server commerce event modules
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
