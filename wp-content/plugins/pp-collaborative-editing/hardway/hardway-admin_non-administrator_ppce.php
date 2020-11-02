<?php

if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

global $pagenow;
	
if ( 'nav-menus.php' != $pagenow ) {	// nav-menus.php only needs admin_referer check.  TODO: split this file
	//if ( false === strpos( $_SERVER['REQUEST_URI'], 'upload.php' ) )
		PPCE_AdminHardway_Ltd::add_filters();
}

add_action( 'check_admin_referer', array('PPCE_AdminHardway_Ltd', 'act_check_admin_referer') );
add_filter( 'wp_insert_post_empty_content', array( 'PPCE_AdminHardway_Ltd', 'flt_intercept_post_insert' ), 10, 2 );
add_action( '_admin_menu', array('PPCE_AdminHardway_Ltd', 'adjust_menu_reqd_caps') );

// police nav menu parent
add_action( 'updated_post_meta', array( 'PPCE_AdminHardway_Ltd', 'act_updated_post_meta' ), 10, 4 );
add_action( 'added_post_meta', array( 'PPCE_AdminHardway_Ltd', 'act_updated_post_meta' ), 10, 4 );

if ( in_array( $pagenow, array( 'edit-tags.php', 'post.php', 'post-new.php', 'term.php' ) ) ) {
      add_filter( 'gettext', array( 'PPCE_AdminHardway_Ltd', 'flt_hide_term_parent_none' ), 99, 3 );
}

class PPCE_AdminHardway_Ltd {
	public static function add_filters() {
		global $pagenow, $pp_plugin_page;
	
		add_action( 'check_ajax_referer', array('PPCE_AdminHardway_Ltd', 'act_check_ajax_referer') );

		// URIs ending in specified filename will not be subjected to low-level query filtering
		$nomess_uris = apply_filters( 'pp_skip_lastresort_filter_uris', array( 'categories.php', 'themes.php', 'plugins.php', 'profile.php', 'link.php' ) );
		
		if ( empty( $_POST['ps'] ) && ( empty($_REQUEST['action']) || ( 'ajax-tag-search' != $_REQUEST['action'] ) ) )	// need to filter Find Posts query in Media Library
			$nomess_uris = array_merge($nomess_uris, array('admin-ajax.php'));
		
		if ( ! in_array( $pagenow, $nomess_uris ) && ! in_array( $pp_plugin_page, $nomess_uris ) )
			add_filter('query', array('PPCE_AdminHardway_Ltd', 'flt_last_resort_query'), 5 );  // early execution for Revisionary compat
	}
	
	public static function flt_intercept_post_insert( $disallow, $post_arr ) {		
		if ( 'nav_menu_item' == $post_arr['post_type'] ) {
			require_once( PPCE_ABSPATH . '/admin/filters-admin-nav_menus_ppce.php' );
		
			$action = ( isset( $_REQUEST['action'] ) ) ? $_REQUEST['action'] : '';
			
			if ( 'add-menu-item' == $action ) {
				if ( isset( $_REQUEST['menu-item'] ) ) {
					foreach( $_REQUEST['menu-item'] as $menu_item ) {  // normally just one element in array
						$menu_item_type = ( isset( $menu_item['menu-item-type'] ) ) ? $menu_item['menu-item-type'] : '';
						$object_type = ( isset( $menu_item['menu-item-object'] ) ) ? $menu_item['menu-item-object'] : '';
						$object_id = ( isset( $menu_item['menu-item-object-id'] ) ) ? $menu_item['menu-item-object-id'] : '';
			
						if ( ! PPCE_NavMenus::can_edit_menu_item( 0, compact( array( 'menu_item_type', 'object_type', 'object_id' ) ) ) ) {
							if( defined('DOING_AJAX') && DOING_AJAX )
								die(-1);
							else
								return true; // true means disallow
						}
					}
				}
			} elseif ( ! empty($post_arr['ID']) ) {
				if ( ! PPCE_NavMenus::can_edit_menu_item( $post_arr['ID'] ) ) {
					return true;  // true means disallow
				}
			}
		}
		
		return $disallow;
	}
	
