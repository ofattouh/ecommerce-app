<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once( dirname(__FILE__).'/db-config_pps.php' );

add_action( 'pp_maint_filters', '_pps_act_maint_filters' );

//add_action( 'pp_user_init', '_pps_fix_author_caps', 0 );
add_action( 'init', '_pps_act_registrations', 46 );
add_action( 'init', '_pps_act_post_stati_prep', 48 );	// note: pps_process_conditions() follows up for any late-registered statuses
add_action( 'pp_pre_init', '_pps_act_version_check' );
add_action( 'pp_pre_init', '_pps_act_force_distinct_post_caps' );

add_action( 'pp_load_config', '_pps_act_load_config' );
add_action( 'pp_apply_config_options', '_pps_act_apply_config_options' );

add_action( 'pp_query_interceptor', '_pps_act_load_metacaps' );
add_action( 'pp_enable_status_mapping', '_pps_act_enable_status_mapping' );

add_filter( 'pp_pattern_roles', '_pps_flt_pattern_roles' );
add_filter( 'pp_pattern_role_caps', '_pps_flt_default_rolecaps' );
add_filter( 'pp_exclude_arbitrary_caps', '_pps_flt_exclude_arbitrary_caps' );


if ( defined( 'RVY_VERSION' ) )
	require_once( dirname(__FILE__).'/rvy-helper_pps.php' );

function _pps_act_maint_filters() {
	require_once( dirname(__FILE__).'/admin/filters-maint_pps.php' );
}

// Register default custom stati; Additional labels in status registration
function _pps_act_registrations() {
	global $wp_post_statuses;
	
	// custom private stati
	register_post_status( 'member', array(
		'label'       => _x( 'Member', 'post' ),
		'private'     => true,
		'label_count' => _n_noop( 'Member <span class="count">(%s)</span>', 'Member <span class="count">(%s)</span>' ),
		'pp_builtin'  => true,
	) );
	
	register_post_status( 'premium', array(
		'label'       => _x( 'Premium', 'post' ),
		'private'     => true,
		'label_count' => _n_noop( 'Premium <span class="count">(%s)</span>', 'Premium <span class="count">(%s)</span>' ),
		'pp_builtin'  => true,
	) );

	register_post_status( 'staff', array(
		'label'       => _x( 'Staff', 'post' ),
		'private'     => true,
		'label_count' => _n_noop( 'Staff <span class="count">(%s)</span>', 'Staff <span class="count">(%s)</span>' ),
		'pp_builtin'  => true,
	) );
	
	$custom_stati = get_option( "pp_custom_conditions_post_status" );

	if ( is_array($custom_stati) ) {
		foreach( $custom_stati as $status => $status_args ) {
			if ( ! empty($status_args['moderation']) ) {
				if ( defined( 'PP_NO_MODERATION' ) )
					continue;
				
				$status_args['protected'] = true;
			}

			$sing = sprintf( __('%s <span class="count">()</span>', 'pps'), $status_args['label']);
			$plur = sprintf( __('%s <span class="count">()</span>', 'pps'), $status_args['label']);
			$status_args['label_count'] = _n_noop( str_replace('()', '(%s)', $sing), str_replace('()', '(%s)', $plur) );
		
			if ( isset($wp_post_statuses[$status]) ) {
				if ( ! empty($status_args['label']) ) {
					$wp_post_statuses[$status]->label = $status_args['label'];
					
					if ( ! isset($wp_post_statuses[$status]->labels) )
						$wp_post_statuses[$status]->labels = (object) array();
					
					$wp_post_statuses[$status]->labels->caption = $status_args['label'];
					$wp_post_statuses[$status]->label_count = _n_noop( str_replace('()', '(%s)', $sing), str_replace('()', '(%s)', $plur) );
				}
				
				if ( ! empty($status_args['post_type']) )
					$wp_post_statuses[$status]->post_type = (array) $status_args['post_type'];

			} elseif ( $status ) { // sanity check to avoid adding invalid status name
				register_post_status( $status, $status_args );
			}
			
			if ( ! isset($wp_post_statuses[$status]) )
				continue;
			
			if ( empty( $wp_post_statuses[$status]->_builtin ) && ! in_array( $status, array( 'draft', 'pending' ) ) )
				$wp_post_statuses[$status]->pp_custom = true;

			if ( ! empty($status_args['save_as_label']) ) {
				if ( ! isset($wp_post_statuses[$status]->labels) )
					$wp_post_statuses[$status]->labels = (object) array();

				$wp_post_statuses[$status]->labels->save_as = $status_args['save_as_label'];
			}
			
			if ( ! empty($status_args['publish_label']) ) {
				if ( ! isset($wp_post_statuses[$status]->labels) )
					$wp_post_statuses[$status]->labels = (object) array();

				$wp_post_statuses[$status]->labels->publish = $status_args['publish_label'];
			}
		}
	}
}

