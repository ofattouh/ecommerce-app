<?php
class PPCE_PostTermsSave {
	public static function get_object_terms($object_ids, $taxonomy, $args = array()) {
		global $wpdb;

		if ( empty( $object_ids ) || ! $taxonomy )
			return array();

		if ( !is_array($object_ids) )
			$object_ids = array($object_ids);
		$object_ids = array_map('intval', $object_ids);

		$defaults = array(
			'fields'  => 'all',
			'parent'  => '',
		);
		$args = wp_parse_args( $args, $defaults );

		$terms = array();

		$t = get_taxonomy($taxonomy);
		if ( isset($t->args) && is_array($t->args) )
			$args = array_merge($args, $t->args);

		$fields = $args['fields'];

		$object_id_array = $object_ids;
		$object_ids = implode(', ', $object_ids);

		$select_this = '';
		if ( 'all' == $fields ) {
			$select_this = 't.*, tt.*';
		} elseif ( 'ids' == $fields ) {
			$select_this = 't.term_id';
		} elseif ( 'names' == $fields ) {
			$select_this = 't.name';
		}

		$where = array(
			"tt.taxonomy = '$taxonomy'",
			"tr.object_id IN ($object_ids)",
		);

		$where = implode( ' AND ', $where );

		$query = "SELECT $select_this FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON tt.term_id = t.term_id INNER JOIN $wpdb->term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE $where";

		$objects = false;
		if ( 'all' == $fields ) {
			$_terms = $wpdb->get_results( $query );
			$object_id_index = array();
			foreach ( $_terms as $key => $term ) {
				$term = sanitize_term( $term, $taxonomy, 'raw' );
				$_terms[ $key ] = $term;

				if ( isset( $term->object_id ) ) {
					$object_id_index[ $key ] = $term->object_id;
				}
			}

			$terms = array_merge( $terms, $_terms );
			$objects = true;

		} elseif ( 'ids' == $fields || 'names' == $fields || 'slugs' == $fields ) {
			$_terms = $wpdb->get_col( $query );
			$_field = ( 'ids' == $fields ) ? 'term_id' : 'name';
			foreach ( $_terms as $key => $term ) {
				$_terms[$key] = sanitize_term_field( $_field, $term, $term, $taxonomy, 'raw' );
			}
			$terms = array_merge( $terms, $_terms );
		} elseif ( 'tt_ids' == $fields ) {
			$terms = $wpdb->get_col( "SELECT tr.term_taxonomy_id FROM $wpdb->term_relationships AS tr INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tr.object_id IN ($object_ids) AND tt.taxonomy = '$taxonomy'" );
		}

		if ( ! $terms ) {
			$terms = array();
		} elseif ( $objects ) {
			$_tt_ids = array();
			$_terms = array();
			foreach ( $terms as $term ) {
				if ( in_array( $term->term_taxonomy_id, $_tt_ids ) ) {
					continue;
				}

				$_tt_ids[] = $term->term_taxonomy_id;
				$_terms[] = $term;
			}
			$terms = $_terms;
		} else {
			$terms = array_values( array_unique( $terms ) );
		}
		
		return $terms;
	}
	
	public static function get_posted_object_terms( $taxonomy ) {
		if ( defined('XMLRPC_REQUEST') ) {
			require_once( dirname(__FILE__).'/filters-admin-xmlrpc_ppce.php' );
			return PPCE_XMLRPC_Post::get_posted_xmlrpc_terms( $taxonomy );
		}

		if ( 'category' == $taxonomy ) {
			if ( ! empty($_POST['post_category']) )
				return $_POST['post_category'];
		} else {
			$tx_obj = get_taxonomy( $taxonomy );
			if ( $tx_obj &&	! empty( $tx_obj->object_terms_post_var ) ) {
				if ( isset( $_POST[ $tx_obj->object_terms_post_var ] ) )
					return $_POST[ $tx_obj->object_terms_post_var ];
			} elseif ( ! empty($_POST['tax_input'][$taxonomy]) ) {
				if ( is_taxonomy_hierarchical($taxonomy) && is_array($_POST['tax_input'][$taxonomy]) ) {
					return $_POST['tax_input'][$taxonomy];
				} else {
					$term_info = self::parse_term_names( $_POST['tax_input'][$taxonomy], $taxonomy );
					return array_map( 'intval', self::flt_pre_object_terms( $term_info['terms'], $taxonomy ) );
				}
			} elseif ( 'post_tag' == $taxonomy && ! empty($_POST['tags_input']) ) {
				$term_info = self::parse_term_names( $_POST['tags_input'], $taxonomy );
				return array_map( 'intval', self::flt_pre_object_terms( $term_info['terms'], $taxonomy ) );
			}
		}
		
		return array();
	}
	
