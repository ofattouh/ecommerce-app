<?php

	/****************************************************************************************************
	* PHP script to integrate Woo commerce with the CRM using API REST calls
	* Update a simple product that belongs to a grouped product with dynamic categories and attributes
	* End point: products
	* URL: https://github.com/woocommerce/wc-api-php
	* Ver: 1.0
	******************************************************************************************************/

	require '../wp-load.php';  // For word press & woo commerce functions to work - API library has to be on the same server as WP installtion
	require 'api-config.php';  // Woo commerce API config file
	
 	// Debug
//   	$_POST['apiurl']       = $api_url_config;
// 	$_POST['apikey']       = $consumer_key_api;
// 	$_POST['apisecret']    = $consumer_secret_api;
// 	$_POST['apiendpoints'] = $endpoints_api;
// 	$_POST['product_id']   = 46131;  // should be created earlier from the script: create-simple-product.php 
// 	$_POST['attributes']   = array(  
// 								 //'pa_facilitator'        => array('id' => 1,  'options' => array(esc_attr("''''Fac'il'itator: ". date('Y-m-d H:i:s')))),
// 								 
//  								 //'pa_course-location'    => array('id' => 2,  'options' => array('Course Location: '.date('Y-m-d H:i:s'))),
//  								 'pa_course-location'    => array('id' => 2,  'options' => array(esc_attr("'\\''''Best Eastern'''' Plus' Cairn Croft Hotel, 6400 Lundy's Lane, Niagara Falls, L2G 1T6"))),
// 								 //'pa_course-location'    => array('id' => 2,  'options' => array('Best Western St')),
//  								 
//  								 //'pa_course-city'        => array('id' => 3,  'options' => array('Course City: '.date('Y-m-d H:i:s'))),
//  								 //'pa_sector'             => array('id' => 5,  'options' => array("Sector'''': ".date('Y-m-d H:i:s'))),
//  								 //'pa_time'             => array('id' => 6,  'options' => array('8:30am - 4:30 pm')),
//  								 //'pa_length-of-course' => array('id' => 7,  'options' => array('2 days: '.date('Y-m-d H:i:s'))),
//  								 //'pa_region'             => array('id' => 8,  'options' => array('Region: '.date('Y-m-d H:i:s'))),
//  								 //'pa_start-date'         => array('id' => 9,  'options' => array('06/05/2018')),
// 	 							 //'pa_month'              => array('id' => 10, 'options' => array('December')),
// 	 							 //'pa_all-courses'        => array('id' => 11, 'options' => array('All Courses: '.date('Y-m-d H:i:s'))),
// 	 							 //'pa_training'         => array('id' => 12, 'options' => array('molextended', 'blended')), 
//  								 //'pa_training'           => array('id' => 12, 'options' => array('molextended', 'In-class Training')),
//  								 //'pa_training-category'  => array('id' => 13, 'options' => array('training')),
//  								 //'pa_room'               => array('id' => 15, 'options' => array('Room: '.date('Y-m-d H:i:s'))),
//  								 //'pa_session-info'       => array('id' => 16, 'options' => array('Session Info: '.date('Y-m-d H:i:s'))),
//  								 //'pa_customattrtrain'    => array('id' => 17, 'options' => array('molextendedcustomattrtrain', 'inclasscustomattrtrain')),
//  								 //'pa_customattrtrain'  => array('id' => 17, 'options' => array('molextendedcustomattrtrain', 'othercustomattrtrain')),
//  								 //'pa_session-id'         => array('id' => 18, 'options' => array('Session ID: '.date('Y-m-d H:i:s')))
//  								 // 'pa_session-id'      => array('id' => 18, 'visible' => true, 'options' => array('1010'))
//  	 							);

	require __DIR__ . '/vendor/autoload.php';
 	use Automattic\WooCommerce\Client;
 	
 	// Decode the JSON object and fetch the POST parameters
 	$_POST = json_decode(file_get_contents("php://input"), true);
 	
 	$api_url         = ( $_POST['apiurl'] != ''       && isset($_POST['apiurl']) )?       $_POST['apiurl']       : '';
	$consumer_key    = ( $_POST['apikey'] != ''       && isset($_POST['apikey']) )?       $_POST['apikey']       : '';
	$consumer_secret = ( $_POST['apisecret'] != ''    && isset($_POST['apisecret']) )?    $_POST['apisecret']    : '';
	$endpoints       = ( $_POST['apiendpoints'] != '' && isset($_POST['apiendpoints']) )? $_POST['apiendpoints'] : '';	
	
	// I: Validate API POST authenticatation parameters
	require 'api-auth.php';
	
	// II: Validate API POST parameters  
	if ( $_POST['product_id'] != '' && isset($_POST['product_id']) && sizeof($_POST['attributes']) > 0 ){
		 	
		 $woocommerce                  = new Client($api_url, $consumer_key, $consumer_secret, $options);
		 $product                      = $woocommerce->get( 'products/'.$_POST['product_id'] );
		 $attributes_post_data_options = [];
		 $attributes_post_data_visible = [];
		 $attributes_all_data   = []; 
		 $attributes_unique_ids = [];
		 $attribute_options     = []; 
		 $msg                   = '';
		  
		 // Fetch the new product attributes from the POST data
		 foreach ($_POST['attributes'] as $k => $v){
			 $attributes_post_data_options[$v['id']] = $v['options'];	
		 }
	 	
		 // Fetch the existing product attributes data against the new product post attributes
		 foreach ($product['attributes'] as $k => $v){
			 $attributes_post_data_options[$v['id']] = ( array_key_exists($v['id'], $attributes_post_data_options) ) ? $attributes_post_data_options[$v['id']] : $v['options'];
			 $attributes_post_data_visible[$v['id']] = $v['visible'];
		 }

		 // re-construct all the API product attributes data
		 foreach ($attributes_post_data_options as $k => $v){
			 foreach($v as $k2=>$v2){
				 if ( ! array_key_exists($k, $attributes_unique_ids) ){
				 	 $attributes_unique_ids[$k] = $v2;
				 	 $attribute_options[] = esc_attr($attributes_unique_ids[$k]);
			 	 }
			 	 else{
				 	 $attribute_options[] = esc_attr($v2);
			 	 }
		 	 }

		 	 $attributes_all_data[] = array( 'id' => $k , 'options' => $attribute_options, 'visible' => $attributes_post_data_visible[$k] );
		 	 $attribute_options     = []; 
		 }
		 
		 $data = [ 'attributes' => $attributes_all_data, ];
		 
		 // Fetch the inventory if any
		 $manage_stock        = ( $_POST['manage_stock'] != ''        && isset($_POST['manage_stock']) )        ? $_POST['manage_stock']        : '';
 		 $number_participants = ( $_POST['number_participants'] != '' && isset($_POST['number_participants']) ) ? $_POST['number_participants'] : '';
 		 
 		 if ($manage_stock && $number_participants){
		 	 $data = [ 'manage_stock' => $manage_stock, 'stock_quantity' => $number_participants ];
		 	 $msg = 'Product: '.$_POST['product_id'].' is updated with new inventory: '.$number_participants.' ';
	 	 }
		 	    

// 		 echo "<br><br>POST= "; print_r($_POST);
// 		 echo "<br><br><br>attributes_post_data_options=<br>"; print_r($attributes_post_data_options); 
// 		 echo "<br><br><br>attributes_product_data=<br>"; print_r($product['attributes']); 
// 		 echo "<br><br><br>attributes_all_data=<br>"; print_r($attributes_all_data); 
// 		 echo "<br><br>data= "; print_r($data); echo "<br><br>";
 		  
		 $woocommerce->put($endpoints.'/'.$_POST['product_id'], $data);
		 
		 $msg .= ' Product: '.$_POST['product_id'].' is updated with new values for attributes: '.implode(', ', array_keys($_POST['attributes']));
		 debug_msg(array('status_code' => '202','status_msg' => $msg, 'product_id' => $_POST['product_id']));
	}
	else{
		debug_msg(array('status_code' => '500','status_msg' => 'Error! POST parameters product_id and attributes are required.'));	
	}
 	
	// Debug function
	function debug_msg( $status = array() ) {
		print_r(json_encode($status));
		$log = fopen( 'logs/debug.txt', 'a+' );
		$message = '[' . date( 'd-m-Y H:i:s' ).'] '.$status['status_code'].': '.$status['status_msg'] .PHP_EOL;
		fwrite( $log, $message );
		fclose( $log );	
	}
	

?>