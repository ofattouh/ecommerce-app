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
 * Moneris Payment Gateway Credit Card Class
 *
 * Implements the Moneris eSELECTplus direct credit card API
 *
 * @since 2.0
 */
class WC_Gateway_Moneris_Credit_Card extends SV_WC_Payment_Gateway_Direct {


	/** @var string integration country, one of INTEGRATION_CA or INTEGRATION_US */
	protected $integration_country;

	/** @var string configured dynamic descriptor */
	protected $dynamic_descriptor;

	/** @var string the configured production store id */
	protected $store_id;

	/** @var string the configured production api token */
	protected $api_token;

	/** @var string the configured test store id for the US integration */
	protected $us_test_store_id;

	/** @var string the configured test store id for the Canadian integration */
	protected $ca_test_store_id;

	/** @var string whether avs is enabled 'yes' or 'no' */
	protected $enable_avs;

	/** @var string how to handle AVS neither street address nor zip code match: 'accept', 'reject', 'hold' */
	protected $avs_neither_match;

	/** @var string how to handle AVS zip code match: 'accept', 'reject', 'hold' */
	protected $avs_zip_match;

	/** @var string how to handle AVS street address match: 'accept', 'reject', 'hold' */
	protected $avs_street_match;

	/** @var string how to handle AVS neither street address nor zip code verified: 'accept', 'reject', 'hold' */
	protected $avs_not_verified;

	/** @var string whether CSC is required for *all* (including tokenized) transactions, 'yes' or 'no' */
	protected $require_csc;

	/** @var string how to handle CVD does not match: 'accept', 'reject', 'hold' */
	protected $csc_not_match;

	/** @var string how to handle CVD not verified: 'accept', 'reject', 'hold' */
	protected $csc_not_verified;

	/** @var string whether hosted tokenization is enabled, 'yes' or 'no' */
	protected $hosted_tokenization;

	/** @var string the production environment hosted tokenization profile id */
	protected $hosted_tokenization_profile_id;

	/** @var string the test environment hosted tokenization profile id */
	protected $test_hosted_tokenization_profile_id;

	/** @var WC_Moneris_API instance */
	protected $api;

	/** @var string hopefully temporary work-around for the inflexible SV_WC_Payment_Gateway::do_transaction() handling of order notes for held authorize-only transaction */
	protected $held_authorization_status_message;


	/**
	 * Initialize the gateway
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_Direct::__construct()
	 */
	public function __construct() {

		parent::__construct(
			WC_Moneris::CREDIT_CARD_GATEWAY_ID,
			wc_moneris(),
			array(
				'method_title'       => __( 'Moneris', 'woocommerce-gateway-moneris' ),
				'method_description' => __( 'Allow customers to securely check out using Moneris', 'woocommerce-gateway-moneris' ),
				'supports'           => array(
					self::FEATURE_PRODUCTS,
					self::FEATURE_CARD_TYPES,
					self::FEATURE_TOKENIZATION,
					self::FEATURE_TOKEN_EDITOR,
					self::FEATURE_CREDIT_CARD_CHARGE,
					self::FEATURE_CREDIT_CARD_AUTHORIZATION,
					self::FEATURE_CREDIT_CARD_CAPTURE,
					self::FEATURE_CREDIT_CARD_CHARGE_VIRTUAL,
				 ),
				'payment_type'       => self::PAYMENT_TYPE_CREDIT_CARD,
				'environments'       => array(
					self::ENVIRONMENT_PRODUCTION => __( 'Production', 'woocommerce-gateway-moneris' ),
					self::ENVIRONMENT_TEST       => __( 'Sandbox', 'woocommerce-gateway-moneris' )
				),
				'currencies'         => array(), // no currency requirements
			)
		);

		if ( $this->hosted_tokenization_available() ) {
			add_filter( 'wc_payment_gateway_' . $this->get_plugin()->get_id() . '_javascript_url', array( $this, 'hosted_tokenization_javascript_url' ), 10 );
		}
	}


	/**
	 * Returns an array of form fields specific for this method
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway::get_method_form_fields()
	 * @return array of form fields
	 */
	protected function get_method_form_fields() {

		$form_fields = array(

			'integration_country' => array(
				'title'    => __( 'Integration Country', 'woocommerce-gateway-moneris' ),
				'type'     => 'select',
				'desc_tip' => __( 'Is your Moneris account based in the US or Canada?', 'woocommerce-gateway-moneris' ),
				'default'  => 'CA' == WC()->countries->get_base_country() ? WC_Moneris::INTEGRATION_CA : WC_Moneris::INTEGRATION_US,
				'options'  => array(
					WC_Moneris::INTEGRATION_CA => __( 'Canada', 'woocommerce-gateway-moneris' ),
					WC_Moneris::INTEGRATION_US => __( 'United States', 'woocommerce-gateway-moneris' ),
				),
			),

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

			'us_test_store_id' => array(
				'title'       => __( 'Store ID', 'woocommerce-gateway-moneris' ),
				'type'        => 'select',
				'options'     => array(
					'monusqa002' => 'monusqa002 (Pinless Debit)',
					'monusqa003' => 'monusqa003',
					'monusqa004' => 'monusqa004',
					'monusqa005' => 'monusqa005',
					'monusqa006' => 'monusqa006',
					//'monusqa024' => 'monusqa024 (ACH only)',
					//'monusqa025' => 'monusqa025 (ACH and Credit Card)',
				),
				'default'     => 'monusqa003',
				'class'       => 'environment-field test-field integration-field us-field',
				'description' => sprintf( __( "Moneris uses a set of shared test accounts, to view your transactions log in to the %smerchant center%s with the selected store id and password 'abc1234'", 'woocommerce-gateway-moneris' ),
											'<a href="https://esplusqa.moneris.com/usmpg/index.php">', '</a>' ),
			),

			'ca_test_store_id' => array(
				'title'       => __( 'Store ID', 'woocommerce-gateway-moneris' ),
				'type'        => 'select',
				'options'     => array(
					'store1'  => 'store1',
					'store2'  => 'store2',
					'store3'  => 'store3',
					'store5'  => 'store5 (test AVS &amp; CVD)',
					// 'moneris' => 'moneris (test VBV)',
				),
				'default'     => 'store1',
				'class'       => 'environment-field test-field integration-field ca-field',
				'description' => sprintf( __( "Moneris uses a set of shared test accounts, to view your transactions log in to the %smerchant center%s with the selected store id and password 'password'", 'woocommerce-gateway-moneris' ),
											'<a href="https://esqa.moneris.com/mpg/">', '</a>' ),
			),

			'dynamic_descriptor' => array(
				'title'       => __( 'Dynamic Decriptor', 'woocommerce-gateway-moneris' ),
				'type'        => 'text',
				'desc_tip'    => __( 'What your buyers will see on their credit card statement ', 'woocommerce-gateway-moneris' ),
				'description' => __( 'Twenty characters maximum allowed if the integration country is United States' ),
			),

			'enable_avs' => array(
				'title'       => __( 'Address Verification Service (AVS)', 'woocommerce-gateway-moneris' ),
				'label'       => __( 'Perform an AVS check on customers billing addresses', 'woocommerce-gateway-moneris' ),
				'desc_tip'    => __( 'This must first be enabled in your Moneris merchant profile and works only with Visa / MasterCard / Discover / JCB / American Express card types.  All other card types will not be declined due to AVS.', 'woocommerce-gateway-moneris' ),
				'type'        => 'checkbox',
				'default'     => 'no',
			),

			'avs_neither_match' => array(
				'description' => __( 'If neither street address nor zip code match', 'woocommerce-gateway-moneris' ),
				'class'       => 'avs-field',
				'type'        => 'select',
				'options'     => array(
					'accept' => __( 'Accept Transaction', 'woocommerce-gateway-moneris' ),
					'reject' => __( 'Reject Transaction', 'woocommerce-gateway-moneris' ),
					'hold'   => __( 'Hold Transaction',   'woocommerce-gateway-moneris' ),
				),
				'default'     => 'accept',
				'desc_tip'    => __( "Use 'Accept' to automatically accept the transaction, 'Reject' to automatically decline the transaction, and 'Hold' to perform an authorization and hold the order for review.", 'woocommerce-gateway-moneris' ),
			),

			'avs_zip_match' => array(
				'description' => __( 'If zip code matches but street address does not match or could not be verified', 'woocommerce-gateway-moneris' ),
				'class'       => 'avs-field',
				'type'        => 'select',
				'options'     => array(
					'accept' => __( 'Accept Transaction', 'woocommerce-gateway-moneris' ),
					'reject' => __( 'Reject Transaction', 'woocommerce-gateway-moneris' ),
					'hold'   => __( 'Hold Transaction',   'woocommerce-gateway-moneris' ),
				),
				'default'     => 'accept',
				'desc_tip'    => __( "Use 'Accept' to automatically accept the transaction, 'Reject' to automatically decline the transaction, and 'Hold' to perform an authorization and hold the order for review.", 'woocommerce-gateway-moneris' ),
			),

			'avs_street_match' => array(
				'description' => __( 'If street address matches but zip code does not match or could not be verified', 'woocommerce-gateway-moneris' ),
				'class'       => 'avs-field',
				'type'        => 'select',
				'options'     => array(
					'accept' => __( 'Accept Transaction', 'woocommerce-gateway-moneris' ),
					'reject' => __( 'Reject Transaction', 'woocommerce-gateway-moneris' ),
					'hold'   => __( 'Hold Transaction',   'woocommerce-gateway-moneris' ),
				),
				'default'     => 'accept',
				'desc_tip'    => __( "Use 'Accept' to automatically accept the transaction, 'Reject' to automatically decline the transaction, and 'Hold' to perform an authorization and hold the order for review.", 'woocommerce-gateway-moneris' ),
			),

			'avs_not_verified' => array(
				'description' => __( 'If street address and zip code could not be verified', 'woocommerce-gateway-moneris' ),
				'class'       => 'avs-field',
				'type'        => 'select',
				'options'     => array(
					'accept' => __( 'Accept Transaction', 'woocommerce-gateway-moneris' ),
					'reject' => __( 'Reject Transaction', 'woocommerce-gateway-moneris' ),
					'hold'   => __( 'Hold Transaction',   'woocommerce-gateway-moneris' ),
				),
				'default'     => 'accept',
				'desc_tip'    => __( "Use 'Accept' to automatically accept the transaction, 'Reject' to automatically decline the transaction, and 'Hold' to perform an authorization and hold the order for review.", 'woocommerce-gateway-moneris' ),
			),

		);

		// collect the CSC fields
		$csc_form_fields = array();

		foreach ( $this->form_fields as $name => $field ) {
			if ( 'enable_csc' == $name || ( isset( $field['class'] ) && false !== strpos( $field['class'], 'csc-field' ) ) ) {
				$csc_form_fields[ $name ] = $field;
				unset( $this->form_fields[ $name ] );
			}
		}

		// and append them following the AVS fields
		$fields = $form_fields + $csc_form_fields;

		return $fields;
	}


