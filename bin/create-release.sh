#!/usr/bin/env bash
set -euo pipefail

plugin_version="$(sed -n "s/^define( 'KDCONSENT_PLUGIN_VERSION', '\([^']*\)' );/\1/p" consent-banner.php)"
[[ -n "${plugin_version}" ]] || { printf 'Unable to read plugin version.\n' >&2; exit 1; }

output="${1:-dist/consent-banner-${plugin_version}.zip}"
mkdir -p "$(dirname "${output}")"

git archive \
  --format=zip \
  --prefix=consent-banner/ \
  --output="${output}" \
  HEAD \
  consent-banner.php uninstall.php readme.txt README.md CHANGELOG.md \
  assets gtm-template includes languages views

sha256sum "${output}"
