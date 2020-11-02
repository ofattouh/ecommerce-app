<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( did_action( 'plugins_loaded' ) )
	PP_Rvy_Helper::init_rvy_interface();
else
	add_action( 'plugins_loaded', array( 'PP_Rvy_Helper', 'init_rvy_interface' ) );

add_filter( 'map_meta_cap', array( 'PP_Rvy_Helper', 'flt_map_meta_cap' ), 1, 4 );
add_filter( 'pre_post_parent', array('PP_Rvy_Helper', 'flt_page_parent') );
add_filter( 'pp_get_exception_items', array( 'PP_Rvy_Helper', 'flt_get_exception_items' ), 10, 5 );

add_filter( 'pp_additions_clause', array( 'PP_Rvy_Helper', 'flt_additions_clause' ), 10, 4 );

add_filter( 'pp_administrator_caps', array( 'PP_Rvy_Helper', 'flt_pp_administrator_caps' ), 5 );
add_filter( 'pp_term_include_clause', array( 'PP_Rvy_Helper', 'flt_pp_term_include_clause' ), 10, 2 );
add_filter( 'pp_exception_clause', array( 'PP_Rvy_Helper', 'flt_pp_exception_clause' ), 10, 4 );

class PP_Rvy_Helper {
	public static function init_rvy_interface() {
		if ( class_exists('RevisionaryContentRoles') ) {
			global $revisionary;
			if ( ! empty($revisionary) && method_exists( $revisionary, 'set_content_roles' ) ) {
				require_once( dirname(__FILE__).'/revisionary-content-roles_ppce.php' );
				$revisionary->set_content_roles( new PP_RvyContentRoles() );
			}
		}
	}

	public static function flt_pp_administrator_caps( $caps ) {
		// TODO: why is edit_others_revisions cap required for Administrators in Edit Posts listing (but not Edit Pages) ?
		
		//if ( $type_obj = get_post_type_object( 'revision' ) ) {	
			$caps['edit_revisions'] = true;	
			$caps['edit_others_revisions'] = true;
			$caps['delete_revisions'] = true;
			$caps['delete_others_revisions'] = true;
		//}
		
		return $caps;
	}

	public static function flt_pp_exception_clause( $clause, $required_operation, $post_type, $args = array() ) {
		$defaults = array( 'logic' => 'NOT IN', 'ids' => array(), 'src_table' => '' );
		extract( array_merge( $defaults, $args ), EXTR_SKIP );
		return "( $clause OR ( $src_table.post_type = 'revision' AND $src_table.post_parent $logic ('" . implode( "','", $ids ) . "') ) )";
	}
	
	public static function flt_pp_term_include_clause( $clause, $args = array() ) {
		global $wpdb;
		
		$defaults = array( 'tt_ids' => array(), 'src_table' => '' );
		extract( array_merge( $defaults, $args ), EXTR_SKIP );
		
		$clause .= " OR ( $src_table.post_type='revision' AND $src_table.post_parent IN ( SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id IN ('" . implode( "','", $tt_ids ) . "') ) )";
		return $clause;
	}
	
	public static function flt_page_parent ( $parent_id ) {
		global $revisionary;
		if ( ! empty($revisionary->admin->revision_save_in_progress) ) {
			do_action( 'pp_disable_page_parent_filter' );
		}

		return $parent_id;
	}
	
	public static function flt_additions_clause( $clause, $operation, $post_type, $args ) {
		//$args = compact( 'status', 'in_clause', 'src_table' ) 
	
		if ( in_array( $operation, array( 'edit', 'delete' ) ) && empty( $args['status'] ) && ! in_array( $post_type, apply_filters( 'pp_unrevisable_types', array() ) ) ) {
			global $pp_current_user;
			$clause .= " OR ( {$args['src_table']}.post_type = 'revision' AND {$args['src_table']}.post_status IN ('pending', 'scheduled' ) AND {$args['src_table']}.post_author = $pp_current_user->ID AND {$args['src_table']}.post_parent {$args['in_clause']} )";
		}

		return $clause;
	}
	
