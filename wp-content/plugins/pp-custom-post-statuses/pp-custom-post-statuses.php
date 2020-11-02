<?php
/**
 * Plugin Name: PP Custom Post Statuses
 * Plugin URI:  http://presspermit.com
 * Description: Press Permit 2 extension: Your custom post statuses registered and implemented. Moderation statuses (also requires PP Collaborative Editing) allow unlimited steps between pending and published, each with distinct capability requirements and role assignments.
 * Author:      Agapetry Creations LLC
 * Author URI:  http://agapetry.com/
 * Version:     2.2.3
 * Text Domain: pps
 * Domain Path: /languages/
 * Min WP Version: 3.4
 */

/*
Copyright © 2011-2017 Agapetry Creations LLC.

This file is part of PP Custom Post Statuses.

PP Custom Post Statuses is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

PP Custom Post Statuses is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this plugin.  If not, see <http://www.gnu.org/licenses/>.
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( defined( 'PPS_FOLDER' ) ) {
	$func = "do_action('pp_duplicate_extension', 'pp-custom-post-status','" . PPS_FOLDER . "');";
	add_action( 'init', create_function( '', $func ) );
	return;
} else {
	define( 'PPS_FILE', __FILE__ );
	define( 'PPS_FOLDER', dirname( plugin_basename(__FILE__) ) );

	add_action( 'plugins_loaded', '_pps_act_load' );

	function _pps_act_load() {
		$ext_version = '2.2.3';
		$min_pp_version = '2.1.7-beta';
		
		if ( ! defined( 'PPC_VERSION' ) )
			return;
		
		if ( is_admin() ) {
			load_plugin_textdomain( 'pps', '', PPS_FOLDER . '/languages' );
			$title = __( 'PP Custom Post Statuses', 'pps' );
		} else
			$title = 'PP Custom Post Statuses';

		if ( pp_register_extension( 'pp-custom-post-statuses', $title, plugin_basename(__FILE__), $ext_version, $min_pp_version ) ) {
			define( 'PPS_VERSION', $ext_version );
			define( 'PPS_DB_VERSION', '1.0' );
			
			require_once( dirname(__FILE__).'/defaults_pps.php' );
			require_once( dirname(__FILE__).'/pps-load.php' );

			if ( is_admin() )
				require_once( dirname(__FILE__).'/admin/admin-load_pps.php' );
		}
	}

	function _pps_clear_update_info() {
		set_site_transient( 'ppc_update_info', false );
	}

	register_activation_hook( __FILE__, '_pps_clear_update_info' );
	register_deactivation_hook( __FILE__, '_pps_clear_update_info' );
}
