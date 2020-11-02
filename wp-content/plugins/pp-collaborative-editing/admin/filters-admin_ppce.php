<?php
class PPCE_AdminFilters
{
	var $inserting_post = false;
	
	// Backend filtering which is generally enabled for all requests 
	//
	function __construct() {
		add_action( 'pp_init', array( &$this, 'disable_forum_pattern_roles' ) );
		
		add_filter( 'pp_enabled_taxonomies', array(&$this, 'flt_get_enabled_taxonomies'), 10, 2 );
		add_filter( 'wp_dropdown_pages', array(&$this, 'flt_dropdown_pages') );

		add_filter( 'pre_post_parent', array( &$this, 'flt_page_parent' ), 50, 1);
		add_filter( 'pre_post_status', array(&$this, 'flt_post_status'), 50, 1);
		
		add_filter( 'user_has_cap', array(&$this, 'flt_has_edit_user_cap'), 99, 3 );
		
		add_filter( 'pp_append_attachment_clause', array( &$this, 'append_attachment_clause' ), 10, 3 );
		
		add_filter( 'pp_operation_captions', array( &$this, 'flt_operation_captions' ) );
		
		// called by permissions-ui
		add_filter( 'pp_exception_types', array( &$this, 'flt_exception_types') );
		add_filter( 'pp_append_exception_types', array( &$this, 'flt_append_exception_types' ) );
		add_action( 'pp_role_types_dropdown', array(&$this, 'act_dropdown_taxonomy_types') );
		add_action( 'pp_exception_types_dropdown', array(&$this, 'act_dropdown_taxonomy_types') );
		
		// called by ajax-exceptions-ui
		add_filter( 'pp_exception_operations', array(&$this, 'flt_exception_operations'), 2, 3 );
		add_filter( 'pp_exception_via_types', array(&$this, 'flt_exception_via_types'), 10, 5 );
		add_filter( 'pp_exceptions_status_ui', array( &$this, 'flt_exceptions_status_ui' ), 4, 3 );
		
		add_filter( 'pp_ajax_role_ui_vars', array( &$this, 'ajax_role_ui_vars' ), 10, 2 );
		add_filter( 'pp_get_type_roles', array( &$this, 'get_type_roles' ), 10, 3 );
		add_filter( 'pp_role_title', array( &$this, 'get_role_title' ), 10, 2 );
		
		// called by agent-edit-handler
		add_filter( 'pp_add_exception', array(&$this, 'flt_add_exception' ) );
		
		// Filtering of terms selection:
		add_filter( 'pre_post_tax_input', array( &$this, 'flt_tax_input' ), 50, 1 );
		add_filter( 'pre_post_category', array( &$this, 'flt_pre_post_terms' ), 50, 1 );
		add_filter( 'pp_pre_object_terms', array( &$this, 'flt_pre_post_terms' ), 50, 2 );
		
		// Track autodrafts by postmeta in case WP sets their post_status to draft
		add_action( 'save_post', array( &$this, 'act_save_post' ), 10, 2 );
		add_filter( 'wp_insert_post_empty_content', array( &$this, 'flt_log_insert_post' ), 10, 2 );
		
		add_filter( 'save_post', array(&$this, 'unload_current_user_exceptions' ) );
		add_filter( 'created_term', array(&$this, 'unload_current_user_exceptions' ) );
		
		add_filter( 'editable_roles', array(&$this, 'flt_editable_roles'), 99 );
	}
	
	function act_save_post( $post_id, $post ) {
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			update_post_meta( $post_id, '_pp_is_autodraft', true );
	}
	
	function unload_current_user_exceptions( $item_id ) {
		global $pp_current_user;
		$pp_current_user->except = array(); // force current user exceptions to be reloaded at relevant next capability check
	}
	
	function disable_forum_pattern_roles() {
		global $pp_role_defs;
		$pp_role_defs->disabled_pattern_role_types = array_merge( $pp_role_defs->disabled_pattern_role_types, array_fill_keys( array('forum', 'topic', 'reply'), true ) );
	}
	
