<?php
class PP_HardwayParent {
	public static function flt_dropdown_pages($orig_options_html) {
		if ( ! strpos( $orig_options_html, 'parent_id' ) || ! $orig_options_html || pp_unfiltered() )
			return $orig_options_html;

		$post_type = pp_find_post_type();

		// User can't associate or de-associate a page with Main page unless they have edit_pages site-wide.
		// Prepend the Main Page option if appropriate (or, to avoid submission errors, if we generated no other options)
		if ( ! ppce_user_can_associate_main( $post_type ) ) {
			global $post;
			$is_new = ( $post->post_status == 'auto-draft' );
				
			if ( ! $is_new ) {
				global $post;
				$object_id = ( ! empty($post->ID) ) ? $post->ID : pp_get_post_id();

				$stored_parent_id = ( ! empty($post->ID) ) ? $post->post_parent : get_post_field( 'post_parent', $object_id );
			}

			if ( $is_new || $stored_parent_id ) {
				$mat = array();
				preg_match('/<option[^v]* value="">[^<]*<\/option>/', $orig_options_html, $mat);
	
				if ( ! empty($mat[0]) )
					return str_replace( $mat[0], '', $orig_options_html );
			}
		}
		
		return $orig_options_html;
	}
}
