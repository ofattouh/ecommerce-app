<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_action( 'admin_print_footer_scripts', array( 'PPS_AdminPostUI', 'supplement_js_captions' ), 99 );

if ( defined( 'EDIT_FLOW_VERSION' ) && defined( 'PPCE_VERSION' ) ) {
	add_action( 'admin_enqueue_scripts', array( 'PPS_AdminPostUI', 'ef_compat' ), 50 );
	add_action( 'admin_notices', array( 'PPS_AdminPostUI', 'ef_restore_status_display' ), 50 );
}
	
class PPS_AdminPostUI {
	public static function ef_compat() {
		wp_dequeue_script( 'edit_flow-custom_status' );
		//wp_dequeue_style( 'edit_flow-custom_status' );
	}
		
	public static function ef_restore_status_display() {
	?>
		<style type="text/css">
		/* Restore post status dropdown (Edit Flow hides by default) **/
		label[for=post_status],
		#post-status-display,
		#publish {
			display: inline;
		}
		</style>
	<?php
	}

	public static function supplement_js_captions() {
		global $typenow, $wp_scripts;
		
		if ( ! isset( $wp_scripts->registered['post']->extra['data'] ) )
			return;

		// WP 3.3 beta 3 sets this argument as JS code var postL10n = {"ok":"OK","cancel":"Cancel", ...} (previous versions left it as a mergeable array)
		$str = explode( '{"', $wp_scripts->registered['post']->extra['data'] );

		if ( ! isset($str[1] ) )
			return;

		?>
<script type="text/javascript">
/* <![CDATA[ */
var postL10n;

if ( typeof(postL10n) != 'undefined' ) {
<?php foreach( array_merge( pp_get_post_stati( array( 'public' => true, 'post_type' => $typenow ), 'object' ), pp_get_post_stati( array( 'private' => true, 'post_type' => $typenow ), 'object' ) ) as $_status => $_status_obj ) {
	if ( ! in_array( $_status, array( 'auto-draft', 'publish' ) ) ) :?>
		postL10n['<?php echo $_status;?>'] = '<?php echo $_status_obj->labels->visibility;?>';
		postL10n['<?php echo $_status;?>Sticky'] = '<?php printf( __('%s, Sticky'), $_status_obj->label );?>';
	<?php endif;?>
<?php
} // end foreach
?>
}

/* ]]> */
</script>
<?php
	} // end function
	
	public static function set_status_labels() {
		global $wp_post_statuses;
		
		foreach ( array_keys($wp_post_statuses) as $status ) {
			if ( empty($wp_post_statuses[$status]->labels) )
				$wp_post_statuses[$status]->labels = (object) array();
		}

		$wp_post_statuses['publish']->labels->publish = esc_attr( _pp_('Publish') );
		$wp_post_statuses['future']->labels->publish = esc_attr( _pp_('Schedule') );
		
		if ( empty($wp_post_statuses['pending']->labels->publish) )
			$wp_post_statuses['pending']->labels->publish = esc_attr( _pp_('Submit for Review') );
		
		$wp_post_statuses['draft']->labels->save_as = esc_attr( _pp_('Save Draft') );
		
		if ( empty($wp_post_statuses['pending']->labels->caption) )
			$wp_post_statuses['pending']->labels->caption = _pp_( 'Pending Review' );
		
		$wp_post_statuses['private']->labels->caption = _pp_( 'Privately Published' );
		$wp_post_statuses['auto-draft']->labels->caption = _pp_( 'Draft' );
		
		foreach( array_keys($wp_post_statuses) as $status ) {
			$args =& $wp_post_statuses[$status];
		
			if ( ! isset( $args->labels ) )
				$args->labels = (object) array();
		
			if ( ! isset( $args->labels->name ) )
				$args->labels->name = ( ! empty( $args->label ) ) ? $args->label : $status;

			if ( ! isset( $args->labels->caption ) )
				$args->labels->caption = $args->labels->name;
				
			$label_name = $args->labels->name;
				
			if ( empty( $args->labels->count ) )
				$args->labels->count = ( ! empty( $args->label_count ) ) ? $args->label_count : array( $label_name, $label_name );

			if ( empty( $args->labels->publish ) )
				$args->labels->publish = esc_attr( sprintf( __( 'Set %s' ), $label_name ) );

			if ( empty( $args->labels->save_as ) )
				$args->labels->save_as = esc_attr( sprintf( __( 'Save as %s' ), $label_name ) );

			if ( empty( $args->labels->visibility ) ) {
				if ( 'publish' == $status )
					$args->labels->visibility =__( 'Public' );
				elseif ( $args->public )
					$args->labels->visibility = ( ! defined('WPLANG') || ( 'en_EN' == WPLANG ) ) ? esc_attr( sprintf( __( 'Public (%s)' ), $label_name ) ) : $label_name;  // not currently customizable by Edit Status UI
				elseif ( $args->private )
					$args->labels->visibility = $label_name;
			}
		}
		
		unset( $args );
	}
}
