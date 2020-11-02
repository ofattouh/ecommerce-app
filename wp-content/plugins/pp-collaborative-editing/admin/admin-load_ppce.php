<?php
add_action( 'init', '_ppce_default_privacy_workaround', 72 );  // late init following status registration, including moderation property for Edit Flow statuses
add_action( 'init', '_ppce_add_author_pages', 99 );

add_action( 'init', '_ppce_implicit_nav_menu_caps' );
add_action( 'current_screen', '_ppce_implicit_nav_menu_caps' );

add_action( 'pp_admin_handlers', '_ppce_admin_handlers' );
add_action( 'load-post.php', '_ppce_maybe_override_kses' );
add_action( 'check_admin_referer', '_pp_check_admin_referer' );
add_filter( 'pre_get_posts', '_ppce_pre_get_posts' );

add_filter( 'pp_has_group_cap', '_ppce_has_group_cap', 10, 4 );
add_filter( 'pp_can_set_exceptions', '_ppce_can_set_exceptions', 10, 4 );

add_filter( 'pp_user_can_admin_role', '_ppce_user_can_admin_role', 10, 6 );
add_filter( 'pp_admin_groups', '_ppce_admin_groups', 10, 2 );
 
global $pagenow;
if ( defined('RVY_VERSION') && defined('DOING_AJAX' ) && DOING_AJAX && ( 'async-upload.php' == $pagenow ) )
	require_once( PPCE_ABSPATH.'/revisionary-helper_ppce.php' );

add_action( '_pp_admin_ui', '_ppce_admin_filters' );	// fires after user load if is_admin(), not XML-RPC, and (with PP Core >= 2.1.38) not Ajax

add_action( 'pp_init', '_ppce_non_administrator_filters' );	// fires after user load
add_action( 'pp_init', '_ppce_admin_hardway_filters' );	

add_action( 'pp_update_item_exceptions', '_ppce_update_item_exceptions', 10, 3 );

function _ppce_admin_filters() {
	require_once( dirname(__FILE__).'/admin_ppce.php' );
	
	if ( ! pp_unfiltered() ) {
		require_once( dirname(__FILE__).'/admin-ui-non_administrator_ppce.php' );
	}
}

function _ppce_non_administrator_filters() {
	if ( ! pp_unfiltered() ) {
		require_once( dirname(__FILE__).'/admin-non_administrator_ppce.php' );
		
		require_once( dirname(__FILE__).'/cap-interceptor-admin_ppce.php' );
	}
}

function _ppce_admin_hardway_filters() {
	global $pagenow;
	
	if ( 'plugins.php' != $pagenow ) {
		global $pp_plugin_page;

		// low-level filtering for miscellaneous admin operations which are not well supported by the WP API
		$hardway_uris = array(
		'index.php',		'revision.php',			'admin.php?page=rvy-revisions',
		'post.php', 		'post-new.php', 		'edit.php', 
		'upload.php', 		'edit-comments.php', 	'edit-tags.php',	'term.php',
		'profile.php',		'admin-ajax.php',
		'link-manager.php', 'link-add.php',			'link.php',		 
		'edit-link-category.php', 	'edit-link-categories.php',
		'media-upload.php',	'nav-menus.php'
		);

		$hardway_uris = apply_filters( 'pp_admin_hardway_uris', $hardway_uris );

		if ( in_array( $pagenow, $hardway_uris ) || in_array( $pp_plugin_page, $hardway_uris ) /* || defined('XMLRPC_REQUEST') */ ) {
			if ( ! pp_unfiltered() )
				require_once( PPCE_ABSPATH.'/hardway/hardway-admin_non-administrator_ppce.php' );
		}
	}
}

function _ppce_implicit_nav_menu_caps () {
	global $current_user;
	
	if ( empty( $current_user->allcaps['manage_nav_menus'] ) && ( ! defined( 'PP_STRICT_MENU_CAPS' ) && ( ! empty( $current_user->allcaps['switch_themes'] ) || ! empty( $current_user->allcaps['edit_theme_options'] ) ) ) ) {
		$current_user->allcaps['manage_nav_menus'] = true;
	}
}

function _ppce_has_group_cap( $has_cap, $cap_name, $group_id, $group_type ) {
	require_once( dirname(__FILE__).'/admin-roles_ppce.php' );
	return PPCE_AdminRoles::flt_has_group_cap( $has_cap, $cap_name, $group_id, $group_type );
}

