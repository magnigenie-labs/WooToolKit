=== B2B, Wholesale Prices & Request a Quote for WooCommerce – WooToolkit ===
Contributors: magnigenie, sagarseth9, kshirod-patel, dipakbbsr
Tags: wholesale, b2b, request a quote, catalog mode, wholesale prices
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Wholesale prices, request a quote and catalog mode for WooCommerce. Built for the Cart & Checkout blocks and HPOS.

== Description ==

WooToolkit turns a WooCommerce store into a B2B store. Switch on only the kits you need: each one works on its own, and together they cover the usual wholesale setup.

**Built for today's WooCommerce.** Every kit works with the Cart & Checkout blocks, the Store API and High-Performance Order Storage (HPOS), and is tested on every WooCommerce release against the current and previous WordPress and WooCommerce versions on PHP 7.4 to 8.4.

= Wholesale prices =

* A **Wholesale customer** role. Assign it to any user.
* A wholesale price on each product and on each variation, a wholesale discount (%) per product category, or a store-wide wholesale discount.
* Optionally show the retail price crossed out next to the wholesale price.
* Wholesale customers see and pay their prices everywhere: shop pages, product blocks, the Store API, the cart and the checkout. Guests and retail customers keep seeing retail prices.
* Orders from wholesale customers are marked "Wholesale order" in the order screen.

= Catalog mode =

* Hide prices, stop purchases, or both.
* For everyone, for guests only, for logged-in customers only, or for everyone except wholesale customers ("log in to see prices").
* On all products, on chosen categories (subcategories included) or on products you tick one by one.
* Replace the price with your own text and the Add to cart button with a product link, a custom link or **Add to quote**.
* Purchases are refused by WooCommerce itself, so direct add-to-cart links and the Store API cannot get around it. Hidden prices are also kept out of the Store API and Google's product data.

= Request a quote =

* An **Add to quote** button on product pages, next to Add to cart or instead of it in Catalog mode. Variable products work too.
* A quote page (Quote block or `[wootoolkit_quote]` shortcode) where shoppers review their list and send the request with their name, email, company and message.
* **Each quote is a WooCommerce order** with the status "Quote requested". Edit products, prices and shipping in the normal order screen, then choose **Send quote to customer**. The customer gets an email and pays through WooCommerce's own pay-for-order page with any payment method you offer.
* Emails for the store ("New quote request") and the customer ("Your quote"), editable under WooCommerce > Settings > Emails.
* Quotes stay out of WooCommerce Analytics until they are paid.

= Classic kits =

Sites that used WooToolkit 1.x keep their kits as **Classic kits**: Accordions, Continue Shopping, Custom Product Tabs, Empty Cart, Mailchimp, Product Sorting Options, Products Per Page, Sequential Order Numbers and WooCommerce Customizer. They get bug fixes but no new features, and they only work with the classic cart and checkout.

= Need a kit we don't have? =

Open a topic in the [support forum](https://wordpress.org/support/plugin/wootoolkit/) and tell us what your store needs.

== Installation ==

1. Install and activate WooCommerce.
2. Install WooToolkit from Plugins > Add New, or upload the plugin folder to `/wp-content/plugins/`, and activate it.
3. Go to WooCommerce > Settings > WooToolkit and switch on the kits you need. Each kit adds its own settings section on that screen.

== Frequently Asked Questions ==

= Does it work with the Cart & Checkout blocks and HPOS? =

Yes. Wholesale prices, Catalog mode and Request a quote support both. WooToolkit tells WooCommerce which features it supports based on the kits you have switched on. The Classic kits support only the classic cart and checkout, and Sequential Order Numbers does not support HPOS.

= How do I make someone a wholesale customer? =

Switch on the Wholesale prices kit, then edit the user and change their role to "Wholesale customer".

= How does a customer pay for a quote? =

When you choose "Send quote to customer" on the order, WooCommerce's pay-for-order link is emailed to them. They pay with any payment method your store offers, and the order then moves to Processing (or On hold) like any other order. Guests confirm their email address before paying.

= Can shoppers request a quote for products they cannot buy? =

Yes. That is what Catalog mode with the "Add to quote" button is for.

= Does Catalog mode stop people from buying through add-to-cart links? =

Yes. Catalog products are unpurchasable in WooCommerce itself, so `?add-to-cart=` links, the Store API and carts saved earlier are all refused.

= I used WooToolkit 1.x. What happens when I update? =

Every kit you had switched on stays on as a Classic kit, with its settings. The 1.x Catalog kit is replaced by the new Catalog mode kit and your settings are carried over. It is switched on only if catalog mode was on in 1.x. Kits are now managed under WooCommerce > Settings > WooToolkit, and Classic kit settings are under WooCommerce > WooToolkit Classic.

= Does WooToolkit store personal data? =

Quote requests are stored as WooCommerce orders, with the name, email, phone, company and message the shopper enters. They are covered by WooCommerce's personal data export and erasure tools. WooToolkit does not send data to external services, except the Classic Mailchimp kit, which sends the emails of customers who opt in at checkout to your Mailchimp list.

== Screenshots ==

1. Switch on the kits you need under WooCommerce > Settings > WooToolkit.
2. Wholesale prices: a wholesale customer sees the wholesale price with the retail price crossed out.
3. Wholesale prices: wholesale price on the product, per category and store-wide.
4. Catalog mode settings: who, which products, prices, purchases and the replacement button.
5. Catalog mode on a product page, with "Add to quote" instead of Add to cart.
6. The quote page: the shopper's quote list and request form.
7. A quote in the order screen, with "Send quote to customer".
8. The customer's quote email with the link to pay.

== Changelog ==

= 2.0.0 =
* New: Kits are managed under WooCommerce > Settings > WooToolkit, which replaces the old Installed Kits screen.
* New: Kit framework and registry. WooToolkit declares HPOS and Cart & Checkout block compatibility based on the kits you actually have switched on.
* New: Wholesale prices kit. A Wholesale customer role with per-product, per-variation, per-category or store-wide wholesale prices, in classic templates, the blocks, the Store API, the cart and checkout.
* New: Catalog mode kit, replacing the 1.x Catalog kit. Hide prices and stop purchases for everyone, guests, or everyone except wholesale customers, on all products, chosen categories or chosen products. Purchases are refused by WooCommerce itself, so add-to-cart links and the Store API are blocked too. 1.x Catalog settings are carried over.
* New: Request a quote kit. Shoppers add products to a quote list and send a request; each quote is a WooCommerce order with the status "Quote requested". Edit the prices, choose "Send quote to customer", and the customer pays through WooCommerce's pay-for-order page. Includes a Quote block and [wootoolkit_quote] shortcode, two emails, and an "Add to quote" button for Catalog mode.
* Changed: The 1.x kits become "Classic kits" (bug fixes only). Their settings moved to WooCommerce > WooToolkit Classic.
* Changed: Fresh installs start with no kits switched on. Upgrades from 1.x keep every kit that was on.
* Changed: WooCommerce is now a required plugin. Without it WooToolkit shows a notice instead of deactivating itself.

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

= 2.0.0 =
Major update. Kits move to WooCommerce > Settings > WooToolkit and every 1.x kit you use keeps working as a Classic kit. The 1.x Catalog kit is replaced by the new Catalog mode kit, with your settings carried over. WooCommerce is now required. Please update a staging site first.

= 1.0.3 =
Security release. Please update.
