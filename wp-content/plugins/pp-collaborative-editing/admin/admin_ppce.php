<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define ( 'PPCE_URLPATH', plugins_url( '', PPCE_FILE ) );

global $pagenow;
if ( 'nav-menus.php' == $pagenow ) {  // Administrators also need this, to add private posts to available items list
	global $ppce_nav_menu_query;
	require_once( dirname(__FILE__).'/filters-nav-menu-query_ppce.php' );
	$ppce_nav_menu_query = new PPCE_NavMenuQuery();
}

global $pagenow;
if ( ( 'index.php' == $pagenow ) && ! defined( 'USE_RVY_RIGHTNOW' )  )
	include_once( dirname(__FILE__).'/admin-dashboard_ppce.php' );

global $ppce_admin;
$ppce_admin = new PPCE_Admin();

class PPCE_Admin
{
	function __construct() {
		global $pagenow, $pp_plugin_page;
		
		wp_enqueue_style( 'ppce', PPCE_URLPATH . '/admin/css/ppce.css', array(), PPCE_VERSION );
		
		if ( 0 === strpos( $pp_plugin_page, 'pp-' ) )
			wp_enqueue_style( 'ppce-plugin-pages', PPCE_URLPATH . '/admin/css/ppce-plugin-pages.css', array(), PPCE_VERSION );
		
		add_action( 'admin_head', array( &$this, 'admin_head' ) );
		//add_action( 'admin_print_scripts', array( &$this, 'print_scripts' ) );
		add_action( 'admin_print_footer_scripts', array( &$this, 'quickpress_workaround' ), 99 );
		
		add_action( 'pp_permissions_menu', array( &$this, 'permissions_menu' ), 10, 2 );
		add_action( 'pp_menu_handler', array( &$this, 'menu_handler' ) );
		
		add_action( 'pp_term_edit_ui', array( &$this, 'term_edit_ui' ) );
		add_action( 'pp_post_edit_ui', array( &$this, 'post_edit_ui' ) );
		add_action( 'pp_post_listing_ui', array( &$this, 'post_listing_ui' ) );
		add_action( 'pp_options_ui', array(&$this, 'options_ui') );
		
		add_filter( 'pp_post_status_types', array(&$this, 'flt_status_links' ), 5 );
		
		if ( defined('RVY_VERSION') )
			require_once( PPCE_ABSPATH.'/revisionary-helper_ppce.php' );
		
		if ( 'users.php' == $pagenow )
			require_once( dirname(__FILE__).'/users-ui_ppce.php' );
	
		//add_filter( 'pp_posts_clauses_where', array( &$this, 'flt_posts_clauses_where' ), 10, 1 );
		
		/*
		if ( ! empty($_REQUEST['noheader']) ) {
			$pp_agents_ui = pp_init_agents_ui();
			$agents_ajax = $pp_agents_ui->init_ajax();
			$agents_ajax->register_ajax_js( 'user', 'member', '', 0 );
			
			global $pp_plugin_page;
			if ( 'pp-add-author' == $pp_plugin_page )
				add_action( 'admin_print_footer_scripts', array( &$this, 'force_footer_jquery' ), 1 );
			
			add_action( 'admin_init', array(&$this, 'register_iframe_scripts') );
		}
		*/
	}
	
	function menu_handler( $pp_page ) {
		$pp_page = $_GET['page'];

		if ( in_array( $pp_page, array( 'pp-role-usage', 'pp-role-usage-edit' ) ) ) {
			include_once( PPCE_ABSPATH . "/admin/{$pp_page}.php" );
		}
	}
	
	function permissions_menu( $pp_options_menu, $handler ) {
		if ( pp_get_option('advanced_options') )
			add_submenu_page($pp_options_menu, __('Role Usage', 'pp'), __('Role Usage', 'pp'), 'read', 'pp-role-usage', $handler );
		
		// satisfy WordPress' demand that all admin links be properly defined in menu
		global $pp_plugin_page, $pp_admin;
		if ( in_array( $pp_plugin_page, array( 'pp-role-usage-edit' ) ) ) {
			$permissions_menu = $pp_admin->get_menu( 'permits' );
			$titles = array( 'pp-role-usage-edit' => __('Edit Role Usage', 'pp') );
			add_submenu_page( $permissions_menu, $titles[$pp_plugin_page], '', 'read', $pp_plugin_page, $handler );
		}
	}
	
