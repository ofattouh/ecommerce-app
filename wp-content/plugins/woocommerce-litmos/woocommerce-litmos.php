<?php
/**
 * Plugin Name: WooCommerce Litmos
 * Plugin URI: http://www.woocommerce.com/products/litmos/
 * Description: Integrate Litmos with WooCommerce by exporting WooCommerce customers as Litmos Users and assigning them purchased courses
 * Author: SkyVerge
 * Author URI: http://www.woocommerce.com
 * Version: 1.7.0
 * Text Domain: woocommerce-litmos
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2013-2017, SkyVerge, Inc. (info@skyverge.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package     WC-Litmos
 * @author      SkyVerge
 * @Category    Integration
 * @copyright   Copyright (c) 2013-2017, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

// Required functions
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . 'woo-includes/woo-functions.php' );
}

// Plugin updates
woothemes_queue_update( plugin_basename( __FILE__ ), '41d5f57f5c2af60de1a739736fc7f5ce', '138934' );

// WC active Check
if ( ! is_woocommerce_active() ) {
	return;
}

// Required library class
if ( ! class_exists( 'SV_WC_Framework_Bootstrap' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . 'lib/skyverge/woocommerce/class-sv-wc-framework-bootstrap.php' );
}

SV_WC_Framework_Bootstrap::instance()->register_plugin( '4.6.0', __( 'WooCommerce Litmos', 'woocommerce-litmos' ), __FILE__, 'init_woocommerce_litmos', array(
	'minimum_wc_version'   => '2.5.5',
	'minimum_wp_version'   => '4.1',
	'backwards_compatible' => '4.4',
) );

function init_woocommerce_litmos() {

/**
 * Main Plugin Class
 *
 * @since 1.0
 */
class WC_Litmos extends SV_WC_Plugin {


	/** plugin version number */
	const VERSION = '1.7.0';

	/** @var WC_Litmos single instance of this plugin */
	protected static $instance;

	/** plugin id */
	const PLUGIN_ID = 'litmos';

	/** plugin text domain, DEPRECATED as of 1.5.0 */
	const TEXT_DOMAIN = 'woocommerce-litmos';

	/** @var \WC_Litmos_Admin class instance */
	protected $admin;

	/** @var \WC_Litmos_API class instance */
	private $api;


	/**
	 * Setup hooks
	 *
	 * @since 1.0
	 * @return \WC_Litmos
	 */
	public function __construct() {

		parent::__construct(
			self::PLUGIN_ID,
			self::VERSION,
			array(
				'text_domain' => 'woocommerce-litmos',
			)
		);

		$this->includes();

		// export when WC_Order::payment_complete() is called, for gateways that do not call WC_Order::payment_complete(), and when payment has previously failed
		if ( 'yes' == get_option( 'wc_litmos_auto_create_accounts' ) ) {

			$actions = array(
				'woocommerce_payment_complete',
				'woocommerce_order_status_on-hold_to_processing',
				'woocommerce_order_status_on-hold_to_completed',
				'woocommerce_order_status_failed_to_processing',
				'woocommerce_order_status_failed_to_completed',
				'woocommerce_order_status_pending_to_processing',
			);

			foreach ( $actions as $action ) {
				add_action( $action, array( $this, 'export_order' ) );
			}
		}

		// Require company field on Checkout if customer purchasing more than 1 user license
		if ( 'yes' === get_option( 'wc_litmos_require_company_field' ) ) {
			add_filter( 'woocommerce_billing_fields', array( $this, 'require_company_field_on_checkout' ) );
		}

		// Add 'My Courses' section to My Account page
		if ( SV_WC_Plugin_Compatibility::is_wc_version_lt_2_6() ) {
			add_action( 'woocommerce_after_my_account', array( $this, 'add_my_courses_section' ) );
		} else {
			add_action( 'woocommerce_account_dashboard', array( $this, 'add_my_courses_section' ), 25 );
		}
	}


	/**
	 * Loads required files
	 *
	 * @since 1.0
	 */
	public function includes() {

		require_once( $this->get_plugin_path() . '/includes/class-wc-litmos-handler.php' );

		if ( is_admin() ) {

			$this->admin_includes();
		}
	}


