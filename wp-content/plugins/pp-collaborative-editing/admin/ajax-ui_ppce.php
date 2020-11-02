<?php
class PPCE_Permissions_Ajax {
	public static function flt_operation_captions( $op_captions ) {
		$op_captions['edit'] = (object) array( 'label' => __('Edit'), 		  'noun_label' => __('Editing', 'pp') );
		
		if ( defined( 'PP_PUBLISH_EXCEPTIONS' ) )
			$op_captions['publish'] = (object) array( 'label' => __('Publish'),		'noun_label' => __('Publishing', 'pp') );
		
		if ( defined( 'RVY_VERSION' ) )
			$op_captions['revise'] = (object) array( 'label' => __('Revise'), 	'noun_label' => __('Revision', 'pp') );
			
		if ( class_exists('Fork', false ) && ! defined( 'PP_DISABLE_FORKING_SUPPORT' ) )
			$op_captions['fork'] = (object) array( 'label' => __('Fork'), 	'noun_label' => __('Fork', 'pp') );
			
		$op_captions = array_merge( $op_captions, array( 
			'associate' => (object) array( 	'label' => __('Associate', 'pp'), 	'noun_label' => __('Association (as Parent)', 'pp'), 'agent_label' => __('Associate (as parent)', 'pp') ),
			'assign' => (object) array( 	'label' => __('Assign Term', 'pp'),	'noun_label' => __('Assignment', 'pp') ),
			/*'publish' => (object) array( 	'label' => __('Publish'),		  	'noun_label' => __('Publishing', 'pp') ),*/
			'manage' => (object) array( 	'label' => __('Manage'),		  	'noun_label' => __('Management', 'pp') ),
		) );
			
		return $op_captions;
	}
	
	public static function flt_exception_operations( $ops, $for_src_name, $for_item_type ) {
		if ( 'post' == $for_src_name ) {
			$op_obj = pp_get_op_object( 'edit', $for_item_type );
			$ops['edit'] = $op_obj->label; //, 'delete' => __('Delete') );
			
			if ( defined( 'PP_PUBLISH_EXCEPTIONS' ) ) {
				$op_obj = pp_get_op_object( 'publish', $for_item_type );
				$ops['publish'] = $op_obj->label; //, 'delete' => __('Delete') );
			}
			
			if ( class_exists('Fork', false ) && ! defined( 'PP_DISABLE_FORKING_SUPPORT' ) && ! in_array( $for_item_type, array( 'forum' ) ) ) {
				$op_obj = pp_get_op_object( 'fork', $for_item_type );
				$ops['fork'] = $op_obj->label;
			}
			
			if ( defined( 'RVY_VERSION' ) && ! in_array( $for_item_type, array( 'forum' ) ) ) {
				$op_obj = pp_get_op_object( 'revise', $for_item_type );
				$ops['revise'] = $op_obj->label;
			}
			
			if ( $for_item_type && is_post_type_hierarchical( $for_item_type ) ) {
				$op_obj = pp_get_op_object( 'associate', $for_item_type );
				$ops['associate'] = $op_obj->agent_label;
			}
			
			$type_arg = ( $for_item_type ) ? array( 'object_type' => $for_item_type ) : array();
			if ( pp_get_enabled_taxonomies( $type_arg ) ) {
				$op_obj = pp_get_op_object( 'assign', $for_item_type );
				$ops['assign'] = $op_obj->label;
			}

		} elseif ( '_term_' == $for_src_name ) {
			$op_obj = pp_get_op_object( 'manage' );
			$ops['manage'] = $op_obj->label;

			//if ( is_taxonomy_hierarchical( $for_item_type ) )
				$op_obj = pp_get_op_object( 'associate' );
				$ops['associate'] = $op_obj->agent_label;

		} elseif ( in_array( $for_src_name, array( 'pp_group', 'pp_net_group' ) ) ) {
			$op_obj = pp_get_op_object( 'manage', $for_item_type );
			$ops['manage'] = $op_obj->label;
		}
		
		return $ops;
	}
	
	public static function flt_exceptions_status_ui( $html, $for_type, $args = array() ) {
		$defaults = array( 'via_src_name' => '', 'operation' => '', 'type_caps' => array() );
		extract( array_merge( $defaults, $args ), EXTR_SKIP );
	
		if ( ! in_array( $operation, array( 'read', 'publish_topics', 'publish_replies' ) ) && ( 'attachment' != $for_type ) ) {
			$html .= '<p class="pp-checkbox" style="white-space:nowrap">'
					. '<input type="checkbox" id="pp_select_cond_post_status_unpub" name="pp_select_cond[]" value="post_status:{unpublished}"> '
					. '<label for="pp_select_cond_post_status_unpub">' . __('(unpublished)', 'pp') . '</label>'
					. '</p>';
		}
		
		return $html;
	}
	
	public static function flt_exception_via_types( $types, $for_src_name, $for_type, $operation, $mod_type ) {
		if ( '_term_' == $for_src_name ) {
			foreach( pp_get_enabled_taxonomies( array( 'object_type' => false ), 'object' ) as $taxonomy => $tx_obj )
				$types[$taxonomy] = $tx_obj->labels->name;
				
			if ( 'manage' != $operation )
				unset( $types['nav_menu'] );
		}
		
		return $types;
	}
}
