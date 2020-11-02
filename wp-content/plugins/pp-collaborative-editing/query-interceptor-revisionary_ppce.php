<?php
class PPCE_QueryInterceptor_Rvy {
	var $filtered_post_clauses_type = '';
	
	function __construct() {
		add_filter( 'pp_valid_stati', array( &$this, 'flt_valid_stati' ), 10, 2 );
		add_filter( 'pp_equiv_conditions', array( &$this, 'flt_equiv_conditions' ), 10, 3 );
		add_filter( 'pp_main_posts_clauses_types', array( &$this, 'flt_posts_clauses_object_types' ), 10, 2 );
		add_filter( 'pp_main_posts_clauses_where', array( &$this, 'flt_posts_clauses_where' ), 10, 1 );
		add_filter( 'pp_posts_where', array( &$this, 'flt_posts_where' ), 10, 2 );
		add_filter( 'pp_meta_cap', array( &$this, 'flt_meta_cap' ) );
	}

	function flt_equiv_conditions( $equiv_conditions, $attribute, $meta_cap ) {
		if ( ( 'post_status' == $attribute ) && ( 'edit_post' == $meta_cap ) ) {
			if ( defined('RVY_VERSION') && ( ! is_admin() && ( ! empty($_REQUEST['post_type']) && ( 'revision' == $_REQUEST['post_type'] ) && ( ! empty($_REQUEST['preview']) || ! empty($_REQUEST['preview_id']) ) ) ) ) {
				$equiv_conditions['publish']['pending'] = true;
				$equiv_conditions['publish']['future'] = true;	// require qualifying role for pending/scheduled revision preview (will not expose pending / future posts)
				$equiv_conditions['publish']['inherit'] = true;
			}
		}
		
		return $equiv_conditions;
	}
	
	function flt_valid_stati( $valid_stati, $meta_cap, $src_table ) {
		if ( 'edit_post' == $meta_cap ) {
			if ( defined('RVY_VERSION') && ( ! is_admin() && ( ! empty($_REQUEST['post_type']) && ( 'revision' == $_REQUEST['post_type'] ) && ( ! empty($_REQUEST['preview']) || ! empty($_REQUEST['preview_id']) ) ) ) ) {
				$valid_stati = "( $valid_stati OR ( $src_table.post_type = 'revision' AND $src_table.post_status IN ('pending','future','inherit') ) )";
			}
		}
		
		return $valid_stati;
	}
	
	function flt_posts_clauses_object_types( $object_types ) {
		global $wp_query;
	
		if ( $wp_query->is_preview && defined('RVY_VERSION') ) {
			if ( $_post = get_post( $wp_query->query['p'] ) ) {
				if ( 'revision' == $_post->post_type ) {
					if ( $_type = get_post_field( 'post_type', $_post->post_parent ) ) {
						$object_types = $_type;
						$this->filtered_post_clauses_type = $_type;
					}
				}
			}
		}

		return $object_types;
	}
	
	function flt_posts_clauses_where( $objects_where ) {
		if ( $this->filtered_post_clauses_type ) {
			//if ( ! is_admin() && ! empty($_REQUEST['preview']) )
			//	$objects_where = str_replace( "post_type = 'post'", "post_type IN ('$this->filtered_post_clauses_type', 'revision')", $objects_where );
			//else
				$objects_where = str_replace( "post_type = 'post'", "post_type = '$this->filtered_post_clauses_type'", $objects_where );
			
			$this->filtered_post_clauses_type = '';
		}

		return $objects_where;
	}
	
	
	function flt_posts_where( $where, $args ) {
		if ( defined('RVY_VERSION') && ( ! is_admin() && ( ! empty($_REQUEST['post_type']) && ( 'revision' == $_REQUEST['post_type'] ) && ( ! empty($_REQUEST['preview']) || ! empty($_REQUEST['preview_id']) ) ) ) ) {
			$matches = array();
			if ( preg_match( "/post_type = '([0-9a-zA-Z_\-]+)'/", $where, $matches ) ) {
				if ( $matches[1] ) {
					global $wpdb;
					$where = str_replace( "$wpdb->posts.post_type = '{$matches[1]}'", "( $wpdb->posts.post_type = '{$matches[1]}' OR ( $wpdb->posts.post_type = 'revision' AND $wpdb->posts.post_status IN ('pending','future','inherit') AND $wpdb->posts.post_parent IN ( SELECT ID FROM $wpdb->posts WHERE post_type = '{$matches[1]}' ) ) )", $where );
				}
			}
		}
		
		return $where;
	}
	
	function flt_meta_cap( $meta_cap ) {
		if ( defined('RVY_VERSION') && ( 'read_post' == $meta_cap ) && ( ! is_admin() && ( ! empty($_REQUEST['post_type']) && ( 'revision' == $_REQUEST['post_type'] ) && ( ! empty($_REQUEST['preview']) || ! empty($_REQUEST['preview_id']) ) ) ) ) {
			$meta_cap = 'edit_post';
		}
		return $meta_cap;
	}
}