function _pps_act_post_stati_prep() {
	global $wp_post_statuses;
	
	// set default properties
	foreach( array_keys( $wp_post_statuses ) as $status ) {
		if ( ! isset( $wp_post_statuses[$status]->moderation ) )
			$wp_post_statuses[$status]->moderation = false;
	}
	
	// apply PP-stored status config
	if ( $stati_post_types = (array) pp_get_option( 'status_post_types' ) ) { // @todo: does this cause extra query because not included in pp_default_options array?
		foreach( array_intersect_key( $stati_post_types, $wp_post_statuses ) as $status => $types ) {
			if ( $types )
				$wp_post_statuses[$status]->post_type = $types;
		}
	}
	
	if ( defined( 'PPCE_VERSION' ) ) {
		$stati_order = array_intersect_key( (array) pp_get_option( 'status_order' ), $wp_post_statuses );
		foreach( $stati_order as $status => $order ) {
			$wp_post_statuses[$status]->order = $order;
		}
		
		if ( ! isset( $wp_post_statuses['pending']->order ) )
			$wp_post_statuses['pending']->order = 10;
		
		if ( ! isset( $wp_post_statuses['approved']->order ) )
			$wp_post_statuses['approved']->order = $wp_post_statuses['pending']->order + 8; // one proposed WP patch has publish status defaulting priority to 20

		foreach( pp_get_post_stati( array( 'moderation' => true ) ) as $status ) {
			if ( ! isset($wp_post_statuses[$status]->order) )
				$wp_post_statuses[$status]->order = $wp_post_statuses['pending']->order + 4;
		}
	}
	
	/*
	// default to all post types
	$default_post_types = get_post_types( array( 'public' => true, 'show_ui' => true ) );
	
	foreach( array_keys($wp_post_statuses) as $status ) {
		if ( ! isset( $wp_post_statuses[$status]->post_type ) )
			$wp_post_statuses[$status]->post_type = $default_post_types;
	}
	*/
}

function _pps_act_version_check() {
	$ver = get_option('pps_version');
	
	if ( get_option( 'ppperm_added_cc_role_caps_10beta' ) && ! get_option( 'ppperm_added_pps_role_caps_10beta' ) ) {
		// clean up from dual use of ppperm_added_cc_role_caps_10beta flag by both PP Circles and PP Custom Post Statuses
		require_once( dirname(__FILE__).'/admin/update_pps.php');
		PPS_Updated::flag_cleanup();
	}
	
	if ( empty($ver['db_version']) || version_compare( PPS_DB_VERSION, $ver['db_version'], '!=') ) {
		require_once( dirname(__FILE__).'/db-setup_pps.php');
		PPS_DB_Setup::db_setup($ver['db_version']);
		update_option( 'pps_version', array( 'version' => PPS_VERSION, 'db_version' => PPS_DB_VERSION ) );
	}
	
	if ( ! empty($ver['version']) ) {
		// These maintenance operations only apply when a previous version of PPCS was installed 
		if ( version_compare( PPS_VERSION, $ver['version'], '!=') ) {
			require_once( dirname(__FILE__).'/admin/update_pps.php');
			PPS_Updated::version_updated( $ver['version'] );
			update_option( 'pps_version', array( 'version' => PPS_VERSION, 'db_version' => PPS_DB_VERSION ) );
		}
	} else {
		// first execution after install
		require_once( dirname(__FILE__).'/admin/update_pps.php');
		PPS_Updated::populate_roles();
	}
}

