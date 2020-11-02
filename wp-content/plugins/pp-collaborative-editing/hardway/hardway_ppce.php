<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_filter( 'pp_get_pages_intercept', '_ppce_get_pages_intercept', 10, 3 );
add_filter( 'pp_get_pages_args', '_ppce_get_pages_args' );

function _ppce_get_pages_args( $args ) {
	if ( ! empty( $args['name'] ) && ( 'parent_id' == $args['name'] ) ) {
		global $post;

		if ( ! empty( $post ) && ( $args['post_type'] == $post->post_type ) ) {
			if ( $post->post_parent && ( 'auto-draft' != $post->post_status ) )
				$args['append_page'] = get_post( $post->post_parent );

			$args['exclude_tree'] = $post->ID;
		}
	}

	return $args;
}

function _ppce_get_pages_intercept( $intercept, $results, $args ) {
	// for the page parent dropdown, return no available selections for a published main page if the logged user isn't allowed to de-associate it from Main
	if ( ! empty( $args['name'] ) && ( 'parent_id' == $args['name'] ) ) {
		global $post;
		
		if ( ! $post->post_parent && ! ppce_user_can_associate_main( $args['post_type'] ) ) {
			$status_obj = get_post_status_object( $post->post_status );
			if ( $status_obj->public || $status_obj->private )
				return array();
		}
	}

	return $intercept;
}

