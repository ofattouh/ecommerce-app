<?php
add_action( 'pp_terms_skip_filtering', array( 'PPCE_NavMenus', 'flt_terms_skip_filtering' ), 10, 3 );
add_filter( 'get_terms_args', array( 'PPCE_NavMenus', 'flt_terms_args' ), 40, 2 );	// pp core filtering is at priority 50

add_filter( 'get_user_option_nav_menu_recently_edited', array( 'PPCE_NavMenus', 'flt_nav_menu_recent' ) );

add_filter( 'pre_update_option_nav_menu_options', array( 'PPCE_NavMenus', 'flt_update_nav_menu_options' ), 10, 2 );
add_filter( 'update_post_metadata', array( 'PPCE_NavMenus', 'flt_update_nav_menu_item_parent' ), 10, 5 );

class PPCE_NavMenus {
	public static function flt_nav_menu_recent( $opt ) {
		if ( $tx_obj = get_taxonomy( 'nav_menu' ) ) {
			$menu_tt_id = pp_termid_to_ttid( (int) $opt, 'nav_menu' );
			
			if ( ! current_user_can( $tx_obj->cap->manage_terms, $menu_tt_id ) ) {
				global $pp_current_user;
				
				// note: item_type is taxonomy here
				if ( $tt_ids = $pp_current_user->get_exception_terms( 'manage', 'additional', 'nav_menu', 'nav_menu' ) ) {
					if ( ! in_array( $menu_tt_id, $tt_ids ) ) {
						$tx_by_ref_arg = '';
						$opt = pp_ttid_to_termid( reset( $tt_ids ), $tx_by_ref_arg );
						update_user_option( $pp_current_user->ID, 'nav_menu_recently_edited', $opt );
					}
				} else {
					delete_user_option( $pp_current_user->ID, 'nav_menu_recently_edited' );
					$opt = 0;
				}
			}
		}
		
		return $opt;
	}

	public static function flt_terms_skip_filtering( $skip, $taxonomies, $args ) {
		if ( ( 'nav_menu' != reset($taxonomies) ) ) // && ! pp_get_option( 'admin_nav_menu_filter_items' ) )
			$skip = true;

		return $skip;
	}

	public static function flt_terms_args( $args, $taxonomies ) {
		if ( ( 'nav_menu' != reset($taxonomies) ) ) // && pp_get_option( 'admin_nav_menu_filter_items' ) )
			$args['hide_empty'] = true;

		return $args;
	}

	public static function can_edit_theme_locs() {
		if ( pp_is_content_administrator() )
			return true;
	
		global $current_user;
		return ! empty( $current_user->allcaps['manage_nav_menus'] ) || ( ! defined( 'PP_STRICT_MENU_CAPS' ) && ( ! empty( $current_user->allcaps['switch_themes'] ) || ! empty( $current_user->allcaps['edit_theme_options'] ) ) );
	}

	// make sure theme locations are not wiped because logged user has editing access to a subset of menus
	public static function guard_theme_locs( $referer ) {
		if ( 'update-nav_menu' == $referer ) {			
			if ( ! self::can_edit_theme_locs() ) {
				if ( pp_wp_ver( '3.6-dev' ) ) {
					if ( $stored_locs = get_theme_mod( 'nav_menu_locations' ) )
						$_POST['menu-locations'] = (array) $stored_locs;
					else
						$_POST['menu-locations'] = array();
				} else {
					if ( isset( $_POST['menu-locations'] ) )
						unset( $_POST['menu-locations'] );
				}
			}
		}
	}

	public static function can_edit_menu_item( $menu_item_id, $args = array() ) {
		$defaults = array( 'menu_item_type' => '', 'object_type' => '', 'object_id' => 0 );
		extract( array_merge( $defaults, $args ) );
		
		if ( ! $object_type )
			$object_type = get_post_meta( $menu_item_id, '_menu_item_object', true );
		
		if ( ! $object_id )
			$object_id = get_post_meta( $menu_item_id, '_menu_item_object_id', true );

		if ( ! $menu_item_type )
			$menu_item_type = get_post_meta( $menu_item_id, '_menu_item_type', true );
			
		$lock_custom = ! pp_is_administrator() && ! current_user_can('edit_theme_options') && pp_get_option( 'admin_nav_menu_lock_custom' );
		
		if ( ( ( 'custom' == $menu_item_type ) || ! in_array( $menu_item_type, array( 'post_type', 'taxonomy' ) ) ) && $lock_custom ) {
			return false;
		} elseif ( post_type_exists($object_type) ) {		
			return current_user_can( 'edit_post', $object_id );
		} elseif ( taxonomy_exists($object_type) ) {
			if ( $tx_obj = get_taxonomy( $object_type ) )
				return current_user_can( $tx_obj->cap->manage_terms, $object_id );
		}

		// for menu item types we don't filter
		return true;
	}