	public static function flt_map_meta_cap( $caps, $meta_cap, $user_id, $wp_args ) {
		if ( in_array( $meta_cap, array( 'edit_post', 'edit_page' ) ) ) {
			global $current_user;
			if ( $user_id != $current_user->ID )
				return $caps;
			
			if ( rvy_get_option('pending_revisions') ) {
				$do_revision_clause = false;
				
				if ( isset($wp_args[0]) ) {
					$_post = ( is_object($wp_args[0]) ) ? $wp_args[0] : get_post($wp_args[0]);

					if ( $_post ) {
						$caps = self::convert_post_edit_caps( $caps, $_post->post_type );
						//$caps = PP_Rvy_Helper::adjust_revision_reqd_caps( $caps, $_post->post_type, $do_revision_clause );
					}
				}
			}
			
			if ( ! rvy_get_option( 'require_edit_others_drafts' ) )
				return $caps;
			
			// for pp_map_meta_cap()
			if ( isset($wp_args[0]) && is_object($wp_args[0]) ) {
				global $current_user;

				if ( $current_user->ID == $wp_args[0]->post_author )
					return $caps;
					
				$status_obj = get_post_status_object( $wp_args[0]->post_status );
				if ( $status_obj && ( $status_obj->public || $status_obj->private ) )
					return $caps;
				
				if ( 'revision' == $wp_args[0]->post_type )
					return $caps;
				
				$post_type_obj = get_post_type_object( $wp_args[0]->post_type );
				if ( ! empty( $current_user->allcaps[ $post_type_obj->cap->edit_published_posts ] ) )	// don't require any additional caps for sitewide Editors
					return $caps;
					
				$caps[]= 'edit_others_drafts';
			}
		}
		return $caps;
	}
	
	
	/*
	
	
	// prevent revisors from editing other users' regular drafts and pending posts
	function flt_limit_others_drafts( $caps, $meta_cap, $user_id, $args ) {
		$object_id = ( is_array($args) && ! empty($args[0]) ) ? $args[0] : $args;
		
		if ( ! $object_id || ! is_scalar($object_id) || ( $object_id < 0 ) )
			return $caps;
		
		if ( ! rvy_get_option( 'require_edit_others_drafts' ) )
			return $caps;
		
		if ( $post = get_post( $object_id ) ) {
			if ( 'revision' != $post->ID ) {
				global $current_user;
			
				$status_obj = get_post_status_object( $post->post_status );
			
				if ( ( $current_user->ID != $post->post_author ) && $status_obj && ! $status_obj->public && ! $status_obj->private ) {
					$post_type_obj = get_post_type_object( $post->post_type );
					if ( current_user_can( $post_type_obj->cap->edit_published_posts ) ) {	// don't require any additional caps for sitewide Editors
						return $caps;
					}
				
					static $stati;
					static $private_stati;
				
					if ( ! isset($public_stati) ) {
						$stati = get_post_stati( array( 'internal' => false, 'protected' => true ) );
						$stati = array_diff( $stati, array( 'future' ) );
					}
					
					if ( in_array( $post->post_status, $stati ) ) {
						//if ( $post_type_obj = get_post_type_object( $post->post_type ) ) {
							$caps[]= "edit_others_drafts";
						//}
					}
				}
			}
		}
		
		return $caps;
	}
	
	
	*/
	
	
	
	
	// merge revise exceptions into edit exceptions
	public static function flt_get_exception_items( $exception_items, $operation, $mod_type, $for_item_type, $args = array() ) {
		if ( 'edit' != $operation )
			return $exception_items;
		
		global $revisionary;
		
		if ( empty( $revisionary->skip_revision_allowance ) ) {
			global $pp_current_user;
			
			$defaults = array( 'via_item_source' => 'post', 'via_item_type' => '' );
			extract( array_merge( $defaults, $args ), EXTR_SKIP );
			
			if ( ! isset($pp_current_user->except['revise_post']) ) {
				$pp_current_user->retrieve_exceptions( 'revise', 'post' );
			}
			
			if ( ! isset( $pp_current_user->except['revise_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type] ) )
				return $exception_items;
			
			$exception_items = ( isset($pp_current_user->except['edit_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type]) ) ? $pp_current_user->except['edit_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type] : array();
			
			foreach( array_keys( $pp_current_user->except['revise_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type] ) as $_status ) {
				pp_set_array_elem( $exception_items, array( $_status ) );
				$exception_items[$_status] = array_merge( $exception_items[$_status], $pp_current_user->except['revise_post'][$via_item_source][$via_item_type][$mod_type][$for_item_type][$_status] );
			}
			
			$status = ( isset( $args['status'] ) ) ? $args['status'] : '';
			
			if ( true === $status )
				return $exception_items;
			else 
				return pp_array_flatten( array_intersect_key( $exception_items, array( $status => true ) ) );
		}
		
		return $exception_items;
	}
	
	public static function adjust_revision_reqd_caps( $reqd_caps, $object_type, &$do_revision_clause ) {		
		global $revisionary;
		
		if ( empty( $revisionary->skip_revision_allowance ) ) {
			global $pp_plugin_page, $pagenow;
			
			$revision_uris = apply_filters( 'pp_revision_uris', array( 'edit.php', 'upload.php', 'widgets.php', 'admin-ajax.php', 'rvy-revisions' ) );

			if ( is_admin() || ! empty( $_GET['preview'] ) )
				$revision_uris []= 'index.php';	

			$plugin_page = is_admin() ? $pp_plugin_page : '';

			if ( is_preview() || in_array( $pagenow, $revision_uris ) || in_array( $plugin_page, $revision_uris ) ) {
				$strip_capreqs = array();

				foreach( (array) $object_type as $_object_type ) {
					if ( $type_obj = get_post_type_object( $_object_type ) ) {
						$strip_capreqs = array_merge( $strip_capreqs, apply_filters( 'rvy_replace_post_edit_caps', array( $type_obj->cap->edit_published_posts, $type_obj->cap->edit_private_posts ), $_object_type, 0 ) );
						
						if ( array_intersect( $reqd_caps, $strip_capreqs ) )
							$reqd_caps []= $type_obj->cap->edit_posts;
					}
				}

				$reqd_caps = array_unique( array_diff($reqd_caps, $strip_capreqs) );
			}
			
			$do_revision_clause = true;
		}	
		
		return $reqd_caps;
	}
	
	// Allow contributors and revisors to edit published post/page, with change stored as a revision pending review
	public static function convert_post_edit_caps( $rs_reqd_caps, $post_type )	{
		global $revisionary;

		if ( ! empty( $revisionary->skip_revision_allowance ) || ! rvy_get_option('pending_revisions') )
			return $rs_reqd_caps;
		
		$post_id = pp_get_post_id();
			
		if ( ! defined('DOING_AJAX') || ! DOING_AJAX ) {
			// don't need to fudge the capreq for post.php unless existing post has public/private status
			$status = get_post_field( 'post_status', $post_id, 'post' );
			$status_obj = get_post_status_object( $status );
			
			if ( empty( $status_obj->public ) && empty( $status_obj->private ) && ( 'future' != $status ) ) 
				return $rs_reqd_caps;
		}
		
		if ( $type_obj = get_post_type_object( $post_type ) ) {
			$replace_caps = apply_filters( 'rvy_replace_post_edit_caps', array( 'edit_published_posts', 'edit_private_posts', 'publish_posts', $type_obj->cap->edit_published_posts, $type_obj->cap->edit_private_posts, $type_obj->cap->publish_posts ), $post_type, $post_id );
			$use_cap_req = $type_obj->cap->edit_posts;
		} else
			$replace_caps = array();		
		
		if ( array_intersect( $rs_reqd_caps, $replace_caps) ) {	
			foreach ( $rs_reqd_caps as $key => $cap_name )
				if ( in_array($cap_name, $replace_caps) )
					$rs_reqd_caps[$key] = $use_cap_req;
		}

		return $rs_reqd_caps;
	}
	
	// ensure proper cap requirements when a non-Administrator Quick-Edits or Bulk-Edits Posts/Pages (which may be included in the edit listing only for revision submission)
	public static function fix_table_edit_reqd_caps( $pp_reqd_caps, $orig_meta_cap, $_post, $object_type_obj ) {
		foreach( array( 'edit', 'delete' ) as $op ) {
			if ( in_array( $orig_meta_cap, array( "{$op}_post", "{$op}_page" ) ) ) {
				$status_obj = get_post_status_object( $_post->post_status );
				foreach( array( 'public' => 'published', 'private' => 'private' ) as $status_prop => $cap_suffix ) {
					if ( ! empty($status_obj->$status_prop) ) {
						global $revisionary;
						$cap_prop = "{$op}_{$cap_suffix}_posts";
						$pp_reqd_caps[]= $object_type_obj->cap->$cap_prop;
						$revisionary->skip_revision_allowance = true;
					}
				}
			}
		}
		return $pp_reqd_caps;
	}
} // end class
