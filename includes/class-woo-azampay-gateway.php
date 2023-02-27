<?php

/**
 * WooCommerce AzamPay.
 *
 * Provides a AzamPay Mobile Payment Gateway.
 *
 * @class       Woo_AzamPay_Gateway
 * @extends     WC_Payment_Gateway
 * @version     1.0.2
 * @package     WooCommerce\Classes\Payment
 */

define('WOO_AZAMPAY_VERSION', '1.0.2');

if ( ! class_exists( 'Woo_AzamPay_Gateway' ) ) {
class Woo_AzamPay_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id = 'azampay';
		$this->icon = apply_filters('woocommerce_azampay_icon', plugins_url('../assets/public/images/logo.png', __FILE__));
		$this->method_title = __('AzamPay', 'azampay-woo');
		$this->method_description = __('Acquire consumer payments from all electronic money wallets in Tanzania.', 'azampay-woo');
		$this->has_fields = true;
		$this->title = 'AzamPay';
		$this->description = 'Make sure to have enough funds in your chosen wallet to avoid order cancellation.';

		// map payment api supported partner names
		$this->partners_dictionary = array(
			'Azampesa' => 'Azampesa',
			'HaloPesa' => 'Halopesa',
			'Tigopesa' => 'Tigo',
			'Airtel' => 'Airtel',
		);

		// Base URLs
		$this->test_base_url = 'https://sandbox.azampay.co.tz/';
		$this->test_auth_url = 'https://authenticator-sandbox.azampay.co.tz/';
		$this->prod_base_url = 'https://checkout.azampay.co.tz/';
		$this->prod_auth_url = 'https://authenticator.azampay.co.tz/';

		// Endpoints
		$this->partners_endpoint = 'api/v1/Partner/GetPaymentPartners';
		$this->mno_endpoint = 'azampay/mno/checkout';
		$this->token_endpoint = 'AppRegistration/GenerateToken';

    $this->source = 'Woo commerce Plugin';

		// Load the form fields
		$this->init_form_fields();

		// Load the settings
		$this->init_settings();

		// Get setting values
		$this->enabled = $this->get_option('enabled') === 'yes' ? true : false;
		$this->test_mode = $this->get_option('test_mode') === 'yes' ? true : false;
		$this->autocomplete_order = $this->get_option('autocomplete_order') === 'yes' ? true : false;
		$this->instructions = $this->get_option('instructions');
		$this->allowed_partners = empty($this->get_option('allowed_partners')) ? array(
			'Azampesa' => true,
			'HaloPesa' => true,
			'Tigopesa' => true,
			'Airtel' => true,
				) : $this->get_option('allowed_partners');

		// Production credentials
		$this->prod_app_name = $this->get_option('prod_app_name');
		$this->prod_client_id = $this->get_option('prod_client_id');
		$this->prod_client_secret = $this->get_option('prod_client_secret');
		$this->prod_callback_token = $this->get_option('prod_callback_token');
		// Test credentials
		$this->test_app_name = $this->get_option('test_app_name');
		$this->test_client_id = $this->get_option('test_client_id');
		$this->test_client_secret = $this->get_option('test_client_secret');
		$this->test_callback_token = $this->get_option('test_callback_token');

		$this->app_name = $this->test_mode ? $this->test_app_name : $this->prod_app_name;
		$this->client_id = $this->test_mode ? $this->test_client_id : $this->prod_client_id;
		$this->client_secret = $this->test_mode ? $this->test_client_secret : $this->prod_client_secret;
		$this->callback_token = $this->test_mode ? $this->test_callback_token : $this->prod_callback_token;
		$this->auth_url = $this->test_mode ? $this->test_auth_url : $this->prod_auth_url;
		$this->base_url = $this->test_mode ? $this->test_base_url : $this->prod_base_url;

		$this->token_result = $this->generate_token();
		$this->partners_result = $this->get_partners();

		// Hooks.
		add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));

		add_action('admin_notices', array($this, 'admin_notices'));

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_checkout_update_order_meta', array($this, 'azampay_checkout_update_order_meta'), 10, 1);
		add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'azampay_order_data_after_billing_address'), 10, 1);
		add_action('woocommerce_get_order_item_totals', array($this, 'azampay_order_item_meta_end'), 10, 3);

		// Webhook listener/API hook.
		add_action('woocommerce_api_wc_azampay_webhook', array($this, 'process_webhooks'));

		// thank you page hook.
		add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

		// Check if the gateway can be used.
		if (!$this->is_valid_for_use()) {
			$this->enabled = false;
		}
	}

	/**
	 * Check if this gateway is enabled and available in the user's country.
	 */
	public function is_valid_for_use() {

		$supported_currencies = array('TZS');

		if (!in_array(get_woocommerce_currency(), apply_filters('woocommerce_azampay_supported_currencies', $supported_currencies))) {
			$this->msg = sprintf(__('AzamPay does not support your store currency. Kindly set it to Tanzanian Shillings (TZS) <a href="%s">here</a>', 'azampay-woo'), esc_url(admin_url('admin.php?page=wc-settings&tab=general')));
			return false;
		}

		return true;
	}

	/**
	 * Check if AzamPay merchant details are filled.
	 */
	public function admin_notices() {

		if (!$this->enabled) {
			return;
		}

		// Check required fields.
		if ($this->test_mode && !( $this->test_client_id && $this->test_client_secret && $this->test_app_name && $this->test_callback_token )) {
			echo wp_kses_post('<div class="error"><p>' . sprintf(__('Please enter your AzamPay merchant details for test <strong><a href="%s">here</a></strong> to use the AzamPay WooCommerce plugin.', 'azampay-woo'), esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . esc_attr($this->id)))) . '</p></div>');
		} elseif (!( $this->prod_client_id && $this->prod_client_secret && $this->prod_app_name && $this->prod_callback_token )) {
			echo wp_kses_post('<div class="error"><p>' . sprintf(__('Please enter your AzamPay merchant details for production <strong><a href="%s">here</a></strong> to use the AzamPay WooCommerce plugin.', 'azampay-woo'), esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . esc_attr($this->id)))) . '</p></div>');
		}
	}

	/**
	 * Check if AzamPay gateway is enabled.
	 *
	 * @return bool
	 */
	public function is_available() {

		if ($this->enabled) {
			if (empty($this->app_name) || empty($this->client_id) || empty($this->client_secret) || empty($this->callback_token) ) {
				return false;
			}
			return true;
		}

		return false;
	}

	/**
	 * Admin Panel Options.
	 */
	public function admin_options() {
		if ('woocommerce_page_wc-settings' !== get_current_screen()->id) {
			return;
		}
		?>

		<h2><?php _e($this->title . ' Momo', 'azampay-woo'); ?></h2>


		<?php
		if ($this->is_valid_for_use()) {
			// Adding custom fields
			?>

			<h4>
				<strong><?php printf(__('Mandatory: To verify your transactions and update order status set your callback URL while registering your store to the URL below<span style="color: red"><pre><code>%1$s</code></pre></span>', 'azampay-woo'), get_site_url() . '/?wc-api=wc_azampay_webhook'); ?></strong>
			</h4>

			<table class="form-table">

				<?php
				$partnersHTML = '';
				foreach ($this->partners_dictionary as $partner => $_) {
					$partnerName = strtolower($partner);

					$disabled_flag = $partnerName === 'azampesa' ? 'disabled' : '';
					$checked_flag = $this->allowed_partners[$partner] ? 'checked' : '';

					$partnersHTML .= "<label for='woocommerce_{$this->id}_{$partnerName}_allowed'>
                            <input type='checkbox' name='woocommerce_{$this->id}_{$partnerName}_allowed' id='woocommerce_{$this->id}_{$partnerName}_allowed' value='1' {$checked_flag} {$disabled_flag}>
                            {$partner}
                          </label>";
				}
				?>

				<tr valign="top" style="display:none;">
					<th scope="row" class="titledesc">
						<label for="woocommerce_<?php echo esc_attr($this->id); ?>_allowed_partners">
							Allowed Payment Partners
						</label>
					</th>
					<td id="woocommerce_<?php echo esc_attr($this->id); ?>_allowed_partners" class="forminp">
						<fieldset style="display:flex; gap:15px;">
							<legend class="screen-reader-text">
								<span>
									Allowed Payment Partners
								</span>
							</legend>
							<?php
							echo wp_kses($partnersHTML, array(
								'label' => array(
									'for' => array()
								),
								'input' => array(
									'type' => array(),
									'name' => array(),
									'id' => array(),
									'value' => array(),
									'checked' => array(),
									'disabled' => array(),
								),
							))
							?>
							<br>
						</fieldset>
					</td>
				</tr>

				<?php
				$this->generate_settings_html();
				echo wp_kses('</table>', array('table' => array()));
		} else {
			?>

				<div class="inline error">
					<p>
            <strong>
              <?php _e('AzamPay Payment Gateway Disabled', 'azampay-woo'); ?>
            </strong>: 
            <?php
              echo wp_kses($this->msg, array(
                'a' => array(
                  'href' => array()
                )
              ));
            ?>
          </p>
				</div>

				<?php
		}
	}

		/**
		 * Save custom fields.
		 */
	public function process_admin_options() {
		parent::process_admin_options();

		$this->allowed_partners = array(
			'Azampesa' => true,
			'HaloPesa' => isset($_POST['woocommerce_azampay_halopesa_allowed']),
			'Tigopesa' => isset($_POST['woocommerce_azampay_tigopesa_allowed']),
			'Airtel' => isset($_POST['woocommerce_azampay_airtel_allowed']),
		);

		$this->update_option('allowed_partners', $this->allowed_partners);
	}

		/**
		 * Load admin scripts.
		 */
	public function admin_scripts() {
		if ('woocommerce_page_wc-settings' !== get_current_screen()->id || !$this->enabled) {
			return;
		}

		$azampay_admin_params = array(
			'id' => $this->id,
			'kycUrl' => plugins_url('../assets/public/docs/Plugin_KYCs.pdf', __FILE__)
		);

		wp_enqueue_script('wc_azampay_admin', plugins_url('../assets/admin/js/azampay-admin.js', __FILE__), array(), WOO_AZAMPAY_VERSION, true);

		wp_localize_script('wc_azampay_admin', 'wc_azampay_admin_params', $azampay_admin_params);
	}

		/**
		 * Initialise Gateway Settings Form Fields.
		 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable/Disable', 'azampay-woo'),
				'label' => __('Enable AzamPay', 'azampay-woo'),
				'type' => 'checkbox',
				'description' => '',
				'default' => 'no',
			),
			'instructions' => array(
				'title' => __('Instructions', 'azampay-woo'),
				'type' => 'textarea',
				'description' => __('Instructions that will be added to the orders page after a customer has checked out.', 'azampay-woo'),
				'default' => __('Your payment is being processed.', 'azampay-woo'),
				'desc_tip' => true,
			),
			'autocomplete_order' => array(
				'title' => __('Autocomplete Order After Payment', 'azampay-woo'),
				'label' => __('Autocomplete Order', 'azampay-woo'),
				'type' => 'checkbox',
				'description' => __('If enabled, the order will be marked as complete after successful payment', 'azampay-woo'),
				'default' => 'no',
				'desc_tip' => true,
			),
			'test_mode' => array(
				'title' => __('Test Mode', 'azampay-woo'),
				'label' => __('Enable Test mode', 'azampay-woo'),
				'type' => 'checkbox',
				'description' => '',
				'default' => 'no',
			),
			'prod_app_name' => array(
				'title' => __('Production App Name', 'azampay-woo'),
				'type' => 'text',
				'value' => '',
				'description' => __('Enter the name of the registered app.', 'azampay-woo'),
				'desc_tip' => true,
				'default' => '',
			),
			'prod_client_id' => array(
				'title' => __('Production Client ID', 'azampay-woo'),
				'type' => 'text',
				'value' => '',
				'description' => __('Enter the Client ID you received after registering the app.', 'azampay-woo'),
				'desc_tip' => true,
				'default' => '',
			),
			'prod_client_secret' => array(
				'title' => __('Production Client Secret Key', 'azampay-woo'),
				'type' => 'text',
				'value' => '',
				'description' => __('Enter the Client Secret Key you received after registering the app.', 'azampay-woo'),
				'desc_tip' => true,
				'default' => '',
			),
			'prod_callback_token' => array(
				'title' => __('Production Callback Token', 'azampay-woo'),
				'type' => 'text',
				'value' => '',
				'description' => __('Enter the Callback Token you received after registering the app.', 'azampay-woo'),
				'desc_tip' => true,
				'default' => '',
			),
			'test_app_name' => array(
				'title' => __('Test App Name', 'azampay-woo'),
				'type' => 'text',
				'value' => '',
				'description' => __('Enter the name of the test app.', 'azampay-woo'),
				'desc_tip' => true,
				'default' => '',
			),
			'test_client_id' => array(
				'title' => __('Test Client ID', 'azampay-woo'),
				'type' => 'text',
				'value' => '',
				'description' => __('Enter the Test Client ID you received after registering the app.', 'azampay-woo'),
				'desc_tip' => true,
				'default' => '',
			),
			'test_client_secret' => array(
				'title' => __('Test Client Secret Key', 'azampay-woo'),
				'type' => 'text',
				'value' => '',
				'description' => __('Enter the Test Client Secret Key you received after registering the app.', 'azampay-woo'),
				'desc_tip' => true,
				'default' => '',
			),
			'test_callback_token' => array(
				'title' => __('Test Callback Token', 'azampay-woo'),
				'type' => 'text',
				'value' => '',
				'description' => __('Enter the Test Callback Token you received after registering the app.', 'azampay-woo'),
				'desc_tip' => true,
				'default' => '',
			),
		);
	}

		/**
		 * Generate token and return result.
		 *
		 * @return array $result Token with its details.
		 */
	private function generate_token() {

		$result = [
			'success' => false,
			'message' => '',
			'token' => '',
			'code' => '',
		];

		// check if user has configured store correctly
		if (!$this->is_available()) {
      $result['message'] = $this->title . ' plugin has been configured incorrectly.';
			$result['code'] = '203';
			return $result;
		}

		$data_to_retrieve_token = array(
			'appName' => $this->app_name,
			'clientId' => $this->client_id,
			'clientSecret' => $this->client_secret,
		);

		// Generate token for App
		$token_request = wp_remote_post($this->auth_url . $this->token_endpoint, array(
			'method' => 'POST',
			'headers' => array(
				'Accept' => 'application/json',
				'Content-Type' => 'application/json',
				'X-API-KEY' => $this->callback_token,
			),
			'body' => json_encode($data_to_retrieve_token),
		));

		$token_response_code = wp_remote_retrieve_response_code($token_request);

		// Error generating token
		if (is_wp_error($token_request) || $token_response_code !== 200) {
			$result['code'] = '400';

			if ($token_response_code === 423) {
				$result['message'] = 'Provided detail is not valid for this app or secret key has expired.';
			} elseif ($token_response_code === 500) {
				$result['message'] = 'Internal Server Error.';
			} else {
				$result['message'] = 'Something went wrong. Contact store owner to have it fixed.';
			}
		}

		// if token was generated successfully
		if ($token_response_code === 200) {
			$result['code'] = '200';

			$result['token'] = json_decode(wp_remote_retrieve_body($token_request))->data->accessToken;

			$result['success'] = true;
		}

		return $result;
	}

		/**
		 * Get list of partners and return result.
		 *
		 * @return array $result Partners with their details.
		 */
	private function get_partners() {

		$result = [
			'success' => false,
			'message' => '',
			'partners' => '',
		];

		// check if user has configured store correctly
		if (!$this->is_available()) {
			$result['message'] = $this->title . ' plugin has been configured incorrectly.';
			return $result;
		}

		// check if user is authenticated
    if (!$this->token_result['success']) {
      $result['message'] = 'Your credentials are invalid.';
      return $result;
    } 

		$partners_request = wp_remote_get($this->base_url . $this->partners_endpoint, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->token_result['token'],
			),
		));

		$partners_response = json_decode(wp_remote_retrieve_body($partners_request));

		if (is_null($partners_response)) {
			$result['message'] = 'Could not get payment partners.';
		} elseif (!is_array($partners_response) && property_exists($partners_response, 'status') && $partners_response->status === 'Error') {
			$result['message'] = property_exists($partners_response, 'message') ? 'Could not get payment partners. ' . $partners_response->message : 'Could not get payment partners.';
		} else {
			$result['success'] = true;
			$result['partners'] = $partners_response;
		}

		return $result;
	}

		/**
		 * Display the payment fields.
		 */
	public function payment_fields() {

		if (!is_checkout()) {
			return;
		}

		// include plugin styling for checkout fields
		wp_enqueue_style('styles', plugins_url('../assets/public/css/azampay-styles.css', __FILE__), array(), false);

		if ($this->description) {
			if ($this->test_mode) {
				$this->description .= '<p class="form-row form-row-wide" style="margin-top:5px">TEST MODE ENABLED. In Sandbox, you can use the AzamPesa numbers listed below to proceed with tests for the different scenarios.</p>';
				$this->description = trim($this->description);
			}

			// display the description with <p> tags etc.
			echo wpautop(wp_kses_post($this->description));
		}

		// Disable payment method selection if error
		if (!$this->token_result['success'] || !$this->partners_result['success']) {
			?>
				<script type="text/javascript">
					jQuery("input[name=\'payment_method\']").prop("checked", false);
					jQuery("#payment_method_<?php echo esc_js($this->id); ?>").prop("disabled", true);
				</script>
				<?php
		}

		// Failed to generate token
		if (!$this->token_result['success']) {
			// error messages for admins and non admins
			$admin_message = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . esc_attr($this->id))) . '" target="_blank">Click here to configure the plugin</a>.';
			$non_admin_message = ' Contact store owner to have it fixed.';

			// Incorrect configuration
			if ($this->token_result['code'] === '203') {
				$message = current_user_can('manage_options') ? $this->token_result['message'] . ' ' . $admin_message : $this->token_result['message'] . ' ' . $non_admin_message;
				$notice_type = 'notice';
			} else {
				$message = $this->token_result['message'];
				$notice_type = 'error';
			}

			// To avoid duplicate notices
			if (!wc_has_notice($message, $notice_type)) {
				wc_add_notice($message, $notice_type);
			}
			return;

			// Failed to get partners
		} elseif (!$this->partners_result['success']) {
			if (!wc_has_notice($this->partners_result['message'], 'error')) {
				wc_add_notice($this->partners_result['message'], 'error');
			}

			return;
		} else {
			// form fields
			$other_fields = '';

			foreach ($this->partners_result['partners'] as $partner) {
				$partner_name = $partner->partnerName;

				// skip partner if disabled
				if (!$this->allowed_partners[$partner_name]) {
					continue;
				}

				$partner_value = array_key_exists($partner_name, $this->partners_dictionary) ? $this->partners_dictionary[$partner_name] : $partner_name;

				if ($partner_name === 'Azampesa') {
					$azampesa_field = '<div class="form-row form-row-wide azampesa-label-container">
                              <label class="azampesa-container">
                                <input id="azampesa-radio-btn" type="radio" name="payment_network" value=' . esc_attr($partner_value) . '>
                                <div class="azampesa-right-block">
                                  <p>Pay with AzamPesa</p>
                                  <img class="azampesa-img" src=' . plugins_url('../assets/public/images/azampesa-logo.svg',__FILE__) . ' alt=' . esc_attr($partner_value) . '>
                                </div>
                              </label>
                            </div>';
				} else {
					$logo_path = '../assets/public/images/' . esc_attr(strtolower($partner_name)) . '-logo.svg';
					$other_fields .= '<label>
                          <input class="other-partners-radio-btn" type="radio" name="payment_network" value=' . esc_attr($partner_value) . '>
                          <img class="other-partner-img" src=' . plugins_url($logo_path, __FILE__) . ' alt=' . esc_attr($partner_name) . '>
                        </label>';
				}
			}

			$form_html = '<fieldset id="wc-' . esc_attr($this->id) . '-form" class="wc-payment-form">
                      <input id="payment_number_field" name="payment_number" class="form-row form-row-wide payment-number-field" placeholder="Enter mobile phone number" type="text" role="presentation">
                      ' . $azampesa_field;

			if (!empty($other_fields)) {
				$form_html .= '<button id="other-mno-btn" class="form-row-wide">Pay with other MNO</button>
                      <hr class="form-row form-row-wide divider"/>
                      <div class="form-row form-row-wide content radio-btn-container">
                        <div>' . $other_fields . '</div>
                        <hr class="form-row form-row-wide divider"/>
                      </div>';
			}

			$form_html .= '</fieldset>';

			$allowed_post = wp_kses_allowed_html('post');
			$allowed_inputs = array(
				'input' => array(
					'type' => array(),
					'value' => array(),
					'placeholder' => array(),
					'class' => array(),
					'id' => array(),
					'name' => array(),
				)
			);

			echo wp_kses($form_html, array_merge($allowed_inputs, $allowed_post));

			// Enable payment method and make the other wallets button collapsible
			?>
				<script type="text/javascript">
					jQuery("#payment_method_<?php echo esc_js($this->id); ?>").prop("disabled", false);
					var btn = jQuery("#other-mno-btn");
					var azampesaBtn = jQuery("#azampesa-radio-btn");
					btn.click(function (e) {
						e.preventDefault();
						jQuery(this).toggleClass("active");
						var content = jQuery(".radio-btn-container");
						if (content.css("max-height") && content.css("max-height") !== "0px") {
							if (jQuery(".other-partners-radio-btn:checked").length === 1) {
								azampesaBtn.prop("checked", true);
							}
							content.css("max-height", "0px");
						} else {
							content.css("max-height", content.prop("scrollHeight") + "px");
						}
					});
				</script>
				<?php
		}
	}

		/**
		 * Validate payment fields.
		 *
		 * @return bool
		 */
	public function validate_fields() {

		$is_azampay_selected = 'azampay' === sanitize_text_field($_POST['payment_method']);

		$payment_number = sanitize_text_field($_POST['payment_number']);

		$payment_network = sanitize_text_field($_POST['payment_network']);

		if ($is_azampay_selected && !isset($payment_number) || empty($payment_number)) {
			wc_add_notice('Please enter a valid phone number that is to be billed.', 'error');

			return false;
		}

		if ($is_azampay_selected && !isset($payment_network) || empty($payment_network)) {
			wc_add_notice('Please select a payment network.', 'error');

			return false;
		}

		// Pattern for valid phone number:
		// For all: [0|255|+255][777][123456]
		// For azampesa: [0|1|255|+255][777][123456]
		$payment_number_pattern = $payment_network === 'Azampesa' ? '/^(0|1|255|\+255)?(6[1-9]|7[1-8])([0-9]{7})$/' : '/^(0|255|\+255)?(6[1-9]|7[1-8])([0-9]{7})$/';

		if (!preg_match($payment_number_pattern, $payment_number)) {
			wc_add_notice('Please enter a valid phone number that is to be billed.', 'error');

			return false;
		}

		return true;
	}

		/**
		 * Add payment details to order.
		 *
		 * @param int $order_id
		 */
	function azampay_checkout_update_order_meta( $order_id) {

		$payment_number = sanitize_text_field($_POST['payment_number']);

		if (!isset($payment_number) || !empty($payment_number)) {
			update_post_meta($order_id, 'payment_number', $payment_number);
		}

		$payment_network = sanitize_text_field($_POST['payment_network']);

		if (!isset($payment_network) || !empty($payment_network)) {
			update_post_meta($order_id, 'payment_network', $payment_network);
		}
	}

		/**
		 * Update order details on order page for admins.
		 *
		 * @param WC_Order $order Order object.
		 */
	function azampay_order_data_after_billing_address( $order) {

		wp_kses_post('<p><strong>' . __('Payment Phone Number:', 'azampay-woo') . '</strong></br>' . get_post_meta($order->get_id(), 'payment_number', true) . '</p>');

		wp_kses_post('<p><strong>' . __('Payment Network:', 'azampay-woo') . '</strong></br>' . get_post_meta($order->get_id(), 'payment_network', true) . '</p>');
	}

		/**
		 * Update order details on order page for customer.
		 *
		 * @param array $total_rows.
		 * @param WC_Order $order Order object.
		 * @return array $total_rows.
		 */
	function azampay_order_item_meta_end( $total_rows, $order) {

		// Set last total row in a variable and remove it.
		$order_total = $total_rows['order_total'];

		unset($total_rows['order_total']);

		// Insert new rows
		$total_rows['payment_number'] = array(
			'label' => __('Payment number:', 'azampay-woo'),
			'value' => get_post_meta($order->get_id(), 'payment_number', true),
		);

		$total_rows['payment_network'] = array(
			'label' => __('Payment network:', 'azampay-woo'),
			'value' => get_post_meta($order->get_id(), 'payment_network', true),
		);

		// Set back last total row
		$total_rows['order_total'] = $order_total;

		return $total_rows;
	}

		/**
		 * Process the payment and return the result.
		 *
		 * @param int $order_id Order ID.
		 * @return array
		 */
	public function process_payment( $order_id) {
		$order = wc_get_order($order_id);

		if ($order->get_total() > 0) {
			$result = $this->azampay_payment_processing($order);

			// return if there was an error
			if (!$result) {
				return;
			}
		}

		// Remove cart.
		WC()->cart->empty_cart();

		// Return thankyou redirect.
		return array(
			'result' => 'success',
			'redirect' => $this->get_return_url($order),
		);
	}

		/**
		 * Process payment through api.
		 *
		 * @param  WC_Order $order Order object.
		 * @return bool
		 */
	private function azampay_payment_processing( $order) {

		$checkout_data = array(
			'provider' => sanitize_text_field($_POST['payment_network']),
			'source' => $this->source,
			'accountNumber' => sanitize_text_field($_POST['payment_number']),
			'amount' => $order->get_total(),
			'externalId' => $order->get_id(),
			'currency' => $order->get_currency(),
			'additionalProperties' => array(
				'customerId' => $order->get_customer_id(),
				'orderId' => $order->get_id(),
				'total' => $order->get_total(),
			),
		);

		// if token was not generated.
		if (!$this->token_result['success']) {
			wc_add_notice($this->token_result['message'], 'error');
			return false;
		} else {
			// send checkout request
			$checkout_request = wp_remote_post($this->base_url . $this->mno_endpoint, array(
				'method' => 'POST',
				'headers' => array(
					'Accept' => 'application/json',
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . $this->token_result['token'],
				),
				'body' => json_encode($checkout_data),
			));

			$checkout_response_code = wp_remote_retrieve_response_code($checkout_request);

			$checkout_response_body = json_decode(wp_remote_retrieve_body($checkout_request));

			// if checkout was unsuccessful
			if (is_wp_error($checkout_request) || $checkout_response_code !== 200) {
				$error_msg = wp_remote_retrieve_response_message($checkout_request);
				$error_msg = empty($error_msg) ? 'There was a problem with the transaction. Please contact store owner.' : $error_msg;

				wc_add_notice($error_msg, 'error');
				return false;
			} elseif (!$checkout_response_body->success) {
				wc_add_notice($checkout_response_body->message, 'error');
				return false;
			}

			// Checkout request was sent. Set payment status to pending.
			$order->update_status(apply_filters('woocommerce_azampay_process_payment_order_status', $order->has_downloadable_item() ? 'on-hold' : 'pending', $order), __('Pending Payment.', 'azampay-woo'));

			return true;
		}
	}

		/**
		 * Process callback from api and update order status
		 */
	public function process_webhooks() {

		if (( strtoupper($_SERVER['REQUEST_METHOD']) != 'POST' )) {
			http_response_code(405);
			exit;
		}

		$required_fields = [
			'utilityref',
			'reference',
			'transactionstatus',
			'amount'
		];

		// get request body
		$json = file_get_contents('php://input');

		if (empty($json)) {
			http_response_code(400);
			echo esc_html('Payload empty.');
			exit;
		}

		$data = json_decode($json);

		// make sure all required properties exist on payload
		foreach ($required_fields as $field) {
			if (!property_exists($data, $field)) {
				http_response_code(400);
				echo esc_html($field . ' must be specified in payload.');
				exit;
			}
		}

		$order_id = $data->utilityref ? $data->utilityref : null;

		if (is_null($order_id)) {
			http_response_code(400);
			echo esc_html('Order id not specified.');
			exit;
		}

		$order = wc_get_order($order_id);

		if (is_null($order)) {
			http_response_code(400);
			echo esc_html('Order with given order id does not exist.');
			exit;
		}

		$order_status = $order->get_status();

		if (in_array($order_status, array('processing', 'completed', 'on-hold'))) {
			echo esc_html('Order has already been processed.');
			exit;
		}

		$amount_paid = $data->amount ? $data->amount : null;

		$order_total = $order->get_total();

		if (is_null($amount_paid)) {
			http_response_code(400);
			echo esc_html('Amount not specified.');
			exit;
		}

		$transaction_status = $data->transactionstatus ? $data->transactionstatus : null;

		if (is_null($transaction_status)) {
			http_response_code(400);
			echo esc_html('Transaction status not specified.');
			exit;
		}

		$message = $data->message ? $data->message : null;

		$azampay_ref = $data->reference ? $data->reference : null;

		$order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : $order->get_order_currency();

		$currency_symbol = get_woocommerce_currency_symbol($order_currency);

		if ($transaction_status === 'success') {
			// check if the amount paid is equal to the order amount.
			if ($amount_paid < $order_total) {
				$order->update_status('on-hold', '');

				add_post_meta($order_id, '_transaction_id', $azampay_ref, true);

				$notice = sprintf(__('Thank you for shopping with us.%1$sYour payment transaction was successful, but the amount paid is not the same as the total order amount.%2$sYour order is currently on hold.%3$sKindly contact us for more information regarding your order and payment status.', 'azampay-woo'), '<br />', '<br />', '<br />');
				$notice_type = 'notice';

				// Add Customer Order Note
				$order->add_order_note($notice, 1);

				// Add Admin Order Note
				$admin_order_note = sprintf(__('<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Amount paid is less than the total order amount.%3$sAmount Paid was <strong>%4$s (%5$s)</strong> while the total order amount is <strong>%6$s (%7$s)</strong>%8$s<strong>AzamPay Transaction Reference:</strong> %9$s', 'azampay-woo'), '<br />', '<br />', '<br />', $currency_symbol, $amount_paid, $currency_symbol, $order_total, '<br />', $azampay_ref);

				$order->add_order_note($admin_order_note);

				function_exists('wc_reduce_stock_levels') ? wc_reduce_stock_levels($order_id) : $order->reduce_order_stock();

				wc_add_notice($notice, $notice_type);
			} else {
				$order->payment_complete($azampay_ref);

				$order->add_order_note(sprintf(__('Payment via AzamPay successful (Transaction Reference: %s)', 'azampay-woo'), $azampay_ref));

				if ($this->is_autocomplete_order_enabled($order)) {
					$order->update_status('completed');
				}
			}
		} else {
			$order->update_status('failed', __('Payment was declined by AzamPay.', 'azampay-woo'));
		}

		// Add Customer Order Note
		if (!is_null($message)) {
			$order->add_order_note($message, 1);
		}

		echo esc_html('Order updated.');

		exit;
	}

		/**
		 * Checks if autocomplete order is enabled for the payment method.
		 *
		 * @param WC_Order $order Order object.
		 * @return bool
		 */
	protected function is_autocomplete_order_enabled( $order) {
		$autocomplete_order = false;

		$payment_method = $order->get_payment_method();

		$azampay_settings = get_option('woocommerce_' . $payment_method . '_settings');

		if (isset($azampay_settings['autocomplete_order']) && 'yes' === $azampay_settings['autocomplete_order']) {
			$autocomplete_order = true;
		}

		return $autocomplete_order;
	}

		/**
		 * Output for the order received page.
		 */
	public function thankyou_page() {
		if ($this->instructions) {
			echo wp_kses_post(wpautop(wptexturize($this->instructions)));
		}
	}
}
}