<?php

/**
 * Plugin Name: WooCommerce AzamPay
 * Plugin URI: https://azampay.co.tz/
 * Description: Acquire consumer payments from all electronic money wallets in Tanzania.
 * Version: 1.0.0
 * Author: AzamPay
 * Author URI: https://azampay.co.tz/
 * Requires PHP: 7.0
 * WC requires at least: 6.0.0
 * WC tested up to: 6.0.1
 * Text Domain: azampay-woo
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

defined('ABSPATH') || exit;

/**
 * Initialize AzamPay WooCommerce payment gateway.
 */
function wc_azampay_payment_init()
{

    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', 'wc_azampay_missing_notice');
        return;
    }

    add_action('admin_notices', 'wc_azampay_testmode_notice');

    require_once dirname(__FILE__) . '/includes/class-wc-azampay-gateway.php';

    add_filter('woocommerce_payment_gateways', 'wc_add_azampay', 99);

    add_filter('woocommerce_currencies', 'wc_add_currencies');

    add_filter('woocommerce_currency_symbol', 'wc_add_currencies_symbol', 10, 2);

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'woo_azampay_plugin_action_links');

    add_filter('plugin_row_meta', 'woo_azampay_plugin_row_meta', 10, 2);
}

add_action('plugins_loaded', 'wc_azampay_payment_init', 99);

/**
 * Add Settings link to the plugin entry in the plugins menu.
 *
 * @param array $links Plugin action links.
 *
 * @return array
 * */
function woo_azampay_plugin_action_links($links)
{
    $settings_link = array('<a href="' .
        esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=azampay')) .
        '" title="' . esc_attr(__('View AzamPay WooCommerce Settings', 'azampay-woo')) . '">'
        . esc_html(__('Settings')) . '</a>');

    return array_merge($settings_link, $links);
}

/**
 * Show row meta on the plugin screen.
 *
 * @param mixed $links Plugin Row Meta.
 *
 * @return array
 */
function woo_azampay_plugin_row_meta($links)
{

    /**
     * The AzamPay Terms and Conditions URL.
     */
    $tnc_url = apply_filters('azampay_tnc_url', plugins_url('/includes/assets/docs/Terms_and_Conditions.pdf', __FILE__));

    /**
     * The AzamPay Privacy Policy URL.
     */
    $pp_url = apply_filters('azampay_pp_url', plugins_url('/includes/assets/docs/Privacy_Policy_V.1.0.pdf', __FILE__));

    $row_meta = array(
        'tnc' => '<a href="' . esc_url($tnc_url) . '" aria-label="' . esc_attr__('View AzamPay terms and conditions', 'azampay-woo') . '">' . esc_html__('Terms and Conditions', 'azampay-woo') . '</a>',
        'pp' => '<a href="' . esc_url($pp_url) . '" aria-label="' . esc_attr__('View AzamPay privacy policy', 'azampay-woo') . '">' . esc_html__('Privacy Policy', 'azampay-woo') . '</a>',
    );

    return array_merge($links, $row_meta);
}

/**
 * Add AzamPay Gateway to WooCommerce.
 *
 * @param array $methods WooCommerce payment gateways methods.
 *
 * @return array
 */
function wc_add_azampay($methods)
{
    $methods[] = 'WC_AzamPay_Gateway'; // payment gateway class name
    return $methods;
}

/**
 * Add AzamPay Gateway to WooCommerce.
 *
 * @param array $currencies WooCommerce currencies.
 *
 * @return array
 */
function wc_add_currencies($currencies)
{
    $currencies['TZS'] = __('Tanzanian Shillings', 'azampay-woo');
    return $currencies;
}

/**
 * Add AzamPay Gateway to WooCommerce.
 *
 * @param string $currency_symbol WooCommerce currency symbol.
 * @param string $currency WooCommerce currency.
 *
 * @return string
 */
function wc_add_currencies_symbol($currency_symbol, $currency)
{
    switch ($currency) {
        case 'TZS':
            $currency_symbol = 'TZS';
            break;
    }
    return $currency_symbol;
}

/**
 * Display a notice if WooCommerce is not installed
 */
function wc_azampay_missing_notice()
{
    echo wp_kses_post('<div class="error"><p><strong>' . sprintf(__('AzamPay requires WooCommerce to be installed and active. Click %s to install WooCommerce.', 'azampay-woo'), '<a href="' . esc_url(admin_url('plugin-install.php?tab=plugin-information&plugin=woocommerce&TB_iframe=true&width=772&height=539')) . '" class="thickbox open-plugin-details-modal">here</a>') . '</strong></p></div>');
}

/**
 * Display the test mode notice.
 * */
function wc_azampay_testmode_notice()
{

    if (!current_user_can('manage_options') || 'woocommerce_page_wc-settings' !== get_current_screen()->id) {
        return;
    }

    $azampay_settings = get_option('woocommerce_azampay_settings');
    $test_mode = isset($azampay_settings['test_mode']) ? $azampay_settings['test_mode'] : '';
    $enabled = isset($azampay_settings['enabled']) ? $azampay_settings['enabled'] : '';
    if ('yes' === $enabled && 'yes' === $test_mode) {
        echo wp_kses_post('<div class="error"><p>' . sprintf(__('AzamPay test mode is still enabled, Click <strong><a href="%s">here</a></strong> to disable it when you want to start accepting live payment on your site.', 'azampay-woo'), esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=azampay'))) . '</p></div>');
    }
}
