<?php // @codingStandardsIgnoreLine
/**
 * Plugin Name: WP Mail, Email Logs, Gmail SMTP, PHP Mailer - Mail Bank
 * Plugin URI: https://mail-bank.tech-banker.com/
 * Description: WordPress SMTP Plugin that sends outgoing email with SMTP or PHP Mailer. Supports Gmail SMTP, Sendgrid SMTP, oAuth, Email Logs and almost everything!
 * Author: Tech Banker
 * Author URI: https://mail-bank.tech-banker.com/
 * Version: 3.0.64
 * License: GPLv3
 * Text Domain: wp-mail-bank
 * Domain Path: /languages
 *
 * @package  wp-mail-bank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
/* Constant Declaration */
if ( ! defined( 'MAIL_BANK_FILE' ) ) {
	define( 'MAIL_BANK_FILE', plugin_basename( __FILE__ ) );
}
if ( ! defined( 'MAIL_BANK_DIR_PATH' ) ) {
	define( 'MAIL_BANK_DIR_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'MAIL_BANK_PLUGIN_DIRNAME' ) ) {
	define( 'MAIL_BANK_PLUGIN_DIRNAME', plugin_basename( dirname( __FILE__ ) ) );
}
if ( ! defined( 'MAIL_BANK_LOCAL_TIME' ) ) {
	define( 'MAIL_BANK_LOCAL_TIME', strtotime( date_i18n( 'Y-m-d H:i:s' ) ) );
}
if ( ! defined( 'MAIL_BANK_PLUGIN_DIR_URL' ) ) {
	define( 'MAIL_BANK_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'TECH_BANKER_BETA_URL' ) ) {
	define( 'TECH_BANKER_BETA_URL', 'https://mail-bank.tech-banker.com' );
}
if ( is_ssl() ) {
	if ( ! defined( 'TECH_BANKER_URL' ) ) {
		define( 'TECH_BANKER_URL', 'https://tech-banker.com' );
	}
} else {
	if ( ! defined( 'TECH_BANKER_URL' ) ) {
		define( 'TECH_BANKER_URL', 'http://tech-banker.com' );
	}
}
if ( ! defined( 'TECH_BANKER_STATS_URL' ) ) {
	define( 'TECH_BANKER_STATS_URL', 'http://stats.tech-banker-services.org' );
}
if ( ! defined( 'MAIL_BANK_VERSION_NUMBER' ) ) {
	define( 'MAIL_BANK_VERSION_NUMBER', '3.0.64' );
}


$memory_limit_mail_bank = intval( ini_get( 'memory_limit' ) );
if ( ! extension_loaded( 'suhosin' ) && $memory_limit_mail_bank < 512 ) {
	@ini_set( 'memory_limit', '1024M' ); // @codingStandardsIgnoreLine
}

/**
 * Function Name: install_script_for_mail_bank
 * Parameters: No
 * Description: This function is used to create Tables in Database.
 * Created On: 15-06-2016 09:52
 * Created By: Tech Banker Team
 */
function install_script_for_mail_bank() {
	global $wpdb;
	if ( is_multisite() ) {
		$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );// db call ok; no-cache ok.
		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id ); // @codingStandardsIgnoreLine
			$version = get_option( 'mail-bank-version-number' );
			if ( $version < '3.0.5' ) {
				if ( file_exists( MAIL_BANK_DIR_PATH . 'lib/class-db-helper-install-script-mail-bank.php' ) ) {
					include MAIL_BANK_DIR_PATH . 'lib/class-db-helper-install-script-mail-bank.php';
				}
			}
			restore_current_blog();
		}
	} else {
		$version = get_option( 'mail-bank-version-number' );
		if ( $version < '3.0.5' ) {
			if ( file_exists( MAIL_BANK_DIR_PATH . 'lib/class-db-helper-install-script-mail-bank.php' ) ) {
				include_once MAIL_BANK_DIR_PATH . 'lib/class-db-helper-install-script-mail-bank.php';
			}
		}
	}
}
/**
 * Function Name: check_user_roles_mail_bank
 * Parameters: Yes($user)
 * Description: This function is used for checking roles of different users.
 * Created On: 19-10-2016 03:40
 * Created By: Tech Banker Team
 */
function check_user_roles_mail_bank() {
	global $current_user;
	$user = $current_user ? new WP_User( $current_user ) : wp_get_current_user();
	return $user->roles ? $user->roles[0] : false;
}
/**
 * Function Name: mail_bank
 * Parameters: No
 * Description: This function is used to return Parent Table name with prefix.
 * Created On: 15-06-2016 10:44
 * Created By: Tech Banker Team
 */
function mail_bank() {
	global $wpdb;
	return $wpdb->prefix . 'mail_bank';
}
/**
 * Function Name: mail_bank_meta
 * Parameters: No
 * Description: This function is used to return Meta Table name with prefix.
 * Created On: 15-06-2016 10:44
 * Created By: Tech Banker Team
 */
function mail_bank_meta() {
	global $wpdb;
	return $wpdb->prefix . 'mail_bank_meta';
}
/**
 * Function Name: mail_bank_email_logs
 * Parameters: No
 * Description: This function is used to return Email Logs Table name with prefix.
 * Created On: 14-10-2016 11:48
 * Created By: Tech Banker Team
 */
function mail_bank_email_logs() {
	global $wpdb;
	return $wpdb->prefix . 'mail_bank_email_logs';
}

/**
 * Function Name: get_others_capabilities_mail_bank
 * Parameters: No
 * Description: This function is used to get all the roles available in WordPress
 * Created On: 21-10-2016 12:06
 * Created By: Tech Banker Team
 */
function get_others_capabilities_mail_bank() {
	$user_capabilities = array();
	if ( function_exists( 'get_editable_roles' ) ) {
		foreach ( get_editable_roles() as $role_name => $role_info ) {
			foreach ( $role_info['capabilities'] as $capability => $_ ) {
				if ( ! in_array( $capability, $user_capabilities, true ) ) {
					array_push( $user_capabilities, $capability );
				}
			}
		}
	} else {
		$user_capabilities = array(
			'manage_options',
			'edit_plugins',
			'edit_posts',
			'publish_posts',
			'publish_pages',
			'edit_pages',
			'read',
		);
	}

	return $user_capabilities;
}
/**
 * Function Name: mail_bank_action_links
 * Parameters: Yes
 * Description: This function is used to create link for Pro Editions.
 * Created On: 24-04-2017 12:20
 * Created By: Tech Banker Team
 *
 * @param string $plugin_link .
 */