function _pps_act_force_distinct_post_caps() {
	global $pp_cap_helper, $wp_post_types;
	
	$generic_caps = array( 'post' => array( 'set_posts_status' => 'set_posts_status' ), 'page' => array( 'set_posts_status' => 'set_posts_status' ) );

	// post types which are enabled for PP filtering must have distinct type-related cap definitions
	foreach( array_intersect( get_post_types( array( 'public' => true ), 'names' ), pp_get_enabled_post_types() ) as $post_type ) {
		if ( 'post' == $post_type )
			$type_caps['set_posts_status'] = 'set_posts_status';
		else
			$type_caps['set_posts_status'] = str_replace( '_post', "_$post_type", 'set_posts_status' );
		
		$wp_post_types[$post_type]->cap = (object) array_merge( (array) $wp_post_types[$post_type]->cap, $type_caps );

		$pp_cap_helper->all_type_caps = array_merge( $pp_cap_helper->all_type_caps, array_fill_keys( $type_caps, true ) );

		foreach( pp_get_post_stati( array( 'moderation' => true, 'post_type' => $post_type ) ) as $status_name ) {
			$cap_property = "set_{$status_name}_posts";
			$wp_post_types[$post_type]->cap->$cap_property = str_replace( "_posts", "_$post_type", $cap_property );
		}
	} // end foreach post type
}

function _pps_act_load_metacaps() {
	global $pp_meta_caps;
	require_once( dirname(__FILE__).'/meta_caps_pp.php' );
	$pp_meta_caps = new PP_Meta_Caps();
}

function _pps_act_enable_status_mapping( $enable ) {
	global $pp_meta_caps;
	$pp_meta_caps->do_status_cap_map = $enable;  // for perf, instead of removing/adding 'map_meta_cap' filter here
}

// for optimal flexibility with custom moderation stati (including Edit Flow), dynamically insert a Submitter role contains the 'set_posts_status' capability
// 	 With default Contributor rolecaps, a "Page Contributor - Assigned" role enables the user to edit their own pages which have been set to assigned status.  
//   "Page Submitter - Assigned" role enables setting their other pages to the Approved status
//   These supplemental roles may be assigned individually or in conjunction
//	 Note that the set_posts_status capability is granted implicitly for the 'pending' status, even if custom capabilities are enabled.
function _pps_flt_default_rolecaps( $caps ) {
	if ( defined( 'PPCE_VERSION') && ! isset( $caps['submitter'] ) ) {
		$caps['submitter'] = array_fill_keys( array( 'read', 'set_posts_status' ), true );
	}

	return $caps;
}

function _pps_flt_pattern_roles( $roles ) {
	if ( defined( 'PPCE_VERSION') ) {
		if ( ! isset($roles['submitter']) )
			$roles['submitter'] = (object) array();

		if ( ! isset( $roles['submitter']->labels ) )
			$roles['submitter']->labels = (object) array( 'name' => __('Submitters', 'pp'), 'singular_name' => __('Submitter', 'pp') );
	}
	
	return $roles;
}

