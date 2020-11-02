<?php
/**
 * WooCommerce Moneris
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Moneris to newer
 * versions in the future. If you wish to customize WooCommerce Moneris for your
 * needs please refer to http://docs.woocommerce.com/document/moneris/ for more information.
 *
 * @package   WC-Gateway-Moneris/Gateway
 * @author    SkyVerge
 * @copyright Copyright (c) 2012-2017, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * # Moneris Payment Gateway Interac Online
 *
 * Implements the Moneris eSELECTplus Interac Online API.  Interac online is a
 * popular Canadian payment option that allows customers to pay with their debit
 * card, using their online banking.
 *
 * ## Gateway Operation
 * This is a POST-only hosted/redirect gateway, with a direct completion call
 * after the redirect.  No IPN is used.
 *
 * The gateway flow is:
 *
 * 1. Customer selects INTERAC Online as the payment type and clicks "Place Order"
 * 2. Client is directed to the WC checkout pay page, and immediately redirected
 *    to the hosted Interac payment page
 * 3. The customer selects their financial institution, and approves (or denies)
 *    the fund transfer
 * 4. Client is redirected back to the WC API page for this gateway
 * 5. On funded response, a direct request is made to the Moneris servers to
 *    confirm the payment
 * 6. If this is successful, the client is redirected to the "thank you" page,
 *    otherwise they're redirected back to the "pay" page for the order
 *
 * ## Special Receipt Handling
 *
 * The Issuer Confirmation and Issuer Name values returned by the Interact
 * request, and persisted to the order meta, must be displayed to the customer
 * on their receipt, etc.
 *
 * This is handled by the Moneris plugin class
 *
 * ## Duplicate Invoice Numbers
 *
 * Unlike with the eSELECTplus credit card transaction requests, the invoice
 * number sent to Interac does not *appear* to need to be unique.
 *
 * @since 2.0
 */

class WC_Gateway_Moneris_Interac extends SV_WC_Payment_Gateway_Hosted {


	/** the production URL endpoint */
	const PRODUCTION_URL_ENDPOINT = 'https://gateway.interaconline.com/merchant_processor.do';

	/** the test (sandbox) URL endpoint  */
	const TEST_URL_ENDPOINT = 'https://merchant-test.interacidebit.ca/gateway/merchant_test_processor.do';

	/** the url endpoint used for the certification test transactions */
	const CERTIFICATION_URL_ENDPOINT = 'https://merchant-test.interacidebit.ca/gateway/merchant_certification_processor.do';

	/** the test store id */
	const TEST_STORE_ID = 'store3';

	/** the test API token */
	const TEST_API_TOKEN = 'yesguy';


	/** @var string production MERCHNUM provided by Moneris */
	protected $merchant_number;

	/** @var string test MERCHNUM provided by Moneris */
	protected $test_merchant_number;

	/** @var string the configured production store id */
	protected $store_id;

	/** @var string the configured test store id */
	protected $test_store_id;

	/** @var string the configured production api token */
	protected $api_token;

	/** @var WC_Moneris_API instance */
	protected $api;


	/**
	 * Initialize the gateway
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_Direct::__construct()
	 */
	public function __construct() {

		parent::__construct(
			WC_Moneris::INTERAC_GATEWAY_ID,
			wc_moneris(),
			array(
				'method_title'       => __( 'Moneris Interac', 'woocommerce-gateway-moneris' ),
				'method_description' => __( 'Allow customers to securely check out with Interac', 'woocommerce-gateway-moneris' ),
				'supports'           => array(
					self::FEATURE_PRODUCTS,
				 ),
				'payment_type'       => self::PAYMENT_TYPE_BANK_TRANSFER,
				'environments'       => array(
					self::ENVIRONMENT_PRODUCTION => __( 'Production', 'woocommerce-gateway-moneris' ),
					self::ENVIRONMENT_TEST       => __( 'Sandbox', 'woocommerce-gateway-moneris' )
				),
				'shared_settings'    => array(
					'store_id',
					'api_token',
				),
				'currencies' => array( 'CAD' ),
			)
		);

		$this->icon = $this->get_plugin()->get_plugin_url() . '/assets/images/card-interac.png';

		add_action( 'woocommerce_review_order_after_payment', array( $this, 'render_checkout_currency_disclaimer' ) );
		add_action( 'woocommerce_after_template_part',        array( $this, 'maybe_render_checkout_currency_disclaimer' ), 10, 4 );
		add_action( 'woocommerce_thankyou_moneris_interac',   array( $this, 'render_checkout_currency_disclaimer' ) );
	}


