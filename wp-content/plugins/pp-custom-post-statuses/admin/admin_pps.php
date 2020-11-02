<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_filter( 'display_post_states', '_pps_flt_display_post_states' );

add_action( 'pp_post_admin', '_pps_act_post_admin_ui' );
add_action( 'pp_post_listing_ui', '_pps_act_post_listing_ui' );
add_action( 'pp_post_edit_ui', '_pps_act_post_edit_ui' );
add_filter( 'pp_post_status_types', '_pps_flt_status_links', 1 );
add_filter( 'pp_exceptions_status_ui', '_pps_flt_exceptions_status_ui', 8, 3 );
add_action( 'pp_options_ui', '_pps_options_ui' );

add_action( 'admin_enqueue_scripts', '_pps_scripts' );
add_action( 'pp_admin_ui', '_ef_ppce_dependency' );

//_pps_scripts();

function _pps_scripts() {
	global $pp_plugin_page, $pagenow;

	$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
	
	if ( 0 === strpos( $pp_plugin_page, 'pp-' ) )
		wp_enqueue_style( 'pps', PPS_URLPATH . '/admin/css/pps-plugin-pages.css', array(), PPS_VERSION );
	elseif( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) ) {
		wp_enqueue_style( 'post-edit_pps', PPS_URLPATH . '/admin/css/post-edit_pps.css', array(), PPS_VERSION );
		wp_enqueue_style( 'post-edit-ie_pps', PPS_URLPATH . '/admin/css/post-edit-ie_pps.css', array(), PPS_VERSION );
	}
	
	wp_enqueue_script( 'pps_misc', PPS_URLPATH . "/admin/js/pps{$suffix}.js", array('jquery'), PPS_VERSION, false );

	if ( in_array( $pp_plugin_page, array( 'pp-status-edit', 'pp-status-new' ) ) ) {
		wp_enqueue_script( 'pp_status_edit', PPS_URLPATH . "/admin/js/pps_status-edit{$suffix}.js", array('jquery', 'jquery-form'), PPS_VERSION, true );
	}
	
	if ( 'pp-stati' == $pp_plugin_page ) {
		wp_enqueue_script( 'pp_stati', PPS_URLPATH . "/admin/js/pps_stati{$suffix}.js", array('jquery', 'jquery-form'), PPS_VERSION, true );
	}
}

function _ef_ppce_dependency() {
	if ( defined( 'EDIT_FLOW_VERSION' ) && ! defined( 'PPCE_VERSION' ) )
		ppc_notice( __( 'Edit Flow integration also requires the PP Collaborative Editing extension', 'pps' ) );
}

function _pps_flt_display_post_states( $stati ) {
	require_once( dirname(__FILE__).'/post-listing-ui_pps.php' );
	
	global $pps_edit_listing_filters;
	return $pps_edit_listing_filters->flt_display_post_states( $stati );
}

function _pps_act_post_admin_ui() {
	global $pps_filters_admin_ui_post;
	require_once( dirname(__FILE__).'/post-ui_pps.php' );
	$pps_filters_admin_ui_post = new PPS_AdminPostUI();
}

function _pps_act_post_listing_ui() {
	require_once( dirname(__FILE__).'/post-listing-ui_pps.php' );
}

function _pps_act_post_edit_ui() {
	if ( in_array( pp_find_post_type(), array( 'forum', 'topic', 'reply' ) ) ) // future @todo: support bbp custom privacy as applicable
		return;

	require_once( dirname(__FILE__).'/post-edit-ui_pps.php' );
}

function _pps_flt_status_links( $links ) {
	if ( current_user_can( 'pp_define_post_status' ) || current_user_can( 'pp_define_privacy' ) )
		$links[] = (object) array( 'attrib_type' => 'private', 'url' => 'admin.php?page=pp-stati&amp;attrib_type=private', 'label' => __('Privacy', 'pp') );

	return $links;
}

function _pps_options_ui() {
	global $pps_options;
	require_once(dirname(__FILE__).'/options_pps.php');
	$pps_options = new PPS_Options();
}

function _pps_flt_exceptions_status_ui( $html, $for_type, $args = array() ) {
	require_once( dirname(__FILE__).'/ajax-ui_pps.php' );
	return PPS_Permissions_Ajax::flt_exceptions_status_ui( $html, $for_type, $args );
}