function _pps_act_load_config() {
	global $pp_attributes;
	require_once( dirname(__FILE__).'/attributes_pp.php' );
	$pp_attributes = new PP_RestrictionAttributes();
	
	// Edit Flow compat (mark EF stati as moderation)
	if ( defined( 'EDIT_FLOW_VERSION' ) && defined( 'PPCE_VERSION' ) && ! defined( 'PP_NO_MODERATION' ) ) {
		global $wp_post_statuses;
		
		$ef_stati = array();
		$ef_terms = get_terms( 'post_status', array( 'hide_empty' => false ) );
		foreach( $ef_terms as $term ) {
			if ( is_object($term) )
				$ef_stati[$term->slug] = $term->position;
		}
		
		foreach( get_post_stati( array( 'public' => false, 'private' => false ), 'names' ) as $status ) {
			if ( array_key_exists( $status, $ef_stati ) && ! in_array( $status, array( 'draft', 'pending' ) ) ) {
				$wp_post_statuses[$status]->moderation = true;
				$wp_post_statuses[$status]->edit_flow = true;
				
				if ( ! isset($wp_post_statuses[$status]->order) ) {
					if ( ! empty( $ef_stati[$status] ) ) {
						$wp_post_statuses[$status]->order = $ef_stati[$status];
					} else {
						switch ( $status ) {
							case 'pitch':
								$wp_post_statuses[$status]->order = 2;
								break;
							
							case 'assigned':
								$wp_post_statuses[$status]->order = 5;
								break;
								
							case 'in-progress':
								$wp_post_statuses[$status]->order = 7;
								break;
						}
					}
				}
			}
		}
	}

	do_action( 'pp_status_registrations' );
	
	pps_init_attributes();

	// Restriction of read access will be accomplished by post status setting (either private or a custom status registered with private=true) 
	//
	// force_visibility attribute does not impose condition caps, but affects the post_status of published posts.
	pps_register_attribute( 'force_visibility', 'post', array( 'label' => __( 'Force Visibility', 'pps' ), 'default' => 'none', 'suppress_item_edit_ui' => array( 'object' => true ) ) );
	
	// note: post_status is NOT stored to the attributes table
	pps_register_attribute( 'post_status', 'post', array( 'label' => __( 'Post Status', 'pps' ) ) );

	if ( ! defined( 'PPS_CUSTOM_PRIVACY_EDIT_CAPS' ) )
		define( 'PPS_CUSTOM_PRIVACY_EDIT_CAPS', version_compare( PPC_VERSION, '2.0.29-beta', '<' ) || pp_get_option('custom_privacy_edit_caps') );
	
	// register each custom post status as an attribute condition with mapped caps
	foreach( get_post_stati( array(), 'object' ) as $status => $status_obj ) {
		if ( ! empty($status_obj->private) ) {
			pps_register_condition( 'force_visibility', $status, array( 'label' => $status_obj->label ) );

			$suppress_edit_caps = defined( 'PP_SUPPRESS_PRIVACY_EDIT_CAPS' ) || ! PPS_CUSTOM_PRIVACY_EDIT_CAPS;

			$metacap_map = ( $suppress_edit_caps ) ? array( 'read_post' => "read_{$status}_posts", 'edit_post' => "edit_private_posts", 'delete_post' => "delete_private_posts" ) : array( 'read_post' => "read_{$status}_posts", 'edit_post' => "edit_{$status}_posts", 'delete_post' => "delete_{$status}_posts" );
			$cap_map = ( $suppress_edit_caps ) ? array() : array( 'set_posts_status' => "set_posts_{$status}" );

			pps_register_condition(  'post_status', $status, array( 
									'label' => $status_obj->label, 
									'metacap_map' => $metacap_map,
									'cap_map' => $cap_map,
									'pattern_role_availability_requirement' => array( 'edit_posts' => 'edit_published_posts', 'delete_posts' => 'delete_published_posts' ),
									/*'pattern_role_availability_requirement' => array( 'edit_posts' => 'edit_private_posts', 'delete_posts' => 'delete_private_posts' ),*/
								) );
		}
	}
	
	if ( is_user_logged_in() && pp_get_option( 'draft_reading_exceptions' ) && ( pp_is_front() || ( defined('REST_REQUEST') && REST_REQUEST ) ) ) {
		global $wp_post_statuses;
		$wp_post_statuses['draft']->private = true;
		$wp_post_statuses['draft']->protected = false;
		
		$status_obj = get_post_status_object( 'draft' );
		
		pps_register_condition( 'post_status', 'draft', array( 
			'label' => $status_obj->label, 
			'metacap_map' => array( 'read_post' => 'read_draft_posts' ),
		) );
	}
	
	do_action( 'ppc_registrations' );
	
	//$pp_attributes->load_custom_conditions();
	
	do_action( 'pp_conditions_loaded' );

	$pp_attributes->process_status_caps();
}

