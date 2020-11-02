<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $pps_edit_listing_filters;
$pps_edit_listing_filters = new PPS_EditListingFilters();

class PPS_EditListingFilters {
	var $post_ids = array();
	
	function __construct() {
		add_filter( 'views_' . pp_find_post_type(), array( &$this, 'flt_views_stati' ) );
		add_action( 'admin_print_footer_scripts', array( &$this, 'act_modify_inline_edit_ui' ) );
		add_filter( 'pp_hide_quickedit', array( &$this, 'flt_hide_quickedit' ), 10, 2 );

		add_action( 'the_post', array( &$this, 'log_displayed_posts' ) );
	}
	
	function log_displayed_posts( $_post ) {
		$this->post_ids[]= $_post->ID;
	}
	
	function flt_hide_quickedit( $hide, $type_obj ) {
		if ( ! $hide )
			$hide = ! current_user_can( 'pp_moderate_any' );

		return $hide;
	}
	
	function flt_views_stati( $views ) {
		$post_type = pp_find_post_type();
		$type_stati = pp_get_post_stati( array('show_in_admin_all_list' => true, 'post_type' => $post_type ) );

		$views = array_intersect_key( $views, array_flip( $type_stati ) );

		// also remove filtered stati from "All" count 
		$num_posts = array_intersect_key( wp_count_posts( $post_type, 'readable' ), $type_stati );

		$total_posts = array_sum( (array) $num_posts );

		$class = ! isset( $views['mine'] ) && empty( $_REQUEST['post_status'] ) && empty( $_REQUEST['show_sticky'] ) ? ' class="current"' : '';
		$allposts = ( strpos( $views['all'], 'all_posts=1' ) ) ? $allposts = '&all_posts=1' : '';
		$views['all'] = "<a href='edit.php?post_type=$post_type{$allposts}'$class>" . sprintf( _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $total_posts, 'posts' ), number_format_i18n( $total_posts ) ) . '</a>';

