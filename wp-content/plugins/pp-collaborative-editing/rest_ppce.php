<?php

//pp_errlog( 'ppce_rest' );

foreach( pp_get_enabled_post_types() as $post_type ) {
	add_filter( "rest_{$post_type}_collection_params", 'ppce_rest_post_collection_params', 99, 2 );
}

add_filter( "rest_post_collection_params", 'ppce_rest_post_collection_params', 1, 2 );

function ppce_rest_post_collection_params( $params, $post_type_obj ) {
	global $current_user;
	
	//pp_errlog( 'ppce_rest_post_collection_params' );
	
	//if ( empty( $current_user->ID ) )
		//return $params;

	if ( isset( $_REQUEST['context'] ) && ( 'edit' == $_REQUEST['context'] ) ) {
		$params['status']['default'] = '';
	}
	
	return $params;
}