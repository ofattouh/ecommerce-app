<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PPCE_Rvy_CapInterceptor {
	function __construct() {
		add_filter( 'pp_has_post_cap_vars', array(&$this, 'has_post_cap_vars'), 10, 4 );
		
		if ( ! defined('DOING_AJAX') || ! DOING_AJAX )
			add_filter( 'map_meta_cap', array(&$this, 'flt_adjust_reqd_caps'), 1, 4 );

		// byref argument was actually ineffective prior to 2.3.15, so maintain that behavior by eliminating action hook
		add_action( 'pp_has_post_cap_done', array(&$this, 'has_post_cap_tweak_memcache'), 10, 5);
		
		//add_filter( 'pp_user_can_meta_flags', array(&$this, 'user_can_meta_flags' ) );
	}
	
	// hooks to map_meta_cap
	function flt_adjust_reqd_caps( $reqd_caps, $orig_cap, $user_id, $args ) {
		global $pagenow, $current_user;

		if ( $user_id != $current_user->ID )
			return $reqd_caps;

		if ( ! empty($args[0]) && ! empty($args[0]->query_contexts) && in_array( 'comments', $args[0]->query_contexts ) )
			return $reqd_caps;
		
		if ( isset($args[0]) && in_array( $orig_cap, array( 'edit_post', 'delete_post', 'edit_page', 'delete_page' ) ) ) {
			require_once( dirname(__FILE__).'/revisionary-helper_ppce.php' );
			$object_type = pp_find_post_type( $args[0] ); // $args[0] is object id; type property will be pulled from object
			
			// ensure proper cap requirements when a non-Administrator Quick-Edits or Bulk-Edits Posts/Pages (which may be included in the edit listing only for revision submission)
			if ( in_array( $pagenow, array('edit.php', 'edit-tags.php', 'admin-ajax.php') ) && ! empty($_REQUEST['action']) && ( ( -1 != $_REQUEST['action'] ) || ( isset($_REQUEST['action2']) && -1 != $_REQUEST['action2'] ) ) ) {
				$reqd_caps = PP_Rvy_Helper::fix_table_edit_reqd_caps( $reqd_caps, $orig_cap, get_post( $args[0] ), get_post_type_object($object_type) );
			}
		}
		
		if ( ! empty($object_type) && in_array( $orig_cap, array( 'edit_post', 'edit_page' ) ) ) {
			require_once( dirname(__FILE__).'/revisionary-helper_ppce.php' );
			$unused_byref_arg = false;
			$reqd_caps = PP_Rvy_Helper::adjust_revision_reqd_caps( $reqd_caps, $object_type, $unused_byref_arg );
		}

		return $reqd_caps;
	}
	
	function has_post_cap_vars( $force_vars, $wp_sitecaps, $pp_reqd_caps, $vars ) {
		extract($vars, EXTR_SKIP);	 // compact( 'post_type', 'post_id', 'user_id', 'required_operation' )

		$return = array();

		if ( ( 'read_post' == reset($pp_reqd_caps) ) ) {
			if ( ! is_admin() && ! empty($_REQUEST['post_type']) && ( 'revision' == $_REQUEST['post_type'] ) && ( ! empty($_REQUEST['preview']) || ! empty($_REQUEST['preview_id']) ) ) {
				$return['pp_reqd_caps'] = array('edit_post');
			}
		}
		
		global $cap_interceptor;
		if ( empty($cap_interceptor->flags['memcache_disabled']) ) {
			global $revisionary;
			if ( isset( $revisionary ) && ! empty( $revisionary->skip_revision_allowance ) )
				$cap_interceptor->flags['cache_key_suffix'] .= '-skip_revision_allowance-';
		}
	
		return ( $return ) ? array_merge( (array) $force_vars, $return ) : $force_vars;

		// note: PP_CapInterceptor::flt_user_has_cap() filters return array to allowed variables before extracting
	}

	function has_post_cap_tweak_memcache( $this_id_okay, $pp_reqd_caps, $src_name, $object_type, $post_id ) {
		global $cap_interceptor;

		// if we redirected the cap check to revision parent, also credit all the revisions for passing results ($revisions array may be returned by a 'pp_has_cap_force_vars' filter)
		if ( $this_id_okay && ! empty($cap_interceptor->revisions) && ! $cap_interceptor->memcache_disabled ) {
			$cap_interceptor->memcache['tested_ids'][$object_type][$capreqs_key] = $cap_interceptor->memcache['tested_ids'][$src_name][$object_type][$capreqs_key] + array_fill_keys( $cap_interceptor->revisions, true );
		}
	}

	
	/*
	function user_can_meta_flags( $meta_flags ) {
		// handle special case revisionary flag
		if ( ! empty($meta_flags['skip_revision_allowance']) ) {
			if ( defined( 'RVY_VERSION' ) ) {
				global $revisionary;
				$revisionary->skip_revision_allowance = true;	// this will affect the behavior of Press Permit's user_has_cap filter
			}
			
			unset( $meta_flags['skip_revision_allowance'] );	// no need to set this flag on cap_interceptor
		}
		
		return $meta_flags;
	}
	*/
}
