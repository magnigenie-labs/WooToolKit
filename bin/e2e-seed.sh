#!/usr/bin/env bash
# Prepares the wp-env development site for end-to-end tests.
set -euo pipefail
wp() { npx wp-env run cli wp "$@"; }

wp plugin activate woocommerce wootoolkit
# Install Storefront through WP-CLI if wp-env did not place it where WordPress expects it.
if ! wp theme is-installed storefront; then
	wp theme list --fields=name,status
	wp theme install storefront
fi
wp theme activate storefront
# WooCommerce 11 starts new stores in "Coming soon" mode, which hides products from visitors.
wp option update woocommerce_coming_soon no
wp rewrite structure '/%postname%/' --hard
if [ "$(wp post list --post_type=product --name=e2e-product --format=count)" = "0" ]; then
	wp wc product create --name="E2E Product" --slug=e2e-product --regular_price=19 --user=admin
fi
