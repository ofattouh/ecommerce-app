<?php
class PPCE_CustomizePostUI {
	public static function hide_admin_divs( $hide_ids, $object_type ) {
		$hide_ids = str_replace( ' ', '', $hide_ids );
		$hide_ids = str_replace( ',', ';', $hide_ids );
		$hide_ids = explode( ';', $hide_ids );	// option storage is as semicolon-delimited string
	
		if ( empty($hide_ids) )
			return;
		
		global $pp, $pp_current_user;
			
		$object_id = pp_get_post_id();

		if ( ! $type_obj = get_post_type_object( $object_type ) )
			return;
		
		$reqd_caps = array();
		//$can_edit_sitewide = $pp->user_can_edit_sitewide('post', $object_type, array( 'ignore_others_caps' => true ) );
		
		$sitewide_requirement = pp_get_option('editor_ids_sitewide_requirement');
		$sitewide_requirement_met = false;
		
		if ( 'admin_option' == $sitewide_requirement )
			$sitewide_requirement_met = current_user_can('pp_manage_settings');
		
		elseif ( 'admin_user' == $sitewide_requirement )
			$sitewide_requirement_met = pp_is_user_administrator();
			
		elseif ( 'admin_content' == $sitewide_requirement )
			$sitewide_requirement_met = pp_is_content_administrator();
			
		elseif ( 'editor' == $sitewide_requirement )
			$reqd_caps = array( $type_obj->cap->edit_published_posts, $type_obj->cap->edit_others_posts ); // $pp->user_can_edit_sitewide('post', $object_type, array( 'status' => 'publish' ) );
		
		elseif ( 'author' == $sitewide_requirement )
			$reqd_caps = array( $type_obj->cap->edit_published_posts ); // $pp->user_can_edit_sitewide('post', $object_type, array( 'status' => 'publish', 'ignore_others_caps' => true ) );
		
		elseif ( $sitewide_requirement )
			$reqd_caps = array( $type_obj->cap->edit_posts );
		else
			$sitewide_requirement_met = true;

		if ( $reqd_caps )
			$sitewide_requirement_met = ! array_diff( $reqd_caps, array_keys( array_intersect( $pp_current_user->allcaps, array( true, 1, '1' ) ) ) );

		if ( $sitewide_requirement_met ) {
			// don't hide anything if a user with sufficient site-wide role is creating a new object
			if ( ! $object_id )
				return;

			if ( ! $object = get_post( $object_id ) )
				return;

			if ( empty($object->post_date) ) // don't prevent the full editing of new posts/pages
				return;

			// don't hide anything if a user with sufficient site-wide role is editing their own object
			/*
			global $current_user;
			if ( empty($object->post_author) || ( $object->post_author == $current_user->ID) )
				return;
			*/
		}

		
		if ( $sitewide_requirement && ! $sitewide_requirement_met ) { // || ! $pp_admin->user_can_admin_object( 'post', $object_id, $object_type ) ) {
			do_action( 'pp_hide_admin_divs', $object_id, $hide_ids, $sitewide_requirement );

			echo( "\n<style type='text/css'>\n<!--\n" );
			
			$removeable_metaboxes = apply_filters( 'pp_removeable_metaboxes', array( 'categorydiv', 'tagsdiv-post_tag', 'postcustom', 'pagecustomdiv', 'authordiv', 'pageauthordiv', 'trackbacksdiv', 'revisionsdiv', 'pending_revisions_div', 'future_revisionsdiv' ) );
			
			foreach ( $hide_ids as $id ) {
				if ( in_array( $id, $removeable_metaboxes ) ) {
					// thanks to piemanek for tip on using remove_meta_box for any core admin div
					remove_meta_box($id, $object_type, 'normal');
					remove_meta_box($id, $object_type, 'advanced');
				} else {
					// hide via CSS if the element is not a removeable metabox
					echo "#$id { display: none !important; }\n";  // this line adapted from Clutter Free plugin by Mark Jaquith
				}
			}
			
			echo "-->\n</style>\n";
		}
	} 
} // end class
