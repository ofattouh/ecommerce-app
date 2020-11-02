<?php
class PPS_PostSave {
	public static function flt_post_status( $post_status ) {
		if ( empty($_POST) || empty( $_POST['post_ID'] ) )
			return $post_status;	

		if ( defined('RVY_VERSION') && ! empty($_POST) && ( ! empty($_REQUEST['page']) && ( 'rvy-revisions' == $_REQUEST['page'] ) ) )
			return $post_status;

		static $done;
		if ( ! empty($done) ) return $post_status;	// Important: if other plugin code inserts additional posts in response, don't filter those
		$done = true;
		
		$post_id = $_POST['post_ID'];
		
		$selected_status = pp_sanitize_key($_POST['post_status']);
		
		if ( 'publish' == $_POST['post_status'] ) {
			$selected_status = ( isset( $_POST['visibility'] ) ) ? pp_sanitize_key($_POST['visibility']) : $selected_status;

			if ( 'public' == $selected_status )
				$selected_status = 'publish';
		}
		
		// inline edit: apply keep_status checkbox selection
		if ( ! empty( $_POST['action'] ) && ( 'inline-save' == $_POST['action'] ) ) {
			if ( $_post = get_post( $post_id ) ) {
				foreach( pp_get_post_stati( array( 'private' => true, 'post_type' => $_post->post_type ) ) as $status ) {
					if ( ! empty( $_POST["keep_{$status}"] ) ) {
						$selected_status = $status;
						break;
					}
				}
			}
		}
		
		$post_status_obj = get_post_status_object( $selected_status );
		if ( $post_status_obj && ! empty($post_status_obj->moderation) ) {
			$selected_status = apply_filters( 'pp_selected_moderation_status', $selected_status, $post_id );
		}
		
		// make sure user is allowed to set post to this status
		if ( ! in_array( $selected_status, array( 'draft', 'pending', 'publish', 'private' ) ) && ! current_user_can( 'pp_moderate_any' ) && ! current_user_can( 'pp_administer_content' ) ) {
			$pp_attributes = pps_init_attributes();
		
			$stored_status = get_post_field( 'post_status', $post_id );
			if ( $stored_status != $selected_status ) {
				$type_obj = get_post_type_object( pp_find_post_type() );
				$selected_status_obj = get_post_status_object( $selected_status );
				
				if ( $selected_status_obj && ( empty($selected_status_obj->moderation) || ! current_user_can( $type_obj->cap->publish_posts ) ) ) {
					// @todo: move this to function/method (also used in post-edit-ui)
					if ( empty( $type_obj->cap->set_posts_status ) ) {
						$set_status_cap = $type_obj->cap->publish_posts;
					} else {
						$cond_caps = $pp_attributes->get_condition_caps( $type_obj->cap->set_posts_status, $type_obj->name, 'post_status', $selected_status );
						
						if ( ! $set_status_cap = reset( $cond_caps ) )
							$set_status_cap = $type_obj->cap->set_posts_status;
					}
					
					if ( ! current_user_can( $set_status_cap ) ) {
						$selected_status = $stored_status;
					}
				}
			}
		}
		
		if ( ! $post_status_obj = get_post_status_object( $selected_status ) )
			return $post_status;
		
		$post_status = $selected_status;
		
		if ( ! empty( $post_status_obj->private ) ) {
			$_POST['post_password'] = '';
			
			if ( isset( $_POST['sticky'] ) )
				unset( $_POST['sticky'] );
		}
		
		if ( $post_status_obj->public || $post_status_obj->private ) {
			if ( ! empty( $_POST['post_date_gmt'] ) )
				$post_date_gmt = $_POST['post_date_gmt'];
			elseif ( ! empty($_POST['aa']) ) {
				foreach( array( 'aa' => 'Y', 'mm' => 'n', 'jj' => 'j', 'hh' => '', 'mn' => '', 'ss' => '' ) as $var => $format ) {
					$$var = ( ! $format || $_POST[$var] > 0 ) ? $_POST[$var] : date( $format );
				}
				$post_date = sprintf( "%04d-%02d-%02d %02d:%02d:%02d", $aa, $mm, min($jj, 31), min($hh, 23), min($mn, 59), 0 );
				$post_date_gmt = get_gmt_from_date( $post_date );
			}
		
			// set status to future if a future date was selected with a private status
			$now = gmdate('Y-m-d H:i:59');
			if ( ! empty($post_date_gmt) && mysql2date('U', $post_date_gmt, false) > mysql2date('U', $now, false) ) {
				update_post_meta( $post_id, '_scheduled_status', $post_status );
				$post_status = 'future';
			} else {
				// if a post is being transitioned from scheduled to published/private, apply scheduled status
				$_post = get_post( $post_id );
				if ( 'future' == $_post->post_status ) {  // stored status is future
					if ( $selected_status = get_post_meta( $post_id, '_scheduled_status', true ) )
						$post_status = $selected_status;

					delete_post_meta( $post_id, '_scheduled_status' );
				}
			}
		}

		return $post_status;
	}