	/**
	 * Make the shared settings option a production environment-only field
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway::add_shared_settings_form_fields()
	 * @see SV_WC_Payment_Gateway::$inherit_settings
	 * @param array $form_fields gateway form fields
	 * @return array $form_fields gateway form fields
	 */
	protected function add_shared_settings_form_fields( $form_fields ) {

		$form_fields = parent::add_shared_settings_form_fields( $form_fields );

		// mark the inherit_settings field as environment production since we must always use store3 when in test mode
		$form_fields['inherit_settings']['class'] =
			( ( isset( $form_fields['inherit_settings']['class'] ) && $form_fields['inherit_settings']['class'] ) ?
				$form_fields['inherit_settings']['class'] . ' ' :
				'' ) .
			' environment-field production-field';

		$form_fields['inherit_settings']['label'] = __( 'Use connection/authentication settings from Moneris credit card gateway', 'woocommerce-gateway-moneris' );

		return $form_fields;
	}


	/**
	 * Returns an array of form fields specific for this method
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway::get_method_form_fields()
	 * @return array of form fields
	 */
	protected function get_method_form_fields() {

		$pay_url = wc_get_endpoint_url( 'order-pay', '', wc_get_page_permalink( 'checkout' ) );

		if ( 'yes' == get_option( 'woocommerce_force_ssl_checkout' ) || is_ssl() ) {
			$pay_url = str_replace( 'http:', 'https:', $pay_url );
		}

		$fields = array(

			'store_id' => array(
				'title'    => __( 'Store ID', 'woocommerce-gateway-moneris' ),
				'type'     => 'text',
				'class'    => 'environment-field production-field',
				'desc_tip' => __( 'Your Moneris store ID', 'woocommerce-gateway-moneris' ),
			),

			'api_token' => array(
				'title'    => __( 'API Token', 'woocommerce-gateway-moneris' ),
				'type'     => 'password',
				'class'    => 'environment-field production-field',
				'desc_tip' => __( 'Your Moneris API token.  Find this by logging into your Moneris account and going to Admin &gt; store settings &gt; API Token', 'woocommerce-gateway-moneris' ),
			),

			'test_store_id' => array(
				'title'       => __( 'Store ID', 'woocommerce-gateway-moneris' ),
				'type'        => 'select',
				'options'     => array(
					self::TEST_STORE_ID  => self::TEST_STORE_ID,  // interac is available for only one store
				),
				'default'     => self::TEST_STORE_ID,
				'class'       => 'environment-field test-field',
				'description' => sprintf( __( "Moneris uses a set of shared test accounts, to view your transactions log in to the %smerchant center%s with the selected store id and password 'password'", 'woocommerce-gateway-moneris' ),
											'<a href="https://esqa.moneris.com/mpg/">', '</a>' ),
			),

			'merchant_number' => array(
				'title'    => __( 'InteracOnline Merchant Number', 'woocommerce-gateway-moneris' ),
				'type'     => 'text',
				'class'    => 'environment-field production-field',
				'desc_tip' => __( 'IDEBIT_MERCHNUM value provided by Moneris', 'woocommerce-gateway-moneris' ),
				'description' => sprintf( __( 'To receive your IDEBIT_MERCHNUM you will need to provide Moneris with your funded and non-funded URL: %1$s As well as your referrer URL: %2$s', 'woocommerce-gateway-moneris' ),
					'<strong class="nowrap">' . esc_url( $this->get_transaction_response_handler_url() ) . '</strong>',
					'<strong class="nowrap">' . esc_url( $pay_url ) . '</strong>'
				),
			),

			'test_merchant_number' => array(
				'title'    => __( 'InteracOnline Merchant Number', 'woocommerce-gateway-moneris' ),
				'type'     => 'text',
				'class'    => 'environment-field test-field',
				'desc_tip' => __( 'IDEBIT_MERCHNUM value provided by Moneris', 'woocommerce-gateway-moneris' ),
				'description' => sprintf( __( 'To receive your IDEBIT_MERCHNUM you will need to provide Moneris with your funded and non-funded URL: %1$s As well as your referrer URL: %2$s', 'woocommerce-gateway-moneris' ),
					'<strong class="nowrap">' . esc_url( $this->get_transaction_response_handler_url() ) . '</strong>',
					'<strong class="nowrap">' . esc_url( $pay_url ) . '</strong>'
				),
			),
		);

		/**
		 * Filter the settings form fields for the Interac gateway
		 *
		 * TODO: remove this once using framework 3.2+ as the filter is already included there
		 *
		 * @since 2.2.2
		 * @param array $fields form fields
		 * @param \WC_Gateway_Moneris_Interac $this Moneris Interac class instance
		 */
		return apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_form_fields', $fields, $this );
	}


