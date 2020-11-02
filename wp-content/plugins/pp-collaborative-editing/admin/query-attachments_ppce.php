<?php
class PPCE_QueryAttachments {
	public static function append_attachment_clause( $where, $clauses, $args ) {
		$defaults = array( 'src_table' => '', 'source_alias' => '', 'required_operation' => 'edit' );
		extract( array_merge( $defaults, $args ), EXTR_SKIP );
		
		global $wpdb, $current_user;

		//if ( ( 'read' == $args['required_operation'] ) && ! is_admin() )
		if ( 'read' == $args['required_operation'] )
			return $where;

		if ( empty($args['limit_statuses']) )
			$args['skip_stati_usage_clause'] = true;
	
		if ( ! $src_table )
			$src_table = ( $source_alias ) ? $source_alias : $wpdb->posts;

		$comment_query = isset($args['query_contexts']) && in_array( 'comments', (array) $args['query_contexts'] );

		if ( ( empty($args['has_cap_check']) || ( 'read' == $required_operation ) ) && ! $comment_query )
			$admin_others_attached = pp_get_option( 'admin_others_attached_files' );
		else {
			if ( $admin_others_attached = pp_get_option( 'edit_others_attached_files' ) ) {
				if ( $comment_query && ( 'read' != $required_operation ) )
					$admin_others_attached = ! empty( $current_user->allcaps['edit_others_files'] );
			}
		}
		
		$type_obj = get_post_type_object( 'attachment' );

		if ( 'delete' == $required_operation )
			$reqd_cap = ( $type_obj->cap->delete_others_posts != 'edit_others_posts' ) ? $type_obj->cap->edit_others_posts : 'delete_others_files';
		else
			$reqd_cap = ( $type_obj->cap->edit_others_posts != 'edit_others_posts' ) ? $type_obj->cap->edit_others_posts : 'edit_others_files';

		$admin_others_unattached = ( empty($args['has_cap_check']) || ! empty( $current_user->allcaps[$reqd_cap] ) ) 																// edit_others_unattached_files
								&& ( pp_get_option( 'admin_others_unattached_files' ) || ! empty( $current_user->allcaps['list_others_unattached_files'] ) || ! empty( $current_user->allcaps['edit_others_files'] ) );  // PP Setting effectively eliminates cap requirement
		
		$can_edit_others_sitewide = false;
		
		if ( ! $admin_others_attached || ! $admin_others_unattached ) {
			if ( pp_is_content_administrator() )
				$can_edit_others_sitewide = true;
		}
		
		// optionally hide other users' unattached uploads, but not from site-wide Editors
		if ( $admin_others_unattached || $can_edit_others_sitewide )
			$author_clause = '';
		else
			$author_clause = "AND $src_table.post_author = {$current_user->ID}";
	
		if ( empty($args['subqry']) && $author_clause && ! $admin_others_unattached && ! $admin_others_attached && ! $can_edit_others_sitewide ) {
			$where .= " AND ( 1=1 $author_clause )";
		} else {
			$parent_subquery = ( ! empty($args['subqry']) ) ? "$src_table.post_parent IN ({$args['subqry']})" : "$src_table.post_parent > 0";
			
			$admin_others_attached_to_readable = ( empty($args['has_cap_check']) || ( 'read_post' == $args['has_cap_check'] ) ) && pp_get_option( 'admin_others_attached_to_readable' );
			
			// todo: leaner implementation?
			if ( ! empty($args['subqry']) && $admin_others_attached_to_readable && ( 'edit' == $args['required_operation'] ) && isset( $args['subqry_args'] ) ) {
				global $query_interceptor;
				$args['subqry_args']['required_operation'] = 'read';
				$pp_where = $query_interceptor->flt_posts_where( '', $args['subqry_args'] );
				$readable_parents_subqry = "SELECT ID FROM $wpdb->posts AS p WHERE 1=1 AND p.post_type IN ('" . $args['subqry_typecsv'] . "') $pp_where";
				$parent_subquery = "( $parent_subquery OR $src_table.post_parent IN ( $readable_parents_subqry ) )";
			}
			
			if ( is_admin() && ( ! defined('PP_BLOCK_UNATTACHED_UPLOADS') || ! PP_BLOCK_UNATTACHED_UPLOADS ) )
				$unattached_clause = "( $src_table.post_parent = 0 $author_clause ) OR";
			else
				$unattached_clause = '';

			//$attached_clause = ( $admin_others_attached || $can_edit_others_sitewide || ! empty($args['has_cap_check']) ) ? '' : "AND $src_table.post_author = '{$current_user->ID}'";
			$attached_clause = ( $admin_others_attached || $can_edit_others_sitewide || $admin_others_attached_to_readable ) ? '' : "AND $src_table.post_author = {$current_user->ID}";
			
			$own_clause = ( pp_get_option( 'own_attachments_always_editable' ) || ! empty($current_user->allcaps['edit_own_attachments']) ) ? "$src_table.post_author = {$current_user->ID} OR " : '';
			$where .= " AND ( {$own_clause}$unattached_clause ( $parent_subquery $attached_clause ) )";
		}

		$where = str_replace( "( $src_table.post_parent = 0 ) OR ( $src_table.post_parent > 0 )", "1=1", $where );
		
		return $where;
	}
} // end class
