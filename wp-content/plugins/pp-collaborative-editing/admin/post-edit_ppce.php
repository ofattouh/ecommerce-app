<?php
class PPCE_PostEditHelper {
	public static function default_privacy_workaround() {
		if ( empty($_POST['publish']) && isset($_POST['visibility']) && isset($_POST['post_type']) && pp_get_type_option( 'default_privacy', $_POST['post_type'] ) ) {
			$stati = get_post_stati( array( 'moderation' => true ), 'names' );
			if ( in_array( $_POST['post_status'], $stati ) )
				return;
			
			$stati = get_post_stati( array( 'public' => true, 'private' => true ), 'names', 'or' );

			if ( ! in_array( $_POST['visibility'], array( 'public', 'password' ) ) && ! in_array( $_POST['hidden_post_status'], $stati ) ) {
				$_POST['post_status'] = $_POST['hidden_post_status'];
				$_REQUEST['post_status'] = $_REQUEST['hidden_post_status'];
				
				$_POST['visibility'] = 'public';
				$_REQUEST['visibility'] = 'public';
			}
		}
	}
	
	public static function flt_post_status( $status ) {
		//if ( pp_unfiltered() || ( 'auto-draft' == $status ) || strpos( $_SERVER['REQUEST_URI'], 'nav-menus.php' ) )  // already checked by calling function
		//	return $status;

		$post_type = pp_find_post_type();

		if ( $type_obj = get_post_type_object( $post_type ) ) {
			if ( $type_obj->hierarchical && post_type_supports( $post_type, 'page-attributes' ) ) {
				if ( ! ppce_user_can_associate_main($post_type) ) {
					require_once( dirname(__FILE__).'/post-save-hierarchical_ppce.php' );
					$status = PPCE_PostSaveHierarchical::enforce_top_pages_lock( $status );
				}
			}
		}

		$status_obj = get_post_status_object( $status );
		
		if ( defined( 'PPS_VERSION' ) ) {
			$pp_attributes = pps_init_attributes();
			
			$post_id = pp_get_post_id();	// $_REQUEST['post_ID'];
			
			// Support "Approve" button (or other default approval status)
			if ( ( 'pending' == $status ) && ! empty($_POST) && ( ! empty($_POST['publish']) ) && ! empty($_POST['default_approval_status'] ) ) {
				if ( current_user_can( 'pp_moderate_any' ) || current_user_can( 'pp_administer_content' ) )
					return $status;

				$default_approval_status = $_POST['default_approval_status'];
				
				$check_caps = $pp_attributes->get_condition_caps( $type_obj->cap->set_posts_status, $post_type, 'post_status', $default_approval_status );
				
				$pass = true;
				foreach( $check_caps as $_cap ) {
				if ( ! current_user_can( $_cap, $post_id ) )
					$pass = false;
					break;
				}
				
				if ( $pass )
					return pp_sanitize_key( $default_approval_status );
					
			} elseif ( ! empty($status_obj->moderation) && ( 'pending' != $status ) && ! current_user_can( $type_obj->cap->publish_posts ) ) {
				if ( current_user_can( 'pp_moderate_any' ) || current_user_can( 'pp_administer_content' ) )
					return $status;

				$check_caps = $pp_attributes->get_condition_caps( $type_obj->cap->set_posts_status, $post_type, 'post_status', $status );
				
				foreach( $check_caps as $cap ) {
					if ( ! current_user_can( $cap, $post_id ) ) {
						// if this status is the same as last stored, allow it
						if ( $status == get_post_field( 'post_status', $post_id ) )
							return $status;
						else
							return 'pending';
					}
				}
			}
		}
		
		return $status;
	}
	
	public static function user_can_associate_main( $post_type ) {
		if ( pp_unfiltered() )
			return true;

		if ( ! $post_type_obj = get_post_type_object($post_type) )
			return true;
			
		if ( ! $post_type_obj->hierarchical )
			return true;
		
		// apply manually assigned associate exceptions even if lock_top_pages filtering is disabled
		global $pp_current_user;
		
		$post_ids = $pp_current_user->get_exception_posts( 'associate', 'exclude', $post_type );
		if ( in_array( 0, $post_ids ) )
			return false;
			
		$post_ids = $pp_current_user->get_exception_posts( 'associate', 'include', $post_type );
		if ( $post_ids && ! in_array( 0, $post_ids ) )
			return false;
		
		$post_ids = $pp_current_user->get_exception_posts( 'edit', 'include', $post_type );
		if ( $post_ids ) {
			global $post;
			
			if ( $additional_post_ids = $pp_current_user->get_exception_posts( 'edit', 'additional', $post_type ) )
				$post_ids = array_merge( $post_ids, $additional_post_ids );
			
			// cannot currently support propagation of parent exceptions to new top level pages, so don't offer (no parent) as a post parent selection if editing is limited to a subset of pages and this page is not in that subset
			$post_id = pp_get_post_id();
			if ( ! $post_id || ! in_array( $post_id, $post_ids ) )
				return false;
		}
		
		$top_pages_locked = pp_get_option( 'lock_top_pages' );
		
		if ( 'no_parent_filter' == $top_pages_locked )
			return true;
		
		if ( ( 'page' == $post_type ) || ! defined( 'PP_LOCK_OPTION_PAGES_ONLY' ) ) {
			if ( '1' === $top_pages_locked ) {
				// only administrators can change top level structure
				return false;
			} else {
				$reqd_caps = ( 'author' === $top_pages_locked ) ? $post_type_obj->cap->publish_posts : $post_type_obj->cap->edit_others_posts;
				return current_user_can( $reqd_caps );
			}
		} else
			return true;
	}
}
