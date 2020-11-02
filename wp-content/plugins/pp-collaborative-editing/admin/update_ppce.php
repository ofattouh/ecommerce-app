<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PPCE_Updated {
	public static function populate_roles() {
		if ( $role = @get_role( 'administrator' ) ) {
			$role->add_cap( 'pp_set_edit_exceptions' );
			$role->add_cap( 'pp_set_associate_exceptions' );
			$role->add_cap( 'pp_set_term_assign_exceptions' );
			$role->add_cap( 'pp_set_term_manage_exceptions' );
			$role->add_cap( 'pp_set_term_associate_exceptions' );
		}
		
		if ( $role = @get_role( 'editor' ) ) {
			$role->add_cap( 'pp_set_edit_exceptions' );
			$role->add_cap( 'pp_set_associate_exceptions' );
			$role->add_cap( 'pp_set_term_assign_exceptions' );
			$role->add_cap( 'pp_set_term_manage_exceptions' );
			$role->add_cap( 'pp_set_term_associate_exceptions' );
		}

		update_option( 'ppce_added_role_caps_21beta', true );
	}

	public static function version_updated( $prev_version ) {
		// single-pass do loop to easily skip unnecessary version checks
		do {
			if ( ! $prev_version ) {
				break;  // no need to run through version comparisons if no previous version
			}

			if ( version_compare( $prev_version, '2.1.4-beta', '<') ) {
				// added pp caps for exception assignment from Post/Term edit screen by non-Administrators
				if ( ! get_option( 'ppce_added_role_caps_21beta' ) )
					ppce_populate_roles();
			}

		} while ( 0 ); // end single-pass version check loop
		
		if ( $prev_version && is_admin() ) {
			if ( preg_match( "/dev|alpha|beta|rc/i", PPCE_VERSION ) && ! preg_match( "/dev|alpha|beta|rc/i", $prev_version ) ) {
				ppc_notice( __( 'You have installed a development / beta version of PP Collaborative Editing. If this is a concern, see Permissions > Settings > Install > Beta Updates.', 'ppce' ), 'updated' );
			}
		}
	}
} // end class
