<?php

	/*
	* 
	* Woo commerce REST API script to integrate with PSHSA CRM
	* V.1
	*/


 	require __DIR__ . '/vendor/autoload.php';
 
 	use Automattic\WooCommerce\Client;
 	
// 	$consumer_key    = 'ck_945e4ab831cd98e16980d11b2a59687a4f46b096';
// 	$consumer_secret = 'cs_b3cc3b6bb613be0059ef56b4e3ffecaaf384515e';
// 	$endpoints       = 'products';

 	$api_url         = 'https://dev.pshsa.ca/';
	$consumer_key    = $_GET['apikey'];
	$consumer_secret = $_GET['apisecret'];
	$endpoints       = $_GET['apiendpoints'];
	
	$options = [
				'wp_api' => true,
		        'version' => 'wc/v1',
	    	];
	
	if ( ! isset($consumer_key) || ! isset($consumer_secret) || ! isset($endpoints) ){
		echo "<h3>Error! You are not allowed to run this script!</h3>";
		exit;
	}
		 
	if ( $consumer_key    != 'ck_945e4ab831cd98e16980d11b2a59687a4f46b096' || 
		 $consumer_secret != 'cs_b3cc3b6bb613be0059ef56b4e3ffecaaf384515e' ||
		 $endpoints       != 'products') {
		echo "<h3>Error! You are not allowed to run this script!</h3>";
		exit;	
	}
	
	$woocommerce = new Client($api_url, $consumer_key, $consumer_secret, $options);
	

	// Example calls
	// *******************************************************************************************
	
	// 1. Retrive a product
	print_r($woocommerce->get($endpoints.'/31502'));
	

	
	
	// 2. Create a simple product
		
// 	$data = [
// 	    'name' => 'Try Product API - name field - Premium Quality',
// 	    'type' => 'simple',
// 	    'regular_price' => '9.99',
// 	    'description' => 'Try Product API product description',
// 	    'short_description' => 'Try Product API product short description',
// 	    'categories' => [
// 	        [
// 	            'id': 226   // training
// 	        ],
// 	        [
// 	            'id': 11    // blended
// 	        ],
// 	        [
// 	            'id': 111    // molextended
// 	        ]
// 	    ],
// 	    'images' => [
// 	        [
// 	            'src' => 'http://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2013/06/T_2_front.jpg',
// 	            'position' => 0  // featured
// 	        ],
// 	        [
// 	            'src' => 'http://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2013/06/T_2_back.jpg',
// 	            'position' => 1
// 	        ]
// 	    ]
// // 	    ,'attributes' => [
// // 	        [
// // 	            'id' => 6,
// // 	            'position' => 0,
// // 	            'visible' => false,
// // 	            'variation' => false,
// // 	            'options' => [
// // 	                'Black',
// // 	                'Green'
// // 	            ]
// // 	        ],
// // 	        [
// // 	            'name' => 'Size',
// // 	            'position' => 0,
// // 	            'visible' => true,
// // 	            'variation' => true,
// // 	            'options' => [
// // 	                'S',
// // 	                'M'
// // 	            ]
// // 	        ]
// // 	    ]
// 	];
// 	
// 	echo "<br><br><br>Create a simple product<br><br>";
// 	print_r($woocommerce->post($endpoint, $data));

	/*
	$data = [
	    'name' => 'Ship Your Idea',
	    'type' => 'variable',
	    'description' => 'Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.',
	    'short_description' => 'Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas.',
	    'categories' => [
	        [
	            'id': 9
	        ],
	        [
	            'id': 14
	        ]
	    ],
	    'images' => [
	        [
	            'src' => 'http://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2013/06/T_4_front.jpg',
	            'position' => 0
	        ],
	        [
	            'src' => 'http://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2013/06/T_4_back.jpg',
	            'position' => 1
	        ],
	        [
	            'src' => 'http://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2013/06/T_3_front.jpg',
	            'position' => 2
	        ],
	        [
	            'src' => 'http://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2013/06/T_3_back.jpg',
	            'position' => 3
	        ]
	    ],
	    'attributes' => [
	        [
	            'id' => 6,
	            'position' => 0,
	            'visible' => false,
	            'variation' => true,
	            'options' => [
	                'Black',
	                'Green'
	            ]
	        ],
	        [
	            'name' => 'Size',
	            'position' => 0,
	            'visible' => true,
	            'variation' => true,
	            'options' => [
	                'S',
	                'M'
	            ]
	        ]
	    ],
	    'default_attributes' => [
	        [
	            'id' => 6,
	            'option' => 'Black'
	        ],
	        [
	            'name' => 'Size',
	            'option' => 'S'
	        ]
	    ],
	    'variations' => [
	        [
	            'regular_price' => '19.99',
	            'image' => [
	                [
	                    'src' => 'http://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2013/06/T_4_front.jpg',
	                    'position' => 0
	                ]
	            ],
	            'attributes' => [
	                [
	                    'id' => 6,
	                    'option' => 'black'
	                ],
	                [
	                    'name' => 'Size',
	                    'option' => 'S'
	                ]
	            ]
	        ],
	        [
	            'regular_price' => '19.99',
	            'image' => [
	                [
	                    'src' => 'http://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2013/06/T_3_front.jpg',
	                    'position' => 0
	                ]
	            ],
	            'attributes' => [
	                [
	                    'id' => 6,
	                    'option' => 'green'
	                ],
	                [
	                    'name' => 'Size',
	                    'option' => 'M'
	                ]
	            ]
	        ]
	    ]
	];
	
	print_r($woocommerce->post('products', $data));
	
	*/

	
	
	

?>