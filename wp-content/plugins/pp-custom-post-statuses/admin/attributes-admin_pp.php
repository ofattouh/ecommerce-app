<?php
class PP_AttributesAdmin {
	// returns $arr[item_id][condition] = true or (if return_array=true) array( 'inherited_from' => $row->inherited_from )
	// src_name = item source name (i.e. 'post') 
	//
	public static function get_item_condition( $src_name, $attribute, $args = array() ) {
		// Note: propogating conditions are always directly assigned to the child item(s).
		// Use assign_for = 'children' to retrieve condition values that are set for propagation to child items,
		$defaults = array( 'id' => null, 'object_type' => '', 'assign_for' => 'item', 'default_only' => false );
		$args = array_merge( $defaults, (array) $args );
		extract($args, EXTR_SKIP);

		if ( $default_only )
			return null;
		
		$query_ids = (array) $id;
		
		global $pp;
		static $listed_object_conditions;

		if ( ! isset($listed_object_conditions) )
			$listed_object_conditions = array();

		$object_cache_id = md5( $src_name . $attribute . serialize($args) );
		
		if ( ! empty( $pp->listed_ids ) && empty( $listed_object_conditions[$object_cache_id] ) ) {
			//$query_ids = array_merge( $query_ids, array_keys( pp_array_flatten( $pp->listed_ids) ) );
			foreach( array_keys($pp->listed_ids) as $_type ) {
				$query_ids = array_merge( $query_ids, array_keys($pp->listed_ids[$_type]) );
			}
		} elseif ( ! empty( $listed_object_conditions[$object_cache_id] ) ) {
			if ( $results = array_intersect_key( $listed_object_conditions[$object_cache_id], array_flip($query_ids) ) ) {
				if ( count( $results ) == count( $query_ids ) )
					return $results;
			}
		}
		
		global $wpdb;

		$items = array();				
		
		if ( $query_ids ) { // don't return all objects
			sort($query_ids);
			$id_clause = "AND item_id IN ('" . implode("','", $query_ids) . "')";	
		} else
			$id_clause = "AND item_id != 0";

		static $all_attrib_conditions;
		if ( ! isset( $all_attrib_conditions ) )
			$all_attrib_conditions = array();

		$qry = $wpdb->prepare( "SELECT attribute, condition_name, item_id, inherited_from FROM $wpdb->pp_conditions WHERE scope = 'object' AND assign_for = %s AND item_source = %s $id_clause", $assign_for, $src_name );
		if ( ! isset($all_attrib_conditions[$qry]) ) {
			$all_attrib_conditions[$qry] = $wpdb->get_results($qry);
		}

		if ( isset($all_attrib_conditions[$qry]) ) {
			foreach( $all_attrib_conditions[$qry] as $row) {
				if ( $attribute == $row->attribute ) {
					$items[$row->item_id][$row->condition_name] = true;
				}
			}
			
			if ( empty( $listed_object_conditions[$object_cache_id] ) )
				$listed_object_conditions[$object_cache_id] = $items;
		}

		return ( ! is_null($id) && isset( $items[$id] ) ) ? key( $items[$id] ) : null;
	}
} // end class
