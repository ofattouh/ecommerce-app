<?php

/*
 * Plugin Name: WooCommerce PSHSA CRM
 * Description: Add new facilitators from the PSHSA CRM spread sheet into woo commerce database
 * Version: 1.0.1
 * Author: Omar M.
 * Author URI: http://www.pshsa.ca/
 * Copyright: (c) 2016 PSHSA
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Hook into word press
//add_action( 'init', 'process_spreadsheet');

/**
 * @since 1.0.1
 * Process the CRM spread sheet
*/
function process_spreadsheet(){
	
	$spread_sheet = "Book1.csv";
	$attribute    = "pa_facilitator";   // taxonomy

	// Sanity check
	if ( ! file_exists(plugin_dir_path( __FILE__ ).$spread_sheet) || ! is_readable(plugin_dir_path( __FILE__ ).$spread_sheet) ) :
		exit;
	endif;
	
	$output = readCSVFile(plugin_dir_path( __FILE__ ).$spread_sheet);   // Read the CRM spread sheet 
	insert_new_terms_crm($output, $attribute);    						// Add new terms if any
}

/**
 * @since 1.0.1
 * Read from the CRM spread sheet
*/
function readCSVFile($file_name){
	$handle = fopen($file_name, "r") or die("Unable to open $file_name!");
	$i = 0;
	
	if ($handle) {
	    while (($buffer = fgets($handle, filesize($file_name))) !== false) {
	        $oneline = explode(",", $buffer);
	       	$lines["fullname"][$i] = $oneline[0]." ".$oneline[1];
	       	$lines["email"][$i]    = $oneline[2];
	       	$i++;
	    }
	    if (!feof($handle)) {
	        debug_msg("Error: can not read from ".$file_name);
	    }
	    fclose($handle);
	}
	
	return $lines;
}

/** 
 * @since 1.0.1
 * Add/update the terms to woo commerce DB product attributes
*/
function insert_new_terms_crm($output, $taxomomy){
	
	debug_msg('==================Start of Facilitators Report=========================');
	
	for ($i=0;$i<sizeof($output['fullname']);$i++){
		
		$slug        = strtolower(str_replace(" ","-",$output['fullname'][$i]));
		$description = $output['email'][$i];
		$term        = term_exists($output['fullname'][$i], $taxomomy);
		
		// Update the existing facilitator description field with the email
		if ($term !== 0 && $term !== null) {
		  	$term_taxonomy_obj = get_term_by('slug', $slug, $taxomomy);
		  	
		  	if ( $term_taxonomy_obj->term_id != '' ){
			  	wp_update_term($term_taxonomy_obj->term_id, $taxomomy, array(
				  	'description' => $description
				));
				
				$msg = "Existing Facilitator ID: ".$term_taxonomy_obj->term_id.",Fullname: ".$output['fullname'][$i].",slug: ".$slug.", description: ".$description;
				//echo "<br><br>".$msg;
			 	debug_msg($msg);
			} 	
		}
		else{
			wp_insert_term(   				// Add the new term
				  $output['fullname'][$i], 	// the term 
				  $taxomomy, 				// the taxonomy
				  array(
				    'description'=> $description,
				    'slug' => $slug
				  )
			);
			
			$msg = "New Facilitator: ".$output['fullname'][$i].",slug: ".$slug.", description: ".$description;
			//echo "<br><br>".$msg;
		 	debug_msg($msg);
		 	
 			get_terms_woo_db($taxomomy, 'orderby=name&hide_empty=0');  // Debug
		}
	}
	
	debug_msg('==================End of Facilitators Report=========================');
}


/**
 * @since 1.0.1
 * get the woo commerce terms from the DB 
*/
function get_terms_woo_db($taxomomy, $args){
	$all_terms = get_terms($taxomomy, $args );
	if ( ! empty( $all_terms ) && ! is_wp_error( $all_terms ) ){
		debug_msg('List of Facilitators in the DB:');
		foreach ( $all_terms as $term ) {
			debug_msg(esc_attr( $term->slug ).': '.$term->name);
		}
	}
}

/**
 * Add a debug log message to the debug file located in [plugin folder]
 * @since 1.0.1
 */
function debug_msg( $message ) {
	$log = fopen( plugin_dir_path( __FILE__ ) . 'log.txt', 'a+' );
	$message = '[' . date( 'd-m-Y H:i:s' ) . '] ' . $message . PHP_EOL;
	fwrite( $log, $message );
	fclose( $log );
}