	function admin_head() {
		if ( ! empty($_REQUEST['page']) && ( 'pp-role-usage' == $_REQUEST['page'] ) ) {
			global $pp_role_usage_table;
			require_once( dirname(__FILE__).'/includes/class-pp-role-usage-list-table.php' );
			$pp_role_usage_table = new PPCE_Role_Usage_List_Table();
		}
	}
	
	/*
	function print_scripts() {
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
		wp_enqueue_script( 'ppce_misc', PPCE_URLPATH . "/admin/js/ppce{$suffix}.js", array('jquery'), PPCE_VERSION, true );
	}
	*/
	
	function quickpress_workaround() {  // need this for multiple qp entries by limited user
		if ( ! pp_unfiltered() ) :?>
			<?php 
			preg_match('/MSIE (.*?);/', $_SERVER['HTTP_USER_AGENT'], $matches);
			$ie_version = ( count($matches) ) ? $matches[1] : 0;
			if ( ! $ie_version || ( $ie_version >= 9 ) ) :?>
<script type="text/javascript">
/* <![CDATA[ */
if ( typeof wp == 'undefined' ) {
	var wp = new Object();
	wp.media = new Object();
	wp.media.view = new Object();
	wp.media.view.settings = new Object();
	wp.media.view.settings.post = new Object();

	wp.media.editor = new Object();
	wp.media.editor.remove = new Function();
	wp.media.editor.add = new Function();
}
/* ]]> */
</script>
			<?php endif;?>
		<?php endif;
	}
	
	function options_ui() {
		global $ppce_options;
		require_once(dirname(__FILE__).'/options_ppce.php');
		$ppce_options = new PPCE_Options();
	}
	
	function post_listing_ui() {
		require_once(dirname(__FILE__).'/post-listing-ui_ppce.php');
		
		if ( ! pp_unfiltered() ) {
			// listing filter used for role status indication in edit posts/pages
			require_once( dirname(__FILE__).'/post-listing-ui-non-administrator_ppce.php');
		}
	}
	
	function post_edit_ui() {
		global $ppce_filters_admin_item_ui;
		require_once(dirname(__FILE__).'/post-edit-ui_ppce.php');
		$ppce_filters_admin_item_ui = new PPCE_AdminFiltersObjectUI();
	}
	
	function term_edit_ui() {
		global $ppce_filters_admin_item_ui;
		require_once(dirname(__FILE__).'/term-edit-ui_ppce.php');
		$ppce_filters_admin_item_ui = new PPCE_AdminFiltersTermUI();
	}
	
	function flt_status_links( $links ) {
		if ( current_user_can( 'pp_define_post_status' ) || current_user_can( 'pp_define_moderation' ) )
			$links[] = (object) array( 'attrib_type' => 'moderation', 'url' => 'admin.php?page=pp-stati&attrib_type=moderation', 'label' => __('Moderation', 'pp') );

		return $links;
	}
	
	/*
	function register_iframe_scripts() {
		global $wp_scripts;
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
		wp_enqueue_script( 'pp_item_popup', PPCE_URLPATH . "/admin/js/pp_add_author_popup{$suffix}.js", array('jquery', 'jquery-form'), PPCE_VERSION, true );
		$arr = array( 'ajaxurl' => admin_url('') );
		wp_localize_script( 'pp_item_popup', 'ppItemPopup', $arr );
		$wp_scripts->in_footer []= 'pp_item_popup';  // otherwise it will not be printed in footer, as of WP 3.2.1
	}
	*/
	
	// temp workaround for difficulty enqueing jquery in footer for headerless iframe
	//function force_footer_jquery() {
	//	echo "<script type='text/javascript' src='" . site_url('') . "/wp-includes/js/jquery/jquery.js'></script>";
	//}
}