function mail_bank_action_links( $plugin_link ) {
	$plugin_link[] = '<a href="https://mail-bank.tech-banker.com/" style="color: red; font-weight: bold;" target="_blank">Go Pro!</a>';
	return $plugin_link;
}
/**
 * Function Name: mail_bank_settings_link
 * Parameters: No
 * Description: This function is used to add settings link.
 * Created On: 09-08-2016 02:50
 * Created By: Tech Banker Team
 *
 * @param string $action .
 */
function mail_bank_settings_link( $action ) {
	global $wpdb, $user_role_permission;
	$settings_link = '<a href = "' . admin_url( 'admin.php?page=mb_email_configuration' ) . '"> Settings </a>';
	array_unshift( $action, $settings_link );
	return $action;
}
$version = get_option( 'mail-bank-version-number' );
if ( $version >= '3.0.5' ) {
	/**
	 * Function Name: get_users_capabilities_mail_bank
	 * Parameters: No
	 * Description: This function is used to get users capabilities.
	 * Created On: 21-10-2016 15:21
	 * Created By: Tech Banker Team
	 */
	function get_users_capabilities_mail_bank() {
		global $wpdb, $user_role_permission;
		$user_role_permission      = array();
		$capabilities              = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT meta_value FROM ' . $wpdb->prefix . 'mail_bank_meta WHERE meta_key = %s', 'roles_and_capabilities'
			)
		);// db call ok; no-cache ok.
		$core_roles                = array(
			'manage_options',
			'edit_plugins',
			'edit_posts',
			'publish_posts',
			'publish_pages',
			'edit_pages',
			'read',
		);
		$unserialized_capabilities = maybe_unserialize( $capabilities );
		$user_role_permission      = isset( $unserialized_capabilities['capabilities'] ) ? $unserialized_capabilities['capabilities'] : $core_roles;
		return $user_role_permission;
	}
	/**
	 * Function Name: add_dashboard_widgets_mail_bank
	 * Parameters: No
	 * Description: This function is used to add a widget to the dashboard.
	 * Created On: 24-08-2017 10:48
	 * Created By: Tech Banker Team
	 */
	function add_dashboard_widgets_mail_bank() {

		wp_add_dashboard_widget(
			'mb_dashboard_widget', // Widget slug.
			'Mail Bank Statistics', // Title.
			'dashboard_widget_function_mail_bank'// Display function.
		);
	}
	/**
	 * Function Name: dashboard_widget_function_mail_bank
	 * Parameters: No
	 * Description: This function is used to to output the contents of our Dashboard Widget.
	 * Created On: 24-08-2017 10:48
	 * Created By: Tech Banker Team
	 */
	function dashboard_widget_function_mail_bank() {
		global $wpdb;
		if ( file_exists( MAIL_BANK_DIR_PATH . 'lib/dashboard-widget.php' ) ) {
			include_once MAIL_BANK_DIR_PATH . 'lib/dashboard-widget.php';
		}
	}

	if ( is_admin() ) {
		/**
		 * Function Name: backend_js_css_for_mail_bank
		 * Description: This hook is used for calling css and js files for backend
		 * Created On: 26-09-2016 11:18
		 * Created by: Tech Banker Team
		 */
		function backend_js_css_for_mail_bank() {
			$pages_mail_bank = array(
				'mb_mail_bank_welcome_page',
				'mb_email_configuration',
				'mb_test_email',
				'mb_connectivity_test',
				'mb_email_logs',
				'mb_settings',
				'mb_roles_and_capabilities',
				'mb_system_information',
			);
			if ( in_array( isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '', $pages_mail_bank, true ) ) { // WPCS: CSRF ok,Input var okay.
				wp_enqueue_script( 'jquery' );
				wp_enqueue_script( 'jquery-ui-datepicker' );
				wp_enqueue_script( 'mail-bank-bootstrap.js', plugins_url( 'assets/global/plugins/custom/js/custom.js', __FILE__ ) );
				wp_enqueue_script( 'mail-bank-jquery.validate.js', plugins_url( 'assets/global/plugins/validation/jquery.validate.js', __FILE__ ) );
				wp_enqueue_script( 'mail-bank-jquery.datatables.js', plugins_url( 'assets/global/plugins/datatables/media/js/jquery.datatables.js', __FILE__ ) );
				wp_enqueue_script( 'mail-bank-jquery.fngetfilterednodes.js', plugins_url( 'assets/global/plugins/datatables/media/js/fngetfilterednodes.js', __FILE__ ) );
				wp_enqueue_script( 'mail-bank-toastr.js', plugins_url( 'assets/global/plugins/toastr/toastr.js', __FILE__ ) );

				wp_enqueue_style( 'mail-bank-simple-line-icons.css', plugins_url( 'assets/global/plugins/icons/icons.css', __FILE__ ) );
				wp_enqueue_style( 'mail-bank-components.css', plugins_url( 'assets/global/css/components.css', __FILE__ ) );
				wp_enqueue_style( 'mail-bank-custom.css', plugins_url( 'assets/admin/layout/css/mail-bank-custom.css', __FILE__ ) );
				if ( is_rtl() ) {
					wp_enqueue_style( 'mail-bank-bootstrap.css', plugins_url( 'assets/global/plugins/custom/css/custom-rtl.css', __FILE__ ) );
					wp_enqueue_style( 'mail-bank-layout.css', plugins_url( 'assets/admin/layout/css/layout-rtl.css', __FILE__ ) );
					wp_enqueue_style( 'mail-bank-tech-banker-custom.css', plugins_url( 'assets/admin/layout/css/tech-banker-custom-rtl.css', __FILE__ ) );
				} else {
					wp_enqueue_style( 'mail-bank-bootstrap.css', plugins_url( 'assets/global/plugins/custom/css/custom.css', __FILE__ ) );
					wp_enqueue_style( 'mail-bank-layout.css', plugins_url( 'assets/admin/layout/css/layout.css', __FILE__ ) );
					wp_enqueue_style( 'mail-bank-tech-banker-custom.css', plugins_url( 'assets/admin/layout/css/tech-banker-custom.css', __FILE__ ) );
				}
				wp_enqueue_style( 'mail-bank-default.css', plugins_url( 'assets/admin/layout/css/themes/default.css', __FILE__ ) );
				wp_enqueue_style( 'mail-bank-toastr.min.css', plugins_url( 'assets/global/plugins/toastr/toastr.css', __FILE__ ) );
				wp_enqueue_style( 'mail-bank-jquery-ui.css', plugins_url( 'assets/global/plugins/datepicker/jquery-ui.css', __FILE__ ), false, '2.0', false );
				wp_enqueue_style( 'mail-bank-datatables.foundation.css', plugins_url( 'assets/global/plugins/datatables/media/css/datatables.foundation.css', __FILE__ ) );
			}
		}
	}
	add_action( 'admin_enqueue_scripts', 'backend_js_css_for_mail_bank' );

	/**
	 * Function Name: helper_file_for_mail_bank
	 * Parameters: No
	 * Description: This function is used to create Class and Function to perform operations.
	 * Created On: 15-06-2016 09:52
	 * Created By: Tech Banker Team
	 */
	function helper_file_for_mail_bank() {
		global $wpdb, $user_role_permission;
		if ( file_exists( MAIL_BANK_DIR_PATH . 'lib/class-db-helper-mail-bank.php' ) ) {
			include_once MAIL_BANK_DIR_PATH . 'lib/class-db-helper-mail-bank.php';
		}
	}
	/**
	 * Function Name: sidebar_menu_for_mail_bank
	 * Parameters: No
	 * Description: This function is used to create Admin sidebar menus.
	 * Created On: 15-06-2016 09:52
	 * Created By: Tech Banker Team
	 */
	function sidebar_menu_for_mail_bank() {
		global $wpdb, $current_user, $user_role_permission;
		if ( file_exists( MAIL_BANK_DIR_PATH . 'includes/translations.php' ) ) {
			include MAIL_BANK_DIR_PATH . 'includes/translations.php';
		}
		if ( file_exists( MAIL_BANK_DIR_PATH . 'lib/sidebar-menu.php' ) ) {
			include_once MAIL_BANK_DIR_PATH . 'lib/sidebar-menu.php';
		}
	}
	/**
	 * Function Name: topbar_menu_for_mail_bank
	 * Parameters: No
	 * Description: This function is used for creating Top bar menu.
	 * Created On: 15-06-2016 10:44
	 * Created By: Tech Banker Team
	 */
	function topbar_menu_for_mail_bank() {
		global $wpdb, $current_user, $wp_admin_bar, $user_role_permission;
		$role_capabilities                        = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT meta_value FROM ' . $wpdb->prefix . 'mail_bank_meta WHERE meta_key = %s', 'roles_and_capabilities'
			)
		);// db call ok; no-cache ok.
		$roles_and_capabilities_unserialized_data = maybe_unserialize( $role_capabilities );
		$top_bar_menu                             = $roles_and_capabilities_unserialized_data['show_mail_bank_top_bar_menu'];

		if ( 'enable' === $top_bar_menu ) {
			if ( file_exists( MAIL_BANK_DIR_PATH . 'includes/translations.php' ) ) {
				include MAIL_BANK_DIR_PATH . 'includes/translations.php';
			}
			if ( file_exists( MAIL_BANK_DIR_PATH . 'lib/admin-bar-menu.php' ) ) {
				include_once MAIL_BANK_DIR_PATH . 'lib/admin-bar-menu.php';
			}
		}
	}
	/**
	 * Function Name: ajax_register_for_mail_bank
	 * Parameters: No
	 * Description: This function is used for register ajax.
	 * Created On: 15-06-2016 10:44
	 * Created By: Tech Banker Team
	 */
	function ajax_register_for_mail_bank() {
		global $wpdb, $user_role_permission;
		if ( file_exists( MAIL_BANK_DIR_PATH . 'includes/translations.php' ) ) {
			include MAIL_BANK_DIR_PATH . 'includes/translations.php';
		}
		if ( file_exists( MAIL_BANK_DIR_PATH . 'lib/action-library.php' ) ) {
			include_once MAIL_BANK_DIR_PATH . 'lib/action-library.php';
		}
	}
	/**
	 * Function Name: plugin_load_textdomain_mail_bank
	 * Parameters: No
	 * Description: This function is used to load the plugin's translated strings.
	 * Created On: 16-06-2016 09:47
	 * Created By: Tech Banker Team
	 */
	function plugin_load_textdomain_mail_bank() {
		load_plugin_textdomain( 'wp-mail-bank', false, MAIL_BANK_PLUGIN_DIRNAME . '/languages' );
	}
	/**
	 * Function Name: oauth_handling_mail_bank
	 * Parameters: No
	 * Description: This function is used to Manage Redirect.
	 * Created On: 11-08-2016 11:53
	 * Created By: Tech Banker Team
	 */
	function oauth_handling_mail_bank() {
		if ( is_admin() && is_user_logged_in() && ! isset( $_REQUEST['action'] ) ) { // WPCS: CSRF ok,Input var okay.
			if ( ( count( $_REQUEST ) <= 2 ) && isset( $_REQUEST['code'] ) ) { // WPCS: CSRF ok, Input var okay.
				if ( file_exists( MAIL_BANK_DIR_PATH . 'lib/callback.php' ) ) {
					include_once MAIL_BANK_DIR_PATH . 'lib/callback.php';
				}
			} elseif ( ( count( $_REQUEST ) <= 2 ) && isset( $_REQUEST['error'] ) ) { // WPCS: CSRF ok,Input var okay.
				$url = admin_url( 'admin.php?page=mb_email_configuration' );
				header( "location: $url" );
			}
		}
	}
	/**
	 * This function is used for checking test email.
	 *
	 * @param string $phpmailer .
	 */
	function email_configuration_mail_bank( $phpmailer ) {
		global $wpdb;
		$email_configuration_data       = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT meta_value FROM ' . $wpdb->prefix . 'mail_bank_meta WHERE meta_key = %s', 'email_configuration'
			)
		);// db call ok; no-cache ok.
		$email_configuration_data_array = maybe_unserialize( $email_configuration_data );

		$phpmailer->Mailer = 'mail'; // @codingStandardsIgnoreLine
		if ( 'override' === $email_configuration_data_array['sender_name_configuration'] ) {
			$phpmailer->FromName = stripcslashes( htmlspecialchars_decode( $email_configuration_data_array['sender_name'], ENT_QUOTES ) ); // @codingStandardsIgnoreLine
		}
		if ( 'override' === $email_configuration_data_array['from_email_configuration'] ) {
			$phpmailer->From = $email_configuration_data_array['sender_email']; // @codingStandardsIgnoreLine
		}
		if ( '' !== $email_configuration_data_array['reply_to'] ) {
			$phpmailer->clearReplyTos();
			$phpmailer->AddReplyTo( $email_configuration_data_array['reply_to'] );
		}
		if ( '' !== $email_configuration_data_array['cc'] ) {
			$phpmailer->clearCCs();
			$cc_address_array = explode( ',', $email_configuration_data_array['cc'] );
			foreach ( $cc_address_array as $cc_address ) {
				$phpmailer->AddCc( $cc_address );
			}
		}
		if ( '' !== $email_configuration_data_array['bcc'] ) {
			$phpmailer->clearBCCs();
			$bcc_address_array = explode( ',', $email_configuration_data_array['bcc'] );
			foreach ( $bcc_address_array as $bcc_address ) {
				$phpmailer->AddBcc( $bcc_address );
			}
		}
		if ( isset( $email_configuration_data_array['headers'] ) && '' !== $email_configuration_data_array['headers'] ) {
			$phpmailer->addCustomHeader( $email_configuration_data_array['headers'] );
		}
		$phpmailer->Sender = $email_configuration_data_array['email_address']; // @codingStandardsIgnoreLine
	}
	/**
	 * Function Name: admin_functions_for_mail_bank
	 * Parameters: No
	 * Description: This function is used for calling admin_init functions.
	 * Created On: 15-06-2016 10:44
	 * Created By: Tech Banker Team
	 */
	function admin_functions_for_mail_bank() {
		global $user_role_permission;
		install_script_for_mail_bank();
		helper_file_for_mail_bank();
	}
	/**
	 * Function Name: mailer_file_for_mail_bank
	 * Parameters: No
	 * Description: This function is used for including Mailer File.
	 * Created On: 30-06-2016 02:13
	 * Created By: Tech Banker Team
	 */
	function mailer_file_for_mail_bank() {
		if ( file_exists( MAIL_BANK_DIR_PATH . 'includes/class-mail-bank-auth-host.php' ) ) {
			include_once MAIL_BANK_DIR_PATH . 'includes/class-mail-bank-auth-host.php';
		}
	}
	/**
	 * Function Name: user_functions_for_mail_bank
	 * Parameters: No
	 * Description: This function is used to call on init hook.
	 * Created On: 16-06-2016 11:08
	 * Created By: Tech Banker Team
	 */
	function user_functions_for_mail_bank() {
		global $wpdb;
		$meta_values = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT meta_value FROM ' . $wpdb->prefix . 'mail_bank_meta WHERE meta_key IN(%s,%s)', 'settings', 'email_configuration'
			)
		);// db call ok; no-cache ok.

		$meta_data_array = array();
		foreach ( $meta_values as $value ) {
			$unserialize_data = maybe_unserialize( $value->meta_value );
			array_push( $meta_data_array, $unserialize_data );
		}
		mailer_file_for_mail_bank();
		if ( 'php_mail_function' === $meta_data_array[0]['mailer_type'] ) {
			add_action( 'phpmailer_init', 'email_configuration_mail_bank' );
		} else {
			if ( class_exists( 'Postman' ) ) {
				$class_methods = get_class_methods( 'Postman' );
				foreach ( $class_methods as $method_name ) {
					if ( '__construct' === $method_name ) {
						break;
					}
				}
			}
			apply_filters( 'wp_mail', 'wp_mail' );
		}
		oauth_handling_mail_bank();
	}
	/**
	 * Description: Override Mail Function here.
	 * Created On: 30-06-2016 02:13
	 * Created By: Tech Banker Team
	 */
	mailer_file_for_mail_bank();
	Mail_Bank_Auth_Host::override_wp_mail_function();

	/**
	 * This function is used to log email in case of phpmailer.
	 */
	function generate_logs_mail_bank() {
		global $wpdb;
		$email_configuration_data_array = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT meta_value FROM ' . $wpdb->prefix . 'mail_bank_meta WHERE meta_key=%s', 'email_configuration'
			)
		);// WPCS: db call ok; no-cache ok.
		$email_configuration_data       = maybe_unserialize( $email_configuration_data_array );

		if ( 'php_mail_function' === $email_configuration_data['mailer_type'] ) {
			if ( file_exists( MAIL_BANK_DIR_PATH . 'includes/class-mail-bank-email-logger.php' ) ) {
				include_once MAIL_BANK_DIR_PATH . 'includes/class-mail-bank-email-logger.php';
			}
			$email_logger = new Mail_Bank_Email_Logger();
			$email_logger->load_emails_mail_bank();
		}
	}

	/* hooks */

	/**
	 * This hook is used for calling the function of get_users_capabilities_mail_bank.
	 */

	add_action( 'plugins_loaded', 'get_users_capabilities_mail_bank' );

	/**
	 * This hook is used for calling the function of install script.
	 */

	register_activation_hook( __FILE__, 'install_script_for_mail_bank' );

	/**
	 * This hook contains all admin_init functions.
	 */
	add_action( 'admin_init', 'admin_functions_for_mail_bank' );

	/**
	 * This hook is used for calling the function of user functions.This hook contains all admin_init functions.
	 */

	add_action( 'init', 'user_functions_for_mail_bank' );

	/**
	 * This hook is used for calling the function of sidebar menu.
	*/

	add_action( 'admin_menu', 'sidebar_menu_for_mail_bank' );

	/**
	 * This hook is used for calling the function of sidebar menu in multisite case.
	*/

	add_action( 'network_admin_menu', 'sidebar_menu_for_mail_bank' );

	/*
	 * This hook is used for calling the function of topbar menu.
	*/

	add_action( 'admin_bar_menu', 'topbar_menu_for_mail_bank', 100 );

	/**
	 * This hook is used for calling the function of languages.
	*/

	add_action( 'init', 'plugin_load_textdomain_mail_bank' );

	/**
	 * This hook is used to register ajax.
	*/
	add_action( 'wp_ajax_mail_bank_action', 'ajax_register_for_mail_bank' );

	/*
	 * This hook is used to add widget on dashboard.
	*/
	add_action( 'wp_dashboard_setup', 'add_dashboard_widgets_mail_bank' );

	/*
	 * This hook is used for calling the function of settings link.
	*/
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'mail_bank_settings_link', 10, 2 );

	/**
	 * This hook is used to generate logs.
	 */
	add_action( 'plugins_loaded', 'generate_logs_mail_bank', 101 );

} else {
	/**
	 * This function add menu when version is not updated.
	 */
	function sidebar_menu_mail_bank_temp() {
		add_menu_page( 'Mail Bank', 'Mail Bank', 'read', 'mb_email_configuration', '', plugins_url( 'assets/global/img/icon.png', __FILE__ ) );
		add_submenu_page( 'Mail Bank', 'Mail Bank', '', 'read', 'mb_email_configuration', 'mb_email_configuration' );
	}

	/**
	 * This function used to include files.
	 */
	function mb_email_configuration() {
		global $wpdb;
		$user_role_permission = array(
			'manage_options',
			'edit_plugins',
			'edit_posts',
			'publish_posts',
			'publish_pages',
			'edit_pages',
		);
		if ( file_exists( MAIL_BANK_DIR_PATH . 'includes/translations.php' ) ) {
			include MAIL_BANK_DIR_PATH . 'includes/translations.php';
		}
		if ( file_exists( MAIL_BANK_DIR_PATH . 'includes/queries.php' ) ) {
			include_once MAIL_BANK_DIR_PATH . 'includes/queries.php';
		}
		if ( file_exists( MAIL_BANK_DIR_PATH . 'includes/header.php' ) ) {
			include_once MAIL_BANK_DIR_PATH . 'includes/header.php';
		}
		if ( file_exists( MAIL_BANK_DIR_PATH . 'includes/sidebar.php' ) ) {
			include_once MAIL_BANK_DIR_PATH . 'includes/sidebar.php';
		}
		if ( file_exists( MAIL_BANK_DIR_PATH . 'views/wizard/wizard.php' ) ) {
			include_once MAIL_BANK_DIR_PATH . 'views/wizard/wizard.php';
		}
		if ( file_exists( MAIL_BANK_DIR_PATH . 'includes/footer.php' ) ) {
			include_once MAIL_BANK_DIR_PATH . 'includes/footer.php';
		}
	}
	add_action( 'admin_menu', 'sidebar_menu_mail_bank_temp' );
	add_action( 'network_admin_menu', 'sidebar_menu_mail_bank_temp' );
}

