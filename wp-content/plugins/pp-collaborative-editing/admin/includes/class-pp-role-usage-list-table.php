<?php
require_once( dirname(__FILE__).'/role-usage-query_ppce.php' );

class PPCE_Role_Usage_List_Table extends WP_List_Table {
	var $site_id;
	var $role_info;

	function __construct() {
		$screen = get_current_screen();

		// clear out empty entry from initial admin_header.php execution
		global $_wp_column_headers;
		if ( isset( $_wp_column_headers[ $screen->id ] ) )
			unset( $_wp_column_headers[ $screen->id ] );
			
		parent::__construct( array(
			'singular' => 'role',
			'plural'   => 'roles'
		) );
	}
	
	function ajax_user_can() {
		return current_user_can( 'pp_manage_settings' );
	}

	function prepare_items() {
		// Query the user IDs for this page
		$search = new PPCE_Role_Usage_Query();

		$this->items = $search->get_results();
		
		$this->set_pagination_args( array(
			'total_items' => $search->get_total(),
			//'per_page' => $groups_per_page,
		) );
	}

	function no_items() {
		_e( 'No matching roles were found.', 'pp' );
	}

	function get_views() {
		return array();
	}

	function get_bulk_actions() {
		return array();
	}

	function get_columns() {
		$c = array(
			'role_name'  => _pp_( 'Role' ),
			'usage' => __( 'Usage', 'pp' ),
		);

		return $c;
	}

	function get_sortable_columns() {
		$c = array(
			//'usage' => 'usage',
		);

		return $c;
	}

	function display_rows() {
		$style = '';
		
		foreach ( $this->items as $role_object ) {
			$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
			echo "\n\t", $this->single_row( $role_object, $style );
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
	function single_row( $role_obj, $style = '' ) {
		static $base_url;
		
		$role_name = $role_obj->name;

		// Set up the hover actions for this user
		$actions = array();
		$checkbox = '';
		
		static $can_manage;
		if ( ! isset($can_manage) )
			$can_manage = current_user_can( 'pp_manage_settings' );
		
		// Check if the group for this row is editable
		if ( $can_manage ) {
			$edit_link = $base_url . "?page=pp-role-usage-edit&amp;action=edit&amp;role={$role_name}";
			$edit = "<strong><a href=\"$edit_link\">{$role_obj->labels->singular_name}</a></strong><br />";
			$actions['edit'] = '<a href="' . $edit_link . '">' . _pp_( 'Edit' ) . '</a>';
		} else {
			$edit = '<strong>' . $role_obj->labels->name . '</strong>';
		}

		$actions[''] = '&nbsp;';  // temp workaround to prevent shrunken row

		$actions = apply_filters( 'pp_role_usage_row_actions', $actions, $role_obj );
		$edit .= $this->row_actions( $actions );

		$r = "<tr $style>";

		list( $columns, $hidden ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			$class = "class=\"$column_name column-$column_name\"";

			$style = '';
			if ( in_array( $column_name, $hidden ) )
				$style = ' style="display:none;"';

			$attributes = "$class$style";

			switch ( $column_name ) {
				case 'role_name':
					$r .= "<td $attributes>$edit</td>";
					break;
				case 'usage':
					switch( $role_obj->usage ) {
						case 'direct':
							$caption = __('Direct Assignment', 'pp');
							break;
						default:
							$caption = ( empty( $role_obj->usage ) ) ? __('no supplemental assignment', 'pp') : __('Pattern Role', 'pp');
					}
					$r .= "<td $attributes>$caption</td>";
					break;
				default:
					$r .= "<td $attributes>";
					$r .= apply_filters( 'pp_manage_pp_role_usage_custom_column', '', $column_name, $role_obj );
					$r .= "</td>";
			}
		}
		$r .= '</tr>';

		return $r;
	}
}
