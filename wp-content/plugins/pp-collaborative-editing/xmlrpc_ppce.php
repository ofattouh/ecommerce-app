<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

if ( version_compare( '7.0', phpversion(), '>=' ) ) {
	$raw_post_data = file_get_contents('php://input');
} else {
	global $HTTP_RAW_POST_DATA;
	$raw_post_data = ! empty($HTTP_RAW_POST_DATA) ? $HTTP_RAW_POST_DATA : '';
}

if ( $raw_post_data ) {
	global $pp_last_raw_post_data;
	$pp_last_raw_post_data = $raw_post_data; // global var is not retained reliably
}
	
//add_action( 'xmlrpc_call', '_pp_wlw_on_init', 0 );
add_action( 'pp_user_init', '_pp_wlw_on_init' );

add_filter( 'xmlrpc_methods', '_pp_adjust_methods' );
function _pp_adjust_methods( $methods ) {
	$methods['mt.setPostCategories'] = '_pp_mt_set_categories';
	return $methods;
}

add_filter( 'pre_post_category', '_pp_pre_post_category' );

function _pp_pre_post_category( $catids ) {
	return apply_filters( 'pp_pre_object_terms', $catids, 'category' );
}

// Override default method. Otherwise categories are unfilterable.
function _pp_mt_set_categories( $args ) {
	global $wp_xmlrpc_server;
	$wp_xmlrpc_server->escape($args);

	$post_ID    = (int) $args[0];
	$username  = $args[1];
	$password   = $args[2];
	$categories  = $args[3];

	if ( !$user = $wp_xmlrpc_server->login($username, $password) )
		return $wp_xmlrpc_server->error;

	if ( empty($categories) )
		$categories = array();

	$catids = array();
	foreach( $categories as $cat ) {
		$catids []= $cat['categoryId'];
	}

	$catids = apply_filters( 'pp_pre_object_terms', $catids, 'category' );

	do_action('xmlrpc_call', 'mt.setPostCategories');

	if ( ! get_post( $post_ID ) )
		return new IXR_Error( 404, __( 'Invalid post ID.' ) );

	if ( !current_user_can('edit_post', $post_ID) )
		return new IXR_Error(401, __('Sorry, you cannot edit this post.'));

	wp_set_post_categories($post_ID, $catids);
	
	return true;
}

function _pp_wlw_on_init() {
	global $wp_xmlrpc_server;

	if ( isset( $wp_xmlrpc_server->message ) ) {
		switch( $wp_xmlrpc_server->message->methodName ) {
			case 'metaWeblog.newPost': 
				if ( empty( $wp_xmlrpc_server->message->params[3]['categories'] ) ) {
					$wp_xmlrpc_server->message->params[3]['categories'] = (array) get_option( 'default_category' );
				}
				break;
		} // end switch
	}
} // end function

// clean up after xmlrpc clients that don't specify a post_type for mw_editPost
if ( defined( 'WLW_XMLRPC_HACK' ) )
	include( dirname(__FILE__).'/xmlrpc-wlw_ppce.php' );