    public static function flt_hide_term_parent_none( $trans, $text, $domain ) {
		static $none_strings;
		
		if ( ! isset($none_strings) ) {
			$none_strings = array( 'None' );
			
			foreach( get_taxonomies( array(), 'object' ) as $tax )
				$none_strings []= '&mdash; ' . $tax->labels->parent_item . ' &mdash;';
		}
		
		foreach( $none_strings as $none_text ) {
			if ( $none_text == $text ) {
				global $pp_current_user;
				$taxonomy = ( isset( $_REQUEST['taxonomy'] ) ) ? $_REQUEST['taxonomy'] : 'category';

				$additional_tt_ids = $pp_current_user->get_exception_terms( 'associate', 'additional', $taxonomy, $taxonomy, array( 'merge_universals' => true ) );
				
				if ( $tt_ids = $pp_current_user->get_exception_terms( 'associate', 'include', $taxonomy, $taxonomy, array( 'merge_universals' => true ) ) ) {
					$tt_ids = array_merge( $tt_ids, $additional_tt_ids );
					if ( ! in_array( 0, $tt_ids ) )
						return '';
				} elseif ( $tt_ids = $pp_current_user->get_exception_terms( 'associate', 'exclude', $taxonomy, $taxonomy, array( 'merge_universals' => true ) ) ) {
					$tt_ids = array_diff( $tt_ids, $additional_tt_ids );
					if ( in_array( 0, $tt_ids ) )
						return '';
				}
				
				break;
		    }
	    }

        return $trans;
    }

	public static function adjust_menu_reqd_caps() {
		global $menu, $submenu, $current_user;
		
		if ( ! empty( $current_user->allcaps['edit_posts'] ) ) 
			return;
		
		// users lacking edit_posts cap may have moderate_comments capability via a supplemental role
		foreach( array_keys($menu) as $key ) {
			if ( ( 'edit-comments.php' == $menu[$key][2] ) && ( 'edit_posts' == $menu[$key][1] ) ) { // no need to change the cap requirement if they also have edit_posts cap
				$menu[$key][1] = 'moderate_comments';
			}
		}
		
		if ( isset( $submenu['edit-comments.php'] ) ) {
			if ( 'edit_posts' == $submenu['edit-comments.php'][0][1] ) {
				$submenu['edit-comments.php'][0][1] = 'moderate_comments';
			}
		}

		if ( isset( $submenu['themes.php'] ) && ! defined( 'PP_DEFAULT_APPEARANCE_MENU' ) ) {
			// users lacking edit_posts cap may have moderate_comments capability via a supplemental role
			foreach( array_keys($menu) as $key ) {
				if ( ( 'themes.php' == $menu[$key][2] ) && empty( $current_user->allcaps['edit_theme_options'] ) && ( 'edit_theme_options' == $menu[$key][1] ) ) { // no need to change the cap requirement if they also have edit_posts cap
					$menu[$key][0] = __('Menus');
					$menu[$key][1] = 'manage_nav_menus';
					$menu[$key][2] = 'nav-menus.php';
				}
			}
		}
	}
	
