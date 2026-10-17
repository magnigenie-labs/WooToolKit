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
wp option update wootoolkit_active_kits '{"wholesale-prices":"yes","catalog-mode":"yes","request-a-quote":"yes"}' --format=json
if ! wp user get wholesale --field=ID >/dev/null 2>&1; then
	wp user create wholesale wholesale@example.test --role=wholesale_customer --user_pass=wholesale-e2e-pass
fi
PRODUCT_ID=$(wp post list --post_type=product --name=e2e-product --field=ID | tr -d '[:space:]')
wp post meta update "$PRODUCT_ID" _wootoolkit_wholesale_price 12

# Catalog mode kit: only "E2E Catalog Product" (29.00) is in catalog mode, for everyone.
if [ "$(wp post list --post_type=product --name=e2e-catalog-product --format=count)" = "0" ]; then
	wp wc product create --name="E2E Catalog Product" --slug=e2e-catalog-product --regular_price=29 --user=admin
fi
CATALOG_ID=$(wp post list --post_type=product --name=e2e-catalog-product --field=ID | tr -d '[:space:]')
wp post meta update "$CATALOG_ID" _wootoolkit_catalog yes
wp option update wootoolkit_catalog_scope products
wp option update wootoolkit_catalog_audience all
wp option update wootoolkit_catalog_price_text 'Call for price'
wp option update wootoolkit_catalog_button custom
wp option update wootoolkit_catalog_button_text 'Ask for a price'
wp option update wootoolkit_catalog_button_url 'https://example.test/contact'

# Request a quote kit: a Quote page, and the Check payments gateway so a sent quote can be paid.
if [ "$(wp post list --post_type=page --name=quote --format=count)" = "0" ]; then
	wp post create --post_type=page --post_status=publish --post_title=Quote --post_name=quote --post_content='<!-- wp:wootoolkit/quote /-->'
fi
QUOTE_PAGE=$(wp post list --post_type=page --name=quote --field=ID | tr -d '[:space:]')
wp option update wootoolkit_quote_page_id "$QUOTE_PAGE"
wp option update woocommerce_cheque_settings '{"enabled":"yes","title":"Check payments","description":"","instructions":""}' --format=json
