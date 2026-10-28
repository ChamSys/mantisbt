#!/bin/sh
# Build the release tarball of the fork, shaped like an upstream MantisBT release: one top
# directory, vendor/ included (no dev packages), and none of the development files.
#
#   chamsys/release/build.sh [OUTPUT_DIR]      (default: the current directory)
#
# It builds what is committed (git archive of HEAD), not the working tree, and refuses a dirty
# tree: a release is a commit. The version is MANTIS_VERSION of core/constant_inc.php, raise it
# before building (2.28.4.1, 2.28.4.2, ...). It prints the file and its SHA-512, which is what
# a deployment pins (see the pin in the provisioning of the site that uses it).
#
# composer must be on the PATH, or docker: then the composer image installs vendor/. The platform
# requirements are ignored on purpose: the build machine is not where the code runs.
set -eu

cd "$(dirname "$0")/../.."
out=$(cd "${1:-.}" && pwd)

if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
	echo "The working tree has uncommitted changes: commit them first." >&2
	exit 1
fi

version=$(sed -n "s/.*define( *'MANTIS_VERSION', *'\([^']*\)'.*/\1/p" core/constant_inc.php)
[ -n "$version" ] || { echo "MANTIS_VERSION not found in core/constant_inc.php" >&2; exit 1; }
name="mantisbt-$version"
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

# What is committed, without what is only for developing the fork (the docker stack and the
# audit notes in chamsys/) and what upstream's release does not ship either (the tests and the
# git files).
git archive --format=tar --prefix="$name/" HEAD | tar -x -C "$work"
rm -rf "$work/$name/chamsys" "$work/$name/tests" "$work/$name/.git"* "$work/$name/.editorconfig" "$work/$name/.phpcs"* 2>/dev/null || true

if command -v composer >/dev/null 2>&1; then
	composer install --working-dir="$work/$name" --no-dev --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs --quiet
else
	docker run --rm -v "$work/$name:/app" -w /app composer:2 \
		install --no-dev --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs --quiet
fi

# Reproducible enough to compare two builds of the same commit: sorted names, fixed owner, the
# time of the commit.
stamp=$(git log -1 --format=%ct HEAD)
tar --sort=name --owner=0 --group=0 --numeric-owner --mtime="@$stamp" -czf "$out/$name.tar.gz" -C "$work" "$name"

echo "$out/$name.tar.gz"
sha512sum "$out/$name.tar.gz"