	// next-best way to handle any permission checks for non-Ajax operations which can't be done via has_cap filter
	public static function act_check_admin_referer( $referer_name ) {
		
		if ( ! empty($_POST['tag_ID']) && ( 'update-tag_' . $_POST['tag_ID'] == $referer_name ) ) {
			require_once( PPCE_ABSPATH . '/admin/term-edit_ppce.php' );
			PPCE_TermEdit::term_edit_attempt();

		} elseif ( 'update-nav_menu' == $referer_name ) {
			global $current_user;
			
			if ( ! pp_unfiltered() && empty( $current_user->allcaps['edit_theme_options'] ) && empty( $current_user->allcaps['edit_menus'] ) ) {
				if ( $menu = get_term( $_REQUEST['menu'], 'nav_menu' ) ) {
					$_REQUEST['menu-name'] = $menu->name;
					$_POST['menu-name'] = $menu->name;
				}
			}
			
			// make sure theme locations are not wiped because logged user has editing access to a subset of menus
			require_once( PPCE_ABSPATH.'/admin/filters-admin-nav_menus_ppce.php' );
			PPCE_NavMenus::guard_theme_locs( $referer_name );
			
			$tx = get_taxonomy( 'nav_menu' );

			//$use_term_roles = pp_get_otype_option( 'use_term_roles', 'post', 'nav_menu' );
			$use_term_roles = array( 'nav_menu' => true );
			
			if ( empty ( $current_user->allcaps['edit_theme_options'] ) || ! empty( $use_term_roles['nav_menu'] ) ) {
				//if ( ! pp_user_can( $current_user, $tx->cap->manage_terms, $_REQUEST['menu'], array( 'id_detection' => false, 'skip_any_term_check' => true ) ) ) {
				if ( ! current_user_can( $tx->cap->manage_terms, $_REQUEST['menu'] ) ) {
					if ( $_REQUEST['menu'] )
						wp_die( __('You do not have permission to update that Navigation Menu', 'pp') );
					else
						wp_die( __('You do not have permission to create new Navigation Menus', 'pp') );
				}
			}
		} elseif ( false !== strpos( $referer_name, 'delete-nav_menu-' ) ) {
			if ( ! pp_unfiltered() && empty($current_user->allcaps['edit_theme_options']) && empty($current_user->allcaps['delete_menus']) ) {
				wp_die( __( 'You do not have permission to delete that Navigation Menu.', 'pp' ) );
			}
		} elseif ( false !== strpos( $referer_name, 'delete-menu_item_' ) ) {
			if ( pp_get_option( 'admin_nav_menu_filter_items' ) ) {
				$menu_item_id = substr( $referer_name, strlen( 'delete-menu_item_' ) );
				require_once( PPCE_ABSPATH . '/admin/filters-admin-nav_menus_ppce.php' );
				PPCE_NavMenus::modify_nav_menu_item( $menu_item_id, 'delete' );
			}
		} elseif ( $referer_name == 'move-menu_item' ) {
			if ( pp_get_option( 'admin_nav_menu_filter_items' ) ) {
				require_once( PPCE_ABSPATH . '/admin/filters-admin-nav_menus_ppce.php' );
				PPCE_NavMenus::modify_nav_menu_item( $_REQUEST['menu-item'], 'move' );
			}
		}
	}
	
