<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( defined( 'PPS_VERSION' ) )
	add_action( 'admin_print_footer_scripts', array( 'PPCE_EditListingFilters', 'modify_inline_edit_ui' ) );

add_action('admin_print_scripts', array('PPCE_EditListingFilters', 'act_maybe_hide_quickedit') );

add_action( 'admin_head', array('PPCE_EditListingFilters', 'register_column_filters') );

add_action( 'init', array('PPCE_EditListingFilters', 'tax_force_show_admin_col' ), 99 );

class PPCE_EditListingFilters {
	public static function tax_force_show_admin_col() {
		if ( pp_get_option( 'force_taxonomy_cols' ) ) {
			global $wp_taxonomies;
	
			foreach( pp_get_enabled_taxonomies( array( 'object_type' => pp_find_post_type() ), 'names' ) as $taxonomy )
				$wp_taxonomies[$taxonomy]->show_admin_column = true;
		}
	}

	public static function register_column_filters() {
		global $typenow;
		add_action("manage_{$typenow}_posts_custom_column", array('PPCE_EditListingFilters', 'flt_manage_posts_custom_column'), 10, 2);
	}
	
	public static function flt_manage_posts_custom_column( $column_name, $id ) {
		if ( 'status' == $column_name ) {
			global $post;
			
			if ( ! in_array( $post->post_status, array( 'draft', 'public', 'private', 'pending' ) ) ) {
				global $edit_flow;
				
				if ( $edit_flow && method_exists($edit_flow->custom_status, 'get_custom_status_by' ) ) {
					if ( $edit_flow->custom_status->get_custom_status_by( 'slug', $post->post_status ) )
						return;
				}
				
				if ( $status_obj = get_post_status_object( $post->post_status ) ) {
					if ( ! empty($status_obj->private) || ( ! empty($status_obj->moderation) && ( 'future' != $post->post_status ) ) )
						echo $status_obj->label;
				}
			}
		}
	}
	
	// Quick Edit provides access to some properties which some content-specific editors cannot modify (Page parent, post/page visibility and status)
	// For now, avoid this complication and filtering overhead by turning off Quick Edit for users lacking site-wide edit_others capability
	public static function act_maybe_hide_quickedit() {
		if ( ppce_is_limited_editor() && ! current_user_can('pp_force_quick_edit') ) {
			// @todo: better quick/bulk edit support for limited editors
			?>
			<style type="text/css">
			.editinline,div.tablenav div.actions select option[value="edit"]{display:none;}
			</style>
			<?php
		}
	}

	public static function modify_inline_edit_ui() {
		$screen = get_current_screen();
		$post_type_object = get_post_type_object( $screen->post_type );
		
		if ( apply_filters( 'pp_disable_object_cond_metaboxes', false, 'post', 0 ) )
			return;
?>
<script type="text/javascript">
/* <![CDATA[ */
jQuery(document).ready( function($) {
	<?php
	$moderation_stati = array();
	foreach( pp_get_post_stati( array( '_builtin' => false, 'moderation' => true, 'post_type' => $screen->post_type ), 'object' ) as $status => $status_obj ) {
		$set_status_cap = "set_{$status}_posts";
		$check_cap = ( ! empty( $post_type_object->cap->$set_status_cap ) ) ? $post_type_object->cap->$set_status_cap : $post_type_object->cap->publish_posts;
		
		if ( pp_is_content_administrator() || current_user_can( $check_cap ) ) {
			$moderation_stati[$status] = $status_obj;
		}
	}
	
	foreach( $moderation_stati as $status => $status_obj ) :?>		
		if ( ! $('select[name="_status"] option[value="<?php echo $status;?>"]').length ) {
			$('<option value="<?php echo $status;?>"><?php echo $status_obj->label;?></option>').insertBefore('select[name="_status"] option[value="pending"]');
		}
	<?php endforeach;?>
});
//]]>
</script>
<?php
	} // end function add_inline_edit_ui
}