	// If a public or private status is selected, change it to the specified force_visibility status
	public static function flt_force_visibility ($status) {
		if ( ! $status_obj = get_post_status_object( $status ) )
			return $status;

		static $done;
		if ( ! empty($done) ) return $status;	// Important: if other plugin code inserts additional posts in response, don't filter those
		$done = true;

		if ( $status_obj->public || $status_obj->private ) {
			$post_id = pp_get_post_id();
			$_post = get_post( $post_id );
		
			if ( empty($_POST) || empty($_post) || ! is_object($_post) )
				return $status;
		
			if ( ! empty($_POST) ) {
				if ( ! empty($_POST['post_password']) ) return $status;
			} elseif ( $_post && $_post->post_password )
				return $status;
		
			if ( pp_get_type_option( 'force_default_privacy', $_post->post_type ) ) {
				if ( $forced_default = pp_get_type_option( 'default_privacy', $_post->post_type ) )
					return $forced_default;
			}

			if ( $is_hierarchical = is_post_type_hierarchical( $_post->post_type ) ) {	
				// since force_visibility is always a propagating condition and the parent setting may be in flux too, check setting for parent instead of post
				if ( ! empty($_POST) && isset($_POST['parent_id']) )
					$parent_id = apply_filters( 'pre_post_parent', $_POST['parent_id'] );
				elseif ( $_post )
					$parent_id = $_post->post_parent;
			}
			
			if ( ! $is_hierarchical || ! empty($parent_id) ) {  
				// also poll force_visibility for non-hierarchical types to support PPCE forcing default visibility
				$pp_attributes = pps_init_attributes();
				$_args = ( $is_hierarchical ) ? array( 'id' => $parent_id, 'assign_for' => 'children' ) : array( 'default_only' => true, 'post_type' => $_post->post_type ); 
				if ( $force_status = $pp_attributes->get_item_condition( 'post', 'force_visibility', $_args ) ) {
					$status = $force_status;
				}
			}
		}
		
		return $status;
	}

	// called by PP_AdminFilters::mnt_save_object
	// This handler is meant to fire whenever an object is inserted or updated.
	public static function act_save_post( $post_id, $object = '') {
		if ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			|| ( ! empty( $_REQUEST['action'] ) && ( 'untrash' == $_REQUEST['action'] ) )
			|| ( 'revision' == $object->post_type )	// operations in this function do not apply to revision save
		) { return; }
		
		if ( defined( 'RVY_VERSION' ) ) {
			global $revisionary;
			if ( ! empty($revisionary->admin->revision_save_in_progress) ) {
				$revisionary->admin->revision_save_in_progress = false;
				return;
			}
		}

		if ( isset( $_POST['ch_visibility'] ) ) {
			require_once( dirname(__FILE__).'/item-save_pps.php' );
			PPS_ItemSave::propagate_post_visibility( $post_id, pp_sanitize_key($_POST['ch_visibility']) );
		}
		
		if ( is_post_type_hierarchical( $object->post_type ) ) {
			require_once( dirname(__FILE__).'/item-save_pps.php' );
			PPS_ItemSave::post_update_force_visibility( $object );
		}
	}
} // end class
