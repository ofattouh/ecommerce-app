<?php
// add pp_moderate_any capability to user's allcaps array if it is present in any of their supplemental post roles (any post type)
function _ppce_supplement_pp_moderate_any() {			
	global $pp_current_user, $wp_roles;
	$post_types = pp_get_enabled_post_types();
	
	foreach( array_keys($pp_current_user->site_roles) as $pp_role_name ) {
		$wp_role_name = '';
		$arr_role = explode( ':', $pp_role_name );
		
		if ( count($arr_role) == 1 ) {  // direct-assigned role
			$wp_role_name = $pp_role_name;
		} elseif ( count($arr_role) == 3 ) {
			if ( ( 'post' == $arr_role[1] ) && in_array( $arr_role[2], $post_types ) )
				$wp_role_name = $arr_role[0];
		}
		
		if ( $wp_role_name && ! empty( $wp_roles->role_objects[$wp_role_name]->capabilities['pp_moderate_any'] ) ) {
			global $current_user;
			$pp_current_user->allcaps['pp_moderate_any'] = true;
			$current_user->allcaps['pp_moderate_any'] = true;
			break;
		}
	}
}
