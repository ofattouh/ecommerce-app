<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PPS_Updated {
	public static function populate_roles( $ver_tag = '10beta' ) {
		if ( get_option( "ppperm_added_pps_role_caps_{$ver_tag}" ) ) // in case the role has been manually customized, don't force default caps back in
			return;
		
		switch( $ver_tag ) {
			case '10beta' :
				if ( $role = @get_role( 'administrator' ) ) {
					//$role->add_cap( 'pp_set_conditions' );
					$role->add_cap( 'pp_define_post_status' );
					$role->add_cap( 'set_posts_status' );	// need this in pattern role to support mapping of set_posts_approved, etc.
				}
				
				if ( $role = @get_role( 'editor' ) ) {
					//$role->add_cap( 'pp_set_conditions' );
					$role->add_cap( 'set_posts_status' );
					$role->add_cap( 'pp_moderate_any' );
				}
				
				if ( $role = @get_role( 'author' ) ) {
					//$role->add_cap( 'pp_set_conditions' );
					$role->add_cap( 'set_posts_status' );
				}
				break;
		}
		
		update_option( "ppperm_added_pps_role_caps_{$ver_tag}", true );
	}
	
	public static function version_updated( $prev_version ) {
		// single-pass do loop to easily skip unnecessary version checks
		do {
			if ( ! get_option( "ppperm_added_pps_role_caps_10beta" ) )
				self::populate_roles();

			if ( version_compare( $prev_version, '2.1.14', '<') ) {
				if ( $statuses = get_option( 'pp_custom_conditions_post_status' ) ) {
					$modified = false;
					foreach( array_keys($statuses) as $status_name ) {
						if ( strlen( $status_name ) > 20 ) {
							$new_name = substr( $status_name, 0, 20 );
							$statuses[$new_name] = (array) $statuses[$status_name];
							unset( $statuses[$status_name] );
							$modified = true;
						}
					}
					
					if ( $modified )
						update_option( 'pp_custom_conditions_post_status', $statuses );
				}
			} else break;
			
			if ( version_compare( $prev_version, '2.0.10-beta', '<') ) {
				global $wpdb;
				$wpdb->update( $wpdb->options, array( 'autoload' => 'yes' ), array( 'option_name' => 'pp_custom_conditions_post_status' ) );
			} else break;
		} while ( 0 ); // end single-pass version check loop
	}
	
	// clean up from dual use of ppperm_added_cc_role_caps_10beta flag by both PP Circles and PP Custom Post Statuses
	public static function flag_cleanup() {
		if ( $role = @get_role( 'administrator' ) ) {
			$admin_caps = (array) $role->capabilities;
			if ( ! empty( $admin_caps['pp_define_post_status'] ) ) {
				update_option( 'ppperm_added_pps_role_caps_10beta', true ); // PP Custom Post Status caps were actually initialized already
			}
		}
	}
} // end class
