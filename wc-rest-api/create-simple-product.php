<?php

	/**********************************************************************************************************************************************
	* PHP script to integrate woocommerce with the CRM using API REST calls
	* Create a simple product that belongs to a grouped product with dynamic categories and attributes and update the SKU field for the simple
	* product with the CRM session ID. The grouped product SKU has to be added manually from the backend and the grouped product will be created 
	* manually for now. The group product featured image is used as the featured image of the simple product. The grouped product description 
	* and short description are copied to the simple product. The product attribues can be visible or not on the product page.
	* Enable stock managment for each product and set the product stock quantity with the number of participants from the CRM. 
	* End point: products.
	* URL: https://github.com/woocommerce/wc-api-php
	* Ver: 1.0
	************************************************************************************************************************************************/

	require '../wp-load.php';  // For word press & woo commerce functions to work - API library has to be on the same server as WP installtion
	require 'api-config.php';  // Woo commerce API config file
	
	// Debug
//     $_POST['apiurl']              = $api_url_config;
// 	$_POST['apikey']              = $consumer_key_api;
// 	$_POST['apisecret']           = $consumer_secret_api;
// 	$_POST['apiendpoints']        = 'products';
//  	$_POST['title']               = esc_attr("Test''||&*($%#{}'' Session created at: ".date('Y-m-d H:i:s'));
//  	$_POST['type']                = 'simple';
//  	$_POST['regular_price']       = '5.99';
//  	//$_POST['description']       = esc_attr('Long description goes here: '.date('Y-m-d H:i:s'));
//  	//$_POST['short_description'] = esc_attr('Short description goes here: '.date('Y-m-d H:i:s'));
//  	$_POST['manage_stock']        = 1;
//     $_POST['catalog_visibility']  = 'hidden';
//  	$_POST['number_participants'] = 10;
//  	$_POST['sku']                 = 'PBAKTAFR0316';    			// 42237 - valid SKU for grouped product - CRM product
//  	//$_POST['sku']               = 'SWFKTAEN0715';    			// 25629 - valid SKU for grouped product - CRM product
//     //$_POST['sku']               = 'S#$%03QEW15';     			// does not exist
//     $_POST['sku_session']         = '10086';           			// CRM session ID
//     $_POST['categories']          = array(992,191,1131);      	// training, In Class, molextended
// 	//$_POST['categories']        = array(992,1130,1131);     	// training, blended, molextended
// 	$_POST['attributes']          = array( 
// 									  'pa_facilitator'       => array('id' => 1,  'visible' => true, 'options' => array("F'ac\\{}^&%ilit'ator: ".date('Y-m-d H:i:s'))),
// 	 								  //'pa_course-location'   => array('id' => 2,  'visible' => true, 'options' => array('Course Location: '.date('Y-m-d H:i:s'))),
// 	 								  'pa_course-location'   => array('id' => 2,  'visible' => true, 'options' => array("Best' Western Plus' Cairn Croft Hotel, 6400 Lundy's Lane, Niagara Falls, L2G 1T6")),
// 	 								  'pa_course-city'       => array('id' => 3,  'visible' => true, 'options' => array("Course' City: ".date('Y-m-d H:i:s'))),
// 	 								  'pa_sector'            => array('id' => 5,  'visible' => true, 'options' => array("Sector ''1: ".date('Y-m-d H:i:s'), "S\\{}ector ''2: ".date('Y-m-d H:i:s'), "Sector ''3: ".date('Y-m-d H:i:s'), "Sec{}||()*&^%$#''tor ''4: ".date('Y-m-d H:i:s'))),
// 	 								  'pa_time'              => array('id' => 6,  'visible' => true, 'options' => array('8:30am - 4:30 pm')),
// 	 								  //'pa_length-of-course'  => array('id' => 7,  'visible' => true, 'options' => array('2 days: '.date('Y-m-d H:i:s'))),
// 	 								  'pa_region'            => array('id' => 8,  'visible' => true, 'options' => array("'''''Nor'''th: ".date('Y-m-d H:i:s'))),
// 	 								  'pa_start-date'        => array('id' => 9,  'visible' => true, 'options' => array('10/25/2018')),
// 	 								  'pa_month'             => array('id' => 10, 'visible' => true, 'options' => array('October')),
// 	 								  'pa_all-courses'       => array('id' => 11, 'visible' => true, 'options' => array('All Courses: '.date('Y-m-d H:i:s'))),
// 	 								  //'pa_training'        => array('id' => 12, 'visible' => true, 'options' => array('molextended', 'blended')), 
// 	 								  'pa_training'          => array('id' => 12, 'visible' => true, 'options' => array('molextended', 'In-class Training')),
// 	 								  'pa_training-category' => array('id' => 13, 'visible' => true, 'options' => array('training')),
// 	 								  'pa_room'              => array('id' => 15, 'visible' => true, 'options' => array('room: '.date('Y-m-d H:i:s'))),
// 	 								  'pa_session-info'      => array('id' => 16, 'visible' => true, 'options' => array('Session Info: '.date('Y-m-d H:i:s'))),
// 	 								  'pa_customattrtrain'   => array('id' => 17, 'visible' => true, 'options' => array('molextendedcustomattrtrain', 'inclasscustomattrtrain')),
// 	 								  //'pa_customattrtrain' => array('id' => 17, 'visible' => true, 'options' => array('molextendedcustomattrtrain', 'othercustomattrtrain')),
// 	 								  'pa_session-id'        => array('id' => 18, 'visible' => true, 'options' => array('Session ID: '.date('Y-m-d H:i:s')))
// 		 							);

	require __DIR__ . '/vendor/autoload.php';
 	use Automattic\WooCommerce\Client;
 	
 	// Decode the JSON object and fetch the POST parameters
 	$_POST = json_decode(file_get_contents("php://input"), true);
 	
 	$api_url         = ( $_POST['apiurl'] != ''       && isset($_POST['apiurl']) )?       $_POST['apiurl']       : '';
	$consumer_key    = ( $_POST['apikey'] != ''       && isset($_POST['apikey']) )?       $_POST['apikey']       : '';
	$consumer_secret = ( $_POST['apisecret'] != ''    && isset($_POST['apisecret']) )?    $_POST['apisecret']    : '';
	$endpoints       = ( $_POST['apiendpoints'] != '' && isset($_POST['apiendpoints']) )? $_POST['apiendpoints'] : '';	
	$json_messages   = array();  // Store all json messages into one big array
	
	// I: Validate API POST authenticatation parameters
	require 'api-auth.php';
	
	// All products will be created in draft mode
	$_POST['status'] = 'draft';
	
	// All products will be created as virtual (no shipping)
	$_POST['virtual'] = 1;
	 
	// II: Validate API POST product parameters  
	if ( $_POST['title'] != ''               && isset($_POST['title']) && 
	     $_POST['type'] != ''                && isset($_POST['type']) &&
	     $_POST['status'] != ''              && isset($_POST['status']) &&
	     $_POST['regular_price'] != ''       && isset($_POST['regular_price']) &&
	     $_POST['virtual']       != ''       && isset($_POST['virtual']) &&  
	     //$_POST['description'] != ''       && isset($_POST['description']) &&
	     //$_POST['short_description'] != '' && isset($_POST['short_description']) &&
	     $_POST['sku'] != ''                 && isset($_POST['sku']) && 
	     $_POST['sku_session'] != ''         && isset($_POST['sku_session']) &&
	     $_POST['manage_stock'] != ''        && isset($_POST['manage_stock']) &&
 		 $_POST['number_participants'] != '' && isset($_POST['number_participants']) &&
	     sizeof($_POST['categories']) > 0    && sizeof($_POST['attributes']) > 0 ){
		
		 $parent_id  = wc_get_product_id_by_sku($_POST['sku']);          // SKU for grouped product
		 $session_id = wc_get_product_id_by_sku($_POST['sku_session']);  // SKU for simple product
	     
		 if ($parent_id > 0 && $session_id == 0){
	 	
			 $woocommerce = new Client($api_url, $consumer_key, $consumer_secret, $options);
		
			 $categories_post_data = [];
			 $attributes_post_data = [];
		 	 
		 	 
			 foreach ($_POST['categories'] as $k=>$v){   // re-construct the categories POST data
				 $categories_post_data[] = array( 'id' => $v );
			 }
			
			 foreach ($_POST['attributes'] as $k=>$v){   // re-construct the attributes POST data
			 	
			 	$attr_options = [];	// Reset the array
			 	
			 	foreach ($v['options'] as $k2=>$v2){
				 	if ( ! array_key_exists($k2, $attr_options) ){
				 		$attr_options = array_merge($attr_options, array(esc_attr($v2)));
			 		}
			 		else{
				 		$attr_options = array_merge($attr_options, array(esc_attr($v2)));
			 		}
			 	}
			 	
			 	$visible = ( $v['visible'] == true ) ? $v['visible'] : false;
				$attributes_post_data[] = array( 'id' => $v['id'], 'visible' => $visible, 'options' => $attr_options );	
			 }
			 
			 // Copy the parent product description to the simple product. esc_attr is not used: HTML markup will be preserved.
			 $description = wc_get_product($parent_id)->get_description();
			 
			 // Copy the parent product short description to the simple product. esc_attr is not used: HTML markup will be preserved.
			 $short_description = wc_get_product($parent_id)->get_short_description();
			 
			 $data = [
		 	    'name'               => esc_attr($_POST['title']),  // no HTML markup preserved
		 	    'type'               => $_POST['type'],
		 	    'status'             => $_POST['status'],
		 	    'regular_price'      => $_POST['regular_price'],
		 	    'virtual' 			 => $_POST['virtual'],
		 	    'description'        => $description,
		 	    'short_description'  => $short_description,
		 	    'parent_id'          => 0,  // since woo commerce v3.0
		 	    'manage_stock'       => $_POST['manage_stock'],
		 	    'catalog_visibility' => ( $_POST['catalog_visibility'] != '' && isset($_POST['catalog_visibility']) )? $_POST['catalog_visibility'] : 'hidden',
		 	    'stock_quantity'     => $_POST['number_participants'],
		 	    'categories'         => $categories_post_data,
		  	    'attributes'         => $attributes_post_data,
		  	    'images' => [    // update the simple product featured image with the grouped product featured image
				        [
				            'src' => wp_get_attachment_url(wc_get_product(wc_get_product_id_by_sku($_POST['sku']) )->get_image_id()),
				            'position' => 0   // product image is featured
				        ]
			    ],
		 	 ];
		 	 
			 $product = $woocommerce->post($endpoints, $data);
			
			 if ($product['id'] != '' && $_POST['sku_session'] != ''){  // verify product is created
			 	 
			 	 $json_messages = array('status_code' => '201','status_msg' => 'Product is created', 'product_id' => $product['id']);

// 		 	 echo "<br><br>"; print_r($categories_post_data); echo "<br><br>"; print_r($attributes_post_data); 
// 		 	 echo "<br><br>"; print_r($_POST); echo "<br><br>"; print_r($data);
			 	 
			 	 // verify simple product sku is updated and it has only one parent - since woo commerce upgrade: v3.0
				 if( update_post_meta($product['id'], '_sku', $_POST['sku_session']) && 
				 	 update_post_meta($product['id'], 'product_custom_parent_id', $parent_id) &&
				 	 update_post_meta($parent_id, '_children', wp_parse_args(array($product['id']), wc_get_product($parent_id)->get_children())) ){
				 	 $json_messages = array('status_code' => '202','status_msg' => 'Product: '.$product['id'].' is created with only one parent: '.$parent_id.'. SKU: '.$_POST['sku_session'].' is updated.', 'sku_session' => $_POST['sku_session'], 'product_id' => $product['id']);
			 	 }
			 	 else{
				 	 $json_messages = array('status_code' => '502','status_msg' => 'Error! Product sku field is not updated', 'sku_session' => $_POST['sku_session']);	 
			 	 }
			 }
			 else{
			 	 $json_messages = array('status_code' => '501','status_msg' => 'Error! Product is not created', 'sku_session' => $_POST['sku_session']);	 
			 }
		 }
		 else{
			 $json_messages = array('status_code' => '404','status_msg' => 'Error! sku: '.$_POST['sku'].' for grouped product does not exist or sku_session: '.$_POST['sku_session'].' already exist. Simple product is not created.');
		 }
		  
		 debug_msg($json_messages);
	}
	else{
		$json_messages[] = array('status_code' => '500','status_msg' => 'Error! Product not created. Title,type,regular_price,sku, sku_session, manage_stock, number_participants, categories, and attributes are all required.');	
		debug_msg($json_messages); 
	}
 	
	// Debug function
	function debug_msg( $status = array() ) {
		print_r(json_encode($status));
		$log = fopen( 'logs/debug.txt', 'a+' );
		$message = '[' . date( 'd-m-Y H:i:s' ).'] '.$status['status_code'].': '.$status['status_msg'] .PHP_EOL;
		fwrite( $log, $message );
		fclose( $log );	
	}
	
    // 2. Retrive the product
	//print_r($woocommerce->get($endpoints.'/'.$product['id']));

		

?>