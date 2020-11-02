<?php
/*
Plugin Name: WooCommerce Litmos Single Sign On Login
Plugin URI: http://pshsa.ca
Description: Integrate Single Sign On for Litmos with Woo Commerce Checkout Registration plugin
Version: 1.0.0
Author: Omar M.
Author URI: http://pshsa.ca
Text Domain: woocommerce-litmos-sso
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

	
// Parse the URL
if (preg_match("/^\/sso-litmos/i", htmlspecialchars($_SERVER['REQUEST_URI']))):
	if (isset($_GET['uid']) && $_GET['uid'] != ''):
		get_litmos_sso_link($_GET['uid']);
	endif;
endif;


// Redirect the user browser to Litmos SSO link	
function get_litmos_sso_link( $user_id ) {

	if ( ! $user_id || ! get_option( 'wc_litmos_api_key' ) ) { return; }  //Valid Litmos user and API key
	
	//$this->set_endpoint( $this->users_uri . '/' . $user_id );
	//$this->http_request( 'GET' );
	//$this->parse_response();
	
	$msg            = '';
	$endpoint       = 'https://api.litmos.com/v1.svc/users/'.$user_id;
	$request_params = array( 'limit' => 9999 );
	$auth_params    = array( 'apikey' => get_option( 'wc_litmos_api_key' ), 'source' => 'woocommerce_litmos' );
	
	$wp_remote_http_args = array(
		'method'      => 'GET',
		'timeout'     => '60',
		'redirection' => 0,
		'httpversion' => '1.0',
		'sslverify'   => true,
		'blocking'    => true,
		'headers'     => array(
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json'
		),
		'body'        => '',
		'cookies'     => array()
	);

	$response = wp_safe_remote_request( $endpoint . '?' . http_build_query( array_merge( $request_params, $auth_params ) ), $wp_remote_http_args );

	// Check for errors
	if ( is_wp_error( $response ) ) {
		$msg = 'Litmos User ID: '.$user_id.': '.$response->get_error_message();
		report_sso_error($msg, $user_id);
		redirectRequest(get_home_url());
	}

	if ( ! isset( $response['response'] ) ) {
		$msg = 'Litmos User ID: '.$user_id.': Empty Response';
		report_sso_error($msg, $user_id);
		redirectRequest(get_home_url());
	}

	if ( ! isset( $response['body'] ) ) {
		$msg = 'Litmos User ID: '.$user_id.': Empty Body';
		report_sso_error($msg, $user_id);
		redirectRequest(get_home_url());
	}

	if ( isset( $response['response']['code'] ) ) {
		if ( '200' == $response['response']['code'] || '201' == $response['response']['code'] ) { //Success
			$response_code = $response['response']['code'];
			$response      = $response['body'];
			$response      = json_decode( $response, true );

		} else { 
			$msg = 'Litmos User ID: '.$user_id.': Error Code: '.$response['response']['code'].', Error Message: '.strip_tags($response['body']);
			report_sso_error($msg, $user_id);
			redirectRequest(get_home_url());
		}

	} else { 
		$msg = 'Litmos User ID: '.$user_id.' Response HTTP Code Not Set';
		report_sso_error($msg, $user_id);
		redirectRequest(get_home_url());
	}

	//print_r($response);
	
	//Go to Litmos user Home page if no errors
	if ( isset( $response['LoginKey'] ) && ! empty( $response['LoginKey'] ) ) {
		redirectRequest($response['LoginKey']); 
	}
	else{
		$msg = 'Error! No Login Key found for Litmos User ID: '.$user_id;
		report_sso_error($msg, $user_id);
		redirectRequest(get_home_url()); 
	}
	
}


// Redirect the user
function redirectRequest($location){
	header('Location: '.$location); 
	exit; 
}


// Catch the SSO error in both the log file and send an email with the error report
function report_sso_error($msg, $litmos_user_id){
	debug_log($msg);
	send_sso_email($msg, $litmos_user_id);	
}


// Trap Litmos API errors
function debug_log( $message ) {
	$log = fopen( plugin_dir_path( __FILE__ ) .'logs/sso_log.txt', 'a+' );
	$message = '[' . date( 'd-m-Y H:i:s' ) . '] ' . $message . PHP_EOL;
	fwrite( $log, $message );
	fclose( $log );
}


// Send an email to the CC team with the SSO error for the user
function send_sso_email($msg, $litmos_user_id){
	
	require_once ABSPATH . WPINC . '/class-phpmailer.php';
	require_once ABSPATH . WPINC . '/class-smtp.php';
	$phpmailer = new PHPMailer();
	
	$phpmailer->IsSMTP();  
	$phpmailer->Host       = "box770.bluehost.com";   
	$phpmailer->SMTPSecure = "ssl";
	$phpmailer->SMTPAuth   = true;
	//$phpmailer->SMTPDebug  = true;
	$phpmailer->Port       = 465;
	$phpmailer->From       = "noreply@pshsa.ca"; 
	$phpmailer->FromName   = "PSHSA Communications";     
	$phpmailer->Username   = "test2@pshsadev.com";   
	$phpmailer->Password   = "EjHGHgd$%^98fgGHgd$%^98fg";  
	
	$phpmailer->AddAddress("omohamed@pshsa.ca");     //cc TEAM
	
	$phpmailer->Subject  = "Single Sign On Error for Litmos User ID: ".$litmos_user_id;
	$phpmailer->Body     = $msg;
	//$phpmailer->WordWrap = 50;
	
	if(!$phpmailer->Send()) {
	  	debug_log('Litmos User ID: '.$litmos_user_id.'\r\n\r\nError sending email. Please contact your system admin!');
	}
	
}
