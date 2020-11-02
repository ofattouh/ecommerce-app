<?php
class PPCE_AdminFiltersObjectUI {
	function __construct() {
		add_action( 'admin_head', array(&$this, 'ui_hide_admin_divs') );
		add_action( 'admin_print_scripts', array(&$this, 'ui_add_js') );
		add_action( 'admin_print_footer_scripts', array(&$this, 'ui_add_author_link') );
		add_action( 'admin_print_footer_scripts', array(&$this, 'suppress_upload_ui') );
		add_action( 'admin_print_footer_scripts', array(&$this, 'suppress_add_category_ui') );
		
		add_filter( 'pp_item_edit_exception_ops', array( &$this, 'flt_item_edit_exception_ops' ), 10, 3 );
		
		if ( ! empty($_REQUEST['message']) && ( 6 == $_REQUEST['message'] ) )
			add_filter( 'post_updated_messages', array( &$this, 'flt_post_updated_messages' ) );
			
		add_filter( 'pp_get_pages_clauses', array( &$this, 'flt_get_pages_clauses' ), 10, 3 );
		
		$post_type = pp_find_post_type();
		if ( $post_type && pp_get_type_option( 'default_privacy', $post_type ) )
			add_action('admin_footer', array(&$this, 'default_privacy_js') );
	}
	
	function flt_get_pages_clauses( $clauses, $post_type, $args ) {
		global $wpdb, $pp_current_user, $post;
		
		$col_id = ( strpos( $clauses['where'], $wpdb->posts ) ) ? "$wpdb->posts.ID" : "ID";
		$col_status = ( strpos( $clauses['where'], $wpdb->posts ) ) ? "$wpdb->posts.post_status" : "post_status";
		
		// never offer to set a descendant as parent
		if ( ! empty( $post ) && ! empty( $post->ID ) ) {
			require_once( dirname(__FILE__).'/post-save-hierarchical_ppce.php' );
			$descendants = PPCE_PostSaveHierarchical::get_page_descendant_ids( $post->ID );
			$descendants[]= $post->ID;
			$clauses['where'] .= " AND $col_id NOT IN ('" . implode("','", $descendants) . "')";
		}
		
		if ( ! current_user_can( 'pp_associate_any_page' ) ) {
			if ( $restriction_where = PP_Hardway::get_restriction_clause( 'associate', $post_type, compact( 'col_id' ) ) )
				$clauses['where'] .= $restriction_where;
		}
		
		if ( $additional_ids = $pp_current_user->get_exception_posts( 'associate', 'additional', $post_type ) ) {
			if ( empty( $clauses['where'] ) )
				$clauses['where'] = 'AND 1=1';
			
			$clauses['where'] = " AND ( ( 1=1 {$clauses['where']} ) OR ( $col_id IN ('" . implode("','", array_unique($additional_ids) ) . "') AND $col_status NOT IN ('" . implode( "','", get_post_stati( array( 'internal' => true ) ) ) . "') ) )";
		}
		
		return $clauses;
	}
	
	function flt_item_edit_exception_ops( $operations, $for_src_name, $for_item_type ) {
		foreach( array( 'edit', 'fork', 'revise', 'associate' ) as $op ) {
			if ( _pp_can_set_exceptions( $op, $for_item_type, array( 'via_src_name' => 'post', 'for_src_name' => $for_src_name ) ) )
				$operations[$op] = true;
		}
		
		return $operations;
	}
	
	function flt_post_updated_messages( $messages ) {
		if ( ! pp_unfiltered() ) {
			if ( $type_obj = pp_get_type_object( 'post', pp_find_post_type() ) ) {
				if ( ! current_user_can($type_obj->cap->publish_posts) ) {
					$messages['post'][6] = __( 'Post Approved', 'pp' );
					$messages['page'][6] = __( 'Page Approved', 'pp' );
				}
			}
			return $messages;
		}
	}
	
