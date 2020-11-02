<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_filter( 'pre_post_status', '_pps_flt_post_status', 20 );
add_action( 'save_post', '_pps_act_save_post', 10, 2 );
add_action( 'delete_post', '_pps_act_delete_post', 10, 3 );

function _pps_flt_post_status( $status ) {
	global $pagenow;
	if ( in_array( $status, array( 'inherit', 'trash' ) ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ( 'async-upload.php' == $pagenow ) )
		return $status;

	require_once( dirname(__FILE__) . '/post-save_pps.php' );
	$status = PPS_PostSave::flt_post_status($status);
	return PPS_PostSave::flt_force_visibility($status);
}

function _pps_act_save_post( $post_id, $post ) {
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
	if ( 'revision' == $post->post_type ) return;

	require_once( dirname(__FILE__) . '/post-save_pps.php' );
	return PPS_PostSave::act_save_post($post_id, $post);
}

function _pps_act_delete_post( $object_id ) {
	require_once( dirname(__FILE__).'/item-deletion_pps.php' );
	PP_ItemDeletion::act_delete_post( $object_id );
}
