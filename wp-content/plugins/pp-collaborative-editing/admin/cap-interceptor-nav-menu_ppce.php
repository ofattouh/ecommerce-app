<?php
class NavMenuCapHelper {
	public static function fudge_nav_menu_caps( $reqd_caps ) {
		// this function is called only if edit_theme_options is in reqd_caps but not in current_user->allcaps
		global $cap_interceptor;

		// global $pagenow;
		//if ( in_array( $pagenow, array( 'nav-menus.php', 'admin-ajax.php' ) ) || $cap_interceptor->doing_admin_menus() ) {
			$key = array_search( 'edit_theme_options', $reqd_caps );
			if ( false !== $key ) {
				$tx = get_taxonomy( 'nav_menu' );
				$reqd_caps[$key] = $tx->cap->manage_terms;
				
				// menu-specific manager assignment does not permit deletion of the menu
				if ( ! empty( $_REQUEST['action'] ) && ( 'delete' == $_REQUEST['action'] ) )
					$cap_interceptor->skip_any_term_check = true;
			}
		//}
		
		return $reqd_caps;
	}
}
