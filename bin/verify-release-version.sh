#!/usr/bin/env bash

set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${project_root}"

fail() {
	echo "Release version check failed: $1" >&2
	exit 1
}

assert_equal() {
	local label="$1"
	local actual="$2"
	local expected="$3"

	[[ "${actual}" == "${expected}" ]] || fail "${label} is '${actual}', expected '${expected}'"
}

plugin_version="$(sed -n "s/^define( 'KDCONSENT_PLUGIN_VERSION', '\([^']*\)' );/\1/p" consent-banner.php)"
[[ -n "${plugin_version}" ]] || fail 'KDCONSENT_PLUGIN_VERSION is missing'

expected_tag="v${plugin_version}"
assert_equal 'requested tag' "${1:-${expected_tag}}" "${expected_tag}"
assert_equal 'plugin header' "$(sed -n 's/^ \* Version: \(.*\)$/\1/p' consent-banner.php)" "${plugin_version}"
assert_equal 'KDCONSENT_DB_VERSION' "$(sed -n "s/^define( 'KDCONSENT_DB_VERSION', '\([^']*\)' );/\1/p" consent-banner.php)" "${plugin_version}"
assert_equal 'Installer::DB_VERSION' "$(sed -n "s/.*public const DB_VERSION[[:space:]]*= '\([^']*\)';/\1/p" includes/Installer.php)" "${plugin_version}"
assert_equal 'package.json version' "$(awk -F'"' '$2 == "version" { print $4; exit }' package.json)" "${plugin_version}"
assert_equal 'readme.txt stable tag' "$(sed -n 's/^Stable tag: //p' readme.txt)" "${plugin_version}"

mapfile -t lock_versions < <(
	awk -F'"' '
		$2 ~ /^node_modules\// { exit }
		$2 == "version" { print $4 }
	' package-lock.json
)
[[ "${#lock_versions[@]}" -eq 2 ]] || fail 'package-lock.json must expose exactly two root version fields'
for lock_version in "${lock_versions[@]}"; do
	assert_equal 'package-lock.json root version' "${lock_version}" "${plugin_version}"
done

grep -Fq "alt=\"Version ${plugin_version}\"" README.md || fail 'README badge version is inconsistent'
grep -Fq "## ${plugin_version}" CHANGELOG.md || fail 'CHANGELOG release heading is missing'
grep -Fq "= ${plugin_version} =" readme.txt || fail 'readme.txt release heading is missing'

translation_files=(
	languages/consent-banner.pot
	languages/consent-banner-bg_BG.po
	languages/consent-banner-bg_BG.mo
	languages/consent-banner-bg_BG.l10n.php
	languages/consent-banner-de_DE.po
	languages/consent-banner-de_DE.mo
	languages/consent-banner-de_DE.l10n.php
)

for translation_file in "${translation_files[@]}"; do
	translation_version="$(LC_ALL=C grep -aoE 'Consent Banner [0-9]+\.[0-9]+\.[0-9]+' "${translation_file}" | sort -u)"
	assert_equal "${translation_file} project version" "${translation_version}" "Consent Banner ${plugin_version}"
done

echo "Release version ${plugin_version} is consistent."