/**
 * This hook is used for calling the function of install script.
 */
register_activation_hook( __FILE__, 'install_script_for_mail_bank' );

/**
 * This hook used for calling the function of install script.
 */
add_action( 'admin_init', 'install_script_for_mail_bank' );

/**
 * This hook is used for create link for premium Editions.
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'mail_bank_action_links' );

/**
 * This function is used to add option on plugin activation.
 */
function plugin_activate_wp_mail_bank() {
	add_option( 'wp_mail_bank_do_activation_redirect', true );
}
/**
 * This function is used to redirect to email setup.
 */
function wp_mail_bank_redirect() {
	if ( get_option( 'wp_mail_bank_do_activation_redirect', false ) ) {
		delete_option( 'wp_mail_bank_do_activation_redirect' );
		wp_safe_redirect( admin_url( 'admin.php?page=mb_email_configuration' ) );
		exit;
	}
}
register_activation_hook( __FILE__, 'plugin_activate_wp_mail_bank' );
add_action( 'admin_init', 'wp_mail_bank_redirect' );

/**
 * This function is used to create the object of admin notices.
 */
function mail_bank_admin_notice_class() {
	global $wpdb;
	/**
	 * This class is used to add admin notices.
	 */
	class Mail_Bank_Admin_Notices {
		/**
		 * The version of this plugin.
		 *
		 * @access   public
		 * @var      string    $config  .
		 */
		public $config;
		/**
		 * The version of this plugin.
		 *
		 * @access   public
		 * @var      integer    $notice_spam .
		 */
		public $notice_spam = 0;
		/**
		 * The version of this plugin.
		 *
		 * @access   public
		 * @var      integer    $notice_spam_max .
		 */
		public $notice_spam_max = 2;
		/**
		 * Initialize the class and set its properties.
		 *
		 * @since    1.0.0
		 * @param array $config .
		 */
		public function __construct( $config = array() ) {
			// Runs the admin notice ignore function incase a dismiss button has been clicked.
			add_action( 'admin_init', array( $this, 'mb_admin_notice_ignore' ) );
			// Runs the admin notice temp ignore function incase a temp dismiss link has been clicked.
			add_action( 'admin_init', array( $this, 'mb_admin_notice_temp_ignore' ) );
			add_action( 'admin_notices', array( $this, 'mb_display_admin_notices' ) );
		}
		/**
		 * Checks to ensure notices aren't disabled and the user has the correct permissions.
		 */
		public function mb_admin_notices() {
			$settings = get_option( 'mb_admin_notice' );
			if ( ! isset( $settings['disable_admin_notices'] ) || ( isset( $settings['disable_admin_notices'] ) && 0 === $settings['disable_admin_notices'] ) ) {
				if ( current_user_can( 'manage_options' ) ) {
					return true;
				}
			}
			return false;
		}
		/**
		 * Primary notice function that can be called from an outside function sending necessary variables.
		 *
		 * @param string $admin_notices .
		 */
		public function change_admin_notice_mail_bank( $admin_notices ) {
			// Check options.
			if ( ! $this->mb_admin_notices() ) {
				return false;
			}
			foreach ( $admin_notices as $slug => $admin_notice ) {
				// Call for spam protection.
				if ( $this->mb_anti_notice_spam() ) {
					return false;
				}

				// Check for proper page to display on.
				if ( isset( $admin_notices[ $slug ]['pages'] ) && is_array( $admin_notices[ $slug ]['pages'] ) ) {
					if ( ! $this->mb_admin_notice_pages( $admin_notices[ $slug ]['pages'] ) ) {
						return false;
					}
				}

				// Check for required fields.
				if ( ! $this->mb_required_fields( $admin_notices[ $slug ] ) ) {

					// Get the current date then set start date to either passed value or current date value and add interval.
					$current_date = current_time( 'm/d/Y' );
					$start        = ( isset( $admin_notices[ $slug ]['start'] ) ? $admin_notices[ $slug ]['start'] : $current_date );
					$start        = date( 'm/d/Y' );
					$interval     = ( isset( $admin_notices[ $slug ]['int'] ) ? $admin_notices[ $slug ]['int'] : 0 );
					$date         = strtotime( '+' . $interval . ' days', strtotime( $start ) );
					$start        = date( 'm/d/Y', $date );

					// This is the main notices storage option.
					$admin_notices_option = get_option( 'mb_admin_notice', array() );
					// Check if the message is already stored and if so just grab the key otherwise store the message and its associated date information.
					if ( ! array_key_exists( $slug, $admin_notices_option ) ) {
						$admin_notices_option[ $slug ]['start'] = date( 'm/d/Y' );
						$admin_notices_option[ $slug ]['int']   = $interval;
						update_option( 'mb_admin_notice', $admin_notices_option );
					}

					// Sanity check to ensure we have accurate information.
					// New date information will not overwrite old date information.
					$admin_display_check    = ( isset( $admin_notices_option[ $slug ]['dismissed'] ) ? $admin_notices_option[ $slug ]['dismissed'] : 0 );
					$admin_display_start    = ( isset( $admin_notices_option[ $slug ]['start'] ) ? $admin_notices_option[ $slug ]['start'] : $start );
					$admin_display_interval = ( isset( $admin_notices_option[ $slug ]['int'] ) ? $admin_notices_option[ $slug ]['int'] : $interval );
					$admin_display_msg      = ( isset( $admin_notices[ $slug ]['msg'] ) ? $admin_notices[ $slug ]['msg'] : '' );
					$admin_display_title    = ( isset( $admin_notices[ $slug ]['title'] ) ? $admin_notices[ $slug ]['title'] : '' );
					$admin_display_link     = ( isset( $admin_notices[ $slug ]['link'] ) ? $admin_notices[ $slug ]['link'] : '' );
					$output_css             = false;

					// Ensure the notice hasn't been hidden and that the current date is after the start date.
					if ( 0 === $admin_display_check && strtotime( $admin_display_start ) <= strtotime( $current_date ) ) {

						// Get remaining query string.
						$query_str = ( isset( $admin_notices[ $slug ]['later_link'] ) ? $admin_notices[ $slug ]['later_link'] : esc_url( add_query_arg( 'mb_admin_notice_ignore', $slug ) ) );
						if ( strpos( $slug, 'promo' ) === false ) {
							// Admin notice display output.
							echo '<div class="update-nag mb-admin-notice">
															 <div></div>
																<strong><p>' . $admin_display_title . '</p></strong>
																<strong><p style="font-size:14px !important">' . $admin_display_msg . '</p></strong>
																<strong><ul>' . $admin_display_link . '</ul></strong>
															</div>'; // WPCS: XSS ok.
						} else {
							echo '<div class="admin-notice-promo">';
							echo $admin_display_msg; // WPCS: XSS ok.
							echo '<ul class="notice-body-promo blue">
																		' . $admin_display_link . '
																	</ul>'; // WPCS: XSS ok.

							echo '</div>';
						}
						$this->notice_spam += 1;
						$output_css         = true;
					}
				}
			}
		}
		/**
		 * Spam protection check.
		 */
		public function mb_anti_notice_spam() {
			if ( $this->notice_spam >= $this->notice_spam_max ) {
				return true;
			}
			return false;
		}
		/**
		 * Ignore function that gets ran at admin init to ensure any messages that were dismissed get marked.
		 */
		public function mb_admin_notice_ignore() {
			// If user clicks to ignore the notice, update the option to not show it again.
			if ( isset( $_REQUEST['mb_admin_notice_ignore'] ) ) {// WPCS: CSRF ok,Input var okay.
				$admin_notices_option = get_option( 'mb_admin_notice', array() );
				$admin_notices_option[ wp_unslash( $_REQUEST['mb_admin_notice_ignore'] ) ]['dismissed'] = 1;// @codingStandardsIgnoreLine.
				update_option( 'mb_admin_notice', $admin_notices_option );
				$query_str = remove_query_arg( 'mb_admin_notice_ignore' );
				wp_safe_redirect( $query_str );
				exit;
			}
		}
		/**
		 * Temp Ignore function that gets ran at admin init to ensure any messages that were temp dismissed get their start date changed.
		 */
		public function mb_admin_notice_temp_ignore() {
			// If user clicks to temp ignore the notice, update the option to change the start date - default interval of 7 days.
			if ( isset( $_REQUEST['mb_admin_notice_temp_ignore'] ) ) { // WPCS: CSRF ok,Input var okay.
				$admin_notices_option = get_option( 'mb_admin_notice', array() );
				$current_date         = current_time( 'm/d/Y' );
				$interval             = ( isset( $_GET['int'] ) ? wp_unslash( $_GET['int'] ) : 7 ); // @codingStandardsIgnoreLine.
				$date                 = strtotime( '+' . $interval . ' days', strtotime( $current_date ) );
				$new_start            = date( 'm/d/Y', $date );

				$admin_notices_option[ wp_unslash( $_REQUEST['mb_admin_notice_temp_ignore'] ) ]['start']     = $new_start; // @codingStandardsIgnoreLine
				$admin_notices_option[ wp_unslash( $_REQUEST['mb_admin_notice_temp_ignore'] ) ]['dismissed'] = 0;// @codingStandardsIgnoreLine
				update_option( 'mb_admin_notice', $admin_notices_option );
				$query_str = remove_query_arg( array( 'mb_admin_notice_temp_ignore', 'int' ) );
				wp_safe_redirect( $query_str );
				exit;
			}
		}
		/**
		 * Check pages to show admin notice.
		 *
		 * @param string $pages .
		 */
		public function mb_admin_notice_pages( $pages ) {
			foreach ( $pages as $key => $page ) {
				if ( is_array( $page ) ) {
					if ( isset( $_REQUEST['page'] ) && $_REQUEST['page'] === $page[0] && isset( $_REQUEST['tab'] ) && $_REQUEST['tab'] === $page[1] ) {// WPCS: CSRF ok, Input var okay.
						return true;
					}
				} else {
					if ( 'all' === $page ) {
						return true;
					}
					if ( get_current_screen()->id === $page ) {
						return true;
					}
					if ( isset( $_REQUEST['page'] ) && $_REQUEST['page'] === $page ) {// WPCS: CSRF ok Input var okay.
						return true;
					}
				}
				return false;
			}
		}
		/**
		 * Required fields check.
		 *
		 * @param string $fields .
		 */
		public function mb_required_fields( $fields ) {
			if ( ! isset( $fields['msg'] ) || ( isset( $fields['msg'] ) && empty( $fields['msg'] ) ) ) {
				return true;
			}
			if ( ! isset( $fields['title'] ) || ( isset( $fields['title'] ) && empty( $fields['title'] ) ) ) {
				return true;
			}
			return false;
		}
		/**
		 * Display admin notice.
		 */
		public function mb_display_admin_notices() {
			$two_week_review_ignore     = add_query_arg( array( 'mb_admin_notice_ignore' => 'two_week_review' ) );
			$two_week_review_temp       = add_query_arg(
				array(
					'mb_admin_notice_temp_ignore' => 'two_week_review',
					'int'                         => 7,
				)
			);
			$mb_sure_love_to            = __( "Sure! I'd love to!", 'wp-mail-bank' );
			$mb_leave_review            = __( "I've already left a review", 'wp-mail-bank' );
			$mb_may_be_later            = __( 'Maybe Later', 'wp-mail-bank' );
			$notices['two_week_review'] = array(
				'title'      => __( 'Leave A Review For Mail Bank ?', 'wp-mail-bank' ),
				'msg'        => __( 'We love and care about you. Mail Bank Team is putting our maximum efforts to provide you the best functionalities.<br> We would really appreciate if you could spend a couple of seconds to give a Nice Review to the plugin for motivating us!', 'wp-mail-bank' ),
				'link'       => '<span class="dashicons dashicons-external mail-bank-admin-notice"></span><span class="mail-bank-admin-notice"><a href="https://wordpress.org/support/plugin/wp-mail-bank/reviews/?filter=5" target="_blank" class="mail-bank-admin-notice-link"> ' . $mb_sure_love_to . ' </a></span>
												<span class="dashicons dashicons-smiley mail-bank-admin-notice"></span><span class="mail-bank-admin-notice"><a href="' . $two_week_review_ignore . '" class="mail-bank-admin-notice-link">' . $mb_leave_review . '</a></span>
												<span class="dashicons dashicons-calendar-alt mail-bank-admin-notice"></span><span class="mail-bank-admin-notice"><a href="' . $two_week_review_temp . '" class="mail-bank-admin-notice-link"> ' . $mb_may_be_later . ' </a></span>',
				'later_link' => $two_week_review_temp,
				'int'        => 7,
			);

			$this->change_admin_notice_mail_bank( $notices );
		}
	}
	$plugin_info_mail_bank = new Mail_Bank_Admin_Notices();
}
add_action( 'init', 'mail_bank_admin_notice_class' );

