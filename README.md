=== AzamPay Payment Gateway for WooCommerce ===
Contributors: azampay
Tags: woocommerce, payment request, azampay, azampesa, mobile money, tigopesa, airtel money, halopesa, tanzania, online payments, mpesa, malipo
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
WC requires at least: 10.1.0
WC tested up to: 10.1.0
Stable tag: 1.1.4
License: GPL-3.0+
License URI: https://www.gnu.org/licenses/gpl-3.0.txt

Acquire consumer payments from all electronic money wallets in Tanzania.

== Description ==
**AzamPay Momo WooCommerce** is a WordPress plugin that allows merchants to receive mobile money payments through their woo-commerce checkout pages. This allows merchants to receive payments from their **AzamPesa**, **TigoPesa**, **AirtelMoney**, and **HaloPesa** customers.
This plugin works in both sandbox and production environments.

= Take AzamPay payments easily and directly on your store =

The plugin extends WooCommerce allowing you to take payments directly on your store via AzamPay's API.

**AzamPay** is available for store owners and merchants in Tanzania.

= Use Of Azampay Woocommerce Plugin as a Third-Party Service =

This plugin is developed to enable merchants and businesses to receive payment through the AzamPay payment gateway provided by **AzamPay Tanzania Limited**.
Upon checkout, a push request is sent to the customer's handset where they can then confirm payment.

== User Manual ==

= Test Environment =

1. Go to [Sandbox](https://developers.azampay.co.tz/) and register your website.
1. From the sandbox retrieve your credentials.
1. Go back to the plugin settings and enable Test Mode.
1. Enter your credentials to the plugin settings.
1. Test payments from the checkout Page.

= Live Environment =

1. Submit **KYCs** to AzamPay to get Live credentials.
1. Disable Test Mode in the Plugin settings.
1. Enter live credentials. Checkout is now enabled for live environment.

## SUPPORTED CURRENCIES
TZS

## SUPPORTED MOBILE MONEY CHANNELS

* AzamPesa
* AirtelMoney
* Halopesa
* TigoPesa


== Installation ==
This section describes how to install the plugin and get it working.

**AUTOMATIC INSTALLATION**

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't need to leave your web browser. To do an automatic install of the **AzamPay for WooCommerce** plugin, log in to your WordPress dashboard, navigate to the **Plugins** menu and click **Add New**.

In the search field type **AzamPay for WooCommerce** and click **Search Plugins**. Once you've found our plugin you can view details like the description. Most importantly, of course, you can install it by simply clicking **Install Now**, then **Activate**.

**MANUAL INSTALLATION**

The manual installation method involves downloading our plugin and uploading it to your web server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

* Unzip **azampay.zip** to the **/wp-content/plugins/** directory, or install from the **WordPress Plugins Directory**.
* Activate the plugin through the **Plugins** menu in WordPress.
* Configure plugin under **WooCommerce > Payments**, look for **AzamPay**.

== Frequently Asked Questions ==

= How do I approve the Payment? =

After submitting the payment through **MOMO**, confirm the payment on your phone by entering the PIN number.

= Which number is the payment request sent to? =

The payment request is sent to the mobile number provided during checkout.

= What is the format of the mobile number? =

For **Tanzania** 255xxxxxxxxx or xxxxxxxxx or 0xxxxxxxxx
No '+' sign before country code

== Important Links ==
1. **https://authenticator.azampay.co.tz** and **https://authenticator-sandbox.azampay.co.tz** are authentication URLs to get the access token for the Checkout API of AzamPay Payment Gateway in the sandbox and production environment respectively.
2. **https://sandbox.azampay.co.tz** and **https://checkout.azampay.co.tz** are used in the sandbox and production environment respectively to process checkout payment requests and related APIs.
3. **https://developers.azampay.co.tz/sandbox/registerapp** is used to register new users to get new client IDs, client secrets, and other required access to use the plugin.

== Screenshots ==

1. The AzamPay Gateway settings screen is used to configure the main Azampay gateway.
2. Checkout page that offers a range of payment methods such as local and alternative payment methods.

== Changelog ==

= 1.1.4 =
* Added support for WooCommerce 10.1.0 and PHP 8.0.

= 1.1.3 =
* Added support for wordpress 6.7.2.

= 1.1.2 =
* Added support for **M-Pesa**.

= 1.1.1 =
* Fixed invalid icon for Legacy Checkout.

= 1.1.0 =
* Added Checkout Blocks support.
* Added WooCommerce Custom Orders Table support.

= 1.0.5 =
* Removed "Pay with other MNO" button. All payment options are displayed by default.

= 1.0.4 =
* Fixed css issues.

= 1.0.3 =
* Added separate menu id on woocommerce settings layout.

= 1.0.2 =
* Added source field in checkout data.

= 1.0.1 =
* Fixed bugs related to token authentication.

= 1.0.0 =
* Our first version with **AzamPay for WooCommerce** Live and Sandbox.
* **AzamPay Checkout API** is fully supported.

== Upgrade Notice ==

= 1.1.4 =
* Added support for WooCommerce 10.1.0 and PHP 8.0.

= 1.1.3 =
* Added support for wordpress 6.7.2.

= 1.1.2 =
This version adds support for M-Pesa.

= 1.1.1 =
This version fixes the invalid icon for pages using Legacy Checkout. 

= 1.1.0 =
This version adds support for Checkout Blocks in WooCommerce. The plugin has been updated to work with the latest version of WooCommerce.

= 1.0.5 =
Removed "Pay with other MNO" button. All payment options are displayed by default.

= 1.0.4 =
This version fix css issues.

= 1.0.3 =
This version adds a separate the menu id on woocommerce settings layout.

= 1.0.2 =
This version adds a source field in the checkout data.

= 1.0.1 =
This version fixes credential-related bugs. Upgrade immediately.

= 1.0.0 =
The first version of the plugin (This version is now obsolete).
