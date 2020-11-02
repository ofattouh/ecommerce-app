<?php
/**
 * PP_RestrictionAttributes
 * 
 * @package PP
 * @author Kevin Behrens <kevin@agapetry.net>
 * @copyright Copyright (c) 2011-2013, Agapetry Creations LLC
 * 
 */
class PP_RestrictionAttributes {
	var $attributes = array();				// attributes[attribute] = object with the following properties: conditions, label, taxonomies
	var $condition_cap_map = array();		// condition_cap_map[basic_cap_name][attribute][condition] = condition_cap_name
	var $condition_metacap_map = array();	// condition_metacap_map[object_type][basic_metacap_name][attribute][condition] = condition_cap_name
	var $all_custom_condition_caps = array();
	var $pattern_role_cond_caps = array();
	
	function __construct() {
		add_filter( 'pp_pattern_role_caps', array( &$this, 'flt_log_pattern_role_cond_caps' ) );
		add_filter( 'pp_exclude_arbitrary_caps', array( &$this, 'flt_exclude_arbitrary_caps' ) );
		add_filter( 'pp_get_typecast_caps', array( &$this, 'flt_get_typecast_caps' ), 10, 3 );
		add_filter( 'pp_administrator_caps', array( &$this, 'flt_administrator_caps' ) ); 
		add_filter( 'pp_base_cap_replacements', array( $this, 'flt_base_cap_replacements' ), 10, 3 );
	}

	function flt_base_cap_replacements( $replace_caps, $reqd_caps, $object_type ) {
		if ( isset( $this->all_privacy_caps[$object_type] ) && is_array( $this->all_privacy_caps[$object_type] ) )  {
			if ( $cond_caps = array_intersect_key( $this->all_privacy_caps[$object_type], array_fill_keys( $reqd_caps, true ) ) ) {
				// note: for author's editing access to their own post, private and custom private post_status caps will be removed, but _published caps will remain
				$replace_caps = array_merge( $cond_caps, $replace_caps );
			}
		}
		
		return $replace_caps;
	}
	
	function flt_administrator_caps( $caps ) {
		return array_merge( $caps, array_fill_keys( array_keys( pp_array_flatten( $this->all_custom_condition_caps ) ), true ) );
	}
	
	function flt_get_typecast_caps( $caps, $arr_name, $type_obj ) {
		$base_role_name = $arr_name[0];
		$src_name = $arr_name[1];
		$object_type = $arr_name[2];
		$attribute = ( ! empty($arr_name[3]) ) ? $arr_name[3] : '';
		$condition = ( ! empty($arr_name[4]) ) ? $arr_name[4] : '';

		// If the typecast role assignment is for a specific condition (i.e. custom post_status), only add caps for that condition
		if ( $attribute ) {
			// -------- exclude "published_" and "private_" caps from typecasting except for custom private post status -----------
			
			// NOTE: post_status casting involves both read and edit caps (whichever are in pattern rolecaps).
			if ( 'post_status' == $attribute ) {
				$status_obj = get_post_status_object( $condition );

				// Due to complications with the publishing metabox and WP edit_post() handler, 'publish' and 'private' caps need to be included with custom privacy caps
				// However, it's not necessary to include the deletion caps. Withhold them unless an Editor role is assigned for standard statuses, unless constant is defined.
				if ( ! defined( 'PP_LEGACY_STATUS_CAPS' ) )
					$caps = array_diff_key( $caps, array_fill_keys( array( 'delete_published_posts', 'delete_private_posts', 'delete_others_posts' ), true ) );

				if ( empty($status_obj) || ! $status_obj->private ) {
					$caps = array_diff_key( $caps, array_fill_keys( array( 'edit_published_posts', 'publish_posts', 'read_private_posts', 'edit_private_posts', 'delete_published_posts', 'delete_private_posts' ), true ) );
				}
				
				if ( $status_obj->private && pp_get_option( 'custom_privacy_edit_caps' ) && ! defined( 'PP_LEGACY_STATUS_EDIT_CAPS' ) )
					$caps = array_diff_key( $caps, array_fill_keys( array( 'edit_published_posts' ), true ) );
			}
			// ------------------------------------------------------------------------------------------------------------------------------------

			$match_caps = $caps;
			if ( isset( $caps['read'] ) )
				$match_caps['read_post'] = 'read_post';
				
			if ( isset( $caps['edit_posts'] ) )
				$match_caps['edit_post'] = 'edit_post';
				
			if ( isset( $caps['delete_posts'] ) )
				$match_caps['delete_post'] = 'delete_post';

			$caps = array_merge( $caps, $this->get_condition_caps( $match_caps, $object_type, $attribute, $condition ) );

		} elseif ( 'term_taxonomy' != $src_name ) {
			$plural_name = ( isset( $type_obj->plural_name ) ) ? $type_obj->plural_name : $object_type . 's';
		
			// also cast all condition caps which are in the pattern role
			if ( ! empty($this->pattern_role_cond_caps[$base_role_name]) ) {
				foreach( array_keys($this->pattern_role_cond_caps[$base_role_name]) as $cap_name ) {
					$cast_cap_name = str_replace( '_posts', "_{$plural_name}", $cap_name );
					$caps[ $cast_cap_name ] = $cast_cap_name;
				}
			}
		}

		return $caps;
	}
	
	function flt_log_pattern_role_cond_caps( $pattern_role_caps ) {
		foreach( array_keys($pattern_role_caps) as $role_name ) {
			// log condition caps for the "post" type
			if ( isset($this->all_custom_condition_caps['post']) )
				$this->pattern_role_cond_caps[$role_name] = array_intersect_key( $pattern_role_caps[$role_name], $this->all_custom_condition_caps['post'] );
			else
				$this->pattern_role_cond_caps[$role_name] = array();
		}
		return $pattern_role_caps;
	}

