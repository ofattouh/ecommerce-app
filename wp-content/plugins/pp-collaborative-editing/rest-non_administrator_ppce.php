<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PPCE_REST {
	var $request = '';
	var $method = '';
	var $is_view_method = false;
	var $endpoint_class = '';
	var $taxonomy = '';
	var $post_type = '';
	var $operation = '';

	function __construct() {
		add_filter( 'pp_rest_post_cap_requirement', array( &$this, 'rest_post_cap_requirement' ), 10, 2 );
		
		//foreach( pp_get_enabled_post_types() as $post_type )
		//	add_filter( "rest_{$post_type}_collection_params", array( &$this, 'rest_post_collection_params' ), 10, 2 );
	}
	
	/*
	function rest_post_collection_params( $args, $post_type_obj ) {
		$params = WP_REST_Request::get_body_params();
		pp_errlog( $params );
		pp_errlog( '--------------' );
		
		$params = WP_REST_Request::get_url_params();
		pp_errlog( $params );
		pp_errlog( '--------------' );
		
		return $args;
	}
	*/
	
	function rest_post_cap_requirement( $orig_cap, $item_id ) {
		if ( 'edit' == $this->operation ) {
			$post_type = get_post_field( 'post_type', $item_id );
			
			if ( $type_obj = get_post_type_object( $post_type ) ) {
				if ( $orig_cap == $type_obj->cap->read_post ) {
					$orig_cap = $type_obj->cap->edit_post;
				}
			}
		}
	
		return $orig_cap;
	}
	
	function pre_dispatch( $rest_response, $rest_server, $request ) {
		$this->method = $request->get_method();
		$path   = $request->get_route();
		
		foreach ( $rest_server->get_routes() as $route => $handlers ) {
			if ( ! $match = preg_match( '@^' . $route . '$@i', $path, $args ) )
				continue;

			foreach ( $handlers as $handler ) {
				if ( ! empty( $handler['methods'][ $this->method ] ) && is_array($handler['callback']) && is_object($handler['callback'][0]) ) {
					$this->request = $request;
					$this->endpoint_class = get_class( $handler['callback'][0] );
					
					$this->is_view_method = in_array( $this->method, array( WP_REST_Server::READABLE, 'GET' ) );
					
					// rest_operation is used primarily for voluntary filtering of get_items (for WYSIWY can edit, etc.)
					if ( ! empty( $_REQUEST['operation'] ) ) {
						$this->operation = $_REQUEST['operation'];
					} else {
						$this->operation = ( isset( $_REQUEST['context'] ) ) ? sanitize_key( $_REQUEST['context'] ) : '';
						if ( 'view' == $this->operation )
							$this->operation = 'read';
					}
					
					$valid_ops = array( 'edit', 'assign', 'manage', 'delete' );
					if ( $this->is_view_method )
						$valid_ops []= 'read';
					
					// NOTE: setting or default may be adapted downstream
					if ( ! in_array( $this->operation, $valid_ops ) ) {
						$this->operation = ( $this->is_view_method ) ? 'read' : 'edit';
					}
					
					switch( $this->endpoint_class ) {
						case 'WP_REST_Posts_Terms_Controller':		// note: no longer exists as of v2 beta 12
							// (add or remove a term from a post)

							if ( empty( $args['post_id'] ) ) break;
							
							// back post type out of path because controller object does not expose it
							$type_base = $this->get_path_element( $path, 2 ); // second string from right
							$this->post_type = $this->get_type_from_rest_base( $type_base );

							// back taxonomy out of path because controller object does not expose it
							$tx_base = $this->get_path_element( $path );
							
							if ( ! $this->taxonomy = $this->get_taxonomy_from_rest_base( $tx_base ) )
								break;
							
							if ( ! $this->post_type )
								break;
							
							if ( empty( $args['term_id'] ) ) break;
							
							$required_operation = ( 'read' == $this->operation ) ? 'read' : 'assign';
							$user_terms = get_terms( $this->taxonomy, array( 'required_operation' => $required_operation, 'hide_empty' => 0, 'fields' => 'ids', 'post_type' => $this->post_type ) );
							if ( ! in_array( $args['term_id'], $user_terms ) ) { return self::rest_denied(); }
							break;
						
						case 'WP_REST_Terms_Controller':
							if ( ! empty( $_REQUEST['post_type'] ) )
								$this->post_type = sanitize_key( $_REQUEST['post_type'] );
							
							// back taxonomy out of path because controller object does not expose it
							$tx_base = $this->get_path_element( $path );
							if ( ! $this->taxonomy = $this->get_taxonomy_from_rest_base( $tx_base ) )
								break;
						
							$required_operation = ( 'read' == $this->operation ) ? 'read' : 'manage';
							
							if ( ! empty( $_REQUEST['post'] ) ) {
								$check_cap = ( 'read' == $required_operation ) ? 'read_post' : 'edit_post';
								
								if ( ! current_user_can( $check_cap, intval($_REQUEST['post']) ) ) { return self::rest_denied(); }
							}
							
							if ( empty( $args['id'] ) ) break;
							
							$user_terms = get_terms( $this->taxonomy, array( 'required_operation' => $required_operation, 'hide_empty' => 0, 'fields' => 'ids' ) );
							if ( ! in_array( $args['id'], $user_terms ) ) { return self::rest_denied(); }
							break;
						
						case 'WP_REST_Posts_Controller':
							$post_id = $this->get_id_element( $path );
							
							// back post type out of path because controller object does not expose it
							$type_base = $this->get_path_element( $path );
							
							$this->post_type = $this->get_type_from_rest_base( $type_base );
							
							// do this here because WP does not trigger a capability check if the post type is public
							if ( $post_id && ( 'read' == $this->operation ) && in_array( $this->post_type, pp_get_enabled_post_types() ) ) {
								$post_status_obj = get_post_status_object( get_post_field( 'post_status', $post_id ) );
								
								if ( $post_status_obj->public && ! current_user_can( 'read_post', $post_id ) ) { return self::rest_denied(); }
							}
							
							if ( ! $this->post_type )
								break;

							if ( $this->is_view_method )
								break;
						
							break;
							
					} // end switch
				}
			}
		}
	}  // end function pre_dispatch

	function rest_denied() {
		return new WP_Error( 'rest_forbidden', __( "You don't have permission to do this." ), array( 'status' => 403 ) );	
	}
	
	function get_id_element( $path, $position_from_right = 0 ) {
		$arr_path = explode( '/', $path );
		
		$count = -1;
		for( $i=count($arr_path) - 1; $i>=0; $i-- ) {
			$count++;
			
			if ( $count == $position_from_right )
				return $arr_path[$i];
		}
		
		return '';
	}
	
	function get_path_element( $path, $string_num_from_right = 1 ) {
		$arr_path = explode( '/', $path );
		
		$count = 0;
		for( $i=count($arr_path) - 1; $i>=0; $i-- ) {
			if ( is_numeric( $arr_path[$i] ) )
				continue;
			
			$count++;
			
			if ( $count == $string_num_from_right )
				return $arr_path[$i];
		}
		
		return '';
	}
	
	function get_taxonomy_from_rest_base( $rest_base ) {
		if ( $taxs = get_taxonomies( array( 'rest_base' => $rest_base ) ) ) {
			$taxonomy = reset( $taxs );
			return $taxonomy;
		} elseif( taxonomy_exists( $rest_base ) ) {
			return $rest_base;
		} else {
			return false;
		}
	}
	
	private function get_type_from_rest_base( $rest_base ) {
		if ( $types = get_post_types( array( 'rest_base' => $rest_base ) ) ) {
			$post_type = reset( $types );
			return $post_type;
		} elseif( post_type_exists( $post_type ) ) {
			return $post_type;
		} else {
			return false;
		}
	}
}