	/**
	 * Loads admin class
	 *
	 * @since 1.2
	 */
	public function admin_includes() {

		$this->admin = $this->load_class( '/includes/admin/class-wc-litmos-admin.php', 'WC_Litmos_Admin' );
	}


	/**
	 * Creates user accounts in Litmos & assigns courses/teams
	 *
	 * @since 1.0
	 * @param string $order_id ID of order to export
	 */
	public function export_order( $order_id ) {

		$order = wc_get_order( $order_id );

		if ( ! SV_WC_Order_Compatibility::get_meta( $order, '_wc_litmos_order_exported' ) )  {

			$handler = new WC_Litmos_Handler( $order_id );

			$handler->export();

			SV_WC_Order_Compatibility::update_meta_data( $order, '_wc_litmos_order_exported', 1 );
		}
	}


	/**
	 * Return admin class instance
	 *
	 * @since 1.6.0
	 * @return \WC_Litmos_Admin
	 */
	public function get_admin_instance() {
		return $this->admin;
	}


	/** Frontend methods ******************************************************/


	/**
	 * Requires the company field when checking out if >1 quantity of a Litmos-enabled product is in cart
	 * The Team name in Litmos is set to this
	 *
	 * @since 1.0
	 * @param array $address_fields WooCommerce billing address fields, from filter
	 * @return array address fields with company field set to required if necessary
	 */
	public function require_company_field_on_checkout( $address_fields ) {

		// require company field if true
		$require_flag = false;

		// loop thru each item in cart
		foreach ( WC()->cart->get_cart() as $item_key => $item ) {

			$product_id = $item['product_id'];

			// use variation ID if set
			if ( isset( $item['variation_id'] ) && $item['variation_id'] > 0 ) {
				$product_id = $item['variation_id'];
			}

			// Get Litmos course ID if it exists
			$course_id = get_post_meta( $product_id, '_wc_litmos_course_id', true );

			// only force for multi-instance course quantity
			if ( $course_id && $item['quantity'] > 1 ) {
				$require_flag = true;
			}
		}

		// if company field exists & require is true
		if ( isset( $address_fields['billing_company'] ) && $require_flag ) {
			$address_fields['billing_company']['required'] = true;
		}

		return $address_fields;
	}


	/**
	 * Adds the 'My Courses' section to the My Account page
	 *
	 * @since 1.0
	 */
	public function add_my_courses_section() {

		$litmos_user_id = get_user_meta( get_current_user_id(), '_wc_litmos_user_id', true );

		if ( ! $litmos_user_id ) {
			return;
		}

		// Get course listing
		$courses = get_transient( 'wc_litmos_' . $litmos_user_id . '_courses' );

		if ( empty( $courses ) ) {

			try {

				$courses = $this->get_api()->get_courses_assigned_to_user( $litmos_user_id );
			}

			catch( Exception $e ) {
				$this->log( $e->getMessage() );
				return;
			}

			// set 5 minute transient
			set_transient( 'wc_litmos_' . $litmos_user_id . '_courses', $courses, 60 * 5 );
		}

		// Get SSO link
		$sso_link = '';

		try {

			$sso_link = $this->get_api()->get_user_sso_link( $litmos_user_id );
		}

		catch ( Exception $e ) {
			$this->log( $e->getMessage() );
		}

		?>
		<h2 style="margin-top: 40px;"><?php esc_html_e( 'My Courses', 'woocommerce-litmos' ); ?></h2>
			<table>
				<thead><tr>
					<th><?php esc_html_e( 'Course Code', 'woocommerce-litmos' ); ?></th>
					<th><?php esc_html_e( 'Course Title', 'woocommerce-litmos' ); ?></th>
					<th><?php esc_html_e( 'Completion', 'woocommerce-litmos' ); ?></th>
					<th><?php esc_html_e( 'Date Completed', 'woocommerce-litmos' ); ?></th>
				</tr></thead>
					<tbody>
						<?php
						foreach( $courses as $course ) :
							?><tr>
								<td><?php echo esc_html( $course['Code'] ); ?></td>
								<td><?php echo esc_html( $course['Name'] ); ?></td>
								<td><?php echo esc_html( $course['PercentageComplete'] ); ?> &#37;</td>
								<td><?php echo esc_html( $course['DateCompleted'] ); ?></td>
							</tr>
						<?php endforeach;
						?>
					</tbody>
			</table>
			<p><a href="<?php echo esc_url( $sso_link ); ?>" target="_blank" class="button"><?php esc_html_e( 'Course Login', 'woocommerce-litmos' ); ?></a></p>

		<?php
	}


