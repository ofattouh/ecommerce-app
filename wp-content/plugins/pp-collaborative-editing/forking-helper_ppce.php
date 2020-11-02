<?php
function ppce_any_forking_caps( $post_type = '' ) {
	if ( pp_is_content_administrator() )
		return true;
	
	if ( ! $post_type )
		$post_type = pp_find_post_type();

	if ( in_array( $post_type, pp_get_enabled_post_types() ) ) {
		global $pp_current_user;
		
		$ids = $pp_current_user->get_exception_posts( 'fork', 'include', $post_type );
		
		if ( count($ids) ) { // if this user has an exception to fork only "none", indicate no capabilities
			if ( ( 1 == count($ids) ) && ( 0 == reset($ids) ) )
				return false;
		}
	}
	
	return post_type_supports( $post_type, 'fork' );
	
	/*  TODO: revisit this if Post Forking is modified to enforce a fork_posts capability
	if ( 'fork' == $post_type ) {
		return true;
	} else {
		$type_obj = get_post_type_object( $post_type );
		return current_user_can( $type_obj->cap->fork_posts ) || current_user_can( $type_obj->cap->branch_posts );
	}
	*/
}

class PPCE_ForkingHelper {
	function __construct() {
		global $wpdb;
		add_filter( 'option_' . $wpdb->prefix . 'user_roles', array( &$this, 'bypass_forking_negation_rolecaps' ), 60 );
		$this->prevent_redundant_role_update();	  // PPCE_ForkingHelper constructor fires on plugins_loaded action
		
		add_action( 'admin_init', array( &$this, 'regulate_action_links' ), 90 );

		//add_filter( 'pp_unfiltered_post_types', array( &$this, 'unfiltered_types' ) );
		//add_action( 'pp_pre_init', array( $this, 'force_distinct_caps' ) ); // possible future implementation
		
		// workarounds to allow forking caps to be customized by CME role edit
		//add_action( 'init', array( &$this, 'maybe_prevent_default_caps' ), 5 );
		//add_action( 'init', array( &$this, 'restore_customized_caps' ), 12 );
		
		// preliminary support for Post Forking plugin
		if ( ! defined('DOING_AJAX') || ! DOING_AJAX ) {
			add_filter( 'pp_apply_additions', array( &$this, 'flt_apply_additions' ), 10, 5 );
			add_filter( 'user_has_cap', array(&$this, 'flt_user_has_cap'), 100, 3 );
		}
	}

	// Post Forking's implementation is problematic in that supplemental bbPress / BuddyPress roles receive negative caps, blocking associated users (including admin) from editing forks
	function bypass_forking_negation_rolecaps( $user_roles ) {
		global $fork;
		
		foreach( array_keys( $user_roles ) as $role_name ) {
			if ( ! in_array( $role_name, array( 'subscriber', 'contributor', 'author', 'editor', 'administrator' ) ) ) {
				foreach( array_intersect_key( $user_roles[$role_name]['capabilities'], $fork->capabilities->defaults['subscriber'] ) as $cap => $stored_val ) {
					if ( ! $stored_val )
						unset( $user_roles[$role_name]['capabilities'][$cap] );
				}
			}
		}

		return $user_roles;
	}
	
	function prevent_redundant_role_update() {
		if ( get_option( 'ppce_fork_caps_stored' ) ) {
			global $fork;

			if ( ! empty($fork) && ! empty($fork->capabilities) ) {
				remove_action( 'init', array( &$fork->capabilities, 'add_caps' ) );
			}
		} else {
			update_option( 'ppce_fork_caps_stored', true );
		}
	}
	
	function regulate_action_links() {
		global $pagenow;
		
		if ( 'edit.php' == $pagenow ) {
			$any_caps = ppce_any_forking_caps();
			$fork_published_only = ( $any_caps ) ? pp_get_option( 'fork_published_only' ) : false;	

			if ( ! $any_caps || $fork_published_only ) {
				global $fork;
				remove_filter( 'post_row_actions', array( $fork->admin, 'row_actions' ), 10, 2 );
				remove_filter( 'page_row_actions', array( $fork->admin, 'row_actions' ), 10, 2 );
			}
			
			if ( $fork_published_only ) {
				add_filter( 'post_row_actions', array( &$this, 'row_fork_actions' ), 10, 2 );
				add_filter( 'page_row_actions', array( &$this, 'row_fork_actions' ), 10, 2 );
			}
		}
	}
	
	function row_fork_actions( $actions, $post ) {
		global $fork;
		
		if ( post_type_supports( get_post_type( $post ), 'fork' ) ) {
			if ( $status_obj = get_post_status_object( $post->post_status ) ) {
				if ( ! empty( $status_obj->public ) || ! empty( $status_obj->private ) ) {
					$label = ( $fork->branches->can_branch ( $post ) ) ? __( 'Create branch', 'fork' ) : __( 'Fork', 'fork' );
					$actions[] = '<a href="' . admin_url( "?fork={$post->ID}" ) . '">' . $label . '</a>';
				}
			}
		}

		if ( Fork::post_type == get_post_type( $post ) ) {
			$parent = $fork->revisions->get_previous_revision( $post );
			$actions[] = '<a href="' . admin_url( "revision.php?action=diff&left={$parent}&right={$post->ID}" ) . '">' . __( 'Compare', 'fork' ) . '</a>';
		}

		return $actions;
	}

