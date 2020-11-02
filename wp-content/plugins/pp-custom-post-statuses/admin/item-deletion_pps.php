<?php
class PP_ItemDeletion {
	public static function act_delete_post( $object_id, $args = array() ) {
		$defaults = array( 'object_type' => '' );
		$args = array_intersect_key( $defaults, (array) $args );
		extract($args, EXTR_SKIP);
	
		if ( ! $object_id )
			return;

		// could defer role maint to speed potential bulk deletion, but script may be interrupted before admin_footer
		self::item_deletion_aftermath( 'object', 'post', $object_id );
	}
	
	public static function item_deletion_aftermath( $scope, $src_name, $item_id ) {
		global $wpdb;

		require_once( dirname(__FILE__).'/role_assigner_pps.php' );
		
		if ( $ass_ids = $wpdb->get_col( $wpdb->prepare( "SELECT assignment_id FROM $wpdb->pp_conditions WHERE scope = %s AND item_source = %s AND item_id = %d", $scope, $src_name, $item_id ) ) ) {
			PPS_RoleAssigner::remove_conditions_by_id( $ass_ids );
			
			// Propagated requirements will be converted to direct-assigned roles if the original progenetor goes away.  Removal of a "link" in the parent/child propagation chain has no effect.
			$id_in = "'" . implode("', '", $ass_ids) . "'";
			$wpdb->query("UPDATE $wpdb->pp_conditions SET inherited_from = '0' WHERE inherited_from IN ($id_in)");
		}
	}
}
