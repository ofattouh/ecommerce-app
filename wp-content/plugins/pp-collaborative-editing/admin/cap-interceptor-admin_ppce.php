<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $pp_cap_interceptor_admin;
$pp_cap_interceptor_admin = new PPCE_CapInterceptorAdmin();

class PPCE_CapInterceptorAdmin {
	var $in_has_cap_call = false;
	
	function __construct() {
		add_filter( 'pp_do_find_post_id', array( &$this, 'do_find_post_id' ), 10, 3 );
		add_filter( 'pp_user_has_cap_params', array( &$this, 'user_has_cap_params' ), 10, 3 );
		add_filter( 'pp_credit_cap_exception', array( &$this, 'credit_tx_cap_exception' ), 10, 2 );
		add_filter( 'pp_user_has_caps', array( &$this, 'user_has_caps' ), 10, 3 );
		add_filter( 'pp_get_terms_exceptions', array( &$this, 'get_terms_exceptions' ), 10, 6 );
		add_filter( 'terms_clauses', array(&$this, 'get_terms_preserve_current_parent'), 55, 3 );
		add_filter( 'pp_posts_clauses_intercept', array( $this, 'flt_bypass_attachments_filtering' ), 10, 4 );

		
		// filter pre_option_category_children, pre_update_option_category_children to disable/enable terms filtering
		foreach( pp_get_enabled_taxonomies( array( 'object_type' => false ) ) as $taxonomy ) {
			$func = create_function( '', "PPCE_CapInterceptorAdmin::disable_terms_filter('" . $taxonomy . "');" );
			add_action( "pre_option_{$taxonomy}_children", $func );
		}
		
		add_filter( 'map_meta_cap', array(&$this, 'flt_adjust_reqd_caps'), 1, 4 );
		
		add_filter( 'pp_adjust_posts_where_clause', array(&$this, 'flt_adjust_posts_where_clause'), 10, 4 );
		add_filter( 'pp_force_attachment_parent_clause', array(&$this, 'flt_force_attachment_parent_clause'), 10, 2 );
		add_filter( 'pp_have_site_caps', array(&$this, 'flt_have_site_caps' ), 10, 3 );
		
		add_filter( 'pp_construct_posts_request_args', array(&$this, 'flt_construct_posts_request_args' ) );
		
		add_filter( 'redirect_post_location', array(&$this, 'flt_maybe_redirect_post_edit_location' ), 10, 2 );
		
		// prevent infinite recursion if current_user_can( 'edit_posts' ) is called from within another plugin's user_has_cap handler
		add_filter( 'user_has_cap', array( &$this, 'flag_has_cap_call' ), 0 );
		add_filter( 'user_has_cap', array( &$this, 'flag_has_cap_done' ), 999 );
	}

	function flag_has_cap_call( $caps ) {
		$this->in_has_cap_call = true;
		return $caps;
	}
	
	function flag_has_cap_done( $caps ) {
		$this->in_has_cap_call = false;
		return $caps;
	}
	
	function flt_bypass_attachments_filtering( $clauses, $orig_clauses, $_wp_query = false, $args = array() ) {
		$required_operation = ( isset($args['required_operation']) ) ? $args['required_operation'] : '';

		if ( in_array( $required_operation, array( '', 'read' ) ) && empty($args['pp_context']) && strpos( $orig_clauses['where'], "post_type = 'attachment'" ) ) {
			if ( $this->attachment_filtering_disabled() ) {
				$post_types = ( isset($args['post_types']) ) ? (array) $args['post_types'] : array();
				if ( ! $post_types || ( ( 1 == count($post_types) ) && ( 'attachment' == reset($post_types) ) ) ) {
					return $orig_clauses;
				}
			}
		}
		
		return $clauses;
	}
	
	function attachment_filtering_disabled() {
		global $pp_current_user;
		return ! empty( $pp_current_user->allcaps['pp_list_all_files'] );
	}

