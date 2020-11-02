<?php

class PPCE_TermEdit {
	public static function term_edit_attempt() {
		// filter category parent selection for Category editing
		if ( ! isset( $_POST['tag_ID'] ) )
			return;
		
		$taxonomy = pp_sanitize_key($_POST['taxonomy']);
		
		if ( ! $tx = get_taxonomy($taxonomy) )
			return;
			
		if ( ! $tx->hierarchical )
			return;
			
		$stored_term = get_term_by( 'id', $_POST['tag_ID'], $taxonomy );

		$selected_parent = (int) $_POST['parent'];
		
		if ( -1 == $selected_parent )
			$selected_parent = 0;
		
		if ( $stored_term->parent != $selected_parent ) {
			global $pp_current_user;;
			
			if ( $tx_obj = get_taxonomy( $taxonomy ) ) {
				if ( ! $included_ttids = apply_filters( 'pp_get_terms_exceptions', $pp_current_user->get_exception_terms( 'associate', 'include', $taxonomy, $taxonomy, array( 'merge_universals' => true ) ), 'associate', 'include', $taxonomy, $additional_tt_ids ) )
					$excluded_ttids = apply_filters( 'pp_get_terms_exceptions', $pp_current_user->get_exception_terms( 'associate', 'include', $taxonomy, $taxonomy, array( 'merge_universals' => true ) ), 'associate', 'exclude', $taxonomy, $additional_tt_ids );
				
				if ( $selected_parent ) {
					$terms_interceptor = pp_init_terms_interceptor();
					
					$additional_tt_ids = $pp_current_user->get_exception_terms( 'associate', 'additional', $taxonomy, $taxonomy );
					$parent_ttid = pp_termid_to_ttid( $selected_parent, $taxonomy );

					$permit = true;
					if ( $included_ttids ) {
						if ( $additional_tt_ids )
							$included_ttids = array_merge( $included_ttids, $additional_tt_ids );
							
						if ( ! in_array( $parent_ttid, $included_ttids ) )
							$permit = false;
					} else {
						if ( $additional_tt_ids )
							$excluded_ttids = array_diff( $excluded_ttids, $additional_tt_ids );
							
						if ( in_array( $parent_ttid, $excluded_ttids ) )
							$permit = false;
					}
				} else {
					if ( $included_ttids && ! in_array( 0, $included_ttids ) )
						$permit = false;
					elseif ( $excluded_ttids && in_array( 0, $excluded_ttids ) )
						$permit = false;
					else
						$permit = ! empty( $pp_current_user->allcaps[ $tx_obj->cap->manage_terms ] );
				}
			}
			
			if ( empty($permit) ) {
				wp_die( __('You do not have permission to select that Parent', 'pp') );
			}
		}
	}
}

