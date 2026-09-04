#!/usr/bin/env bash

set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
fixture_root="$(mktemp -d)"
remote_repo="${fixture_root}/remote.git"
source_repo="${fixture_root}/source"

git init --bare --quiet "${remote_repo}"
git init --quiet --initial-branch=main "${source_repo}"

(
	cd "${source_repo}"
	git config user.name 'Consent Banner CI'
	git config user.email 'ci@example.invalid'
	printf 'main\n' > fixture.txt
	git add fixture.txt
	git commit --quiet -m 'Create fixture'
	git remote add origin "${remote_repo}"
	git push --quiet --set-upstream origin main

	main_commit="$(git rev-parse HEAD)"
	git tag -a v1.0.0 -m 'Annotated fixture'
	git tag v1.0.1
	git push --quiet origin refs/tags/v1.0.0 refs/tags/v1.0.1

	GITHUB_SHA="${main_commit}" bash "${project_root}/bin/verify-release-provenance.sh" v1.0.0

	if GITHUB_SHA="${main_commit}" bash "${project_root}/bin/verify-release-provenance.sh" v1.0.1 >/dev/null 2>&1; then
		echo 'Lightweight tag unexpectedly passed provenance verification.' >&2
		exit 1
	fi

	if GITHUB_SHA="${main_commit}" bash "${project_root}/bin/verify-release-provenance.sh" v1.0.2 >/dev/null 2>&1; then
		echo 'Missing tag unexpectedly passed provenance verification.' >&2
		exit 1
	fi

	if GITHUB_SHA="0000000000000000000000000000000000000000" bash "${project_root}/bin/verify-release-provenance.sh" v1.0.0 >/dev/null 2>&1; then
		echo 'Mismatched event commit unexpectedly passed provenance verification.' >&2
		exit 1
	fi

	git switch --quiet -c divergent
	printf 'divergent\n' >> fixture.txt
	git add fixture.txt
	git commit --quiet -m 'Create divergent fixture'
	divergent_commit="$(git rev-parse HEAD)"
	git tag -a v1.1.0 -m 'Non-main fixture'
	git push --quiet origin refs/tags/v1.1.0

	if GITHUB_SHA="${divergent_commit}" bash "${project_root}/bin/verify-release-provenance.sh" v1.1.0 >/dev/null 2>&1; then
		echo 'Non-main tag unexpectedly passed provenance verification.' >&2
		exit 1
	fi

	if GITHUB_SHA="${main_commit}" bash "${project_root}/bin/verify-release-provenance.sh" v1.0.0 >/dev/null 2>&1; then
		echo 'Mismatched checkout unexpectedly passed provenance verification.' >&2
		exit 1
	fi
)

echo 'Release provenance regression checks passed.'