// returns supplemental group which can be edited or member-managed via supplemental permissions
function _ppce_admin_groups( $editable_group_ids, $operation ) {
	require_once( dirname(__FILE__).'/admin-roles_ppce.php' );
	return PPCE_AdminRoles::retrieve_admin_groups( $editable_group_ids, $operation );
}

function _ppce_can_set_exceptions( $can, $operation, $for_item_type, $args = array() ) {
	require_once( dirname(__FILE__).'/admin-roles_ppce.php' );
	return PPCE_AdminRoles::flt_can_set_exceptions( $can, $operation, $for_item_type, $args );
}

/*
if ( ! empty($_REQUEST['noheader']) && ! empty($_REQUEST['page']) && ( 'pp-add-author' == $_REQUEST['page'] ) ) {
	if ( ! defined( 'IFRAME_REQUEST' ) )
		define( 'IFRAME_REQUEST', true );
}
*/

// prevent default_privacy option from forcing a draft/pending post into private publishing
function _ppce_default_privacy_workaround() {
	global $pagenow;
	if ( ! empty($_POST) && in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) ) {
		require_once( dirname(__FILE__).'/post-edit_ppce.php' );
		PPCE_PostEditHelper::default_privacy_workaround();
	}
}

function _ppce_pre_get_posts( $query_obj ) {
	if ( defined('DOING_AJAX') && DOING_AJAX && ! empty($_REQUEST['action']) ) {
		switch( $_REQUEST['action'] ) {
			case 'find_posts':
				$query_obj->query_vars['suppress_filters'] = false;
				break;
		}
	}

}

function _pp_check_admin_referer( $referer ) {
	if ( in_array( $referer, array( 'bulk-posts', 'inlineeditnonce' ) ) ) {
		if ( 'bulk-posts' == $referer ) {
			if ( ! empty( $_REQUEST['action']) && ! is_numeric($_REQUEST['action']) )
				$action = $_REQUEST['action'];
			elseif ( ! empty( $_REQUEST['action2']) && ! is_numeric($_REQUEST['action2']) )
				$action = $_REQUEST['action2'];
			else
				$action = '';
				
			if ( 'edit' != $action )
				return;
		}
		
		if ( ppce_is_limited_editor() && ! current_user_can('pp_force_quick_edit') )
			wp_die( __('access denied', 'ppce') );
	}
}

function _ppce_maybe_override_kses() {
	if ( ! empty($_POST) && ! empty($_POST['action']) && ( 'editpost' == $_POST['action'] ) ) {
		if ( current_user_can( 'unfiltered_html' ) ) // initial core cap check in kses_init() is unfilterable
			kses_remove_filters();
	}
}

function _ppce_admin_handlers() {
	if ( ! empty($_POST) ) {
		global $pp_plugin_page;

		if ( 'pp-role-usage-edit' == $pp_plugin_page ) {
			$func = "require_once( '" . dirname(__FILE__) . "/pp-role-usage-edit-handler.php');";
			add_action( 'pp_user_init', create_function( '', $func ) );
		}
	}
}

function _ppce_add_author_pages() {
	if ( ! empty( $_REQUEST['add_member_page'] ) ) {
		require_once( dirname(__FILE__).'/bulk-edit_ppce.php' );
		PPCE_BulkEdit::add_author_pages( $_REQUEST );
	}
}

function ppce_get_posted_object_terms( $taxonomy ) {
	require_once( dirname(__FILE__).'/post-terms-save_ppce.php' );
	return PPCE_PostTermsSave::get_posted_object_terms( $taxonomy );
}

function ppce_is_limited_editor() {
	if ( pp_is_content_administrator() )
		return false;
	
	require_once( dirname(__FILE__).'/user-limitation_ppce.php' );
	return PPCE_UserLimitation::is_limited_editor();
}

function _ppce_user_can_admin_role( $can_admin, $role_name, $item_id, $src_name, $object_type, $user ) {
	require_once( dirname(__FILE__).'/permission_lib_ppce.php' );
	return _ppce_user_can_admin_role($role_name, $item_id, $src_name, $object_type, $user );
}

function _ppce_update_item_exceptions( $via_item_source, $item_id, $args ) {
	if ( 'term' == $via_item_source ) {
		PP_ItemSave::item_update_process_exceptions( 'term', 'term', $item_id, $args );
	}
}