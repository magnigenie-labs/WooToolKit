=== WooToolkit - A Scalable WooCommerce Addon! ===
Contributors: magnigenie, sagarseth9, kshirod-patel, dipakbbsr
Tags: woocommerce, woocommerce toolkit, accordions, catalog mode, continue shopping
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=sales@magnigenie.com&item_name=WooToolkit&return=https://wootoolkit.com/
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooToolKit will help you manage all your WooCommerce needs, support, and updates from one place.

== Description ==

You have always been seeking a one-stop solution for all your WooCommerce needs. 

Yes, with this plugin, you can achieve remarkable scalability of functionalities and features, all under one plugin! The core specialty is that you can get support and updates all in one place!

= NOTE =

WooToolkit is being rebuilt. Version 2.0 will bring Cart & Checkout block support, HPOS support and new B2B kits. Version 1.0.3 is a security and maintenance release for the current kits.

= Compatibility =

* The kits hook the classic WooCommerce cart and checkout templates. They do not appear inside the Cart & Checkout blocks yet.
* The Sequential Order Numbers kit does not support High-Performance Order Storage (HPOS) yet and pauses itself when HPOS is enabled. All other kits work with HPOS.
* Catalog Mode hides the Add to Cart buttons and prices in your theme's templates. It does not block purchases through direct add-to-cart links or the Store API.

Have an idea for a kit? Open a topic in the [support forum](https://wordpress.org/support/plugin/wootoolkit/).

= FEATURES =

1. Accordions:
* Convert your WooCommerce Tabs into Accordions with the help of a powerful admin dashboard. It enables you to change each part of the accordion to match your theme design and feel.

2. Catalog Mode:
* Converts your store into catalog mode by hiding the add to cart button, price tag, ratings, and reviews.

3. Continue Shopping:
* Enables you to display a continue shopping link to the WooCommerce cart, encouraging your customers to continue shopping without going to other pages.

4. Custom Product Tabs:
* Adds different custom tabs to each WooCommerce product.

5. Empty Cart:
* Adds an empty cart button to the WooCommerce cart that works with a single click.

6. MailChimp:
* Enables you to capture email addresses of the orders on MailChimp. The plugin displays a checkbox on the checkout page to achieve this.

7. Product Sorting Options:
* Allows you to add new sorting options to the WooCommerce shop page and rename existing options.

8. Products Per Page:
* Adds a dropdown to your WooCommerce shop page, which can be used by users to change the number of products displayed on the page.

9. Sequential Order Numbers:
* Enables you to have sequential numbers for the orders you get through your WooCommerce store.

10. WooCommerce Customizer:
* Allows you to customize default WooCommerce texts and features.

== Installation ==

1. Upload "Woo Toolkit" to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Installation complete.

== Frequently Asked Questions ==

= What actually is WooToolKit? =
WooToolKit is a scalable WooCommerce addon that enables you to beautifully manage all your WooCommerce functionalities and features from one place. It helps you have hassle-free control over your WooCommerce addons.

= What does your WooToolKit contain? =
It contains 10 free kits to start with, to help you address your issues without encumbrances. With time, we are going to add more kits to help you manage more complex features.

== Screenshots ==

1. Kit listing page
2. Kit Accordion: Settings Page
3. Kit Catalog: Settings Page
4. Kit Continue Shopping: Settings Page
5. Kit Empty Cart: Settings Page
6. Kit Mailchimp: Settings Page
7. Kit Products Per Page: Settings Page
8. Kit Customizer: Settings Page
9. Kit Sorting Options: Settings Page

== Changelog ==

= 1.0.3 =
* Security: Kit activation and bulk actions now require a nonce and the manage_options capability.
* Security: Fixed a reflected XSS on the Settings screen through the tab parameter.
* Security: Custom Product Tabs now sanitises the tab title, filters tab content for users without the unfiltered_html capability, and escapes output.
* Security: The Empty Cart link now requires a nonce, so other sites cannot empty a shopper's cart.
* Security: Products Per Page only accepts the values configured in its settings.
* Fix: Mailchimp no longer stores customer emails in the database. It never re-subscribes contacts who unsubscribed, and an invalid API key can no longer break checkout.
* Fix: Removed create_function() and utf8_encode(), which fail on modern PHP.
* Fix: Listing kits outside wp-admin (for example from WP-CLI or cron) no longer causes a fatal error on PHP 8.
* Security: Continue Shopping now escapes its link and only returns shoppers to pages on your own site.
* Fix: Catalog Mode escapes its custom button and no longer uses the deprecated product ID property.
* Fix: Redirects in wp-admin (including "Settings saved" after saving kit settings) no longer fail. Two kit files printed a stray space before any output.
* Fix: Settings scripts now load only on WooToolkit screens.
* Fix: Removed PHP 8.2 dynamic-property deprecation notices and PHP warnings from unconfigured kits.
* New: Declared HPOS and Cart & Checkout block compatibility to WooCommerce.
* Requires WordPress 6.0+ and PHP 7.4+.

= 1.0.2 =
* Fixed: Minor bug issue
* Code cleanup

= 1.0.1 =
* Fixed: Required plugin active validation issue
* Code optimized

= 1.0 =
* Initial release

== Upgrade Notice ==

= 1.0.3 =
Security release. Please update.
