#!/usr/bin/env bash
# Installs WordPress, its PHPUnit test library and WooCommerce for running PHPUnit without Docker.
# Usage: bin/install-wp-tests.sh <wp-version> <wc-version> [db-name] [db-user] [db-pass] [db-host]
# Set PLUGIN_SRC=<repo path> to copy WooToolkit into wp-content/plugins/wootoolkit (where it lives on
# a real site; WooCommerce's feature-compatibility checks depend on that path).
# Then:  cd $BASE/wordpress/wp-content/plugins/wootoolkit && WP_TESTS_DIR=$BASE/tests vendor/bin/phpunit
set -euo pipefail
WP=${1:?wp version}; WC=${2:?wc version}
DB_NAME=${3:-wordpress_tests}; DB_USER=${4:-root}; DB_PASS=${5:-root}; DB_HOST=${6:-127.0.0.1:3306}
BASE=${WP_TESTS_BASE:-/tmp/wp-tests}

mkdir -p "$BASE"
cd "$BASE"

if [ ! -f wordpress/wp-settings.php ]; then
	curl -sSfL --retry 5 --retry-all-errors --retry-delay 3 "https://wordpress.org/wordpress-${WP}.zip" -o wp.zip
	unzip -q wp.zip && rm wp.zip
fi

if [ ! -f tests/includes/functions.php ]; then
	rm -rf develop
	for attempt in 1 2 3; do
		git clone -q --depth 1 --branch "$WP" --filter=blob:none --sparse https://github.com/WordPress/wordpress-develop.git develop && break
		rm -rf develop; sleep 5
	done
	git -C develop sparse-checkout set tests/phpunit/includes tests/phpunit/data
	mkdir -p tests
	cp -R develop/tests/phpunit/includes develop/tests/phpunit/data tests/
	cp develop/wp-tests-config-sample.php tests/wp-tests-config-sample.php
	rm -rf develop
fi

sed -e "s:dirname( __FILE__ ) . '/src/':'${BASE}/wordpress/':" \
	-e "s/youremptytestdbnamehere/${DB_NAME}/" \
	-e "s/yourusernamehere/${DB_USER}/" \
	-e "s/yourpasswordhere/${DB_PASS}/" \
	-e "s/localhost/${DB_HOST}/" \
	tests/wp-tests-config-sample.php > tests/wp-tests-config.php

if [ "$(cat wordpress/.wc-version 2>/dev/null)" != "$WC" ]; then
	rm -rf wordpress/wp-content/plugins/woocommerce
	curl -sSfL --retry 5 --retry-all-errors --retry-delay 3 "https://downloads.wordpress.org/plugin/woocommerce.${WC}.zip" -o wc.zip
	unzip -q wc.zip -d wordpress/wp-content/plugins/ && rm wc.zip
	echo "$WC" > wordpress/.wc-version
fi

if [ -n "${PLUGIN_SRC:-}" ]; then
	mkdir -p wordpress/wp-content/plugins/wootoolkit
	rsync -a --delete --exclude .git --exclude node_modules --exclude svn --exclude dist "${PLUGIN_SRC%/}/" wordpress/wp-content/plugins/wootoolkit/
fi

echo "WordPress ${WP} + WooCommerce ${WC} test environment ready in ${BASE}"
