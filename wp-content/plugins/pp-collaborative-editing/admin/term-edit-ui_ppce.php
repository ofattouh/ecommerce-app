<?php
class PPCE_AdminFiltersTermUI {
	function __construct() {
		add_filter( 'pp_item_edit_exception_ops', array( &$this, 'flt_item_edit_exception_ops' ), 10, 4 );
		
		add_filter( 'pp_term_exceptions_metaboxes', array(&$this, 'pp_term_exceptions_metaboxes'), 10, 3 );
		add_action( 'pp_prep_metaboxes', array(&$this, 'pp_prep_metaboxes'), 10, 3 );
		//add_action( 'pp_update_item_exceptions', array(&$this, 'update_item_exceptions'), 10, 3 );
	}
	
	function flt_item_edit_exception_ops( $operations, $for_src_name, $taxonomy, $for_item_type ) {
		foreach( array( 'edit', 'fork', 'revise', 'assign' ) as $op ) {
			if ( _pp_can_set_exceptions( $op, $for_item_type, array( 'via_src_name' => 'term', 'via_type_name' => $taxonomy, 'for_src_name' => $for_src_name ) ) )
				$operations[$op] = true;
		}
		
		return $operations;
	}
	
	function update_item_exceptions( $via_item_source, $item_id, $args ) {
		if ( 'term' == $via_item_source ) {
			PP_ItemSave::item_update_process_exceptions( 'term', 'term', $item_id, $args );
		}
	}
	
	function pp_prep_metaboxes ( $via_item_source, $via_item_type, $tt_id ) {
		if ( 'term' == $via_item_source ) {
			global $pp_term_edit_ui, $typenow;
			if ( ! $typenow ) {
				$args = array( 'for_item_type' => $via_item_type );	// via_src, for_src, via_type, item_id, args
				$pp_term_edit_ui->item_exceptions_ui->data->load_exceptions( 'term', 'term', $via_item_type, $tt_id, $args );
			}
		}
	}
	
	function pp_term_exceptions_metaboxes( $boxes, $taxonomy, $typenow ) {
		global $typenow;
		if ( ! $typenow ) {	 // term management / association exceptions UI only displed when editing "Universal Exceptions" (empty post type)
			$tx = get_taxonomy( $taxonomy );
			$add_boxes = array();
			
			foreach( array( 'manage', 'associate' ) as $op ) {
				if ( $op_obj = pp_get_op_object( $op, $typenow ) ) {
					pp_set_array_elem( $add_boxes, array( $op, "pp_{$op}_{$taxonomy}_exceptions" ) );				
					$add_boxes[$op]["pp_{$op}_{$taxonomy}_exceptions"]['for_item_type'] = $taxonomy;
					$add_boxes[$op]["pp_{$op}_{$taxonomy}_exceptions"]['for_item_source'] = 'term'; // $taxonomy;
					$add_boxes[$op]["pp_{$op}_{$taxonomy}_exceptions"]['title'] = sprintf( __( '%1$s %2$s Exceptions', 'pp' ), $tx->labels->singular_name, $op_obj->noun_label );
				}
			}
			
			$boxes = array_merge( $add_boxes, $boxes );  // put Category Management Exceptions box at the top
		}
		
		return $boxes;
	}
}
