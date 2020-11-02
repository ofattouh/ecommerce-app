<?php
/* Template Name: Constant Contact API - Sign Up Form Confirmation Page */

/** 
 * API php-sdk @ https://github.com/constantcontact/php-sdk
 * This template uses a Constant Contact owner account to add or update a contact to their account. 
 * An API Key and an access token can be obtained from: http://constantcontact.mashery.com
 * @package presscore
 * @since presscore 0.1
 */
 
 
// File Security Check
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Exit if accessed directly
if ( ! $_POST['signupccapi_submitted'] ):
	wp_safe_redirect( get_home_url() );
	exit;
endif;

get_header();

get_footer(); 


?>
						   					