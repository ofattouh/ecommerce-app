<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define ( 'PPS_URLPATH', plugins_url( '', PPS_FILE ) );

add_action( 'pp_conditions_loaded', '_pps_act_process_conditions', 49 );

add_action( 'pp_admin_handlers', '_pps_act_admin_handlers' );
add_action( 'check_ajax_referer', '_pps_act_inline_edit_status_helper' );
add_action( 'check_admin_referer', '_pps_act_bulk_edit_posts' );

add_action( 'pp_pre_init', '_pps_act_load_lang' );
add_action( 'admin_menu', '_pps_act_build_menu', 50, 1 );
add_action( 'pp_menu_handler', '_pps_act_menu_handler' );
add_action( 'pp_permissions_menu', '_pps_act_permissions_menu', 10, 2 );
add_action( 'admin_head', '_pps_act_admin_head' );

add_action( 'pp_condition_caption', '_pps_act_condition_caption', 10, 3 );
add_filter( 'pp_permission_status_ui', '_pps_flt_permission_status_ui', 10, 4 );

if ( defined('DOING_AJAX') && DOING_AJAX && ! defined( 'PP_AJAX_FINDPOSTS_STATI_OK' ) )
	add_action( 'wp_ajax_find_posts', '_pps_ajax_find_posts', 0 );

if ( defined( 'EDIT_FLOW_VERSION' ) && defined( 'PPCE_VERSION' ) )
	add_action( 'pp_user_init', '_pps_edit_flow_workarounds' );

add_filter( 'acf/location/rule_values/post_status', '_pps_acf_status_rule_options' );
	
require_once( dirname(__FILE__).'/admin_pps.php' );

function _pps_edit_flow_workarounds() {
	if ( ! empty($_POST) && ! empty($_POST['publish']) && ! empty($_POST['post_status']) ) {
		if ( $status_obj = get_post_status_object( $_POST['post_status'] ) ) {
			if ( ! $status_obj->public && ! $status_obj->private ) {
				global $edit_flow;
				remove_action( 'admin_init', array( $edit_flow->modules->custom_status, 'check_timestamp_on_publish' ) );  // disable EF workaround if we are not really publishing
				
				// but remove_action call fails as of WP 3.5.1, so do it manually
				global $wp_filter;
				
				if ( isset($wp_filter['admin_init']['10']) ) {
					foreach( array_keys($wp_filter['admin_init']['10']) as $key ) {
						if ( strpos( $key, 'check_timestamp_on_publish' ) ) {
							global $merged_filters;
							
							unset( $wp_filter['admin_init']['10'][$key] );
							
							if ( empty($wp_filter['admin_init']['10']) )
								unset($wp_filter['admin_init']['10']);

							unset($merged_filters['admin_init']);
						}
					}
				}
			}
		}
	}
}

function _pps_ajax_find_posts() {
	require_once( dirname(__FILE__).'/wp-ajax-support_pps.php' );
	PPS_WP_Ajax::wp_ajax_find_posts();
}

function _pps_act_process_conditions() {
	global $wp_post_statuses, $pagenow;
	
	_pps_reinstate_draft_pending();
	add_action( 'wp_loaded', '_pps_reinstate_draft_pending' );
	
	// This is necessary to make these statuses available in the Permissions > Post Statuses list. But actual treatment as a moderation status is determined by stored option and applied by PPCE before pps_register_condition() call
	$wp_post_statuses['pending']->moderation = true;
	$wp_post_statuses['future']->moderation = true;
	
	foreach( array_keys($wp_post_statuses) as $status ) {
		if ( empty( $wp_post_statuses[$status]->moderation ) )
			$wp_post_statuses[$status]->moderation = false;
	}

	if ( in_array( $pagenow, array( 'edit.php', 'post.php', 'post-new.php' ) ) || pp_is_ajax( 'inline-save' ) ) {
		require_once( dirname(__FILE__).'/post-ui_pps.php' );
		PPS_AdminPostUI::set_status_labels();
	}
}

function _pps_reinstate_draft_pending() {
	global $wp_post_statuses;
	
	// Cannot currently deal with Edit Flow's deletion of Draft and Pending statuses
	if ( empty($wp_post_statuses['draft']) || empty($wp_post_statuses['draft']->label) ) {
		register_post_status( 'draft', array(
			'label'       => _x( 'Draft', 'post' ),
			'protected'   => true,
			'_builtin'    => true, /* internal use only. */
			'label_count' => _n_noop( 'Draft <span class="count">(%s)</span>', 'Drafts <span class="count">(%s)</span>' ),
		) );
		
		$wp_post_statuses['draft']->labels->save_as = esc_attr( _pp_('Save Draft') );
	}

	if ( empty($wp_post_statuses['pending']) || empty($wp_post_statuses['pending']->label) ) {
		register_post_status( 'pending', array(
			'label'       => _x( 'Pending', 'post' ),
			'protected'   => true,
			'_builtin'    => true, /* internal use only. */
			'label_count' => _n_noop( 'Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>' ),
		) );
	}
}