/**
 * This function is used for executing the code on deactivation.
 */
function deactivation_function_for_wp_mail_bank() {
	delete_option( 'mail-bank-welcome-page' );
}
/**
 * This hook is used to sets the deactivation hook for a plugin.
 */
register_deactivation_hook( __FILE__, 'deactivation_function_for_wp_mail_bank' );

/**
 * This function is used to add dialof form on deactivation.
 */
function add_popup_on_deactivation_mail_bank() {
	global $wpdb;
	/**
	 * This class is used to add popup on deactivation.
	 */
	class Mail_Bank_Deactivation_Form { // @codingStandardsIgnoreLine
		/**
		 * Initialize the class and set its properties.
		 */
		function __construct() {
			add_action( 'wp_ajax_post_user_feedback_mail_bank', array( $this, 'post_user_feedback_mail_bank' ) );
			global $pagenow;
			if ( 'plugins.php' === $pagenow ) {
					add_action( 'admin_enqueue_scripts', array( $this, 'feedback_form_js_mail_bank' ) );
					add_action( 'admin_head', array( $this, 'add_form_layout_mail_bank' ) );
					add_action( 'admin_footer', array( $this, 'add_deactivation_dialog_form_mail_bank' ) );
			}
		}
		/**
		 * Enqueue js files.
		 */
		function feedback_form_js_mail_bank() {
			wp_enqueue_style( 'wp-jquery-ui-dialog' );
			wp_register_script( 'post-feedback', plugins_url( 'assets/global/plugins/deactivation/deactivate-popup.js', __FILE__ ), array( 'jquery', 'jquery-ui-core', 'jquery-ui-dialog' ), false, true );
			wp_localize_script( 'post-feedback', 'post_feedback', array( 'admin_ajax' => admin_url( 'admin-ajax.php' ) ) );
			wp_enqueue_script( 'post-feedback' );
		}
		/**
		 * This function is used to post user feedback.
		 */
		function post_user_feedback_mail_bank() {
			$mail_bank_deactivation_reason = isset( $_REQUEST['reason'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['reason'] ) ) : ''; // WPCS: CSRF ok, Input var okay.
			$plugin_info_wp_mail_bank      = new Plugin_Info_Wp_Mail_Bank(); // WPCS: CSRF ok.
			global $wp_version, $wpdb;
			$url              = TECH_BANKER_STATS_URL . '/wp-admin/admin-ajax.php';
			$type             = get_option( 'mail-bank-welcome-page' );
			$user_admin_email = get_option( 'mail-bank-admin-email' );
			$theme_details    = array();
			if ( $wp_version >= 3.4 ) {
				$active_theme                   = wp_get_theme();
				$theme_details['theme_name']    = strip_tags( $active_theme->Name ); // @codingStandardsIgnoreLine
				$theme_details['theme_version'] = strip_tags( $active_theme->Version ); // @codingStandardsIgnoreLine
				$theme_details['author_url']    = strip_tags( $active_theme->{'Author URI'} );
			}
			$plugin_stat_data                     = array();
			$plugin_stat_data['plugin_slug']      = 'wp-mail-bank';
			$plugin_stat_data['reason']           = $mail_bank_deactivation_reason;
			$plugin_stat_data['type']             = 'standard_edition';
			$plugin_stat_data['version_number']   = MAIL_BANK_VERSION_NUMBER;
			$plugin_stat_data['status']           = $type;
			$plugin_stat_data['event']            = 'de-activate';
			$plugin_stat_data['domain_url']       = site_url();
			$plugin_stat_data['wp_language']      = defined( 'WPLANG' ) && WPLANG ? WPLANG : get_locale();
			$plugin_stat_data['email']            = false !== $user_admin_email ? $user_admin_email : get_option( 'admin_email' );
			$plugin_stat_data['wp_version']       = $wp_version;
			$plugin_stat_data['php_version']      = esc_html( phpversion() );
			$plugin_stat_data['mysql_version']    = $wpdb->db_version();
			$plugin_stat_data['max_input_vars']   = ini_get( 'max_input_vars' );
			$plugin_stat_data['operating_system'] = PHP_OS . '  (' . PHP_INT_SIZE * 8 . ') BIT';
			$plugin_stat_data['php_memory_limit'] = ini_get( 'memory_limit' ) ? ini_get( 'memory_limit' ) : 'N/A';
			$plugin_stat_data['extensions']       = get_loaded_extensions();
			$plugin_stat_data['plugins']          = $plugin_info_wp_mail_bank->get_plugin_info_wp_mail_bank();
			$plugin_stat_data['themes']           = $theme_details;

			$response = wp_safe_remote_post(
				$url, array(
					'method'      => 'POST',
					'timeout'     => 45,
					'redirection' => 5,
					'httpversion' => '1.0',
					'blocking'    => true,
					'headers'     => array(),
					'body'        => array(
						'data'    => maybe_serialize( $plugin_stat_data ),
						'site_id' => false !== get_option( 'mb_tech_banker_site_id' ) ? get_option( 'mb_tech_banker_site_id' ) : '',
						'action'  => 'plugin_analysis_data',
					),
				)
			);

			if ( ! is_wp_error( $response ) ) {
				false !== $response['body'] ? update_option( 'mb_tech_banker_site_id', $response['body'] ) : '';
			}
				die( 'success' );
		}
		/**
		 * Add form layout.
		 */
		function add_form_layout_mail_bank() {
			?>
			<style type="text/css">
					.mail-bank-feedback-form .ui-dialog-buttonset {
						float: none !important;
					}
					#mail-bank-feedback-dialog-continue,#mail-bank-feedback-dialog-skip {
						float: right;
					}
					#mail-bank-feedback-cancel{
						float: left;
					}
					#mail-bank-feedback-content p {
						font-size: 1.1em;
					}
					.mail-bank-feedback-form .ui-icon {
						display: none;
					}
					#mail-bank-feedback-dialog-continue.mail-bank-ajax-progress .ui-icon {
						text-indent: inherit;
						display: inline-block !important;
						vertical-align: middle;
						animation: rotate 2s infinite linear;
					}
					#mail-bank-feedback-dialog-continue.mail-bank-ajax-progress .ui-button-text {
						vertical-align: middle;
					}
					@keyframes rotate {
					0%    { transform: rotate(0deg); }
					100%  { transform: rotate(360deg); }
					}
			</style>
			<?php
		}
		/**
		 * Add dialiog form on deactivation.
		 */
		function add_deactivation_dialog_form_mail_bank() {
			?>
			<div id="mail-bank-feedback-content" style="display: none;">
			<p style="margin-top:-5px"><?php echo esc_attr( __( 'We feel guilty when anyone stop using Mail Bank.', 'wp-mail-bank' ) ); ?></p>
						<p><?php echo esc_attr( _e( "If Mail Bank isn't working for you, others also may not.", 'wp-mail-bank' ) ); ?></p>
						<p><?php echo esc_attr( _e( 'We would love to hear your feedback about what went wrong.', 'wp-mail-bank' ) ); ?></p>
						<p><?php echo esc_attr( _e( 'We would like to help you in fixing the issue.', 'wp-mail-bank' ) ); ?></p>
						<p><?php echo esc_attr( _e( 'If you click Continue, some data would be sent to our servers for Compatiblity Testing Purposes.', 'wp-mail-bank' ) ); ?></p>
						<p><?php echo esc_attr( _e( 'If you Skip, no data would be shared with our servers.', 'wp-mail-bank' ) ); ?></p>
			<form>
				<?php wp_nonce_field(); ?>
				<ul id="mail-bank-deactivate-reasons">
					<li class="mail-bank-reason mail-bank-custom-input">
						<label>
							<span><input value="0" type="radio" name="reason" /></span>
							<span><?php echo esc_attr( _e( "The Plugin didn't work", 'wp-mail-bank' ) ); ?></span>
						</label>
					</li>
					<li class="mail-bank-reason mail-bank-custom-input">
						<label>
							<span><input value="1" type="radio" name="reason" /></span>
							<span><?php echo esc_attr( _e( 'I found a better Plugin', 'wp-mail-bank' ) ); ?></span>
						</label>
					</li>
					<li class="mail-bank-reason">
						<label>
							<span><input value="2" type="radio" name="reason" checked/></span>
							<span><?php echo esc_attr( _e( "It's a temporary deactivation. I'm just debugging an issue.", 'wp-mail-bank' ) ); ?></span>
						</label>
					</li>
					<li class="mail-bank-reason mail-bank-custom-input">
						<label>
							<span><input value="3" type="radio" name="reason" /></span>
							<span><a href="https://wordpress.org/support/plugin/wp-mail-bank" target="_blank"><?php echo esc_attr( _e( 'Open a Support Ticket for me.', 'wp-mail-bank' ) ); ?></a></span>
						</label>
					</li>
				</ul>
			</form>
		</div>
		<?php
		}
	}
	$plugin_deactivation_details = new Mail_Bank_Deactivation_Form();
}
add_action( 'plugins_loaded', 'add_popup_on_deactivation_mail_bank' );

