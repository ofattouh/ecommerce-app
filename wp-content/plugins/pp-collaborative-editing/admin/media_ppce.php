<?php
class PPCE_Media{
	public static function count_attachments_query( $query ) { // return false if no modification
		if ( $where_pos = strpos($query, 'WHERE ') ) {
			//if ( ! defined( 'PP_MEDIA_LIB_UNFILTERED' ) ) {  // already checked. Note: this constant actually just prevents Media Library filtering, falling back to WP Roles for attachment editability and leaving uneditable uploads viewable in Library
				static $att_sanity_count = 0;
				
				if ( $att_sanity_count > 5 )  // @todo: why does this apply filtering to 300+ queries on at least one MS installation?
					return false;
				
				$att_sanity_count++;
				
				global $pp_cap_interceptor_admin;

				if ( $pp_cap_interceptor_admin->attachment_filtering_disabled() )
					return false;
				else
					return apply_filters( 'pp_posts_request', $query, array( 'pp_context' => 'count_attachments' ) );
			//}
		}
		
		return false;
	}
}
