<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PPCE_QueryInterceptor {
	function __construct() {
		//add_filter( 'rvy_init', array( &$this, 'revisionary_filters' ) );
		$this->revisionary_filters();
		
		add_filter( 'pp_query_missing_caps', array( &$this, 'apply_list_all_posts' ), 10, 4 );
		
		if ( pp_wp_ver( '3.8' ) ) // WP autosave sets post_status to 'draft' at initial post creation
			add_filter( 'pp_exception_clause', array( &$this, 'allow_autodraft_editing' ), 10, 4 );
	}
	
	// Ensure that autodrafts can be edited even if they do not yet have a required page parent or term setting (NOTE: this fix also requires PP Core 2.1.39)
	// $args = compact( 'mod', 'ids', 'src_table', 'logic' );
	function allow_autodraft_editing( $exception_clause, $required_operation, $post_type, $args ) {
		if ( ( 'edit' == $required_operation ) && ( 'include' == $args['mod'] ) && is_admin() ) {
			global $pagenow, $current_user;
	
			if ( in_array( $pagenow, array( 'post-new.php', 'post.php' ) ) && ! empty($_POST) && in_array( $_REQUEST['action'], array( 'edit', 'editpost' ) ) ) {
				if ( $post_id = pp_get_post_id() ) {
					$_post = get_post( $post_id );

					if ( ( $current_user->ID == $_post->post_author ) && ( $_post->post_type == $post_type ) && ( ( 'auto-draft' == $_post->post_status ) || ( ( 'draft' == $_post->post_status ) && get_post_meta( $post_id, '_pp_is_autodraft', true ) ) ) ) {
						// Ensure the current query pertains to a capability check for the autodraft itself
						if ( ! empty($args['limit_ids']) && ( 1 == count($args['limit_ids']) ) && ( $_post->ID == reset($args['limit_ids']) ) ) {
							// The autodraft flag is required because WP autosave sets post_status to 'draft'.  But this is only an issue for initial creation before parent/terms are assigned. Delete the flag now so editing exceptions are not permanently bypassed.
							delete_post_meta( $post_id, '_pp_is_autodraft' );
							return '1=1';
						}
					}
				}
			}
		}
		
		return $exception_clause;
	}
	
	function revisionary_filters() {
		global $query_interceptor_ppce_rvy;
		require_once( dirname(__FILE__).'/query-interceptor-revisionary_ppce.php' );
		$query_interceptor_ppce_rvy = new PPCE_QueryInterceptor_Rvy();
	}

	function apply_list_all_posts( $missing_caps, $reqd_caps, $post_type, $meta_cap ) {
		if ( 'edit_post' == $meta_cap ) {
			global $pp_current_user;

			if ( $missing_caps = array_diff( $reqd_caps, array_keys( $pp_current_user->allcaps ) ) ) {
				$type_obj = get_post_type_object( $post_type );
				$list_cap = str_replace( 'edit_', 'list_all_', $type_obj->cap->edit_posts );

				if ( ! empty( $pp_current_user->allcaps[$list_cap] ) ) {
					foreach( $missing_caps as $key => $cap_name ) {
						if ( 0 === strpos( $cap_name, 'edit_' ) )
							unset( $missing_caps[$key] );
					}
				}
			}
		}

		return $missing_caps;
	}
}
