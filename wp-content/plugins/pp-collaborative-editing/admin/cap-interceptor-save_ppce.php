<?php
	class PPCE_EditCapHelper {
		// Workaround to deal with WP core's checking of publish cap prior to storing categories
		// Store terms to DB in advance of any cap-checking query which may use those terms to qualify an operation		
		public static function pre_assign_terms( $pp_reqd_caps, $post_type, $object_id ) {
			$set_terms = false;

			if ( ! $post_type_obj = get_post_type_object( $post_type ) )
				return false;

			if ( array_intersect( array( 'edit_post', 'publish_posts', 'edit_posts', $post_type_obj->cap->edit_post, $post_type_obj->cap->publish_posts, $post_type_obj->cap->edit_posts ), $pp_reqd_caps) ) {
				static $objects_done;
				if ( ! isset($objects_done) )
					$objects_done = array();
					
				if ( in_array( $object_id, $objects_done ) )
					return false;
					
				$objects_done[]= $object_id;
				
				$uses_taxonomies = pp_get_enabled_taxonomies( array( 'object_type' => $post_type ) );
				
				static $inserted_terms;
				if ( ! isset( $inserted_terms ) )
					$inserted_terms = array();
				
				foreach ( $uses_taxonomies as $taxonomy ) {
					if ( isset( $inserted_terms[$taxonomy][$object_id] ) )
						continue;
					
					// don't filter term selection for non-hierarchical taxonomies
					//if ( ! is_taxonomy_hierarchical( $taxonomy ) )
					//	continue;

					$inserted_terms[$taxonomy][$object_id] = true;

					$tx_obj = get_taxonomy( $taxonomy );
					
					//if ( empty($tx_obj->requires_term) )
					//	continue;
					
					//global $pp;
					//$stored_terms = $pp->get_terms($taxonomy, UNFILTERED_PP, 'id', compact( 'object_id' ) );	  // note: wp_get_object_terms() would cause trouble if WP core ever auto-stores object terms on post creation
					$stored_terms = ppce_get_object_terms( $object_id, $taxonomy, array( 'fields' => 'ids', 'pp_no_filter' => true ) );
					
					if ( ! $selected_terms = ppce_get_posted_object_terms( $taxonomy ) ) {
						$selected_terms = ppce_get_user_default_terms( $taxonomy );
					}

					if ( is_array($selected_terms) ) { // non-hierarchical terms don't need to be pre-inserted
						if ( $set_terms = apply_filters( 'pp_pre_object_terms', $selected_terms, $taxonomy ) ) {
							$set_terms = array_unique( array_map('intval', $set_terms) );

							if ( ( $set_terms != $stored_terms ) && $set_terms && ( $set_terms != array(1) ) ) { // safeguard against unintended clearing of stored categories
								wp_set_object_terms( $object_id, $set_terms, $taxonomy );
								$set_terms = true;
							}
						}
					}
				}
			}
			
			return $set_terms;
		}
		
	} // end class