	function flt_adjust_posts_where_clause( $adjust, $type_where_clause, $post_type, $args ) {
		if ( 'attachment' == $post_type ) {
			global $pp_current_user;
		
			if ( ! empty($args['has_cap_check']) && ! pp_get_option( 'own_attachments_always_editable' ) && empty( $pp_current_user->allcaps['edit_own_attachments'] ) ) {  // PP setting eliminates cap requirement
				$adjust = ( $adjust ) ? $adjust : $type_where_clause;
				$adjust .= " AND {$args['src_table']}.post_parent = '0'";
			}
		}

		return $adjust;
	}
	
	function flt_force_attachment_parent_clause( $force, $args ) {
		global $current_user;

		//$force = ( empty($args['has_cap_check']) || pp_get_option( 'edit_others_attached_files') ) && ( empty($args['pp_context']) || 'count_attachments' != $args['pp_context'] );   // parent clause already applied by PPCE_Media::count_attachments_query()
		//return ! empty($args['has_cap_check']);
		//return ( empty($args['pp_context']) || 'count_attachments' != $args['pp_context'] || in_array( 'attachment', pp_get_enabled_post_types() ) );  // TODO: review, test this further
		return ( empty($args['pp_context']) || 'count_attachments' != $args['pp_context'] );
	}
	
	function flt_have_site_caps( $have_site_caps, $post_type, $args ) {
		if ( 'attachment' == $post_type ) {
			global $pp_current_user;
			
			if ( pp_get_option( 'own_attachments_always_editable' ) || ! empty( $pp_current_user->allcaps['edit_own_attachments'] ) ) 
				$have_site_caps['owner'][]= 'inherit';
		}
		
		return $have_site_caps;
	}
	