	function flt_get_enabled_taxonomies( $taxonomies, $args ) {
		if ( empty( $args['object_type'] ) || ( 'nav_menu_item' == $args['object_type'] ) )
			$taxonomies['nav_menu'] = 'nav_menu';

		return $taxonomies;
	}
	
	function flt_add_exception( $exception ) {
		if ( '_term_' == $exception['for_type'] ) {
			$exception['for_type'] = $exception['via_type'];
		}
	
		return $exception;
	}
	
	function flt_exception_types( $types ) {
		if ( ! isset( $types['attachment'] ) )
			$types['attachment'] = get_post_type_object( 'attachment' );

		return $types;
	}
	
	function flt_append_exception_types( $types ) {
		$types['pp_group'] = (object) array( 'name' => 'pp_group', 'labels' => (object) array( 'singular_name' => __( 'Permission Group', 'pp' ), 'name' => __( 'Permission Groups', 'pp' ) ) );
		return $types;
	}
	
	function act_dropdown_taxonomy_types( $args = array() ) {
		if ( empty($args['agent']) || empty($args['agent']->metagroup_id) || ! in_array( $args['agent']->metagroup_id, array( 'wp_anon', 'wp_all' ) ) )
			echo "<option value='_term_'>" . __('term (manage)', 'pp') . '</option>';
	}
	
	function flt_operation_captions( $op_captions ) {
		require_once( dirname(__FILE__).'/ajax-ui_ppce.php');
		return PPCE_Permissions_Ajax::flt_operation_captions( $op_captions );
	}
	
	function flt_exception_operations( $ops, $for_src_name, $for_item_type ) {
		require_once( dirname(__FILE__).'/ajax-ui_ppce.php');
		return PPCE_Permissions_Ajax::flt_exception_operations( $ops, $for_src_name, $for_item_type );
	}
	
	function flt_exception_via_types( $types, $for_src_name, $for_type, $operation, $mod_type ) {
		require_once( dirname(__FILE__).'/ajax-ui_ppce.php');
		return PPCE_Permissions_Ajax::flt_exception_via_types( $types, $for_src_name, $for_type, $operation, $mod_type );
	}
	
	function flt_exceptions_status_ui( $html, $for_type, $args = array() ) {
		require_once( dirname(__FILE__).'/ajax-ui_ppce.php');
		return PPCE_Permissions_Ajax::flt_exceptions_status_ui( $html, $for_type, $args );
	}
	
	function get_role_title( $role_title, $args ) {
		$matches = array();
		preg_match("/pp_(.*)_manager/", $role_title, $matches);
		
		if ( ! empty( $matches[1] ) ) {
			$taxonomy = $matches[1];
			if ( $tx_obj = get_taxonomy( $taxonomy ) )
				$role_title = sprintf( __( '%s Manager', 'pp' ), $tx_obj->labels->singular_name );
		}
		
		return $role_title;
	}
	
	function ajax_role_ui_vars( $force, $args ) {
		if ( 0 === strpos( $args['for_item_type'], '_term_' ) ) {
			$force = (array) $force;
			$force['for_item_source'] = 'term';
			$force['for_item_type'] = substr( $args['for_item_type'], strlen('_term_') );
		}
		
		return $force;
	}
	
	function get_type_roles( $type_roles, $for_item_source, $for_item_type ) {
		if ( 'term' == $for_item_source ) {
			foreach( pp_get_enabled_taxonomies( array( 'object_type' => false ) ) as $taxonomy ) {
				$type_roles["pp_{$taxonomy}_manager"] = ppc_get_role_title( "pp_{$taxonomy}_manager" );
			}
		}
	
		return $type_roles;
	}
	
	function flt_tax_input( $tax_input ) {
		require_once( dirname(__FILE__).'/post-terms-save_ppce.php');
		return PPCE_PostTermsSave::flt_tax_input( $tax_input );
	}

	function flt_pre_post_terms( $terms, $taxonomy = 'category' ) {
		require_once( dirname(__FILE__).'/post-terms-save_ppce.php');
		return PPCE_PostTermsSave::flt_pre_object_terms( $terms, $taxonomy );
	}
	
