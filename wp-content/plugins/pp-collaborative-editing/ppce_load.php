<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// plugins already loaded at this point
if ( class_exists( 'Fork', false ) && ! defined( 'PP_DISABLE_FORKING_SUPPORT' ) ) {
	require_once( dirname(__FILE__).'/forking-helper_ppce.php'); // load this early to block rolecap addition if necessary
	$ppce_forking_helper = new PPCE_ForkingHelper();
}
	
add_action( 'init', '_ppce_init' );
add_filter( 'pp_meta_caps', '_ppce_meta_caps' );
add_filter( 'pp_exclude_arbitrary_caps', '_ppce_flt_exclude_arbitrary_caps' );

add_action( 'pre_get_posts', '_ppce_prevent_trash_suffixing' );
function _ppce_prevent_trash_suffixing( $wp_query ) {
	if ( strpos( $_SERVER['REQUEST_URI'], 'wp-admin/nav-menus.php' ) && ! empty($_POST) && ! empty( $_POST['action'] ) && ( 'update' == $_POST['action'] ) ) {
		$bt = debug_backtrace();
		
		foreach( $bt as $fcall ) {
			if ( ! empty( $fcall['function'] ) && 'wp_add_trashed_suffix_to_post_name_for_trashed_posts' == $fcall['function'] ) {
				$wp_query->query_vars['suppress_filters'] = 1;
				break;
			}
		}
	}
}

function _ppce_meta_caps( $meta_caps ) {
	return array_merge( $meta_caps, array( 'edit_post' => 'edit', 'edit_page' => 'edit', 'delete_post' => 'delete', 'delete_page' => 'delete' ) );
}

function _ppce_init() {
	$min_pp_version = '2.0-alpha';
	
	$pp_version = defined( 'PPC_VERSION' ) ? PPC_VERSION : 0;

	if ( version_compare( $pp_version, $min_pp_version, '<' ) ) {
		if ( is_admin() ) {
			if ( $pp_version ) { // bow out silently if PP core not activated
				$err_msg = sprintf( __('%1$s won&#39;t work until you upgrade Press Permit Lite to a newer version.', 'pp'), __('PP Collaborative Editing Pack', 'ppce') );
				
				$func_body = "echo '" 
				. '<div id="message" class="error fade" style="color: black"><p><strong>' . $err_msg . '</strong></p></div>'
				. "';";

				add_action('admin_notices', create_function('', $func_body) );
			}
		}
	} else {
		add_action( 'pp_pre_init', '_ppce_on_init' );

		add_action( 'pp_options', '_ppce_adjust_options' );
		add_filter( 'pp_role_caps', '_ppce_role_caps', 10, 2 );

		add_filter( 'pp_pattern_roles', '_ppce_pattern_roles' );
		add_action( 'pp_apply_config_options', '_ppce_set_role_usage' );
	
		add_action( 'pp_maint_filters', '_ppce_load_filters' ); // fires early if is_admin() - at bottom of PP_AdminUI constructor
		
		add_action( 'pp_query_interceptor', '_ppce_query_interceptor' );
		add_action( 'pp_cap_interceptor', '_ppce_cap_interceptor' );
		add_action( 'pp_hardway', '_ppce_hardway' );
		add_filter( 'pp_get_terms_is_term_admin', '_ppce_get_terms_is_term_admin', 10, 2 );
		add_filter( 'pp_get_item_condition', '_ppce_force_default_visibility', 10, 4 );  // if PPS is active, hook into its visibility forcing mechanism and UI (applied by PPS for specific pages)
		
		add_filter( 'pp_read_own_attachments', '_ppce_read_own_attachments', 10, 2 );
		add_filter( 'pp_ajax_edit_actions', '_ppce_ajax_edit_actions' );
	}
}

function _ppce_ajax_edit_actions( $actions ) {
	if ( ! pp_get_option( 'admin_others_attached_to_readable' ) )
		$actions[]= 'query-attachments';
	
	return $actions;
}

function _ppce_read_own_attachments( $read_own, $args = array() ) {
	if ( ! $read_own ) {
		global $current_user;
		return pp_get_option( 'own_attachments_always_editable' ) || ! empty( $current_user->allcaps['edit_own_attachments'] );
	}
	
	return $read_own;
}