	// hooks to map_meta_cap
	function flt_adjust_reqd_caps( $reqd_caps, $orig_cap, $user_id, $args ) {
		global $pagenow, $cap_interceptor, $current_user;
		
		if ( $this->in_has_cap_call || ( $user_id != $current_user->ID ) )
			return $reqd_caps;

		$orig_reqd_caps = (array) $reqd_caps;
		
		// for scoped menu management roles, satisfy edit_theme_options cap requirement
		if ( ( 'nav-menus.php' == $pagenow ) 
		|| ( ( 'edit_theme_options' == reset($reqd_caps) ) && ( $this->doing_admin_menus() || ( defined('DOING_AJAX') && DOING_AJAX ) ) ) ) {
			//if ( ( 'nav-menus.php' == $pagenow ) || empty( $current_user->allcaps['edit_theme_options'] ) ) {
			if ( empty( $current_user->allcaps['edit_theme_options'] ) ) {
				require_once( dirname(__FILE__).'/cap-interceptor-nav-menu_ppce.php' );
				$reqd_caps = NavMenuCapHelper::fudge_nav_menu_caps( $reqd_caps );
			}
		} else {
			// Work around WP's occasional use of literal 'cap_name' instead of $post_type_object->cap->$cap_name (as of WP 3.0)
			// note: cap names for "post" type may be customized too
			//
			if ( in_array( $pagenow, array( 'edit.php', 'post.php', 'post-new.php', 'press-this.php', 'admin-ajax.php', 'upload.php', 'media.php' ) ) && ! $this->doing_admin_menus() ) {
				$replace_post_caps = array( 'publish_posts', 'edit_others_posts', 'edit_published_posts' );  // possible future TODO: , 'fork_posts', 'branch_posts' );  // distinct type-specific caps for Post Forking plugin once API supports it

				static $did_admin_init = false;
				if ( ! $did_admin_init )
					$did_admin_init = did_action( 'admin_init' );

				if ( $did_admin_init )	// otherwise extra padding between menu items due to some items populated but unpermitted
					$replace_post_caps []= 'edit_posts';
				
				if ( in_array( $pagenow, array( 'upload.php', 'media.php' ) ) )
					$replace_post_caps = array_merge( $replace_post_caps, array( 'delete_posts', 'delete_others_posts' ) );

				if ( array_intersect( $reqd_caps, $replace_post_caps ) ) {
					if ( ! empty($args[0]) )
						$item_id = ( is_object($args[0]) ) ? $args[0]->ID : $args[0];
					else
						$item_id = 0;
					
					if ( $type_obj = get_post_type_object( pp_find_post_type( $item_id ) ) ) {
						foreach( $replace_post_caps as $post_cap_name ) {
							$key = array_search( $post_cap_name, $reqd_caps );
							if ( false !== $key ) {		
								$reqd_caps[$key] = $type_obj->cap->$post_cap_name;
							}
						}
					}
				}
			}
			
			// accept edit_files capability instead of upload_files in some contexts
			$key = array_search( 'upload_files', $reqd_caps );
			
			if ( false !== $key && ( $this->doing_admin_menus() || in_array( $pagenow, array( 'upload.php', 'post.php', 'post-new.php' ) ) || ( defined('DOING_AJAX') && DOING_AJAX && ( 'query-attachments' == $_REQUEST['action'] ) ) ) ) {
				if ( empty( $current_user->allcaps[ 'upload_files' ] ) && ! empty( $current_user->allcaps[ 'edit_files' ] ) )
					$reqd_caps[$key] = 'edit_files';
			}
			
			// Edit Flow workaround (literal edit_posts capability required for dashboard widgets)
			if ( is_blog_admin() && in_array( 'edit_posts', $reqd_caps ) && empty( $current_user->allcaps['edit_posts'] ) && ( 1 == count($reqd_caps) ) && did_action( 'load-index.php' ) && ! did_action( 'admin_enqueue_scripts' ) ) {
				foreach( get_post_types( array( 'public' => true ), 'object' ) as $post_type => $type_obj ) {
					if ( ! empty( $current_user->allcaps[ $type_obj->cap->edit_posts ] ) ) {
						$key = array_search( 'edit_posts', $reqd_caps );
						$reqd_caps[$key] = $type_obj->cap->edit_posts;
						break;
					}
				}
			}
		}
			
		//===============================

		if ( $reqd_caps !== $orig_reqd_caps ) {
			$reqd_caps = apply_filters( 'ppce_adjusted_reqd_caps', $reqd_caps, $orig_reqd_caps, $orig_cap, $user_id, $args );
			
			// workaround for Wiki plugin
			if ( ( 'edit_others_posts' == $orig_cap ) && did_action( 'auth_redirect' ) && ! did_action( '_admin_menu' ) ) {
				$reqd_caps = $orig_reqd_caps;
			}
		}
		
		if ( pp_is_taxonomy_enabled( 'post_tag' ) && in_array( $orig_cap, array( 'manage_post_tags', 'edit_post_tags', 'delete_post_tags' ) ) && in_array( 'manage_categories', $reqd_caps ) && ! defined('PP_LEGACY_POST_TAG_CAPS') ) {
			$reqd_caps = array_diff( $reqd_caps, array( 'manage_categories' ) );
			$reqd_caps []= 'manage_post_tags';
		}
		
		return $reqd_caps;
	}
	
	private function doing_admin_menus() {
		return ( ( did_action( '_admin_menu' ) && ! did_action('admin_menu') ) 	 // menu construction
				|| ( did_action( 'admin_head' ) && ! did_action('adminmenu') )	 // menu display
				);
	}
	
	public static function disable_terms_filter( $taxonomy ) {
		$terms_interceptor = pp_init_terms_interceptor();
		$terms_interceptor->no_filter = true;
		
		$func = create_function( '', "PPCE_CapInterceptorAdmin::enable_terms_filter('" . $taxonomy . "');" );
		add_action( "pre_update_option_{$taxonomy}_children", $func );
	}
	
	public static function enable_terms_filter( $taxonomy ) {
		$terms_interceptor = pp_init_terms_interceptor();
		$terms_interceptor->no_filter = false;
	}
	