	// next-best way to handle permission checks for Ajax operations which can't be done via has_cap filter
	public static function act_check_ajax_referer( $referer_name ) {
		switch( $referer_name ) {
			case 'add-tag':
			case 'add-category':
				$taxonomy = ( isset($_REQUEST['taxonomy']) ) ? $_REQUEST['taxonomy'] : 'category';
		
				if ( $tx_obj = get_taxonomy( $taxonomy ) )
					$cap_name = $tx_obj->cap->manage_terms;

				if ( empty($cap_name) )
					$cap_name = 'manage_categories';
			
				$post_type = pp_find_post_type();
			
				// WP add category JS for Edit Post form does not tolerate absence of some categories from "All Categories" tab
				global $pp_current_user;
				
				$term_parent = ( ! empty( $_REQUEST['parent'] ) && ( $_REQUEST['parent'] > 0 ) ) ? (int) $_REQUEST['parent'] : 0;
				
				$ug_clause = $pp_current_user->get_usergroups_clause('e');
				$new_term_exceptions = ppc_get_exceptions( array( 'operations' => array( 'manage' ), 'for_item_source' => 'term', 'via_item_source' => 'term', 'assign_for' => 'children', 'taxonomies' => array( $taxonomy ), 'post_types' => array( $post_type ), 'item_id' => pp_termid_to_ttid( $term_parent, $taxonomy ), 'ug_clause' => $ug_clause ) );

				// block term creation if user is bound by "Only these" exceptions for term management (but allow if a propagating exception for selected term parent will apply)
				if ( $includes = $pp_current_user->get_exception_terms( 'manage', 'include', $post_type, $taxonomy, array( 'merge_universals' => true ) ) ) {
					if ( ! $term_parent || ! $new_term_exceptions || ( empty( $new_term_exceptions['manage_term']['term'][$taxonomy]['include'] ) && empty( $new_term_exceptions['manage_term']['term'][$taxonomy]['additional'] ) ) )
						die(-1);
				} elseif ( $excludes = $pp_current_user->get_exception_terms( 'manage', 'exclude', $post_type, $taxonomy, array( 'merge_universals' => true ) ) ) {
					// block term creation if user is bound by "Not these" exceptions for term management (but allow if a propagating exception for selected term parent will apply)
					if ( ! empty( $new_term_exceptions['manage_term']['term'][$taxonomy]['exclude'] ) && empty( $new_term_exceptions['manage_term']['term'][$taxonomy]['additional'] ) )
						die(-1);
				}

				// block term creation if selected parent is explicity blocked
				if ( $term_parent ) {
					$user_terms = get_terms( $taxonomy, array( 'fields' => 'ids', 'hide_empty' => false, 'required_operation' => 'associate' ) );
					if ( ! in_array( $term_parent, $user_terms ) )
						die(-1);
				} else {
					// can user create top-level terms?
					$additional_tt_ids = $pp_current_user->get_exception_terms( 'associate', 'additional', $taxonomy, $taxonomy, array( 'merge_universals' => true ) );
			
					if ( $tt_ids = $pp_current_user->get_exception_terms( 'associate', 'include', $taxonomy, $taxonomy, array( 'merge_universals' => true ) ) ) {
						$tt_ids = array_merge( $tt_ids, $additional_tt_ids );
						if ( ! in_array( 0, $tt_ids ) )
							die(-1);
					} elseif ( $tt_ids = $pp_current_user->get_exception_terms( 'associate', 'exclude', $taxonomy, $taxonomy, array( 'merge_universals' => true ) ) ) {
						$tt_ids = array_diff( $tt_ids, $additional_tt_ids );
						if ( in_array( 0, $tt_ids ) )
							die(-1);
					}
				}

				break;

			case 'add-menu_item':
				if ( pp_get_option( 'admin_nav_menu_filter_items' ) ) {
					//$menu_item_type = ( isset( $_REQUEST['menu-item-type'] ) ) ? $_REQUEST['menu-item-type'] : '';
					$object_id = ( isset( $_REQUEST['menu-item-object-id'] ) ) ? (int) $_REQUEST['menu-item-object-id'] : 0;
					//$object_type = isset($_REQUEST['menu-item-object']) ? $_REQUEST['menu-item-object'] : '';
					$menu = isset($_REQUEST['menu']) ? $_REQUEST['menu'] : 0;
					
					if ( defined('PPCE_RESTRICT_MENU_TOP_LEVEL') && empty( $_REQUEST['menu_item']['menu-item-parent-id'] ) ) {
						// prevent new menu items from going to top level
						require_once( PPCE_ABSPATH . '/admin/filters-admin-nav_menus_ppce.php' );
					
						if ( $parent_id = PPCE_NavMenus::flt_menu_item_parent( 0, $object_id, $menu ) ) {
							$_REQUEST['menu_item']['menu-item-parent-id'] = $parent_id;
						} else {
							// if no editable item is found, block the new item addition
							die(-1);
						}
					}
					
					//require_once( PPCE_ABSPATH . '/admin/filters-admin-nav_menus_ppce.php' );
					//if ( ! PPCE_NavMenus::can_edit_menu_item( 0, compact( array( 'menu_item_type', 'object_type', 'object_id' ) ) ) )
					//	die(-1);
				}
				
				break;
		}
	}