	/**
	 * Initialize payment gateway settings fields
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway::init_form_fields()
	 */
	public function init_form_fields() {

		parent::init_form_fields();

		// tweak the title field type so we can allow html entities
		$this->form_fields['title']['type'] = 'entity_text';
	}


	/**
	 * Get the default title for Interac, which is configurable within the
	 * admin and displayed on checkout.  Uses the recommended text from the
	 * integration guide.
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway::get_default_title()
	 * @return string payment method title to show on checkout
	 */
	protected function get_default_title() {
		return __( 'INTERAC&reg; Online', 'woocommerce-gateway-moneris' );
	}


	/**
	 * Get the default description for Interac, which is configurable
	 * within the admin and displayed on checkout.  Uses the recommended
	 * text from the integration guide.
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway::get_default_description()
	 * @return string payment method description to show on checkout
	 */
	protected function get_default_description() {
		return __( "Pay securely with INTERAC Online", 'woocommerce-gateway-moneris' ) . "\n" .
			__( "&reg; Trade-mark of Interac Inc. Used under licence", 'woocommerce-gateway-moneris' ) . "\n" .
			__( "Learn more at <a target=\"_blank\" href=\"http://www.interaconline.com/learn\">www.interaconline.com/learn</a>", 'woocommerce-gateway-moneris' );
	}


	/**
	 * Returns true if the gateway is properly configured to perform transactions.
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway::is_configured()
	 * @return boolean true if the gateway is properly configured
	 */
	protected function is_configured() {

		$is_configured = parent::is_configured();

		// missing configuration
		if ( ! $this->get_store_id() || ! $this->get_api_token() || ! $this->get_merchant_number() ) {
			$is_configured = false;
		}

		return $is_configured;
	}


	/**
	 * Interac requires a notice that all amounts are in CAD
	 *
	 * @since 2.0
	 */
	public function render_checkout_currency_disclaimer() {
		if ( 'CAD' == get_woocommerce_currency() && $this->is_available() ) {
			echo '<p class="wc-moneris-interac-currency-notice">' . __( 'All amounts shown are in Canadian Dollars.', 'woocommerce-gateway-moneris' ) . '</p>';
		}
	}


	/**
	 * Display the "amounts shown are in canadian dollars" disclaimer on the
	 * pay page
	 *
	 * @since 2.0
	 * @param string $template_name the template name
	 * @param string $template_path the template path
	 * @param string $located the template location
	 * @param array $args get template arguments
	 */
	public function maybe_render_checkout_currency_disclaimer( $template_name, $template_path, $located, $args ) {
		if ( 'checkout/form-pay.php' == $template_name ) {
			$this->render_checkout_currency_disclaimer();
		}
	}


	/** Redirect API methods ******************************************************/


