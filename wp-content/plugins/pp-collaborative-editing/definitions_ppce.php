<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_action( 'init', '_ppce_status_registrations', 44 );  // statuses need to be registered before establish_status_caps() execution

add_action( 'ppc_registrations', '_ppce_registrations' );	// this action is triggered by PP Custom Post Statuses extension
add_action( 'pp_apply_config_options', '_ppce_adjust_default_pattern_roles' );

add_filter( 'pp_operations', '_ppce_operations' );
add_filter( 'pp_define_pattern_caps', 	'_ppce_define_pattern_caps' );
add_filter( 'pp_apply_arbitrary_caps', 	'_ppce_apply_arbitrary_caps', 10, 3 );

function _ppce_operations( $ops ) {
	$ops[]= 'edit';
	
	if ( defined( 'PP_PUBLISH_EXCEPTIONS' ) )
		$ops[]= 'publish';
	
	if ( class_exists('Fork', false ) && ! defined( 'PP_DISABLE_FORKING_SUPPORT' ) )
		$ops[] = 'fork';
	
	if ( defined( 'RVY_VERSION' ) )
		$ops[]= 'revise';
	
	$ops = array_merge( $ops, array( 'associate', 'assign', 'manage' ) );
	
	return $ops;
}

function _ppce_define_pattern_caps( $pattern_role_caps ) {
	global $pp_cap_caster;

	$type_obj = get_taxonomy( 'category' );
	$type_caps['category'] = array_intersect_key( get_object_vars( $type_obj->cap ), array_fill_keys( array( 'manage_terms' ), true ) );

	foreach( array_keys($pattern_role_caps) as $role_name ) {
		// log caps defined for the "category" taxonomy
		$pp_cap_caster->pattern_role_taxonomy_caps[$role_name] = array_intersect_key( $pattern_role_caps[$role_name], $type_caps['category'] );
	}
}

function _ppce_apply_arbitrary_caps(  $caps, $arr_name, $type_obj ) {
	global $pp_cap_caster;

	$base_role_name = $arr_name[0];

	// "Misc" caps are other caps in the pattern role which are not type-defined
	if ( ! empty($pp_cap_caster->pattern_role_arbitrary_caps[ $base_role_name ]) && post_type_exists( $type_obj->name ) ) {  // for now, don't apply arbitrary caps for typecast term management roles
		$arbitrary_caps = $pp_cap_caster->pattern_role_arbitrary_caps[$base_role_name];
		if ( ! empty( $arr_name[4] ) )	// these caps will be added only for supplemental roles with no status specified
			$arbitrary_caps = array_diff_key( $arbitrary_caps, array_fill_keys( apply_filters( 'pp_status_role_skip_caps', array( 'list_users', 'edit_users', 'delete_users', 'switch_themes', 'edit_themes', 'activate_plugins', 'edit_plugins', 'manage_options', 'manage_links', 'import' ) ), true ) );

		$caps = array_merge( $arbitrary_caps, $caps );
	}
	
	return $caps;
}

function _ppce_status_registrations() {
	if ( ! defined('PPC_VERSION' ) )
		return;

	if ( defined( 'PPS_VERSION' ) && ! defined( 'PP_NO_MODERATION' ) ) {
		// custom moderation stati
		register_post_status( 'approved', array(
			'label'       => _x( 'Approved', 'post' ),
			'labels'	  => (object) array( 'publish' => __( 'Approve', 'pp' ) ),
			'moderation'  => true,
			'protected'   => true,
			'internal'	  => false,
			'label_count' => _n_noop( 'Approved <span class="count">(%s)</span>', 'Approved <span class="count">(%s)</span>' ),
			'pp_builtin'  => true,
		) );
	}
}

function _ppce_registrations() {
	if ( defined( 'PPS_VERSION' ) ) {
		global $wp_post_statuses, $pp_current_user;
		
		foreach( array( 'pending', 'future' ) as $status )
			$wp_post_statuses[$status]->moderation = pp_get_option( "custom_{$status}_caps" );

		// unfortunate little hack due to execution order
		if ( pp_get_option( 'supplemental_cap_moderate_any' ) && $pp_current_user->ID && $pp_current_user->site_roles && ! pp_is_content_administrator() ) {
			require_once( dirname(__FILE__).'/site-roles-bootstrap_ppce.php' );
			_ppce_supplement_pp_moderate_any();
		}

		$skip_metacaps = ! empty($pp_current_user->allcaps['pp_moderate_any']) && false === strpos( $_SERVER['REQUEST_URI'], 'page=pp-stati' );
		
		// register each custom post status as an attribute condition with mapped caps
		foreach( get_post_stati( array(), 'object' ) as $status => $status_obj ) {
			if ( ! empty( $status_obj->moderation ) ) { 
				$metacap_map = ( $skip_metacaps ) ? array() : array( 'edit_post' => "edit_{$status}_posts", 'delete_post' => "delete_{$status}_posts" );

				pps_register_condition( 'post_status', $status, array( 
										'label' => $status_obj->label, 
										'metacap_map' => $metacap_map,
										'cap_map' => array( 'set_posts_status' => "set_posts_{$status}" ),
									) );
			}
		}
	}
}

function _ppce_adjust_default_pattern_roles() {
	//pp_register_pattern_role( 'reviewer', array( 'labels' => (object) array( 'name' => __('Reviewers', 'pp'), 'singular_name' => __('Reviewer', 'pp') ) ) );

	if ( defined('RVY_VERSION') ) {
		pp_register_pattern_role( 'revisor', array( 'labels' => (object) array( 'name' => __('Revisors', 'pp'), 'singular_name' => __('Revisor', 'pp') ) ) );
	}
}

