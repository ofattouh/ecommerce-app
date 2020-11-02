<?PHP
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class WCR_Admin_Settings
 *
 * Admin settings class
 *
 * @class       WCR_Admin_Settings
 * @version     1.0.0
 * @author      Grow Development
 */
class WCR_Admin_Settings {


	/**
	 * __construct function.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		$this->wcr_hooks();

	}


	/**
	 * Class hooks.
	 *
	 * All initial hooks used in this class.
	 *
	 * @since 1.0.0
	 */
	public function wcr_hooks() {

		// Add WC settings tab
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'wcr_woocommerce_settings_tab' ), 40 );

		// Settings page contents
		add_action( 'woocommerce_settings_tabs_student_data', array( $this, 'wcr_woocommerce_settings_page' ) );

		// Save settings page
		add_action( 'woocommerce_update_options_student_data', array( $this, 'wcr_woocommerce_update_options' ) );

	}


	/**
	 * Settings tab.
	 *
	 * Add a WooCommerce settings tab for the Student data settings page.
	 *
	 * @since 1.0.0
	 *
	 * @param $tabs
	 * @return array All WC settings tabs including newly added.
	 */
	public function wcr_woocommerce_settings_tab( $tabs ) {

		$tabs['student_data'] = __( 'Student data', 'woocommerce-checkout-registration' );

		return $tabs;

	}


	/**
	 * Settings page array.
	 *
	 * Get settings page fields array.
	 *
	 * @since 1.0.0
	 */
	public function wcr_woocommerce_get_settings() {

		$all_categories = get_terms( 'product_cat', array( 'hide_empty' => false ) );

		foreach ( $all_categories as $category ) :
			$categories[ $category->slug ] = $category->name;
		endforeach;


		$settings = apply_filters( 'woocommerce_studen_data_settings', array(

			array(
				'title' 	=> __( 'General', 'woocommerce-checkout-registration' ),
				'type' 		=> 'title',
				'desc' 		=> '',
				'id' 		=> 'student_data_general'
			),

			array(
				'title'   	=> __( 'Enable student data', 'woocommerce-checkout-registration' ),
				'desc' 	  	=> __( 'Use this setting to enable or disable all student data registration functions.', 'woocommerce-checkout-registration' ),
				'id' 	  	=> 'enable_student_data',
				'default' 	=> 'yes',
				'type' 	  	=> 'checkbox',
				'autoload'	=> false
			),

			array(
				'title' 	=> __( 'Categories', 'woocommerce-checkout-registration' ),
				'desc' 		=> __( 'Select for which categories you want to ', 'woocommerce-checkout-registration' ),
				'id' 		=> 'student_data_categories',
				'css' 		=> 'min-width:350px;',
				'type' 		=> 'multiselect',
				'class'		=> 'chosen_select',
				'desc_tip'	=>  true,
				'options'   => $categories,
			),

			array(
				'title'   	=> __( 'Debug', 'woocommerce-checkout-registration' ),
				'desc' 	  	=> __( 'Enable debugging, files will be saved in plugin/logs folder.', 'woocommerce-checkout-registration' ),
				'id' 	  	=> 'enable_wcr_debug',
				'default' 	=> 'no',
				'type' 	  	=> 'checkbox',
				'autoload'	=> false,
			),

			array(
				'type' 		=> 'sectionend',
				'id' 		=> 'student_data_general'
			),


			array(
				'title' 	=> __( 'Database Settings', 'woocommerce-checkout-registration' ),
				'type' 		=> 'title',
				'desc' 		=> __( 'Fill in the following settings to enable and set up remote database connection.', 'woocommerce-checkout-registration' ),
				'id' 		=> 'sql_settings'
			),

			array(
				'title' 	=> __( 'Server name', 'woocommerce-checkout-registration' ),
				'id' 		=> 'wcr_sql_name',
				'desc'		=> __( 'Server name, can be \'localhost\', domain.com or an IP address.', 'woocommerce-checkout-registration' ),
				'defuault' 	=> '',
				'type' 		=> 'text',
				'css' 		=> 'min-width:300px;',
				'autoload'  => false
			),

			array(
				'title' 	=> __( 'Database', 'woocommerce-checkout-registration' ),
				'id' 		=> 'wcr_sql_database',
				'desc'		=> __( 'Name of the database.', 'woocommerce-checkout-registration' ),
				'default' 	=> '',
				'type' 		=> 'text',
				'css' 		=> 'min-width:300px;',
				'autoload'  => false
			),

			array(
				'title' 	=> __( 'Table', 'woocommerce-checkout-registration' ),
				'id' 		=> 'wcr_sql_table',
				'desc'		=> __( 'Name of the table where the student data should be inserted.', 'woocommerce-checkout-registration' ),
				'default'	=> '',
				'type' 		=> 'text',
				'css' 		=> 'min-width:300px;',
				'autoload'  => false
			),

			array(
				'title'		=> __( 'Port', 'woocommerce-checkout-registration' ),
				'id' 		=> 'wcr_sql_port',
				'desc'		=> __('Port of the Database server.', 'woocommerce-checkout-registration' ),
				'default'	=> '3306',
				'type' 		=> 'text',
				'css' 		=> 'min-width:300px;',
				'autoload'  => false
			),

			array(
				'title'		=> __( 'User', 'woocommerce-checkout-registration' ),
				'id' 		=> 'wcr_sql_user',
				'desc' 		=> '',
				'default'	=> '',
				'type' 		=> 'text',
				'css' 		=> 'min-width:300px;',
				'autoload'  => false
			),


			array(
				'title' 	=> __( 'Password', 'woocommerce-checkout-registration' ),
				'id' 		=> 'wcr_sql_password',
				'desc' 		=> '',
				'default'	=> '',
				'type' 		=> 'password',
				'css' 		=> 'min-width:300px;',
				'autoload'  => false
			),
			
			array(
				'type' 		=> 'sectionend',
				'id' 		=> 'student_data_sql'
			),


		) );

		return $settings;

	}


	/**
	 * Settings page content.
	 *
	 * Output settings page content via WooCommerce output_fields() method.
	 *
	 * @since 1.0.0
	 */
	public function wcr_woocommerce_settings_page() {

		WC_Admin_Settings::output_fields( $this->wcr_woocommerce_get_settings() );

	}


	/**
	 * Save settings.
	 *
	 * Save settings based on WooCommerce save_fields() method.
	 *
	 * @since 1.0.0
	 */
	public function wcr_woocommerce_update_options() {

		WC_Admin_Settings::save_fields( $this->wcr_woocommerce_get_settings() );

	}


}

global $wcr_admin_setings;
$wcr_admin_settings = new WCR_Admin_Settings();