	private function taxonomy_from_caps( $caps ) {
		foreach( pp_get_enabled_taxonomies( array( 'object_type' => false ), 'object' ) as $taxonomy => $tx_obj ) {
			if ( array_intersect( (array) $tx_obj->cap, $caps ) )
				return $taxonomy;
		}

		return false;
	}
	
	function user_has_cap_params( $params, $orig_reqd_caps, $args ) {
		global $ppce_cap_helper;
		
		// taxonomy caps
		if ( $type_caps = array_intersect( $orig_reqd_caps, array_keys( $ppce_cap_helper->all_taxonomy_caps ) ) ) {
			global $tag_ID, $taxonomy;

			if ( $taxonomy ) {
				// todo: put this check in pp_is_taxonomy_enabled()
				$tx_name = ( is_object( $taxonomy ) && isset( $taxonomy->name ) ) ? $taxonomy->name : $taxonomy;
				
				if ( ! pp_is_taxonomy_enabled( $tx_name ) )
					return $params;
			}
			
			if ( ! array_diff( $orig_reqd_caps, array( 'edit_posts' ) ) )
				return $params;
			
			$is_term_cap = true;

			if ( 'assign_term' == $args['orig_cap'] ) {
				if ( ! empty( $args['item_id'] ) ) {
					$term_obj = get_term( $args['item_id'] );
					if ( ! empty( $term_obj->taxonomy ) ) {
						global $post_type;
						
						$op = 'assign';
						$taxonomy = $term_obj->taxonomy;
						if ( ! empty( $post_type ) )
							$item_type = $post_type;
					}
				} else {
					$item_type = '';
				}
				
				$op = 'assign';
			} else {
				if ( ! $item_type = $this->taxonomy_from_caps( $type_caps ) )
					return $params;
		
				$tx_obj = get_taxonomy( $item_type );
				$taxonomy = $item_type;
				
				$base_cap = reset( $type_caps );
				
				switch( $base_cap ) {
					case $tx_obj->cap->manage_terms :
						$op = 'manage';
						break;
					/*
					case $tx_obj->cap->edit_terms :
						$op = 'edit';
						break;
					case $tx_obj->cap->delete_terms :
						$op = 'delete';
						break;
					*/
					default :
						$op = false;
				}
			}
			
			$return = compact( 'type_caps', 'item_type', 'is_term_cap', 'op', 'taxonomy' );
	
			if ( empty( $params['item_id'] ) ) {
				$qvar = ( 'nav_menu' == $item_type ) ? 'menu' : 'tag_ID';
				
				if ( ! empty($_REQUEST[$qvar]) )
					$return['item_id'] = pp_termid_to_ttid( (int) $_REQUEST[$qvar], $item_type );
			}
			
			return ( is_array($params) ) ? array_merge( $params, $return ) : $return;
		}
		
		return $params;
	}
	
	function credit_tx_cap_exception( $pass, $params ) {
		if ( ! empty( $params['is_term_cap'] ) ) {
			extract( $params );
			
			if ( count( $type_caps ) == 1 ) {
				if ( $op ) {
					global $pp_current_user;

					// note: item_type is taxonomy here
					if ( $tt_ids = $pp_current_user->get_exception_terms( $op, 'additional', $item_type, $item_type ) ) {
						if ( ! $item_id || in_array( $item_id, $tt_ids ) )
							$pass = true;
					}
				}
			}
		}
		
		return $pass;
	}
	
