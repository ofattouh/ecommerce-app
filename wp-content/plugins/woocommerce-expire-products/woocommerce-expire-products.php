<?php 
/*
 * Plugin Name: WooCommerce Expire Products
 * Description: Expire the woo commerce products after seven days of the start date
 * Version: 1.0
 * Author: Omar M.
 * Author URI: http://www.pshsa.ca/
 * Copyright: (c) 2016 PSHSA 
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

//Increase the maximum time the script is allowed to run: 15 minutes
ini_set ( 'max_execution_time', 900);

//On plugin activate add the cron job
register_activation_hook( __FILE__, 'process_woocommerce_products_activation' );

//On plugin deactivate remove the cron job
register_deactivation_hook( __FILE__ , 'process_woocommerce_products_deactivate' );


//add_action( 'init', 'process_woo_products_hook' );                              //Manual
add_action( 'woocommerce_products_custom_hook', 'process_woo_products_hook' );    //Cron job


/*
 * Process the hook
*/
function process_woo_products_hook() {
	
	global $wpdb;
	
	$email_msg     = '';
	$can_send_mail = 0;
	
	//Fetch all published woo commerce products that belong to the training parent category
	$query_woo_products = "
		SELECT * FROM $wpdb->posts as wpost
		INNER JOIN $wpdb->term_relationships ON (wpost.ID = $wpdb->term_relationships.object_id)
		INNER JOIN $wpdb->term_taxonomy ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id)
		INNER JOIN $wpdb->terms ON ($wpdb->term_taxonomy.term_id = $wpdb->terms.term_id)
		where $wpdb->term_taxonomy.taxonomy = 'product_cat'
		AND wpost.post_status = 'publish'
		AND $wpdb->terms.name = 'Training'
		AND $wpdb->terms.slug = 'training'
		AND wpost.post_type = 'product'
		ORDER BY wpost.post_date DESC
	 ";
	 
	$training_products = $wpdb->get_results($query_woo_products, OBJECT);

	foreach ($training_products as $k => $v){

		$woo_product = wc_get_product($v->ID);
		
		$start_date_terms = get_the_terms( $v->ID, 'pa_start-date' );
		
		//I: Check for simple products and valid sessions start date
		if( $woo_product->is_type( 'simple' ) && isset($start_date_terms[0]->name) 
			&& ! in_array('blended', get_my_product_terms($v->ID, 'pa_training')) ){
			date_default_timezone_set('America/New_York');
			$start_date_obj   = new DateTime($start_date_terms[0]->name);
			$current_date_obj = new DateTime('now');
			$start_date_obj->modify("-2 days");
			$interval_obj   = $start_date_obj->diff($current_date_obj);
			$number_of_days = $interval_obj->format('%r%a');
			
// 			echo '<br>start: '.$start_date_terms[0]->name.', number_of_days: '.$number_of_days.'<br>';
// 			echo '<br>now:  ';
// 			print_r($current_date_obj);
// 			echo '<br>cutoff:  ';
// 			print_r($start_date_obj);
// 			echo '<br>';
			
			if ($number_of_days < 0 || ($start_date_obj->getTimestamp() - $current_date_obj->getTimestamp()) > 0 ):
				//echo 'You can buy';
			elseif (0 <= $number_of_days && $number_of_days <= 2):
				//echo 'Only view the course!';
			elseif($number_of_days > 2):         //Unpublish these courses				
				$my_updated_post_id = wp_update_post( array('ID' => $v->ID, 'post_status' => 'draft') , true);
				
				//Debug Log file
				if (is_wp_error($v->ID)):
					$errors = $v->ID->get_error_messages();
					foreach ($errors as $error) {
						debug_woo_custom_log( "DB Error: Product #: ".$v->ID." status is not updated!" );
						debug_woo_custom_log( $error );
					}
				endif;
				
				if ($my_updated_post_id > 0):
					debug_woo_custom_log( "- Product #: ". $my_updated_post_id ." with start date: ".$start_date_terms[0]->name.
								" status is changed to draft for Post #: ".$v->ID );
								
					$can_send_mail = 1;			
					$email_msg .= "\r\n\r\n- Product #: ". $my_updated_post_id ." with start date: ".$start_date_terms[0]->name.
								" status is changed to draft for Post #: ".$v->ID;
				endif;
				
			endif;
		} // end if
		
		// II: Check for simple blended and molextended elearning products
		if( $woo_product->is_type( 'simple' ) && in_array('blended', get_my_product_terms($v->ID, 'pa_training')) 
				&& in_array('molextended', get_my_product_terms($v->ID, 'pa_training')) && check_for_elearning_simple_product($v->ID) 
				&& in_array('sales-type-indicator-regular', get_my_product_terms($v->ID, 'pa_training')) ){
			//echo "<br><br>elearning blended=".$v->ID.", parent: ".$woo_product->post->post_parent."<br><br>";
			
			$can_buy_blended_sessions = array();
			
			// Get the parent blended grouped product for each elearning blended simple product and loop through only all of its published 
			// non elearning simple products children sessions
			foreach(wc_get_product(get_post_meta($v->ID, 'product_custom_parent_id', true))->get_children() as $childk => $childv){
				
				if ( wc_get_product($childv)->get_status() == 'publish' && $childv != $v->ID ){
					
					$start_date_terms_blended = get_the_terms( $childv, 'pa_start-date' );  //Get each session start date
					
					if (isset($start_date_terms_blended[0]->name)){
						
						//Determine the session visiblity according to the session cut off date
						date_default_timezone_set('America/New_York');
						$srt_date_obj_blended = new DateTime($start_date_terms_blended[0]->name);
						$cur_date_obj_blended = new DateTime('now');
						$srt_date_obj_blended->modify("-2 days");
						$interval_obj_blended  = $srt_date_obj_blended->diff($cur_date_obj_blended);
						$number_of_days_blended = $interval_obj_blended->format('%r%a');
						//$action   = "N/A";
						
						if ($number_of_days_blended < 0 || ($srt_date_obj_blended->getTimestamp() - $cur_date_obj_blended->getTimestamp()) > 0 ):
							//$action = "buy";
							$can_buy_blended_sessions[$childv] = "buy";
						elseif (0 <= $number_of_days_blended && $number_of_days_blended <= 2):
							//$action = "view";
						elseif($number_of_days_blended > 2):
							//$action = "hide";
						endif;
						
// 						echo "<br><br>".$childk."=>".$childv;
// 			 			echo '<br>start date: '.$start_date_terms_blended[0]->name.', number_of_days: '.$number_of_days_blended.'<br>';
// 			 			echo '<br>now:  ';
// 			 			print_r($cur_date_obj_blended);
// 			 			echo '<br>cutoff:  ';
// 			 			print_r($srt_date_obj_blended);
// 						echo '<br>action: ';
// 			 			echo $action;
					
					} // end if
				} // end if
			} // end foreach
			
			// Show the add to cart button in the blended grouped product page
			if (sizeof($can_buy_blended_sessions) > 0){ 
				//print_r($can_buy_blended_sessions);
			}
			else{  // Hide it
				$updated_post_blended_elearning_id = wp_update_post( array('ID' => $v->ID, 'post_status' => 'draft') , true);
				
				if ($updated_post_blended_elearning_id > 0){
					debug_woo_custom_log( "- eLearning Blended Product #: ".$updated_post_blended_elearning_id.
						" status is changed to draft for Post #: ".$v->ID );
								
					$can_send_mail = 1;			
					$email_msg .= "\r\n\r\n- eLearning Blended Product #: ".$updated_post_blended_elearning_id.
						" status is changed to draft for Post #: ".$v->ID;
				}
			}
		} // end if	
	} // end foreach

	
	//Only send an email if there are expired products
	if ( $can_send_mail == 1 ):
		if(send_expire_products_email( $email_msg )) :
			debug_woo_custom_log( "Email sent successfully to PSHSA CC team" );
		else:
			debug_woo_custom_log( "Error sending email. Contact your system admin!" );
		endif;
	endif;
	
}

