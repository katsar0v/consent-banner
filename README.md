<h1 align="center">Consent Banner</h1>

<p align="center">
  GDPR/ePrivacy consent management for WordPress with category-level controls, essential cookies always on, and bilingual EN/BG support.
</p>

<p align="center">
  <img alt="Version 0.2.0" src="https://img.shields.io/badge/version-0.2.0-1f6feb?style=for-the-badge">
  <img alt="WordPress 6.4+" src="https://img.shields.io/badge/WordPress-6.4%2B-21759b?style=for-the-badge&logo=wordpress&logoColor=white">
  <img alt="PHP 8.1+" src="https://img.shields.io/badge/PHP-8.1%2B-777bb4?style=for-the-badge&logo=php&logoColor=white">
  <img alt="License GPL-2.0-or-later" src="https://img.shields.io/badge/license-GPL--2.0--or--later-0f766e?style=for-the-badge">
</p>

## Overview

Consent Banner adds a configurable consent banner to WordPress with **Accept all**, **Reject all**, and **Customize** flows. Categories are managed in admin, essential cookies stay enabled by design, and consent can be revisited from a shortcode or theme trigger.

This version records consent decisions and exposes a JS/PHP API for integrations. Script auto-blocking is intentionally out of scope for v0.2.0.

## Highlights

| Area | What it gives you |
| --- | --- |
| Consent UX | Accept all, reject all, and per-category customization in a modal. |
| Category model | Essential category is enforced as required; custom categories can be added in admin. |
| Admin settings | Categories, EN/BG texts, lifetime, position, theme, uninstall behavior, and version bumping. |
| REST API | Public consent submission/config endpoint + admin settings endpoint. |
| WP-CLI | JSON settings import/export for deployments and backups. |
| Integrations | JS API (`window.kdconsent`) + PHP helper (`kdconsent_has_consent`) + WP hooks/filters. |
| Internationalization | English and Bulgarian text packs (site locale based). |
| Audit option | Optional hashed consent logging for proof records. |

## Requirements

| Requirement | Version |
| --- | --- |
| WordPress | 6.4 or newer |
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
3. Review EN/BG texts.
4. Set banner behavior (position, theme, lifetime).
5. Save. Optionally bump consent version to force re-consent.

## Frontend Behavior

- Banner is rendered in `wp_footer` and hidden when valid consent exists for the current consent version.
- `Accept all`: enables all categories.
- `Reject all`: enables only required categories.
- `Customize`: opens modal with category toggles (essential locked on).
- `[kdconsent_preferences]` shortcode renders a button to reopen preferences.
- Any element with class `.kdconsent-open-preferences` reopens preferences.

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
| Action | `kdconsent_consent_recorded` | Runs when a consent decision is persisted. |
| PHP helper | `kdconsent_has_consent( string $category ): bool` | Check category consent in PHP templates/plugin logic. |

## Data Storage

| Key | Purpose |
| --- | --- |
| `kdconsent_settings` | Main plugin settings payload. |
| `kdconsent_consent_version` | Consent schema/version for re-prompting users. |
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