function _pps_act_apply_config_options() {
	global $pp_plugin_page;
	if ( 'pp-stati' != $pp_plugin_page ) {
		if ( $disabled = (array) pp_get_option( 'disabled_post_status_conditions' ) ) {
			$pp_attributes = pps_init_attributes();
			$pp_attributes->attributes['post_status']->conditions = array_diff_key( $pp_attributes->attributes['post_status']->conditions, $disabled );

			global $wp_post_statuses;
			$disabled = array_diff_key( $disabled, get_post_stati( array( '_builtin' => true ) ) );
			$wp_post_statuses = array_diff_key( $wp_post_statuses, $disabled );
		}
	}
}


function pps_init_attributes() {
	global $pp_attributes;

	if ( empty( $pp_attributes ) ) {
		if ( ! did_action( 'pp_registrations' ) ) {
			require_once( dirname(__FILE__).'/attributes_pp.php' );
			$pp_attributes = new PP_RestrictionAttributes();
			$pp_attributes->process_status_caps();
		}
	}
	
	return $pp_attributes;
}

// args:
//   label = translated string
//   cap_map = array( 'base_cap_property' => restriction_cap_pattern ) where restriction_cap_pattern may contain "_posts" (will be converted to plural name of obj type)
//   metacap_map = array( 'meta_cap' => restriction_cap_pattern ) 
//   exemption_cap = base cap property corresponding to a capability whose presence in a role indicates the role should be credited with all caps for this status (i.e. if a role has $cap->publish_posts, it also has all 'restricted_submission' caps) 
function pps_register_condition( $attribute, $condition, $args = array() ) {
	$defaults = array( 'label' => $condition, 'cap_map' => array(), 'metacap_map' => array() );
	$args = array_merge( $defaults, $args );

	$pp_attributes = pps_init_attributes();

	if ( ! isset( $pp_attributes->attributes[$attribute] ) )
		return;

	$args['name'] = $condition;
	$pp_attributes->attributes[$attribute]->conditions[$condition] = (object) $args;
}

// args:
//   label = translated string
function pps_register_attribute( $attribute, $src_name = 'post', $args = array() ) {
	$defaults = array( 'label' => $attribute, 'taxonomies' => array() );
	$args = array_merge( $defaults, $args );
	$args['conditions'] = array();
	$args['src_name'] = $src_name;
	
	$pp_attributes = pps_init_attributes();
	$pp_attributes->attributes[ $attribute ] = (object) $args;
}

// $set_conditions[attribute][condition] = true
// $args = array( 'force_flush' => false );
function pps_set_item_condition( $attribute, $scope, $item_source, $item_id, $set_conditions, $assign_for = 'item', $args = array() ) {
	require_once( dirname(__FILE__).'/admin/role_assigner_pps.php' );
	return PPS_RoleAssigner::set_item_condition( $attribute, $scope, $item_source, $item_id, $set_conditions, $assign_for, $args );
}

// $args = array ( 'inherited_only' => false );
function pps_clear_item_condition ( $attribute, $scope, $item_source, $item_id, $assign_for, $args = array() ) {
	require_once( dirname(__FILE__).'/admin/role_assigner_pps.php' );
	return PPS_RoleAssigner::clear_item_condition ( $attribute, $scope, $item_source, $item_id, $assign_for, $args );
}

function _pps_flt_exclude_arbitrary_caps( $caps ) {
	$excluded = array( 'pp_define_post_status', 'pp_define_moderation', 'pp_define_privacy' );
	
	if ( ! pp_get_option( 'supplemental_cap_moderate_any' ) )
		$excluded []= 'pp_moderate_any';
	
	return array_merge( $caps, $excluded );
}

if ( ! function_exists( '_pp_' ) ) {	// introduced in Press Permit Core 2.3.17
	function _pp_( $string, $unused = '' ) {
		return __( $string );		
	}
}