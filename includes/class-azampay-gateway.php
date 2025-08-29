<?php

defined( 'ABSPATH' ) || exit;

/**
 * AzamPay for WooCommerce.
 *
 * Provides a AzamPay Mobile Payment Gateway.
 *
 * @class       AzamPay_Gateway
 * @extends     WC_Payment_Gateway
 * @package     WooCommerce\Classes\Payment
 */

class AzamPay_Gateway extends WC_Payment_Gateway
{
	const ID = 'azampaymomo';

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;


	/**
	 * Text that appears on checkout page
	 *
	 * @var string
	 */
  public $title;

	/**
	 * Url to logo for checkout page
	 *
	 * @var string
	 */
  public static $icon_url = WC_AZAMPAY_PLUGIN_URL . '/assets/public/images/logo.png';

	/**
	 * Should the order be marked as complete on payment?
	 *
	 * @var bool
	 */
	private $autocomplete_order;

	/**
	 * Phone number regex pattern for azampesa.
   * [0|1|255|+255][777][123456]
	 *
	 * @var string
	 */
  private static $phone_azampesa_regex = '/^(0|1|255|\+255)?(6[1-9]|7[1-8])([0-9]{7})$/';

	/**
	 * Phone number regex pattern for other payment partners.
   * [0|255|+255][777][123456]
	 *
	 * @var string
	 */
  private static $phone_others_regex = '/^(0|255|\+255)?(6[1-9]|7[1-8])([0-9]{7})$/';

	/**
	 * All payment partners.
	 *
	 * @var array
	 */
	private static $partners_dictionary = [
    'Azampesa' => 'Azampesa',
    'HaloPesa' => 'Halopesa',
    'Tigopesa' => 'Tigo',
    'Airtel' => 'Airtel',
    'vodacom' => 'Mpesa'
  ];

	/**
	 * Instructions to show after order payment.
	 *
	 * @var array
	 */
	private $instructions;

	/**
	 * Allowed payment partners.
	 *
	 * @var array
	 */
	private $allowed_partners;

	/**
	 * Base urls.
	 *
	 * @var array
	 */
  private static $base_urls = [
    'test_base_url' => 'https://sandbox.azampay.co.tz/',
    'test_auth_url' => 'https://authenticator-sandbox.azampay.co.tz/',

    'prod_base_url' => 'https://checkout.azampay.co.tz/',
    'prod_auth_url' => 'https://authenticator.azampay.co.tz/',
  ];

	/**
	 * Authentication base url.
	 *
	 * @var string
	 */
  private $auth_url;

	/**
	 * Checkout base url.
	 *
	 * @var string
	 */
  private $base_url;

	/**
	 * Available Endpoints.
	 *
	 * @var array
	 */
  private static $endpoints = [
    'partners' => 'api/v1/Partner/GetPaymentPartners',
    'mno' => 'azampay/mno/checkout',
    'token' => 'AppRegistration/GenerateToken',
  ];

	/**
	 * Describe the source of the payment request.
	 *
	 * @var string
	 */
  private static $source = 'Woo commerce Plugin';
  
	/**
	 * Credentials for payment api.
	 *
	 * @var array
	 */
  private $client_credentials;

  /**
   * Token result with its details.
   * 
   * @var array
   */
  public $token_result;

  /**
   * Partner details.
   * 
   * @var array
   */
  public $partners_result;