	/**
	 * Returns the parameters required to request the hosted Interac payment page
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_Hosted::get_hosted_pay_page_params()
	 * @param WC_Order $order the order object
	 * @return array associative array of name-value parameters
	 */
	protected function get_hosted_pay_page_params( $order ) {

		// get the country portion of the current locale, and ensure it's one of 'en' or 'fr'
		$language = substr( get_locale(), 0, 2 );
		if ( ! in_array( $language, array( 'en', 'fr' ) ) ) {
			$language = 'en';
		}

		return array(
			'IDEBIT_MERCHNUM'     => $this->get_merchant_number(),
			'IDEBIT_AMOUNT'       => $order->payment_total * 100,   // in pennies
			'IDEBIT_CURRENCY'     => SV_WC_Order_Compatibility::get_prop( $order, 'currency', 'view' ),  // USD/CAD
			'IDEBIT_INVOICE'      => apply_filters( 'wc_gateway_moneris_request_order_id', ltrim( $order->get_order_number(),  _x( '#', 'hash before order number', 'woocommerce-gateway-moneris' ) ) ),
			'IDEBIT_MERCHDATA'    => $this->get_signed_merch_data( $order ),
			'IDEBIT_FUNDEDURL'    => $this->get_transaction_response_handler_url(),
			'IDEBIT_NOTFUNDEDURL' => $this->get_transaction_response_handler_url(),
			'IDEBIT_MERCHLANG'    => $language,
			'IDEBIT_VERSION'      => 1,
			//'IDEBIT_ISSLANG'      => $language,
		);
	}


	/**
	 * Returns an API response object for the current response request
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_Hosted::get_transaction_response()
	 * @param array $request_response_data the current request response data
	 * @return SV_WC_Payment_Gateway_API_Payment_Notification_Response the response object
	 */
	protected function get_transaction_response( $request_response_data ) {

		require_once( $this->get_plugin()->get_plugin_path() . '/includes/api/class-wc-moneris-api-interac-response.php' );

		return new WC_Moneris_API_Interac_Response( $request_response_data, $this->get_api_token() );
	}


	/**
	 * Process the transaction response for the given order
	 *
	 * @since 2.1.0
	 * @see SV_WC_Payment_Gateway_Hosted::process_transaction_response()
	 * @param WC_Order $order the order
	 * @param SV_WC_Payment_Gateway_API_Payment_Notification_Response transaction response
	 * @throws Exception for network timeout, etc
	 */
	protected function process_transaction_response( $order, $response ) {

		// if we fail out at the redirect-back response, just pass the response
		// object back up the chain for standard error handling
		if ( ! $response->transaction_approved() ) {
			return parent::process_transaction_response( $order, $response );
		}

		// otherwise, we need to perform an idebit_purchase api request
		$order = $this->get_idebit_purchase_order( $order, $response );

		// since the interac request was successful, add the transaction data
		$this->add_interac_transaction_data( $order, $response );

		// complete the transaction
		$response = $this->get_api()->idebit_purchase( $order );

		return parent::process_transaction_response( $order, $response );
	}


	/**
	 * Adds idebit_purchase transaction request data to the order
	 *
	 * @since 2.0
	 * @param WC_Order $order the order object
	 * @param SV_WC_Payment_Gateway_API_Payment_Notification_Response transaction response
	 */
	protected function get_idebit_purchase_order( $order, $response ) {

		$order->payment                   = new stdClass();
		$order->payment_total             = number_format( $order->get_total(), 2, '.', '' );
		$order->payment->track2           = $response->get_track2();
		$order->payment->issconf          = $response->get_issconf();
		$order->payment->issname          = $response->get_issname();
		$order->payment->interac_trans_id = $response->get_transaction_id();

		$order = $this->get_order_with_unique_transaction_ref( $order );

		return $order;
	}


	/**
	 * Adds the standard transaction data to the order from the IPN response
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway::add_payment_gateway_transaction_data()
	 * @param WC_Order $order the order object
	 * @param SV_WC_Payment_Gateway_API_Response $response response object
	 */
	public function add_payment_gateway_transaction_data( $order, $response ) {

		parent::add_payment_gateway_transaction_data( $order, $response );

		// record the receipt ID (order number)
		$this->update_order_meta( $order, 'receipt_id', $response->get_receipt_id() );

		// set the main transaction id to the interac response transaction id,
		// and save the idebit_purchase transaction id to another meta
		$this->update_order_meta( $order, 'trans_id',        $order->payment->interac_trans_id );
		$this->update_order_meta( $order, 'idebit_trans_id', $response->get_transaction_id() );
	}


