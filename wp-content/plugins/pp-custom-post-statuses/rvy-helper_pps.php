<?php
add_filter( 'rvy_replace_post_edit_caps', '_pps_rvy_replace_post_edit_caps', 10, 3 );

function _pps_rvy_replace_post_edit_caps( $caps, $post_type, $post_id ) {
	$pp_attributes = pps_init_attributes();
	
	if ( empty($pp_attributes->all_custom_condition_caps[$post_type]) )
		return $caps;
	
	if ( $type_obj = get_post_type_object( $post_type ) ) {
		if ( isset( $pp_attributes->all_moderation_caps[$post_type][$type_obj->cap->edit_posts] ) )
			$caps = array_merge( $caps, $pp_attributes->all_moderation_caps[$post_type][$type_obj->cap->edit_posts] );
	}
	
	return array_unique( $caps );
}

