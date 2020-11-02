<?php
class PP_RvyContentRoles extends RevisionaryContentRoles {
	function filter_object_terms( $terms, $taxonomy ) { 
		return apply_filters( 'pp_pre_object_terms', $terms, $taxonomy );
	}
	
	function get_metagroup_edit_link( $metagroup_name ) { 
		if ( $group = pp_get_group_by_name( '[' . $metagroup_name . ']' ) ) {
			return "admin.php?page=pp-edit-permissions&action=edit&agent_id=$group->ID";
		}
		return '';
	}
	
	function get_metagroup_members( $metagroup_name, $args = array() ) { 
		if ( $group = pp_get_group_by_name( '[' . $metagroup_name . ']' ) ) {
			return pp_get_group_members( $group->ID, 'pp_group', 'id', array( 'maybe_metagroup' => true ) );
		}
		return array(); 
	}
	
	function users_who_can( $reqd_caps, $object_id = 0, $args = array() ) { 
		global $pp;
		
		$defaults = array( 'cols' => 'id', 'user_ids' => array() );
		$args = array_merge( $defaults, $args );
		extract( $args, EXTR_SKIP );
		
		if ( ! empty($pp) ) {
			global $current_user, $pp, $cap_interceptor;
			$buffer_user_id = $current_user->ID;
			
			$ok_users = array();
			
			foreach( $user_ids as $user_id ) {
				wp_set_current_user( $user_id );

				$pp->memcache = array();
				$cap_interceptor->flags['memcache_disabled'] = true;
				
				if ( current_user_can( 'edit_post', $object_id ) ) {
					if ( 'id' == $cols )
						$ok_users []= $current_user->ID;
					else
						$ok_users []= $current_user;
				}
			}

			$cap_interceptor->flags['memcache_disabled'] = false;
			wp_set_current_user( $buffer_user_id );
		
			return $ok_users;
		}
		return array();
	}
	
	function add_listed_ids( $src_name, $object_type, $id ) {
		global $pp;
		$pp->listed_ids[$src_name][$object_type][$id] = true;	
	}
	
	function set_hascap_flags( $flags ) { 
		global $scoper;
		
		if ( ! is_array($flags) )
			return;
		
		foreach( $flags as $key => $val ) {
			$scoper->cap_interceptor->$key = $val;
		}
	}
	
	function is_direct_file_access() {
		global $pp;
		return ! empty( $pp->direct_file_access );
	}
}