	/**
	 * Adds the CSC result handling form fields
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway::add_csc_form_fields()
	 * @param array $form_fields gateway form fields
	 * @return array $form_fields gateway form fields
	 */
	protected function add_csc_form_fields( $form_fields ) {

		$form_fields = parent::add_csc_form_fields( $form_fields );

		$form_fields['enable_csc']['label'] = __( 'Collect a Card Security Code (CVD) on checkout and validate', 'woocommerce-gateway-moneris' );
		$form_fields['enable_csc']['desc_tip'] = __( 'This must first be enabled in your Moneris merchant profile and works only with Visa / MasterCard / Discover / JCB / American Express card types.  All other card types will not be declined due to CSC.', 'woocommerce-gateway-moneris' );

		$form_fields['csc_not_match'] = array(
			'description' => __( 'If CSC does not match', 'woocommerce-gateway-moneris' ),
			'class'       => 'csc-field',
			'type'        => 'select',
			'options'     => array(
				'accept' => __( 'Accept Transaction', 'woocommerce-gateway-moneris' ),
				'reject' => __( 'Reject Transaction', 'woocommerce-gateway-moneris' ),
				'hold'   => __( 'Hold Transaction',   'woocommerce-gateway-moneris' ),
			),
			'default'     => 'accept',
			'desc_tip'    => __( "Use 'Accept' to automatically accept the transaction, 'Reject' to automatically decline the transaction, and 'Hold' to perform an authorization and hold the order for review.", 'woocommerce-gateway-moneris' ),
		);

		$form_fields['csc_not_verified'] = array(
			'description' => __( 'If CSC could not be verified', 'woocommerce-gateway-moneris' ),
			'class'       => 'csc-field',
			'type'        => 'select',
			'options'     => array(
				'accept' => __( 'Accept Transaction', 'woocommerce-gateway-moneris' ),
				'reject' => __( 'Reject Transaction', 'woocommerce-gateway-moneris' ),
				'hold'   => __( 'Hold Transaction',   'woocommerce-gateway-moneris' ),
			),
			'default'     => 'accept',
			'desc_tip'    => __( "Use 'Accept' to automatically accept the transaction, 'Reject' to automatically decline the transaction, and 'Hold' to perform an authorization and hold the order for review.", 'woocommerce-gateway-moneris' ),
		);

		$form_fields['require_csc'] = array(
			'title'    => __( 'Require Card Verification', 'woocommerce-gateway-moneris' ),
			'class'    => 'csc-field',
			'label'    => __( 'Require the Card Security Code for all transactions', 'woocommerce-gateway-moneris' ),
			'desc_tip' => __( 'Enabling this field will require the CSC even for tokenized transactions, and will disable support for WooCommerce Subscriptions and WooCommerce Pre-Orders.', 'woocommerce-gateway-moneris' ),
			'type'     => 'checkbox',
			'default'  => 'no',
		);

		return $form_fields;
	}


	/**
	 * Adds the Hosted Tokenization options
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway::add_tokenization_form_fields()
	 * @param array $form_fields gateway form fields
	 * @return array $form_fields gateway form fields
	 * @throws SV_WC_Payment_Gateway_Feature_Unsupported_Exception if payment method tokenization is not supported
	 */
	protected function add_tokenization_form_fields( $form_fields ) {

		$form_fields = parent::add_tokenization_form_fields( $form_fields );

		$form_fields['tokenization']['label'] = _x( 'Allow customers to securely save their payment details for future checkout.  You must contact your Moneris account rep to enable the "Vault" option on your account before enabling this setting.', 'Supports tokenization', 'woocommerce-gateway-moneris' );

		$form_fields['hosted_tokenization'] = array(
			'title'   => __( 'Hosted Tokenization', 'woocommerce-gateway-moneris' ),
			'label'   => __( 'Use a hosted form field to collect credit card information on checkout and reduce PCI-compliance assessment scope.', 'woocommerce-gateway-moneris' ),
			'type'    => 'checkbox',
			'default' => 'no',
		);

		$form_fields['hosted_tokenization_profile_id'] = array(
			'title'       => __( 'Hosted Tokenization Profile ID', 'woocommerce-gateway-moneris' ),
			'type'        => 'text',
			'class'       => 'environment-field production-field hosted-tokenization-field',
			'description' => sprintf( __( 'Generate this by logging into your %sMerchant Resource Center%s &gt; Admin &gt; Hosted Tokenization - use %s%s%s as the source domain.', 'woocommerce-gateway-moneris' ),
								'<a href="#" target="_blank">', '</a>',
								'<span class="nowrap">',
								get_home_url( null, '', get_option( 'woocommerce_force_ssl_checkout' ) == 'yes' ? 'https' : null ),
								'</span>'
			)
		);

		$form_fields['test_hosted_tokenization_profile_id'] = array(
			'title'       => __( 'Hosted Tokenization Profile ID', 'woocommerce-gateway-moneris' ),
			'type'        => 'text',
			'class'       => 'environment-field test-field hosted-tokenization-field',
			'description' => sprintf( __( 'Generate this by logging into your %sMerchant Resource Center%s &gt; Admin &gt; Hosted Tokenization - use %s%s%s as the source domain.', 'woocommerce-gateway-moneris' ),
								'<a href="#" target="_blank">', '</a>',
								'<span class="nowrap">',
								get_home_url( null, '', get_option( 'woocommerce_force_ssl_checkout' ) == 'yes' ? 'https' : null ),
								'</span>'
			)
		);

		return $form_fields;
	}


