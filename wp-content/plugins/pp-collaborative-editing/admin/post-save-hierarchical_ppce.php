<?php
class PPCE_PostSaveHierarchical {
	public static function flt_page_parent ( $parent_id, $post_type = '' ) {
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return $parent_id;
		
		if ( function_exists( 'bbp_get_version' ) && ! empty( $_REQUEST['action'] ) && in_array( $_REQUEST['action'], array( 'bbp-new-topic', 'bbp-new-reply' ) ) ) {
			return $parent_id;
		}
		
		$selected_parent_id = $parent_id;
		$post_id = pp_get_post_id();
		
		if ( ! $post_id && ! $post_type && defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			global $ppce_rest;
			if ( ! empty( $ppce_rest ) )
				$post_type = $ppce_rest->post_type;
		}
		
		if ( ! $post_id && ! $post_type )  // allow post type to be passed in for pre-filtering of new page creation
			return $parent_id;

		if ( ( $parent_id == $post_id ) && $post_id )	// normal revision save
			return $parent_id;

		if ( $parent_post = get_post( $parent_id ) ) {
			if ( ! in_array( $parent_post->post_type,  pp_get_enabled_post_types() ) )
				return $parent_id;
		}
		
		if ( ! $post_type ) {
			if ( $post = get_post( $post_id ) )
				$post_type = $post->post_type;
		}
		
		// If a newly selected parent is invalid due to exceptions or because it's a descendant, revert to last stored setting
		
		if ( $post_type ) {
			if ( ! in_array( $post_type,  pp_get_enabled_post_types() ) )
				return $parent_id;
			
			static $return;
			if ( ! isset($return) ) $return = array();
			if ( isset( $return[$post_id] ) ) return $return[$post_id];
			
			if ( $parent_id ) {
				global $pp_current_user;
				
				$descendants = self::get_page_descendant_ids( $post_id );
				
				$revert = false;
				if ( in_array( $parent_id, $descendants ) ) {
					$revert = true;
				} 
				
				$additional_ids = $pp_current_user->get_exception_posts( 'associate', 'additional', $post_type );
				
				if ( $include_ids = $pp_current_user->get_exception_posts( 'associate', 'include', $post_type ) ) {
					$exclude_ids = false;
					$include_ids = array_merge( $include_ids, $additional_ids );
					if ( ! in_array( $parent_id, $include_ids ) )
						$revert = true;

				} elseif ( $exclude_ids = array_diff( $pp_current_user->get_exception_posts( 'associate', 'exclude', $post_type ), $additional_ids ) ) {
					if ( in_array( $parent_id, $exclude_ids ) )
						$revert = true;
				}

				if ( $revert ) {
					$parent_id = self::revert_page_parent( $post_id, $post_type, compact( 'descendants', 'include_ids', 'exclude_ids' ) );
				}
			}

			$parent_id = apply_filters( 'pp_validate_page_parent', $parent_id, $post_type, compact( 'descendants', 'include_ids', 'exclude_ids' ) );
			
			// subsequent filtering is currently just a safeguard against invalid "no parent" posting in violation of lock_top_pages
			// if ( $parent_id || ( ! $selected_parent_id && ppce_user_can_associate_main( $post_type ) ) )
			if ( $parent_id || ppce_user_can_associate_main( $post_type ) ) 
				return $parent_id;
		
			return self::revert_page_parent( $post_id, $post_type );
			
		}
		
		$return[$post_id] = $parent_id;

		return $parent_id;
	}
	
	static function get_page_descendant_ids($page_id, $pages = '' ) {
		global $wpdb;
		
		if ( empty( $pages ) )
			$pages = $wpdb->get_results( "SELECT ID, post_parent FROM $wpdb->posts WHERE post_parent > 0 AND post_type NOT IN ( 'revision', 'attachment' )" );	

		$descendant_ids = array();
		foreach ( (array) $pages as $page ) {
			if ( $page->post_parent == $page_id ) {
				$descendant_ids[] = $page->ID;
				if ( $children = get_page_children($page->ID, $pages) ) {  // RS note: okay to use unfiltered WP function here since it's only used for excluding
					foreach( $children as $_page )
						$descendant_ids []= $_page->ID;
				}
			}
		}
		
		return $descendant_ids;
	}
	
