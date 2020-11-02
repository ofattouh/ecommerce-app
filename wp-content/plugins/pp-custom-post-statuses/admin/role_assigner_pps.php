<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PPS_RoleAssigner {
	static function set_condition_action( $assignment_id, $cond_arr ) {		
		do_action( 'pp_set_item_condition', $cond_arr['scope'], $cond_arr['item_source'], $cond_arr['item_id'], $assignment_id, $cond_arr );
	}

	// $set_conditions[attribute][condition] = true
	// additional arguments recognized by _pp_insert_item_condition:
	//	is_auto_insertion = false  (if true, skips logging the item as having a manually modified condition)
	public static function set_item_condition( $attribute, $scope, $src_name, $item_id, $set_condition, $assign_for = 'item', $args = array() ) {
		//$defaults = array( 'force_flush' => false, 'propagate' => false, 'is_bulk' => false );
		//$args = array_merge($defaults, (array) $args);
		//extract($args, EXTR_SKIP);

		global $wpdb;
		
		// Note: each attribute should have at most one stored condition per item.  But force retrieval of last entry in case of redundant storage.  pp_insert_item_condition will delete redundancies
		$qry = $wpdb->prepare( "SELECT condition_name, assignment_id FROM $wpdb->pp_conditions WHERE scope = '$scope' AND attribute = %s"
			. " AND item_source = %s AND item_id = %d AND assign_for = %s ORDER BY assignment_id DESC LIMIT 1",
			$scope, $attribute, $src_name, $item_id, $assign_for
			);

		if ( $row = $wpdb->get_row($qry) ) {
			if ( ( $row->condition_name != $set_condition ) || $force_flush ) {
				self::remove_conditions_by_id( array( $row->assignment_id ) );
			} else { //if ( ! $propagate || ! $is_bulk ) {
				return;
			}
		}

		self::insert_item_condition( $attribute, $scope, $src_name, $item_id, $set_condition, $assign_for, $args );
	}

	// $insert_condition = assign_for value for insertion ('item' or 'children')
	// $propagate_from_ass_id = assignment_id for inherited_from
	public static function insert_item_condition ($attribute, $scope, $item_source, $item_id, $condition, $assign_for, $args = array()) {
		$defaults = array( 'inherited_from' => 0, 'is_auto_insertion' => false, 'propagate' => false );  // auto_insertion arg set for restriction propagation from parent objects
		$args = array_merge( $defaults, (array) $args );
		extract($args, EXTR_SKIP);

		global $wpdb;
		
		$inherited_from = (int) $inherited_from;
		
		$qry_delete_base = $wpdb->prepare( "SELECT assignment_id FROM $wpdb->pp_conditions"
						. " WHERE scope = %s AND attribute = %s AND item_source = %s", $scope, $attribute, $item_source );
		
		$qry_insert_base = $wpdb->prepare( "INSERT INTO $wpdb->pp_conditions"
						 . " (item_source, attribute, condition_name, scope, item_id, assign_for, inherited_from)"
						 . " VALUES (%s, %s, %s', %s,", $item_source, $attribute, $condition, $scope ); // item_id, assign_for, inherited_from values must be appended
		
		// before inserting the role, delete any other matching or conflicting assignments this user/group has for the same object
		if ( $ass_ids = $wpdb->get_col( $wpdb->prepare( $qry_delete_base . " AND assign_for = %s AND item_id = %d", $assign_for, $item_id ) ) ) {
			self::remove_conditions_by_id( $ass_ids );
		}

		$item_id = (int) $item_id;
		$assign_for = pp_sanitize_key($assign_for);
		$inherited_from = (int) $inherited_from;
		
		$insert_data = compact( 'item_source', 'attribute', 'scope' );
		$insert_data['condition_name'] = $condition;

		// insert condition for specified item
		$wpdb->insert( $wpdb->pp_conditions, array_merge( $insert_data, compact( 'item_id', 'assign_for', 'inherited_from' ) ) );

		$assignment_id = $wpdb->insert_id;
		$cond_arr = compact( 'attribute', 'condition_name', 'scope', 'item_source', 'item_id', 'assign_for', 'inherited_from' );
		self::set_condition_action( $assignment_id, $cond_arr );

		// insert condition for all descendant items, if requested
		if ( ( 'children' == $assign_for ) && $propagate ) {
			if ( ! $inherited_from ) {
				$inherited_from = (int) $wpdb->insert_id;
				$cond_arr['inherited_from'] = $inherited_from;
			}
			
			if ( ! $descendant_ids = pp_get_descendant_ids( $item_source, $item_id ) ) //, compact('is_auto_insertion') ) )
				return;
			
			// note: Propagated conditions will be converted to direct-assigned roles if the parent object/term is deleted.
			//		 If the parent setting is changed, conditions inherited from old parent will be cleared before inheriting conditions from new parent. 
			$delete_ass_ids = array();
			
			foreach ( $descendant_ids as $id ) {
				// before inserting the role, delete any other propagated assignments this user/group has for the same object type
				if ( $_ass_ids = $wpdb->get_col( $qry_delete_base . " AND item_id = '$id'" ) )
					$delete_ass_ids = array_merge( $delete_ass_ids, $_ass_ids );

				$cond_arr['item_id'] = $id;

				$wpdb->insert( $wpdb->pp_conditions, array_merge( $insert_data, array( 'item_id' => $id, 'assign_for' => 'item', 'inherited_from' => $inherited_from ) ) );
				$assignment_id = $wpdb->insert_id;
				$cond_arr['assign_for'] = 'item';
				self::set_condition_action( $assignment_id, $cond_arr );
				
				$wpdb->insert( $wpdb->pp_conditions, array_merge( $insert_data, array( 'item_id' => $id, 'assign_for' => 'children', 'inherited_from' => $inherited_from ) ) );
				$assignment_id = $wpdb->insert_id;
				$cond_arr['assign_for'] = 'item';
				self::set_condition_action( $assignment_id, $cond_arr );
			}
			
			if ( $delete_ass_ids )
				self::remove_conditions_by_id( $delete_ass_ids );
		}
	}

	public static function clear_item_condition ( $attribute, $scope, $src_name, $item_id, $assign_for, $args = array() ) {
		$defaults = array ( 'inherited_only' => false, 'propagate' => false );
		$args = array_merge( $defaults, (array) $args );
		extract($args, EXTR_SKIP);	

		global $wpdb;

		$inherited_clause = ( $inherited_only ) ? "AND inherited_from > 0" : '';
		
		$ids = (array) $item_id;
		
		if ( $propagate ) {
			if ( $descendant_ids = pp_get_descendant_ids( $src_name, $item_id ) ) {
				$ids = array_merge( $ids, $descendant_ids );
			}
		}
		
		$qry = $wpdb->prepare( 
			"SELECT assignment_id FROM $wpdb->pp_conditions WHERE attribute = %s AND scope = %s AND assign_for = %s AND item_source = %s $inherited_clause AND item_id IN ('" . implode( "','", $ids ) . "')",
			$attribute, $scope, $assign_for, $src_name
		);
		
		
		if ( $ass_ids = $wpdb->get_col( $qry ) ) {
			self::remove_conditions_by_id( $ass_ids );
		}
	}
	
	public static function remove_conditions_by_id( $delete_assignments ) {
		$delete_assignments = (array) $delete_assignments;
		
		if ( ! count($delete_assignments) )
			return;

		global $wpdb;
		
		// Propagated roles will be deleted only if the original progenetor goes away.  Removal of a "link" in the parent/child propagation chain has no effect.
		$id_in = "'" . implode("', '", $delete_assignments ) . "'";
		//$where = "assignment_id IN ($id_in) OR (inherited_from IN ($id_in) AND inherited_from != '0')";
		$where = "assignment_id IN ($id_in)";
		
		$conditions = $wpdb->get_results( "SELECT * FROM $wpdb->pp_conditions WHERE $where" );	// deleted condition data will be passed through action
		
		$wpdb->query( "DELETE FROM $wpdb->pp_conditions WHERE $where" );

		foreach( $conditions as $cond ) {
			do_action( 'pp_removed_item_condition', $cond->scope, $cond->item_source, $cond->item_id, $cond->assignment_id, (array) $cond );    // called once per removed conditions (potentially multiple per item)
		}
		
		do_action( 'pp_removed_conditions', $delete_assignments );
	}
} // end class
