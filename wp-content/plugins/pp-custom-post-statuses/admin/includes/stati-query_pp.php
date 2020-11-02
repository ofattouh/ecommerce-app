<?php

class PP_Attribute_Query {
	var $attribute;
	var $attrib_type;
	
	/**
	 * List of found group ids
	 *
	 * @access private
	 * @var array
	 */
	var $results;

	/**
	 * Total number of found groups for the current query
	 *
	 * @access private
	 * @var int
	 */
	var $total_groups = 0;

	/**
	 * PHP5 constructor
	 *
	 * @param string|array $args The query variables
	 * @return WP_Group_Query
	 */
	function __construct( $attribute, $attrib_type, $query = null ) {
		$this->attribute = $attribute;
		$this->attrib_type = $attrib_type;
		
		//if ( !empty( $query ) ) {
			global $blog_id;
		
			$this->query_vars = wp_parse_args( $query, array(
				'blog_id' => $blog_id,
				'include' => array(),
				'exclude' => array(),
				'search' => '',
				'orderby' => 'login',
				'order' => 'ASC',
				'offset' => '', 'number' => '',
				'count_total' => true,
				'fields' => 'all',
			) );

			$this->prepare_query();
			$this->query();
		//}
	}

	function prepare_query() {
	}

	/**
	 * Execute the query, with the current variables
	 *
	 * @since 3.1.0
	 * @access private
	 */
	function query() {
		//pp_attributes = pps_init_attributes();
		global $pp_attributes;
	
		//if ( 'post_status' == $this->attribute ) {
		
			$args = array( $this->attrib_type => true );
			$this->results = get_post_stati( $args, 'object' );
			
			foreach( array_keys($this->results) as $cond ) {
				if ( ! empty( $pp_attributes->attributes['post_status']->conditions[$cond]->metacap_map ) )
					$this->results[$cond]->metacap_map = $pp_attributes->attributes['post_status']->conditions[$cond]->metacap_map;
					
				if ( ! empty( $pp_attributes->attributes['post_status']->conditions[$cond]->cap_map ) )
					$this->results[$cond]->cap_map = $pp_attributes->attributes['post_status']->conditions[$cond]->cap_map;
			}
		//} else {
		//	$this->results = $pp_attributes->attributes[ $this->attribute ]->conditions;
		//}

		$custom_conditions = (array) get_option( "pp_custom_conditions_{$this->attribute}" );
		foreach ( $this->results as $index => $row ) {
			$this->results[$index]->builtin = ! empty( $row->_builtin );
		}
		
		// list in moderation order
		if ( 'moderation' == $this->attrib_type ) {
			$moderation_order = array();

			foreach( $this->results as $status => $status_obj ) {
				$order =  ( isset($status_obj->order) ) ? $status_obj->order : 100;

				if ( ! isset( $moderation_order[$order] ) )
					$moderation_order[$order] = array();
				
				$moderation_order[$order][$status] = $status_obj;
			}
			
			ksort($moderation_order);
			
			$results = array();
			foreach( array_keys($moderation_order) as $_order_key ) {
				foreach( $moderation_order[$_order_key] as $status => $status_obj )
					$results[$status] = $status_obj;
			}
			$this->results = $results;
		} else {
			$this->results = apply_filters( 'pp_order_types', $this->results, array( 'order_property' => 'label' ) );
		}
		
		$this->total_groups = count($this->results);
	}

	/*
	 * Used internally to generate an SQL string for searching across multiple columns
	 *
	 * @access protected
	 * @since 3.1.0
	 *
	 * @param string $string
	 * @param array $cols
	 * @param bool $wild Whether to allow wildcard searches. Default is false for Network Admin, true for
	 *  single site. Single site allows leading and trailing wildcards, Network Admin only trailing.
	 * @return string
	 */
	function get_search_sql( $string, $cols, $wild = false ) {
	}

	/**
	 * Return the list of groups
	 *
	 * @access public
	 *
	 * @return array
	 */
	function get_results() {
		return $this->results;
	}

	/**
	 * Return the total number of groups for the current query
	 *
	 * @access public
	 *
	 * @return array
	 */
	function get_total() {
		return $this->total_groups;
	}
}