		return $views;
	}
	
	// status display in Edit Posts table rows
	function flt_display_post_states( $post_states ) {
		global $post;
	
		if ( in_array( $post->post_status, array( 'publish', 'private', 'pending', 'draft' ) ) )
			return $post_states;
	
		if ( 'future' == $post->post_status ) {		// also display eventual visibility of scheduled post (if non-public)
			if ( $scheduled_status = get_post_meta( $post->ID, '_scheduled_status', true ) ) {
				if ( 'publish' != $scheduled_status ) {
					if ( $_scheduled_status_obj = get_post_status_object( $scheduled_status ) )
						$post_states[] = $_scheduled_status_obj->label;
				}
			}
		} elseif ( empty( $_GET['post_status'] ) || ( $_GET['post_status'] != $post->post_status ) ) {	// if filtering for this status, don't display caption in result rows
			if ( $status_obj = get_post_status_object( $post->post_status ) ) {
				if ( $status_obj->private || ! empty( $status_obj->moderation ) )
					$post_states[] = $status_obj->label;
			}
		}
		
		return $post_states;
	}
	
	// @todo: move to .js
	// add "keep" checkboxes for custom private stati; set checked based on current or scheduled post status
	// add conditions UI to inline edit
	public static function act_modify_inline_edit_ui() {
		$screen = get_current_screen();
		$post_type_object = get_post_type_object( $screen->post_type );
?>
<script type="text/javascript">
/* <![CDATA[ */
jQuery(document).ready( function($) {
	<?php
	$is_content_administrator= pp_is_content_administrator();
	$moderation_stati = array();
	global $typenow;
	
	$_stati = apply_filters( 'pp_order_types', pp_get_post_stati( array( '_builtin' => false, 'moderation' => true, 'post_type' => $typenow ), 'object' ), array( 'order_property' => 'order' ) ); 
	foreach( $_stati as $status => $status_obj ) {
		$set_status_cap = "set_{$status}_posts";
		$check_cap = ( ! empty( $post_type_object->cap->$set_status_cap ) ) ? $post_type_object->cap->$set_status_cap : $post_type_object->cap->publish_posts;
		
		if ( $is_content_administrator || current_user_can( 'pp_moderate_any') || current_user_can( $check_cap ) ) {
			$moderation_stati[$status] = $status_obj;
		}
	}

	$pvt_stati = array();
	$_stati = apply_filters( 'pp_order_types', pp_get_post_stati( array( 'private' => true, 'post_type' => $typenow ), 'object' ), array( 'order_property' => 'label' ) );
	foreach( $_stati as $status => $status_obj ) {
		$set_status_cap = "set_{$status}_posts";
		$check_cap = ( ! empty( $post_type_object->cap->$set_status_cap ) ) ? $post_type_object->cap->$set_status_cap : $post_type_object->cap->publish_posts;

		if ( $is_content_administrator || current_user_can( $check_cap ) ) {
			$pvt_stati[$status] = $status_obj;
		}
	}
	?>
	
	<?php foreach( $moderation_stati as $status => $status_obj ) :?>		
		if ( ! $('select[name="_status"] option[value="<?php echo $status;?>"]').length ) {
			$('<option value="<?php echo $status;?>"><?php echo $status_obj->label;?></option>').insertBefore('select[name="_status"] option[value="pending"]');
		}
	<?php endforeach;?>

	if ( $('select[name="_status"] option[value="-1"]').length ) {
	<?php foreach( $pvt_stati as $status => $status_obj ) :?>		
		if ( ! $('select[name="_status"] option[value="<?php echo $status;?>"]').length ) {
			$('<option value="<?php echo $status;?>"><?php echo $status_obj->label;?></option>').insertAfter('select[name="_status"] option[value="private"]');
		}
	<?php endforeach;?>
	}

	<?php 
	// also support forcing of default privacy for non-hierarchical types
	$is_hierarchical = is_post_type_hierarchical( $typenow );
	
	if ( $is_hierarchical || ( pp_get_type_option( 'default_privacy', $typenow ) && pp_get_type_option( 'force_default_privacy', $typenow ) ) ) {
		global $posts;
		
		if ( ! empty($posts) ) {
			$pp_attributes = pps_init_attributes();
			
			foreach( array_keys($posts) as $key ) : 
				if ( ! in_array( $posts[$key]->ID, $this->post_ids ) ) continue;
				
				$force_vis = $pp_attributes->get_item_condition( 'post', 'force_visibility', array( 'id' => $posts[$key]->ID, 'assign_for' => 'item', 'default_only' => ! $is_hierarchical, 'post_type' => $typenow ) );

				if ( $is_hierarchical ) :?>
					<?php
					$child_status = $pp_attributes->get_item_condition( 'post', 'force_visibility', array( 'id' => $posts[$key]->ID, 'assign_for' => 'children' ) );
					?>
					$('#inline_<?php echo($posts[$key]->ID);?> div._status').after('<div class="_status_sub"><?php echo $child_status;?></div>');
				<?php endif; ?>
				$('#inline_<?php echo($posts[$key]->ID);?> div._status').after('<div class="_force_vis"><?php echo $force_vis;?></div>');
			<?php 
			endforeach;
		}
		?>

		$("tr.bulk-edit-page label.inline-edit-status").parent().after('<div class="inline-edit-group"><label class="inline-edit-status-sub alignleft"><span class="title"><?php printf(__('Subpage Vis.', 'pps'), $post_type_object->label);?></span><span class="pp_child_select-open" style="margin-left: 0.1em">[</span><select name="_status_sub" title="<?php printf(__('Force visibility of sub-%s', 'pps'), $post_type_object->label);?>"></select><span class="pp_child_select-close">]</span></label><span><label for="pp_propagate_visibility" class="alignleft" style="display:none; margin-top:0.5em"><input type="checkbox" name="pp_propagate_visibility" id="pp_propagate_visibility" checked="checked" disabled="disabled" /> <?php printf(__('existing sub-%s', 'pps'), $post_type_object->label );?></label></span></div>');
		
		var elems = '';
		if ( ! $('select[name="_status_sub"] option[value="<?php echo $status;?>"]').length ) {
		elems = elems + '<option value="publish"><?php echo _pp_('Public');?></option>';
		<?php foreach( $pvt_stati as $status => $status_obj ) :?>		
			elems = elems + '<option value="<?php echo $status;?>"><?php echo $status_obj->label;?></option>';
		<?php endforeach;?>
		}
		$("select[name='_status_sub']").html( '<option value=""><?php _e('(manual)', 'pps');?></option>' + elems );
		$('.inline-edit-status-sub select').prepend('<option value="-1"><?php _e( '&mdash; No Change &mdash;' );?></option>');
		$('.inline-edit-status-sub select option[value="-1"]').prop('selected',true);

		$("label.inline-edit-status-sub span.title").append('<span class="pp_disclaimer" title="<?php _e('Status may also be altered by category or term', 'pps');?>"> * </span>');
		$("select[name='_status_sub']").siblings('span').attr('title', $("select[name='_status_sub']").attr('title') );
		
		//$('select[name="_status_sub"]').live('click', function(e){
		$(document).on('click', 'select[name="_status_sub"]', function(e){
			$('input[name="pp_propagate_visibility"]').parent().toggle( $(this).val() != -1 && $(this).val() != '' );
		});
	<?php } // endif hier ?>
	
	//$('.inline-edit-row input[name="keep_custom_privacy"]').live('click', function(){
	$(document).on('click', '.inline-edit-row input[name="keep_custom_privacy"]', function(){
		$("input[name='keep_private']").prop("checked", false);
		$('input.inline-edit-password-input').val('').prop('disabled', true);
	});
	
	//$('.inline-edit-row input[name="keep_private"]').live('click', function(){
	$(document).on('click', '.inline-edit-row input[name="keep_private"]', function(){
		$("input[name='keep_custom_privacy']").prop("checked", false);
	});

	//$('.inline-edit-row input.ptitle').live('focusin', function(){
	$(document).on('focusin', '.inline-edit-row input.ptitle', function(){
<?php
		global $pp;
		$scheduled_stati = array();
	
		if ( ! empty($pp->listed_ids[$screen->post_type]) ) {
			global $wpdb;
			$id_csv = implode( "','", array_keys( $pp->listed_ids[$screen->post_type] ) );
			
			if ( $results = $wpdb->get_results( "SELECT meta_value, post_id FROM $wpdb->postmeta WHERE meta_key = '_scheduled_status' AND post_id IN ('" . $id_csv . "')" ) ) {
				foreach( $results as $row )
					$scheduled_stati[$row->meta_value][$row->post_id] = true;
			}
		}
?>	
		var rowData, status;
		id = inlineEditPost.getId(this);
		rowData = $('#inline_'+id);
		status = $('._status', rowData).text();
		keep_status = '';
		var pvt_stati = ["<?php echo implode( '", "', array_keys($pvt_stati) ); ?>"];

		<?php // append elements for pvt stati ?>
		if ( ! $('#edit-'+id+' input[name="keep_custom_privacy"]').length ) {
			var elems = '';
			<?php foreach( $pvt_stati as $status => $status_obj ) : 
				if ( 'private' == $status ) continue;
			?>
				elems = elems + '<label class="alignleft">&nbsp;&nbsp;<input type="radio" name="keep_custom_privacy" value="<?php echo $status;?>"><span class="checkbox-title"> <?php echo $status_obj->label;?>&nbsp;&nbsp;</span></label>';
			<?php endforeach; ?>

			if ( elems ) {
				<?php if ( ! $post_type_object->hierarchical ) : ?>
				$('#edit-'+id+' label.inline-edit-private').before('<br /><br />');
				<?php endif; ?>
				
				$('#edit-'+id+' label.inline-edit-private').after(elems);
			}
		}

		if ( -1 !== jQuery.inArray(status, pvt_stati) ) {
			keep_status = status;
		} 
		<?php if( $scheduled_stati ) : ?>
		else if ( 'future' == status ) {
			var id_val = parseInt(id);
		
			<?php foreach( $scheduled_stati as $status => $pvt_ids ) : ?>
				var pvt_ids = new Array(<?php echo( implode(', ', array_keys($pvt_ids)) ); ?>);
				
				if ( -1 !== jQuery.inArray(id_val, pvt_ids) )
					keep_status = '<?php echo $status; ?>';
			<?php endforeach; ?>
		}
		<?php endif; ?>
		
		if ( keep_status ) {
			if ( 'private' != keep_status ) {
				$("input[name='keep_private']").prop("checked", false);
				$("input[name='keep_custom_privacy'][value='"+keep_status+"']").prop("checked", true);
			}
		}
		
		var current_val = $('._status_sub', rowData).text();
		$('select[name="_status_sub"] option[value="'+current_val+'"]').prop( 'selected', true );
		
		var current_val = $('._force_vis', rowData).text();
		var inputs = $("input[name='keep_private'],input[name='keep_custom_privacy']");
		$(inputs).prop('disabled', current_val != '');
		if ( current_val != '' ) {
			$(inputs).parent().attr('title', '<?php _e('Visibility locked', 'pps');?>');
		} else {
			$(inputs).parent().attr('title','');
		}
	});
});
//]]>
</script>
<?php
	} // end function modify_inline_edit_ui
}
