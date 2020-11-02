<?php
// Clean up after xmlrpc clients (Windows Live Writer) that don't specify a post_type for mw_editPost
// Couldn't find a clean way to filter args into default methods, and this is much better than forking entire method
//

if ( version_compare( '7.0', phpversion(), '>=' ) ) {
	$raw_post_data = file_get_contents('php://input');
} else {
	global $HTTP_RAW_POST_DATA;
	$raw_post_data = $HTTP_RAW_POST_DATA;
}

if ( isset($raw_post_data) && $pos = strpos($raw_post_data, '<string>') )
	if ( $pos_end = strpos($raw_post_data, '</string>', $pos) ) {
		$post_id = substr($raw_post_data, $pos + strlen('<string>'), $pos_end - ($pos + strlen('<string>')) ); 
		
		// workaround for Windows Live Writer passing in postID = 1 for new posts
		if ( strpos($raw_post_data, '<methodName>metaWeblog.newPost</methodName>') )
			$post_id = 0;
	}
	
if ( ! empty($post_id) ) {
	global $pp_xmlrpc_post_id;
	$pp_xmlrpc_post_id = $post_id;
	
	$post_type = '';
	if ( $pos = strpos($raw_post_data, '<name>post_type</name>') )
		if ( $pos = strpos($raw_post_data, '<string>', $pos) )
			if ( $pos_end = strpos($raw_post_data, '</string>', $pos) )
				$post_type = substr($raw_post_data, $pos + strlen('<string>'), $pos_end - ($pos + strlen('<string>')) ); 
	
	if ( empty($post_type) ) {
		if ( $pos_member_end = strpos($raw_post_data, '</member>') ) {
			if ( $pos_member_end = strpos($raw_post_data, '</member>', $pos_member_end + 1) ) {
				$pos_insert = $pos_member_end + strlen('</member>');
	
				global $wpdb;
				if ( $post_type = pp_get_var("SELECT post_type FROM $wpdb->posts WHERE ID = '$post_id'") ) {
					if ( 'post' != $post_type ) {
						global $pp_xmlrpc_post_type;
						$pp_xmlrpc_post_type = $post_type;
					}
				
					$insert_xml = 
"          <member>
            <name>post_type</name>
            <value>
              <string>$post_type</string>
            </value>
          </member>";
          
					$raw_post_data = substr($raw_post_data, 0, $pos_insert + 1) . $insert_xml . substr($raw_post_data, $pos_insert);
					
				} // endif parsed post type
			} // endif found existing member markup
		} // endif found 2nd existing member markup
	} // endif post_type not passed
}