	function user_has_caps( $wp_sitecaps, $orig_reqd_caps, $params ) {
		extract( $params );
		
		if ( ! empty( $params['is_term_cap'] ) && ( ( $op != 'assign' ) || ! $this->doing_admin_menus() ) ) {
			if ( $item_id && $op ) {
				global $pp_current_user;
				$fail = false;
				
				$taxonomy = ( ! empty( $params['taxonomy'] ) ) ? $params['taxonomy'] : $item_type;
				
				$args = ( 'assign' == $op ) ? array( 'merge_universals' => true ) : array();
				
				$additional_tt_ids = $pp_current_user->get_exception_terms( $op, 'additional', $item_type, $taxonomy, $args );
				
				// note: item_type is taxonomy here
				if ( $tt_ids = $pp_current_user->get_exception_terms( $op, 'include', $item_type, $taxonomy, $args ) ) {
					if ( ! in_array( $item_id, array_merge( $tt_ids, $additional_tt_ids ) ) )
						$fail = true;
						
				} elseif ( $tt_ids = $pp_current_user->get_exception_terms( $op, 'exclude', $item_type, $taxonomy, $args ) ) {
					$tt_ids = array_diff( $tt_ids, $additional_tt_ids );
					if ( in_array( $item_id, $tt_ids ) )
						$fail = true;
				}
				
				if ( $fail )
					$wp_sitecaps = array_diff_key( $wp_sitecaps, array_fill_keys( $orig_reqd_caps, true ) );
			}
		}

		return $wp_sitecaps;
	}
	
	// if user lacks sitewide term management cap, make any additions double as implicit inclusions (so inaccessable terms are not listed)
	function get_terms_exceptions( $exceptions, $taxonomy, $op, $mod_type, $post_type, $args = array() ) {
		if ( ( 'include' == $mod_type ) && ! $exceptions && ! empty($args['additional_tt_ids']) ) {
			if ( 'manage' == $op ) {
				global $pp_current_user;
			
				$tx_obj = get_taxonomy( $taxonomy );
				if ( empty( $pp_current_user->allcaps[ $tx_obj->cap->manage_terms ] ) )
					$exceptions = $args['additional_tt_ids'];
			}
		}

		return $exceptions;
	}
	
	function get_terms_preserve_current_parent( $clauses, $taxonomies, $args ) {
		global $pagenow;
		
		if ( is_admin() && in_array( $pagenow, array( 'edit-tags.php', 'term.php' ) ) && ! empty( $_REQUEST['tag_ID'] ) ) {
			$tx_obj = get_taxonomy( reset($taxonomies) );
			if ( $tx_obj->hierarchical ) {
				global $wpdb;
				// don't filter current parent category out of selection UI even if current user can't manage it
				$clauses['where'] .= " OR t.term_id = (SELECT parent FROM $wpdb->term_taxonomy WHERE taxonomy = '$tx_obj->name' AND term_id = '" . intval($_REQUEST['tag_ID']) . "') ";
			}
		}
	
		return $clauses;
	}
	
	function flt_construct_posts_request_args( $args ) {
		foreach( array( 'action', 'action2' ) as $var ) {
			if ( ! empty($_REQUEST[$var]) && in_array( $_REQUEST[$var], array( 'trash', 'untrash', 'delete' ) ) ) {
				$args['include_trash'] = true;
			}
		}

		return $args;
	}
	
	function do_find_post_id( $do, $orig_reqd_caps, $args ) {
		if ( $this->doing_admin_menus() )
			return false;
		
		return $do;
	}
	
	function flt_maybe_redirect_post_edit_location( $location, $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			if ( $type_obj = get_post_type_object( get_post_field( 'post_type', $post_id ) ) ) {
				$edit_link = "<a href='" . admin_url( "edit.php?post_type=$type_obj->name" ) . "'>" . sprintf( __( 'Go to %s', 'ppce' ), $type_obj->labels->name ) . '</a>'; 
				
				if ( isset($_POST['save']) || isset($_POST['publish']) )
					$arr_msg = array( sprintf( __( 'The %s was saved, but you can no longer edit it.', 'ppce' ), strtolower($type_obj->labels->singular_name) ), $edit_link );
			} else {
				$edit_link = "<a href='" . admin_url( 'index.php' ) . "'>" . __( 'Dashboard' ) . '</a>'; 
			}
			
			if ( empty($arr_msg) )
				$arr_msg = array( __( 'The requested modification was processed, but you can no longer edit the post.', 'ppce' ), sprintf( __( 'Go to %s', 'ppce' ), $edit_link ) );

			wp_die( '<p>' . implode( '</p><p>' , $arr_msg ) . '</p>' );
		}
		
		return $location;
	}
}