/**
 * Insert id on deativation link.
 *
 * @param string $links .
 */
function insert_deactivate_link_id_mail_bank( $links ) {
	if ( ! is_multisite() ) {
		$links['deactivate'] = str_replace( '<a', '<a id="mail-bank-plugin-disable-link"', $links['deactivate'] );
	}
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'insert_deactivate_link_id_mail_bank', 10, 2 );
/**
 * This function is used to deactivate plugins.
 */
function deactivate_plugin_mail_bank() {
	if ( wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '', 'mb_deactivate_plugin_nonce' ) ) {
		deactivate_plugins( isset( $_GET['plugin'] ) ? wp_unslash( $_GET['plugin'] ) : '' );// WPCS: Input var ok, sanitization ok.
		wp_safe_redirect( wp_get_referer() );
		die();
	}
}
add_action( 'admin_post_mail_bank_deactivate_plugin', 'deactivate_plugin_mail_bank' );
/**
 * This function is used to display admin notice.
 */
function display_admin_notice_mail_bank() {
	$conflict_plugins_list = array(
		'WP Mail SMTP by WPForms'    => 'wp-mail-smtp/wp_mail_smtp.php',
		'Post SMTP Mailer/Email Log' => 'post-smtp/postman-smtp.php',
		'Easy WP SMTP'               => 'easy-wp-smtp/easy-wp-smtp.php',
		'Gmail SMTP'                 => 'gmail-smtp/main.php',
		'SMTP Mailer'                => 'smtp-mailer/main.php',
		'WP Email SMTP'              => 'wp-email-smtp/wp_email_smtp.php',
		'SMTP by BestWebSoft'        => 'bws-smtp/bws-smtp.php',
		'WP SendGrid SMTP'           => 'wp-sendgrid-smtp/wp-sendgrid-smtp.php',
		'Cimy Swift SMTP'            => 'cimy-swift-smtp/cimy_swift_smtp.php',
		'SAR Friendly SMTP'          => 'sar-friendly-smtp/sar-friendly-smtp.php',
		'WP Easy SMTP'               => 'wp-easy-smtp/wp-easy-smtp.php',
		'WP Gmail SMTP'              => 'wp-gmail-smtp/wp-gmail-smtp.php',
		'Email Log'                  => 'email-log/email-log.php',
		'SendGrid'                   => 'sendgrid-email-delivery-simplified/wpsendgrid.php',
		'Mailgun for WordPress'      => 'mailgun/mailgun.php',
	);
	$found                 = array();
	foreach ( $conflict_plugins_list as $name => $path ) {
		if ( is_plugin_active( $path ) ) {
				$found[] = array(
					'name' => $name,
					'path' => $path,
				);
		}
	}
	if ( count( $found ) ) {
		?>
		<div class="notice notice-error notice-warning" style="margin:5px 20px 15px 0px;">
			<img src="<?php echo esc_attr( plugins_url( 'assets/global/img/wizard-icon.png', __FILE__ ) ); ?>" height="60" width="60" style='float:left;margin:10px 10px 10px 0;'>
			<h3 style=''><?php echo esc_attr( _e( 'WP Mail Bank Compatibility Warning', 'wp-mail-bank' ) ); ?></h3>
			<p style='margin-top:-1%'><?php echo esc_attr( _e( 'The following plugins are not compatible with Mail Bank and may lead to unexpected results: ', 'wp-mail-bank' ) ); ?></p>
			<ul>
			<?php
			foreach ( $found as $plugin ) {
				?>
					<li style='line-height:28px;list-style:disc;margin-left:80px;'><strong><?php echo $plugin['name']; // WPCS: XSS ok. ?></strong>
						<a style='margin-left:10px' href='<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=mail_bank_deactivate_plugin&plugin=' . urlencode( $plugin['path'] ) ), 'mb_deactivate_plugin_nonce' ); // WPCS: XSS ok, @codingStandardsIgnoreLine. ?>'class='button button-primary'><?php echo esc_attr( _e( 'Deactivate', 'wp-mail-bank' ) ); ?></a>
					</li>
					<?php
			}
			?>
			</ul>
		</div>
		<?php
	}
}
/**
 * This hook is used to display admin notice.
 */
add_action( 'admin_notices', 'display_admin_notice_mail_bank' );