	public static function flt_tax_input( $tax_input ) {
		foreach( (array) $tax_input as $taxonomy => $terms ) {
			$enabled_taxonomies = pp_get_enabled_taxonomies();
			
			if ( ! in_array( $taxonomy, $enabled_taxonomies ) )
				continue;
			
			if ( is_string($terms) || ( pp_wp_ver( '4.2' ) && ! is_taxonomy_hierarchical( $taxonomy ) ) ) {  // non-hierarchical taxonomy (tags)
				if ( is_string($terms) ) {
					$term_info = self::parse_term_names( $terms, $taxonomy );
					extract( $term_info ); // $terms, $names_by_id and $new_terms
				} else {
					// WP 4.2 tax_input['post_tag'] is an array, but with existing terms as numeric IDs and new terms as submitted names
					$term_ids = array();
					$names_by_id = array();
					$new_terms = array();
					foreach( $terms as $_term ) {
						if ( is_string( $_term ) ) {
							$term_info = self::parse_term_names( (array) $_term, $taxonomy );
							
							if ( ! empty( $term_info['terms'] ) ) {
								$term_id = reset( $term_info['terms'] );
								$term_ids[] = $term_id;
								
								if ( ! empty( $term_info['names_by_id'] ) )
									$names_by_id[$term_id] = reset( $term_info['names_by_id'] );
							} else {
								$new_terms []= reset( $term_info['new_terms'] );
							}
						} else {
							$term_ids[]= $_term;
						}
					}
					
					$terms = $term_ids;
				}
				
				// if term assignment is limited to a fixed set, ignore any attempt to assign a newly created term
				global $pp_current_user;
				if ( $pp_current_user->get_exception_terms( 'assign', 'include', pp_find_post_type(), $taxonomy ) || $pp_current_user->get_exception_terms( 'assign', 'include', '', $taxonomy ) ) {
					$new_terms = array();
				}

				$filtered_terms = self::flt_pre_object_terms( $terms, $taxonomy );

				// names_by_id returned from parse_term_names() includes only selected terms, not default or alternate terms which may have been filtered in
				foreach( $filtered_terms as $term_id ) {
					if ( ! isset( $names_by_id[$term_id] ) ) {
						if ( $term = get_term_by( 'id', $term_id, $taxonomy ) )
							$names_by_id[$term->term_id] = $term->name;
					}
				}

				$tax_input[$taxonomy] = implode( ",", array_merge( array_intersect_key( $names_by_id, array_flip($filtered_terms) ), $new_terms ) );
			} else {
				$tax_input[$taxonomy] = self::flt_pre_object_terms( $terms, $taxonomy );
			}
		}
		
		return $tax_input;
	}
	
	public static function parse_term_names( $names, $taxonomy ) {
		$arr_names = ( is_array($names) ) ? $names : explode( ",", $names );
		
		$names_by_id = $terms = array();
		$new_terms = array();
		
		// convert tag names to ids for filtering
		foreach( $arr_names as $term_name ) {
			if ( $term = get_term_by( 'name', $term_name, $taxonomy ) ) {
				$terms []= $term->term_id;
				$names_by_id[$term->term_id] = $term_name;
			} else {
				$new_terms []= $term_name;
			}
		}
		
		return compact( array( 'terms', 'names_by_id', 'new_terms' ) );
	}
	
	/*  // this is now handled by flt_pre_object_terms instead
	public static function flt_default_term( $default_term_id, $taxonomy = 'category' ) {
	}	
	*/

