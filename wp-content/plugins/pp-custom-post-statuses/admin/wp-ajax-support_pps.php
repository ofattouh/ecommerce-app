<?php
class PPS_WP_Ajax {
	// ported out due to PHP Warning and "headers already sent" when custom statuses are present
	public static function wp_ajax_find_posts() {
		global $wpdb;
		
		check_ajax_referer( 'find-posts' );

		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $post_types['attachment'] );

		$s = wp_unslash( $_POST['ps'] );
		$searchand = $search = '';
		$args = array(
			'post_type' => array_keys( $post_types ),
			'post_status' => 'any',
			'posts_per_page' => 50,
		);
		if ( '' !== $s )
			$args['s'] = $s;

		$posts = get_posts( $args );

		if ( ! $posts )
			wp_die( __('No items found.') );

		$html = '<table class="widefat"><thead><tr><th class="found-radio"><br /></th><th>'.__('Title').'</th><th class="no-break">'.__('Type').'</th><th class="no-break">'.__('Date').'</th><th class="no-break">'.__('Status').'</th></tr></thead><tbody>';
		$alt = '';
		foreach ( $posts as $post ) {
			$title = trim( $post->post_title ) ? $post->post_title : __( '(no title)' );
			$alt = ( 'alternate' == $alt ) ? '' : 'alternate';

			switch ( $post->post_status ) {
				case 'publish' :
				case 'private' :
					$stat = __('Published');
					break;
				case 'future' :
					$stat = __('Scheduled');
					break;
				case 'pending' :
					$stat = __('Pending Review');
					break;
				case 'draft' :
					$stat = __('Draft');
					break;
				default :  // kevinB modification
					if ( $status_obj = get_post_status_object( $post->post_status  ) )
						$stat = $status_obj->label;
					else
						$stat = $post->post_status;
			}

			if ( '0000-00-00 00:00:00' == $post->post_date ) {
				$time = '';
			} else {
				/* translators: date format in table columns, see http://php.net/date */
				$time = mysql2date(__('Y/m/d'), $post->post_date);
			}

			$html .= '<tr class="' . trim( 'found-posts ' . $alt ) . '"><td class="found-radio"><input type="radio" id="found-'.$post->ID.'" name="found_post_id" value="' . esc_attr($post->ID) . '"></td>';
			$html .= '<td><label for="found-'.$post->ID.'">' . esc_html( $title ) . '</label></td><td class="no-break">' . esc_html( $post_types[$post->post_type]->labels->singular_name ) . '</td><td class="no-break">'.esc_html( $time ) . '</td><td class="no-break">' . esc_html( $stat ). ' </td></tr>' . "\n\n";
		}

		$html .= '</tbody></table>';

		if ( pp_wp_ver( '3.9' ) ) {
			//pp_errlog( 'find posts ok' );
			wp_send_json_success( $html );
		} else {
			$x = new WP_Ajax_Response();
			$x->add( array(
				'data' => $html
			));
			$x->send();
		}
	}
}
