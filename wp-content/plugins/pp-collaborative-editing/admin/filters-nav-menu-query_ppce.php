<?php

/**
 * PPCE_NavMenuQuery class
 * 
 * @package PP
 * @author Kevin Behrens <kevin@agapetry.net>
 * @copyright Copyright (c) 2011-2017, Agapetry Creations LLC
 * 
 */
class PPCE_NavMenuQuery
{
	function __construct() {
		add_filter( 'parse_query', array(&$this, 'available_menu_items_parse_query' ) );
	}
	
	// enable this to prevent Nav Menu Managers from adding items they cannot edit
	function available_menu_items_parse_query( &$query ) {
		//if ( pp_get_option( 'admin_nav_menu_filter_items' ) ) {
			if ( isset($query->query_vars['post_type']) && ( 'nav_menu_item' == $query->query_vars['post_type'] ) )
				return;
			
			$query->query_vars['include'] = '';
			$query->query_vars['post__in'] = '';
			
			$query->query['include'] = '';
			$query->query['post__in'] = '';

			if ( empty($query->query_vars['post_status']) || ( 'trash' != $query->query_vars['post_status'] ) ) {
				$query->query_vars['post_status'] = '';
				$query->query['post_status'] = '';
			}
			
			$query->query['suppress_filters'] = false;
			$query->query_vars['suppress_filters'] = false;
		//}
	}
}
