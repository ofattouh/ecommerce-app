<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_filter( 'the_comments', array( 'CommentsInterceptorAdmin_PPCE', 'log_comment_post_ids' ) );

add_filter( 'wp_count_comments', array( 'CommentsInterceptorAdmin_PPCE', 'wp_count_comments_override'), 99, 2 );
add_filter( 'map_meta_cap', array( 'CommentsInterceptorAdmin_PPCE', 'flt_adjust_reqd_caps'), 1, 4 );

class CommentsInterceptorAdmin_PPCE {
	public static function log_comment_post_ids( $comments ) {
		// buffer the listed IDs for more efficient user_has_cap calls
		if ( empty($pp->listed_ids) ) {
			global $pp;
			$pp->listed_ids = array();

			foreach ( $comments as $row ) {
				if ( ! empty($row->comment_post_ID) ) {
					$post_type = get_post_field( 'post_type', $row->comment_post_ID );
					$pp->listed_ids[$post_type][$row->comment_post_ID] = true;
				}
			}
		}
		
		return $comments;
	}
	
	public static function flt_adjust_reqd_caps( $reqd_caps, $orig_cap, $user_id, $args ) {
		global $pagenow;
		
		// users lacking edit_posts cap may have moderate_comments capability via a supplemental role
		if ( ( 'edit-comments.php' == $pagenow ) && ( $key = array_search( 'edit_posts', $reqd_caps ) ) ) {
			global $current_user;
			if ( did_action( 'load-edit-comments.php' ) && ( $user_id == $current_user->ID ) && empty( $current_user->allcaps['edit_posts'] ) ) {
				$reqd_caps[$key] = 'moderate_comments';
			}
		}
		
		return $reqd_caps;
	}
	
	public static function wp_count_comments_clauses( $clauses, $qry_obj ) {
		if ( ! strpos( $clauses['where'], 'GROUP BY' ) ) {
			$clauses['fields'] = 'comment_approved, COUNT( * ) AS num_comments';
			$clauses['where'] .= ' GROUP BY comment_approved';
		}
		return $clauses;
	}
	
	// force wp_count_comments() through WP_Comment_Query filtering
	public static function wp_count_comments_override( $comments, $post_id = 0 ) {
		add_filter( 'comments_clauses', array( 'CommentsInterceptorAdmin_PPCE', 'wp_count_comments_clauses' ), 99, 2 );
		$count = get_comments( array( 'post_id' => $post_id ) );
		remove_filter( 'comments_clauses', array( 'CommentsInterceptorAdmin_PPCE', 'wp_count_comments_clauses' ), 99, 2 );
		
		// remainder of this function ported from WP 3.2 function wp_count_comments()
	
		$total = 0;
		$approved = array('0' => 'moderated', '1' => 'approved', 'spam' => 'spam', 'trash' => 'trash', 'post-trashed' => 'post-trashed');
		foreach ( (array) $count as $row ) {
			$row = (array) $row;  // PP modification
		
			// Don't count post-trashed toward totals
			if ( ! empty($row['num_comments']) ) {
				if ( 'post-trashed' != $row['comment_approved'] && 'trash' != $row['comment_approved'] )
					$total += $row['num_comments'];
				
				if ( isset( $approved[$row['comment_approved']] ) )
					$stats[$approved[$row['comment_approved']]] = $row['num_comments'];
			}
		}

		$stats['total_comments'] = $total;
		foreach ( $approved as $key ) {
			if ( empty($stats[$key]) )
				$stats[$key] = 0;
		}

		$stats = (object) $stats;

		return $stats;
	}
} // end class
