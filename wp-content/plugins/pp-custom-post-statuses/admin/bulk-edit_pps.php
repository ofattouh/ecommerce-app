<?php
class PP_BulkEdit {
	public static function bulk_edit_posts( $post_data = null ) {
		global $wpdb;

		if ( empty($post_data) )
			$post_data = &$_POST;

		if ( ! isset($post_data['post']) )
			return;

		$post_IDs = array_map( 'intval', (array) $post_data['post'] );
			
		$status = ( isset($post_data['_status_sub']) ) ? $post_data['_status_sub'] : '';

		if ( '-1' === $status )
			return;

		require_once( dirname(__FILE__).'/item-save_pps.php' );

		$updated = $locked = $skipped = array();
		foreach ( $post_IDs as $post_ID ) {
			if ( wp_check_post_lock( $post_ID ) ) {
				$locked[] = $post_ID;
				continue;
			}
			
			require_once( dirname(__FILE__).'/item-save_pps.php' );
			PPS_ItemSave::propagate_post_visibility( $post_ID, $status );
			
			$updated[] = $post_ID;
		}

		return array( 'updated' => $updated, 'skipped' => $skipped, 'locked' => $locked );
	}
} // end class