function _ppce_force_default_visibility( $item_condition, $src_name, $attribute, $args = array() ) {
	if ( ( 'post' == $src_name ) && ( 'force_visibility' == $attribute ) && ! $item_condition && isset($args['post_type']) ) {  // allow any existing page-specific settings to override default forcing
		if ( empty($args['assign_for']) || ( 'item' == $args['assign_for'] ) ) {
			if ( $default_privacy = pp_get_type_option( 'default_privacy', $args['post_type'] ) ) {
				if ( $force = pp_get_type_option( 'force_default_privacy', $args['post_type'] ) ) {
					if ( pp_get_post_stati( array( 'name' => $default_privacy, 'post_type' => $args['post_type'] ) ) ) {  // only apply if status is currently registered and PP-enabled for the post type
						if ( ! empty($args['return_meta']) )
							return (object) array( 'force_status' => $default_privacy, 'force_basis' => 'default' );
						else
							return $default_privacy;
					}
				}
			}
		}
	}
	
	return $item_condition;		
}

function _ppce_on_init() {
	global $ppce_cap_helper;
	require_once( dirname(__FILE__).'/cap-helper_ppce.php' );
	$ppce_cap_helper = new PPCE_Cap_Helper();
	
	// --- version check ---
	$ver = get_option('ppce_version');
	
	if ( $ver && ! empty($ver['version']) ) {
		// These maintenance operations only apply when a previous version of PP was installed 
		if ( version_compare( PPCE_VERSION, $ver['version'], '!=') ) {
			require_once( dirname(__FILE__).'/admin/update_ppce.php');
			PPCE_Updated::version_updated( $ver['version'] );
			update_option( 'ppce_version', array( 'version' => PPCE_VERSION, 'db_version' => 0 ) );
		}
	} elseif ( ! $ver ) {
		// first execution after install
		//if ( ! get_option( 'ppce_added_role_caps_21beta' ) ) {
			ppce_populate_roles();
			update_option( 'ppce_version', array( 'version' => PPCE_VERSION, 'db_version' => 0 ) );
		//}
	}
	// --- end version check ---
	
	
	if ( defined('XMLRPC_REQUEST') ) {
		require_once( dirname(__FILE__).'/xmlrpc_ppce.php' );
	}
	
	if ( false !== strpos( $_SERVER['REQUEST_URI'], '/wp-json/wp/v2' ) ) {
		require_once( dirname(__FILE__).'/hardway/hardway-rest_ppce.php' );
	}
}

function _ppce_adjust_options( $options ) {
	if ( isset($options['pp_enabled_taxonomies']) )
		$options['pp_enabled_taxonomies'] = array_merge( maybe_unserialize($options['pp_enabled_taxonomies']), array( 'nav_menu' => '1' ) );

	if ( ! empty($options['pp_default_privacy']) ) {
		$disabled_types = ( class_exists( 'bbPress', false ) ) ? array( 'forum', 'topic', 'reply' ) : array();
		if ( $disabled_types = apply_filters( 'pp_disabled_default_privacy_types', $disabled_types ) ) {
			if ( $_default_privacy = maybe_unserialize($options['pp_default_privacy']) )
				$options['pp_default_privacy'] = array_diff_key( $_default_privacy, array_fill_keys( $disabled_types, true ) );
		}
	}

	return $options;
}

function _ppce_pattern_roles( $roles ) {
	return array_merge( $roles, array( 'contributor' => (object) array(), 'author' => (object) array(), 'editor' => (object) array() ) );
}

