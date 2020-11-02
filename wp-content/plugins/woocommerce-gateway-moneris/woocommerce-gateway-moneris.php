<?php
/**
 * Plugin Name: WooCommerce Moneris Gateway
 * Plugin URI: http://www.woocommerce.com/products/moneris-gateway/
 * Description: Accept credit cards and Interac Online in WooCommerce with the Moneris Gateway
 * Author: SkyVerge
 * Author URI: http://www.woocommerce.com/
 * Version: 2.7.2
 * Text Domain: woocommerce-gateway-moneris
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2012-2017, SkyVerge, Inc. (info@skyverge.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Gateway-Moneris
 * @author    SkyVerge
 * @category  Gateway
 * @copyright Copyright (c) 2012-2017, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

// Required functions
if ( ! function_exists( 'woothemes_queue_update' ) )
	require_once( plugin_dir_path( __FILE__ ) . 'woo-includes/woo-functions.php' );

// Plugin updates
woothemes_queue_update( plugin_basename( __FILE__ ), 'fb15ca1ba925054072fe2ef35b2e1925', '18638' );

// WC active check
if ( ! is_woocommerce_active() ) {
	return;
}

// Required library classss
if ( ! class_exists( 'SV_WC_Framework_Bootstrap' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . 'lib/skyverge/woocommerce/class-sv-wc-framework-bootstrap.php' );
}

SV_WC_Framework_Bootstrap::instance()->register_plugin( '4.6.1', __( 'WooCommerce Moneris Gateway', 'woocommerce-gateway-moneris' ), __FILE__, 'init_woocommerce_gateway_moneris', array(
	'is_payment_gateway'   => true,
	'minimum_wc_version'   => '2.5.5',
	'minimum_wp_version'   => '4.1',
	'backwards_compatible' => '4.4',
) );

function init_woocommerce_gateway_moneris() {

/**
 * # WooCommerce Moneris Gateway Main Plugin Class
 *
 * ## Plugin Overview
 *
 * This plugin adds Moneris as a payment gateway.  This class handles all the
 * non-gateway tasks such as verifying dependencies are met, loading the text
 * domain, etc.  It also loads the Moneris Gateway when needed now that the
 * gateway is only created on the checkout & settings pages / api hook.  The gateway is
 * also loaded in the following instances:
 *
 * + On the My Account page to display / change saved payment methods
 *
 * ### Gateways
 *
 * This plugin provides two gateways: a credit card, and Interac online.  The
 * credit card gateway is a direct gateway implementation, while Interac online
 * is hosted/redirect, with a direct completion call after the redirect.
 *
 * ### AVS/CSC
 *
 * Moneris, annoyingly, does not handle AVS/CSC validations on their side,
 * instead always returning success for an order, along with the AVS/CSC code,
 * and leaving it up to the integrator to determine how to handle the result.
 *
 * The strategy used by this plugin is to always perform an authorization if
 * AVS and/or CSC validations are enabled and something other than 'accept'
 * (see plugin settings for details).  If an order returns a code which is
 * configured to be rejected, the authorization will be silently reversed.  If
 * on the other hand, the gateway is configured to perform "charge"
 * transactions, and the AVS/CSC validations pass, the transaction will be
 * silently captured.
 *
 * ### Credit Card Tokenization
 *
 * Moneris supports both post-transaction and standalone credit card
 * tokenization requests.  The post-transaction method is used when placing an
 * order and tokenizing at the same time.  The standalone tokenization request
 * is used when capturing a token ie for a subscription with a trial period, or
 * a pre-order product that is charged upon release.
 *
 * ### Authorization (pre-auth)
 *
 * According to the documentation, an authorization MUST either be captured or
 * reversed within 3 days.  Otherwise, who knows what happens, probably nothing.
 * This is "handled" by providing both a "capture" and "reverse" order action
 * for authorize-only orders.
 *
 * No automatic capturing or reversing via cronjob is supported.
 *
 * ## Features
 *
 * + Credit Card Authorization
 * + Credit Card Charge
 * + Credit Card Authorization Capture
 * + Tokenization
 * + WooCommerce Pre-orders Support
 * + WooCommerce Subscriptions Support
 * + AVS/CSC Validation Handling
 * + Interac Online (debit card through online banking)
 *
 * ## Frontend Considerations
 *
 * ### Credit Card Gateway
 *
 * Both the payment fields on checkout (and checkout->pay) and the My cards section on the My Account page are template
 * files for easy customization.
 *
 * ### Interac Gateway
 *
 * The Issuer Confirmation and Issuer Name returned by the Interac server
 * response is displayed on the customer receipt.
 *
 * ## Database
 *
 * ### Global Settings
 *
 * + `woocommerce_moneris_settings` - the serialized gateway settings array
 *
 * ### Post Meta
 *
 * + `_wc_moneris_environment` - the environment the transaction was created in, one of 'test' or 'production'
 * + `_wc_moneris_retry_count` - A count of the number of transaction attempts (ie failures) so that a unique transaction number can be generated for each request
 * + `_wc_moneris_customer_id` - the Moneris customer ID for the order, set only if the customer is logged in/creating an account
 * + `_wc_moneris_integration` - the integration for the order: one of 'us' or 'ca'
 * + `_wc_moneris_receipt_id` - the order number (returned to us by Moneris)
 *
 * #### Credit Card
 *
 * + `_wc_moneris_card_type` - the card type used for the transaction, if known
 * + `_wc_moneris_account_four` - the last four digits of the card used for the order
 * + `_wc_moneris_card_expiry_date` - the expiration date of the card used for the order
 * + `_wc_moneris_payment_token` - the token for the credit card used for this transaction, set only if the customer is logged in and using a tokenized payment method
 * + `_wc_moneris_trans_id` - the credit card transaction ID returned by Moneris
 * + `_wc_moneris_trans_date` - the datetime the transaction was made (used for validating authorization captures)
 * + `_wc_moneris_authorization_code` - the authorization code returned by Moneris
 * + `_wc_moneris_charge_captured` - whether the charge has been captured: 'yes' or 'no'
 * + `_wc_moneris_auth_can_be_captured` - whether the charge can be captured: 'no' if not
 * + `_wc_moneris_avs` - the AVS response code
 * + `_wc_moneris_csc` - the CSC validation response code
 *
 * #### Interac
 *
 * + `_wc_moneris_interac_idebit_issconf` - the Issuer Confirmation number, returned by Interac
 * + `_wc_moneris_interac_idebit_issname` - the Issuer Name, returned by Interac
 * + `_wc_moneris_interac_idebit_trans_id` - the transaction id from the idebit_purchase request which follows a funded response from the Interac pay page
 *
 * ### User Meta
 *
 * + `_wc_moneris_customer_id` - production environment Moneris customer ID for the user
 * + `_wc_moneris_customer_id_test` - test environment Moneris customer ID for the user
 * + `_wc_moneris_payment_tokens` - production environment payment tokens
 * + `_wc_moneris_payment_tokens_test` - test environment payment tokens
 *
 * @since 2.0
 */
