#!/usr/bin/env bash

set -euo pipefail

fail() {
	echo "Release provenance check failed: $1" >&2
	exit 1
}

assert_equal() {
	local label="$1"
	local actual="$2"
	local expected="$3"

	[[ "${actual}" == "${expected}" ]] || fail "${label} is '${actual}', expected '${expected}'"
}

tag_name="${1:-}"
remote_name="${2:-origin}"
main_ref="${3:-origin/main}"

[[ "${tag_name}" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]] || fail "invalid tag name '${tag_name}'"

tag_ref="refs/tags/${tag_name}"
peeled_ref="${tag_ref}^{}"
remote_refs="$(git ls-remote "${remote_name}" "${tag_ref}" "${peeled_ref}")"
tag_object="$(awk -v ref="${tag_ref}" '$2 == ref { print $1 }' <<< "${remote_refs}")"
tag_commit="$(awk -v ref="${peeled_ref}" '$2 == ref { print $1 }' <<< "${remote_refs}")"

[[ -n "${tag_object}" ]] || fail "remote tag '${tag_ref}' is missing"
[[ -n "${tag_commit}" ]] || fail "remote tag '${tag_ref}' is not annotated"
[[ "${tag_object}" != "${tag_commit}" ]] || fail "remote tag '${tag_ref}' has no distinct tag object"

main_commit="$(git rev-parse "${main_ref}^{commit}")"
head_commit="$(git rev-parse 'HEAD^{commit}')"
event_commit="${GITHUB_SHA:-${head_commit}}"

assert_equal 'tag commit' "${tag_commit}" "${main_commit}"
assert_equal 'event commit' "${event_commit}" "${tag_commit}"
assert_equal 'checkout commit' "${head_commit}" "${tag_commit}"

echo "Release tag ${tag_name} is annotated and resolves to ${tag_commit}."
