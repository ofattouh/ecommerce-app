<?php
function pps_editing_plugin() {
	if ( is_admin() ) {
		global $pagenow;
		
		// avoid lockout in case of erroneous plugin edit via wp-admin
		if ( isset($pagenow) && ( 'plugin-editor.php' == $pagenow ) ) {
			if ( empty( $_REQUEST['action'] ) || ! in_array( $_REQUEST['action'], array( 'activate', 'deactivate' ) ) )
				return true;
		}
	}
	
	return false;
}
