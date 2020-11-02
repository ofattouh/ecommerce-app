<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

//add_action( 'wp_dashboard_setup', '_pp_add_dashboard_widgets' );

add_action ( 'right_now_content_table_end', '_ppce_right_now_pending' );
add_action ( 'dashboard_glance_items', '_ppce_right_now_pending' );

function _ppce_right_now_pending() {
	$post_types = array_diff_key( get_post_types( array( 'public' => true ), 'object' ), array( 'attachment' => true ) );
	
	$moderation_stati = array( 'pending' => get_post_status_object('pending') );
	$post_stati = get_post_stati( array(), 'object' );
	foreach( $post_stati as $status_name => $status_obj ) {
		if ( ! empty( $status_obj->moderation ) )
			$moderation_stati[$status_name] = $status_obj;
	}
	
	$tag_open = pp_wp_ver( '3.8' ) ? '<div style="padding-bottom:4px">' : '<tr>';
	$tag_close = pp_wp_ver( '3.8' ) ? '</div>' : '</tr>';
	
	foreach ( $post_types as $post_type => $post_type_obj ) {
		if ( $num_posts = wp_count_posts( $post_type ) ) {
			foreach( $moderation_stati as $status_name => $status_obj ) {
				if ( ! empty($num_posts->$status_name) ) {
					echo "\n\t".$tag_open;
			
					$num = number_format_i18n( $num_posts->$status_name );
					
					//$text = _n( 'Pending Page', 'Pending Pages', intval($num_pages->pending), 'pp' );

					$label = ( 'pending' == $status_name ) ? __( 'Pending', 'pp' ) : $status_obj->label;
					
					if ( intval($num_posts->$status_name) <= 1 )
						$text = sprintf( __('%1$s %2$s', 'pp'), $label, $post_type_obj->labels->singular_name);
					else
						$text = sprintf( __('%1$s %2$s', 'pp'), $label, $post_type_obj->labels->name);
						
					$type_clause = ( 'post' == $post_type ) ? '' : "&post_type=$post_type";
						
					$url = "edit.php?post_status=$status_name{$type_clause}";
					$num = "<a href='$url'><span class='pending-count'>$num</span></a> ";
					$text = "<a class='waiting' href='$url'>$text</a>";
			
					$type_class = ( $post_type_obj->hierarchical ) ? 'b-pages' : 'b-posts';
					
					echo '<td class="first b ' . $type_class . ' b-waiting">' . $num . '</td>';
					echo '<td class="t posts">' . $text . '</td>';
					echo '<td class="b"></td>';
					echo '<td class="last t"></td>';
					echo "{$tag_close}\n\t";
				}
			}
		}
	}
	
	echo '<div style="height:5px"></div>';
}

/*
function _pp_add_dashboard_widgets() {
	wp_add_dashboard_widget( 'pp_dashboard_stuff', __('Press Permit', 'pp'), '_pp_dashboard_stuff' );	
}

function _pp_dashboard_stuff() {

}
*/