function process_woocommerce_products_activation() {
	//wp_schedule_event( time(), 'hourly', 'woocommerce_products_custom_hook' );
	wp_schedule_event( time(), 'daily', 'woocommerce_products_custom_hook' );
}

function process_woocommerce_products_deactivate() {
    wp_clear_scheduled_hook( 'woocommerce_products_custom_hook' );      
}

/**
 * Debug woo commerce products log
 * Add a debug log message to the debug file located in [plugin folder]/logs/.
 */
function debug_woo_custom_log( $message ) {
	$log = fopen( plugin_dir_path( __FILE__ ) . '/logs/debug.txt', 'a+' );
	$message = '[' . date( 'd-m-Y H:i:s' ) . '] ' . $message . PHP_EOL;
	fwrite( $log, $message );
	fclose( $log );
}

/**
 * Send an email with the expired products
 */
 
function send_expire_products_email($body){
	
	$is_email_sent = false;
	$msg  = "Hello Customer Care Team,\r\n\r\n";
	$msg .= "Below is the list of the newly expired Woo Commerce Products:".$body."\r\n\r\nPSHSA Web Master";
	$to   = array("omohamed@pshsa.ca, customerservice@pshsa.ca");
	
	$subject = "PSHSA Expired Woo Commerce Products For: ".date('d-m-Y');
	
	$is_email_sent = wp_mail($to, $subject, $msg);
	return $is_email_sent;
}

/*
 **
 * Fetch all the terms/attributes for the product
*/
function get_my_product_terms($product_id, $attribute){
	$term_Values_Arr = array();
	$term_Values     = get_the_terms( $product_id, $attribute );
	
	if ( $term_Values && ! is_wp_error($term_Values) ) :
		foreach($term_Values as $oneterm){
			$term_Values_Arr[] = $oneterm->name;
		}
	endif;
	
	return $term_Values_Arr;
}

/*
 ** 
 * Check for elearning product
*/
function check_for_elearning_simple_product($postID){
	$found = false;
	
	$productCategories = wp_get_post_terms( $postID, 'product_cat' );
	
	foreach ($productCategories as $k => $v) :
		if ($v->name == 'eLearning') :
			$found = true;
		endif;
	endforeach;
	
	return $found;
}