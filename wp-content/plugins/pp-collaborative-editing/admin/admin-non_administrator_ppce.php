<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $ppce_admin_non_administrator;
$ppce_admin_non_administrator = new PPCE_Admin_NonAdministrator();

class PPCE_Admin_NonAdministrator
{
	function __construct() {
		global $pagenow;
		
		add_filter( 'get_terms_args', array( &$this, 'flt_get_terms_args' ), 50, 2 );
		add_filter( 'terms_clauses', array( &$this, 'flt_get_terms_clauses' ), 2, 3 );
		add_filter( 'pp_get_terms_force_vars', array( &$this, 'flt_get_terms_force_vars' ), 10, 4 );
		add_filter( 'pp_get_terms_operation', array( &$this, 'flt_get_terms_operation' ), 10, 3 );
		add_filter( 'pp_get_terms_universal_exceptions', array( &$this, 'flt_get_terms_universal_exceptions' ), 10, 4 );
		add_filter( 'pp_get_terms_exceptions', array( &$this, 'flt_get_terms_exceptions' ), 10, 6 );
		add_filter( 'pp_get_terms_additional', array( &$this, 'flt_get_terms_additional' ), 10, 5 );
		
		add_filter( 'pp_get_posts_operation', array( &$this, 'flt_get_posts_operation' ), 10, 2 );
		add_filter( 'pp_is_front', array( &$this, 'flt_is_front' ) );
		
		// REST filters
		add_filter( 'get_object_terms', array( &$this, 'flt_rest_get_object_terms' ), 10, 4 );
		
		add_filter( 'pp_generate_where_clause_force_vars', array( &$this, 'flt_where_clause_revisionary' ), 10, 3 );
		
		require_once( dirname(__FILE__).'/comments-interceptor-admin_ppce.php' );
	}
	
	function flt_is_front( $is_front ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			global $ppce_rest;
			
			if ( 'read' == $ppce_rest->operation ) // rest_pre_dispatch filter only allows with matching rest method (GET, etc)
				return true;
		}
		
