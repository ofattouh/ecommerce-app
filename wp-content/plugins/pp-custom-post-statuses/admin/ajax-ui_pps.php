<?php
class PPS_Permissions_Ajax {
	public static function flt_exceptions_status_ui( $html, $for_type, $args = array() ) {
		$defaults = array( 'via_src_name' => '', 'operation' => '', 'type_caps' => array() );
		extract( array_merge( $defaults, $args ), EXTR_SKIP );

		if ( 'term' == $via_src_name ) { 
			if ( 'forum' != $for_type ) {  // @todo: API
				$organized_stati = array();
				$organized_stati['private'] = apply_filters( 'pp_order_types', pp_get_post_stati( array( 'private' => true, '_builtin' => false, 'post_type' => $for_type ), 'object' ), array( 'order_property' => 'label' ) );
				
				if ( 'edit' == $operation )
					$organized_stati['moderation'] = apply_filters( 'pp_order_types', pp_get_post_stati( array( 'moderation' => true, 'post_type' => $for_type ), 'object' ), array( 'order_property' => 'order' ) );
				
				if ( $organized_stati ) {
					$stati_captions = array( 'moderation' => __( 'Custom Moderation: ', 'pp' ),
											 'private' => __( 'Custom Visibility: ', 'pp' ) );

					$html .='<div id="pp_select_custom_attribs">';
					
					foreach( $organized_stati as $status_class => $stati ) {
						$html .= '<div class="pp-attrib">';
						$did_caption = false;
						foreach( $stati as $status_name => $status_obj ) {
							if ( ! $did_caption ) {
								$html .= $stati_captions[$status_class] . '<br />';
								$did_caption = true;
							}
						
							$html .= '<p class="pp-checkbox pp-attrib">'
							. "<input type='checkbox' id='pp_select_x_cond_post_status_{$status_name}' name='pp_select_x_cond[]' value='post_status:{$status_name}' /> "
							. "<label id='lbl_pp_select_x_cond_post_status_{$status_name}' for='pp_select_x_cond_post_status_{$status_name}'>" . $status_obj->label . '</label>'
							. '</p>';
						}
					}
					
					$html .= '</div>'; // pp_select_custom_attribs
				}
			}
		}
		
		return $html;
	}
}