  /**
   * Constructor
   */
  public function __construct()
  {
    $this->id = self::ID;
    $this->icon = self::$icon_url;
  $this->method_title = esc_html__('AzamPay', 'azampay'); 
  $this->method_description = esc_html__('Acquire consumer payments from all electronic money wallets in Tanzania.', 'azampay');
    $this->has_fields = true;
    $this->title = 'AzamPay';
    $this->description = esc_html__('Make sure to have enough funds in your chosen wallet to avoid order cancellation.', 'azampay');

    // Load the form fields
    $this->init_form_fields();

    // Load the settings
    $this->init_settings();

    // Get setting values
    $this->enabled = $this->get_option('enabled') === 'yes' ? true : false;
    $this->testmode = $this->get_option('test_mode') === 'yes' ? true : false;
    $this->autocomplete_order = $this->get_option('autocomplete_order') === 'yes' ? true : false;
    $this->instructions = $this->get_option('instructions');
    $this->allowed_partners = empty($this->get_option('allowed_partners')) ? [
      'Azampesa' => true,
      'HaloPesa' => true,
      'Tigopesa' => true,
      'Airtel' => true,
      'vodacom' => true,
    ] : $this->get_option('allowed_partners');

    $access_key = $this->testmode ? 'test' : 'prod';

    $this->client_credentials = [
      'app_name' => $this->get_option( $access_key . '_app_name'),
      'client_id' => $this->get_option( $access_key . '_client_id'),
      'client_secret' => $this->get_option( $access_key . '_client_secret'),
      'callback_token' => $this->get_option( $access_key . '_callback_token'),
    ];

    $this->auth_url = self::$base_urls[$access_key . '_auth_url'];
    $this->base_url = self::$base_urls[$access_key . '_base_url'];

    $this->token_result = $this->generate_token();
    $this->partners_result = $this->get_all_partners();

    // Hooks.
    add_action('admin_enqueue_scripts', [ $this, 'admin_scripts' ]);

    add_action('admin_notices', [ $this, 'admin_notices' ]);

    add_action('woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ]);
    add_action('woocommerce_checkout_update_order_meta', [ $this, 'azampay_checkout_update_order_meta' ], 10, 1);
    add_action('woocommerce_admin_order_data_after_billing_address', [ $this, 'azampay_order_data_after_billing_address' ], 10, 1);
    add_action('woocommerce_get_order_item_totals', [ $this, 'azampay_order_item_meta_end' ], 10, 3);

    // Webhook listener/API hook.
    add_action('woocommerce_api_wc_azampay_webhook', [ $this, 'process_webhooks' ]);

    // thank you page hook.
    add_action('woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ]);

    // Check if the gateway can be used.
    if (!$this->is_valid_for_use()) {
      $this->enabled = false;
    }
  }

  /**
   * Check if this gateway is enabled and available in the user's country.
   * 
   * @since 1.0.0
   * @version 1.1.5
   */
  public function is_valid_for_use()
  {
    $supported_currencies = ['TZS'];

    if (!in_array(get_woocommerce_currency(), apply_filters('woocommerce_azampay_supported_currencies', $supported_currencies))) {
      /* translators: %s: URL to WooCommerce currency settings page. */
      $this->msg = sprintf(__('AzamPay does not support your store currency. Kindly set it to Tanzanian Shillings (TZS) <a href="%s">here</a>', 'azampay'), esc_url(admin_url('admin.php?page=wc-settings&tab=general')));
      return false;
    }

    return true;
  }

  /**
   * Check if AzamPay merchant details are filled.
   * 
   * @since 1.0.0
   * @version 1.1.5
   */
  public function admin_notices()
  {
    if ($this->is_available() && ( in_array(null, $this->client_credentials, true) || in_array('', $this->client_credentials, true) )) {
      $platform = $this->testmode ? 'test' : 'production';
      
      /* translators: %1$s: platform name (test/production), %2$s: URL to AzamPay merchant settings page. */
      $notice_text = __('Please enter your AzamPay merchant details for %1$s <strong><a href="%2$s">here</a></strong> to use the AzamPay for WooCommerce plugin.', 'azampay');
      echo wp_kses_post('<div class="error"><p>' . sprintf(
        $notice_text,
        esc_html($platform),
        esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $this->id))
      ) . '</p></div>');
    }
  }

  /**
   * Check if AzamPay gateway is enabled.
   *
   * @since 1.0.0
   * 
   * @return bool
   */
  public function is_available()
  {
    return $this->enabled;
  }