	/* // this is now handled by flt_pre_object_terms instead
	function flt_default_term( $default_term_id, $taxonomy = 'category' ) {
		require_once( dirname(__FILE__).'/post-terms-save_ppce.php');
		return PPCE_PostTermsSave::flt_default_term( $default_term_id, $taxonomy );
	}
	*/
	
	// Optionally, prevent anyone from editing or deleting a user whose level is higher than their own
	function flt_has_edit_user_cap($wp_sitecaps, $orig_reqd_caps, $args) {
		if ( defined( 'PP_ENABLE_QUERYFILTERS' ) && ( in_array( 'edit_users', $orig_reqd_caps ) || in_array( 'delete_users', $orig_reqd_caps ) || in_array( 'remove_users', $orig_reqd_caps ) || in_array( 'promote_users', $orig_reqd_caps ) ) && ! empty($args[2]) ) {
			if ( pp_get_option('limit_user_edit_by_level') ) {
				require_once( dirname(__FILE__).'/user_lib_ppce.php' );
				$wp_sitecaps = PPCE_UserEdit::has_edit_user_cap( $wp_sitecaps, $orig_reqd_caps, $args );
			}
		}
	
		return $wp_sitecaps;
	}
	
	function flt_log_insert_post( $maybe_empty, $postarr ) {
		$this->inserting_post = true;
		return $maybe_empty;
	}
	
	function flt_page_parent ( $parent_id, $args = array() ) {
		if ( ! defined( 'PP_ENABLE_QUERYFILTERS' ) || did_action( 'pp_disable_page_parent_filter' ) || ( $this->inserting_post ) )
			return $parent_id;
		
		require_once( dirname(__FILE__).'/post-save-hierarchical_ppce.php');
		return PPCE_PostSaveHierarchical::flt_page_parent( $parent_id );
	}
	
	// filter page dropdown contents for Page Parent controls; leave others alone
	function flt_dropdown_pages($orig_options_html) {
		if ( pp_unfiltered() || ( ! strpos( $orig_options_html, 'parent_id' ) && ! strpos( $orig_options_html, 'post_parent' ) ) )
			return $orig_options_html;

		global $pagenow;
			
		if ( 0 === strpos( $pagenow, 'options-' ) )
			return $orig_options_html;
			
		require_once( PPCE_ABSPATH . '/hardway/hardway-parent_ppce.php' );
		return PP_HardwayParent::flt_dropdown_pages($orig_options_html);
	}
	
	function flt_post_status( $status ) {
		if ( pp_unfiltered() || ( 'auto-draft' == $status ) || strpos( $_SERVER['REQUEST_URI'], 'nav-menus.php' ) )
			return $status;
			
		require_once( dirname(__FILE__).'/post-edit_ppce.php');
		return PPCE_PostEditHelper::flt_post_status( $status );
	}
	
	function append_attachment_clause( $where, $clauses, $args ) {
		require_once( dirname(__FILE__).'/query-attachments_ppce.php' );
		return PPCE_QueryAttachments::append_attachment_clause( $where, $clauses, $args );
	}
	
	// optional filter for WP role edit based on user level
	function flt_editable_roles( $roles ) {
		if ( ! defined( 'PP_ENABLE_QUERYFILTERS' ) || ! pp_get_option('limit_user_edit_by_level') )
			return $roles;
		
		require_once( dirname(__FILE__).'/user_lib_ppce.php' );
		return PPCE_UserEdit::editable_roles( $roles );
	}
}

function ppce_user_can_associate_main( $post_type ) {
	if ( pp_unfiltered() )
		return true;

	if ( ! $post_type_obj = get_post_type_object($post_type) )
		return true;
		
	if ( ! $post_type_obj->hierarchical )
		return true;
	
	require_once( dirname(__FILE__).'/post-edit_ppce.php');
	return PPCE_PostEditHelper::user_can_associate_main( $post_type );
}

function ppce_get_user_default_terms( $taxonomy ) {
	return apply_filters( 'pp_pre_object_terms', array(), $taxonomy );
}