	/**
	 * Returns an array of javascript script params to localize for the
	 * checkout/pay page javascript.  Mostly used for i18n purposes
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway::get_gateway_js_localized_script_params()
	 * @return array associative array of param name to value
	 */
	protected function get_gateway_js_localized_script_params() {

		$params = array( 'require_csc' => $this->csc_required() );

		if ( $this->hosted_tokenization_available() ) {
			$params['hosted_tokenization_url']        = $this->get_hosted_tokenization_url();
			$params['general_error']                  = __( 'An error occurred with your payment, please try again or try another payment method', 'woocommerce-gateway-moneris' );
			$params['card_number_missing_or_invalid'] = __( 'Card number is missing or invalid', 'woocommerce-gateway-moneris' );
			$params['ajaxurl']                        = admin_url( 'admin-ajax.php', 'relative' );

			// get the current order and add the cancel/return URLs
			$order_id = isset( $GLOBALS['wp']->query_vars['order-pay'] ) ? absint( $GLOBALS['wp']->query_vars['order-pay'] ) : 0;

			if ( $order_id ) {
				$order = wc_get_order( $order_id );

				$params['order_id']   = SV_WC_Order_Compatibility::get_prop( $order, 'id' );
			}
		}

		// add the "require_csc" param, which is needed to properly handle the checkout page tokenization logic
		return array_merge( parent::get_payment_form_js_localized_script_params(), $params );
	}


	/**
	 * Display settings page with some additional javascript for hiding
	 * conditional fields.  The "Require CSC" field will be shown only when
	 * the "Enable CSC" and "Tokenization" are enabled
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway::admin_options()
	 */
	public function admin_options() {

		parent::admin_options();

		// add inline javascript to show the correct test store id field based on the current integration country
		ob_start();
		?>
			$( '#woocommerce_<?php echo $this->get_id(); ?>_integration_country, #woocommerce_<?php echo $this->get_id(); ?>_environment, #woocommerce_<?php echo $this->get_id(); ?>_hosted_tokenization' ).change( function() {

				var environment = $( '#woocommerce_<?php echo $this->get_id(); ?>_environment' ).val();
				var integration = $( '#woocommerce_<?php echo $this->get_id(); ?>_integration_country' ).val();
				var hostedTokenization = $( '#woocommerce_<?php echo $this->get_id(); ?>_hosted_tokenization' ).is( ':checked' );

				// I thought to store this as data attributes on the anchor tag, but THINK AGAIN, WooCommerce strips out any data attributes
				var usTestMerchantCenterUrl       = '<?php echo $this->get_merchant_center_url( self::ENVIRONMENT_TEST,       WC_Moneris::INTEGRATION_US ); ?>';
				var usProductionMerchantCenterUrl = '<?php echo $this->get_merchant_center_url( self::ENVIRONMENT_PRODUCTION, WC_Moneris::INTEGRATION_US ); ?>';
				var caTestMerchantCenterUrl       = '<?php echo $this->get_merchant_center_url( self::ENVIRONMENT_TEST,       WC_Moneris::INTEGRATION_CA ); ?>';
				var caProductionMerchantCenterUrl = '<?php echo $this->get_merchant_center_url( self::ENVIRONMENT_PRODUCTION, WC_Moneris::INTEGRATION_CA ); ?>';

				// hide all integration-dependant fields
				$( '.integration-field' ).closest( 'tr' ).hide();

				// show the currently configured integration fields
				var $integrationFields = $( '.' + integration + '-field.' + environment + '-field' );

				$integrationFields.not( '.hidden' ).closest( 'tr' ).show();

				// hide all hosted tokenization-dependant fields
				$( '.hosted-tokenization-field' ).closest( 'tr' ).hide();

				// show the hosted tokenization-dependant fields for the current environment, if hosted tokenization is enabled
				if ( hostedTokenization ) {
					var $hostedTokenizationFields = $( '.hosted-tokenization-field.' + environment + '-field' );
					$hostedTokenizationFields.not( '.hidden' ).closest( 'tr' ).show();

					var merchantCenterUrl = null;
					if ( '<?php echo self::ENVIRONMENT_PRODUCTION; ?>' == environment ) {
						if ( '<?php echo WC_Moneris::INTEGRATION_US; ?>' == integration ) {
							merchantCenterUrl = usProductionMerchantCenterUrl;
						} else {
							merchantCenterUrl = caProductionMerchantCenterUrl;
						}
					} else {
						if ( '<?php echo WC_Moneris::INTEGRATION_US; ?>' == integration ) {
							merchantCenterUrl = usTestMerchantCenterUrl;
						} else {
							merchantCenterUrl = caTestMerchantCenterUrl;
						}
					}
					$hostedTokenizationFields.closest('tr').find('a').attr( 'href', merchantCenterUrl );
				}

				// the dynamic descriptor description is only relevant to the US integration
				var dynamicDescriptorDescription = $( '#woocommerce_<?php echo $this->get_id(); ?>_dynamic_descriptor' ).closest( 'td' ).find( '.description' );
				if ( '<?php echo WC_Moneris::INTEGRATION_US; ?>' == integration ) {
					dynamicDescriptorDescription.show();
				} else {
					dynamicDescriptorDescription.hide();
				}

			} ).change();

			$( '#woocommerce_<?php echo $this->get_id(); ?>_enable_avs' ).change( function() {
				var enableAvs = $( this ).is( ':checked' );

				if ( enableAvs ) {
					$( '.avs-field' ).closest( 'tr' ).show();
				} else {
					$( '.avs-field' ).closest( 'tr' ).hide();
				}
			} ).change();

			$( '#woocommerce_<?php echo $this->get_id(); ?>_enable_csc' ).change( function() {
				var enableCsc = $( this ).is( ':checked' );

				if ( enableCsc ) {
					$( '.csc-field' ).closest( 'tr' ).show();
				} else {
					$( '.csc-field' ).closest( 'tr' ).hide();
				}
			} ).change();

			// add inline javascript to show the "require csc" field when the "enable csc" and "tokenization" fields are both checked
			$( '#woocommerce_<?php echo $this->get_id(); ?>_enable_csc, #woocommerce_<?php echo $this->get_id(); ?>_tokenization' ).change( function() {

				if ( $( '#woocommerce_<?php echo $this->get_id(); ?>_enable_csc' ).is( ':checked' ) && $( '#woocommerce_<?php echo $this->get_id(); ?>_tokenization' ).is( ':checked' ) ) {
					$( '#woocommerce_<?php echo $this->get_id(); ?>_require_csc' ).closest( 'tr' ).show();
				} else {
					$( '#woocommerce_<?php echo $this->get_id(); ?>_require_csc' ).closest( 'tr' ).hide();
				}

			} ).change();
		<?php

		wc_enqueue_js( ob_get_clean() );

	}


