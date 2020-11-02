<?php
class PPCE_UserLimitation {
	public static function is_limited_editor() {
		if ( pp_is_content_administrator() )
			return false;
		
		if ( $type_obj = get_post_type_object( pp_find_post_type() ) ) {
			global $pp_current_user;

			if ( ! current_user_can( $type_obj->cap->edit_posts ) )
				return true;
			
			$has_exceptions = false;
			foreach( array( 'edit_post', 'associate_post' ) as $op ) {
				foreach( array( 'include', 'exclude' ) as $mod ) {
					if ( ! empty( $pp_current_user->except[$op]['post'][''][$mod][$type_obj->name] ) ) {
						return true;
					}
				}
			}
			
			if ( $taxonomies = pp_get_enabled_taxonomies( array( 'object_type' => $type_obj->name ) ) ) {
				foreach( $taxonomies as $taxonomy ) {
					foreach( array( 'include', 'exclude' ) as $mod ) {
						if ( ! empty( $pp_current_user->except['assign_post']['term'][$taxonomy][$mod]['post'] ) ) {
							return true;
						}
					}
				}
			}
			
			return apply_filters( 'pp_hide_quickedit', false, $type_obj );
		}
	}
}
