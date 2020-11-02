<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once( dirname(__FILE__).'/pp-status-helper.php' );

PP_Conditions_Handler::handle_request();

class PP_Conditions_Handler {
	public static function handle_request() {
		global $pp_admin;
		
		$url = $referer = $redirect = $update = '';
		PP_Conditions_Helper::get_url_properties( $url, $referer, $redirect );
		
		$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
		if ( ! $action )
			$action = isset( $_REQUEST['pp_action'] ) ? $_REQUEST['pp_action'] : '';
			
		$attribute = 'post_status';
		$attrib_type = pp_sanitize_key($_REQUEST['attrib_type']);
		
		switch ( $action ) {

		case 'dodelete':
			check_admin_referer('delete-conditions');

			if ( ! current_user_can( 'pp_define_post_status' ) && ( ! $attrib_type || ! current_user_can( "pp_define_{$attrib_type}" ) ) )
				wp_die( __( 'You are not permitted to do that.', 'pps' ) );
			
			if ( empty($_REQUEST['pp_conditions']) && empty($_REQUEST['status']) ) {
				wp_redirect($redirect);
				exit();
			}

			if ( empty($_REQUEST['pp_conditions']) )
				$conds = array($_REQUEST['status']);
			else
				$conds = (array) $_REQUEST['pp_conditions'];
			
			$update = 'del';
			$delete_conds = array();

			foreach ( (array) $conds as $cond) {
				$delete_conds[$cond] = true;
			}

			if ( ! $delete_conds )
				wp_die( __( 'You can&#8217;t delete that status.', 'pps' ) );

			$conds = (array) get_option( "pp_custom_conditions_{$attribute}" );

			$conds = array_diff_key( $conds, $delete_conds );
	
			update_option( "pp_custom_conditions_{$attribute}", $conds );

			// Edit Flow integration
			if ( taxonomy_exists('post_status') && ! defined('PP_DISABLE_EF_STATUS_SYNC') ) {
				foreach( array_keys($delete_conds) as $status ) {
					if ( ! in_array( $status, array( 'draft', 'pending', 'pitch' ) ) ) {
						if ( $term = get_term_by( 'slug', $status, 'post_status' ) )
							wp_delete_term( $term->term_id, 'post_status' );
					}
				}
			}
			
			$redirect = add_query_arg( array('delete_count' => count($delete_conds), 'update' => $update, 'pp_attribute' => $attribute, 'attrib_type' => $attrib_type), $redirect);
			wp_redirect($redirect);
			exit();

			break;

		case 'delete' :
			check_admin_referer('bulk-conditions');

			if ( ! current_user_can( 'pp_define_post_status' ) && ( ! $attrib_type || ! current_user_can( "pp_define_{$attrib_type}" ) ) )
				wp_die( __( 'You are not permitted to do that.', 'pps' ) );
			
			if ( ! empty($_REQUEST['pp_conditions']) ) {
				$redirect = add_query_arg( array('pp_action' => 'bulkdelete', 'wp_http_referer' => $_REQUEST['wp_http_referer'], 'pp_conditions' => $_REQUEST['pp_conditions']), $redirect);
				wp_redirect($redirect);
				exit();
			}

			break;
			
		case 'disable' :
		case 'enable' :
			check_admin_referer('bulk-conditions');

			if ( ! current_user_can( 'pp_define_post_status' ) && ( ! $attrib_type || ! current_user_can( "pp_define_{$attrib_type}" ) ) )
				wp_die( __( 'You are not permitted to do that.', 'pps' ) );
			
			if ( empty($_REQUEST['status']) )
				break;
			
			if ( in_array( $_REQUEST['status'], array( 'pending', 'future' ) ) ) {
				pp_update_option( "custom_{$_REQUEST['status']}_caps", ( 'enable' == $action ) );
			} else {
				$disabled_conditions = pp_get_option( "disabled_{$attribute}_conditions" );
				
				if ( 'enable' == $action )
					$disabled_conditions = array_diff_key( $disabled_conditions, array( $_REQUEST['status'] => true ) );
				else
					$disabled_conditions[ $_REQUEST['status'] ] = true;
					
				pp_update_option( "disabled_{$attribute}_conditions", $disabled_conditions );
			}
			
			$redirect = add_query_arg( array('update' => 'edit', 'pp_attribute' => $attribute, 'attrib_type' => $attrib_type), $redirect);
			wp_redirect($redirect);
			exit();
			
			break;

		default:
			if ( !empty($_GET['wp_http_referer']) ) {
				wp_redirect(remove_query_arg(array('wp_http_referer', '_wpnonce'), stripslashes($_SERVER['REQUEST_URI'])));
				exit;
			}
		} // end switch
	}
}