	function flt_apply_additions ( $additions, $where, $required_operation, $post_type, $args = array() ) {
		$defaults = array( 'source_alias' => '' );
		extract( array_merge( $defaults, $args ), EXTR_SKIP );
		
		if ( ! is_admin() || pp_is_content_administrator() || ! ppce_any_forking_caps() || ( 'edit' != $required_operation ) )
			return $additions;

		if ( ! empty( $args['has_cap_check'] ) && ( 'fork_post' != $args['has_cap_check'] ) )
			return $additions;
			
		$_args = array();
		$_args['append_post_type_clause'] = false;
		
		if ( $fork_exceptions = PP_Exceptions::add_exception_clauses( '1=1', 'fork', $post_type, $_args ) ) {
			if ( pp_get_option( 'fork_published_only' ) ) {
				global $wpdb;
				$stati = apply_filters( 'pp_forkable_stati', get_post_stati( array( 'public' => true, 'private' => true ), 'names', 'or' ) );
				$src_table = ( $source_alias ) ? $source_alias : $wpdb->posts;
				$status_clause = "$src_table.post_status IN ('" . implode("','", $stati ) . "') AND ";
			} else
				$status_clause = '';
			
			$author_clause = '';
			if ( pp_get_option( 'fork_require_edit_others' ) ) {
				global $wpdb, $pp_current_user;
				
				$type_obj = get_post_type_object( $post_type );
				if ( $type_obj && empty( $pp_current_user->allcaps[ $type_obj->cap->edit_others_posts ] ) ) {
					$src_table = ( $source_alias ) ? $source_alias : $wpdb->posts;
					$author_clause = "$src_table.post_author = $pp_current_user->ID AND ";
				}
			}
			
			if ( $status_clause || $author_clause )
				$additions[] = "{$status_clause}{$author_clause}( $fork_exceptions )";
			else
				$additions[] = $fork_exceptions;
		}
		
		return $additions;
	}
	
	// $wp_sitecaps = current user's site-wide capabilities
	// $reqd_caps = primitive capabilities being tested / requested
	// $args = array with:
	// 		$args[0] = original capability requirement passed to current_user_can (possibly a meta cap)
	// 		$args[1] = user being tested
	// 		$args[2] = post id (could be a post_id, link_id, term_id or something else)
	//
	function flt_user_has_cap( $wp_sitecaps, $orig_reqd_caps, $args ) {
		if ( ( 'edit_post' == $args[0] ) && ! empty($args[2]) ) {
			global $pp_current_user;
		
			if ( ( $args[1] != $pp_current_user->ID ) || ! pp_get_option( 'fork_require_edit_others' ) )
				return $wp_sitecaps;

			$_post = get_post( $args[2] );
			if ( 'fork' == $_post->post_type ) {
				if ( get_post_field( 'post_author', $_post->post_parent ) != $pp_current_user->ID ) {
					if ( $parent_type_obj = get_post_type_object( get_post_field( 'post_type', $_post->post_parent ) ) ) {
						if ( empty( $pp_current_user->allcaps[ $parent_type_obj->cap->edit_others_posts ] ) ) {
							unset( $wp_sitecaps[ 'edit_forks' ] );
						}
					}
				}
			}
		}
		
		return $wp_sitecaps;
	}
	
	/*
	function maybe_prevent_default_caps() {
		if ( defined( 'PP_PREVENT_DEFAULT_FORKING_CAPS' ) || get_option( 'pp_prevent_default_forking_caps' ) ) {
			require_once( dirname(__FILE__).'/forking-roles-helper_pp.php' );
			return PPCE_ForkingHelper::maybe_prevent_default_caps();
		}
	}

	// retain role caps saved by Capability Manager Enhanced
	function restore_customized_caps() {
		if ( defined( 'PP_PREVENT_DEFAULT_FORKING_CAPS' ) || get_option( 'pp_prevent_default_forking_caps' ) && ( $customized = pp_get_option( 'customized_roles' ) ) ) {
			require_once( dirname(__FILE__).'/forking-roles-helper_pp.php' );
			return PPCE_ForkingHelper::restore_customized_caps( $customized );
		}
	}
	*/
	
	/*
	function unfiltered_types( $types ) {
		$types[] = 'fork';
		return $types;
	}
	*/
	
	// TODO: revisit this if Post Forking is modified to enforce a fork_posts capability
	/*
	// this allows the fork and branch caps to be cast in pattern roles for type-specific supplemental roles
	function force_distinct_caps() {
		global $wp_post_types, $pp_cap_helper;
		
		$generic_caps = array( 'fork_posts', 'fork_pages', 'branch_posts', 'branch_pages' );
		
		// post types which are enabled for PP filtering must have distinct type-related cap definitions
		foreach( array_diff( pp_get_enabled_post_types(), array( 'attachment', 'revision', 'fork' ) ) as $post_type ) {
			foreach( array( 'fork_posts', 'branch_posts' ) as $cap_property ) {
				if ( ! isset( $wp_post_types[$post_type]->cap->$cap_property ) || in_array( $wp_post_types[$post_type]->cap->$cap_property, $generic_caps ) ) {
					$wp_post_types[$post_type]->cap->$cap_property = str_replace( "_post", "_{$post_type}", $cap_property );
					$pp_cap_helper->all_type_caps[$cap_property] = true;
				}
			}
		}
	}
	*/
}