	static function revert_page_parent( $post_id, $post_type, $args = array() ) {
		$defaults = array( 'descendants' => array(), 'include_ids' => array(), 'exclude_ids' => array() );
		$args = array_merge( $defaults, $args );
		extract($args, EXTR_SKIP);
	
		$post = get_post( $post_id );

		global $wpdb;
		$valid_parents = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type = '" . pp_sanitize_key($post_type) . "' AND post_status NOT IN ('trash')" );
		$valid_parents = array_diff( $valid_parents, $descendants, (array) $post_id );
		
		if ( ! $valid_parents ) {
			$parent_id = 0;
		} elseif ( $post && ( ! $post->post_parent || in_array( $post->post_parent, $valid_parents ) ) ) {
			$parent_id = $post->post_parent;
		} else {
			if ( $include_ids )
				$valid_parents = array_intersect( $valid_parents, $include_ids );
			elseif( $exclude_ids )
				$valid_parents = array_diff( $valid_parents, $exclude_ids );
			
			if ( $valid_parents ) {
				sort($valid_parents);
				$parent_id = reset($valid_parents);
			} else {
				$parent_id = 0;
			}
		}

		$_POST['parent_id'] = $parent_id; // for subsequent post_status filter

		return $parent_id;
	}
	
	// Filtering of Page Parent submission (applied to post_status filter because fallback on invalid submission for a previously unpublished post is to force it to draft status).
	//
	// There is currently no way to explictly restrict or grant Page Association rights to Main Page (root). Instead:
	// 	* Require site-wide edit_others_pages cap for association of a page with Main
	//  * If an unqualified user tries to associate or un-associate a page with Main Page,
	//	  revert page to previously stored parent if possible. Otherwise set status to "unpublished".
	public static function enforce_top_pages_lock( $status ) {
		/*
		// overcome any denials of publishing rights which were not filterable by user_has_cap	// TODO: confirm this is no longer necessary
		if ( ('pending' == $status) && ( ('publish' == $_POST['post_status']) || ('Publish' == $_POST['original_publish'] ) ) )
			if ( ! empty( $current_user->allcaps['publish_pages'] ) )
				$status = 'publish';
		*/

		global $post;
		
		// user can't associate / un-associate a page with Main page unless they have edit_pages site-wide
		if ( ! empty( $_POST['post_ID'] ) ) {
			$post_id = (int) $_POST['post_ID'];
			$selected_parent_id = isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : 0;
		} elseif( ! empty($post) ) {
			$post_id = $post->ID;
			$selected_parent_id = $post->post_parent;
		} else
			return $status;	
		
		$_post = get_post( $post_id );
		
		if ( $saved_status_object = get_post_status_object( $_post->post_status ) )
			$already_published = ( $saved_status_object->public || $saved_status_object->private );
		else
			$already_published = false;
			
		// if neither the stored nor selected parent is Main, we have no beef with it
		if ( ! empty($selected_parent_id) && ( ! empty($_post->post_parent ) || ! $already_published ) )
			return $status;
			
		// if the page is and was associated with Main Page, don't mess
		if ( empty($selected_parent_id) && empty($_post->post_parent) && ( $already_published || defined( 'PPCE_LIMITED_EDITORS_TOP_LEVEL_PUBLISH' ) ) )
			return $status;
		
		if ( empty($_POST['parent_id']) ) {
			if ( ! $already_published ) {  // This should only ever happen if the POST data is manually fudged
				if ( $post_status_object = get_post_status_object( $status ) ) {
					if ( $post_status_object->public || $post_status_object->private )
						$status = 'draft';
				}
			}
		}
		
		return $status;
	}
} // end class
