=== Safepay for WooCommerce ===
Contributors: safepay
Tags: safepay, payments, pakistan, woocommerce, ecommerce
Requires at least: 3.9.2
Tested up to: 5.5
Stable tag: 1.0.7
Requires PHP: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Allows you to use Safepay Checkout with the WooCommerce plugin.

== Description ==

This is the official Safepay Checkout plugin for WooCommerce. It allows you to accept credit cards and debit cards with the WooCommerce plugin. It uses a seamles integration, allowing the customer to pay on your website. This works across all browsers, and is compatible with the latest WooCommerce.

== Installation ==

1. Install the plugin from the [Wordpress Plugin Directory](https://wordpress.org/plugins/safepay-woocommerce/).
2. To use this plugin correctly, you need to be able to make network requests. Please make sure that you have the php-curl extension installed.

== Dependencies == 

1. Wordpress v3.9.2 and later
2. Woocommerce v3.1 and later
3. PHP v5.6.0 and later
4. php-curl extension

== Configuration ==

1. Visit the WooCommerce settings page, and click on the Checkout/Payment Gateways tab.
2. Click on Safepay to edit the settings. If you do not see Safepay in the list at the top of the screen make sure you have activated the plugin in the WordPress Plugin Manager.
3. Enable the Payment Method, name it Pay with Credit & Debit Cards (this will show up on the payment page your customer sees), add in your API keys and Webhook Secrets for both Sandbox and Production environments.
4. Toggle between test payments and live payments by checking/unchecking the Enable Sandbox mode checkbox.

== Upgrade Notice ==

== Changelog ==
= 1.0.7 = 
* Tested with latest releases of Wordpress and Woocommerce

= 1.0.6 = 
* Added logging for more robust debugging

= 1.0.5 = 
* Added a fix to use the correct named method on payment complete

= 1.0.2 =
* Added a fix for Woocommerce nonce checks

= 1.0.1 =
* Added reference code to order meta data.

= 1.0.0 = 
* Redirects customer to payments page on clicking Place Order, as per WooCommerce guidelines.
* Redirects customer to order details page, as per WooCommerce guidelines.

== Frequently Asked Questions ==

= Who can use this? =
 
Currently only merchants and store operators in Pakistan can use this plugin.

= Who can pay using this? =
 
Any customer with a valid Visa or MasterCard credit or debit card can make payments

= What currencies are supported? =

Currently only PKR and USD are supported. Support for more currencies will be added soon. Please follow our [blog](https://medium.com/safepay) to stay up to date on news and announcements.

== Support ==

Visit [spectrum.chat/safepay](https://spectrum.chat/safepay) to access our community and chat with fellow merchants.

Visit our [knowledge center](https://safepay.helpscoutdocs.com/) for detailed guides on how to use Safepay as a merchant. 

== Screenshots ==
1. Configuring your plugin
2. What a customer sees on checkout.


