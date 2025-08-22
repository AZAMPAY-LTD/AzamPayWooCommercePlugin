<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentContext;

defined( 'ABSPATH' ) || exit;

/**
 * AzamPay Payments Blocks integration
 *
 * @since 1.1.0
 * @version 1.1.2
 */
final class AzamPay_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * The gateway instance.
	 *
	 * @var AzamPay_Gateway
	 */
	private $gateway;

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = AzamPay_Gateway::ID;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_rest_checkout_process_payment_with_context', [ $this, 'add_payment_request_order_meta' ], 8, 2 );
	}

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$gateways       = WC()->payment_gateways->payment_gateways();
		$this->gateway  = $gateways[ $this->name ];
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_path       = '/assets/js/ui/wc-azampay-blocks.js';
		$script_asset_path = WC_AZAMPAY_PLUGIN_PATH . '/assets/js/ui/wc-azampay-blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => WC_AZAMPAY_VERSION
			);
		$script_url        = WC_AZAMPAY_PLUGIN_URL . $script_path;

	wp_enqueue_style('styles', WC_AZAMPAY_PLUGIN_URL . '/assets/public/css/azampay-styles.css', [], WC_AZAMPAY_VERSION);

		wp_register_script(
			'wc-azampay-blocks-integration',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);

		return [ 'wc-azampay-blocks-integration' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
   * @since 1.1.0
   * @version 1.1.1
   * 
	 * @return array
	 */
	public function get_payment_method_data() {
    
		return [
      'enabled'     => $this->gateway->partners_result["success"] && $this->gateway->token_result["success"],
      'name'        => AzamPay_Gateway::ID,
			'title'       => $this->gateway->title,
      'description' => $this->gateway->get_description(),
      'icon'        => AzamPay_Gateway::$icon_url,
      'partners'    => [
        'data'    => $this->gateway->get_allowed_partners(),
        'icons'   => $this->get_partner_icons()
      ],
			'supports'    => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] )
		];
	}

	/**
	 * Return the icons urls.
   * 
   * @since 1.1.0
   * @version 1.1.2
	 *
	 * @return array Arrays of icons metadata.
	 */
	private function get_partner_icons() {
		$icons_src = [
			'Azampesa'       => [
				'src' => WC_AZAMPAY_PLUGIN_URL . '/assets/public/images/azampesa-logo.svg',
				'alt' => __( 'Azampesa', 'azampay' ),
			],
			'HaloPesa'       => [
				'src' => WC_AZAMPAY_PLUGIN_URL . '/assets/public/images/halopesa-logo.svg',
				'alt' => __( 'HaloPesa', 'azampay' ),
			],
			'Tigopesa' => [
				'src' => WC_AZAMPAY_PLUGIN_URL . '/assets/public/images/tigopesa-logo.svg',
				'alt' => __( 'TigoPesa', 'azampay' ),
			],
			'Airtel' => [
				'src' => WC_AZAMPAY_PLUGIN_URL . '/assets/public/images/airtel-logo.svg',
				'alt' => __( 'Airtel', 'azampay' ),
			],
			'vodacom' => [
				'src' => WC_AZAMPAY_PLUGIN_URL . '/assets/public/images/vodacom-logo.png',
				'alt' => __( 'Vodacom', 'azampay' ),
			],
		];
		return $icons_src;
	}

	/**
	 * Add payment request data to the order meta as hooked on the
	 * woocommerce_rest_checkout_process_payment_with_context action.
	 *
	 * @param PaymentContext $context Holds context for the payment.
	 */
	public function add_payment_request_order_meta( PaymentContext $context ) {
		$data = $context->payment_data;

    if ( AzamPay_Gateway::ID === $context->payment_method
      && ! empty( $data['payment_network'] )
      && ! empty ( $data['payment_number'] ) ) {
			$this->add_order_meta( $context->order, $data );
		}
	}

	/**
	 * Handles adding information about the payment request type used to the order meta.
	 *
	 * @param \WC_Order $order The order being processed.
	 * @param mixed    $data The payment data to add.
	 */
	private function add_order_meta( \WC_Order $order, $data ) {
    $payment_number = $data['payment_number'];
    $payment_network = $data['payment_network'];
    
    $order->update_meta_data('payment_number', $payment_number);
    $order->update_meta_data('payment_network', $payment_network);
    $order->save();
	}
}
