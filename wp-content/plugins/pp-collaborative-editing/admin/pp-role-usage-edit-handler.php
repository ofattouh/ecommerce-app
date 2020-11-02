<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

//wp_reset_vars(array('action', 'redirect', 'agent_id', 'wp_http_referer'));
$action = ( isset($_REQUEST['action']) ) ? $_REQUEST['action'] : '';

$url = apply_filters( 'pp_role_usage_base_url', 'admin.php' );
$redirect = $err = false;

if ( ! current_user_can( 'pp_manage_settings' ) )
	wp_die( __( 'You are not permitted to do that.', 'pp' ) );

switch( $action ) {
	case 'update' :
		global $pp, $pp_role_defs;
		
		$role_name = sanitize_text_field($_REQUEST['role']);
		check_admin_referer( 'pp-update-role-usage_' . $role_name );
		
		// overall pattern role enable
		$role_usage = pp_get_option( 'role_usage' );
		if ( ! is_array($role_usage) ) {
			$role_usage = array_fill_keys( array_keys($pp_role_defs->pattern_roles), 'pattern' );
			$role_usage = array_merge( $role_usage, array_fill_keys( array_keys($pp_role_defs->direct_roles), 'direct' ) );
		}

		$role_usage[$role_name] = ( isset($_POST['pp_role_usage']) ) ? $_POST['pp_role_usage'] : 0;
		
		pp_update_option( 'role_usage', $role_usage );
		
		pp_refresh_options();
		do_action( 'ppc_registrations' );
		do_action( 'pp_apply_config_options' );
		
		pp_refresh_options();
		
		break;
} // end switch

//if ( is_wp_error( $retval ) ) {
//  global $pp_admin;
//	$pp_admin->errors = $retval;
//} else
if ( $redirect ) {
	if ( ! empty( $_REQUEST['wp_http_referer'] ) )
		$redirect = add_query_arg('wp_http_referer', urlencode($_REQUEST['wp_http_referer']), $redirect);
	
	$redirect = esc_url_raw( add_query_arg('update', 1, $redirect) );
	
	wp_redirect($redirect);
	exit;
}

