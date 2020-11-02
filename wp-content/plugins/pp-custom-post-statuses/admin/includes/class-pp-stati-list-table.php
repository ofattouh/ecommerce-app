<?php
require_once( dirname(__FILE__).'/stati-query_pp.php' );

class PP_Attributes_List_Table extends WP_List_Table {
	var $site_id;
	var $attribute;
	var $attrib_type;
	var $role_info;
	
	function __construct($attrib_type) {
		$screen = get_current_screen();

		// clear out empty entry from initial admin_header.php execution
		global $_wp_column_headers;
		if ( isset( $_wp_column_headers[ $screen->id ] ) )
			unset( $_wp_column_headers[ $screen->id ] );

		add_filter( "manage_{$screen->id}_columns", array( &$this, 'get_columns' ), 0 );

		parent::__construct( array(
			'singular' => 'status',
			'plural'   => 'statuses'
		) );
		
		$this->attribute = 'post_status';
		$this->attrib_type = $attrib_type;
	}
	
	function ajax_user_can() {
		return current_user_can( 'pp_define_post_status' );
	}

	function prepare_items() {
		global $groupsearch;

		$args = array();

		// Query the user IDs for this page
		$pp_attrib_search = new PP_Attribute_Query( $this->attribute, $this->attrib_type, $args );

		$this->items = $pp_attrib_search->get_results();
		
		$this->set_pagination_args( array(
			'total_items' => $pp_attrib_search->get_total(),
			//'per_page' => $groups_per_page,
		) );
	}

	function no_items() {
		_e( 'No matching statuses were found.', 'pps' );
	}

	function get_views() {
		return array();
	}

	function get_bulk_actions() {
		return array();
	}

	function get_columns() {
		$c = array(
			//'cb'       => '<input type="checkbox" />',
			'status'  => __( 'Status' )
		);
		
		if ( defined( 'PPCE_VERSION' ) && ( 'moderation' == $this->attrib_type ) )
			$c['order'] = __( 'Order', 'pps' );
		
		$c = array_merge( $c, array(
			'cap_map'	 => __( 'Capability Mapping', 'pps' ),
			'post_types' => __( 'Post Types', 'pps' ),
			'enabled' => __( 'Enabled' ),
		) );

		return $c;
	}

	function get_sortable_columns() {
		$c = array();

		return $c;
	}

	function display_rows() {
		$style = '';
		
		foreach ( $this->items as $cond_object ) {
			$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
			echo "\n\t", $this->single_row( $cond_object, $style );
		}
	}