	public static function act_updated_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( '_menu_item_menu_item_parent' == $meta_key ) {
			require_once( PPCE_ABSPATH . '/admin/filters-admin-nav_menus_ppce.php' );
			PPCE_NavMenus::act_updated_post_meta( $meta_id, $object_id, $meta_key, $meta_value );
		}
	}
	
	public static function flt_last_resort_query($query) {
		// no recursion
		static $in_process = false;
		
		if ( $in_process )
			return $query;

		global $cap_interceptor;
		if ( $cap_interceptor->in_process )
			return $query;
	
		$in_process = true;
		$query = self::_flt_last_resort_query($query);
		$in_process = false;
		return $query;
	}
	
	// low-level filtering of otherwise unhookable queries
	//
	static function _flt_last_resort_query($query) {
		global $wpdb, $pagenow, $pp;

		$posts = $wpdb->posts;
		
		// Search on query portions to make this as forward-compatible as possible.
		// Important to include " FROM table WHERE " as a strpos requirement because scoped queries (which should not be further altered here) will insert a JOIN clause
		// strpos search for "ELECT " rather than "SELECT" so we don't have to distinguish 0 from false
		
		// wp_count_posts() :
		// SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = %s
		$matches = array();
		
		if ( ( strpos($query, "ELECT post_status, COUNT( * ) AS num_posts ") || strpos($query, "ELECT COUNT( 1 ) FROM $posts") ) && preg_match("/FROM\s*{$posts}\s*WHERE post_type\s*=\s*'([^ ]+)'/", $query, $matches) ) {
			$_post_type = ( ! empty( $matches[1] ) ) ? $matches[1]	: pp_find_post_type();

			if ( $_post_type ) {
				global $current_user;
				
				if ( $clauses = apply_filters( 'pp_posts_clauses_intercept', false, array( 'where' => '' ) ) ) {
					// alternate filtering to match listing query (used for Post Forking support)
					$query = str_replace( " WHERE ", " WHERE 1=1 {$clauses['where']} AND ", $query );
					
				} else {
					foreach( pp_get_post_stati( array( 'private' => true, 'post_type' => $_post_type ) ) as $_status ) {
						$query = str_replace( "AND (post_status != '$_status' OR ( post_author = {$current_user->ID} AND post_status = '$_status' ))", '', $query);
						$query = str_replace( "AND (post_status != '$_status' OR ( post_author = '{$current_user->ID}' AND post_status = '$_status' ))", '', $query);
					}
					
					$query = str_replace( "post_status", "$posts.post_status", $query);
					
					$query = apply_filters( 'pp_posts_request', $query, array( 'use_revisions_object_roles' => defined('RVY_VERSION'), 'post_types' => $_post_type, 'append_post_type_clause' => false ) );
					
					// as of WP 3.0.1, additional queries triggered by posts_request filter breaks all subsequent filters which would have operated on this query
					if ( defined( 'RVY_VERSION' ) ) {
						if ( class_exists( 'RevisionaryAdminHardway_Ltd' ) )
							$query = RevisionaryAdminHardway_Ltd::flt_last_resort_query( $query );
						
						if ( class_exists( 'RevisionaryAdminHardway' ) )
							$query = RevisionaryAdminHardway::flt_include_pending_revisions( $query );
					}
					
					if ( defined('RVY_VERSION') ) {
							//if ( ! strpos( $query, "'{$matches[1]}' OR ( $wpdb->posts.post_type = 'revision'" ) )
							//	$query = str_replace( "$wpdb->posts.post_type = '{$matches[1]}'", "( $wpdb->posts.post_type = '{$matches[1]}' OR ( $wpdb->posts.post_type = 'revision' AND $wpdb->posts.post_status IN ('pending','future') AND $wpdb->posts.post_parent IN ( SELECT ID FROM $wpdb->posts WHERE post_type = '{$matches[1]}' ) ) )", $query );

							//if ( ! strpos( $query, "'{$matches[1]}' OR ( post_type = 'revision'" ) )
								$query = str_replace( " post_type = '{$matches[1]}'", "( post_type = '{$matches[1]}' OR ( post_type = 'revision' AND post_status IN ('pending','future') AND post_parent IN ( SELECT ID FROM $wpdb->posts WHERE post_type = '{$matches[1]}' ) ) )", $query );
					}
				}
			}
			
			return $query;
		}
		
		// wp_count_attachments() :
		// SELECT post_mime_type, COUNT( * ) AS num_posts FROM wp_trunk_posts WHERE post_type = 'attachment' GROUP BY post_mime_type
		//
		// WP_MediaListTable::get_views() - for unattached count :
		// SELECT COUNT( * ) FROM $wpdb->posts WHERE post_type = 'attachment' AND post_status != 'trash' AND post_parent < 1
		if ( strpos($query, "post_type = 'attachment'") && strpos( $query, 'COUNT( * )' ) && ( 0 === strpos($query, "SELECT " ) ) && ( 'upload.php' == $pagenow ) && ! strpos( $query, 'AS num_comments' ) && ! defined( 'PP_MEDIA_LIB_UNFILTERED' ) ) {
			require_once( PPCE_ABSPATH . '/admin/media_ppce.php' );
			if ( $modified_query = PPCE_Media::count_attachments_query( $query ) )
				return $modified_query;
		}
		
		// admin-ajax.php 'find_posts' :
		// SELECT ID, post_title, post_status, post_date FROM $wpdb->posts WHERE post_type = '$what' AND post_status IN ('draft', 'publish') AND ($search) ORDER BY post_date_gmt DESC LIMIT 50
		if ( strpos( $query, "ELECT ID, post_title, post_status, post_date FROM" ) ) {
			if ( ! empty( $_POST['post_type'] ) )
				$query = apply_filters('pp_posts_request', $query, array( 'post_types' => pp_sanitize_key($_POST['post_type']) ) );	
		}
		
		// parent_dropdown() - in case a plugin or theme calls it :
		// SELECT ID, post_parent, post_title FROM $wpdb->posts WHERE post_parent = %d AND post_type = 'page' ORDER BY menu_order
		if ( 'admin.php' == $pagenow ) {
			if ( strpos ($query, "ELECT ID, post_parent, post_title") && strpos($query, "FROM $posts WHERE post_parent =") ) {
				global $pp_data_sources;
				$page_temp = false;
				if ( $object_id = $pp_data_sources->detect( 'id', 'post' ) )
					$page_temp = get_post( $object_id );

				if ( ! $page_temp || $page_temp->post_parent ) {
					//$output = PP_HardwayParentLegacy::dropdown_pages();
					$selected = ( $page_temp && ! empty($page_temp->post_parent) ) ? $page_temp->post_parent : '';

					if ( $output = wp_dropdown_pages( array( 'post_type' => 'page', 'exclude_tree' => $object_id, 'selected' => $selected, 'name' => 'parent_id', 'show_option_none' => __('(no parent)'), 'sort_column'=> 'menu_order, post_title', 'echo' => 0 ) ) )
						echo $output;
				}
				$query = "SELECT ID, post_parent FROM $posts WHERE 1=2";
				
				return $query;
			}
		}
		
		if ( defined('DOING_AJAX') ) {
			if ( strpos( $query, "ELECT t.name FROM" ) && ! empty($_REQUEST['tax']) && ! empty($_SERVER['HTTP_REFERER']) ) {
				$parsed = parse_url($_SERVER['HTTP_REFERER']);
				if ( ! empty($parsed['query']) ) {
					$qry_vars = array();
					wp_parse_str( $parsed['query'], $qry_vars );
					
					if ( ! empty( $qry_vars['post'] ) ) {
						global $pp;
						$taxonomy = pp_sanitize_key($_REQUEST['tax']);
						$ok_tags = $pp->get_terms( $taxonomy, true, 'id', array( 'required_operation' => 'edit', 'object_id' => $qry_vars['post'],'use_object_roles' => true ) );
						$query = str_replace( " WHERE tt.taxonomy = '$taxonomy'", " WHERE tt.term_id IN ('" . implode("','", $ok_tags) . "') AND tt.taxonomy = '$taxonomy'", $query );
					}
				}
			}
		}
		
		return $query;
	} // end function flt_last_resort_query
	
} // end class
