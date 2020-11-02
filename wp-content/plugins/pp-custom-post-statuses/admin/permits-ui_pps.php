<?php
class PP_PermitsUI {
	public static function permission_status_ui( $html, $object_type, $type_caps, $role_name = '' ) {
		$pp_attributes = pps_init_attributes();
		
		// condition caps are mapped from basic meta cap, keyed by object type
		if ( isset($type_caps['edit_posts']) )
			$type_caps['edit_post'] = 'edit_post';
			
		if ( isset($type_caps['delete_posts']) )
			$type_caps['delete_post'] = 'delete_post';
			
		if ( isset($type_caps['read']) )
			$type_caps['read_post'] = 'read_post';
		//==========================
		
		if ( $attributes = self::get_related_attributes( array_keys($type_caps), $object_type, array( 'return' => 'object' ) ) ) {
			$html .='<div id="pp_select_custom_attribs">';
			
			foreach( $attributes as $attrib => $attrib_obj ) {
				if ( in_array( $attrib, array( 'set_visibility', 'force_visibility', 'default_visibility' ) ) )  // the attributes pertain to the bulk/auto setting of visibility
					continue;
				
				$organized_conditions = array();
				$attrib_caption = array();
				$attrib_caption[''] = ( isset($attrib_obj->role_label) ) ? sprintf( __( '%s: ', 'pps' ), $attrib_obj->role_label ) : sprintf( __( 'Custom %s: ', 'pps' ), $attrib_obj->label );
			
				if ( ! defined( 'PP_LEGACY_STATUS_ROLES_UI' ) ) {
					if ( empty( $type_caps['set_posts_status'] ) && ! empty( $type_caps['edit_published_posts'] ) ) {
						$type_obj = get_post_type_object($object_type);
						$type_caps['set_posts_status'] = $type_obj->cap->set_posts_status;
					}
				}
			
				if ( ! $conditions = self::get_available_conditions( $attrib, $type_caps, $object_type ) )
					continue;

				if ( 'post_status' == $attrib ) {
					$attrib_caption['moderation'] = __( 'Custom moderation: ', 'pps' );
					$attrib_caption['private'] = __( 'Custom Visibility: ', 'pps' );

					foreach( $conditions as $cond => $cond_obj ) {
						if ( 'private' == $cond )
							continue;
					
						if ( ( ( $cond == 'future' ) || ( $cond == 'pending' && ( ! pp_get_option( 'custom_pending_caps' ) || defined( 'PP_LEGACY_STATUS_ROLES_UI' ) ) ) ) && ( 0 === strpos( $role_name, 'submitter' ) ) )  // Pending status is currently settable with basic post caps, regardless of PP settings
							continue;
					
						if ( $status_obj = get_post_status_object($cond) ) {
							if ( $status_obj->private )
								$organized_conditions['private'][$cond] = $cond_obj;
							elseif( ! empty($status_obj->moderation) )
								$organized_conditions['moderation'][$cond] = $cond_obj;
							else
								$organized_conditions[''][$cond] = $cond_obj;
						}
					}
				} else
					$organized_conditions[''] = $conditions;
				
				foreach( $organized_conditions as $cond_class => $_conditions ) {
					$html .= '<div class="pp-attrib">';
					$did_attrib_caption = false;
					foreach( $_conditions as $cond => $cond_obj ) {
						if ( ! $cond )
							continue;
					
						if ( $check_caps = $pp_attributes->get_condition_caps( 'edit_post', $object_type, $attrib, $cond ) ) {	
							global $pp_current_user;
							if ( array_diff( $check_caps, array_keys( $pp_current_user->allcaps) ) ) {
								continue;
							}
						}
					
						if ( ! $did_attrib_caption ) {
							$html .= $attrib_caption[$cond_class] . '<br />';
							$did_attrib_caption = true;
						}
					
						$html .= '<p class="pp-checkbox pp-attrib">'
						. "<input type='checkbox' id='pp_select_cond_{$attrib}:{$cond}' name='pp_select_cond[]' value='{$attrib}:{$cond}' /> "
						. "<label id='lbl_pp_select_cond_{$attrib}:{$cond}' for='pp_select_cond_{$attrib}:{$cond}'>" . $cond_obj->label . '</label>'
						. '</p>';
					}
					$html .= '</div>';
				}
			}
			
			$html .= '</div>';
			
			if ( ! empty( $type_caps[ 'edit_others_posts' ] ) ) {
				$html .= '<p class="pp-checkbox">'
				. '<input type="checkbox" id="pp_select_cond_post_status:draft" name="pp_select_cond[]" value="post_status:draft" /> '
				. '<label id="lbl_pp_select_cond_post_status:draft" for="pp_select_cond_post_status:draft">' . __('Draft') . '</label>'
				. '</p>';
			}
		}
		
		return $html;
	}
	
