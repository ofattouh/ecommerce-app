<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Work around bug in More Taxonomies (and possibly other plugins) where category taxonomy is overriden without setting it public
foreach( array( 'category', 'post_tag' ) as $taxonomy ) {
	global $wp_taxonomies;
	if ( isset( $wp_taxonomies[$taxonomy] ) )
		$wp_taxonomies[$taxonomy]->public = true;
}

class PPCE_Cap_Helper {
	var $all_taxonomy_caps = array();	// $all_taxonomy_caps = array of cap names
	
	function __construct() {
		$this->force_distinct_taxonomy_caps();
		add_filter( 'pp_administrator_caps', array( $this, '_ppce_administrator_caps' ) );
		
		add_filter( 'pp_exclude_arbitrary_caps', array( $this, 'flt_exclude_arbitrary_caps' ) );
	}
	
	function _ppce_administrator_caps( $caps ) {
		return array_merge( $caps, array_fill_keys( array_keys($this->all_taxonomy_caps), true ) );
	}
	
	function flt_exclude_arbitrary_caps( $exclude_caps ) {
		return array_merge( $exclude_caps, $this->all_taxonomy_caps );
	}
	
	function force_distinct_taxonomy_caps() {
		global $wp_taxonomies;
	
		$use_taxonomies = pp_get_enabled_taxonomies();
		
		// note: we are allowing the 'assign_terms' property to retain its default value of 'edit_posts'.  The RS user_has_cap filter will convert it to the corresponding type-specific cap as needed.
		$tx_specific_caps = array( 'edit_terms' => 'manage_terms', 'manage_terms' => 'manage_terms', 'delete_terms' => 'manage_terms' );
		$used_values = array();
		
		// currently, disallow category and post_tag cap use by custom taxonomies, but don't require category and post_tag to have different caps
		$core_taxonomies = array( 'category' );
		foreach( $core_taxonomies as $taxonomy )
			foreach( array_keys($tx_specific_caps) as $cap_property )
				$used_values []= $wp_taxonomies[$taxonomy]->cap->$cap_property;
	
		$used_values = array_unique( $used_values );
		
		foreach( array_keys($wp_taxonomies) as $taxonomy ) {
			//if ( 'post_tag' == $taxonomy )
			//	continue;
		
			if ( 'yes' == $wp_taxonomies[$taxonomy]->public ) {	// clean up a GD Taxonomies quirk (otherwise wp_get_taxonomy_object will fail when filtering for public => true)
				$wp_taxonomies[$taxonomy]->public = true;
			
			} elseif ( ( '' === $wp_taxonomies[$taxonomy]->public ) && ( ! empty( $wp_taxonomies[$taxonomy]->query_var_bool ) ) ) { // clean up a More Taxonomies quirk (otherwise wp_get_taxonomy_object will fail when filtering for public => true)
				$wp_taxonomies[$taxonomy]->public = true;
			}
			
			$tx_caps = (array) $wp_taxonomies[$taxonomy]->cap;
			
			if ( ( ! in_array($taxonomy, $use_taxonomies) || empty( $wp_taxonomies[$taxonomy]->public ) ) && ( 'nav_menu' != $taxonomy ) )
				continue;

			if ( ! in_array( $taxonomy, $core_taxonomies ) ) {
				// don't allow any capability defined for this taxonomy to match any capability defined for category or post tag (unless this IS category or post tag)
				foreach( $tx_specific_caps as $cap_property => $replacement_cap_format ) {
					if ( ! empty($tx_caps[$cap_property]) && in_array( $tx_caps[$cap_property], $used_values ) )
						$wp_taxonomies[$taxonomy]->cap->$cap_property = str_replace( 'terms', "{$taxonomy}s", $replacement_cap_format );
						
					$used_values []= $tx_caps[$cap_property];
				}
				
				$tx_caps = (array) $wp_taxonomies[$taxonomy]->cap;
			}

			$this->all_taxonomy_caps = array_merge( $this->all_taxonomy_caps, array_fill_keys( $tx_caps, true ), array( 'assign_term' => true ) );
		}
		
		// make sure Nav Menu Managers can also add menu items
		global $wp_taxonomies;
		$wp_taxonomies['nav_menu']->cap->assign_terms = 'manage_nav_menus';
	}
	
} // end class PPCE_Cap_Helper