	function ui_hide_admin_divs() {
		global $pagenow;
		if ( ! in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) )
			return;
		
		if ( ! $object_type = pp_find_post_type() )
			return;

		// For this data source, is there any html content to hide from non-administrators?
		if ( $hide_ids = pp_get_option( 'editor_hide_html_ids' ) ) {
			require_once( dirname(__FILE__).'/post-ui-customize_ppce.php' );
			PPCE_CustomizePostUI::hide_admin_divs( $hide_ids, $object_type );
		}
	}
	
	function ui_add_js() {
		global $wp_scripts;
		
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
		wp_enqueue_script( 'pp_listbox', PP_URLPATH . "/admin/js/listbox{$suffix}.js", array('jquery', 'jquery-form'), PPC_VERSION, true );
		$wp_scripts->in_footer []= 'pp_listbox';
		wp_localize_script( 'pp_listbox', 'ppListbox', array( 'omit_admins' => '1', 'metagroups' => 1 ) );
	
		wp_enqueue_script( 'pp_agent_select', PP_URLPATH . "/admin/js/agent-exception-select_pp{$suffix}.js", array('jquery', 'jquery-form'), PPC_VERSION, true );
		$wp_scripts->in_footer []= 'pp_agent_select';
		wp_localize_script( 'pp_agent_select', 'PPAgentSelect', array( 'adminurl' => admin_url(''), 'ajaxhandler' => 'got_ajax_listbox' ) );
		
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
		wp_enqueue_script( 'ppce-item-edit', PPCE_URLPATH . "/admin/js/ppce-post-edit{$suffix}.js", array(), PPCE_VERSION );
	}
	
	function default_privacy_js() {
		global $post, $typenow;
		
		if ( 'post-new.php' != $GLOBALS['pagenow'] ) {
			$stati = get_post_stati( array( 'public' => true, 'private' => true ), 'names', 'or' );
			
			if ( in_array( $post->post_status, $stati ) )
				return;
		}
		
		if ( ! $set_visibility = pp_get_type_option( 'default_privacy', $typenow ) )
			return;
			
		if ( is_numeric($set_visibility) || ! get_post_status_object($set_visibility) )
			$set_visibility = 'private';
?>
<script type="text/javascript">
/* <![CDATA[ */
jQuery(document).ready( function($) {
	$('#visibility-radio-<?php echo $set_visibility;?>').click();
	
	$('#post-visibility-display').html(
		postL10n[$('#post-visibility-select input:radio:checked').val()]
	);
});
/* ]]> */
</script>
<?php
	}
	
	function suppress_upload_ui() {
		global $pp_current_user;
		if ( empty($pp_current_user->allcaps['upload_files']) && ! empty($pp_current_user->allcaps['edit_files']) ) : ?>
		<script type="text/javascript">
		/* <![CDATA[ */
		jQuery(document).ready( function($) {
			$(document).on( 'focus', 'div.supports-drag-drop', function() {
				$('div.media-router a:first').hide();
				$('div.media-router a:nth-child(2)').click();
			});
			$(document).on( 'mouseover', 'div.supports-drag-drop', function() {
				$('div.media-menu a:nth-child(2)').hide();
				$('div.media-menu a:nth-child(5)').hide();
			});
		});
		//]]>
		</script>
		<?php endif;
		
		if ( empty($pp_current_user->allcaps['upload_files']) && ! empty($pp_current_user->allcaps['edit_files']) ) : ?>
		<script type="text/javascript">
		/* <![CDATA[ */
		jQuery(document).ready( function($) {
			$(document).on( 'focus', 'div.supports-drag-drop', function() {
				$('div.media-router a:first').hide();
				$('div.media-router a:nth-child(2)').click();
			});
			$(document).on( 'mouseover', 'div.supports-drag-drop', function() {
				$('div.media-menu a:nth-child(2)').hide();
				$('div.media-menu a:nth-child(5)').hide();
			});
		});
		//]]>
		</script>
		<?php endif;
	}
	
	function suppress_add_category_ui() {
		global $pp_current_user;
			
		if ( pp_is_content_administrator() )
			return;
			
		$post_type = pp_find_post_type();
			
		// WP add category JS for Edit Post form does not tolerate absence of some categories from "All Categories" tab
		foreach( get_taxonomies( array( 'hierarchical' => true ), 'object' ) as $taxonomy => $tx ) {
			$disallow_add_term = false;
			$additional_tt_ids = array_merge( $pp_current_user->get_exception_terms( 'assign', 'additional', $post_type, $taxonomy, array( 'merge_universals' => true ) ), $pp_current_user->get_exception_terms( 'edit', 'additional', $post_type, $taxonomy, array( 'merge_universals' => true ) ) );
			
			if ( $pp_current_user->get_exception_terms( 'assign', 'include', $post_type, $taxonomy, array( 'merge_universals' => true ) ) || $pp_current_user->get_exception_terms( 'edit', 'include', $post_type, $taxonomy, array( 'merge_universals' => true ) ) ) {
				$disallow_add_term = true;
			} elseif ( $tt_ids = array_merge( $pp_current_user->get_exception_terms( 'assign', 'exclude', $post_type, $taxonomy, array( 'merge_universals' => true ) ), $tt_ids = $pp_current_user->get_exception_terms( 'edit', 'exclude', $post_type, $taxonomy, array( 'merge_universals' => true ) ) ) ) {
				$tt_ids = array_diff( $tt_ids, $additional_tt_ids );
				if ( count( $tt_ids ) )
					$disallow_add_term = true;
			} elseif ( $additional_tt_ids ) {
				$cap_check = ( isset($tx->cap->manage_terms ) ) ? $tx->cap->manage_terms : 'manage_categories';
			
				if ( ! current_user_can( $cap_check ) )
					$disallow_add_term = true;
			}	
			
			if ( $disallow_add_term  ) :?>
			<style type="text/css">
			#<?php echo $taxonomy;?>-adder{display:none;}
			</style>
			<?php endif;
		}
	}
	
	function ui_add_author_link() {
		static $done;
		if ( ! empty($done) ) return;
		$done = true;
		
		global $post;
		if ( empty($post) )
			return;
		
		$type_obj = get_post_type_object( $post->post_type );
		
		if ( current_user_can( $type_obj->cap->edit_others_posts ) ) :
			//$iframe_src = add_query_arg( array( 'noheader' => true, 'post_id' => $post->ID, 'TB_iframe' => true, 'width' => 400, 'height' => 300 ), admin_url('admin.php?page=pp-add-author') );
			$title = __( 'Author Search / Select', 'ppce' );
			
			$args = array(  
				'suppress_extra_prefix' => true, 
				'ajax_selection' => true,
				'display_stored_selections' => false,
				'label_headline' => '',
				'multi_select' => false,
				'suppress_selection_js' => true,
				'context' => $post->post_type,
			);

			$pp_agents_ui = pp_init_agents_ui();
			?>
			
			<div id="pp_author_search_ui_base" style="display:none"><div class="pp-agent-select pp-agents-selection"><?php $pp_agents_ui->agents_ui( 'user', array(), 'select-author', array(), $args );?></div></div>
			<?
		?>
<script type="text/javascript">
/* <![CDATA[ */
jQuery(document).ready( function($) {
	$("#post_author_override").after('<div id="pp_author_search" class="pp-select-author" style="display:none">' + $('#pp_author_search_ui_base').attr('id','pp_author_search_ui').html() + '</div>&nbsp;<a href="#" class="pp-add-author" style="margin-left:8px" title="<?php echo $title;?>"><?php _e('select other', 'ppce');?></a><a class="pp-close-add-author" href="#" style="display:none;"><?php _e( 'close', 'pp' );?></a>');
});
/* ]]> */
</script>
		<?php endif;
	}
}
