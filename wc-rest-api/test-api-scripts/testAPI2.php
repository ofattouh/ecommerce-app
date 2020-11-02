<?php

	/*
	* 
	* Woo commerce REST API script to integrate with PSHSA CRM
	* V.1
	*/

 	require __DIR__ . '/vendor/autoload.php';
 
 	use Automattic\WooCommerce\Client;

 	$api_url = 'https://dev.pshsa.ca/';
	$_POST   = json_decode(file_get_contents("php://input"), true);
 	
 	$consumer_key    = ( $_POST['apikey'] != '' )?       $_POST['apikey']       : '';
	$consumer_secret = ( $_POST['apisecret'] != '' )?    $_POST['apisecret']    : '';
	$endpoints       = ( $_POST['apiendpoints'] != '' )? $_POST['apiendpoints'] : '';	
	$options         = [ 'wp_api' => true, 'version' => 'wc/v1' ];
		 
	if ( $consumer_key    != 'ck_945e4ab831cd98e16980d11b2a59687a4f46b096' || 
		 $consumer_secret != 'cs_b3cc3b6bb613be0059ef56b4e3ffecaaf384515e' ||
		 $endpoints       != 'products' ||
		 $api_url         == '' ) {
		echo "<h3>Error! You are not allowed to run this script!</h3>";
		exit;	
	}
	
	$woocommerce = new Client($api_url, $consumer_key, $consumer_secret, $options);
	
	// Example calls
	// ******************************************************************************************

	// 1. Create a simple product with attributes

	$data = [
	    'name'              => ( $_POST['title'] != '' && isset($_POST['title']) )?                         $_POST['title']             : '',
	    'type'              => ( $_POST['type'] != '' && isset($_POST['type']) )?                           $_POST['type']              : '',
	    'regular_price'     => ( $_POST['regular_price'] != '' && isset($_POST['regular_price']) )?         $_POST['regular_price']     : '',
	    'description'       => ( $_POST['description'] != '' && isset($_POST['description']) )?             $_POST['description']       : '',
	    'short_description' => ( $_POST['short_description'] != '' && isset($_POST['short_description']) )? $_POST['short_description'] : '',
	    'categories' => [
	        [
	            'id' => 992  // training
	        ],
	        [
	            'id' => 1130  // mol extended
	        ],
	        [
	            'id' => 1131  // blended
	        ]
	    ],
	    'images' => [
	        [
	            'src' => 'http://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2013/06/T_2_front.jpg',
	            'position' => 0
	        ],
	        [
	            'src' => 'http://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2013/06/T_2_back.jpg',
	            'position' => 1
	        ]
	    ],
	    'attributes' => [
			[
	            'id' => 12,             // global attributes - attribute: training(12)
	            'position' => 0,
	            'visible' => false,     // optional default false
	            'variation' => false,   // optional default false
	            'options' => [
	               	'molextended',
	                'blended'
	            ]
	        ]
	    ]
	];	

	if ( $_POST['title']               != ''
		&& $_POST['type']              != '' 
		&& $_POST['regular_price']     != '' 
		&& $_POST['description']       != ''
		&& $_POST['short_description'] != ''
		){
		$woocommerce->post($endpoints, $data);
		//debug_log(file_get_contents("php://input"));
		debug_log(implode (', ', $_POST));
	}
	else{
		debug_log('POST request is empty!');	
	}
	
	/**
	 * Debug log.
	 */
	function debug_log( $message ) {
		echo $message.'<br><br>';
		$log = fopen( 'logs/debug.txt', 'a+' );
		$message = '[' . date( 'd-m-Y H:i:s' ).'] '.$message .PHP_EOL;
		fwrite( $log, $message );
		fclose( $log );	
	}
		
    	// 1. Retrive a product
	//print_r($woocommerce->get($endpoints.'/31502'));
	

	// 2. Create a simple product
		
// 	$data = [
// 	    'name' => 'Premium Quality',
// 	    'type' => 'simple',
// 	    'regular_price' => '21.99',
// 	    'description' => 'Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.',
// 	    'short_description' => 'Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas.',
// 	    'categories' => [
// 	        [
// 	            'id' => 992
// 	        ],
// 	        [
// 	            'id' => 1131
// 	        ]
// 	    ],
// 	    'images' => [
// 	        [
// 	            'src' => 'http://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2013/06/T_2_front.jpg',
// 	            'position' => 0
// 	        ],
// 	        [
// 	            'src' => 'http://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2013/06/T_2_back.jpg',
// 	            'position' => 1
// 	        ]
// 	    ]
// 	];
//  
// 	
// 	echo "<br><br><br>data==================>>>>>>>>>>>>>><br><br>";
// 	print_r($woocommerce->post($endpoints, $data));


?>