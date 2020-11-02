<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PPCE_CapInterceptor {
	function __construct() {
		add_filter( 'pp_has_post_cap_vars', array(&$this, 'has_post_cap_vars'), 10, 4 );
		
		add_filter( 'pp_cap_operation', array( &$this, 'cap_operation' ), 10, 3 );
		add_filter( 'pp_exception_stati', array( &$this, 'exception_stati' ), 10, 4 );
		
		add_filter( 'pp_user_has_cap_params', array( &$this, 'user_has_cap_params' ), 10, 3 );
		
		add_filter( 'pp_force_post_metacap_check', array( &$this, 'force_post_metacap_check' ), 10, 2 );
		
		add_action( 'pp_has_post_cap_pre', array(&$this, 'save_post_permissions_maint'), 10, 4 );
		
		// REST logging and blockers
		add_filter( 'rest_pre_dispatch', array( &$this, 'rest_pre_dispatch' ), 10, 3 );
		
		require_once( dirname(__FILE__).'/rest_ppce.php' );
	}
	
	// block unpermitted create/edit/delete requests; log request and handler parameters for possible reference by subsequent PP filters 
	function rest_pre_dispatch( $rest_response, $rest_server, $request ) {
		if ( pp_is_content_administrator() ) {
			return $rest_response;
		}
		
		global $ppce_admin_non_administrator;
		
		if ( empty($ppce_admin_non_administrator) ) {
			require_once( dirname(__FILE__).'/admin/admin-non_administrator_ppce.php' );
			$ppce_admin_non_administrator = new PPCE_Admin_NonAdministrator();
		}
		
		global $ppce_rest;
		require_once( dirname(__FILE__).'/rest-non_administrator_ppce.php' );
		$ppce_rest = new PPCE_REST();
		
		return $ppce_rest->pre_dispatch( $rest_response, $rest_server, $request );
	}

	function force_post_metacap_check( $force, $args ) {
		global $cap_interceptor;
		global $query_interceptor;
		
		extract( $args, EXTR_SKIP );   // ( 'is_post_cap', 'item_id', 'orig_cap', 'item_type', 'orig_reqd_caps' )
		
		if ( $is_post_cap && $item_id ) {
			$_item_type = $cap_interceptor->post_type_from_caps( (array) $orig_cap );
			$type_obj = get_post_type_object( $_item_type );
			
			if ( empty($op) ) {
				$base_caps = $query_interceptor->get_base_caps( (array) $orig_cap, $_item_type );
				$base_cap = reset( $base_caps );
				$op = $this->cap_operation( 'edit', $base_cap, $_item_type );
			}
			
			if ( $type_obj && in_array( $orig_cap, array( $type_obj->cap->edit_others_posts ) ) ) {
				$orig_cap = "{$op}_post";  // honor literal capability check if user passes corresponding metacap check for current item
				return $orig_cap;
			}
		}

		return $force;
	}
	
	function user_has_cap_params( $params, $orig_reqd_caps, $args ) {
		global $ppce_cap_helper;
		
		extract( $args, EXTR_SKIP );
		
		$return = array();
		
		if ( 'edit_comment' == $orig_cap ) {
			/*
			if ( ! in_array( 'moderate_comments', $orig_reqd_caps ) ) {	 // as of WP 3.2.1, 'edit_comment' maps to related post's 'edit_post' caps without requiring moderate_comments
				$orig_reqd_caps[] = 'moderate_comments';
			}
			*/
			
			if ( $comment = get_comment( $item_id ) ) {
				$return['item_id'] = $comment->comment_post_ID;
				if ( $_post = get_post( $comment->comment_post_ID ) ) {
					$return['item_type'] = $_post->post_type;
					$return['item_status'] = $_post->post_status;
				}
			} else
				$return['item_id'] = 0;
		}
		
		if ( $return )
			return ( is_array($params) ) ? array_merge( $params, $return ) : $return;
		else
			return $params;
	}
	
	function exception_stati( $stati, $item_status, $op, $args = array() ) {
		$status_obj = get_post_status_object( $item_status );
		
		if ( ! empty( $args['item_type']) && ! empty( $args['orig_reqd_caps'] ) ) {
			// don't grant publish cap based on a status-specific term addition (such as "unpublished")
			$type_obj = get_post_type_object( $args['item_type'] );
			
			if ( ! defined( 'PP_PUBLISH_EXCEPTIONS' ) && $type_obj && ( reset( $args['orig_reqd_caps'] ) == $type_obj->cap->publish_posts ) ) {
				$stati = array( '' );
				
				if ( ! $item_status || $status_obj->public ) 
					$stati[]= 'post_status:publish';
					
				return $stati;
			}
		}

		if ( ( 'read' != $op ) && ( !$item_status || ( ! $status_obj->public && ! $status_obj->private ) ) ) {
			$stati['post_status:{unpublished}'] = true;
			//$stati['{unpublished}'] = true;
		}

		return $stati;
	}
	
	function cap_operation( $op, $base_cap, $item_type ) {
		if ( ! $type_obj = get_post_type_object( $item_type ) )
			return '';
		
		switch( $base_cap ) {
			case $type_obj->cap->edit_posts :
				$op = 'edit';
				break;
			case $type_obj->cap->publish_posts :
				$op = ( defined( 'PP_PUBLISH_EXCEPTIONS' ) ) ? 'publish' : 'edit';
				break;
			case $type_obj->cap->delete_posts :
				$op = 'delete';
				break;
		}
		
		return $op;
	}
	
	function has_post_cap_vars( $force_vars, $wp_sitecaps, $pp_reqd_caps, $vars ) {
		extract($vars, EXTR_SKIP);	 // compact( 'post_type', 'post_id', 'user_id', 'required_operation' )
		
		$return = array();
		
		//=== If autodraft ID was supplied... ===
		if ( $post_id && is_admin() ) {
			if ( $is_auto_draft = ( 'auto-draft' == get_post_field( 'post_status', $post_id ) ) ) {
				// (force autosave editing cap so PP exceptions can be applied)
				
				//global $action;
				//if ( in_array( $action, array( 'post-quickpress-save', 'post-quickpress-publish' ) ) ) {
					// QuickPress by limited user
					$post_type = get_post_field( 'post_type', $post_id );
					
					if ( $type_obj = get_post_type_object( $post_type ) ) {
						if ( in_array( reset($pp_reqd_caps), array( 'edit_post', 'edit_page' ) ) ) {
							$return['return_caps'] = array( $type_obj->cap->edit_posts => true );
						}
					}
				//} else {
				//	$return['post_id'] = 0;	
				//}
			}
		}
		
		//=== For revisions, pretend questioned object is the parent post
		//
		if ( $post_id && in_array( $post_type, array( 'revision' ) ) ) {
			if ( $_post = get_post( $post_id ) ) {
				if ( $_post->post_parent && $_parent = get_post($_post->post_parent) ) {
					/*if ( 'revision' == $_parent->post_type ) {
						// attachment parent is a revision, go up one more level (edit_posts cap check on attachments to revision, with Revisionary)
						if ( $_orig_post = get_post($_parent->post_parent) ) {
							$post_type = $_orig_post->post_type;
							$post_id = $_orig_post->ID;
						}						
					} else { */
						global $current_user;
					
						// parent is a regular post type
						$post_type = $_parent->post_type;
						$post_id = $_parent->ID;
						
						if ( ( $_parent->post_status == 'auto-draft' ) && ( $_parent->post_author == $current_user->ID ) ) {
							$post_id = 0;
						}
					//}
					
					if ( 'inherit' == $_post->post_status ) {
						$return['required_operation'] = 'edit';
					}
					
					$return['post_type'] = $post_type;
					$return['post_id'] = $post_id;
					
					if ( ( 'fork' == $post_type ) && ( empty($required_operation) || ( 'read' == $required_operation ) ) ) {
						$return['required_operation'] = 'edit';
					}
				}
				
				global $pp_current_user;
				
				/*if ( 'revision' == $_post->post_type ) {
					require_once( dirname(__FILE__).'/admin/revisions_lib_ppe.php' );
					$rev_where = ( RVY_VERSION && rvy_get_option( 'revisor_lock_others_revisions' ) ) ? " AND post_author = '{$pp_current_user->ID}'" : '';  // might need to apply different cap requirement for other users' revisions
					$cap_interceptor->revisions = ppe_get_post_revisions($_post->post_parent, 'inherit', array( 'fields' => 'id', 'return_flipped' => true, 'where' => $rev_where ) );
				} else { */
					//=== Special case of Attachment uploading: uploading user should have their way with their own orphan attachments
					//
					if ( ! $_post->post_parent && ( $_post->post_author == $pp_current_user->ID ) ) {
						$return['return_caps'] = array_merge( $wp_sitecaps, $pp_reqd_caps );
					}
				//} 
			} // endif retrieved post

		} else { // post_id is not a revision
			if ( ( 'read' == $required_operation ) && ( strpos( $_SERVER['SCRIPT_NAME'], 'wp-admin/revision.php' ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset($_REQUEST['action']) && ( 'get-revision-diffs' == $_REQUEST['action'] ) ) ) ) {
				$return['required_operation'] = 'edit';
			}
		}
	
		global $pagenow;
		if ( 'async-upload.php' == $pagenow ) {
            if ( 'upload_files' == reset($pp_reqd_caps) ) {  // don't apply any exceptions for upload_files requirement on media upload
                $return['return_caps'] = $wp_sitecaps;
            } elseif ( ! empty( $wp_sitecaps['upload_files'] ) ) {
                $_post = ( $post_id ) ? get_post( $post_id ) : false;
				
				if ( ! $_post || ( 'attachment' == $_post->post_type ) ) {
					if ( in_array( 'edit_posts', $pp_reqd_caps ) )
						$return['return_caps'] = array_merge( $wp_sitecaps, array( 'edit_posts' => true ) );
					elseif ( in_array( 'edit_post', $pp_reqd_caps ) )
						$return['return_caps'] = array_merge( $wp_sitecaps, array( 'edit_post' => true ) );
				}
            }
        }
	
		return ( $return ) ? array_merge( (array) $force_vars, $return ) : $force_vars;

		// note: PP_CapInterceptor::flt_user_has_cap() filters return array to allowed variables before extracting
	}

	function save_post_permissions_maint( $pp_reqd_caps, $src_name, $object_type, $post_id ) {
		// Workaround to deal with WP core's checking of publish cap prior to storing categories:
		// Store terms to DB in advance of any cap-checking query which may use those terms to qualify an operation.	
		if ( ! empty($_REQUEST['action']) ) {
			if ( in_array( $_REQUEST['action'], array( 'editpost', 'autosave' ) ) ) {
				require_once( dirname(__FILE__).'/admin/cap-interceptor-save_ppce.php' );
				
				if ( 'post' == $src_name ) {
					if ( PPCE_EditCapHelper::pre_assign_terms( $pp_reqd_caps, $object_type, $post_id ) ) {
						// delete any buffered cap check results which were queried prior to storage of these object terms
						
						// byref argument was actually ineffective prior to 2.3.15, so maintain that behavior
						//global $cap_interceptor;
						//$cap_interceptor->memcache = array();
					}
					
					// assign propagating exceptions in case they are needed for a cap check at post creation
					if ( is_post_type_hierarchical( $object_type ) && array_intersect( $pp_reqd_caps, array( 'edit_post', 'edit_page' ) ) 
					&& ( empty( $_REQUEST['page'] ) || ( 'rvy-revisions' != $_REQUEST['page'] ) )
					&& ( ! defined('DOING_AUTOSAVE') || ! DOING_AUTOSAVE )
					) {
						require_once( PPC_ABSPATH.'/admin/post-save_pp.php' );
						require_once( PPC_ABSPATH.'/admin/item-save_pp.php' );
						
						if ( method_exists( 'PP_ItemSave', 'inherit_parent_exceptions' ) ) { // method added in PP Core 2.0.24-beta
							if ( $is_new = PP_PostSave::is_new_post( $post_id ) ) {
								$parent_info = PP_PostSave::get_post_parent_info( $post_id );
								extract( $parent_info, EXTR_SKIP );	// $set_parent, $last_parent

								if ( $set_parent && ( $set_parent != $last_parent ) ) { // not theoretically necessary, but an easy safeguard to avoid re-inheriting parent roles
									$via_item_source = 'post';
									$_args = compact( 'via_item_source', 'set_parent', 'last_parent', 'is_new' );
								
									if ( PP_ItemSave::inherit_parent_exceptions( $post_id, $_args ) ) {
										global $pp_current_user;
										$pp_current_user->except = array();
									}
								}
							}
						}
					}
				}
			}
		}
	}
}