		return $is_front;
	}
	
	function flt_get_posts_operation( $required_operation, $args ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			global $ppce_rest;
			
			if ( 'read' == $ppce_rest->operation ) {  // rest_pre_dispatch filter only allows with matching rest method (GET, etc)
				return 'read';
			}
		}
		
		return $required_operation;
	}
	
	function flt_get_terms_operation( $required_operation, $taxonomies, $args ) {
		global $pagenow;
	
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			global $ppce_rest;
			
			// (Terms listing)
			if ( 'WP_REST_Terms_Controller' == $ppce_rest->endpoint_class ) {
				return ( 'edit' == $ppce_rest->operation ) ? 'manage' : $ppce_rest->operation;
			}
		} elseif ( in_array( $pagenow, array( 'edit-tags.php', 'nav-menus.php' ) ) ) {
			$required_operation = ( empty( $_REQUEST['tag_ID'] ) && ( empty( $args['name'] ) || ( 'parent' != $args['name'] ) ) ) ? 'manage' : 'associate';
		}
		
		return $required_operation;
	}

	function flt_get_terms_args( $args, $taxonomies ) {
		// terms query should be limited to a single object type for post.php, post-new.php, so only return caps for that object type	
		global $pagenow;
		
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			if ( empty( $args['object_type'] ) ) {
				global $ppce_rest;		
				$args['object_type'] = $ppce_rest->post_type;
			}
		} elseif ( in_array( $pagenow, array( 'post.php', 'post-new.php', 'press-this.php' ) ) )
			$args['object_type'] = pp_find_post_type();
	
		return $args;
	}
	
	function flt_get_terms_clauses( $clauses, $taxonomies, $args ) {
		// If we are dealing with a post taxonomy on a Post Edit Form, include currently stored terms.  User will still not be able to remove them without proper editing roles for object.
		global $pagenow;
		
		if ( 'post.php' == $pagenow ) {
				if ( $object_id = pp_get_post_id() ) {
					// --------------- Polylang workaround -------------------
					if ( count($taxonomies) > 0 ) {
						return $clauses;
					}

					$tx_name = ( is_object( $taxonomies[0] ) && isset( $taxonomies[0]->name ) ) ? $taxonomies[0]->name : $taxonomies[0];
					
					if ( ! pp_is_taxonomy_enabled( $tx_name ) )
						return $clauses;
					//---------------------------------------------------------
					
					if ( ! empty( $args['name'] ) && pp_wp_ver( '4.2' ) && ! empty( $_POST ) ) // don't filter get_terms() call in edit_post(), which invalidates entry term selection if existing term is detected
						return $clauses;
				
					if ( $tt_ids = ppce_get_object_terms( $object_id, $taxonomies[0], array( 'fields' => 'tt_ids', 'pp_no_filter' => true ) ) ) {
						$clauses['where'] = "( {$clauses['where']} OR tt.term_taxonomy_id IN ('" . implode( "','", $tt_ids ) . "') )";
					}
				}
		}
		
		return $clauses;
	}
	
	function flt_rest_get_object_terms( $terms, $object_id_array, $taxonomy_array, $args ) {
		global $ppce_rest;
		
		if ( empty($ppce_rest) || ! $ppce_rest->request || ( WP_REST_Server::READABLE != $ppce_rest->method ) )
			return $terms;

		switch( $ppce_rest->endpoint_class ) {
			case 'WP_REST_Posts_Terms_Controller':
				$operation = ( 'read' == $ppce_rest->operation ) ? 'read' : 'assign';
				$user_terms = get_terms( $ppce_rest->taxonomy, array( 'required_operation' => $operation, 'hide_empty' => 0, 'fields' => 'ids', 'post_type' => $ppce_rest->post_type ) );
				
				foreach( $terms as $k => $term ) {
					if ( ( $term->taxonomy == $ppce_rest->taxonomy ) && ! in_array( $term->term_id, $user_terms ) ) {
						unset( $terms[$k] );
					}
				}
	
				break;
		}
		
		return $terms;
	}
	
	function flt_get_terms_universal_exceptions( $universal, $required_operation, $taxonomy, $args ) {
		if ( 'assign' == $required_operation ) {
			require_once( dirname(__FILE__).'/terms-interceptor-admin_ppce.php' );
			return PPCE_TermsInterceptorAdmin::flt_get_terms_universal_exceptions( $universal, $required_operation, $taxonomy, $args );
		}

		return $universal;
	}
	
	function flt_get_terms_exceptions( $tt_ids, $required_operation, $mod_type, $post_type, $taxonomy, $args = array() ) {
		require_once( dirname(__FILE__).'/terms-interceptor-admin_ppce.php' );
		return PPCE_TermsInterceptorAdmin::flt_get_terms_exceptions( $tt_ids, $required_operation, $mod_type, $post_type, $taxonomy, $args );

		return $tt_ids;
	}
	
	function flt_get_terms_additional( $additional_tt_ids, $required_operation, $post_type, $taxonomy, $args ) {
		if ( 'assign' == $required_operation ) {
			global $pp_current_user;
			
			if ( $_edit_tt_ids = $pp_current_user->get_exception_terms( 'edit', 'additional', $post_type, $taxonomy, array( 'status' => true ) ) )
				$additional_tt_ids = array_merge( $additional_tt_ids, pp_array_flatten( $_edit_tt_ids ) );
		}

		return $additional_tt_ids;
	}
	
	function flt_where_clause_revisionary( $force_vars, $src_name, $args ) {
		// accomodate editing of published posts/pages to revision
		if ( ! defined( 'RVY_VERSION' ) )
			return $force_vars;

		global $revisionary;
		if ( ! empty( $revisionary->skip_revision_allowance ) )
			return $force_vars;
		
		$return = array();

		// enable authors to view / edit / approve revisions to their published posts
		if ( ! defined( 'HIDE_REVISIONS_FROM_AUTHOR' ) ) {
			global $wpdb;
			$src_table = ( $args['source_alias'] ) ? $args['source_alias'] : $wpdb->posts;
			
			if ( ! empty($args['user']->ID) ) {
				if ( $owner_object_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type IN (%s,%s) AND post_author = %d", 'revision', $args['object_type'], $args['user']->ID ) ) ) {
					$return['parent_clause'] = "( $src_table.post_type = 'revision' AND ( post_author = " . intval($args['user']->ID) . " OR $src_table.post_parent IN ('" . implode( "','", $owner_object_ids ) . "') ) ) OR "; 
				}
			}
		}

		return ( $return ) ? array_merge( (array) $force_vars, $return ) : $force_vars;
	}
	
	/*
	function filter_add_new_content_links() {
		global $pp, $submenu;
	
		// workaround for WP's universal inclusion of "Add New"
		foreach ( get_post_types( array( 'public' => true ), 'object' ) as $_post_type => $type_obj ) {			
			$edit_key = ( 'post' == $_post_type ) ? 'edit.php' : "edit.php?post_type=$_post_type";
			$add_key = ( 'post' == $_post_type ) ? 'post-new.php' :  "post-new.php?post_type=$_post_type";
			
			if ( isset($submenu[$edit_key]) ) {
				foreach ( $submenu[$edit_key] as $key => $arr ) {
					if ( isset($arr['2']) && ( $add_key == $arr['2'] ) ) {
						if ( ! current_user_can( $type_obj->cap->edit_posts ) ) {
							unset( $submenu[$edit_key][$key]);
						}
					}
				}
			}
		}
	}
	*/
}
