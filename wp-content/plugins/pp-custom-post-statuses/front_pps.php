<?php
class PPS_Front {
	var $custom_private_stati;
	
	function __construct() {
		if ( $this->prefix_stati = get_post_stati( array( '_builtin' => false, 'private' => true ) ) )
			add_filter('the_title', array(&$this, 'flt_title'), 10, 2);
	}

	function flt_title( $title, $post_id = 0 ) {
		if ( ! $post_id )
			return $title;

		if ( $post_status = get_post_field( 'post_status', $post_id ) ) {
			if ( in_array( $post_status, $this->prefix_stati ) ) {
				$status_obj = get_post_status_object( $post_status );
				
				if ( ! empty( $status_obj->labels->title_format ) )
					$title = sprintf($status_obj->labels->title_format, $title);
			}
		}

		return $title;
	}
}
