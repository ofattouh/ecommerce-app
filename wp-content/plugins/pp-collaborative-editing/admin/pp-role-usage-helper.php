<?php
class PP_Role_Usage_Helper {
	public static function get_url_properties( &$url, &$referer, &$redirect ) {
		$url = apply_filters( 'pp_role_usage_base_url', 'admin.php' );

		if ( empty($_REQUEST) ) {
			$referer = '<input type="hidden" name="wp_http_referer" value="'. esc_attr(stripslashes($_SERVER['REQUEST_URI'])) . '" />';
		} elseif ( ! empty($_REQUEST['wp_http_referer']) ) {
			$redirect = remove_query_arg(array('wp_http_referer', 'updated', 'delete_count'), stripslashes($_REQUEST['wp_http_referer']));
			$referer = '<input type="hidden" name="wp_http_referer" value="' . esc_attr($redirect) . '" />';
		} else {
			$redirect = "$url?page=pp-role-usage";
			$referer = '';
		}
	}
	
	public static function other_notes( $title = '', $extra_items = array() ) {
		$extra_items = (array) $extra_items;
	
		if ( ! $title )
			$title = __('Notes', 'pp');
	
		echo '<h4 style="margin-top:0;margin-bottom:0.1em">' . $title . ':</h4><ul class="pp-notes">';
		
		if ( $extra_items ) {
			$hint = '<li>' . implode( '</li><li>', $extra_items ) . '</li>';
		} else
			$hint = '';

		$hint .= '<li>'
			. __( "The 'posts' capabilities in a WP role determine its function as a Pattern Role for supplemental assignment to Permission Groups. When you assign the 'Author' pattern role for Pages, edit_posts and edit_published_posts become edit_pages and edit_published_pages.", 'pp' ) 
			. '</li>'
			//. '<li>'
			//. __( "Each Pattern Role assignment is for a specific post type. Post types which are enabled in Settings > Features > Filtered Post Types will require type-specific capabilities for reading, editing and deletion. ", 'pp' )
			//. '</li>'
			. '<li>'
			. __( "Capabilities formally defined for other post types (i.e. 'edit_others_pages', 'edit_doohickies') apply to primary role assignment and supplemental direct assignment, but not pattern role assignment.", 'pp' )
			. '</li>'
			. '<li>'
			. __( "If one of the default roles is deleted from the WP database, it will remain available as a pattern role with default WP capabilities (but can be disabled here).", 'pp' )
			. '</li>'
			. '</ul>';

		echo $hint;
	}
}