	// prevent condition caps from being included when a pattern role is assigned without any condition specification
	function flt_exclude_arbitrary_caps( $exclude_caps ) {
		return array_merge( $exclude_caps, pp_array_flatten( $this->all_custom_condition_caps ) );
	}
	
	function is_metacap( $caps ) {
		return (bool) array_intersect( (array) $caps, array( 'read_post', 'read_page', 'edit_post', 'edit_page', 'delete_post', 'delete_page' ) );
	}
	
	function process_status_caps() {
		$condition_cap_map = array();
		$condition_metacap_map = array();
		$all_cond_caps = array( 'post' => array() );
		$all_privacy_caps = array( 'post' => array() );
		$all_moderation_caps = array( 'post' => array() );
		
		foreach( array_keys($this->attributes['post_status']->conditions) as $cond ) {
			$status_obj = get_post_status_object( $cond );
		
			foreach( pp_get_enabled_post_types( array(), 'object' ) as $object_type => $type_obj ) {
				// convert 'edit_restricted_posts' to 'edit_restricted_pages', etc.
				$plural_name = ( isset( $type_obj->plural_name ) ) ? $type_obj->plural_name : $object_type . 's';
			
				// map condition caps to post meta caps( 'edit_post', 'delete_post', etc. ) because (1) mapping to expanded caps is problematic b/c for private posts, 'edit_private_posts' is required but 'edit_posts' is not
				//																					(2) WP converts type-specific meta caps back to basic metacap equivalent before calling 'map_meta_cap'
				foreach( $this->attributes['post_status']->conditions[$cond]->metacap_map as $base_cap_property => $condition_cap_pattern ) {
					// If the type object has "edit_restricted_posts" defined, use it.
					$replacement_cap = ( isset( $type_obj->cap->$condition_cap_pattern ) ) ? $type_obj->cap->$condition_cap_pattern : str_replace( '_posts', "_{$plural_name}", $condition_cap_pattern );

					$condition_metacap_map[ $object_type ][ $base_cap_property ][ 'post_status' ][ $cond ] = $replacement_cap;

					switch( $base_cap_property ) {
						case 'read_post':
							$type_cap = 'read';
							break;
						case 'edit_post':
							$type_cap = $type_obj->cap->edit_posts;
							break;
						case 'delete_post':
							if ( isset( $type_obj->cap->delete_posts ) )
								$type_cap = $type_obj->cap->delete_posts;
							else
								$type_cap = str_replace( 'edit_', 'delete_', $type_obj->cap->edit_posts );
							break;
						default:
							$type_cap = $base_cap_property;
					}
					$all_cond_caps[$object_type][$replacement_cap] = $type_cap;
					
					if ( ! empty( $status_obj->private ) )
						$all_privacy_caps[$object_type][$replacement_cap] = $type_cap;
						
					if ( ! empty( $status_obj->moderation ) )
						$all_moderation_caps[$object_type][$replacement_cap] = $type_cap;
				}

				foreach( $this->attributes['post_status']->conditions[$cond]->cap_map as $base_cap_property => $condition_cap_pattern ) {
					// If the type object has "edit_restricted_posts" defined, use it.
					$replacement_cap = ( isset( $type_obj->cap->$condition_cap_pattern ) ) ? $type_obj->cap->$condition_cap_pattern : str_replace( '_posts', "_{$plural_name}", $condition_cap_pattern );

					$cap_name = ( isset($type_obj->cap->$base_cap_property) ) ? $type_obj->cap->$base_cap_property : $base_cap_property;
					$condition_cap_map[ $cap_name ][ 'post_status' ][ $cond ] = $replacement_cap;

					$all_cond_caps[$object_type][$replacement_cap] = $cap_name;
					
					if ( ! empty( $status_obj->private ) )
						$all_privacy_caps[$object_type][$replacement_cap] = $cap_name;
						
					if ( ! empty( $status_obj->moderation ) )
						$all_moderation_caps[$object_type][$replacement_cap] = $cap_name;
				}
			} // end foreach object type
		} // end foreach condition

		$this->condition_metacap_map = $condition_metacap_map;
		$this->condition_cap_map = $condition_cap_map;

		$this->all_custom_condition_caps = $all_cond_caps;
		$this->all_privacy_caps = $all_privacy_caps;
		$this->all_moderation_caps = $all_moderation_caps;
	}

	function get_condition_caps( $reqd_caps, $object_type, $attribute, $conditions ) {
		$cond_caps = array();

		$reqd_caps = (array) $reqd_caps;
		
		foreach( $reqd_caps as $base_cap ) {
			foreach( (array) $conditions as $cond ) {
				if ( ! empty( $this->condition_cap_map[$base_cap][$attribute][$cond] ) )
					$cond_caps[] = $this->condition_cap_map[$base_cap][$attribute][$cond];
					
				if ( ! empty( $this->condition_metacap_map[$object_type][$base_cap][$attribute][$cond] ) )
					$cond_caps[] = $this->condition_metacap_map[$object_type][$base_cap][$attribute][$cond];
			}
		}

		return array_unique( $cond_caps );
	}
	
	// returns $arr[item_id][condition] = true or (if return_array=true) array( 'inherited_from' => $row->inherited_from )
	// src_name = item source name (i.e. 'post') 
	//
	function get_item_condition( $src_name, $attribute, $args = array() ) {
		require_once( dirname(__FILE__).'/admin/attributes-admin_pp.php' );
		return apply_filters( 'pp_get_item_condition', PP_AttributesAdmin::get_item_condition( $src_name, $attribute, $args ), $src_name, $attribute, $args );
	}
} // end class PP_RestrictionAttributes