	/**
	 * Adds the interac transaction data to the order: issuer confirmation and name
	 *
	 * @since 2.0
	 * @param WC_Order $order the order object
	 * @param WC_Moneris_API_Interac_Response $response response object
	 */
	protected function add_interac_transaction_data( $order, $response ) {

		// record the issuer confirmation number and issuer name, which need to be displayed on the receipt page
		$this->update_order_meta( $order, 'idebit_issconf',  $order->payment->issconf );
		$this->update_order_meta( $order, 'idebit_issname',  $order->payment->issname );

		// record the integration country (always canada for interac)
		$this->update_order_meta( $order, 'integration', WC_Moneris::INTEGRATION_CA );
	}


	/**
	 * Adds an order note, along with anything else required after an approved
	 * transaction
	 *
	 * @since 2.1.0
	 * @param \WC_Order $order the order object
	 * @param \WC_Paytrail_API_Payment_Response $response the response object
	 * @param array $note_args Optional. The order note arguments. @see `SV_WC_Payment_Gateway_Hosted::add_transaction_approved_order_note()`
	 */
	protected function do_transaction_approved( WC_Order $order, $response, $note_args = array() ) {

		if ( $response instanceof SV_WC_Payment_Gateway_API_Payment_Notification_Response ) {
			$transaction_result = __( 'Funded', 'woocommerce-gateway-moneris' );
		} else {
			// idebit_purchase
			$transaction_result = __( 'Completed', 'woocommerce-gateway-moneris' );
		}

		// Add the order note
		$this->add_transaction_approved_order_note( $order, array(
			'transaction_id'     => $response->get_transaction_id(),
			'transaction_result' => $transaction_result,
		) );

		wp_redirect( $this->get_return_url( $order ) );
		exit;
	}


	/** Helper methods ******************************************************/


	/**
	 * Generate Text Input HTML.  This is largely unchanged from the
	 * WC_Settings_API version, aside from how the input value is escaped.  In
	 * the original, esc_attr() is used, however this doesn't properly encode
	 * the character entity we use (&reg;), so htmlentities() is used instead.
	 *
	 * @see WC_Settings_API::generate_text_html()
	 * @param mixed $key settings key
	 * @param mixed $data settings data
	 * @since 2.0
	 * @return string text input HTML
	 */
	public function generate_entity_text_html( $key, $data ) {
		$field    = $this->plugin_id . $this->id . '_' . $key;
		$defaults = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array()
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
				<?php echo $this->get_tooltip_html( $data ); ?>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="<?php echo esc_attr( $data['type'] ); ?>" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo htmlentities( $this->get_option( $key ) ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); ?> />
					<?php echo $this->get_description_html( $data ); ?>
				</fieldset>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}


	/**
	 * Guard against order processing tampering by signing the order id with
	 * the shop API token, which only the shop admin should have
	 *
	 * @since 2.0
	 * @param WC_Order $order the order
	 * @return string signed merch data for the request
	 */
	private function get_signed_merch_data( $order ) {

		$order_id = SV_WC_Order_Compatibility::get_prop( $order, 'id' );

		$merc_data = array(
			'order_id' => $order_id,
			'hash'     => sha1( $this->get_api_token() . '.' . $order_id ),
		);

		return http_build_query( $merc_data, '', '&' );
	}


	/**
	 * Log IPN/redirect-back transaction response request to the log file
	 *
	 * @since 2.1.0
	 * @see SV_WC_Payment_Gateway_Hosted::log_transaction_response_request()
	 * @param array $response the request data
	 * @param string $message optional message string with a %s to hold the
	 *        response data.  Defaults to 'IPN Request %s' or 'Redirect-back
	 *        Request %s' based on the result of `has_ipn()`
	 * $response
	 */
	public function log_transaction_response_request( $response, $message = null ) {

		// mask sensitive PAN in interac response
		if ( isset( $response['IDEBIT_TRACK2'] ) && $response['IDEBIT_TRACK2'] && false !== strpos( $response['IDEBIT_TRACK2'], '=' ) ) {

			list( $pan, $suffix ) = explode( '=', $response['IDEBIT_TRACK2'] );

			// mask the sensitive PAN
			$pan = substr( $pan, 0, 1 ) . str_repeat( '*', strlen( $pan ) - 5 ) . substr( $pan, -4 );

			$response['IDEBIT_TRACK2'] = $pan . '=' . $suffix;
		}

		parent::log_transaction_response_request( $response, $message );
	}