	static function get_available_conditions( $attrib, $pattern_role_caps, $object_type, $return = 'object' ) {
		$pp_attributes = pps_init_attributes();
		
		if ( ! isset( $pp_attributes->attributes[$attrib]->conditions ) )
			return array();

		// map basic caps to corresponding meta cap (@todo: some way to avoid this?)
		if ( isset( $pattern_role_caps['edit_posts'] ) )
			$pattern_role_caps['edit_post'] = true;
			
		if ( isset( $pattern_role_caps['delete_posts'] ) )
			$pattern_role_caps['delete_post'] = true;
			
		if ( isset( $pattern_role_caps['read'] ) )
			$pattern_role_caps['read_post'] = true;
		//==========================

		$related_conditions = array();
		$metacap_map = ( isset( $pp_attributes->condition_metacap_map[$object_type] ) ) ? $pp_attributes->condition_metacap_map[$object_type] : array();
		$arr = array_intersect_key( array_merge($pp_attributes->condition_cap_map, $metacap_map), $pattern_role_caps );
		foreach( array_keys($arr) as $cap_name ) {
			if ( isset( $arr[$cap_name][$attrib] ) )
				$related_conditions = array_merge( $related_conditions, $arr[$cap_name][$attrib] );
		}
		
		if ( 'post_status' == $attrib ) {
			$related_conditions = array_intersect_key( $related_conditions, array_flip( pp_get_post_stati( array( 'post_type' => $object_type ) ) ) );
		}
		
		$conditions = array_intersect_key( $pp_attributes->attributes[$attrib]->conditions, $related_conditions );
		foreach( $conditions as $cond => $cond_obj ) {
			// if pattern role is disqualified for having 'edit_posts' but not 'edit_private_posts', etc.
			if ( ! empty( $conditions[$cond]->pattern_role_availability_requirement ) ) {
				foreach( $conditions[$cond]->pattern_role_availability_requirement as $if_present => $require_also ) {
					if ( in_array( $if_present, array_keys($pattern_role_caps) ) && array_diff( (array) $require_also, array_keys($pattern_role_caps) ) )
						unset( $conditions[$cond] );
				}
			}
			
			if ( ! empty( $conditions[$cond]->pattern_role_unavailable_if ) ) {
				if ( array_intersect_key( $pattern_role_caps, array_flip( (array) $conditions[$cond]->pattern_role_unavailable_if ) ) )
					unset( $conditions[$cond] );
			}
		}
		
		return ( 'object' == $return ) ? $conditions : array_keys($conditions);
	}

	static function get_related_attributes( $reqd_caps, $object_type, $args = array() ) {
		$pp_attributes = pps_init_attributes();
		
		$metacap_map = ( isset( $pp_attributes->condition_metacap_map[$object_type] ) ) ? $pp_attributes->condition_metacap_map[$object_type] : array();
		
		if ( ! $attrib_caps = array_intersect_key( array_merge( $pp_attributes->condition_cap_map, $metacap_map ), array_flip( (array) $reqd_caps ) ) ) {
			return array();
		}

		return array_intersect_key( $pp_attributes->attributes, pp_array_flatten( $attrib_caps ) );
	}
} // end class