	/**
	 * Generate HTML for a single row on the PP Role Groups admin panel.
	 *
	 * @param object $user_object
	 * @param string $style Optional. Attributes added to the TR element.  Must be sanitized.
	 * @param int $num_users Optional. User count to display for this group.
	 * @return string
	 */
	function single_row( $cond_obj, $style = '' ) {
		//$cond_obj = sanitize_user_object( $cond_obj, 'display' );
		
		static $base_url;
		static $disabled_conditions;
		
		$attrib = $this->attribute;
		$attrib_type = $this->attrib_type;
		
		if ( ! isset($base_url) ) {
			$base_url = apply_filters( 'pp_conditions_base_url', 'admin.php' );
			$disabled_conditions = pp_get_option( "disabled_{$attrib}_conditions" );
		}

		$cond = $cond_obj->name;

		// Set up the hover actions for this user
		$actions = array();
		$checkbox = '';
		
		static $can_manage_cond;
		if ( ! isset($can_manage_cond) )
			$can_manage_cond = current_user_can( 'pp_define_post_status' );
		
		// Check if the group for this row is editable
		if ( $can_manage_cond && ! in_array( $cond, array( 'private', 'future' ) ) && empty( $disabled_conditions[$cond] ) ) {
			$edit_link = $base_url . "?page=pp-status-edit&amp;action=edit&amp;status={$cond}";
			$edit = "<strong><a href=\"$edit_link\">$cond_obj->label</a></strong><br />";
			$actions['edit'] = '<a href="' . $edit_link . '">' . _pp_( 'Edit' ) . '</a>';
		} else {
			$edit = '<strong>' . $cond_obj->label . '</strong>';
		}
		
		if ( in_array( $cond, array( 'pending', 'future' ) ) ) {
			if ( ! pp_get_option( "custom_{$cond}_caps" ) )
				$actions['enable'] = "<a class='submitdelete' href='" . wp_nonce_url( $base_url . "?page=pp-stati&amp;pp_action=enable&amp;attrib_type=$attrib_type&amp;status=$cond", 'bulk-conditions' ) . "'>" . __( 'Custom Capabilities', 'pps' ) . "</a>";
			else
				$actions['disable'] = "<a class='submitdelete' href='" . wp_nonce_url( $base_url . "?page=pp-stati&amp;pp_action=disable&amp;attrib_type=$attrib_type&amp;status=$cond", 'bulk-conditions' ) . "'>" . __( 'Standard Capabilities', 'pps' ) . "</a>";
		} elseif ( $cond && empty( $cond_obj->builtin ) ) {
			if ( ! empty( $disabled_conditions[$cond] ) )
				$actions['enable'] = "<a class='submitdelete' href='" . wp_nonce_url( $base_url . "?page=pp-stati&amp;pp_action=enable&amp;attrib_type=$attrib_type&amp;status=$cond", 'bulk-conditions' ) . "'>" . __( 'Enable', 'pps' ) . "</a>";
			else
				$actions['disable'] = "<a class='submitdelete' href='" . wp_nonce_url( $base_url . "?page=pp-stati&amp;pp_action=disable&amp;attrib_type=$attrib_type&amp;status=$cond", 'bulk-conditions' ) . "'>" . __( 'Disable', 'pps' ) . "</a>";
		} else
			$actions[''] = '&nbsp;';  // temp workaround to prevent shrunken row

		if ( ! empty($cond_obj->pp_custom) || ( ( 'moderation' == $attrib_type ) && ! in_array( $cond, array( 'draft', 'pending', 'pitch' ) ) && get_term_by( 'slug', $cond, 'post_status' ) ) ) {  // post_status taxonomy: Edit Flow integration
			$actions['delete'] = "<a class='submitdelete' href='" . wp_nonce_url( $base_url . "?page=pp-stati&amp;pp_action=delete&amp;attrib_type=$attrib_type&amp;status=$cond", 'bulk-conditions' ) . "'>" . __( 'Delete' ) . "</a>";
		}
		
		$actions = apply_filters( 'pp_condition_row_actions', $actions, $attrib, $cond_obj );
		$edit .= $this->row_actions( $actions );
		
		// Set up the checkbox ( because the group or group members are editable, otherwise it's empty )
		if ( $actions )
			$checkbox = "<input type='checkbox' name='pp_conditions[]' id='pp_condition_{$cond}' value='{$cond}' />";
		else
			$checkbox = '';
			
		$r = "<tr $style>";

		list( $columns, $hidden ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			$class = "class=\"$column_name column-$column_name\"";

			$style = '';
			if ( in_array( $column_name, $hidden ) )
				$style = ' style="display:none;"';

			$attributes = "$class$style";

			switch ( $column_name ) {
				case 'cb':
					$r .= "<th scope='row' class='check-column'>$checkbox</th>";
					break;
				case 'status':
					$r .= "<td $attributes>$edit</td>";
					break;
				case 'order':
					$order = ( isset($cond_obj->order) ) ? $cond_obj->order : '';
					$r .= "<td $attributes>$order</td>";
					break;
				case 'post_types':
					if ( ! empty( $cond_obj->post_type ) ) {
						$arr_captions = array();
						foreach( $cond_obj->post_type as $_post_type ) {
							if ( $type_obj = get_post_type_object( $_post_type ) ) {
								$arr_captions []= $type_obj->labels->singular_name;
							}
						}
						
						$types_caption = implode( ', ', array_slice( $arr_captions, 0, 7 ) );
						
						if ( count($arr_captions) > 7 )
							$types_caption = sprintf( __( '%s, more...', 'pps' ), $types_caption );
					} else
						$types_caption = __( 'All' );
					
					$r .= "<td $attributes>$types_caption</td>";
					break;
				case 'cap_map':
					$maps = array();
					if ( ! empty($cond_obj->metacap_map) ) {
						foreach( $cond_obj->metacap_map as $orig => $map )
							$maps []= $orig . ' > ' . $map;							
					}
					if ( ! empty($cond_obj->cap_map) ) {
						foreach( $cond_obj->cap_map as $orig => $map )
							$maps []= $orig . ' > ' . $map;							
					}
					$r .= "<td $attributes><ul><li>" . implode('</li><li>', $maps) . "</li></ul></td>";
					break;
				case 'enabled':
					if ( in_array( $cond, array( 'pending', 'future' ) ) ) {
						$caption = ( pp_get_option( "custom_{$cond}_caps" ) ) ? __('Enabled (custom capabilities)', 'pps') : __('Enabled (standard capabilities)', 'pps');
					} else {
						$caption = ( ! empty( $disabled_conditions[$cond] ) ) ? __('Disabled', 'pps') : __('Enabled', 'pps');
					}
					
					$r .= "<td $attributes>$caption</td>";
					break;
				default:
					$r .= "<td $attributes>";
					$r .= apply_filters( 'pp_manage_pp_conditions_custom_column', '', $column_name, $attrib, $cond );
					$r .= "</td>";
			}
		}
		$r .= '</tr>';

		return $r;
	}
}