	public static function act_updated_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		static $filtered_meta_ids;
		
		if ( ! defined('PPCE_RESTRICT_MENU_TOP_LEVEL') )
			return;
		
		if ( '_menu_item_menu_item_parent' != $meta_key )
			return;
		
		if ( ! isset($filtered_meta_ids) )
			$filtered_meta_ids = array();
			
		if ( isset($filtered_meta_ids[$meta_id]) )
			return;
			
		$filtered_meta_ids[$meta_id] = true;
			
		$menu = self::determine_selected_menu();
		$meta_value = self::flt_menu_item_parent( $meta_value, $object_id, $menu );
		
		update_post_meta( $object_id, $meta_key, $meta_value );
	}
	
	public static function flt_police_menu_item_parent( $post_parent, $object_id, $post_arr_keys, $post_arr ) {
		//if ( empty( $post_arr['post_date_gmt'] ) ) {
			$menu = self::determine_selected_menu();
			$post_parent = self::flt_menu_item_parent( $post_parent, $object_id, $menu );
		//}
		
		return $post_parent;
	}
	
	public static function flt_update_nav_menu_item_parent( $bypass, $object_id, $meta_key, $meta_value, $prev_value ) {
		if ( '_menu_item_menu_item_parent' == $meta_key ) {
			$menu = PPCE_NavMenus::determine_selected_menu();
			$post_parent = self::flt_menu_item_parent( $meta_value, $object_id, $menu );
			
			if ( $post_parent && ( $post_parent != $meta_value ) ) {
				global $wpdb;
				if ( ! $wpdb->update( $wpdb->postmeta, array( 'meta_value' => $post_parent ), array( 'meta_key' => $meta_key, 'post_id' => $object_id ) ) )
					$wpdb->insert( $wpdb->postmeta, array( 'meta_value' => $post_parent, 'meta_key' => $meta_key, 'post_id' => $object_id ) );
				
				$bypass = true;
			}
		}
		
		return $bypass;
	}
	
	public static function flt_menu_item_parent( $post_parent, $object_id, $menu ) {
		if ( $post_parent || ! defined('PPCE_RESTRICT_MENU_TOP_LEVEL') || pp_is_administrator() || current_user_can('edit_theme_options') )
			return $post_parent;
	
		$stored_parent = get_post_meta( $object_id, '_menu_item_menu_item_parent', true );
		$menu_item = get_post($object_id);
		
		// if this menu item is already stored to top level, don't move it
		if ( ! $stored_parent && ( false !== $stored_parent ) && ( empty($_REQUEST['action']) || 'add-menu-item' != $_REQUEST['action'] ) ) {
			return $post_parent;
		}
		
		// move new menu items to alongside or under an existing editable item 
		$nav_menu_items = wp_get_nav_menu_items( $menu, array( 'post_status' => 'any' ) );
		
		$editable_items = array();
		$editable_item_ids = array();
		$item_parents = array();

		$highest_editable_items = array();
		
		foreach( $nav_menu_items as $item ) {
			if ( $item->ID == $object_id )
				continue;
		
			if ( self::can_edit_menu_item( $item->ID ) && ! empty( $item->post_date_gmt ) ) {
				$editable_items[] = $item;
				$editable_item_ids[] = $item->ID;
				$item_parents[$item->ID] = get_post_meta( $item->ID, '_menu_item_menu_item_parent', true );
			}
		}
		
		// (Don't do this if there's more than one editable menu item because it prevents third-level items from being moved to second level if the top level item is hidden)
		// if parent is being set to zero but this menu item is already stored as a sub-item, revert it
		if ( count( $editable_items ) < 2 ) {
			if ( ! $post_parent && $stored_parent && ( empty($_REQUEST['action']) || 'add-menu-item' != $_REQUEST['action'] ) ) {
				return $stored_parent;
			}
		}
		
		foreach( $editable_items as $item ) {
			if ( empty( $item_parents[$item->ID] ) ) {
				// since a top level item is editable, default this new/filtered menu item to under it
				return $item->ID;

			} elseif ( ! in_array( $item_parents[$item->ID], $editable_item_ids ) ) {
				$highest_editable_items[]= $item;
			}
		}
		
		// next best is to default this menu item to below existing items at the highest editable level
		if ( $highest_editable_items && ! defined( 'PP_NAV_MENU_DEFAULT_TO_SUBITEM' ) ) {
			$item = reset( $highest_editable_items );
			return $item_parents[$item->ID];
		}
		
		foreach( $editable_items as $item ) {
			$post_parent =  ( ! empty( $item_parents[$item->ID] ) && ! defined( 'PP_NAV_MENU_DEFAULT_TO_SUBITEM' ) ) ? $item_parents[$item->ID] : $item->ID;
			break;
		}
		
		return $post_parent;
	}
	
	public static function modify_nav_menu_item( $menu_item_id, $menu_operation ) {
		if ( $menu_item = get_post( $menu_item_id ) ) {
			if ( 'nav_menu_item' == $menu_item->post_type ) {
				//$item_type = get_post_meta( $menu_item_id, '_menu_item_type', true );
				$object_type = get_post_meta( $menu_item_id, '_menu_item_object', true );
				$object_id = get_post_meta( $menu_item_id, '_menu_item_object_id', true );

				if ( ! $is_post_type = post_type_exists($object_type) )
					$is_taxonomy = taxonomy_exists($object_type);
		
				// WP performs update on every item even if no values have changed
				if ( 'edit' == $menu_operation ) {
					$posted_vals = array();
					foreach ( array( 'title' => 'menu-item-title', 'attribute' => 'menu-item-attr-title', 'description' => 'menu-item-description', 'target' => 'menu-item-target', 
									 'classes' => 'menu-item-classes', 'xfn' => 'menu-item-xfn', 'menu_order' => 'menu-item-position', 'menu_parent' => 'menu-item-parent-id' ) as $property => $col ) {
						if ( isset( $_POST[$col][$menu_item_id] ) )
							$posted_vals[$property] = $_POST[$col][$menu_item_id];
					}
					
					if ( isset( $posted_vals['classes'] ) )
						$posted_vals['classes'] = array_map( 'sanitize_html_class', explode( ' ', $posted_vals['classes'] ) );

					$stored_vals = array();
					foreach( array( 'title' => 'post_title', 'attribute' => 'post_excerpt', 'description' => 'post_content', 'menu_order' => 'menu_order' ) as $property => $col ) {
						$stored_vals[$property] = trim($menu_item->$col);
					}
					
					$stored_vals['menu_parent'] = get_post_meta( $menu_item_id, '_menu_item_menu_item_parent', true );
					$stored_vals['target'] = get_post_meta( $menu_item_id, '_menu_item_target', true );
					$stored_vals['classes'] = (array) get_post_meta( $menu_item_id, '_menu_item_classes', true );
					$stored_vals['xfn'] = get_post_meta( $menu_item_id, '_menu_item_xfn', true );
					
					if ( empty($stored_val['title']) )
						$stored_vals['title'] = ( $is_post_type ) ? get_post_field( 'post_title', $object_id ) : get_term_field( 'name', $object_id, $object_type );
						
					$changed = false;
					foreach( array_keys($posted_vals) as $property ) {
						if ( $posted_vals[$property] != $stored_vals[$property] ) {
							$changed = true;
							break;
						}
					}
					
					if ( ! $changed )
						return;
				}
				
				if ( $is_post_type ) {	
					$deny_menu_operation = ! current_user_can( 'edit_post', $object_id );
				} elseif ( $is_taxonomy ) {
					if ( $tx_obj = get_taxonomy( $object_type ) ) {
						$deny_menu_operation = ! current_user_can( $tx_obj->cap->manage_terms, $object_id );
					}
				}

				if ( ! empty($deny_menu_operation) ) {
					if ( empty( $stored_vals['title'] ) )
						$stored_vals['title'] = $menu_item->post_title;
					
					if ( empty( $stored_val['title'] ) )
						$stored_vals['title'] = ( $is_post_type ) ? get_post_field( 'post_title', $object_id ) : get_term_field( 'name', $object_id, $object_type );

					$link = admin_url( 'nav-menus.php' );

					switch( $menu_operation ) {
					case 'move':
						wp_die( sprintf( __( 'You do not have permission to move the menu item "%1$s". <br /><br /><a href="%2$s">Return to Menu Editor</a>', 'pp' ), $stored_vals['title'], $link ) );
					break;
					case 'delete':
						wp_die( sprintf( __( 'You do not have permission to delete the menu item "%1$s". <br /><br /><a href="%2$s">Return to Menu Editor</a>', 'pp' ), $stored_vals['title'], $link ) );
					break;
					default:
						wp_die( sprintf( __( 'You do not have permission to edit the menu item "%1$s". <br /><br /><a href="%2$s">Return to Menu Editor</a>', 'pp' ), $stored_vals['title'], $link ) );
					} // end switch
				}
			}
		}
	}

	// transplanted from nav-menus.php
	public static function determine_selected_menu() {
		$nav_menus = wp_get_nav_menus( array('orderby' => 'name') );

		// Get recently edited nav menu
		$recently_edited = (int) get_user_option( 'nav_menu_recently_edited' );

		if ( pp_wp_ver( '3.6-dev' ) ) {
			$menu_count = count( $nav_menus );

			// Are we on the add new screen?
			$add_new_screen = ( isset( $_GET['menu'] ) && 0 == $_GET['menu'] ) ? true : false;

			$locations_screen = ( isset( $_GET['action'] ) && 'locations' == $_GET['action'] ) ? true : false;
		
			// If we have one theme location, and zero menus, we take them right into editing their first menu
			$page_count = wp_count_posts( 'page' );
			$one_theme_location_no_menus = ( 1 == count( get_registered_nav_menus() ) && ! $add_new_screen && empty( $nav_menus ) && ! empty( $page_count->publish ) ) ? true : false;

			$nav_menu_selected_id = isset( $_REQUEST['menu'] ) ? (int) $_REQUEST['menu'] : 0;
			
			if ( empty( $recently_edited ) && is_nav_menu( $nav_menu_selected_id ) )
				$recently_edited = $nav_menu_selected_id;

			// Use $recently_edited if none are selected
			if ( empty( $nav_menu_selected_id ) && ! isset( $_GET['menu'] ) && is_nav_menu( $recently_edited ) )
				$nav_menu_selected_id = $recently_edited;

			// On deletion of menu, if another menu exists, show it
			if ( ! $add_new_screen && 0 < $menu_count && isset( $_GET['action'] ) && 'delete' == $_GET['action'] )
				$nav_menu_selected_id = $nav_menus[0]->term_id;

			// Set $nav_menu_selected_id to 0 if no menus
			if ( $one_theme_location_no_menus ) {
				$nav_menu_selected_id = 0;
			} elseif ( empty( $nav_menu_selected_id ) && ! empty( $nav_menus ) && ! $add_new_screen ) {
				// if we have no selection yet, and we have menus, set to the first one in the list
				$nav_menu_selected_id = $nav_menus[0]->term_id;
			}
			
		} else {  // todo: move to legacy file
			$nav_menu_selected_id = isset( $_REQUEST['menu'] ) ? (int) $_REQUEST['menu'] : 0;
			
			// If there was no recently edited menu, and $nav_menu_selected_id is a nav menu, update recently edited menu.
			if ( !$recently_edited && is_nav_menu( $nav_menu_selected_id ) ) {
				$recently_edited = $nav_menu_selected_id;

			// Else if $nav_menu_selected_id is not a menu and not requesting that we create a new menu, but $recently_edited is a menu, grab that one.
			} elseif ( 0 == $nav_menu_selected_id && ! isset( $_REQUEST['menu'] ) && is_nav_menu( $recently_edited ) ) {
				$nav_menu_selected_id = $recently_edited;

			// Else try to grab the first menu from the menus list
			} elseif ( 0 == $nav_menu_selected_id && ! isset( $_REQUEST['menu'] ) && ! empty($nav_menus) ) {
				$nav_menu_selected_id = $nav_menus[0]->term_id;
			}
		}

		return $nav_menu_selected_id;
	}
	
	public static function can_edit_menu_settings() {
		global $current_user;
		
		$page_type_obj = get_post_type_object( 'page' );
		return pp_unfiltered() || defined('PP_LEGACY_MENU_SETTINGS_ACCESS') || ! empty($current_user->allcaps['manage_menu_settings']) || ( ! empty( $current_user->allcaps[ $page_type_obj->cap->edit_others_posts ] ) && ! empty( $current_user->allcaps[ $page_type_obj->cap->publish_posts ] ) );
	}
	
	public static function flt_update_nav_menu_options( $new_option_value, $old_option_value ) {
		if ( ! self::can_edit_menu_settings() ) {
			$new_option_value = $old_option_value;
			
			// The following sample code is left for possible future need to allow editing of some menu options while locking others
			/*
			$menu_id = self::determine_selected_menu();
			if ( ! $menu_id && isset( $_REQUEST['menu'] ) )
				$menu_id = $_REQUEST['menu'];
			
			if ( ! $menu_id )
				return $new_option_value;
			
			if ( isset( $old_option_value['auto_add'] ) ) {
				$old_key = array_search( $menu_id, $old_option_value['auto_add'] );
			}
			
			if ( isset( $new_option_value['auto_add'] ) ) {
				$new_key = array_search( $menu_id, $new_option_value['auto_add'] );
				
				if ( false !== $new_key ) {
					if ( isset($old_key) && false !== $old_key )
						$new_option_value['auto_add'][$new_key] = $new_option_value['auto_add'][$old_key];
					else
						unset( $new_option_value['auto_add'][$new_key] );
				} else {
					if ( isset($old_key) && false !== $old_key )
						$new_option_value['auto_add'][] = $old_option_value['auto_add'][$old_key];
				}
			}
			*/
		}
		
		return $new_option_value;
	}
}
