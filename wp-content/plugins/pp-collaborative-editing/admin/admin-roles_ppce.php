<?php
class PPCE_AdminRoles {
	public static function flt_has_group_cap( $has_cap, $cap_name, $group_id, $group_type ) {
		global $pp_current_user;
		static $editable_group_types;
		
		if ( ! isset($manageable_groups) )
			$editable_group_types = apply_filters( 'pp_editable_group_types', array( 'pp_group' ) );
		
		if ( false === $group_id ) {
			if ( ! $has_cap ) {  // todo: deal with exclude/include to avoid empty listing?
				foreach( $editable_group_types as $_group_type ) {
					if ( $group_type && ( $_group_type != $group_type ) )
						continue;
				
					if ( ! isset( $pp_current_user->except["manage_{$_group_type}"] ) )
						$pp_current_user->retrieve_exceptions( array('manage'), array($_group_type) );
						
					if ( ! empty( $pp_current_user->except["manage_{$_group_type}"][$_group_type]['']['additional'][$_group_type][''] ) )
						return true;
				}
			}
		} else {
			if ( ! in_array( $group_type, $editable_group_types ) )
				return false;
			
			// temp workaround
			if ( 'pp_net_group' == $group_type )
				$group_type = 'pp_group';
			
			if ( ! isset( $pp_current_user->except["manage_{$group_type}"] ) )
				$pp_current_user->retrieve_exceptions( array('manage'), array($group_type) );
			
			if ( $has_cap ) {
				if ( ! empty( $pp_current_user->except["manage_{$group_type}"]['pp_group']['']['include']['pp_group'][''] ) ) {
					if ( ! in_array( $group_id, $pp_current_user->except["manage_{$group_type}"]['pp_group']['']['include']['pp_group'][''] ) )
						return false;
				
				} elseif ( ! empty( $pp_current_user->except["manage_{$group_type}"]['pp_group']['']['exclude']['pp_group'][''] ) ) {
					if ( in_array( $group_id, $pp_current_user->except["manage_{$group_type}"]['pp_group']['']['exclude']['pp_group'][''] ) )
						return false;
				}
			} else {
				if ( ! empty( $pp_current_user->except["manage_{$group_type}"]['pp_group']['']['additional']['pp_group'][''] ) )
					return in_array( $group_id, $pp_current_user->except["manage_{$group_type}"]['pp_group']['']['additional']['pp_group'][''] );
			}
		}
			
		return $has_cap;
	}

	public static function retrieve_admin_groups( $editable_group_ids, $operation ) {
		global $pp_current_user;
		$pp_current_user->retrieve_exceptions( 'manage' );
		
		if ( ! is_array( $editable_group_ids ) )
			$editable_group_ids = array();
		
		// group management exceptions permit full group editing (as well as member management)
		if ( ! empty( $pp_current_user->except['manage_pp_group']['pp_group']['']['additional']['pp_group'][''] ) )
			$editable_group_ids = array_merge( $editable_group_ids, $pp_current_user->except['manage_pp_group']['pp_group']['']['additional']['pp_group'][''] );
		
		if ( ( 'manage' == $operation ) && current_user_can( 'pp_manage_own_groups' ) ) {
			if ( ! $agent_type = apply_filters( 'pp_query_group_type', $agent_type ) )
				$agent_type = 'pp_group';
			
			// work around loading of pp_net_group membership into $user->groups['pp_group'] on bulk member management post
			if ( ( 'pp_net_group' == $agent_type ) && ! isset( $pp_current_user->groups[$agent_type] ) )
				$agent_type = 'pp_group';
			
			if ( $can_manage_own_groups = current_user_can( 'pp_manage_own_groups' ) )
				$editable_group_ids = array_merge( $editable_group_ids, array_keys( $pp_current_user->groups[$agent_type] ) );
		}
		
		return $editable_group_ids;
	}
	
	public static function flt_can_set_exceptions( $can, $operation, $for_item_type, $args = array() ) {
		$defaults = array( 'item_id' => 0, 'via_item_source' => 'post', 'via_item_type' => '', 'for_item_source' => 'post', 'is_administrator' => false );
		$args = extract( array_merge( $defaults, $args ), EXTR_SKIP );
		
		if ( ! $is_administrator ) {
			$can_assign_edit_exceptions = false;

			if ( pp_get_option( 'non_admins_set_edit_exceptions' ) && defined( 'PP_NON_EDITORS_SET_EDIT_EXCEPTIONS' ) && PP_NON_EDITORS_SET_EDIT_EXCEPTIONS ) {
				$can_edit_published = true;
			} else {
				$can_edit_published = false;
				
				global $pp_current_user;
				if ( $type_obj = get_post_type_object( $for_item_type ) ) {
					$can_edit_published = ! empty( $pp_current_user->allcaps[ $type_obj->cap->edit_published_posts ] );
					
					if ( $can_edit_published ) {
						// also require edit_others_posts (unless this is a post-assigned exception for user's own post)
						if ( ! $can_edit_published = ! empty( $pp_current_user->allcaps[ $type_obj->cap->edit_others_posts ] ) ) {
							if ( 'post' == $via_item_source ) {
								if ( ! $item_id )
									$item_id = pp_get_post_id();
								
								$_post = get_post( $item_id );
								$can_edit_published = $_post && ( $_post->post_author == $pp_current_user->ID );
							}
						}
					}
				}
			}
			
			$can_assign_edit_exceptions = $can_edit_published && current_user_can( 'pp_set_edit_exceptions' );
		}
		
		switch( $operation ) {
			case 'edit' :
				$can = $is_administrator || $can_assign_edit_exceptions;
				break;
				
			case 'fork':
				$can = class_exists('Fork', false ) && ! defined( 'PP_DISABLE_FORKING_SUPPORT' ) && ( $is_administrator || $can_assign_edit_exceptions || ( $can_edit_published && current_user_can( 'pp_set_fork_exceptions' ) ) );
				break;
				
			case 'revise':
				$can = defined( 'RVY_VERSION' ) && ( $is_administrator || $can_assign_edit_exceptions || ( $can_edit_published && current_user_can( 'pp_set_revise_exceptions' ) ) );
				break;
				
			case 'associate':
				if ( 'term' == $via_item_source )
					$can = is_taxonomy_hierarchical( $for_item_type ) && ( $is_administrator || current_user_can( 'pp_set_term_associate_exceptions' ) );
				else
					$can = is_post_type_hierarchical( $for_item_type ) && ( $is_administrator || ( $can_edit_published && current_user_can( 'pp_set_associate_exceptions' ) ) );
				
				break;
				
			case 'assign':
				$can = $is_administrator || ( $can_edit_published && current_user_can( 'pp_set_term_assign_exceptions' ) );
				break;
				
			case 'manage':
				$can = $is_administrator || current_user_can( 'pp_set_term_manage_exceptions' );
				break;
		}
		
		return $can;
	}
} // end class