	/**
	 * Returns the merchant account transaction URL for the given order
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway::get_transaction_url()
	 * @param WC_Order $order the order object
	 * @return string transaction url or null if not supported
	 */
	public function get_transaction_url( $order ) {

		$receipt_id  = $this->get_order_meta( $order, 'receipt_id' );
		$trans_id    = $this->get_order_meta( $order, 'trans_id' );
		$environment = $this->get_order_meta( $order, 'environment' );
		$integration = $this->get_order_meta( $order, 'integration' );

		if ( ! $receipt_id || ! $trans_id || ! $environment || ! $integration ) {
			return null;
		}

		$host = $this->get_moneris_host( $environment, $integration );

		if ( $this->is_us_integration() ) {
			$host .= '/usmpg/reports/order_history/index.php';
		} else {
			$host .= '/mpg/reports/order_history/index.php';
		}

		$this->view_transaction_url = add_query_arg( array( 'order_no' => $receipt_id, 'orig_txn_no' => $trans_id ), $host );

		return parent::get_transaction_url( $order );
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
		if ( ! $this->get_store_id() || ! $this->get_api_token() ) {
			$is_configured = false;
		}

		return $is_configured;
	}


	/**
	 * Add any Moneris specific payment and transaction information as
	 * class members of WC_Order instance.  Added members can include:
	 *
	 * $order->dynamic_descriptor - Merchant defined description sent on a per-transaction basis that will appear on the credit card statement appended to the merchant�s business name.
	 * $order->perform_avs - true if the avs data should be included with the transaction request
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_Direct::get_order()
	 * @param int|WC_Order $order the order or order ID being processed
	 * @return WC_Order object with payment and transaction information attached
	 */
	public function get_order( $order ) {

		// add common order members
		$order = parent::get_order( $order );

		// add the configured dynamic descriptor
		$order->dynamic_descriptor = $this->get_dynamic_descriptor( true );

		// whether to include the avs fields
		$order->perform_avs = $this->avs_enabled();

		if ( empty( $order->payment->card_type ) ) {

			// determine the card type from the account number
			if ( ! empty( $order->payment->account_number ) ) {
				$account_number = $order->payment->account_number;
			} else {
				$account_number = SV_WC_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-card-bin' );
			}

			$order->payment->card_type = SV_WC_Payment_Gateway_Helper::card_type_from_account_number( $account_number );
		}

		if ( $this->is_test_environment() ) {

			// avoid "duplicate order ID" errors during testing
			$order->unique_transaction_ref = $order->unique_transaction_ref . time();
		}

		return $order;
	}


	/**
	 * Validate the payment fields when processing the checkout
	 *
	 * @since 2.0
	 * @see WC_Payment_Gateway::validate_fields()
	 * @return bool true if fields are valid, false otherwise
	 */
	public function validate_fields() {

		$is_valid = true;

		$expiration_month = SV_WC_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-exp-month' );
		$expiration_year  = SV_WC_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-exp-year' );
		$csc              = SV_WC_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-csc' );

		if ( $this->hosted_tokenization_available() && SV_WC_Helper::get_post( 'wc-moneris-temp-payment-token' ) ) {
			// dealing with a hosted tokenization temporary token, which means it does not exist in our local datastore
			// and there's no account number to validate

			$is_valid = $this->validate_credit_card_expiration_date( $expiration_month, $expiration_year ) && $is_valid;

			// validate card security code
			if ( $this->csc_enabled() ) {
				$is_valid = $this->validate_csc( $csc ) && $is_valid;
			}

			return $is_valid;
		}

		// normal operation
		$is_valid = parent::validate_fields();

		// tokenized transaction with CSC required, validate the csc
		if ( SV_WC_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-payment-token' ) && $this->csc_required() ) {

			$csc = SV_WC_Helper::get_post( 'wc-' . $this->get_id_dasherized() . '-csc' );
			$is_valid = $this->validate_csc( $csc ) && $is_valid;

		}

		return $is_valid;
	}


	/**
	 * Performs a credit card transaction for the given order and returns the
	 * result
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_Direct::do_credit_card_transaction()
	 * @param WC_Order $order the order object
	 * @return SV_WC_Payment_Gateway_API_Response the response
	 * @throws Exception network timeouts, etc
	 */
	protected function do_credit_card_transaction( $order, $response = null ) {

		// normal operation if no avs checks in force or card does not support it
		if ( ! $this->has_efraud_validations() || ( $order->payment->card_type && ! $this->card_supports_efraud_validations( $order->payment->card_type ) ) ) {
			return parent::do_credit_card_transaction( $order );
		}

		// we have at least one efraud condition, so we'll start by doing an authorization for the full amount
		$response = $this->get_api()->credit_card_authorization( $order );

		// authorization failure, we're done
		if ( ! $response->transaction_approved() ) {
			return $response;
		}

		// this is for Hosted Tokenization transactions with efraud settings, and tokenization vault disabled
		// we don't know the card type until we perform an authorization request, so we do so, get the card
		// type and proceed from there
		if ( ! $order->payment->card_type && ( ! $response->get_card_type() || ! $this->card_supports_efraud_validations( $response->get_card_type() ) ) ) {

			if ( $this->perform_credit_card_charge( $order ) ) {

				$order = $this->get_order_for_capture( $order );

				// complete the charge if needed
				$order->capture->trans_id   = $response->get_transaction_id();
				$order->capture->receipt_id = $response->get_receipt_id();

				$response = $this->get_api()->credit_card_capture( $order );
			}

			return parent::do_credit_card_transaction( $order, $response );
		}

		// set the card type/account number for hosted tokenized (non-tokenization) transactions
		if ( ! $order->payment->card_type ) {
			$order->payment->card_type      = $response->get_card_type();
			$order->payment->account_number = $response->get_masked_pan();
		}

		// get the combined efraud action
		$efraud_action = $this->get_efraud_action( $response->get_avs_result(), $response->get_csc_result(), $order );

		if ( 'accept' == $efraud_action ) {

			if ( $this->perform_credit_card_charge( $order ) ) {

				$order = $this->get_order_for_capture( $order );

				// complete the charge if needed
				$order->capture->trans_id   = $response->get_transaction_id();
				$order->capture->receipt_id = $response->get_receipt_id();

				$response = $this->get_api()->credit_card_capture( $order );
			} // otherwise just return the authorization response

		} elseif ( 'reject' == $efraud_action ) {

			$order = $this->get_order_for_capture( $order );

			// reverse the charge
			$order->capture->trans_id   = $response->get_transaction_id();
			$order->capture->receipt_id = $response->get_receipt_id();

			$this->get_api()->credit_card_authorization_reverse( $order );

			// mark the original response as failed since we've reversed the authorization
			$response->failed();

			$messages = array();
			if ( $this->has_avs_validations() && 'reject' == $this->get_avs_action( $response->get_avs_result() ) ) {
				$messages[] = sprintf( __( 'AVS %s (result: %s)', 'woocommerce-gateway-moneris' ), $this->get_avs_error_message( $response->get_avs_result() ), $response->get_avs_result_code() );
			}

			if ( $this->has_csc_validations() && 'reject' == $this->get_csc_action( $response->get_csc_result() ) ) {
				$messages[] = sprintf( __( 'CSC %s (result: %s)', 'woocommerce-gateway-moneris' ), $this->get_csc_error_message( $response->get_csc_result() ), $response->get_csc_result_code() );
			}

			$response->set_status_message( implode( ', ', $messages ) );

			// we really don't care whether the reversal succeeded, though it should have
			return $response;

		} else { // hold

			// mark the response as held
			$response->held();

			$messages = array();
			if ( $this->has_avs_validations() && 'hold' == $this->get_avs_action( $response->get_avs_result() ) ) {
				$messages[] = sprintf( __( 'AVS %s (result: %s)', 'woocommerce-gateway-moneris' ), $this->get_avs_error_message( $response->get_avs_result() ), $response->get_avs_result_code() );
			}

			if ( $this->has_csc_validations() && 'hold' == $this->get_csc_action( $response->get_csc_result() ) ) {
				$messages[] = sprintf( __( 'CSC %s (result: %s)', 'woocommerce-gateway-moneris' ), $this->get_csc_error_message( $response->get_csc_result() ), $response->get_csc_result_code() );
			}

			$message = __( "Authorization", 'woocommerce-gateway-moneris' ) . ' ' . implode( ', ', $messages );

			if ( $this->perform_credit_card_authorization() ) {
				// workaround
				$this->held_authorization_status_message = $message;
			} else {
				// this message will be added to the 'hold' order notes
				$response->set_status_message( $message );
			}

			return $response;
		}

		// success! update order record
		return parent::do_credit_card_transaction( $order, $response );
	}


