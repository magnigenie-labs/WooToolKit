#!/usr/bin/env bash
# Runs WordPress.org's Plugin Check on the release build (dist zip, unpacked into build-check/)
# inside wp-env. Fails on errors; every finding is also posted as an annotation, which the public
# API can read without signing in.
set -uo pipefail
wp() { npx wp-env run cli wp "$@"; }

wp plugin activate woocommerce plugin-check wootoolkit >/dev/null
out=$(mktemp)
wp plugin check wootoolkit --format=csv --fields=file,line,type,code,message 2>&1 | tee "$out"

if [ -n "${GITHUB_ACTIONS:-}" ]; then
	grep -E ',(ERROR|WARNING),' "$out" | head -n 40 | while IFS= read -r row; do
		level=warning
		case "$row" in *,ERROR,*) level=error ;; esac
		echo "::${level} title=Plugin Check::$(printf '%s' "$row" | sed -e 's/%/%25/g')"
	done
fi

if grep -q ',ERROR,' "$out"; then
	echo "Plugin Check found errors."
	exit 1
fi
echo "Plugin Check: no errors."
