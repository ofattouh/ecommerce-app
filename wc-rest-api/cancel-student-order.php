<?php

	/****************************************************************************************************
	* PHP script to integrate Woo commerce with CRM using API REST calls.
	* Change the student status and update the product inventory levels for the student order
	* V.1.0
	******************************************************************************************************/

	require '../wp-load.php';  // For word press & woo commerce functions to work - API library has to be on the same server as WP installtion
	require 'api-config.php';  // Woo commerce API config file
	
 	// Debug
//  	$_POST['apiurl']       = $api_url_config;
// 	$_POST['apikey']       = $consumer_key_api;
// 	$_POST['apisecret']    = $consumer_secret_api;
// 	$_POST['apiendpoints'] = $endpoints_api;
//  	$_POST['InfoID']       = 16847;
//  	$_POST['studentStatus'] = 'cancel';
 	
 	// Decode the JSON object and fetch the POST parameters
 	$_POST = json_decode(file_get_contents("php://input"), true);
 	
 	$api_url         = ( $_POST['apiurl'] != ''        && isset($_POST['apiurl']) )? $_POST['apiurl']               : '';
	$consumer_key    = ( $_POST['apikey'] != ''        && isset($_POST['apikey']) )? $_POST['apikey']               : '';
	$consumer_secret = ( $_POST['apisecret'] != ''     && isset($_POST['apisecret']) )? $_POST['apisecret']         : '';
	$endpoints       = ( $_POST['apiendpoints'] != ''  && isset($_POST['apiendpoints']) )? $_POST['apiendpoints']   : '';	
	$InfoID          = ( $_POST['InfoID'] != ''        && isset($_POST['InfoID']) )? $_POST['InfoID']               : '';
	$studentStatus   = ( $_POST['studentStatus'] != '' && isset($_POST['studentStatus']) )? $_POST['studentStatus'] : '';
	
	// I: Validate API POST authenticatation parameters
	require 'api-auth.php';
	
	// II: Validate POST parameters  
	if ( $InfoID != '' && $studentStatus == 'cancel' ){   
		    
		global $wpdb, $woocommerce;	
			
		$update = $wpdb->update( 
			'ckv_student_info', 
		 	array('studentStatus' => $studentStatus), 
		 	array('InfoID' => $InfoID), 
		 	array('%s'), 
		 	array('%d') 
	    ); 
	    
		if (false === $update ){
			$json_messages = array('status_code' => '502','status_msg' => 'Error! Student status is not updated for InfoID: '.$InfoID);
		}
		else{
			$json_messages = array('status_code' => '202','status_msg' => ' Student status is successfully updated to: '.$studentStatus.' for InfoID: '.$InfoID);		
			
			$studentInfo = $wpdb->get_results( "SELECT * FROM `ckv_student_info` WHERE InfoID = ".$InfoID );	
				
			// Update the inventory levels for the product if the stock inventory level management is enabled
			if ( $studentInfo[0]->CourseID > 0 && $studentInfo[0]->studentStatus == 'cancel' && 
				wc_get_product( $studentInfo[0]->CourseID )->increase_stock() && wc_get_product( $studentInfo[0]->CourseID )->managing_stock() ){
				
				$msg = 'Product: '.$studentInfo[0]->CourseID.' stock level is now increased by 1, current stock level is: '.
					       wc_get_product( $studentInfo[0]->CourseID )->get_stock_quantity().' for InfoID: '.$InfoID;
				$json_messages = array('status_code' => '202','status_msg' => $msg);
			}
			else{
				$json_messages = array('status_code' => '502','status_msg' => 'Error! Stock inventory level management for product: '.$studentInfo[0]->CourseID.' is not enabled.');
			}	
		}
		
		debug_msg($json_messages); 
	}
	else{
		$json_messages = array('status_code' => '500','status_msg' => 'Error! POST parameters: InfoID and studentStatus are required.');
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