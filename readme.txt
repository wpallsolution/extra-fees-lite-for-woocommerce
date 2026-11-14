=== Extra Fees Lite for WooCommerce ===
Contributors: hplugin
Tags: woocommerce, extra fees, cart fee, handling fee, checkout
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
WC requires at least: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add fixed or percentage fees to the cart, with conditions for subtotal, products, categories and payment methods.

== Description ==

**[Read the documentation](https://docs.wpallsolution.com/#/collection/xfl-plugin)** – installation, every setting explained and answers. Need help? **[Contact support](https://support.wpallsolution.com/)**.

Extra Fees Lite adds handling fees, service charges and payment-method surcharges to the WooCommerce cart. Fees appear with a clear label in the cart and checkout totals, and are saved as fee lines on the order.

**Fee rules**

* Unlimited fee rules, each one can be enabled or disabled.
* Fixed amount or percentage of the cart subtotal.
* Optional maximum fee for percentage fees, for example 5% but never more than 10.
* Taxable or not, with a tax class for each fee.
* A clear fee label shown to the customer.

**Conditions** (every condition you fill in must match)

* Cart subtotal from and up to.
* Cart contains selected products or variations.
* Cart contains products from selected categories, including child categories.
* Customer picks selected payment methods, for example a cash on delivery fee.

**Documentation**

Every setting is explained in the [documentation](https://docs.wpallsolution.com/#/collection/xfl-plugin).

**Compatibility**

* Requires WooCommerce 8.0 or newer. The plugin shows an admin notice and does nothing if WooCommerce is missing or older.

* Works with both the classic checkout and the block-based Cart and Checkout. The fee follows payment method changes live in block checkout.
* Rules with the same label are numbered automatically, so they never break the cart.
* WPML: fee labels can be translated in WPML String Translation.
* Multi-currency: fixed fees, maximum fees and subtotal conditions are entered in the base currency and converted for the customer. CURCY and WPML's WooCommerce Multilingual are supported; other switchers can use the `exfee_convert_amount` filter.

== Privacy ==

The plugin stores your fee rules only. It does not collect or store any customer data and sends no data to third-party services.

== Frequently Asked Questions ==

= How can I get support? =

Please [contact us here](https://support.wpallsolution.com/) for help, a new feature or a customization.

= Where is the documentation? =

[Read the documentation](https://docs.wpallsolution.com/#/collection/xfl-plugin) for the installation steps, every setting explained and screenshots.

= What does it need? =

WooCommerce 8.0 or newer. Without it the plugin shows an error notice in the admin.

= What happens when I delete the plugin? =

Deleting it from the Plugins screen removes the stored fee rules.

= What is the percentage fee calculated on? =

On the cart subtotal before tax and discounts.

= Does the fee apply to the whole cart or only to matching products? =

To the whole cart. Product and category conditions decide whether the fee applies.

= Does it work with the block checkout? =

Yes. The fee is added through the same WooCommerce cart hook that both checkouts use, including when the customer changes the payment method.

= Does it work with WPML? =

The fee label can be translated in WPML String Translation (context: extra-fees-lite-for-woocommerce), so customers see it in their language.

= Does it work with multi-currency stores? =

Fixed fees, the maximum fee and the subtotal conditions are entered in your base currency and converted into the customer's currency. CURCY (Woo Multi Currency) is supported through its `wmc_get_price()` function, and WPML's WooCommerce Multilingual through its `wcml_raw_price_amount` filter. For other currency plugins, hook the `exfee_convert_amount` filter and return the converted amount. Percentage fees need no conversion.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it from the Plugins screen, then activate it. WooCommerce 8.0 or newer is required.
2. Open WooCommerce > Extra Fees and click Add fee.

Need more detail? See the [documentation](https://docs.wpallsolution.com/#/collection/xfl-plugin).

== Screenshots ==

1. Fee editor with conditions.
2. Fee list with enable, edit and delete.
3. A fee on the cart totals.

== Changelog ==

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.0 =
First release.