	public static function flt_pre_object_terms ($selected_terms, $taxonomy, $args = array()) {
		if ( ! defined( 'PP_ENABLE_QUERYFILTERS' ) || ! pp_is_taxonomy_enabled($taxonomy) )
			return $selected_terms;
		
		//pp_errlog( "_pp_flt_pre_object_terms input: " . serialize($selected_terms) );

		// strip out fake term_id -1 (if applied)
		if ( $selected_terms && is_array($selected_terms) )
			$selected_terms = array_diff($selected_terms, array(-1, 0, '0', '-1', ''));  // not sure who is changing empty $_POST['post_category'] array to an array with nullstring element, but we have to deal with that

		//if ( ! constant('PP_ENABLE_QUERYFILTERS') )
		//	return $selected_terms;
		
		if ( defined( 'RVY_VERSION' ) ) {
			global $revisionary;
			if ( ! empty($revisionary->admin->impose_pending_rev) )
				return $selected_terms;
		}
		
		$stored_terms = array();
		
		// don't filter selected terms for content administrator, but still need to apply default term as needed when none were selected
		if ( pp_unfiltered() ) {
			$user_terms = $selected_terms;
		} else {
			if ( ! is_array($selected_terms) )
				$selected_terms = array();

			$user_terms = get_terms( $taxonomy, array( 'fields' => 'ids', 'hide_empty' => false, 'required_operation' => 'assign', 'object_type' => pp_find_post_type() ) );
			
			$selected_terms = array_intersect( $selected_terms, $user_terms );
			
			if ( $object_id = pp_get_post_id() ) {
				$stored_terms = ppce_get_object_terms( $object_id, $taxonomy, array( 'fields' => 'ids', 'pp_no_filter' => true ) );
				
				if ( ! defined( 'PPCE_DISABLE_' . strtoupper( $taxonomy ) . '_RETENTION' ) ) {
					if ( $deselected_terms = array_diff( $stored_terms, $selected_terms ) ) {
						if ( $unremovable_terms = array_diff( $deselected_terms, $user_terms ) )
							$selected_terms = array_merge( $selected_terms, $unremovable_terms );
					}
				}
			}
		}
		
		//pp_errlog( "user terms: " . serialize($user_terms) );
		//pp_errlog( "selected terms: " . serialize($selected_terms) );
		
		if ( empty($selected_terms) && ( ( is_taxonomy_hierarchical($taxonomy) && ( 'post_tag' != $taxonomy ) ) || self::user_has_term_limitations( $taxonomy ) ) ) {
			if ( ! $tx_obj = get_taxonomy( $taxonomy ) )
				return $selected_terms;
		
			// For now, always check the DB for default terms.  TODO: only if the default_term_option property is set
			if ( isset( $tx_obj->default_term_option ) )
				$default_term_option = $tx_obj->default_term_option;
			else
				$default_term_option = "default_{$taxonomy}";

			// avoid recursive filtering.  @todo: use remove_filter so we can call get_option, support filtering by other plugins 
			global $wpdb;
			$default_terms = (array) maybe_unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s", $default_term_option ) ) );
			
			// but if the default term is not defined or is not in user's subset of usable terms, substitute first available
			if ( $user_terms ) {
				if ( true === $user_terms )
					$filtered_default_terms = $default_terms;
				else
					$filtered_default_terms = array_intersect( $default_terms, $user_terms );
				
				if ( $filtered_default_terms ) {
					$default_terms = $filtered_default_terms;

				} elseif ( is_array($user_terms) ) {
					if ( $default_terms || defined( 'PP_AUTO_DEFAULT_TERM' ) || defined( 'PP_AUTO_DEFAULT_' . strtoupper($taxonomy) ) ) { // always substitute 1st available if this constant is defined
						//if ( ! empty($tx_obj->requires_term)  )
						$default_terms = (array) $user_terms[0];
					} else {
						if ( ( count($user_terms) == 1 ) && ! defined( 'PP_NO_AUTO_DEFAULT_TERM' ) && ! defined( 'PP_NO_AUTO_DEFAULT_' . strtoupper($taxonomy) ) )
							$default_terms = (array) $user_terms[0];
						else
							$default_terms = array();
					}
				}
				
				$selected_terms = $default_terms;
			} elseif ( $stored_terms ) {
				$selected_terms = $stored_terms; // fallback is to currently stored terms
			}
		}
		
		if ( $selected_terms && ! is_taxonomy_hierarchical($taxonomy) && ( 'post_tag' != $taxonomy ) && ! empty($object_id) ) {
			wp_set_object_terms( $object_id, $selected_terms, $taxonomy );
		}

		//pp_errlog( "returning selected terms: " . serialize($selected_terms) );
		return $selected_terms;
	}
	
	static function user_has_term_limitations( $taxonomy, $mod_types = array('include'), $current_post_type = '' ) {
		global $pp_current_user;
		
		if ( ! $current_post_type )
			$current_post_type = pp_find_post_type();
		
		foreach( array_keys($pp_current_user->except) as $for_op ) {
			if ( in_array( $for_op, array( 'read_post', 'edit_term', 'manage_term' ) ) )  // only concerned about edit_post, revise_post, fork_post, etc.
				continue;
		
			foreach( array_keys( $pp_current_user->except[$for_op] ) as $via_src ) {
				if ( ( 'term' != $via_src ) || ! isset($pp_current_user->except[$for_op][$via_src][$taxonomy]) )  // only consider exceptions assigned via specified taxonomy
					continue;

				foreach( array_keys( $pp_current_user->except[$for_op][$via_src][$taxonomy] ) as $mod_type ) {
					if ( in_array( $mod_type, $mod_types ) ) {								  // only consider specified mod type(s)
						foreach( array_keys( $pp_current_user->except[$for_op][$via_src][$taxonomy][$mod_type] ) as $for_item_type ) {
							if ( ! in_array( $for_item_type, array( $current_post_type, '' ) ) )				  // only consider exceptions for current/specified post type
								continue;

							foreach( array_keys( $pp_current_user->except[$for_op][$via_src][$taxonomy][$mod_type] ) as $for_item_status ) {
								if ( ! empty( $pp_current_user->except[$for_op][$via_src][$taxonomy][$mod_type][$for_item_status] ) ) {
									return true;
								}
							}
						}
					}
				}
			}
		}

		return false;
	}
} // end class
