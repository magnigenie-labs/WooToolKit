#!/usr/bin/env bash
# Prepares the wp-env development site for end-to-end tests.
# E2E_THEME selects the theme: storefront (classic, default) or twentytwentyfive (block theme).
set -euo pipefail
wp() { npx wp-env run cli wp "$@"; }
THEME=${E2E_THEME:-storefront}

wp plugin activate woocommerce wootoolkit
# Install the theme through WP-CLI if wp-env did not place it where WordPress expects it.
if ! wp theme is-installed "$THEME"; then
	wp theme list --fields=name,status
	wp theme install "$THEME"
fi
wp theme activate "$THEME"
# WooCommerce 11 starts new stores in "Coming soon" mode, which hides products from visitors.
wp option update woocommerce_coming_soon no
wp rewrite structure '/%postname%/' --hard
if [ "$(wp post list --post_type=product --name=e2e-product --format=count)" = "0" ]; then
	wp wc product create --name="E2E Product" --slug=e2e-product --regular_price=19 --user=admin
fi

# Wholesale prices kit: on, a wholesale customer, and a wholesale price on the test product.
# These are throwaway credentials for the local wp-env site only.
wp option update wootoolkit_active_kits '{"wholesale-prices":"yes"}' --format=json
if ! wp user get wholesale --field=ID >/dev/null 2>&1; then
	wp user create wholesale wholesale@example.test --role=wholesale_customer --user_pass=wholesale-e2e-pass
fi
PRODUCT_ID=$(wp post list --post_type=product --name=e2e-product --field=ID | tr -d '[:space:]')
wp post meta update "$PRODUCT_ID" _wootoolkit_wholesale_price 12