	/**
	 * Mark the given order as 'on-hold', set an order note and display a message
	 * to the customer
	 *
	 * TODO: this should hopefully be a temporary override, until we figure out a
	 * better way to handle the messaging for held authorize-only orders in the
	 * SV_WC_Payment_Gateway::do_transaction() method
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateawy::mark_order_as_held()
	 * @param WC_Order $order the order
	 * @param string $message a message to display within the order note
	 * @param SV_WC_Payment_Gateway_API_Response optional $response the transaction response object
	 */
	public function mark_order_as_held( $order, $message, $response = null ) {

		// reset the capture eligibility as this may be a new authorization
		// for an order that previously had its authorization reversed
		$this->update_order_meta( $order, 'auth_can_be_captured', 'yes' );

		if ( ! is_null( $this->held_authorization_status_message ) ) {
			$message = $this->held_authorization_status_message;
		}

		parent::mark_order_as_held( $order, $message, $response );
	}


	/**
	 * Handle authorization capture errors
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_Direct::do_credit_card_capture()
	 * @param $order WC_Order the order
	 * @return SV_WC_Payment_Gateway_API_Response the response of the capture attempt
	 */
	public function do_credit_card_capture( $order ) {

		$response = parent::do_credit_card_capture( $order );

		if ( $response && ! $response->transaction_approved() && $response->is_authorization_invalid() ) {

			// mark the capture as invalid if it's already been fully captured
			$this->update_order_meta( $order, 'auth_can_be_captured', 'no' );
		}

		return $response;
	}


	/**
	 * Perform a credit card authorization reversal for the given order
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_Direct::do_credit_card_capture()
	 * @param $order WC_Order the order
	 * @return null|SV_WC_Payment_Gateway_API_Response the response of the reversal attempt
	 */
	public function do_credit_card_reverse_authorization( $order ) {

		try {

			$response = $this->get_api()->credit_card_authorization_reverse( $this->get_order_for_capture( $order ) );

			if ( $response->transaction_approved() ) {

				$message = sprintf(
					__( '%s%s Authorization Reversed', 'woocommerce-gateway-moneris' ),
					$this->get_method_title(),
					$this->is_test_environment() ? ' ' . __( 'Test', 'woocommerce-gateway-moneris' ) : ''
				);

				// adds the transaction id (if any) to the order note
				if ( $response->get_transaction_id() ) {
					$message .= ' ' . sprintf( __( '(Transaction ID %s)', 'woocommerce-gateway-moneris' ), $response->get_transaction_id() );
				}

				// cancel the order.  since this results in an update to the post object we need to unhook the save_post action, otherwise we can get boomeranged and change the status back to on-hold
				$this->unhook_woocommerce_process_shop_order_meta();

				$this->mark_order_as_cancelled( $order, $message, $response );

				// once an authorization has been reversed, it cannot be captured again
				$this->update_order_meta( $order, 'auth_can_be_captured', 'no' );

			} else {

				$message = sprintf(
					__( '%s%s Authorization Reversal Failed: %s - %s', 'woocommerce-gateway-moneris' ),
					$this->get_method_title(),
					$this->is_test_environment() ? ' ' . __( 'Test', 'woocommerce-gateway-moneris' ) : '',
					$response->get_status_code(),
					$response->get_status_message()
				);

				if ( $response->is_authorization_invalid() ) {
					// already reversed or captured, cancel the order.  since this results in an update to the post object we need to unhook the save_post action, otherwise we can get boomeranged and change the status back to on-hold
					$this->unhook_woocommerce_process_shop_order_meta();

					$this->mark_order_as_cancelled( $order, $message, $response );

					// mark the capture as invalid
					$this->update_order_meta( $order, 'auth_can_be_captured', 'no' );

				} else {

					$order->add_order_note( $message );
				}

			}

			return $response;

		} catch ( Exception $e ) {

			$message = sprintf(
				__( '%s%s Authorization Reversal Failed: %s', 'woocommerce-gateway-moneris' ),
				$this->get_method_title(),
				$this->is_test_environment() ? ' ' . __( 'Test', 'woocommerce-gateway-moneris' ) : '',
				$e->getMessage()
			);

			$order->add_order_note( $message );

			return null;
		}
	}


	/**
	 * Gets the order object with additional properties needed for capture.
	 *
	 * @since 2.7.0
	 * @param \WC_Order|int $order the order object or ID
	 * @return \WC_Order
	 */
	protected function get_order_for_capture( $order ) {

		$order = parent::get_order_for_capture( $order );

		$order->capture->receipt_id = $this->get_order_meta( $order, 'receipt_id' );

		return $order;
	}


	/**
	 * Called after an unsuccessful transaction attempt
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_Direct::do_transaction_failed_result()
	 * @param WC_Order $order the order
	 * @param SV_WC_Payment_Gateway_API_Response $response the transaction response
	 * @return boolean false
	 */
	protected function do_transaction_failed_result( WC_Order $order, SV_WC_Payment_Gateway_API_Response $response ) {

		// missing token, meaning local token is invalid, so delete it from the local datastore
		if ( ( 'res_preauth_cc' == $response->get_request()->get_type() || 'res_purchase_cc' == $response->get_request()->get_type() )
			&& 983 == $response->get_status_code() ) {
			$this->get_payment_tokens_handler()->remove_token( $order->get_user_id(), $order->payment->token );
		}

		return parent::do_transaction_failed_result( $order, $response );
	}


