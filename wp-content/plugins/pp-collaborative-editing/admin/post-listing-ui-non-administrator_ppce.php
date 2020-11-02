<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_filter( 'posts_results', array( 'PPCE_FiltersAdminUI_NonAdmin', 'flt_posts_results'), 50, 3 );
add_filter( 'the_posts', array( 'PPCE_FiltersAdminUI_NonAdmin', 'flt_posts_listing' ), 50 );

// prevent construction of erroneous view link url when relative hierarchy is preserved by remapping pages around inaccessable ancestors
add_action( 'all_admin_notices', array( 'PPCE_FiltersAdminUI_NonAdmin', 'flush_post_cache' ), 50 );

class PPCE_FiltersAdminUI_NonAdmin {
	public static function flush_post_cache() {
		if ( is_post_type_hierarchical( pp_find_post_type() ) )
			wp_cache_flush();
	}

	public static function flt_posts_results( $results, $query_obj ) {		
		$post_type_obj = get_post_type_object( pp_find_post_type() );

		if ( ! empty($post_type_obj->hierarchical) ) {
			require_once( PPC_ABSPATH . '/lib/ancestry_lib_pp.php' );
			$ancestors = PP_Ancestry::get_page_ancestors(); // array of all ancestor IDs for keyed page_id, with direct parent first
			PP_Ancestry::remap_tree( $results, $ancestors );
		}

		return $results;
	}

	public static function flt_posts_listing( $results ) {
		global $pp;
		
		$listed_ids = array();
		
		// In edit.php, WP forces all objects into recordset for hierarchical post types.  But for perf enchancement, we need to know IDs of items which are actually listed		
		foreach ( $results as $row )
			if ( isset($row->ID) )
				$listed_ids[$row->post_type][$row->ID] = true;

		foreach( array_keys($listed_ids) as $post_type )
			$pp->set_listed_ids( $post_type, $listed_ids[$post_type] );
		
		return $results;
	}
} // end class
