<?php

class PPCE_TermsInterceptorAdmin {
	public static function flt_get_terms_universal_exceptions( $universal, $required_operation, $taxonomy, $args ) {
		// if a term is excluded (or outside an include set) for editing, don't allow assignment either
		//if ( 'assign' == $required_operation ) { // already checked
			global $pp_current_user;
			
			$universal['additional'] = array_merge( $universal['additional'], $pp_current_user->get_exception_terms( 'edit', 'additional', '', $taxonomy ) );  // this is for the purpose of exempting items from omission due to include/exclude exceptions

			// if a term is excluded from editing, don't allow assignment either
			foreach( array( 'include', 'exclude' ) as $mod_type ) {
				if ( $edit_tt_ids = $pp_current_user->get_exception_terms( 'edit', $mod_type, '', $taxonomy ) ) {
					if ( 'include' == $mod_type ) {
						if ( $universal[$mod_type] ) {
							if ( $edit_assign_intersect = array_intersect( $universal[$mod_type], $edit_tt_ids ) )
								$universal[$mod_type] = $edit_assign_intersect;
							else
								$universal[$mod_type] = array( -1 );	// include exceptions are set for both edit and assign, and there is no overlap
						} else
							$universal[$mod_type] = $edit_tt_ids;
					} else {
						$universal[$mod_type] = array_merge( $universal[$mod_type], $edit_tt_ids );
					}
				}
			}
		//}
		
		return $universal;
	}

	public static function flt_get_terms_exceptions( $tt_ids, $required_operation, $mod_type, $post_type, $taxonomy, $args ) {
		global $pp_current_user;
		
		if ( 'assign' == $required_operation ) { // already checked
			// if a term is excluded from editing, don't allow assignment either
			if ( $edit_tt_ids = $pp_current_user->get_exception_terms( 'edit', $mod_type, $post_type, $taxonomy ) ) {
				if ( 'include' == $mod_type ) {
					if ( $tt_ids ) {
						if ( $edit_assign_intersect = array_intersect( $tt_ids, $edit_tt_ids ) )
							$tt_ids = $edit_assign_intersect;
						else
							$tt_ids = array( -1 );	// include exceptions are set for both edit and assign, and there is no overlap.  Note: universal+type_specific include exceptions are additive, but edit*assign include exceptions are an intersection
					} else
						$tt_ids = $edit_tt_ids;
				} else {
					$tt_ids = array_merge( $tt_ids, $edit_tt_ids );
				}
			}
		}
	
		if ( ( 'manage' == $required_operation ) && ( 'include' == $mod_type ) ) {
			if ( ! empty( $args['additional_tt_ids'] ) ) {
				if ( $tx_obj = get_taxonomy( $taxonomy ) ) {
					if ( empty( $pp_current_user->allcaps[ $tx_obj->cap->manage_terms ] ) )  // if a user lacking sitewide manage cap has additions, only those terms are manageable
						$tt_ids = array_merge( $tt_ids, $args['additional_tt_ids'] );
				}
			}
		}
	
		return $tt_ids;
	}
	
} // end class
