#!/usr/bin/env bash
# Stages a release in the WordPress.org SVN working copy (svn-release/, separate from the 1.0.3 copy in svn/), then prints the commands to commit
# and tag it. Nothing is sent to WordPress.org until you run those commands yourself.
# Usage: bin/svn-release.sh [path/to/screenshots-dir]
set -euo pipefail
cd "$(dirname "$0")/.."

version=$(sed -n 's/^[ *]*Version:[[:space:]]*//p' wootoolkit.php | head -1 | tr -d '\r')
stable=$(sed -n 's/^Stable tag:[[:space:]]*//p' readme.txt | head -1 | tr -d '\r')
[ "$version" = "$stable" ] || { echo "Version $version and readme Stable tag $stable differ." >&2; exit 1; }
case "$version" in *-*) echo "Refusing to release a pre-release version ($version)." >&2; exit 1 ;; esac

if [ ! -d svn-release/.svn ]; then
	svn checkout -q --depth immediates https://plugins.svn.wordpress.org/wootoolkit svn-release
	svn update -q --set-depth infinity svn-release/trunk svn-release/assets
else
	svn update -q svn-release/trunk svn-release/assets
fi
if svn ls "https://plugins.svn.wordpress.org/wootoolkit/tags/$version" >/dev/null 2>&1; then
	echo "tags/$version already exists on WordPress.org." >&2; exit 1
fi

zip=$(bash bin/build-zip.sh)
rm -rf build/release && mkdir -p build/release && unzip -q "$zip" -d build/release
rsync -a --delete --exclude=.svn build/release/wootoolkit/ svn-release/trunk/
rm -rf build/release

if [ -n "${1:-}" ]; then
	cp "$1"/screenshot-*.png svn-release/assets/
	svn rm -q --force svn-release/assets/screenshot-*.jpg 2>/dev/null || true
fi

( cd svn-release
  svn status | awk '/^\?/ { print $2 }' | xargs -r svn add -q
  svn status | awk '/^!/ { print $2 }' | xargs -r svn rm -q
  svn status | sed 's/^/  /' | head -40
  echo "  ($(svn status | wc -l | tr -d ' ') changes)" )

cat <<EOF

Staged $version in svn-release/. Review, then publish with:

  cd "$(pwd)/svn-release"
  svn ci -m "Release $version" --username magnigenie
  svn cp https://plugins.svn.wordpress.org/wootoolkit/trunk https://plugins.svn.wordpress.org/wootoolkit/tags/$version -m "Tag $version" --username magnigenie
EOF
