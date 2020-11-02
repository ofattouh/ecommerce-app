<?php
/**
 * Additional metacap mapping for PP-defined conditions
 * 
 * @package PP
 * @author Kevin Behrens <kevin@agapetry.net>
 * @copyright Copyright (c) 2011-2013, Agapetry Creations LLC
 * 
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
 
class PP_Meta_Caps {
	var $do_status_cap_map = false;

	function __construct() {
		add_filter( 'map_meta_cap', array( &$this, 'flt_map_status_caps' ), 1, 4 );  // register early so other filters have a chance to review status/condition caps we append
	}

	function flt_map_status_caps( $caps, $meta_cap, $user_id, $wp_args, $args = array() ) {
		global $current_user, $pp_attributes;
		
		if ( ! $this->do_status_cap_map || ( ( $user_id == $current_user->ID ) && pp_is_content_administrator() ) )  // PP_QueryInterceptor::generate_where_clause selectively enables this (rather than repeatedly adding/removing filter)
			return $caps;

		$meta_cap = str_replace( '_page', '_post', $meta_cap );

		if ( isset( $args['post'] ) ) {
			$post = $args['post'];
		} else {
			if ( empty($wp_args[0]) )
				return $caps;

			if ( ! $post = get_post( $wp_args[0] ) )
				return $caps;
		}

		if ( in_array( $post->post_status, array( 'public', 'private' ) ) )
			return $caps;
		
		//if ( in_array( $post->post_type, array( 'revision', 'attachment' ) ) )
			//	if ( ! $post = get_post( $post->post_parent ) )
			//		return $caps;

		// @todo: collapse condition_metacap_map and condition_cap_map arrays to post_status only
		if ( isset( $pp_attributes->attributes['post_status']->conditions[$post->post_status] ) ) {
			$map_caps = ( isset( $pp_attributes->condition_metacap_map[$post->post_type][$meta_cap] ) ) ? $pp_attributes->condition_metacap_map[$post->post_type][$meta_cap]['post_status'] : array();

			if ( $custom_mapped_caps = array_intersect_key( $pp_attributes->condition_cap_map, $caps ) )
				$map_caps = array_merge( $map_caps, $custom_mapped_caps['post_status'] );

			if ( isset( $map_caps[$post->post_status] ) ) {
				$caps = array_merge( $caps, (array) $map_caps[$post->post_status] );
				
				if ( ! empty( $wp_args[0] ) ) {
					$post_status = false;
					
					if ( is_scalar($wp_args[0]) ) {
						if ( $_post = get_post( $wp_args[0] ) ) {
							$post_status = $_post->post_status;
							$post_type = $_post->post_type;
						}
					} else {
						$wp_args[0] = (object) $wp_args[0];
						
						if ( ! empty( $wp_args[0]->post_status ) ) {
							$post_status = $wp_args[0]->post_status;
							$post_type = $wp_args[0]->post_type;
						}
					}
	
					if ( $post_status ) {
						$status_obj = get_post_status_object( $post_status );
						
						if ( $status_obj->private ) {
							if ( $type_obj = get_post_type_object( $post_type ) ) {
								if ( 0 === strpos( $meta_cap, 'read_' ) ) {
									$caps = array_diff( $caps, array($type_obj->cap->read_private_posts) );
									
									if ( ! PPS_CUSTOM_PRIVACY_EDIT_CAPS ) {
										// extend Custom Privacy Edit Caps exemption so basic Editor role for the post type also enables reading posts with a custom privacy status
										global $pp_current_user;
										if ( ( $user_id == $pp_current_user->ID ) && ! empty( $pp_current_user->allcaps[$type_obj->cap->edit_private_posts] ) && ! empty( $pp_current_user->allcaps[$type_obj->cap->edit_others_posts] ) ) {
											$caps[]= $type_obj->cap->edit_private_posts;
											$caps = array_diff( $caps, array($map_caps[$post->post_status]) );
										}
									}
								} elseif ( PPS_CUSTOM_PRIVACY_EDIT_CAPS ) {
									if ( 0 === strpos( $meta_cap, 'edit_' ) )
										$caps = array_diff( $caps, array($type_obj->cap->edit_private_posts) );
									elseif ( 0 === strpos( $meta_cap, 'delete_' ) )
										$caps = array_diff( $caps, array($type_obj->cap->delete_private_posts) );
								}
							}
						}
					}
				}
				
				if ( ( 'draft' == $post->post_status ) && in_array( "read_draft_{$post_type}s", $caps ) ) {
					if ( $type_obj = get_post_type_object( $post_type ) ) {
						$caps = array_diff( $caps, array( $type_obj->cap->edit_others_posts ) );
					}
				}
				
				$caps = apply_filters( 'pp_map_status_caps', array_unique( $caps ), $meta_cap, $user_id, $post->ID );
			}
		}

		return $caps;
	}
}