  /**
   * Admin Panel Options.
   *
   * @since 1.0.0
   * @version 1.1.5
   */
  public function admin_options()
  {
      if ('woocommerce_page_wc-settings' !== get_current_screen()->id) {
          return;
      }
      ?>

      <!-- Title -->
    <h2>
      <?php
      /* translators: %s is the dynamic title from $this->title. */
      printf(esc_html__('%s Momo', 'azampay'), esc_html($this->title));
      ?>
    </h2>

      <?php
      if ($this->is_valid_for_use()) {
          // Adding custom fields
          ?>

          <!-- Callback URL Notice -->
   <h4>
  <strong><?php /* translators: %s: The webhook callback URL. */ printf(esc_html__('Mandatory: To verify your transactions and update order status set your callback URL while registering your store to the URL below<span style="color: red"><pre><code>%s</code></pre></span>', 'azampay'), esc_url(get_site_url() . '/?wc-api=wc_azampay_webhook')); ?></strong>
      </h4>

          <!-- Settings Form Table -->
          <table class="form-table">
              <?php
              $partnersHTML = '';
              foreach (self::$partners_dictionary as $partner => $_) {
                  $partnerName   = strtolower($partner);
                  $disabled_flag = $partnerName === 'azampesa' ? 'disabled' : '';
                  $checked_flag  = !empty($this->allowed_partners[$partner]) ? 'checked' : '';

                  $partnersHTML .= "<label for='woocommerce_{$this->id}_{$partnerName}_allowed'>
                      <input type='checkbox' name='woocommerce_{$this->id}_{$partnerName}_allowed' id='woocommerce_{$this->id}_{$partnerName}_allowed' value='1' {$checked_flag} {$disabled_flag}>
                      {$partner}
                  </label>";
              }
              ?>

              <!-- Hidden Allowed Partners Field -->
              <tr valign="top" style="display:none;">
                  <th scope="row" class="titledesc">
            <label for="woocommerce_<?php echo esc_attr($this->id); ?>_allowed_partners">
              <?php esc_html_e('Allowed Payment Partners', 'azampay'); ?>
            </label>
                  </th>
                  <td id="woocommerce_<?php echo esc_attr($this->id); ?>_allowed_partners" class="forminp">
                      <fieldset style="display:flex; gap:15px;">
                          <legend class="screen-reader-text">
                              <span><?php esc_html_e('Allowed Payment Partners', 'azampay'); ?></span>
                          </legend>
                          <?php
                          echo wp_kses(
                              $partnersHTML,
                              [
                                  'label' => ['for' => []],
                                  'input' => [
                                      'type'    => [],
                                      'name'    => [],
                                      'id'      => [],
                                      'value'   => [],
                                      'checked' => [],
                                      'disabled'=> [],
                                  ],
                              ]
                          );
                          ?>
                          <br>
                      </fieldset>
                  </td>
              </tr>

          <?php
          // Output other settings fields
          $this->generate_settings_html();
          echo wp_kses('</table>', ['table' => []]);
      } else {
          ?>

          <!-- Disabled Gateway Notice -->
      <div class="inline error">
        <p>
          <strong>
            <?php esc_html_e('AzamPay Payment Gateway Disabled', 'azampay'); ?>
          </strong>
          <?php
          echo wp_kses(
            $this->msg,
            [
              'a' => ['href' => []],
            ]
          );
          ?>
        </p>
      </div>

          <?php
      }
  }

