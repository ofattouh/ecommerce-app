<?php
	function _ppce_user_can_admin_role($role_name, $item_id, $src_name = '', $object_type = '' ) {
		if ( pp_is_user_administrator() )
			return true;

		static $require_sitewide_editor;
			
		if ( ! isset($require_sitewide_editor) )
			$require_sitewide_editor = pp_get_option('role_admin_sitewide_editor_only');

		if ( 'admin' == $require_sitewide_editor )
			return false;  // User Admins already returned true
		
		if ( ( 'admin_content' == $require_sitewide_editor ) && ! pp_is_content_administrator() )
			return false;

		// user can't view or edit role assignments unless they have all rolecaps
		if ( $item_id ) {
			global $pp_cap_caster;
		
			static $reqd_caps;
			
			if ( ! isset($reqd_caps) )
				$reqd_caps = array();

			// TODO: deal with WP, typecast role assignments
			//global $pp_role_caps;
			//if ( ! isset($reqd_caps[$role_name]) )
			//	$reqd_caps[$role_name] = $pp_role_caps[$role_name];

			$reqd_caps[$role_name] = $pp_cap_caster->get_typecast_caps( $role_name, 'object' );
		
			// temp workaround
			foreach( $reqd_caps[$role_name] as $key => $cap_name ) {
				if ( 0 === strpos( $key, 'create_child_' ) ) {
					$cap_name = str_replace( 'create_child_', 'edit_', $cap_name );
				}

				if ( ! current_user_can( $cap_name, $item_id) )
					return false;
			}
			
			// are we also applying the additional requirement (based on RS Option setting) that the user is a site-wide editor?
			if ( $require_sitewide_editor ) {
				static $can_edit_sitewide;

				if ( ! isset($can_edit_sitewide) )
					$can_edit_sitewide = array();
					
				if ( ! isset($can_edit_sitewide[$src_name][$object_type]) ) {
					if ( 'post' == $src_name ) {
						if ( $type_obj = get_post_type_object( $object_type ) )
							$can_edit_sitewide[$src_name][$object_type] = current_user_can( $type_obj->cap->edit_posts );
					}
				}
	
				if ( ! $can_edit_sitewide[$src_name][$object_type] )
					return false;
			}
		}
		
		return true;
	}
