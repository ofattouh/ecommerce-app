<?php

	/****************************************************************************************************
	* PHP script to integrate Woo commerce with CRM using API REST calls.
	* Change product to any status: Default is `any`. Options: `any`, `draft`, `pending`, `private` and `publish`.
	* End point: products
	* URL: https://github.com/woocommerce/wc-api-php
	* Ver: 1.0
	******************************************************************************************************/

	require 'api-config.php';  // Woo commerce API config file
	
 	// Debug
//  	$_POST['apiurl']       = $api_url_config;
// 	$_POST['apikey']       = $consumer_key_api;
// 	$_POST['apisecret']    = $consumer_secret_api;
// 	$_POST['apiendpoints'] = $endpoints_api;
// 	$_POST['product_id']   = 41367;    // Should be created earlier from the CRM API call
//   	$_POST['status']       = 'draft';  // Product status

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
	if ( $_POST['product_id'] != '' && isset($_POST['product_id']) && $_POST['status'] != '' && isset($_POST['status']) ){
		 	
		 $woocommerce = new Client($api_url, $consumer_key, $consumer_secret, $options);
		 $product     = $woocommerce->get( 'products/'.$_POST['product_id'] );
		
		 $data = [ 'status' => $_POST['status'], ];

 		 //echo "<br><br>POST= "; print_r($_POST); echo "<br><br>data= "; print_r($data); echo "<br><br>";
 		  
		 $woocommerce->put($endpoints.'/'.$_POST['product_id'], $data);
		 $json_messages = array('status_code' => '202','status_msg' => 'Product: '.$_POST['product_id'].' status is succesfully changed to: '. $_POST['status']);
		 debug_msg($json_messages);
	}
	else{
		$json_messages = array('status_code' => '500','status_msg' => 'Error! POST parameters: product_id and status are required.');
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
	

?>