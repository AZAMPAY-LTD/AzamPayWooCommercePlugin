<?php

/**
 * Plugin Name: WooCommerce AzamPay
 * Plugin URI: https://azampay.co.tz/
 * Description: Acquire consumer payments from all electronic money wallets in Tanzania.
 * Author: AzamPay
 * Author URI: https://azampay.co.tz/
 * Version: 1.1.1
 * Requires at least: 6.0
 * Tested up to: 6.4.2
 * Requires PHP: 7.0
 * WC requires at least: 8.2.0
 * WC tested up to: 8.5.1
 * Text Domain: azampay-woo
 * License: GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 */

defined('ABSPATH') || exit;

/**
 * Required minimums and constants
 */
define( 'WC_AZAMPAY_VERSION', '1.1.1' ); // WRCS: DEFINED_VERSION.
define( 'WC_AZAMPAY_MIN_PHP_VER', '7.0.0' );
define( 'WC_AZAMPAY_MIN_WC_VER', '7.4' );
define( 'WC_AZAMPAY_FUTURE_MIN_WC_VER', '7.5' );
define( 'WC_AZAMPAY_MAIN_FILE', __FILE__ );
define( 'WC_AZAMPAY_ABSPATH', __DIR__ . '/' );
define( 'WC_AZAMPAY_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_AZAMPAY_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

/**
 * Display a notice if WooCommerce is not installed
 * 
 * @since 1.0.0
 */
function woo_azampay_missing_wc_notice() {
	echo wp_kses_post('<div class="error"><p><strong>' . sprintf(__('AzamPay requires WooCommerce to be installed and active. Click %s to install WooCommerce.', 'azampay-woo'), '<a href="' . esc_url(admin_url('plugin-install.php?tab=plugin-information&plugin=woocommerce&TB_iframe=true&width=772&height=539')) . '" class="thickbox open-plugin-details-modal">here</a>') . '</strong></p></div>');
}

/**
 * WooCommerce not supported fallback notice.
 *
 * @since 1.1.0
 */
function woo_azampay_wc_not_supported() {
	echo wp_kses_post('<div class="error"><p><strong>' . sprintf( esc_html__( 'AzamPay requires WooCommerce %1$s or greater to be installed and active. WooCommerce %2$s is no longer supported.', 'woocommerce-gateway-azampay' ), esc_html( WC_AZAMPAY_MIN_WC_VER ), esc_html( WC_VERSION ) ) . '</strong></p></div>');
}

/**
 * Display the test mode notice.
 * 
 * @since 1.0.0
 * @version 1.1.0
 * 
 * */
function woo_azampay_testmode_notice() {

	if (!current_user_can('manage_options') || 'woocommerce_page_wc-settings' !== get_current_screen()->id) {
    return;
  }

  $azampay_settings = get_option('woocommerce_' . Woo_AzamPay_Gateway::ID . '_settings');
  $test_mode = isset($azampay_settings['test_mode']) ? $azampay_settings['test_mode'] : '';
  $enabled = isset($azampay_settings['enabled']) ? $azampay_settings['enabled'] : '';
  
  if ('yes' === $enabled && 'yes' === $test_mode) {
    echo wp_kses_post('<div class="error"><p>' . sprintf(__('AzamPay test mode is still enabled, Click <strong><a href="%s">here</a></strong> to disable it when you want to start accepting live payment on your site.', 'azampay-woo'), esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section='.Woo_AzamPay_Gateway::ID))) . '</p></div>');
  }
}


/**
 * AzamPay WooCommerce gateway.
 * 
 * @since 1.1.0
 * 
 */
function woocommerce_azampay() {

	static $plugin;

	if ( ! isset( $plugin ) ) {

    class Woo_AzamPay {

			/**
			 * The *Singleton* instance of this class
			 *
			 * @var Woo_AzamPay
			 */
			private static $instance;

			/**
			 * Returns the *Singleton* instance of this class.
			 *
			 * @return Woo_AzamPay The *Singleton* instance.
			 */
			public static function get_instance() {
				if ( null === self::$instance ) {
					self::$instance = new self();
				}
				return self::$instance;
			}

			/**
			 * The main AzamPay gateway instance. Use get_main_azampay_gateway() to access it.
			 *
			 * @var null|Woo_AzamPay_Gateway
			 */
			protected $azampay_gateway = null;

			/**
			 * Private clone method to prevent cloning of the instance of the
			 * *Singleton* instance.
			 *
			 * @return void
			 */
			public function __clone() {}

			/**
			 * Private unserialize method to prevent unserializing of the *Singleton*
			 * instance.
			 *
			 * @return void
			 */
			public function __wakeup() {}

			/**
			 * Protected constructor to prevent creating a new instance of the
			 * *Singleton* via the `new` operator from outside of this class.
			 */
			public function __construct() {
				$this->init();
			}

			/**
			 * Init the plugin after plugins_loaded so environment variables are set.
			 *
			 * @since 1.1.0
			 */
      public function init() {
        require_once dirname(__FILE__) . '/includes/class-woo-azampay-gateway.php';
      
        add_filter( 'woocommerce_payment_gateways', [ $this, 'add_gateway' ] );
        add_filter( 'woocommerce_currencies', [ $this, 'add_currencies_to_store' ] );
        add_filter( 'woocommerce_currency_symbol', [ $this, 'add_currency_symbols_to_store' ], 10, 2 );
        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), [ $this, 'plugin_action_links'] );
        add_filter( 'plugin_row_meta',[ $this, 'plugin_row_meta' ], 10, 2 );
      }

      /**
       * Add Settings link to the plugin entry in the plugins menu.
       *
       * @since 1.0.0
       * @version 1.1.0
       * @param array $links Plugin action links.
       *
       * @return array
       * */
      public function plugin_action_links( $links ) {
        $settings_link = array('<a href="' .
          esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=azampaymomo')) . '" title="' . esc_attr(__('View AzamPay WooCommerce Settings', 'azampay-woo')) . '">' . esc_html(__('Settings')) . '</a>');

        return array_merge( $settings_link, $links );
      }

      /**
       * Show row meta on the plugin screen.
       *
       * @since 1.0.0
       * @param mixed $links Plugin Row Meta.
       *
       * @return array
       */
      public function plugin_row_meta( $links ) {
      
        /**
         * The AzamPay Terms and Conditions URL.
         */
        $tnc_url = apply_filters('azampay_tnc_url', WC_AZAMPAY_PLUGIN_URL . '/assets/public/docs/Terms_and_Conditions.pdf');
      
        /**
         * The AzamPay Privacy Policy URL.
         */
        $pp_url = apply_filters('azampay_pp_url', WC_AZAMPAY_PLUGIN_URL . '/assets/public/docs/Privacy_Policy_V.1.0.pdf');
      
        $row_meta = array(
          'tnc' => '<a href="' . esc_url($tnc_url) . '" aria-label="' . esc_attr__('View AzamPay terms and conditions', 'azampay-woo') . '">' . esc_html__('Terms and Conditions', 'azampay-woo') . '</a>',
          'pp' => '<a href="' . esc_url($pp_url) . '" aria-label="' . esc_attr__('View AzamPay privacy policy', 'azampay-woo') . '">' . esc_html__('Privacy Policy', 'azampay-woo') . '</a>',
        );
      
        return array_merge($links, $row_meta);
      }

      /**
       * Add AzamPay Gateway to WooCommerce.
       *
       * @since 1.0.0
       * @version 1.1.0
       * @param array $methods WooCommerce payment gateways methods.
       *
       * @return array
       */
      public function add_gateway( $methods ) {
        $methods[] = $this->get_main_azampay_gateway();
        return $methods;
      }

      /**
       * Add TZS currency to store.
       *
       * @since 1.0.0
       * @param array $currencies WooCommerce currencies.
       *
       * @return array
       */
      public function add_currencies_to_store( $currencies) {
        $currencies['TZS'] = __('Tanzanian Shillings', 'azampay-woo');
        return $currencies;
      }
      
      /**
       * Add TZS currency symbol to store.
       *
       * @since 1.0.0
       * @param string $currency_symbol WooCommerce currency symbol.
       * @param string $currency WooCommerce currency.
       *
       * @return string
       */
      public function add_currency_symbols_to_store( $currency_symbol, $currency) {
        switch ($currency) {
          case 'TZS':
            $currency_symbol = 'TZS';
            break;
        }
        return $currency_symbol;
      }

			/**
			 * Returns the main AzamPay payment gateway class instance.
			 *
       * @since 1.0.0
			 * @return Woo_AzamPay_Gateway
			 */
			public function get_main_azampay_gateway() {
				if ( ! is_null( $this->azampay_gateway ) ) {
					return $this->azampay_gateway;
				}

				$this->azampay_gateway = new Woo_AzamPay_Gateway();

				return $this->azampay_gateway;
			}

    }

    $plugin = Woo_AzamPay::get_instance();

  }

	return $plugin;
}

/**
 * Initialize AzamPay WooCommerce payment gateway.
 * 
 * @since 1.0.0
 * @version 1.1.0
 * 
 */
function woo_azampay_init() {

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'woo_azampay_missing_wc_notice' );
		return;
	}

	if ( version_compare( WC_VERSION, WC_AZAMPAY_MIN_WC_VER, '<' ) ) {
		add_action( 'admin_notices', 'woo_azampay_wc_not_supported' );
		return;
	}

  add_action('admin_notices', 'woo_azampay_testmode_notice');

  woocommerce_azampay();
}

add_action( 'plugins_loaded', 'woo_azampay_init' );

add_action( 'woocommerce_blocks_loaded', function() {
  if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
    require_once dirname( __FILE__ ) . '/includes/class-woo-azampay-blocks-support.php';
    add_action(
      'woocommerce_blocks_payment_method_type_registration',
      function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
        if ( ! class_exists( 'Woo_AzamPay_Gateway' ) ) {
					return;
				}

				$container = Automattic\WooCommerce\Blocks\Package::container();
				// registers as shared instance.
				$container->register(
					Woo_AzamPay_Blocks_Support::class,
					new Woo_AzamPay_Blocks_Support()
				);

				$payment_method_registry->register(
					$container->get( Woo_AzamPay_Blocks_Support::class )
				);
      }
    );
  }
});

add_action(
  'before_woocommerce_init',
  function() {
  if ( class_exists(
    '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
  }
});