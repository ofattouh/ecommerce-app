<?php
/**
 * Plugin Name: PP Collaborative Editing Pack
 * Plugin URI:  http://presspermit.com
 * Description: Press Permit 2 extension: Supports content-specific editing permissions, term assignment and page parent limitations. In combination with other extensions, supports custom moderation statuses, Edit Flow, Revisionary and Post Forking.
 * Author:      Agapetry Creations LLC
 * Author URI:  http://agapetry.com/
 * Version:     2.3.22
 * Text Domain: ppce
 * Domain Path: /languages/
 * Min WP Version: 3.4
 */

/*
Copyright © 2011-2017 Agapetry Creations LLC.

This file is part of PP Collaborative Editing Pack.

PP Collaborative Editing Pack is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

PP Collaborative Editing Pack is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this plugin.  If not, see <http://www.gnu.org/licenses/>.
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( defined( 'PPCE_FOLDER' ) ) {
	$func = "do_action('pp_duplicate_extension', 'pp-collaborative-editing','" . PPCE_FOLDER . "');";
	add_action( 'init', create_function( '', $func ) );
	return;
} else {
	define( 'PPCE_FILE', __FILE__ );
	define( 'PPCE_FOLDER', dirname( plugin_basename(__FILE__) ) );

	add_action( 'plugins_loaded', '_ppce_act_load' );

	function _ppce_act_load() {
		$ext_version = '2.3.22';
		$min_pp_version = '2.1.13-beta';
		
		if ( ! defined( 'PPC_VERSION' ) )
			return;
		
		if ( is_admin() ) {
			load_plugin_textdomain( 'ppce', '', PPCE_FOLDER . '/languages' );
			$title = __( 'PP Collaborative Editing Pack', 'ppce' );
		} else
			$title = 'PP Collaborative Editing Pack';

		if ( pp_register_extension( 'pp-collaborative-editing', $title, plugin_basename(__FILE__), $ext_version, $min_pp_version ) ) {
			define( 'PPCE_VERSION', $ext_version );

			require_once( dirname(__FILE__).'/defaults_ppce.php' );
			require_once( dirname(__FILE__).'/definitions_ppce.php' );
			require_once( dirname(__FILE__).'/ppce_load.php' );

			if ( is_admin() )
				require_once( dirname(__FILE__).'/admin/admin-load_ppce.php' );
		}
	}

	function _ppce_clear_update_info() {
		set_site_transient( 'ppc_update_info', false );
	}
		
	register_activation_hook( __FILE__, '_ppce_clear_update_info' );
	register_deactivation_hook( __FILE__, '_ppce_clear_update_info' );

	if ( defined('WPMU_PLUGIN_DIR') && ( false !== strpos( __FILE__, WPMU_PLUGIN_DIR ) ) )
		define( 'PPCE_ABSPATH', WPMU_PLUGIN_DIR . DIRECTORY_SEPARATOR . basename(PPCE_FOLDER) );
	elseif ( defined('WP_PLUGIN_DIR') )
		define( 'PPCE_ABSPATH', WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . basename(PPCE_FOLDER) );
	else
		define( 'PPCE_ABSPATH', WP_CONTENT_DIR . '/plugins/' . PPCE_FOLDER );
}
