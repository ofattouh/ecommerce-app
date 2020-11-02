<?php

	/******************************************************************************************************************************
	* PHP script to integrate Woo commerce with CRM using API REST calls.
	* Transfer student to different course in the same order.
	* Only update the courses inventory levels as CRM courses that belong to the same grouped product and have the same price.
	* V.1.0
	*******************************************************************************************************************************/

	require '../wp-load.php';  // For word press & woo commerce functions to work - API library has to be on the same server as WP installtion
	require 'api-config.php';  // Woo commerce API config file
	
 	// Debug
//  	$_POST['apiurl']           = $api_url_config;
// 	$_POST['apikey']           = $consumer_key_api;
// 	$_POST['apisecret']        = $consumer_secret_api;
// 	$_POST['apiendpoints']     = $endpoints_api;
//  	$_POST['InfoID']           = 16846;
//  	$_POST['studentStatus']    = 'transfer';
//  	$_POST['CourseIDTransfer'] = 41367;  // DB row for course student is moving to
 	
 	// Decode the JSON object and fetch the POST parameters
 	$_POST = json_decode(file_get_contents("php://input"), true);
 	
 	$api_url          = ( $_POST['apiurl'] != ''           && isset($_POST['apiurl']) )? $_POST['apiurl']                     : '';
	$consumer_key     = ( $_POST['apikey'] != ''           && isset($_POST['apikey']) )? $_POST['apikey']                     : '';
	$consumer_secret  = ( $_POST['apisecret'] != ''        && isset($_POST['apisecret']) )? $_POST['apisecret']               : '';
	$endpoints        = ( $_POST['apiendpoints'] != ''     && isset($_POST['apiendpoints']) )? $_POST['apiendpoints']         : '';	
	$InfoIDPOST       = ( $_POST['InfoID'] != ''           && isset($_POST['InfoID']) )? $_POST['InfoID']                     : '';
	$studentStatus    = ( $_POST['studentStatus'] != ''    && isset($_POST['studentStatus']) )? $_POST['studentStatus']       : '';
	$CourseIDTransfer = ( $_POST['CourseIDTransfer'] != '' && isset($_POST['CourseIDTransfer']) )? $_POST['CourseIDTransfer'] : '';

	// I: Validate API POST authenticatation parameters
	require 'api-auth.php';
	
	// II: Validate POST parameters  
	if ( $InfoIDPOST != '' && $studentStatus == 'transfer' && $CourseIDTransfer != '' ){   
		    
		global $wpdb, $woocommerce;	
			
		$studentRow = $wpdb->get_results( "SELECT * FROM `ckv_student_info` WHERE InfoID = ".$InfoIDPOST );
		
		$InfoIDRow   = $studentRow[0]->InfoID;     // DB row for InfoID
		$CourseIDOld = $studentRow[0]->CourseID;   // DB row for Course student is moving from
		
		// Validate the InfoID column exist in the student table and this post exist
		if ( $InfoIDRow == $InfoIDPOST && get_post_status($CourseIDTransfer) ) {
		
			$parentIDTransfer = get_post_meta($CourseIDTransfer, 'product_custom_parent_id', true);
			
			$update = $wpdb->update( 
				'ckv_student_info', 
			 	array('studentStatus' => $studentStatus, 'CourseID' => $CourseIDTransfer, 'parentCourseID' => $parentIDTransfer ), 
			 	array('InfoID' => $InfoIDRow), 
			 	array('%s'), 
			 	array('%d') 
		    ); 
		    
			if (false === $update ){
				$json_messages = array('status_code' => '502','status_msg' => 'Error! Student status is not updated to '.$studentStatus.' for InfoID: '.$InfoIDRow);
			}
			else{	
				$json_messages = array('status_code' => '202','status_msg' => 'Student status is successfully updated to: '.$studentStatus.' for InfoID: '.$InfoIDRow);
				
				// Update the inventory levels for both products if the stock inventory level management is enabled from the back end
				if ( wc_update_product_stock(wc_get_product($CourseIDTransfer), 1, 'decrease') && wc_get_product($CourseIDTransfer)->managing_stock() &&
					 wc_update_product_stock(wc_get_product($CourseIDOld), 1, 'increase') && wc_get_product($CourseIDOld)->managing_stock() ){
				    
					$msg = 'Student is now successfully transfrered to Course: '.$CourseIDTransfer.'. Stock level is now increased by 1 for Product: '. 
							$CourseIDOld.' with current stock level: '. wc_get_product($CourseIDOld)->get_stock_quantity().
						    ' while stock level is now decreased by 1 for Product: '.$CourseIDTransfer.' with current stock level: '. 
						    wc_get_product($CourseIDTransfer)->get_stock_quantity().' at DB InfoID: '.$InfoIDRow;
					$json_messages = array('status_code' => '202','status_msg' => $msg );
				}	
				else{
					$json_messages = array('status_code' => '502','status_msg' => 'Error! Stock inventory level management is not set for products: '.$CourseIDOld.' and '.$CourseIDTransfer.' at DB InfoID: '.$InfoIDRow);
				}	
			}
		}
		else{
			$json_messages = array('status_code' => '404','status_msg' => 'Error! InfoID: '.$InfoIDPOST.' or Product: '. $CourseIDTransfer.' does not exist.');
		}
		
		debug_msg($json_messages);
	}
	else{
		$json_messages = array('status_code' => '500','status_msg' => 'Error! POST parameters: InfoID, studentStatus, and CourseIDTransfer are required.');
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