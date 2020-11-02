<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! empty($_POST) ) {
	global $pp_plugin_page;

	if ( in_array( $pp_plugin_page, array( 'pp-status-edit', 'pp-status-new' ) ) ) {
		$func = "require_once( '" . dirname(__FILE__) . "/pp-status-edit-handler.php');";
		add_action( 'pp_user_init', create_function( '', $func ) );
	}
}

if ( ! empty( $_REQUEST['action'] ) || ! empty($_REQUEST['action2']) || ! empty( $_REQUEST['pp_action'] ) ) {
	if ( strpos( $_SERVER['REQUEST_URI'], 'page=pp-stati' ) || ( ! empty( $_REQUEST['wp_http_referer'] ) && ( strpos( $_REQUEST['wp_http_referer'], 'page=pp-stati' ) ) ) ) {
		$func = "require_once( '" . dirname(__FILE__) . "/pp-status-handler.php');";
		add_action( 'pp_user_init', create_function( '', $func ) );
	}
}