class WC_Moneris extends SV_WC_Payment_Gateway_Plugin {


	/** version number */
	const VERSION = '2.7.2';

	/** @var WC_Moneris single instance of this plugin */
	protected static $instance;

	/** the plugin id */
	const PLUGIN_ID = 'moneris';

	/** plugin text domain, DEPRECATED as of 2.4.0 */
	const TEXT_DOMAIN = 'woocommerce-gateway-moneris';

	/** the credit card gateway class name */
	const CREDIT_CARD_GATEWAY_CLASS_NAME = 'WC_Gateway_Moneris_Credit_Card';

	/** the credit card gateway id */
	const CREDIT_CARD_GATEWAY_ID = 'moneris';

	/** the interac online gateway class name */
	const INTERAC_GATEWAY_CLASS_NAME = 'WC_Gateway_Moneris_Interac';

	/** the interac online gateway id */
	const INTERAC_GATEWAY_ID = 'moneris_interac';

	/** the production URL endpoint for the Canadian integration */
	const PRODUCTION_URL_ENDPOINT_CA = 'https://www3.moneris.com';

	/** the test (sandbox) URL endpoint for the Canadian integration */
	const TEST_URL_ENDPOINT_CA = 'https://esqa.moneris.com';

	/** the production URL endpoint for the US integration */
	const PRODUCTION_URL_ENDPOINT_US = 'https://esplus.moneris.com';

