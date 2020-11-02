<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$action = ( isset($_REQUEST['action']) ) ? $_REQUEST['action'] : '';

$url = apply_filters( 'pp_condition_base_url', 'admin.php' );
$redirect = $err = false;

if ( ! isset($_REQUEST['pp_attribute']) )
	return;

$attribute = 'post_status';
$attrib_type = ( isset( $_REQUEST['attrib_type'] ) ) ? pp_sanitize_key($_REQUEST['attrib_type']) : '';

$pp_attributes = pps_init_attributes();

if ( ! current_user_can( "pp_define_{$attribute}" ) && ( ! $attrib_type || ! current_user_can( "pp_define_{$attrib_type}" ) ) )
	wp_die( __( 'You are not permitted to do that.', 'pps' ) );

switch( $action ) {
	case 'update' :
		$status = pp_sanitize_key($_REQUEST['status']);
		check_admin_referer( 'pp-update-condition_' . $status );
		
		require_once( dirname(__FILE__).'/status-save_ppp.php' );
		$return_array = PPP_StatusSave::save( $status );
		extract( array_intersect_key( $return_array, array_fill_keys( array( 'retval', 'redirect' ), true ) ) );
		break;
	case 'createcondition' :
		check_admin_referer( 'pp-create-condition', '_wpnonce_pp-create-condition' );
		
		$status = pp_sanitize_key( str_replace( ' ', '_', $_REQUEST['status_name'] ) );
		require_once( dirname(__FILE__).'/status-save_ppp.php' );
		$return_array = PPP_StatusSave::save( $status, true );
		extract( array_intersect_key( $return_array, array_fill_keys( array( 'retval', 'redirect' ), true ) ) );
		break;
} // end switch

if ( ! empty($retval) && is_wp_error( $retval ) ) {
	global $pp_admin;
	$pp_admin->errors = $retval;
} elseif ( $redirect ) {
	wp_redirect( esc_url_raw( add_query_arg('update', 1, $redirect) ) );
	exit;
}

