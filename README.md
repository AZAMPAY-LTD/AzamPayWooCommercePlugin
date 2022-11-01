=== WooCommerce AzamPay ===
Contributors: azampay
Tags: woocommerce, payment request, azampay, azampesa, mobile money, tigopesa, airtel money, halopesa, tanzania, online payments, mpesa, malipo
Requires at least: 6.0
Tested up to: 6.0
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-3.0.txt

Acquire consumer payments from all electronic money wallets in Tanzania.

== Description ==
AzamPay Momo woocommerce is a wordpress plugin that allows merchants to receive mobile money payments through their woocommerce checkout pages. This allows merchants to receive payments from their AzamPesa, TigoPesa, AirtelMoney, and HaloPesa customers.
This plugin works both sandbox and production environment.

= Take AzamPay payments easily and directly on your store =

The plugin extends WooCommerce allowing you to take payments directly on your store via AzamPay’s API.

AzamPay is available for Store Owners and Merchants in Tanzania.

= Use Of Azampay Woocommerce Plugin as Third-Party Service =

This plugin is developed to enable merchants and businesses to receive payment through AzamPay payment gateway provided by AzamPay Tanzania Limited
Upon checkout, a push request is sent to the customers handset where they can then confirm payment.

== User Manual ==

= Test Environment =

1. Go to [Sandbox](https://developers.azampay.co.tz/) and register your website.
1. From the sandbox retrieve your credentials.
1. Go back to the plugin settings and enable Test Mode.
1. Enter your credentials to the plugin settings.
1. Test payments from the checkout Page.

= Live Environment =

1. Submit KYCs to AzamPay to get Live credentials.
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

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To do an automatic install of the WooCommerce AzamPay plugin, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type “WooCommerce AzamPay” and click Search Plugins. Once you’ve found our plugin you can view details about it such as the description. Most importantly, of course, you can install it by simply clicking "Install Now", then "Activate".

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your web server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

* Unzip woocommerce-azampay.zip to the /wp-content/plugins/ directory, or install from the WordPress Plugins Directory.
* Activate the plugin through the ‘Plugins’ menu in WordPress.
* Configure plugin under WooCommerce->Payments, look for AzamPay.

== Frequently Asked Questions ==

= How do I approve the Payment? =

After submitting the payment through MOMO, confirm the payment on your phone by entering the PIN number.

= Which number is the payment request sent to? =

The payment request is sent to the mobile number provided during the checkout process.

= What is the format of mobile number? =

For Tanzania 255xxxxxxxxx or xxxxxxxxx or 0xxxxxxxxx
NO ‘+’ sign before country code

== Important Links ==
1. https://authenticator.azampay.co.tz/ and https://authenticator-sandbox.azampay.co.tz/ are authentication URLs to get the access token for the Checkout API of AzamPay Payment Gateway in the sandbox and production environment respectively. 
2. https://sandbox.azampay.co.tz/ and https://checkout.azampay.co.tz/ are used in the sandbox and production environment respectively to process checkout payment request and related APIs.

== Screenshots ==

1. The AzamPay Gateway settings screen used to configure the main azampay gateway.
2. Checkout page that offers a range of payment methods such as local and alternative payment methods.

== Changelog ==

= 1.0.0 =
* Our first version with WooCommerce AzamPay Live and Sandbox. 
* AzamPay Checkout API is fully supported.

== Upgrade Notice ==

= 1.0 =
First version of the plugin.