	/** the test (sandbox) URL endpoint for the US integration */
	const TEST_URL_ENDPOINT_US = 'https://esplusqa.moneris.com';

	/** the Canadian integration identifier */
	const INTEGRATION_CA = 'ca';

	/** the US integration identifier */
	const INTEGRATION_US = 'us';


	/**
	 * Setup main plugin class
	 *
	 * @since 2.0
	 */
	public function __construct() {

		parent::__construct(
			self::PLUGIN_ID,
			self::VERSION,
			array(
				'text_domain' => 'woocommerce-gateway-moneris',
				'gateways'    => array(
					self::CREDIT_CARD_GATEWAY_ID => self::CREDIT_CARD_GATEWAY_CLASS_NAME,
					self::INTERAC_GATEWAY_ID     => self::INTERAC_GATEWAY_CLASS_NAME,
				),
				'dependencies'       => array( 'SimpleXML', 'xmlwriter', 'dom' ),
				'currencies'         => array( 'CAD' ),
				'require_ssl'        => true,
				'supports'           => array(
					self::FEATURE_CAPTURE_CHARGE,
					self::FEATURE_CUSTOMER_ID,
					self::FEATURE_MY_PAYMENT_METHODS,
				),
			)
		);

		// Load gateway files after woocommerce is loaded
		add_action( 'sv_wc_framework_plugins_loaded', array( $this, 'includes' ), 11 );

		add_action( 'init', array( $this, 'include_template_functions' ), 25 );

		// Display Interac issuer data to customer
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'interac_order_table_receipt_data' ) );
		add_action( 'woocommerce_email_after_order_table',         array( $this, 'interac_email_order_table_receipt_data' ), 10, 3 );

		add_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'maybe_reverse_authorization' ) );

		if ( is_admin() && ! is_ajax() ) {
			add_action( 'woocommerce_order_action_' . $this->get_id() . '_reverse_authorization', array( $this, 'maybe_reverse_authorization' ) );
		}

		// Pay Page - Hosted Tokenization Checkout
		// AJAX handler to handle request logging
		add_action( 'wp_ajax_wc_payment_gateway_' . $this->get_id() . '_handle_hosted_tokenization_response',        array( $this, 'handle_hosted_tokenization_response' ) );
		add_action( 'wp_ajax_nopriv_wc_payment_gateway_' . $this->get_id() . '_handle_hosted_tokenization_response', array( $this, 'handle_hosted_tokenization_response' ) );
	}


	/**
	 * Loads any required files
	 *
	 * @since 2.0
	 */
	public function includes() {

		// gateway classes
		require_once( $this->get_plugin_path() . '/includes/class-wc-gateway-moneris-credit-card.php' );
		require_once( $this->get_plugin_path() . '/includes/class-wc-gateway-moneris-interac.php' );

		// tokens handler class
		require_once( $this->get_plugin_path() . '/includes/class-wc-gateway-moneris-payment-tokens-handler.php' );
	}


	/**
	 * Returns the "Configure Credit Cards" or "Configure Interac" plugin action links that go
	 * directly to the gateway settings page
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_Plugin::get_settings_url()
	 * @param string $gateway_id the gateway identifier
	 * @return string plugin configure link
	 */
	public function get_settings_link( $gateway_id = null ) {

		return sprintf( '<a href="%s">%s</a>',
			$this->get_settings_url( $gateway_id ),
			self::CREDIT_CARD_GATEWAY_ID === $gateway_id ? __( 'Configure Moneris', 'woocommerce-gateway-moneris' ) : __( 'Configure Interac', 'woocommerce-gateway-moneris' )
		);
	}


	/**
	 * Checks if required PHP extensions are loaded and SSL is enabled. Adds an admin notice if either check fails.
	 * Also gateway settings are checked as well.
	 *
	 * @since  2.1.0
	 * @see SV_WC_Plugin::add_admin_notices()
	 */
	public function add_admin_notices() {

		parent::add_admin_notices();

		// show a notice for any settings/configuration issues
		$this->add_settings_admin_notices();
	}


	/**
	 * Render the dynamic descriptor error message, as needed
	 *
	 * @since  2.0
	 */
	private function add_settings_admin_notices() {

		$settings = $this->get_gateway_settings( self::CREDIT_CARD_GATEWAY_ID );

		// technically not DRY, but avoids unnecessary instantiation of the gateway class
		if ( (
				( isset( $settings['integration'] ) && 'us' == $settings['integration'] && strlen( $settings['dynamic_descriptor'] ) > 20 && ! isset( $_POST['woocommerce_moneris_integration'] ) ) ||
				( isset( $_POST['woocommerce_moneris_integration'] ) && 'us' == $_POST['woocommerce_moneris_integration'] && strlen( $_POST['woocommerce_moneris_dynamic_descriptor'] ) > 20 )
			) ) {

			$message = sprintf(
				__( '%1$sMoneris Gateway:%2$s US integration dynamic descriptor is too long.  You are recommended to %3$sshorten%4$s it to 20 characters or less as only the first 20 characters will be used.', 'woocommerce-gateway-moneris' ),
				'<strong>', '</strong>',
				'<a href="' . $this->get_settings_url() . '#woocommerce_moneris_dynamic_descriptor">', '</a>'
			);
			$this->get_admin_notice_handler()->add_admin_notice( $message, 'us-dynamic-descriptor-notice' );
		}

		// warning if hosted tokenization is enabled but no profile id is configured
		$hosted_tokenization_enabled = isset( $_POST['woocommerce_moneris_environment'] ) ?
			isset( $_POST['woocommerce_moneris_hosted_tokenization'] ) && '1' == $_POST['woocommerce_moneris_hosted_tokenization'] :
			'yes' == $settings['hosted_tokenization'];
		$environment = isset( $_POST['woocommerce_moneris_environment'] ) ? $_POST['woocommerce_moneris_environment'] : $settings['environment'];
		$environment_prefix = 'production' == $environment ? '' : $environment . '_';
		$hosted_tokenization_profile_id = isset( $_POST[ 'woocommerce_moneris_' . $environment_prefix . 'hosted_tokenization_profile_id' ] ) ?
			$_POST[ 'woocommerce_moneris_' . $environment_prefix . 'hosted_tokenization_profile_id' ] :
			$settings[ $environment_prefix . 'hosted_tokenization_profile_id' ];

		if ( (
				$hosted_tokenization_enabled && ! $hosted_tokenization_profile_id
			) ) {

			$message = sprintf(
				__( '%1$sMoneris Gateway:%2$s Hosted tokenization is enabled but will not be active until a %3$sProfile ID%4$s is configured.', 'woocommerce-gateway-moneris' ),
				'<strong>', '</strong>',
				'<a href="' . $this->get_settings_url() . '#woocommerce_moneris_' . $environment_prefix . 'hosted_tokenization_profile_id">', '</a>'
			);
			$this->get_admin_notice_handler()->add_admin_notice( $message, 'hosted-tokenization-profile-id-missing-notice', array( 'notice_class' => 'error' ) );
		}
	}


	/**
	 * Add a "Reverse Authorization" action to the Admin Order Edit Order
	 * Actions dropdown
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_Plugin::add_order_action_charge_action()
	 * @param array $actions available order actions
	 * @return array
	 */
	public function add_order_action_charge_action( $actions ) {

		$actions = parent::add_order_action_charge_action( $actions );

		$actions[ $this->get_id() . '_reverse_authorization' ] = __( 'Reverse Authorization', 'woocommerce-gateway-moneris' );

		return $actions;
	}


	/**
	 * Reverse a prior authorization if this payment method was used for the
	 * given order, the charge hasn't already been captured/reversed
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_Plugin::maybe_capture_charge()
	 * @param WC_Order|int $order the order identifier or order object
	 */
	public function maybe_reverse_authorization( $order ) {

		if ( ! is_object( $order ) ) {
			$order = wc_get_order( $order );
		}

		$payment_method = SV_WC_Order_Compatibility::get_prop( $order, 'payment_method' );

		// bail if the order wasn't paid for with this gateway
		if ( ! $this->has_gateway( $payment_method ) ) {
			return;
		}

		// ensure the authorization is still valid for capture
		if ( ! $this->get_gateway( $payment_method )->authorization_valid_for_capture( $order ) ) {
			return;
		}

		// remove order status change actions, otherwise we get a whole bunch of reverse calls and errors
		remove_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'maybe_reverse_authorization' ) );
		remove_action( 'woocommerce_order_action_' . $this->get_id() . '_reverse_authorization', array( $this, 'maybe_reverse_authorization' ) );

		// Starting in WC 2.1 we need to remove the meta box order save action, otherwise the wp_update_post() call
		//  in WC_Order::update_status() to update the post last modified will re-trigger the save action, which
		//  will update the order status to $_POST['order_status'] which of course will be whatever the order status
		//  was prior to the auth capture (ie 'on-hold')
		remove_action( 'woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 10 );

		// perform the capture
		$this->get_gateway( $payment_method )->do_credit_card_reverse_authorization( $order );
	}


	/** Frontend methods ******************************************************/


	/**
	 * Function used to init any gateway template functions,
	 * making them pluggable by plugins and themes.
	 *
	 * @since 2.0
	 */
	public function include_template_functions() {

		require_once( $this->get_plugin_path() . '/includes/wc-gateway-moneris-template.php' );
	}


	/**
	 * Display the Interac Issuer confirmation number and name in the order
	 * receipt table, if the given order was paid for via Interac
	 *
	 * @since 2.0
	 * @param WC_Order $order the order object
	 */
	public function interac_order_table_receipt_data( $order ) {

		// non-interac order
		if ( self::INTERAC_GATEWAY_ID !== SV_WC_Order_Compatibility::get_prop( $order, 'payment_method' ) ) {
			return;
		}

		$issuer_conf = SV_WC_Order_Compatibility::get_meta( $order, '_wc_moneris_interac_idebit_issconf' );
		$issuer_name = SV_WC_Order_Compatibility::get_meta( $order, '_wc_moneris_interac_idebit_issname' );

		// missing the data
		if ( ! $issuer_conf || ! $issuer_name ) {
			return;
		}

		// otherwise: display the idebit data
		?>
		<header>
			<h2><?php _e( 'INTERAC Details', 'woocommerce-gateway-moneris' ); ?></h2>
		</header>
		<dl class="interac_details">
			<dt><?php _e( 'Issuer Confirmation:', 'woocommerce-gateway-moneris' ); ?></dt><dd><?php echo esc_html( $issuer_conf ); ?></dd>
			<dt><?php _e( 'Issuer Name:', 'woocommerce-gateway-moneris' ); ?></dt><dd><?php echo esc_html( $issuer_name ); ?></dd>
		</dl>
		<?php
	}


	/**
	 * Display the Interac Issuer confirmation number and name in the email
	 * order receipt table, if the given order was paid for via Interac
	 *
	 * @since 2.0
	 * @param WC_Order $order the order object
	 */
	public function interac_email_order_table_receipt_data( $order, $sent_to_admin, $plain_text = false ) {

		// non-interac order
		if ( self::INTERAC_GATEWAY_ID !== SV_WC_Order_Compatibility::get_prop( $order, 'payment_method' ) ) {
			return;
		}

		$issuer_conf = SV_WC_Order_Compatibility::get_meta( $order, '_wc_moneris_interac_idebit_issconf' );
		$issuer_name = SV_WC_Order_Compatibility::get_meta( $order, '_wc_moneris_interac_idebit_issname' );

		// missing the data
		if ( ! $issuer_conf || ! $issuer_name ) {
			return;
		}

		if ( ! $plain_text ) {
			// html email
			?>
			<h2><?php _e( 'INTERAC Details', 'woocommerce-gateway-moneris' ); ?></h2>

			<p><strong><?php _e( 'Issuer Confirmation:', 'woocommerce-gateway-moneris' ); ?></strong> <?php echo esc_html( $issuer_conf ); ?></p>
			<p><strong><?php _e( 'Issuer Name:', 'woocommerce-gateway-moneris' ); ?></strong>         <?php echo esc_html( $issuer_name ); ?></p>
			<?php
		} else {
			// plain text email
			echo __( 'INTERAC Details', 'woocommerce-gateway-moneris' ) . "\n\n";

			echo __( 'Issuer Confirmation:', 'woocommerce-gateway-moneris' ) . ' ' . esc_html( $issuer_conf ) . "\n";
			echo __( 'Issuer Name:', 'woocommerce-gateway-moneris' )         . ' ' . esc_html( $issuer_name ) . "\n";
		}
	}


	/** Hosted Tokenization methods ******************************************************/


	/**
	 * Handle the hosted tokenization response by handing off to the gateway
	 *
	 * @since 2.0
	 */
	public function handle_hosted_tokenization_response() {
		$this->get_gateway()->handle_hosted_tokenization_response();
	}


	/** Helper methods ******************************************************/


	/**
	 * Main Moneris Instance, ensures only one instance is/can be loaded
	 *
	 * @since 2.2.0
	 * @see wc_moneris()
	 * @return WC_Moneris
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/**
	 * Gets the plugin documentation url
	 *
	 * @since 2.0
	 * @see SV_WC_Plugin::get_documentation_url()
	 * @return string documentation URL
	 */
	public function get_documentation_url() {
		return 'http://docs.woocommerce.com/document/moneris/';
	}


	/**
	 * Gets the plugin support URL
	 *
	 * @since 2.3.0
	 * @see SV_WC_Plugin::get_support_url()
	 * @return string
	 */
	public function get_support_url() {

		return 'https://woocommerce.com/my-account/tickets/';
	}


	/**
	 * Returns the plugin name, localized
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway::get_plugin_name()
	 * @return string the plugin name
	 */
	public function get_plugin_name() {
		return __( 'WooCommerce Moneris Gateway', 'woocommerce-gateway-moneris' );
	}


	/**
	 * Returns __FILE__
	 *
	 * @since 2.0
	 * @return string the full path and filename of the plugin file
	 */
	protected function get_file() {
		return __FILE__;
	}


	/** Lifecycle methods ******************************************************/


	/**
	 * Install default settings
	 *
	 * @since 2.0
	 */
	protected function install() {

		if ( $this->get_gateway_settings( self::CREDIT_CARD_GATEWAY_ID ) ) {
			// v1 releases didn't track the version number, so we can't tell what we're upgrading from
			$this->upgrade( null );
		}
	}


	/**
	 * Perform any version-related changes.
	 *
	 * @since 2.0
	 * @param int $installed_version the currently installed version of the plugin
	 */
	protected function upgrade( $installed_version ) {

		if ( null === $installed_version ) {
			// upgrading from v1
			$settings = $this->get_gateway_settings( self::CREDIT_CARD_GATEWAY_ID );

			// rename 'purchasecountry' to 'integration'
			$settings['integration'] = $settings['purchasecountry'];
			unset( $settings['purchasecountry'] );

			// framework standard
			$settings['enable_csc'] = $settings['enable_cvd'];
			unset( $settings['enable_cvd'] );

			$settings['dynamic_descriptor'] = $settings['dynamicdescriptor'];
			unset( $settings['dynamicdescriptor'] );

			$settings['environment'] = 'yes' == $settings['sandbox'] ? 'test' : 'production';
			unset( $settings['sandbox'] );

			if ( 'test' == $settings['environment'] ) {

				$settings['test_store_id'] = $settings['storeid'];
				unset( $settings['storeid'] );

				$settings['test_api_token'] = $settings['apitoken'];
				unset( $settings['apitoken'] );

			} else {

				$settings['store_id'] = $settings['storeid'];
				unset( $settings['storeid'] );

				$settings['api_token'] = $settings['apitoken'];
				unset( $settings['apitoken'] );

			}

			// v1 supported only charge transactions
			$settings['transaction_type'] = 'charge';

			// update to new settings
			update_option( $this->get_gateway_settings_name( self::CREDIT_CARD_GATEWAY_ID ), $settings );
		}

		// upgrade to 2.3.3
		if ( version_compare( $installed_version, '2.3.3', '<' ) ) {

			$settings = $this->get_gateway_settings( self::CREDIT_CARD_GATEWAY_ID );

			$settings['integration_country'] = $settings['integration'];
			unset( $settings['integration'] );

			// update to new settings
			update_option( $this->get_gateway_settings_name( self::CREDIT_CARD_GATEWAY_ID ), $settings );
		}
	}


} // end WC_Moneris


/**
 * Returns the One True Instance of Moneris
 *
 * @since 2.2.0
 * @return WC_Moneris
 */
function wc_moneris() {
	return WC_Moneris::instance();
}

// fire it up!
wc_moneris();

} // init_woocommerce_gateway_moneris