  /**
   * Save custom fields.
   * 
   * @since 1.0.0
   * @version 1.1.2
   */
  public function process_admin_options()
  {
    parent::process_admin_options();

    // Check if this is a settings update with proper nonce
    $is_settings_save = isset($_POST['woocommerce_' . self::ID . '_enabled']) && isset($_POST['save']) && 
                       current_user_can('manage_woocommerce') && 
                       (isset($_POST['woocommerce-settings-nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce-settings-nonce'])), 'woocommerce-settings'));
    
    $this->allowed_partners = [
      'Azampesa' => true,
      'HaloPesa' => $is_settings_save && isset($_POST['woocommerce_' . self::ID . '_halopesa_allowed']),
      'Tigopesa' => $is_settings_save && isset($_POST['woocommerce_' . self::ID . '_tigopesa_allowed']),
      'Airtel' => $is_settings_save && isset($_POST['woocommerce_' . self::ID . '_airtel_allowed']),
      'vodacom' => $is_settings_save && isset($_POST['woocommerce_' . self::ID . '_vodacom_allowed']),
    ];

    $this->update_option('allowed_partners', $this->allowed_partners);
  }

	/**
	 * Get gateway icon.
	 *
   * @since 1.1.0
   * @version 1.1.1
   * 
	 * @return string
	 */
	public function get_icon() {
    $icon = $this->icon ? '<img src="' . WC_HTTPS::force_https_url( $this->icon ) . '" alt="' . esc_attr( $this->title ) . '" />' : '';

    return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

  /**
   * Load admin scripts.
   * 
   * @since 1.0.0
   */
  public function admin_scripts()
  {
    if ('woocommerce_page_wc-settings' !== get_current_screen()->id || !$this->enabled) {
      return;
    }

    $azampay_admin_params = [
      'id' => $this->id,
      'kycUrl' => WC_AZAMPAY_PLUGIN_URL . '/assets/public/docs/Plugin_KYCs.pdf'
    ];

    wp_enqueue_script('wc_azampay_admin', WC_AZAMPAY_PLUGIN_URL . '/assets/admin/js/azampay-admin.js', [], WC_AZAMPAY_VERSION, true);

    wp_localize_script('wc_azampay_admin', 'wc_azampay_admin_params', $azampay_admin_params);
  }

  /**
   * Initialize Gateway Settings Form Fields.
   * 
   * @since 1.0.0
   */
  public function init_form_fields()
  {
    $this->form_fields = [
      'enabled' => [
        'title' => __('Enable/Disable', 'azampay'),
        'label' => __('Enable AzamPay', 'azampay'),
        'type' => 'checkbox',
        'description' => '',
        'default' => 'no',
      ],
      'instructions' => [
        'title' => __('Instructions', 'azampay'),
        'type' => 'textarea',
        'description' => __('Instructions that will be added to the orders page after a customer has checked out.', 'azampay'),
        'default' => __('Your payment is being processed.', 'azampay'),
        'desc_tip' => true,
      ],
      'autocomplete_order' => [
        'title' => __('Autocomplete Order After Payment', 'azampay'),
        'label' => __('Autocomplete Order', 'azampay'),
        'type' => 'checkbox',
        'description' => __('If enabled, the order will be marked as complete after successful payment', 'azampay'),
        'default' => 'no',
        'desc_tip' => true,
      ],
      'test_mode' => [
        'title' => __('Test Mode', 'azampay'),
        'label' => __('Enable Test mode', 'azampay'),
        'type' => 'checkbox',
        'description' => '',
        'default' => 'no',
      ],
      'prod_app_name' => [
        'title' => __('Production App Name', 'azampay'),
        'type' => 'text',
        'value' => '',
        'description' => __('Enter the name of the registered app.', 'azampay'),
        'desc_tip' => true,
        'default' => '',
      ],
      'prod_client_id' => [
        'title' => __('Production Client ID', 'azampay'),
        'type' => 'text',
        'value' => '',
        'description' => __('Enter the Client ID you received after registering the app.', 'azampay'),
        'desc_tip' => true,
        'default' => '',
      ],
      'prod_client_secret' => [
        'title' => __('Production Client Secret Key', 'azampay'),
        'type' => 'text',
        'value' => '',
        'description' => __('Enter the Client Secret Key you received after registering the app.', 'azampay'),
        'desc_tip' => true,
        'default' => '',
      ],
      'prod_callback_token' => [
        'title' => __('Production Callback Token', 'azampay'),
        'type' => 'text',
        'value' => '',
        'description' => __('Enter the Callback Token you received after registering the app.', 'azampay'),
        'desc_tip' => true,
        'default' => '',
      ],
      'test_app_name' => [
        'title' => __('Test App Name', 'azampay'),
        'type' => 'text',
        'value' => '',
        'description' => __('Enter the name of the test app.', 'azampay'),
        'desc_tip' => true,
        'default' => '',
      ],
      'test_client_id' => [
        'title' => __('Test Client ID', 'azampay'),
        'type' => 'text',
        'value' => '',
        'description' => __('Enter the Test Client ID you received after registering the app.', 'azampay'),
        'desc_tip' => true,
        'default' => '',
      ],
      'test_client_secret' => [
        'title' => __('Test Client Secret Key', 'azampay'),
        'type' => 'text',
        'value' => '',
        'description' => __('Enter the Test Client Secret Key you received after registering the app.', 'azampay'),
        'desc_tip' => true,
        'default' => '',
      ],
      'test_callback_token' => [
        'title' => __('Test Callback Token', 'azampay'),
        'type' => 'text',
        'value' => '',
        'description' => __('Enter the Test Callback Token you received after registering the app.', 'azampay'),
        'desc_tip' => true,
        'default' => '',
      ],
    ];
  }

  /**
   * Generate token and return result.
   * 
   * @since 1.0.0
   *
   * @return array $result Token with its details.
   */
  private function generate_token()
  {

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

    $data_to_retrieve_token = [
      'appName' => $this->client_credentials['app_name'],
      'clientId' => $this->client_credentials['client_id'],
      'clientSecret' => $this->client_credentials['client_secret']
    ];

    // Generate token for App
    $token_request = wp_remote_post($this->auth_url . self::$endpoints['token'], [
      'method' => 'POST',
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'X-API-KEY' => $this->client_credentials['callback_token'],
      ],
      'body' => json_encode($data_to_retrieve_token),
    ]);

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
   * @since 1.0.0
   *
   * @return array $result Partners with their details.
   */
  private function get_all_partners()
  {

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

    $partners_request = wp_remote_get($this->base_url . self::$endpoints['partners'], [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->token_result['token'],
      ],
    ]);

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
   * Generate description HTML and add test description if test mode is enabled.
   * 
   * @since 1.1.0
   * 
   * @return string description html.
   */
  public function get_description()
  {
    $description = $this->description;

    if ($description) {
      if ($this->testmode) {
        $description .= '<p class="form-row form-row-wide" style="margin-top:5px">' . esc_html__('TEST MODE ENABLED. In Sandbox, you can use the AzamPesa numbers listed below to proceed with tests for the different scenarios.', 'azampay') . '</p>';
        $description = trim($description);
      }
    }

    return wpautop(wp_kses_post($description));
  }

  /**
   * Get list of partners that are allowed.
   * 
   * @since 1.1.0
   * 
   * @return array array of key-value pairs of the allowed partners and their values.
   */
  public function get_allowed_partners()
  {
    $allowed_partners = [];

    if (!$this->partners_result["success"]) return $allowed_partners;

    foreach ($this->partners_result['partners'] as $partner) {
      $partner_name = $partner->partnerName;

      // skip partner if disabled
      if (!$this->allowed_partners[$partner_name]) {
        continue;
      }

      $partner_value = array_key_exists($partner_name, self::$partners_dictionary) ? self::$partners_dictionary[$partner_name] : $partner_name;

      $allowed_partners[$partner_name] = $partner_value;
    }

    return $allowed_partners;
  }

  /**
   * Display the payment fields.
   * 
   * @since 1.0.0
   * @version 1.1.5
   */
  public function payment_fields()
  {

    if (!is_checkout()) {
      return;
    }

    // include plugin styling for checkout fields
    wp_enqueue_style('styles', WC_AZAMPAY_PLUGIN_URL . '/assets/public/css/azampay-styles.css', [], WC_AZAMPAY_VERSION);

    echo wp_kses_post($this->get_description());

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
      $admin_message = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . esc_attr($this->id))) . '" target="_blank">' . esc_html__('Click here to configure the plugin', 'azampay') . '</a>.';
      $non_admin_message = __('Contact store owner to have it fixed.', 'azampay');

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

      $partners = $this->get_allowed_partners();

      foreach ($partners as $partner_name => $partner_value) {
        $logo_path = WC_AZAMPAY_PLUGIN_URL . '/assets/public/images/' . esc_attr(strtolower($partner_name)) . '-logo.svg';
        
        if ($partner_name === 'Azampesa') {
          $azampesa_field = '<div class="form-row form-row-wide azampesa-label-container">
                            <label class="azampesa-container">
                              <input id="azampesa-radio-btn" type="radio" name="payment_network" value=' . esc_attr($partner_value) . ' />
                              <div class="azampesa-right-block">
                                <p>Pay with AzamPesa</p>
                                <img class="azampesa-img" src=' . $logo_path . ' alt=' . esc_attr($partner_value) . ' />
                              </div>
                            </label>
                          </div>';
        } else {
          if ($partner_name === 'vodacom') {
            $logo_path = WC_AZAMPAY_PLUGIN_URL . '/assets/public/images/vodacom-logo.png';
          }

          $other_fields .= '<label>
                        <input class="other-partners-radio-btn" type="radio" name="payment_network" value=' . esc_attr($partner_value) . ' />
                        <img class="other-partner-img" src=' . $logo_path . ' alt=' . esc_attr($partner_name) . ' />
                      </label>';
        }
      }

      $form_html = '<fieldset id="wc-' . esc_attr($this->id) . '-form" class="wc-payment-form">
                    <input id="payment_number_field" name="payment_number" class="form-row form-row-wide payment-number-field" placeholder="Enter mobile phone number" type="text" role="presentation" required />
                    ' . $azampesa_field;

      if (!empty($other_fields)) {
        $form_html .= '<div class="form-row form-row-wide content radio-btn-container">' . $other_fields . '</div>';
      }

      $form_html .= '</fieldset>';

      $allowed_post = wp_kses_allowed_html('post');
      $allowed_inputs = [
        'input' => [
          'type' => [],
          'value' => [],
          'placeholder' => [],
          'class' => [],
          'id' => [],
          'name' => [],
          'checked' => [],
          'required' => []
        ]
      ];

      echo wp_kses($form_html, array_merge($allowed_inputs, $allowed_post));

      // Enable payment method
      ?>
        <script type="text/javascript">
          jQuery("#payment_method_<?php echo esc_js($this->id); ?>").prop("disabled", false);
        </script>
    <?php
    }
  }

  /**
   * Validate payment phone number.
   *
   * @since 1.0.0
   * @version 1.1.0
   * @param string $payment_number
   * @param string $payment_network
   * 
   * @return bool
   */
  private static function validate_phone_number( $payment_number, $payment_network )
  {
    if (!isset($payment_number) || empty($payment_number)) {
      return false;
    }

    $payment_number_pattern = $payment_network === 'Azampesa' ? self::$phone_azampesa_regex : self::$phone_others_regex;

    if (!preg_match($payment_number_pattern, $payment_number)) {
      return false;
    }

    return true;
  }

  /**
   * Validate payment fields.
   *
   * @since 1.0.0
   * @return bool
   */
  public function validate_fields()
  {
    // Verify checkout nonce for security
    if (!isset($_POST['woocommerce-process-checkout-nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce-process-checkout-nonce'])), 'woocommerce-process_checkout')) {
      wc_add_notice(__('Security verification failed. Please try again.', 'azampay'), 'error');
      return false;
    }
    
    if (!isset($_POST['payment_number'], $_POST['payment_network'])) {
      wc_add_notice(__('Payment details are missing.', 'azampay'), 'error');
      return false;
    }
    
    $payment_number = sanitize_text_field(wp_unslash($_POST['payment_number']));
    $payment_network = sanitize_text_field(wp_unslash($_POST['payment_network']));

    if (!isset($payment_network) || empty($payment_network)) {
      wc_add_notice('Please select a payment network.', 'error');

      return false;
    }

    if (!$this->validate_phone_number( $payment_number, $payment_network )) {
      wc_add_notice('Please enter a valid phone number that is to be billed.', 'error');

      return false;
    }

    return true;
  }

  /**
   * Add payment details to order.
   *
   * @since 1.0.0
   * @version 1.1.0
   * @param int $order_id
   */
  public function azampay_checkout_update_order_meta($order_id)
  {
    // Verify checkout nonce for security
    if (!isset($_POST['woocommerce-process-checkout-nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce-process-checkout-nonce'])), 'woocommerce-process_checkout')) {
      return;
    }
    
    $order = wc_get_order( $order_id );

    if ( self::ID !== $order->get_payment_method() ) {
      return;
    }

    $payment_number = isset($_POST['payment_number']) ? sanitize_text_field(wp_unslash($_POST['payment_number'])) : '';

    if (!empty($payment_number)) {
      $order->update_meta_data('payment_number', $payment_number);
      $order->save();
    }

    $payment_network = isset($_POST['payment_network']) ? sanitize_text_field(wp_unslash($_POST['payment_network'])) : '';

    if (!empty($payment_network)) {
      $order->update_meta_data('payment_network', $payment_network);
      $order->save();
    }
  }

  /**
   * Update order details on order page for admins.
   *
   * @since 1.0.0
   * @version 1.1.0
   * @param WC_Order $order Order object.
   */
  public function azampay_order_data_after_billing_address($order)
  {
    if ( self::ID !== $order->get_payment_method() ) {
      return;
    }
    
    $payment_number = $order->get_meta('payment_number', true);

    if ( ! empty ( $payment_number ) ) {
      echo '<p><strong>' . esc_html__('Payment Phone Number:', 'azampay') . '</strong><br />' . esc_html($payment_number) . '</p>';
    }

    $payment_network = $order->get_meta('payment_network', true);

    if ( ! empty ( $payment_network ) ) {
      echo '<p><strong>' . esc_html__('Payment Network:', 'azampay') . '</strong><br />' . esc_html($payment_network) . '</p>';
    }
  }

  /**
   * Update order details on order page for customer.
   *
   * @since 1.0.0
   * @version 1.1.0
   * @param array $total_rows.
   * @param WC_Order $order Order object.
   * 
   * @return array $total_rows.
   */
  public function azampay_order_item_meta_end($total_rows, $order)
  {
    if ( self::ID !== $order->get_payment_method() ) {
      return $total_rows;
    }

    // Set last total row in a variable and remove it.
    $order_total = $total_rows['order_total'];

    unset($total_rows['order_total']);

    // Insert new rows
    $total_rows['payment_number'] = [
      'label' => esc_html__('Payment number:', 'azampay'),
      'value' => esc_html($order->get_meta('payment_number', true)),
    ];

    $total_rows['payment_network'] = [
      'label' => esc_html__('Payment network:', 'azampay'),
      'value' => esc_html($order->get_meta('payment_network', true)),
    ];

    // Set back last total row
    $total_rows['order_total'] = $order_total;

    return $total_rows;
  }

  /**
   * Process the payment and return the result.
   *
   * @since 1.0.0
   * @version 1.1.0
   * @param int $order_id Order ID.
   * 
   * @return array
   */
  public function process_payment($order_id)
  {
    $order = wc_get_order($order_id);

    if ($order->get_total() > 0) {
      $this->azampay_payment_processing($order);
    } else {
			$order->payment_complete();
		}

    // Remove cart.
    WC()->cart->empty_cart();

    // Return thankyou redirect.
    return [
      'result' => 'success',
      'redirect' => $this->get_return_url($order),
    ];
  }

  /**
   * Process payment through api.
   *
   * @since 1.0.0
   * @version 1.1.0
   * @param  WC_Order $order Order object.
   * 
   * @return bool
   */
  private function azampay_payment_processing($order)
  {
    // Verify checkout nonce for security
    if (!isset($_POST['woocommerce-process-checkout-nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce-process-checkout-nonce'])), 'woocommerce-process_checkout')) {
      return __('Security verification failed.', 'azampay');
    }
    
    if (!isset($_POST['payment_network'], $_POST['payment_number'])) {
      return __('Payment details are missing.', 'azampay');
    }
    
    $payment_network = sanitize_text_field(wp_unslash($_POST['payment_network']));
    $payment_number = sanitize_text_field(wp_unslash($_POST['payment_number']));

    if ( empty ( $payment_network ) || empty ( $payment_number )) {
      wc_add_notice('Invalid payment details.', 'error');
      return false;
    }

    $checkout_data = [
      'provider' => $payment_network,
      'source' => self::$source,
      'accountNumber' => $payment_number,
      'amount' => $order->get_total(),
      'externalId' => $order->get_id(),
      'currency' => $order->get_currency(),
      'additionalProperties' => [
        'customerId' => $order->get_customer_id(),
        'orderId' => $order->get_id(),
        'total' => $order->get_total(),
      ],
    ];

    // if token was not generated.
    if (!$this->token_result['success']) {
      wc_add_notice($this->token_result['message'], 'error');
      return false;
    } else {
      // send checkout request
      $checkout_request = wp_remote_post($this->base_url . self::$endpoints['mno'], [
        'method' => 'POST',
        'headers' => [
          'Accept' => 'application/json',
          'Content-Type' => 'application/json',
          'Authorization' => 'Bearer ' . $this->token_result['token'],
        ],
        'body' => json_encode($checkout_data),
      ]);

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
      $order->update_status(apply_filters('woocommerce_azampay_process_payment_order_status', $order->has_downloadable_item() ? 'on-hold' : 'pending', $order), __('Pending Payment.', 'azampay'));

      return true;
    }
  }

  /**
   * Process callback from api and update order status
   * 
   * @since 1.0.0
   * @version 1.1.5
   */
  public function process_webhooks()
  {

    $request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
    if (strtoupper($request_method) !== 'POST') {
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
      esc_html_e('Payload empty.', 'azampay');
      exit;
    }


    $data = json_decode(sanitize_textarea_field($json));

    // make sure all required properties exist on payload
    foreach ($required_fields as $field) {
      if (!property_exists($data, $field)) {
        http_response_code(400);
        /* translators: %s: The missing field name in the webhook payload. */
        printf( esc_html__( '%s must be specified in payload.', 'azampay' ), esc_html( $field ) );
        exit;
      }
    }

    // Sanitize and validate all incoming data
    $order_id = isset($data->utilityref) ? sanitize_text_field($data->utilityref) : null;
    $azampay_ref = isset($data->reference) ? sanitize_text_field($data->reference) : null;
    $transaction_status = isset($data->transactionstatus) ? sanitize_text_field($data->transactionstatus) : null;
    $amount_paid = isset($data->amount) ? floatval($data->amount) : null;
    $message = isset($data->message) ? sanitize_text_field($data->message) : null;

    // Validate order_id
    if (is_null($order_id) || !is_numeric($order_id)) {
      http_response_code(400);
      esc_html_e('Order id not specified or invalid.', 'azampay');
      exit;
    }

    $order = wc_get_order($order_id);

    if (is_null($order)) {
      http_response_code(400);
      esc_html_e('Order with given order id does not exist.', 'azampay');
      exit;
    }

    $order_status = $order->get_status();

    if (in_array($order_status, ['processing', 'completed', 'on-hold'])) {
      esc_html_e('Order has already been processed.', 'azampay');
      exit;
    }

    $order_total = $order->get_total();

    if (is_null($amount_paid) || $amount_paid <= 0) {
      http_response_code(400);
      esc_html_e('Amount not specified or invalid.', 'azampay');
      exit;
    }

    if (is_null($transaction_status) || !in_array($transaction_status, ['success', 'failed'])) {
      http_response_code(400);
      esc_html_e('Transaction status not specified or invalid.', 'azampay');
      exit;
    }

    $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : $order->get_order_currency();

    $currency_symbol = get_woocommerce_currency_symbol($order_currency);

    if ($transaction_status === 'success') {
      // check if the amount paid is equal to the order amount.
      if ($amount_paid < $order_total) {
        $order->update_status('on-hold', '');

        $order->add_meta_data('transaction_id', $azampay_ref, true);

        /* translators: %1$s, %2$s, %3$s are <br /> line breaks. */
                $notice = sprintf(__('Thank you for shopping with us.%1$sYour payment transaction was successful, but the amount paid is not the same as the total order amount.%2$sYour order is currently on hold.%3$sKindly contact us for more information regarding your order and payment status.', 'azampay'), '<br />', '<br />', '<br />');
        $notice_type = 'notice';

        // Add Customer Order Note
        $order->add_order_note($notice, 1);

        // Add Admin Order Note
        /* translators: %1$s, %2$s, %3$s, %8$s are <br /> line breaks; %4$s and %6$s are currency symbols; %5$s and %7$s are amounts; %9$s is AzamPay transaction reference. */
                $admin_order_note = sprintf(__('<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Amount paid is less than the total order amount.%3$sAmount Paid was <strong>%4$s (%5$s)</strong> while the total order amount is <strong>%6$s (%7$s)</strong>%8$s<strong>AzamPay Transaction Reference:</strong> %9$s', 'azampay'), '<br />', '<br />', '<br />', $currency_symbol, $amount_paid, $currency_symbol, $order_total, '<br />', $azampay_ref);

        $order->add_order_note($admin_order_note);

        function_exists('wc_reduce_stock_levels') ? wc_reduce_stock_levels($order_id) : $order->reduce_order_stock();

        wc_add_notice($notice, $notice_type);
      } else {
        $order->payment_complete($azampay_ref);

        // translators: %s: AzamPay transaction reference.
                /* translators: %s: AzamPay transaction reference. */
                $order->add_order_note(sprintf(__('Payment via AzamPay successful (Transaction Reference: %s)', 'azampay'), $azampay_ref));

        if ($this->autocomplete_order) {
          $order->update_status('completed');
        }
      }
    } else {
      $order->update_status('failed', __('Payment was declined by AzamPay.', 'azampay'));
    }

    // Add Customer Order Note
    if (!is_null($message)) {
      $order->add_order_note($message, 1);
    }

    $order->save();

    esc_html_e('Order updated.', 'azampay');

    exit;
  }

  /**
   * Output for the order received page.
   * 
   * @since 1.0.0
   * @version 1.1.5
   */
  public function thankyou_page()
  {
    if ($this->instructions) {
      echo wp_kses_post(wpautop(wptexturize($this->instructions)));
    }
  }
}