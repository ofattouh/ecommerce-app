<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PPP_StatusSave {
	public static function save( $status, $new = false ) {
		$arr_return = array( 'retval' => false, 'redirect' => '' );
		
		if ( strlen($status) > 20 )
			$status = substr( $status, 0, 20 );
		
		$status_obj = get_post_status_object( $status );
		
		if ( $new && $status_obj || in_array( $status, array( 'public', 'password' ) ) ) {
			$errors = new WP_Error();
			$errors->add( 'status_name', __( '<strong>ERROR</strong>: That status name is already registered. Please choose another one.', 'pps' ) );
			$arr_return['retval'] = $errors;
			return $arr_return;
		}
		
		if ( $status_obj || $new ) {
			if ( empty($_REQUEST['status_label']) && ! in_array( $status, array( 'pending', 'future', 'draft' ) ) ) {
				$errors = new WP_Error();
				$errors->add( 'status_label', __( '<strong>ERROR</strong>: Please enter a label for the status.', 'pps' ) );
				$arr_return['retval'] = $errors;
			} else {
				$custom_conditions = (array) get_option( "pp_custom_conditions_post_status" );

				if ( ! isset($custom_conditions[$status]) )
					$custom_conditions[$status] = array();
			
				$custom_conditions[$status]['label'] = sanitize_text_field($_REQUEST['status_label']);
				$custom_conditions[$status]['save_as_label'] = ( ! empty($_REQUEST['status_save_as_label']) ) ? sanitize_text_field($_REQUEST['status_save_as_label']) : '';
				$custom_conditions[$status]['publish_label'] = ( ! empty($_REQUEST['status_publish_label']) ) ? sanitize_text_field($_REQUEST['status_publish_label']) : '';
				
				if ( $new ) {
					$attrib_type = ( isset( $_REQUEST['attrib_type'] ) ) ? pp_sanitize_key($_REQUEST['attrib_type']) : '';
					if ( $attrib_type )
						$custom_conditions[$status][$attrib_type] = true;
				}
				
				pp_update_option( "custom_conditions_post_status", $custom_conditions );
				
				$arr_return['redirect'] = ( $new ) ? str_replace( 'pp-status-new', 'pp-stati', $_SERVER['REQUEST_URI'] ) : $_SERVER['REQUEST_URI'];
				
				// Edit Flow integration
				if ( ! empty($_REQUEST['attrib_type']) && ( 'moderation' == $_REQUEST['attrib_type'] ) && taxonomy_exists('post_status') && ! defined('PP_DISABLE_EF_STATUS_SYNC') ) {
					if ( ! $term = get_term_by( 'slug', $status, 'post_status' ) ) {
						// if another taxonomy already has a term with this slug, don't get implicated in that mess
						$term = (object) array( 'taxonomy' => 'post_status', 'alias_of' => '', 'description' => '', 'parent' => 0, 'slug' => $status, 'name' => $custom_conditions[$status]['label']);
						$slug = wp_unique_term_slug($status, $term);
						if ( $slug == $status )
							wp_insert_term( $custom_conditions[$status]['label'], 'post_status', array( 'slug' => $status ) );
					} else {
						if ( $custom_conditions[$status]['label'] != $term->name ) {
							wp_update_term( $term->term_id, 'post_status', array( 'name' => $custom_conditions[$status]['label'] ) );
						}
					}
				}
			}

			// === store status post types ===
			if ( ! $status_post_types = pp_get_option( 'status_post_types' ) )
				$status_post_types = array();
			
			if ( ! empty( $_REQUEST['pp_status_all_types'] ) ) {
				$status_post_types[$status] = array();

			} elseif ( isset( $_REQUEST['pp_status_post_types'] ) ) {
				if ( ! isset( $status_post_types[$status] ) )
					$status_post_types[$status] = array();
			
				if ( $add_types = array_intersect( $_REQUEST['pp_status_post_types'], array('1', true, 1) ) )
					$status_post_types[$status] = array_unique( array_merge( $status_post_types[$status], array_map( 'pp_sanitize_key', array_keys($add_types) ) ) );
				
				if ( $remove_types = array_diff( $_REQUEST['pp_status_post_types'], array('1', true, 1) ) )
					$status_post_types[$status] = array_diff( $status_post_types[$status], array_keys($remove_types) );
			}
			
			pp_update_option( 'status_post_types', $status_post_types );
			
			// === store status order ===
			if ( ! $status_order = pp_get_option( 'status_order' ) )
				$status_order = array();
			
			if ( ! empty($_REQUEST['status_order']) && is_numeric( $_REQUEST['status_order'] ) || ! empty( $status_order[$status] ) ) {  // don't store value if no entry and not already stored
				$status_order[$status] = (int) $_REQUEST['status_order'];
				pp_update_option( "status_order", $status_order );
			}
		} else {
			$errors = new WP_Error();
			$errors->add( 'condition_name', __( '<strong>ERROR</strong>: The specified status does not exist.', 'pps' ) );
			$arr_return['retval'] = $errors;
		}
		
		return $arr_return;
	}
}
