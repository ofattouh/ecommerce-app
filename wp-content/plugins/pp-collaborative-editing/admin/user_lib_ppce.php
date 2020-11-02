<?php
class PPCE_UserEdit {
	// optional filter for WP role edit based on user level
	public static function editable_roles( $roles ) {
		global $current_user;

		$role_levels = self::get_role_levels();
		
		$current_user_level = self::get_user_level( $current_user->ID );
		
		foreach ( array_keys($roles) as $role_name )
			if ( isset($role_levels[$role_name]) && ( $role_levels[$role_name] > $current_user_level ) )
				unset( $roles[$role_name] );
		
		return $roles;
	}	

	public static function has_edit_user_cap($wp_sitecaps, $orig_reqd_caps, $args) {
		// prevent anyone from editing a user whose level is higher than their own
		$levels = self::get_user_level( array( $args[1], $args[2] ) );
		
		// finally, compare would-be editor's level with target user's
		if ( $levels[ $args[2] ] > $levels[ $args[1] ] )
			$wp_sitecaps = array_diff_key(  $wp_sitecaps, array_fill_keys( array( 'edit_users', 'delete_users', 'remove_users', 'promote_users' ), true ) );
			
		return $wp_sitecaps;
	}
	
	public static function get_user_level( $user_ids ) {
		static $user_levels;
		
		$return_array = is_array( $user_ids );  // if an array was passed in, return results as an array
		
		if ( ! is_array($user_ids) ) {
			if ( is_multisite() && function_exists('is_super_admin') && is_super_admin() )	// mu site administrator may not be a user for the current site
				return 10;
			
			$orig_user_id = $user_ids;	
			$user_ids = (array) $user_ids;
		}
	
		if ( ! isset($user_levels) )
			$user_levels = array();
			
		if ( array_diff( $user_ids, array_keys($user_levels) ) ) {
			// one or more of the users were not already logged	

			$role_levels = self::get_role_levels(); // local buffer for performance
				
			// If the listed user ids were logged following a search operation, save extra DB queries by getting the levels of all those users now
			global $wp_user_search, $current_user;
			
			if ( ( count($user_ids) == 1 ) && ( current($user_ids) == $current_user->ID ) ) {
				$results = array();
				foreach( $current_user->roles as $role_name ) {
					$results[]= (object) array( 'user_id' => $current_user->ID, 'role_name' => $role_name );
				}
			} else {
				if ( ! empty( $wp_user_search->results ) ) {
					$query_users = $wp_user_search->results;
					$query_users = array_unique( array_merge( $query_users, $user_ids ) );
				} else
					$query_users = $user_ids;
		
				// get the WP roles for user
				global $wpdb;
				$results = $wpdb->get_results( "SELECT m.user_id, g.metagroup_id AS role_name FROM $wpdb->pp_groups AS g INNER JOIN $wpdb->pp_group_members AS m ON m.group_id = g.ID WHERE g.metagroup_type = 'wp_role' AND m.user_id IN ('" . implode( "','", $query_users ) . "')" );
			}
			
			// credit each user for the highest role level they have
			foreach ( $results as $row ) {
				if ( ! isset( $role_levels[ $row->role_name ] ) )
					continue;
	
				if ( ! isset( $user_levels[$row->user_id] ) || ( $role_levels[ $row->role_name ] > $user_levels[$row->user_id] ) )
					$user_levels[$row->user_id] = $role_levels[ $row->role_name ];
			}
			
			// note any "No Role" users
			if ( ! empty( $query_users ) ) {
				if ( $no_role_users = array_diff( $query_users, array_keys($user_levels) ) )
					$user_levels = $user_levels + array_fill_keys( $no_role_users, 0 );
			}
		}
		
		
		if ( $return_array )
			$return = array_intersect_key( $user_levels, array_fill_keys( $user_ids, true ) );
		else 
			$return = ( isset($user_levels[$orig_user_id]) ) ? $user_levels[$orig_user_id] : 0;

		return $return;
	}

	// NOTE: user/role levels are used only for optional limiting of user edit - not for content filtering
	public static function get_role_levels() {
		static $role_levels;
		
		if ( isset($role_levels) )
			return $role_levels;

		$role_levels = array();
		
		global $wp_roles;
		foreach ( $wp_roles->role_objects as $role_name => $role ) {
			$level = 0;
			for ( $i=0; $i<=10; $i++ )
				if ( ! empty( $role->capabilities["level_$i"] ) )
					$level = $i;
			
			$role_levels[$role_name] = $level;
		}	
		
		return $role_levels;
	}

} // end class