function _pps_act_admin_handlers() {
	require_once( dirname(__FILE__).'/admin-handlers_pps.php' );
}

function _pps_act_inline_edit_status_helper( $referer ) {
	if ( 'inlineeditnonce' == $referer ) {
		if ( ! empty( $_POST['keep_custom_privacy'] ) ) {
			$_POST['_status'] = pp_sanitize_key($_POST['keep_custom_privacy']);
		}
	}
}

function _pps_act_bulk_edit_posts( $referer ) {
	if ( 'bulk-posts' == $referer ) {
		if ( pp_is_content_administrator() || current_user_can('pp_force_quick_edit') ) {
			require_once( dirname(__FILE__).'/bulk-edit_pps.php' );
			PP_BulkEdit::bulk_edit_posts( $_REQUEST );
		}
	}
}

function _pps_act_load_lang() {
	load_plugin_textdomain( 'pps', false, PPS_FOLDER . '/languages' );
}

function _pps_act_build_menu() {
	// satisfy WordPress' demand that all admin links be properly defined in menu
	global $pp_plugin_page;
	if ( in_array( $pp_plugin_page, array( 'pp-status-new', 'pp-status-edit' ) ) ) {
		global $pp_admin;
		
		$handler = array( $pp_admin, 'menu_handler' );
		$pp_cred_menu = $pp_admin->get_menu( 'permits' );

		$titles = array( 'pp-status-new' => __('Add New Status', 'pps'),
						 'pp-status-edit' => __('Edit Status', 'pps'),
						);
		add_submenu_page( $pp_cred_menu, $titles[$pp_plugin_page], '', 'read', $pp_plugin_page, $handler );
	}
}

function _pps_act_menu_handler( $pp_page ) {
	if ( in_array( $pp_page, array( 'pp-stati', 'pp-status-edit', 'pp-status-new' ) ) ) {
		include_once( dirname(__FILE__) . "/{$pp_page}.php" );
	}
}

function _pps_act_permissions_menu( $options_menu, $handler ) {
	add_submenu_page($options_menu, __('Post Statuses', 'pps'), __('Post Statuses', 'pps'), 'read', 'pp-stati', $handler );
}

function _pps_act_admin_head() {
	global $pp_plugin_page;

	if ( 'pp-stati' == $pp_plugin_page ) {
		if ( isset( $_REQUEST['attrib_type'] ) ) {
			$attrib_type = pp_sanitize_key($_REQUEST['attrib_type']);
		} else {
			if ( $links = apply_filters( 'pp_post_status_types', array() ) ) {
				$link = reset( $links );
				$attrib_type = $link->attrib_type;
			}
		}
		
		global $pp_attributes_list_table;
		require_once( dirname(__FILE__).'/includes/class-pp-stati-list-table.php' );
		$pp_attributes_list_table = new PP_Attributes_List_Table( $attrib_type );
	}
}

function _pps_act_condition_caption( $cond_caption, $attrib, $cond ) {
	$pp_attributes = pps_init_attributes();

	if ( isset( $pp_attributes->attributes[ $attrib ]->conditions[ $cond ] ) ) {
		$cond_caption = $pp_attributes->attributes[ $attrib ]->conditions[ $cond ]->label;
	} elseif ( 'post_status' == $attrib ) {
		if ( $status_obj = get_post_status_object($cond) )
			$cond_caption = $status_obj->label;
	}
	
	return $cond_caption;
}

function _pps_flt_permission_status_ui( $html, $object_type, $type_caps, $role_name = '' ) {
	require_once( dirname(__FILE__).'/permits-ui_pps.php' );
	return PP_PermitsUI::permission_status_ui( $html, $object_type, $type_caps, $role_name );
}

function _pps_acf_status_rule_options( $statuses ) {
	$stati = get_post_stati( array( 'internal' => false ), 'object' );
	foreach( $stati as $status => $status_obj ) {
		if ( ! isset($statuses[$status]) )
			$statuses[$status] = $status_obj->label;
	}

	return $statuses;
}
