#!/usr/bin/env bash
# Sample B2B store for the WordPress.org screenshots (runs after bin/e2e-seed.sh, in wp-env only).
set -euo pipefail
wp() { npx wp-env run cli wp "$@"; }

# wp-env has no mail server; without this, orders get "email failed to send" notes.
wp eval 'wp_mkdir_p( WPMU_PLUGIN_DIR ); file_put_contents( WPMU_PLUGIN_DIR . "/screenshots-no-mail.php", "<?php add_filter( \"pre_wp_mail\", \"__return_true\" );\n" );'
wp option update blogname 'Acme Supply'
wp option update blogdescription 'Workwear and safety gear for trade customers'
wp option update wootoolkit_active_kits '{"wholesale-prices":"yes","catalog-mode":"yes","request-a-quote":"yes","accordions":"no","empty-cart":"no"}' --format=json
wp option update wootoolkit_wholesale_discount 20
wp option update wootoolkit_catalog_scope products
wp option update wootoolkit_catalog_audience all
wp option update wootoolkit_catalog_price_text 'Price on request'
wp option update wootoolkit_catalog_button quote

# Plain product images, generated on the runner (no third-party images).
mkdir -p .screens
make_image() { convert -size 900x900 "gradient:$2" -gravity center -fill white -font DejaVu-Sans-Bold -pointsize 64 -annotate 0 "$3" ".screens/$1.png"; }
make_image gloves  '#fde68a-#d97706' 'Work Gloves'
make_image goggles '#bae6fd-#0284c7' 'Safety Goggles'
make_image vest    '#d9f99d-#65a30d' 'Hi-Vis Vest'
make_image boots   '#e5e7eb-#4b5563' 'Steel Toe Boots'
make_image hat     '#fecaca-#dc2626' 'Branded Hard Hat'

product() { # slug name price image [catalog]
	if [ "$(wp post list --post_type=product --name="$1" --format=count)" = "0" ]; then
		id=$(wp wc product create --name="$2" --slug="$1" --regular_price="$3" --short_description="Trade pricing available. Ships in 2 business days." --user=admin --porcelain | tr -d '[:space:]')
		wp media import "wp-content/plugins/wootoolkit/.screens/$4.png" --post_id="$id" --featured_image --porcelain
		if [ -n "${5:-}" ]; then
			wp post meta update "$id" _wootoolkit_catalog yes
		fi
	fi
}
product industrial-work-gloves 'Industrial Work Gloves' 19 gloves
product safety-goggles 'Safety Goggles' 14 goggles
product hi-vis-vest 'Hi-Vis Vest' 24 vest
product steel-toe-boots 'Steel Toe Boots' 89 boots
product branded-hard-hat 'Custom Branded Hard Hat' 39 hat catalog
GLOVES=$(wp post list --post_type=product --name=industrial-work-gloves --field=ID | tr -d '[:space:]')
wp post meta update "$GLOVES" _wootoolkit_wholesale_price 12.50