	/**
	 * Unhooks the core WooCommerce process shop order meta, so we can update
	 * the order status without causing the core WooCommerce code to fire and
	 * undo our change
	 *
	 * @since 2.0
	 */
	private function unhook_woocommerce_process_shop_order_meta() {

		// complete the order.  since this results in an update to the post object we need to unhook the save_post action, otherwise we can get boomeranged and change the status back to on-hold
		remove_action( 'woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40 );
	}


	/**
	 * Display the payment fields on the checkout page
	 *
	 * @since 2.0
	 * @see WC_Payment_Gateway::payment_fields()
	 */
	public function payment_fields() {

		if ( $this->hosted_tokenization_available() ) {
			woocommerce_moneris_hosted_tokenization_payment_fields( $this );
		} else {
			// deafult behavior: payment from checkout page
			woocommerce_moneris_payment_fields( $this );
		}
	}


	/**
	 * Adds any gateway-specific transaction data to the order
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_Direct::add_payment_gateway_transaction_data()
	 * @param WC_Order $order the order object
	 * @param WC_Moneris_API_Response $response the transaction response
	 */
	public function add_payment_gateway_transaction_data( $order, $response ) {

		// record the integration country (us/ca)
		$this->update_order_meta( $order, 'integration', $this->get_integration_country() );

		// record the receipt ID (order number)
		$this->update_order_meta( $order, 'receipt_id', $response->get_receipt_id() );

		// record the transaction reference number
		if ( $response->get_reference_num() ) {
			$this->update_order_meta( $order, 'reference_num', $response->get_reference_num() );
		}

		// record the avs result code
		if ( $response->get_avs_result_code() ) {
			$this->update_order_meta( $order, 'avs', $response->get_avs_result_code() );
		}

		// record the csc validation code
		if ( $response->get_csc_result_code() ) {
			$this->update_order_meta( $order, 'csc', $response->get_csc_result_code() );
		}

		// if we're configured to perform a credit card charge, but a preauth
		// was performed, this likely indicates an AVS failure.  Mark the
		// charge as not captured so it can be managed through the admin
		if ( $this->perform_credit_card_charge( $order ) && 'preauth' == $response->get_request()->get_type() ) {
			$this->update_order_meta( $order, 'charge_captured', 'no' );
		}
	}


	/**
	 * Returns true if tokenization takes place after an authorization/charge
	 * transaction.
	 *
	 * Moneris has both a post-transaction tokenization request, as well as a
	 * dedicated tokenization request.
	 *
	 * @since 2.0
	 * @return boolean true if there is a tokenization request that is issued
	 *         after an authorization/charge transaction
	 */
	public function tokenize_after_sale() {
		return true;
	}


	/**
	 * Return the Payment Tokens Handler class instance.
	 *
	 * @since 2.5.0
	 * @return \WC_Gateway_Moneris_Payment_Tokens_Handler
	 */
	protected function build_payment_tokens_handler() {

		return new WC_Gateway_Moneris_Payment_Tokens_Handler( $this );
	}


	/** AVS/CSC methods ******************************************************/


	/**
	 * Returns true if the AVS checks should be performed when processing a payment
	 *
	 * @since 2.0
	 * @return boolean true if AVS is enabled
	 */
	public function avs_enabled() {
		return 'yes' == $this->enable_avs;
	}


	/**
	 * Returns true if either AVS or CSC checks should be performed when
	 * processing a payment
	 *
	 * @since 2.0
	 * @return boolean true if AVS or CSC is enabled
	 */
	public function has_efraud_validations() {
		return $this->has_avs_validations() || $this->has_csc_validations();
	}


	/**
	 * Returns true if the given card type supports eFraud (AVS/CSC) validations
	 *
	 * @since 2.0
	 * @param string $card_type the card type
	 * @return boolean true if the card type supports AVS and CSC validations
	 */
	private function card_supports_efraud_validations( $card_type ) {

		$valid_types = array(
			SV_WC_Payment_Gateway_Helper::CARD_TYPE_VISA,
			SV_WC_Payment_Gateway_Helper::CARD_TYPE_MASTERCARD,
			SV_WC_Payment_Gateway_Helper::CARD_TYPE_AMEX,
			SV_WC_Payment_Gateway_Helper::CARD_TYPE_DISCOVER,
			SV_WC_Payment_Gateway_Helper::CARD_TYPE_JCB,
		);

		return in_array( $card_type, $valid_types, true );
	}


	/**
	 * Returns true if avs checks are enabled, and at least one check is not
	 * simply 'accept'
	 *
	 * @since 2.0
	 * @return true if avs checks are enabled
	 */
	public function has_avs_validations() {
		return $this->avs_enabled() && ( 'accept' != $this->avs_neither_match || 'accept' != $this->avs_zip_match || 'accept' != $this->avs_street_match || 'accept' != $this->avs_not_verified );
	}


	/**
	 * Returns the single action to take based on the AVS and CSC responses.
	 *
	 * @since 2.0.0
	 *
	 * @param string $avs_code the standardized avs code, one of 'Z', 'A', 'N', 'Y', 'U' or null
	 * @param string $csc_code the standardized csc code, one of 'M', 'N', 'U', or null
	 * @param \WC_Order $order order object
	 *
	 * @return string one of 'accept', 'reject', 'hold'
	 */
	private function get_efraud_action( $avs_code, $csc_code, $order ) {

		$actions = array();

		if ( $avs_code ) {
			$actions[] = $this->get_avs_action( $avs_code );
		}

		// Only get the CSC action if there is a response code and:
		//     a. the csc is required
		//     b. this isn't a saved method transaction
		//     c. this is a hosted tokenization transaction
		// This avoids rejections for situations where a CSC is not a factor,
		// like for a saved method or subscription renewals
		if ( $csc_code && ( $this->csc_required() || empty( $order->payment->token ) || SV_WC_Helper::get_post( 'wc-moneris-temp-payment-token' ) ) ) {
			$actions[] = $this->get_csc_action( $csc_code );
		}

		// rejection conquers all
		if ( in_array( 'reject', $actions ) ) {
			return 'reject';
		}

		// hold beats accept
		if ( in_array( 'hold', $actions ) ) {
			return 'hold';
		}

		// if all good
		return 'accept';
	}


	/**
	 * Returns the action to take based on the settings configuration and
	 * standardized $avs_code.
	 *
	 * @since 2.0
	 * @param string $avs_code the standardized avs code, one of 'Z', 'A', 'N', 'Y', 'U' or null
	 * @return string one of 'accept', 'reject', 'hold'
	 */
	private function get_avs_action( $avs_code ) {

		// unknown card type or unknown result, mark as approved
		if ( is_null( $avs_code ) ) {
			return 'accept';
		}

		switch ( $avs_code ) {
			// zip match, locale no match
			case 'Z': return $this->avs_zip_match;

			// zip no match, locale match
			case 'A': return $this->avs_street_match;

			// zip no match, locale no match
			case 'N': return $this->avs_neither_match;

			// zip match, locale match
			case 'Y': return 'accept';

			// zip and locale could not be verified
			case 'U': return $this->avs_not_verified;
		}
	}


	/**
	 * Returns an error message based on the $avs_code
	 *
	 * @since 2.0
	 * @param string $avs_code the unified AVS error code, one of 'Z', 'A', 'N', 'U'
	 * @return string message based on the code
	 */
	private function get_avs_error_message( $avs_code ) {

		switch ( $avs_code ) {
			// zip match, locale no match
			case 'Z': return __( 'postal code match, street address no match', 'woocommerce-gateway-moneris' );

			// zip no match, locale match
			case 'A': return __( 'postal code no match, street address match', 'woocommerce-gateway-moneris' );

			// zip no match, locale no match
			case 'N': return __( 'postal code no match, street address no match', 'woocommerce-gateway-moneris' );

			// zip and locale could not be verified
			case 'U': return __( 'could not be verified', 'woocommerce-gateway-moneris' );;
		}
	}


	/**
	 * Returns the action to take based on the settings configuration and
	 * standardized $csc_code.
	 *
	 * @since 2.0
	 * @param string $csc_code the standardized avs code, one of 'M', 'N', 'U', or null
	 * @return string one of 'accept', 'reject', 'hold'
	 */
	private function get_csc_action( $csc_code ) {

		// unsupported card
		if ( is_null( $csc_code ) ) {
			return 'accept';
		}

		switch ( $csc_code ) {
			// match
			case 'M': return 'accept';

			// no match
			case 'N': return $this->csc_not_match;

			// could not be verified, or unknown result code
			case 'U': return $this->csc_not_verified;
		}
	}


	/**
	 * Returns an error message based on the $csc_code
	 *
	 * @since 2.0
	 * @param string $csc_code the unified CSC error code, one of 'N', 'U'
	 * @return string message based on the code
	 */
	private function get_csc_error_message( $csc_code ) {

		switch ( $csc_code ) {
			// no match
			case 'N': return __( 'no match', 'woocommerce-gateway-moneris' );

			// zip and locale could not be verified
			case 'U': return __( 'could not be verified', 'woocommerce-gateway-moneris' );;
		}
	}


	/**
	 * Returns true if CSC checks are enabled, and at least one check is not
	 * simple 'accept'
	 *
	 * @since 2.0
	 * @return true if csc checks are enabled
	 */
	private function has_csc_validations() {
		return $this->csc_enabled() && ( 'accept' != $this->csc_not_match || 'accept' != $this->csc_not_verified );
	}


	/**
	 * Returns true if the CSC is required for all transactions, including
	 * tokenized
	 *
	 * @since 2.0
	 * @return boolean true if the CSC is required for all transactions, even tokenized
	 */
	public function csc_required() {
		return $this->csc_enabled() && 'yes' == $this->require_csc;
	}


	/** Subscriptions ******************************************************/


	/**
	 * Tweak the labels shown when editing the payment method for a Subscription
	 *
	 * @hooked from SV_WC_Payment_Gateway_Integration_Subscriptions
	 *
	 * @since 2.3.2
	 * @see SV_WC_Payment_Gateway_Integration_Subscriptions::admin_add_payment_meta()
	 * @param array $meta payment meta
	 * @param \WC_Subscription $subscription subscription being edited, unused
	 * @return array
	 */
	public function subscriptions_admin_add_payment_meta( $meta, $subscription ) {

		if ( isset( $meta[ $this->get_id() ] ) ) {

			$meta[ $this->get_id() ]['post_meta'][ $this->get_order_meta_prefix() . 'payment_token' ]['label'] = __( 'Data Key', 'woocommerce-gateway-moneris' );
		}

		return $meta;
	}


	/**
	 * Returns meta keys to be excluded when copying over meta data when:
	 *
	 * + a renewal order is created from a subscription
	 * + the user changes their payment method for a subscription
	 * + processing the upgrade from Subscriptions 1.5.x to 2.0.x
	 *
	 * @since 2.3.2
	 * @param array $meta_keys
	 * @return array
	 */
	public function subscriptions_get_excluded_order_meta_keys( $meta_keys ) {

		$meta_keys[] = $this->get_order_meta_prefix() . 'integration';
		$meta_keys[] = $this->get_order_meta_prefix() . 'receipt_id';
		$meta_keys[] = $this->get_order_meta_prefix() . 'reference_num';
		$meta_keys[] = $this->get_order_meta_prefix() . 'avs';
		$meta_keys[] = $this->get_order_meta_prefix() . 'csc';

		return $meta_keys;
	}


	/**
	 * Returns true if this gateway with its current configuration supports
	 * subscriptions.  Requiring CSC for all transactions removes support for
	 * subscriptions
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway::supports_subscriptions()
	 * @return boolean true if the gateway supports subscriptions
	 */
	public function supports_subscriptions() {
		return parent::supports_subscriptions() && ! $this->csc_required();
	}


	/**
	 * Returns true if this gateway with its current configuration supports
	 * pre-orders.  Requiring CSC for all transactions removes support for
	 * pre-orders
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway::supports_pre_orders()
	 * @return boolean true if the gateway supports pre-orders
	 */
	public function supports_pre_orders() {
		return parent::supports_pre_orders() && ! $this->csc_required();
	}


	/** Pay Page Hosted Tokenization methods **********************************/


	/**
	 * Returns the url to the hosted tokenization checkout script
	 *
	 * @since 2.0
	 * @param string $url the checkout javascript url, unused
	 * @return string the checkout javascript url
	 */
	public function hosted_tokenization_javascript_url( $url ) {
		// return the url to the hosted tokenization javascript
		return $this->get_plugin()->get_plugin_url() . '/assets/js/frontend/wc-' . $this->get_plugin()->get_id_dasherized() . '-hosted-tokenization.min.js';
	}


	/**
	 * Handle the hosted tokenization response
	 *
	 * This is called from an AJAX context because the request is made in
	 * client-side javascript
	 *
	 * @since 2.0
	 */
	public function handle_hosted_tokenization_response() {

		$order_id      = isset( $_GET['orderId'] )      ? $_GET['orderId']      : '';  // order id if on pay page only
		$response_code = isset( $_GET['responseCode'] ) ? $_GET['responseCode'] : '';
		$error_message = isset( $_GET['errorMessage'] ) ? $_GET['errorMessage'] : '';
		$token         = isset( $_GET['token'] )        ? $_GET['token'] : '';
		$request_time  = isset( $_GET['requestTime'] )  ? $_GET['requestTime']  : '';  // request time, in seconds

		$response_body = array();
		if ( $token ) {
			$response_body[] = sprintf( __( 'token: %s', 'woocommerce-gateway-moneris' ), $token );
		}
		if ( $error_message ) {
			$response_body[] = sprintf( __( 'hosted tokenization response error message: %s', 'woocommerce-gateway-moneris' ), $error_message );
		}

		$this->get_plugin()->log_api_request(
			array(
				'time'   => $request_time,
				'method' => 'POST',
				'uri'    => $this->get_hosted_tokenization_iframe_url(),
				'body'   => null,
			),
			array(
				'code' => $response_code,
				'body' => implode( ', ', $response_body ),
			)
		);

		if ( $response_code >= 50 ) {

			if ( ! $order_id ) {

				if ( WC()->session->order_awaiting_payment > 0 ) {

					$putative_order_id = absint( WC()->session->order_awaiting_payment );

					// Check order is unpaid by getting its status
					$terms        = wp_get_object_terms( $putative_order_id, 'shop_order_status', array( 'fields' => 'slugs' ) );
					$order_status = isset( $terms[0] ) ? $terms[0] : 'pending';

					// current working order available?
					if ( get_post( $putative_order_id ) && ( $order_status == 'pending' || $order_status == 'failed' ) ) {
						$order_id = $putative_order_id;
					}
				}
			}

			// if we have an order id (on the pay page) add an order note
			if ( $order_id ) {
				$order = wc_get_order( $order_id );

				$order_note = sprintf( '%s: %s', $response_code, implode( ', ', $response_body ) );

				// any chance of some kind of a delay occurring, and this marking an order as failed when it has already succeeded?
				$this->mark_order_as_failed_quiet( $order, $order_note );
			}
		}
	}


	/**
	 * Mark the given order as failed and set the order note
	 *
	 * @since 2.0
	 * @param WC_Order $order the order
	 * @param string $error_message a message to display inside the "Payment Failed" order note
	 */
	protected function mark_order_as_failed_quiet( $order, $error_message ) {

		$order_note = sprintf( _x( '%s Payment Failed (%s)', 'Order Note: (Payment method) Payment failed (error)', 'woocommerce-gateway-moneris' ), $this->get_method_title(), $error_message );

		// Mark order as failed if not already set, otherwise, make sure we add the order note so we can detect when someone fails to check out multiple times
		if ( ! $order->has_status( 'failed' ) ) {
			$order->update_status( 'failed', $order_note );
		} else {
			$order->add_order_note( $order_note );
		}

		$this->add_debug_message( $error_message, 'error' );

		// shhhh quiet like
		// wc_add_notice( __( 'An error occurred, please try again or try an alternate form of payment.', 'woocommerce-gateway-moneris' ), 'error' );
	}


	/** Frontend methods ******************************************************/


	/**
	 * Enqueues the required gateway.js library and custom checkout javascript.
	 * Also localizes payment method validation errors
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway::enqueue_scripts()
	 * @return boolean true if the scripts were enqueued, false otherwise
	 */
	public function enqueue_scripts() {

		// call to parent and determine whether we need to load
		if ( ! parent::enqueue_scripts() ) {
			return false;
		}

		// enqueue the frontend styles
		wp_enqueue_style( 'wc-moneris', $this->get_plugin()->get_plugin_url() . '/assets/css/frontend/wc-moneris.min.css', false, WC_Moneris::VERSION );

		return true;
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
		require_once( $this->get_plugin()->get_plugin_path() . '/includes/api/class-wc-moneris-api-create-payment-token-response.php' );
		require_once( $this->get_plugin()->get_plugin_path() . '/includes/api/class-wc-moneris-api-delete-payment-token-response.php' );

		return $this->api = new WC_Moneris_API( $this->get_id(), $this->get_api_endpoint(), $this->get_store_id(), $this->get_api_token(), $this->get_integration_country() );
	}


	/**
	 * Returns the configured integration country identifier
	 *
	 * @since 2.0
	 * @return string one of 'us' or 'ca'
	 */
	public function get_integration_country() {
		return $this->integration_country;
	}


	/**
	 * Set the integration country identifier
	 *
	 * @since 2.3.2
	 * @param string $country, either `us` or `ca`
	 */
	public function set_integration_country( $country ) {

		$this->integration_country = $country;
	}


	/**
	 * Returns true if the configured integration is US
	 *
	 * @since 2.0
	 * @param string $integration optional integration id, one of 'us' or 'ca'.
	 *        Defaults to currently configured integration
	 * @return boolean true if the configured integration is US
	 */
	public function is_us_integration( $integration = null ) {

		if ( is_null( $integration ) ) {
			$integration = $this->get_integration_country();
		}

		return WC_Moneris::INTEGRATION_US == $integration;
	}


	/**
	 * Returns true if the configured integration is Canadian
	 *
	 * @since 2.0
	 * @param string $integration optional integration id, one of 'us' or 'ca'.
	 *        Defaults to currently configured integration
	 * @return boolean true if the configured integration is Canadian
	 */
	public function is_ca_integration( $integration = null ) {

		if ( is_null( $integration ) ) {
			$integration = $this->get_integration_country();
		}

		return WC_Moneris::INTEGRATION_CA == $integration;
	}


	/**
	 * Returns true if the hosted tokenization option is enabled
	 *
	 * @since 2.0
	 * @return boolean true if the hosted tokenization option is enabled
	 */
	public function hosted_tokenization_enabled() {
		return 'yes' == $this->hosted_tokenization;
	}


	/**
	 * Returns the currently configured store id, based on the current
	 * environment and integration country
	 *
	 * @since 2.0
	 * @return string the current store id
	 */
	public function get_hosted_tokenization_profile_id() {

		if ( $this->is_production_environment() ) {
			$profile_id = $this->hosted_tokenization_profile_id;
		} else {
			$profile_id = $this->test_hosted_tokenization_profile_id;
		}

		// get the current order ID
		$order_id = isset( $GLOBALS['wp']->query_vars['order-pay'] ) ? absint( $GLOBALS['wp']->query_vars['order-pay'] ) : 0;

		/**
		 * Filter the hosted tokenization profile ID.
		 *
		 * @since 2.5.0
		 * @param string $profile_id the profile ID
		 * @param int $order_id the order ID
		 * @param \WC_Gateway_Moneris_Credit_Card the gateway instance
		 */
		return apply_filters( 'wc_moneris_hosted_tokenization_profile_id', $profile_id, $order_id, $this );
	}


	/**
	 * Returns the hosted tokenization url, which can be used to create the
	 * iframe url
	 *
	 * @since 2.0
	 * @return string hosted tokenization url
	 */
	public function get_hosted_tokenization_url() {
		return $this->get_moneris_host() . '/HPPtoken/index.php';
	}


	/**
	 * Returns the hosted tokenization iframe url, given the currently configured
	 * environment, integration, and profile ID
	 *
	 * @since 2.0
	 * @return string hosted tokenization iframe url
	 */
	public function get_hosted_tokenization_iframe_url() {

		$url = $this->get_hosted_tokenization_url();

		if ( $this->get_hosted_tokenization_profile_id() ) {
			$url = add_query_arg( array( 'id' => $this->get_hosted_tokenization_profile_id() ), $url );
		}

		$css_textbox = apply_filters( 'wc_moneris_hosted_tokenization_css_textbox', 'width:100%;border-radius:3px;padding:0.428571rem;border-width:1px;', $this );
		if ( $css_textbox ) {
			$url = add_query_arg( array( 'css_textbox' => $css_textbox ), $url );
		}

		return $url;
	}


	/**
	 * Returns true if hosted tokenization is available (enabled and profile
	 * id set)
	 *
	 * @since 2.0
	 * @return boolean true if hosted tokenization is available, false otherwise
	 */
	public function hosted_tokenization_available() {
		return $this->hosted_tokenization_enabled() && $this->get_hosted_tokenization_profile_id();
	}


	/**
	 * Returns the Moneris host given $environment and $integration
	 *
	 * @since 2.0
	 * @param string $environment optional environment id, one of 'test' or 'production'.
	 *        Defaults to currently configured environment
	 * @param string $integration optional integration id, one of 'us' or 'ca'.
	 *        Defaults to currently configured integration
	 * @return string moneris host based on the environment and integration
	 */
	public function get_moneris_host( $environment = null, $integration = null ) {

		// get parameter defaults
		if ( is_null( $environment ) ) {
			$environment = $this->get_environment();
		}
		if ( is_null( $integration ) ) {
			$integration = $this->get_integration_country();
		}

		if ( $this->is_production_environment( $environment ) ) {
			return WC_Moneris::INTEGRATION_US == $integration ? WC_Moneris::PRODUCTION_URL_ENDPOINT_US : WC_Moneris::PRODUCTION_URL_ENDPOINT_CA;
		} else {
			return WC_Moneris::INTEGRATION_US == $integration ? WC_Moneris::TEST_URL_ENDPOINT_US : WC_Moneris::TEST_URL_ENDPOINT_CA;
		}
	}


	/**
	 * Returns the API endpoint based on the environment and integration
	 * country
	 *
	 * @since 2.0
	 * @return string current API endpoint URL
	 */
	public function get_api_endpoint() {

		$endpoint = $this->get_moneris_host();

		if ( $this->is_us_integration() ) {
			return $endpoint . '/gateway_us/servlet/MpgRequest';
		} else {
			return $endpoint . '/gateway2/servlet/MpgRequest';
		}
	}


	/**
	 * Gets the merchant center login URL
	 *
	 * @since 2.0
	 * @param string $environment optional environment id, one of 'test' or 'production'.
	 *        Defaults to currently configured environment
	 * @param string $integration optional integration id, one of 'us' or 'ca'.
	 *        Defaults to currently configured integration
	 * @return string merchant center login URL based on the environment and integration
	 */
	public function get_merchant_center_url( $environment = null, $integration = null ) {

		// get parameter defaults
		if ( is_null( $environment ) ) {
			$environment = $this->get_environment();
		}
		if ( is_null( $integration ) ) {
			$integration = $this->get_integration_country();
		}

		$endpoint = $this->get_moneris_host( $environment, $integration );

		if ( $this->is_us_integration( $integration ) ) {
			return $endpoint . '/usmpg';
		} else {
			return $endpoint . '/mpg';
		}
	}


	/**
	 * Returns true if the configured dynamic descriptor is valid.  For the US
	 * integration, the dynamic descriptor must be 20 characters or less
	 *
	 * @since 2.0
	 * @return boolean true if the configured dynamic descriptor is valid
	 */
	public function dynamic_descriptor_is_valid() {
		if ( $this->is_us_integration() && strlen( $this->get_dynamic_descriptor() ) > 20 ) {
			return false;
		}

		return true;
	}


	/**
	 * Returns the configured dynamic descriptor
	 *
	 * @since 2.0
	 * @param boolean $safe optional parameter, to indicate whether to return a
	 *        safe (US integration) version.  Defaults to false
	 * @return string the dynamic descriptor
	 */
	public function get_dynamic_descriptor( $safe = false ) {
		if ( $safe && $this->is_us_integration() ) {
			return substr( $this->dynamic_descriptor, 0, 20 );
		} else {
			return $this->dynamic_descriptor;
		}
	}


	/**
	 * Returns the currently configured store id, based on the current
	 * environment and integration country
	 *
	 * @since 2.0
	 * @return string the current store id
	 */
	public function get_store_id() {
		if ( $this->is_production_environment() ) {
			return $this->store_id;
		} else {
			return $this->is_us_integration() ? $this->us_test_store_id : $this->ca_test_store_id;
		}
	}


	/**
	 * Returns the currently configured API token, based on the current
	 * environment and integration country
	 *
	 * @since 2.0
	 * @return string the current store id
	 */
	public function get_api_token() {
		if ( $this->is_production_environment() ) {
			return $this->api_token;
		} else {
			if ( $this->is_us_integration() ) {
				return 'qatoken';
			} else {
				if ( 'moneris' == $this->get_store_id() ) {
					return 'hurgle';
				} else {
					return 'yesguy';
				}
			}
		}
	}


}