	/** Helper methods ******************************************************/


	/**
	 * Main Litmos Instance, ensures only one instance is/can be loaded
	 *
	 * @since 1.3.0
	 * @see wc_litmos()
	 * @return WC_Litmos
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/**
	 * Gets the WC Litmos API object
	 *
	 * @since 1.1
	 * @return \WC_Litmos_API
	 */
	public function get_api() {

		if ( is_object( $this->api ) ) {
			return $this->api;
		}

		require_once( $this->get_plugin_path() . '/includes/class-wc-litmos-api.php' );

		return $this->api = new WC_Litmos_API( get_option( 'wc_litmos_api_key' ) );
	}


	/**
	 * Returns the plugin name, localized
	 *
	 * @since 1.2
	 * @see SV_WC_Plugin::get_plugin_name()
	 * @return string the plugin name
	 */
	public function get_plugin_name() {
		return __( 'WooCommerce Litmos', 'woocommerce-litmos' );
	}


	/**
	 * Returns __FILE__
	 *
	 * @since 1.2
	 * @see SV_WC_Plugin::get_file()
	 * @return string the full path and filename of the plugin file
	 */
	protected function get_file() {
		return __FILE__;
	}


	/**
	 * Gets the URL to the settings page
	 *
	 * @since 1.2
	 * @see SV_WC_Plugin::is_plugin_settings()
	 * @param string $_ unused
	 * @return string URL to the settings page
	 */
	public function get_settings_url( $_ = '' ) {
		return admin_url( 'admin.php?page=wc-settings&tab=litmos' );
	}


	/**
	 * Gets the plugin documentation URL
	 *
	 * @since 1.4.0
	 * @see SV_WC_Plugin::get_documentation_url()
	 * @return string
	 */
	public function get_documentation_url() {
		return 'http://docs.woocommerce.com/document/litmos/';
	}


	/**
	 * Gets the plugin support URL
	 *
	 * @since 1.4.0
	 * @see SV_WC_Plugin::get_support_url()
	 * @return string
	 */
	public function get_support_url() {
		return 'https://woocommerce.com/my-account/tickets/';
	}


	/**
	 * Returns true if on the plugin settings page
	 *
	 * @since 1.2
	 * @see SV_WC_Plugin::is_plugin_settings()
	 * @return boolean true if on the settings page
	 */
	public function is_plugin_settings() {
		return isset( $_GET['page'] ) && 'wc-settings' == $_GET['page'] && isset( $_GET['tab'] ) && 'litmos' == $_GET['tab'];
	}


	/** Lifecycle methods ******************************************************/


	/**
	 * Run every time.  Used since the activation hook is not executed when updating a plugin
	 *
	 * @since 1.1.2
	 */
	protected function install() {

		$legacy_install = get_option( 'wc_litmos_is_installed' );

		if ( ! $legacy_install ) {

			require_once( $this->get_plugin_path() . '/includes/admin/class-wc-litmos-admin.php' );

			// install default settings, terms, etc
			foreach ( WC_Litmos_Admin::get_settings() as $setting ) {

				if ( isset( $setting['default'] ) ) {
					add_option( $setting['id'], $setting['default'] );
				}
			}

		} else {

			delete_option( 'wc_litmos_is_installed' );
		}
	}


} // end WC_Litmos


/**
 * Returns the One True Instance of Litmos
 *
 * @since 1.3.0
 * @return WC_Litmos
 */
function wc_litmos() {
	return WC_Litmos::instance();
}

// fire it up!
//wc_litmos();

$GLOBALS['wc_litmos'] = wc_litmos();

} // init_woocommerce_litmos()