function _ppce_set_role_usage() {
	global $wp_roles, $pp_role_defs;

	if ( empty($wp_roles) )
		return;

	// don't apply custom Role Usage settings if advanced options are disabled
	$stored_usage = ( pp_get_option('advanced_options') ) ? pp_get_option( 'role_usage' ) : array();

	if ( $stored_usage ) {
		$enabled_pattern_roles = array_intersect( (array) $stored_usage, array( 'pattern' ) );
		$enabled_direct_roles = array_intersect( (array) $stored_usage, array( 'direct' ) );
		$no_usage_roles = array_intersect( (array) $stored_usage, array( '0', 0, false ) );
	} else {
		$enabled_pattern_roles = $enabled_direct_roles = array();
	}

	$pp_role_defs->pattern_roles = apply_filters( 'pp_default_pattern_roles', $pp_role_defs->pattern_roles );
	
	if ( $stored_usage /*|| $is_config */ ) {  // if no role usage is stored, use default pattern roles
		// Pattern Role Usage
		//if ( ! $is_config )
			$pp_role_defs->pattern_roles = array_diff_key( $pp_role_defs->pattern_roles, $enabled_direct_roles, $no_usage_roles );

		$additional_pattern_roles = array_diff_key( $enabled_pattern_roles, $pp_role_defs->pattern_roles );
		
		//if ( $is_config )
		//	$additional_pattern_roles = array_merge( $additional_pattern_roles, array_diff_key( $wp_roles->role_names, $enabled_pattern_roles, array( 'administrator' => true ) ) );

		foreach( array_keys($additional_pattern_roles) as $role_name ) {
			if ( isset( $wp_roles->role_names[$role_name] ) )
				$pp_role_defs->pattern_roles[$role_name] = (object) array( 'is_additional' => true, 'labels' => (object) array( 'name' => $wp_roles->role_names[$role_name], 'singular_name' => $wp_roles->role_names[$role_name] ) );
		}

		// Direct Role Usage
		$use_wp_roles = array_diff_key( $wp_roles->role_names, array( 'administrator' => true ) );
		
		//if ( $is_config ) {
		//	$use_wp_roles = $wp_roles->role_names;
		//} else {
			$use_wp_roles = array_intersect_key( $use_wp_roles, $enabled_direct_roles, $wp_roles->role_names );
		//}
		
		foreach( array_keys($use_wp_roles) as $role_name ) {
			$labels = ( isset( $pp_role_defs->pattern_roles[$role_name] ) ) ? $pp_role_defs->pattern_roles[$role_name]->labels : (object) array( 'name' => $wp_roles->role_names[$role_name], 'singular_name' => $wp_roles->role_names[$role_name] );
			$pp_role_defs->direct_roles[$role_name] = (object) compact( 'labels' );
		}
	}
}

function _ppce_role_caps( $caps, $role_name ) {
	$matches = array();
	preg_match("/pp_(.*)_manager/", $role_name, $matches);
	
	if ( ! empty( $matches[1] ) ) {
		$taxonomy = $matches[1];
		if ( $tx_obj = get_taxonomy( $taxonomy ) ) {
			$caps = array_diff( (array) $tx_obj->cap, array( 'edit_posts' ) );
		}
	}
	
	return $caps;
}

function _ppce_get_terms_is_term_admin( $is_term_admin, $taxonomy ) {
	global $pagenow;

	return $is_term_admin 
	|| in_array( $pagenow, array( 'edit-tags.php', 'term.php' ) ) 
	|| ( 'nav_menu' == $taxonomy && ( 'nav-menus.php' == $pagenow ) 
	|| ( ( 'admin-ajax.php' == $pagenow ) && ( ! empty($_REQUEST['action']) && in_array( $_REQUEST['action'], array( 'add-menu-item', 'menu-locations-save' ) ) ) )
	);
}

function _ppce_hardway() {  // get_pages filters
	require_once( dirname(__FILE__).'/hardway/hardway_ppce.php' );
}

function _ppce_cap_interceptor() {
	global $ppce_cap_interceptor;
	require_once( dirname(__FILE__).'/cap-interceptor_ppce.php' );
	$ppce_cap_interceptor = new PPCE_CapInterceptor();
	
	if ( defined( 'RVY_VERSION' ) ) {
		global $ppce_cap_interceptor_rvy;
		require_once( dirname(__FILE__).'/cap-interceptor-revisionary_ppce.php' );
		$ppce_cap_interceptor_rvy = new PPCE_Rvy_CapInterceptor();
	}
}

function _ppce_query_interceptor() {
	global $ppce_query_interceptor;
	require_once( dirname(__FILE__).'/query-interceptor_ppce.php' );
	$ppce_query_interceptor = new PPCE_QueryInterceptor();
}

function _ppce_load_filters() {
	global $ppce_admin_filters;
	require_once( dirname(__FILE__).'/admin/filters-admin_ppce.php' );
	$ppce_admin_filters = new PPCE_AdminFilters();
}

function _ppce_flt_exclude_arbitrary_caps( $caps ) {
	return array_merge( $caps, array( 'pp_force_quick_edit', 'edit_own_attachments', 'list_others_unattached_files', 'admin_others_unattached_files', 'pp_list_all_files' ) );
}

function ppce_populate_roles() {
	require_once( dirname(__FILE__).'/admin/update_ppce.php');
	PPCE_Updated::populate_roles();
}

function ppce_get_object_terms($object_ids, $taxonomy, $args = array()) {
	require_once( dirname(__FILE__).'/admin/post-terms-save_ppce.php' );
	return PPCE_PostTermsSave::get_object_terms($object_ids, $taxonomy, $args);
}

if ( ! function_exists( '_pp_' ) ) {	// introduced in Press Permit Core 2.3.17
	function _pp_( $string, $unused = '' ) {
		return __( $string );		
	}
}