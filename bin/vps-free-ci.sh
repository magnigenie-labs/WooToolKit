#!/bin/bash
# Runs on the test VPS (uploaded by bin/test-free.sh). Reuses the vendor dir and test library that
# /root/wtk-ci-local.sh prepared in /root/wtk-dev; never touches them.
set -uo pipefail
NAME=$1; FILTER=${2:-}; WPV=7.1.3; R=/root/wtk-dev
# Inside the test WordPress plugins dir, so plugin_basename() works as on a real site.
K=$R/wp-$WPV/wp-content/plugins/wtkrun-$NAME; CFG=/root/wtk-free/cfg-$NAME.php; mkdir -p /root/wtk-free
rm -rf "$K"; mkdir -p "$K"; tar xzf "/root/wtk-freesrc-$NAME.tgz" -C "$K"
VENDOR=$R/wp-$WPV/wp-content/plugins/wootoolkit/vendor
[ -x "$VENDOR/bin/phpunit" ] || { echo "vendor missing: run /root/wtk-ci-local.sh once"; exit 1; }
ln -s "$VENDOR" "$K/vendor"
PREFIX="wf$(echo "$NAME" | tr -cd 'a-z0-9' | cut -c1-14)_"
sed "s/^\$table_prefix *=.*/\$table_prefix = '$PREFIX';/" "$R/tests-$WPV/wp-tests-config.php" > "$CFG"
cd "$K"
status=0
if [ -z "$FILTER" ]; then
	echo "== php -l"
	for f in $(find . -name '*.php' -not -path './vendor/*' -not -path './node_modules/*' -not -path './svn*' -not -path './build*'); do for v in 7.4 8.4; do php$v -l "$f" >/dev/null 2>&1 || { php$v -l "$f"; status=1; }; done; done
	echo "== phpcs"
	php8.3 vendor/bin/phpcs -q --report=full 2> >(grep -v "PHP Startup" >&2) || status=1
	echo "== phpstan"
	php8.3 vendor/bin/phpstan analyse --memory-limit=1G --no-progress --error-format=table 2>&1 | grep -v "PHP Startup\|^PHP Warning\|^Note: Using" | tail -40; [ "${PIPESTATUS[0]}" -eq 0 ] || status=1
fi
for PHPV in 7.4 8.4; do
	HPOS=$([ "$PHPV" = 8.4 ] && echo 1 || echo "")
	echo "== phpunit PHP $PHPV ($([ -n "$HPOS" ] && echo "HPOS order tables" || echo "posts order storage"))"
	WP_TESTS_DIR=$R/tests-$WPV WP_TESTS_CONFIG=$CFG \
	WC_PLUGIN_FILE=$R/wp-$WPV/wp-content/plugins/woocommerce/woocommerce.php WTK_HPOS=$HPOS \
	php$PHPV vendor/bin/phpunit ${FILTER:+--filter "$FILTER"} 2>&1 | grep -v "^Not running\|^Installing\|^Running as single\|as_unschedule_all_actions\|^$" | tail -40
	[ "${PIPESTATUS[0]}" -eq 0 ] || status=1
done
echo "== result: $([ $status -eq 0 ] && echo PASS || echo FAIL)"
exit $status
