# WooToolkit

B2B, wholesale prices, request a quote and catalog mode for WooCommerce. WordPress.org slug: `wootoolkit`.
This file is for developers. The WordPress.org listing lives in `readme.txt`.

## Branches

- `master`: 2.0 development.
- `release/1.0.x`: maintenance for the 1.0 line.

**PHPUnit without Docker:** `bin/install-wp-tests.sh 7.1.3 11.2.1` (MySQL needed; args 3-6 = db name/user/pass/host), with `PLUGIN_SRC=$PWD` set, then run `vendor/bin/phpunit` from `/tmp/wp-tests/wordpress/wp-content/plugins/wootoolkit` with `WP_TESTS_DIR=/tmp/wp-tests/tests`. CI runs PHPUnit this way; wp-env is only used for E2E.

## Code layout

- `src/`: 2.0 code, namespace `WooToolkit\`, loaded by a small PSR-4 autoloader in `wootoolkit.php`. Composer is used for development only.
  - `Plugin`: wires hooks.
  - `Kit\Kit`: the kit interface. `Kit\Registry` tracks registered and active kits, and `Kit\ClassicKit` adapts the 1.x kits.
  - `Admin\SettingsPage`: the WooCommerce > Settings > WooToolkit tab.
  - `Install\Installer`: install, plus the 1.x to 2.0 migration.
- `kits/`: the 1.x "Classic" kits, loaded unchanged through `ClassicKit`. Bug fixes only.
- `includes/wootoolkit.settings.class.php`: the settings screen the Classic kits still use (WooCommerce > WooToolkit Classic).
- Add-ons register kits with `do_action( 'wootoolkit_register_kits', $registry )`. Active kits are stored in the `wootoolkit_active_kits` option as id => `yes`/`no`.

## Variation swatches hooks

The Variation swatches kit (`src/Kits/VariationSwatches/`) exposes these for add-ons such as the Pro kit:

| Hook | Type | Arguments | Use |
| --- | --- | --- | --- |
| `wootoolkit_variation_swatches_types` | filter | `array $types` (type => label) | Add a swatch type (e.g. `radio`). Unknown types render as text buttons unless `..._item_html` renders them. |
| `wootoolkit_variation_swatches_attribute_type` | filter | `string $type, string $attribute, ?WC_Product $product` | How an attribute is shown: `select` (dropdown), `color`, `image`, `label` or an added type. Per-product overrides hook here. |
| `wootoolkit_variation_swatches_term_data` | filter | `array $data, ?WP_Term $term, string $attribute, string $value, ?WC_Product $product` | Colour (`color`, hex) and image (`image_id` or `image_url`) of one option. Re-sanitised after the filter. |
| `wootoolkit_variation_swatches_item` | filter | `array $item, string $attribute, WC_Product $product, string $type` | One swatch before rendering: add `classes`, `tooltip_html` (kses'd), change label/colour/image. |
| `wootoolkit_variation_swatches_item_html` | filter | `string $html, array $item, string $type, string $attribute, WC_Product $product` | One swatch's button. Keep `role="radio"` and `data-value`. |
| `wootoolkit_variation_swatches_html` | filter | `string $out, string $select_html, array $args` | The wrapper (WooCommerce's select + the swatch group). |
| `wootoolkit_variation_swatches_image_size` | filter | `string $size` | Image size of image swatches (default `thumbnail`). |
| `wootoolkit_variation_swatches_enqueue_assets` | action | none | Fires after the swatch CSS/JS are enqueued. |

PHP helpers: `Swatches::render_group( $args )` (args as for `wc_dropdown_variation_attribute_options()`), `Swatches::attribute_type()`, `Swatches::term_data()`, `Swatches::item_inner()`, `Swatches::options()` and `Storefront::enqueue_assets()` (load the CSS/JS on any page). JS: `window.wootoolkitSwatchesInit( element )` prepares swatches added after page load.

## Requirements

PHP 7.4+, Composer 2, Node 20+ and Docker. wp-env needs Docker.

## Quick start

```bash
composer install
npm ci
npx wp-env start          # dev site http://localhost:8888, tests site http://localhost:8889 (admin / password)
npm run test:php          # PHPUnit inside the wp-env tests container
npm run env:seed          # activate WooCommerce + WooToolkit, Storefront, a test product
npx playwright install chromium
npm run test:e2e          # Playwright against http://localhost:8888
composer lint             # PHPCS: WordPress-Extra for new code, PHP 7.4+ compatibility for everything
composer analyse          # PHPStan level 5 (legacy 1.x issues are baselined in phpstan-baseline.neon)
```

To pin versions locally, create `.wp-env.override.json`, for example:

```json
{ "phpVersion": "7.4", "core": "https://wordpress.org/wordpress-7.0.7.zip",
  "plugins": [ "https://downloads.wordpress.org/plugin/woocommerce.11.1.2.zip" ] }
```

## CI (`.github/workflows/ci.yml`)

Runs on every push and pull request, and every night:

- **Versions:** `bin/resolve-versions.mjs` reads the WordPress.org APIs and produces the matrix: PHP 7.4 / 8.1 / 8.4 × the latest two WordPress branches × the latest two WooCommerce minors. A new release is picked up without any code change.
- **Lint:** PHPCS + PHPStan, plus `php -l` on PHP 7.4, 8.1 and 8.4.
- **PHPUnit:** the full matrix, in wp-env.
- **E2E:** Playwright on the latest WordPress and WooCommerce. It also fails if WooToolkit writes anything to `debug.log`.
- **WooCommerce RC:** runs PHPUnit + E2E against any WooCommerce beta or RC newer than the latest stable release.
- **Build:** produces `dist/wootoolkit-<version>.zip` (files excluded by `.distignore`) as an artifact.

## Releasing

1. Bump `Version` in `wootoolkit.php`, plus `WOOTOOLKIT_VERSION`, `Stable tag` and the changelog in `readme.txt`.
2. Run `npm run build` to create `dist/wootoolkit-<version>.zip`.
3. Sync to WordPress.org SVN with `rsync -a --delete --exclude .svn <build>/ svn/trunk/`, then `svn ci`, then `svn cp trunk tags/<version>`.

## Gotchas

- The repo stores LF line endings (`.gitattributes`). WordPress.org SVN has CRLF in `kits/` up to 1.0.3, so the first 2.0 SVN commit will show those files as fully rewritten. That's expected.
- WooCommerce 11 starts new stores in "Coming soon" mode, so visitors see no products. `env:seed` switches it off.
- `wp --context=admin` fatals with WooCommerce 11. Register admin hooks manually in tests instead.