	/** Getter methods ******************************************************/


	/**
	 * Get the API object
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_Direct::get_api()
	 * @return WC_Moneris_API API instance
	 */
	public function get_api() {

		if ( isset( $this->api ) ) {
			return $this->api;
		}

		require_once( $this->get_plugin()->get_plugin_path() . '/includes/api/class-wc-moneris-api.php' );
		require_once( $this->get_plugin()->get_plugin_path() . '/includes/api/class-wc-moneris-api-request.php' );
		require_once( $this->get_plugin()->get_plugin_path() . '/includes/api/class-wc-moneris-api-response.php' );
		require_once( $this->get_plugin()->get_plugin_path() . '/includes/api/class-wc-moneris-api-idebit-response.php' );

		return $this->api = new WC_Moneris_API( $this->get_id(), $this->get_api_endpoint(), $this->get_store_id(), $this->get_api_token() );
	}


	/**
	 * Returns true if this gateway uses an automatic form-post from the pay
	 * page to "redirect" to the hosted payment page
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_Hosted::use_form_post()
	 * @return boolean true if this gateway uses a form post, false if it
	 *         redirects directly to the hosted pay page from checkout
	 */
	public function use_form_post() {
		// Interac requires a true post, unfortunately
		return true;
	}


	/**
	 * Returns the Moneris host for the current environment
	 *
	 * @since 2.0
	 * @param string $environment optional environment id, one of 'test' or 'production'.
	 *        Defaults to currently configured environment
	 * @return string moneris host based on the environment and integration
	 */
	public function get_moneris_host( $environment = null ) {

		// get parameter defaults
		if ( is_null( $environment ) ) {
			$environment = $this->get_environment();
		}

		if ( $this->is_production_environment( $environment ) ) {
			return WC_Moneris::PRODUCTION_URL_ENDPOINT_CA;
		} else {
			return WC_Moneris::TEST_URL_ENDPOINT_CA;
		}
	}


	/**
	 * Returns the API endpoint based on the environment
	 *
	 * @since 2.0
	 * @return string current API endpoint URL
	 */
	public function get_api_endpoint() {
		return $this->get_moneris_host() . '/gateway2/servlet/MpgRequest';
	}


	/**
	 * Gets the hosted pay page url to redirect to, to allow the customer to
	 * authorize the transaction
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_Hosted::get_hosted_pay_page_url()
	 * @param WC_Order $order the order
	 * @return string hosted pay page url
	 */
	public function get_hosted_pay_page_url( $order = null ) {
		if ( $this->is_production_environment() ) {
			return self::PRODUCTION_URL_ENDPOINT;
		} else {
			return self::TEST_URL_ENDPOINT;
		}
	}


	/**
	 * Returns the currently configured store id, based on the current
	 * environment
	 *
	 * @since 2.0
	 * @return string the current store id
	 */
	public function get_store_id() {
		if ( $this->is_production_environment() ) {
			return $this->store_id;
		} else {
			return $this->test_store_id;
		}
	}


	/**
	 * Returns the currently configured API token, based on the current
	 * environment
	 *
	 * @since 2.0
	 * @return string the current store id
	 */
	public function get_api_token() {
		if ( $this->is_production_environment() ) {
			return $this->api_token;
		} else {
			return self::TEST_API_TOKEN;
		}
	}


	/**
	 * Returns the currently configured merchant number, based on the current
	 * environment
	 *
	 * @since 2.0
	 * @return string the current merchant number
	 */
	public function get_merchant_number() {
		if ( $this->is_production_environment() ) {
			return $this->merchant_number;
		} else {
			return $this->test_merchant_number;
		}
	}


}
