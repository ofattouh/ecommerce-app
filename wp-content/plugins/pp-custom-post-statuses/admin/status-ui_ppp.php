<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PPP_StatusEditUI {
	public static function status_edit_ui( $status, $args = array() ) {
		$defaults = array( 'new' => false, 'attrib_type' => '' );
		extract( array_merge( $defaults, $args ), EXTR_SKIP );
	
		$status_obj = get_post_status_object( $status );
		
		$status_label = ( ! empty($status_obj->label) ) ? $status_obj->label : '';
		$status_types = ( ! empty($status_obj) && ! empty($status_obj->post_type) ) ? $status_obj->post_type : array();
		
		$status_order = ( ! empty($status_obj) && isset($status_obj->order) ) ? $status_obj->order : '';
		
		$name_disabled = ( $new ) ? '' : 'disabled="disabled"';
		$label_disabled = ( 'future' == $status ) ? 'disabled="disabled"' : '';
		
	?>
	<table class="form-table">
	<tr class="form-field form-required">
		<th scope="row"><label for="status_name"><?php _e('Slug', 'pps'); ?></label></th>
		<td><input type="text" name="status_name" id="status_name" value="<?php echo $status;?>" placeholder="<?php _e('(Latin alphanumeric, maximum 20 characters)', 'pps');?>" class="regular-text" <?php echo $name_disabled;?> /> </td>
	</tr>

	<tr class="form-field">
		<th><label for="status_label"><?php _e('Label', 'pps') ?></label></th>
		<td><input type="text" name="status_label" id="status_label" value="<?php echo esc_attr(stripslashes($status_label));?>" class="regular-text" <?php echo $label_disabled;?> /></td>
	</tr>

	<?php if( ( 'moderation' == $attrib_type ) && ( 'future' != $status ) ) : 
		$save_as_label = ( ! empty($status_obj) && ! empty($status_obj->labels->save_as) ) ? $status_obj->labels->save_as : '';
	?>
		<tr class="form-field">
			<th><label for="status_save_as_label"><?php _e('Save As Label (optional)', 'pps') ?></label></th>
			<td><input type="text" name="status_save_as_label" id="status_save_as_label" value="<?php echo esc_attr(stripslashes($save_as_label));?>" class="regular-text" /></td>
		</tr>
	<?php
		$button_label = ( ! empty($status_obj) && ! empty($status_obj->labels->publish) ) ? $status_obj->labels->publish : '';
	?>
		<tr class="form-field">
			<th><label for="status_publish_label"><?php _e('Submit Button Label (optional)', 'pps') ?></label></th>
			<td><input type="text" name="status_publish_label" id="status_publish_label" value="<?php echo esc_attr(stripslashes($button_label));?>" class="regular-text" /></td>
		</tr>

		<tr class="form-field">
			<th><label for="status_order"><?php _e('Order', 'pps') ?></label></th>
			<td><input type="text" name="status_order" id="status_order" value="<?php echo esc_attr(stripslashes($status_order));?>" class="regular-text" /></td>
		</tr>
	<?php endif;?>
	
	<tr>
		<th><label for="types_label"><?php _e('Post Types', 'pps') ?></label></th>
		<td style="align:left">
		
		<?php
		$types = get_post_types( array( 'public' => true ), 'object' );
		
		$omit_types = apply_filters( 'pp_unfiltered_post_types', array() );
		$omit_types = array_merge( $omit_types, array( 'nav_menu', 'attachment', 'revision' ) );
		$types = array_diff_key( $types, array_fill_keys( (array) $omit_types, true ) );

		//$hidden_types = apply_filters( 'pp_hidden_post_types', array() );
		$option_name = 'pp_status_post_types';

		$enabled = ! empty( $status_types ) ? (array) $status_types : array();
		?>
		<div>
		<?php
		if ( $locked_status = in_array( $status, array( 'pending', 'future', 'draft' ) ) ) :?>
			<input type="hidden" name="<?php echo('pp_status_all_types');?>" value="1" />
		<?php endif;
		
		$all_enabled = empty($enabled) || $locked_status;
		$disabled = ( $locked_status ) ? ' disabled="disabled"' : '';
		?>
		<div class="agp-vspaced_input">
		<label for="<?php echo('pp_status_all_types');?>">
		<input name="<?php echo('pp_status_all_types');?>" type="checkbox" id="<?php echo('pp_status_all_types');?>" value="1" <?php checked('1', $all_enabled ); echo $disabled;?> />
		<?php _e( '(All Types)', 'pps' );?>
		</label>
		</div>
		<?php
		
		$hint = '';
		
		if ( ! $locked_status ) {
			$disabled = ( $all_enabled ) ? 'disabled="disabled"' : '';
			
			if ( defined( 'EDIT_FLOW_VERSION' ) && defined( 'PPCE_VERSION' ) && ! empty( $status_obj->edit_flow ) ) {
				global $edit_flow;

				if ( ! empty( $edit_flow->modules->custom_status->options->post_types ) )
					$types = array_intersect_key( $types, array_intersect( $edit_flow->modules->custom_status->options->post_types, array( 'on' ) ) );
					
				$hint = sprintf( __( 'Note: Post Types must also be enabled in %1$sEdit Flow settings%2$s', 'pps' ), "<a href='" . admin_url( 'admin.php?action=change-options&page=ef-custom-status-settings' ) . "'>", '</a>' );
			}
			
			foreach ( $types as $key => $obj ) {
				$id = $option_name . '-' . $key;
				$name = $option_name . "[$key]";
				?>
				<div class="agp-vspaced_input">
				<label for="<?php echo($id);?>" title="<?php echo($key);?>">
				<input name="<?php echo($name);?>" type="hidden" value="0" />
				<input name="<?php echo($name);?>" type="checkbox" class="pp_status_post_types" <?php echo $disabled;?> id="<?php echo($id);?>" value="1" <?php checked('1', in_array($key, $enabled) );?> />
				
				<?php 
				if ( isset( $obj->labels_pp ) )
					echo $obj->labels_pp->name;
				elseif ( isset( $obj->labels->name ) )
					echo $obj->labels->name;
				else
					echo $key;

				echo ('</label></div>');
			} // end foreach src_otype
		}
		?>
		
		</div>
		
		<?php if ( $hint ) :?>
		<br /><p>
		<?php echo $hint; ?>
		</p>
		<?php endif?>
		</td>
	</tr>
	</table>
	
	<script type="text/javascript">
	/* <![CDATA[ */
	function ucwords (str) {
	  // http://kevin.vanzonneveld.net
	  // +   original by: Jonas Raoni Soares Silva (http://www.jsfromhell.com)
	  // +   improved by: Waldo Malqui Silva
	  // +   bugfixed by: Onno Marsman
	  // +   improved by: Robin
	  // +      input by: James (http://www.james-bell.co.uk/)
	  // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
	  // *     example 1: ucwords('kevin van  zonneveld');
	  // *     returns 1: 'Kevin Van  Zonneveld'
	  return (str + '').replace(/^([a-z\u00E0-\u00FC])|\s+([a-z\u00E0-\u00FC])/g, function ($1) {
		return $1.toUpperCase();
	  });
	}
	
	jQuery(document).ready( function($) {
		$('#status_name').focusout( function() {
			if ( ! $('#status_label').val() ) {
				$('#status_label').val( ucwords( $(this).val().replace(/[_-]/g, ' ') ) );
			}
			
			$(this).val( $(this).val().replace(/[ \t]+/g, '_').toLowerCase().replace(/[^a-z0-9_\-]/gi, '').substring(0,20) );
		});
	});
	/* ]]> */
	</script>
	
	<?php
	} // end function

} // end class
