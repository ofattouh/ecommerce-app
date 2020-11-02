<?PHP
/*
Plugin Name: WooCommerce Checkout Registration
Plugin URI: http://pshsa.ca
Description: Custom plugin developed for PSHSA.ca and updated by Omar M.
Version: 1.0.1
Author: Grow Development & PSHSA
Author URI: http://growdevelopment.com
Text Domain: woocommerce-checkout-registration
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class WooCommerce_Checkout_Registration
 *
 * Main WAC class initializes the plugin
 *
 * @class       WooCommerce_Checkout_Registration
 * @version     1.0.0
 * @author      Grow Development
 */
class WooCommerce_Checkout_Registration {
	
	private $apiLitmosCustom;	
	private $ccteamEmail;
	
	/**
	 * __construct function.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// Check if WooCommerce is active
		if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) :
			//if ( ! is_plugin_active_for_network( 'woocommerce/woocommerce.php' ) ) :
			//	return;
			//endif;
		endif;
		
		// CC Team email
		$this->ccteamEmail = "omohamed@pshsa.ca";
		
		// Hooks
		$this->wcr_hooks();	

	}


	/**
	 * Class hooks.
	 *
	 * All initial hooks used in this class.
	 *
	 * @since 1.0.0
	 */
	public function wcr_hooks() {

		global $wc_litmos;
		
		// Use WC Checkout Registration?
		if ( 'no' ==  get_option( 'enable_student_data' ) ) :
			return;
		endif;
		
		// Enqueue script
		add_action( 'wp_enqueue_scripts', array( $this, 'wcr_enqueue_scripts' ) );
		
		// Add student data checkout fields
		add_action( 'woocommerce_checkout_after_customer_details', array( $this, 'wcr_woocommerce_checkout_fields' ) );
		
		// Validate student data
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'wcr_woocommerce_validate_checkout_fields' ) );
		   
		// Validate Bundeled discounted promotion course data against Litmus if any - should be the last validation
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'wcr_woocommerce_validate_checkout_fields_bundeled_courses' ), 100 );
		
		// Save student data checkout fields
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'wcr_save_checkout_fields' ), 10, 2 );
		
		// Order status change actions
		add_action( 'woocommerce_order_status_processing', array( $this, 'wrc_order_status_processing'), 10, 1);
		add_action( 'woocommerce_order_status_completed', array( $this, 'wrc_order_status_completed'), 10, 1);
		add_action( 'woocommerce_order_status_pending', array( $this, 'wrc_order_status_pending'), 10, 1);
		add_action( 'woocommerce_order_status_failed', array( $this, 'wrc_order_status_failed'), 10, 1);
		add_action( 'woocommerce_order_status_on-hold', array( $this, 'wrc_order_status_on_hold'), 10, 1);
		add_action( 'woocommerce_order_status_refunded', array( $this, 'wrc_order_status_refunded'), 10, 1);
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'wrc_order_status_cancelled'), 10, 1);
		
		// Update the manual/cheque payment from on-hold to processing for all orders - as per requirements - and to enable auto Litmos API calls
		add_action( 'woocommerce_thankyou_cheque', array( $this, 'wrc_update_on_hold_manual_payment_to_processing'), 10, 1 );
		
		add_filter( 'wp_mail_from', function( $email ) {
			return 'customerservice@pshsa.ca';
		});
		
		add_filter( 'wp_mail_from_name', function( $name ) {
			return 'PSHSA Customer service';
		});

		// Remove the filters and actions of skyverge plugin: woocommerce-litmos and then add new actions/filters for custom plugin multiple registeration
		//if ( 'yes' == get_option( 'wc_litmos_auto_create_accounts' ) ) {
			
			//Custom Woo Commerce Litmos plugin code should be invoked now only
			require plugin_dir_path( __FILE__ ) . '/lib/class-wc-litmos-api-custom.php';
			$this->apiLitmosCustom = new WC_Litmos_API_Custom( get_option( 'wc_litmos_api_key' ) );
		
			foreach ( array( 'woocommerce_payment_complete', 'woocommerce_order_status_on-hold_to_processing', 'woocommerce_order_status_on-hold_to_completed', 'woocommerce_order_status_failed_to_processing', 'woocommerce_order_status_failed_to_completed' ) as $action ) {
				remove_action( $action, array($wc_litmos, 'export_order' ) );
				remove_filter( 'woocommerce_billing_fields', array($wc_litmos, 'require_company_field_on_checkout') );
				remove_action( 'woocommerce_after_my_account', array($wc_litmos, 'add_my_courses_section' ) );
				
				add_action($action, array( $this, 'export_order_Litmos' ));	
				
				// Save Affiliate ID only if the order is marked as processing/completed
				add_action($action, array($this, 'set_affiliate_id'));
			}
		//}
		
		// Add 'My Courses' section to My Account page
		//add_action( 'woocommerce_after_my_account', array( $this, 'add_my_courses_section' ) )
		
	}

	/**
	 * Save Affiliate ID to the Order
	*/
	function set_affiliate_id($order_id){
		if (isset($_COOKIE['affiliate_id']) && $_COOKIE['affiliate_id'] != '' && isset($order_id) && $order_id != ''):
			$order = wc_get_order($order_id);
			update_post_meta($order_id, '_woo_affiliate_id', $_COOKIE['affiliate_id']);
			$order->add_order_note(__('Affiliate ID: ' . $_COOKIE["affiliate_id"] . ' is saved to the Database for Order number: ' . $order_id, 'woocommerce-checkout-registration'));
			$this->payload_ga_data($order);
		endif;
	}

	/**
	  * Process Order information for GA Ecommerce Transactions
	*/
	function payload_ga_data($order){

		// https: //developers.google.com/analytics/devguides/collection/protocol/v1/devguide
		// https: //ga-dev-tools.appspot.com/hit-builder/
		// $ga_google_api_url_debug = 'https: //www.google-analytics.com/debug/collect';

		$ga_property_Id    = 'UA-38778992-1';
		$ga_google_api_url = 'https://www.google-analytics.com/collect';

		$payload_order = array();

		// 1. Send Order information
		//$order_total_taxes_shipping         = $order->get_total_tax() + $order->get_shipping_tax();
		//$order_total_no_taxes_shipping      = $order->get_total() - $order->get_total_tax() - $order->get_shipping_total() - $order->get_shipping_tax();
		//$payload_order['order_total']       = wc_format_decimal($order_total_no_taxes_shipping, wc_get_price_decimals());
		//$payload_order['order_total_taxes'] = wc_format_decimal($order_total_taxes_shipping, wc_get_price_decimals());

		$order_id                           = $order->get_id();
		$order_item_data                    = wc_get_order($order_id)->get_items();	
		$payload_order['order_id']          = $order_id;
		$payload_order['order_total']       = $order->get_total();
		$payload_order['order_total_taxes'] = $order->get_total_tax();
		// $payload_order['order_discount'] = null; // ToDo

		$ga_data  = "Order number: ". $order_id." is purchased from " . $_COOKIE["affiliate_id"]. " for the total amount of: $" . $order->get_total()." (including $".$payload_order['order_total_taxes']." taxes)";
		$response = $this->send_ecommerce_ga_order_data($ga_property_Id, $ga_google_api_url, $ga_data, $payload_order);

		if ( !is_wp_error($response) ) {
			$woo_affiliates_is_in_ga = '_woo_affiliates_is_in_ga_'.$order_id;
			update_post_meta($payload_order['order_id'], $woo_affiliates_is_in_ga, 'yes');
			$order->add_order_note(__('Order number: '.$order_id.' Data for Affiliate: ' . $_COOKIE["affiliate_id"] . ' is sent to Google Analytics', 'woocommerce-checkout-registration'));
		}
		else{
			$order->add_order_note(__('Error! '.$response->get_error_message().', Order number: '.$order_id.' Data for Affiliate: ' . $_COOKIE["affiliate_id"] .' is not sent to Google Analytics', 'woocommerce-checkout-registration'));
		}

		// 2. Send Product information
		foreach ($order_item_data as $item_id => $item_data){
			$payload_product = array();
		
			// Product information
			$product    = wc_get_product(wc_get_order_item_meta($item_id, '_product_id', true));
			$product_id = $product->get_id();
			$payload_product['order_id']         = $order_id;
			$payload_product['product_title']    = $product->get_name();
			$payload_product['product_price']    = $product->get_price();
			$payload_product['product_quantity'] = wc_get_order_item_meta($item_id,'_qty', true);
			$payload_product['product_sku']      = $product->get_sku();
			$payload_product['product_category'] = 'training'; // Hard Coded

			$ga_data  = $payload_product['product_title']." (ID: ".$product_id.", SKU: ".$product->get_sku().") is purchased from " . $_COOKIE["affiliate_id"]. " for the amount of: $" . $payload_product['product_price']." (excluding taxes) for Order number: ".$order_id;
			$response = $this->send_ecommerce_ga_product_data($ga_property_Id, $ga_google_api_url, $ga_data, $payload_product);

			if ( !is_wp_error($response) ) {
				$woo_affiliates_is_in_ga = '_woo_affiliates_is_in_ga_'.$product_id;
				update_post_meta($order_id, $woo_affiliates_is_in_ga, 'yes');
				$order->add_order_note(__('Product ID: '.$product_id.' Data for Affiliate: ' . $_COOKIE["affiliate_id"] . ' is sent to Google Analytics for Order number: '.$order_id, 'woocommerce-checkout-registration'));
			}
			else{
				$order->add_order_note(__('Error! '.$response->get_error_message().', Product ID: '.$product_id.' Data is not sent to Google Analytics for Order number: '.$order_id, 'woocommerce-checkout-registration'));
			}
		}
	}

	/**
	 * Send Order information to GA
	*/
	private function send_ecommerce_ga_order_data($ga_property_Id, $ga_google_api_url, $ga_data, $payload = array()) {
		?>
		<script>__gaTracker('send', 'event',  'Checkout', 'Order Purchase', '<?php echo $ga_data; ?>');</script>

		<?php

		// Order transaction
		$payload_order = array(
			'v'   => 1,                             // Version
			'tid' => $ga_property_Id,               // Tracking ID (PSHSA Property ID)
			'cid' => mt_rand(0,10000),              // Anonymous Client ID (randomly generated)
			't'   => 'transaction',                 // Transaction hit type
			'ti'  => $payload['order_id'],          // Transaction ID (Required)
			'ta'  => $_COOKIE["affiliate_id"],      // Transaction Affiliation   
			'tr'  => $payload['order_total'],       // Transaction revenue
			'ts'  => '0.00',                        // Transaction shipping
			'tt'  => $payload['order_total_taxes'], // Transaction tax
			'cu'  => 'CAD'                          // Currency code
			// 'tcc' => $payload['order_discount']  // Discount code (ToDo)
		);

		$body = http_build_query($payload_order, '', '&');

		$args = array(
			'method'     => 'POST',
			'timeout'    => 15,
			'blocking'   => false, // https://codex.wordpress.org/HTTP_API#Other_Arguments
			'body'       => $body,
			'user-agent' => 'E-Commerce Order Tracker for Property: '.$ga_property_Id,
		);

		$response = wp_remote_post( $ga_google_api_url, $args );
		
		return $response;
	}

	/**
	 * Send Product information to GA
	*/
	private function send_ecommerce_ga_product_data($ga_property_Id, $ga_google_api_url, $ga_data, $payload = array()) {
		?>
		<script>__gaTracker('send', 'event',  'Checkout', 'Product Purchase', '<?php echo $ga_data; ?>');</script>

		<?php

		// Product Transaction
		$payload_product = array(
			'v'   => 1,                            // Version
			'tid' => $ga_property_Id,              // Tracking ID (PSHSA Property ID)
			'cid' => mt_rand(0,100000),            // Anonymous Client ID (randomly generated)
			't'   => 'item',                       // Item hit type
			'ti'  => $payload['order_id'],         // Transaction ID (Required)  
			'in'  => $payload['product_title'],    // Item name. Required
			'ip'  => $payload['product_price'],    // Item price
			'iq'  => $payload['product_quantity'], // Item quantity
			'ic'  => $payload['product_sku'],      // Item code / SKU
			'iv'  => $payload['product_category'], // Item category
			'cu'  => 'CAD'                         // Currency code
		);

		$body = http_build_query($payload_product, '', '&');

		$args = array(
			'method'     => 'POST',
			'timeout'    => 15,
			'blocking'   => false, // https://codex.wordpress.org/HTTP_API#Other_Arguments
			'body'       => $body,
			'user-agent' => 'E-Commerce Product Tracker for Property: '.$ga_property_Id,
		);

		$response = wp_remote_post( $ga_google_api_url, $args );
		
		return $response;
	}
	
	/**
	 * Creates user accounts in Litmos & assigns courses to users using the custom multiple registeration plugin
	 * Only applies to the elearning category with Litmos enabled products - hard coded for now
	 * @since 1.0
	 * @param string $order_id ID of order to export
	*/
	public function export_order_Litmos( $order_id ) {
		
		global $wc_litmos;
		
		if ( !$order_id ) return;
		
		$isLitmosEnabledCourse = false;
		$order                 = wc_get_order( $order_id );
		$student_information   = get_post_meta($order_id, '_student_information', true );
		
		if ( isset($student_information) && is_array($student_information) ){
			//Get all the students/classes (one to one)
			foreach ( $student_information as $student ) :
				
				$isLitmosEnabledCourse = $this->wrc_woocommerce_check_for_product_category($student['course_id'], 'elearning');
				
				if ($isLitmosEnabledCourse){
					
					$username = sanitize_user( $student['email'], true );
					
					// Build user array
					$user = array(
						'UserName'        => $username,
						'FirstName'       => $student['first_name'],
						'LastName'        => $student['last_name'],
						'CompanyName'     => 'YOUR COMPANY NAME',
						'PhoneWork'       => '999 999 9999',
						'Street1'         => 'YOUR STREET 1',
						'Street2'         => 'YOUR STREET 2',
						'City'            => 'YOUR CITY',
						'State'           => 'YOUR STATE',
						'PostalCode'      => 'YOUR POSTAL CODE',
						'Country'         => 'YOUR COUNTRY',
						'DisableMessages' => ( 'yes' == get_option( 'wc_litmos_disable_messages' ) ) ? true : false,
						'SkipFirstLogin'  => ( 'yes' == get_option( 'wc_litmos_skip_first_login' ) ) ? true : false,
					);
				
					// Get Litmos course ID if it exists
					//$course_id = get_post_meta( ( ! empty( $product->variation_id ) ) ? $product->variation_id : $product->id, '_wc_litmos_course_id', true );
					$course_id = get_post_meta( $student['course_id'] , '_wc_litmos_course_id', true );
					
					// Add course ID & quantity to array - one each
					if ( $course_id ) {
						//$courses[ $course_id ] = (int) $order_item['qty'];
						$courses[] = $course_id;
					}
					
					try {
		
						// Check if logged in user already has a Litmos ID and use it for assigning courses
						//if ( $order->get_user_id() > 0 ) {
							//$litmos_user_id = get_user_meta( $order->get_user_id(), '_wc_litmos_user_id', true );
						//}
			
						// Check if username already exists in Litmos
						//if ( empty( $litmos_user_id ) ) {
							//$litmos_user_id = $wc_litmos->get_api()->get_user_id_by_username( $username );
							$litmos_user_id = $this->apiLitmosCustom->get_user_id_by_username_custom( $username );
						//}
			
						// No Litmos User ID, create a new one
						if ( empty( $litmos_user_id ) || ! $litmos_user_id ) {
			
							// Create user in Litmos
							$litmos_user = $wc_litmos->get_api()->create_user( $user );
			
							// set 'litmos user created' order note
							$order->add_order_note( __( 'Litmos account created for customer', WC_Litmos::TEXT_DOMAIN ) );
			
							// Set User Meta (only for logged in users)
							//if ( $this->order->get_user_id() > 0 ) {
								//if ( ! update_user_meta( $this->order->get_user_id(), '_wc_litmos_user_id', $litmos_user['Id'] ) ) {
									//throw new Exception( __( 'Could not set Litmos user ID as user meta', WC_Litmos::TEXT_DOMAIN ) );
								//}
							//}
			
							$litmos_user_id = $litmos_user['Id'];
							
						}
			
						// reset the course results for user if purchasing the same course as before
						if ( 'yes' == get_option( 'wc_litmos_reset_course_duplicate_purchase' ) ) {
			
							// get courses for user
							$litmos_courses = $wc_litmos->get_api()->get_courses_assigned_to_user( $litmos_user_id );
			
							// check if any courses currently assigned are being purchased again & and reset the results
							foreach ( $litmos_courses as $litmos_course ) {
			
								if ( in_array( $litmos_course['Id'], $courses ) ) {
									//$wc_litmos->get_api()->reset_course_results( $litmos_user_id, $litmos_course['Id'] );
									$order->add_order_note( sprintf( __( 'Customer assigned to a duplicate course: %s', WC_Litmos::TEXT_DOMAIN ), $litmos_course['Id'] ));
								}
							}
						}
			
						// Assign purchased course(s) to user and do not send course invitation emails for blended courses
						$term_Training_Values = $this->get_attribute_values($student['course_id']);
							
						if( in_array('blended', $term_Training_Values) ){
							$this->apiLitmosCustom->assign_courses_to_user_custom( $litmos_user_id, $courses );
						}
						else{
							$wc_litmos->get_api()->assign_courses_to_user( $litmos_user_id, $courses );
						}
			
						// set 'Customer Assigned to Course...' order note(s)
						foreach ( $courses as $course_id ) {
							$course_info = $wc_litmos->get_api()->get_course_by_id( $course_id );
							$order->add_order_note( sprintf( __( 'Customer assigned to course: %s', WC_Litmos::TEXT_DOMAIN ), $course_info['Name'] ) );
						}
			
						// Update the student table with Litmos User ID
						$this->wrc_update_ckv_student_info('ckv_student_info', $order_id, '', $litmos_user_id, $username, $student['course_id'], $student['first_name'], $student['last_name']);
							
					}
			
					catch( Exception $e ) {
			
						// Log error as order note
						$order->add_order_note( $e->getMessage() );
					}
		
					//Reset for other students
					$user                  = array();
					$courses               = array();
					$isLitmosEnabledCourse = false;
				}
			
			endforeach;
		} // end if
				
	}
	
	
	/**
	 * Check for product category to look for: elearning Litmos enabled, JHSC Cert1 and JHSC Cert2 courses
	 *
	 * @since 1.0.0
	 */
	private function wrc_woocommerce_check_for_product_category($postID, $lookForCategory){
		$found = false;
		
		$productCategories = wp_get_post_terms( $postID, 'product_cat' );
		
		foreach ($productCategories as $k => $v) :
			if ( $v->slug == 'elearning' && $lookForCategory == 'elearning' ) :                
				$found = true;
			elseif ( $v->slug == 'jhsc-certification-part-1' && $lookForCategory == 'jhsc-certification-part-1' ) :                
				$found = true;
			elseif ( $v->slug == 'jhsc-certification-part-2' && $lookForCategory == 'jhsc-certification-part-2' ) :                
				$found = true;
			endif;
		endforeach;
		
		return $found;
	}
	
	/**
	 * Enqueue scripts.
	 *
	 * Enqueue any java and style scripts.
	 *
	 * @since 1.0.0
	 */
	public function wcr_enqueue_scripts() {
		wp_enqueue_script( 'woocommerce-checkout-registration', plugins_url( '/assets/js/woocommerce-checkout-registration.js', __FILE__ ), array( 'jquery' ) );
		//wp_enqueue_script('canada-post-addresscomplete-js', '//ws1.postescanada-canadapost.ca/js/addresscomplete-2.30.min.js?key=nm89-jc91-wx19-yx59"');
		//wp_enqueue_style('canada-post-addresscomplete-css', '//ws1.postescanada-canadapost.ca/css/addresscomplete-2.30.min.css?key=nm89-jc91-wx19-yx59');
	}

	/**
	 * Get number of fields.
	 *
	 * Get the number of student fields that need to be filled in during checkout.
	 *
	 * @since 1.0.0
	 */
	public function wcr_num_student_fields() {

		$categories = get_option( 'student_data_categories' );
		if ( ! $categories ) :
			return '0';
		endif;


		$fields = 0;
		foreach ( WC()->cart->cart_contents as $product ) :

			if ( has_term( $categories, 'product_cat', $product['product_id'] ) ) :
				$fields += $product['quantity'];
			endif;

		endforeach;

		return $fields;

	}

	
	// Look for all chained products in the cart that have special discounts
	function check_for_product_discounts_in_checkout($my_cart) {
		global $wc_chained_products;
		$chained_products_in_cart = array();
		
		foreach ( $my_cart->get_cart() as $cart_item_key => $cart_item ) {
			if( in_array('promotion', get_course_attributes($cart_item['product_id'], 'pa_discount')) &&
					$wc_chained_products->has_chained_products($cart_item['product_id']) ) {
				$chained_products_details = $wc_chained_products->get_all_chained_product_details($cart_item['product_id']);
				$chained_product_ids      = ( is_array( $chained_products_details ) ) ? array_keys( $chained_products_details ) : null;
				$chained_products_in_cart[$cart_item['product_id']] = $chained_product_ids;
			}
		}
			   
		return $chained_products_in_cart;
	}
	
	// Look for the chained products in the cart
	function look_for_chained_product_in_cart_array($look_for, $arr) {
	   foreach ($arr as $k => $v) {
		   foreach ($v as $k2 => $v2) {
		       if ($look_for == $v2) {
		           //echo "<br><br><br><br>searcharray found: ".$k.": ".$k2."=>".$v2;
		           return true;
		       }
       		}
	   } 
	   return false;
	}
	
	/**
	 * Checkout fields.
	 *
	 * Add extra checkout fields when there are certain products in the cart.
	 *
	 * @since 1.0.0
	 */
	public function wcr_woocommerce_checkout_fields() {
		
		global $woocommerce, $wc_chained_products;
		$count            = 0;
		$uniqueCoursesArr = array();
		$chained_products = array();

		foreach($woocommerce->cart->get_cart_item_quantities() as $k=>$v){
			$arrStudentsProducts[$count] = $k;
			if ($v>1){
				for($j=1;$j<$v;$j++){
					//echo "more:".$k."=>".$v."<br>";
					$count++;
					$arrStudentsProducts[$count] = $k;
				}
			}
			$count++;
		}
		
		$num_fields = $this->wcr_num_student_fields();

		//if ( $num_fields <= 1 ) :  //Omar
		if ( $num_fields < 1 || $woocommerce->cart->get_cart_contents_count() <> $num_fields) :
			return;
		endif;
		
		// Look for all chained products in the cart that have special discounts
		$chained_products = $this->check_for_product_discounts_in_checkout(WC()->cart);
		
		?>
		
		<style>
			.one-third { width: 20%; float: left; margin: 2% 0 2% 0; font-size: 12px;} 
			.half { width: 40%; float:left;}
			.one-third-label { float: left; margin-bottom: 3%; } 
			
			.form-heading .required { border: 0; color: red; }
			/*
			.form-heading { padding: 3px; } 
			.coursetitlecls, .courseparticipantcls, .participantdivcls, .companydivcls{ 
					margin: 2% 0 2% 0; clear: both; font-size: 16px;
			};
			*/

		</style>
		
		<div class="col2-set" id="customer_details">

			<div class="woocommerce-student-fields">
				<h2><?php _e( 'Participant information', 'woocommerce-checkout-registration' ); ?></h2>
		
				<?php
					
					for ( $i = 0; $i < $num_fields; $i++ ){
					if (!in_array($arrStudentsProducts[$i], $uniqueCoursesArr)){
						$uniqueCoursesArr[$arrStudentsProducts[$i]] = $arrStudentsProducts[$i];
						
						//Get all the terms
						$term_Training_Values = $this->get_attribute_values($arrStudentsProducts[$i]);
						
						// Don't show for the chained products with attribute discount promotion 
						if( in_array('promotion', $this->get_product_terms($arrStudentsProducts[$i], 'pa_discount')) && 
								$wc_chained_products->is_chained_product($arrStudentsProducts[$i]) && 
								$this->look_for_chained_product_in_cart_array( $arrStudentsProducts[$i], $chained_products ) ){
						}
						else{
							//Show the parent product title only for blended courses otherwise show the simple product title
							if( in_array('blended', $term_Training_Values) ){
								echo "<div id='coursetitle_".$arrStudentsProducts[$i]."' class='coursetitlecls'>".get_post(get_post_meta($arrStudentsProducts[$i], 'product_custom_parent_id', true))->post_title."</div>";
								//echo "<p style='float:left;'>Please enter the required information for each learner by clicking the boxes below. Please note that the names entered at this time will be the names that appear on completion certificates.</p>";
							}
							else{
								echo "<div id='coursetitle_".$arrStudentsProducts[$i]."' class='coursetitlecls'>".get_post($arrStudentsProducts[$i])->post_title."</div>";
								//echo "<p style='float:left;'>Please note: Enter the name that you want to appear on your certificate when you register</p>";
							}
						}
						
						// Look for parent & chained products for discount promotion
// 						if ( in_array('promotion', $this->get_product_terms($arrStudentsProducts[$i], 'pa_discount')) &&
// 								$wc_chained_products->has_chained_products($arrStudentsProducts[$i]) ){
// 							$chained_products_details = $wc_chained_products->get_all_chained_product_details($arrStudentsProducts[$i]);
// 							$chained_product_ids      = ( is_array( $chained_products_details ) ) ? array_keys( $chained_products_details ) : null;
// 							$chained_products[$arrStudentsProducts[$i]] = $chained_product_ids;
// 						}			
					}
									
					//Course Type if any
					if (in_array('blended', $term_Training_Values)) :
						echo "<input type='hidden' id='student[{$i}][course_type]' name='student[{$i}][course_type]' value='blended' />";
					endif;
				
					//Parent Product if any
					if (get_post_meta($arrStudentsProducts[$i], 'product_custom_parent_id', true) != '') :
						echo "<input type='hidden' id='student[{$i}][parent_course_id]' name='student[{$i}][parent_course_id]' value='".get_post_meta($arrStudentsProducts[$i], 'product_custom_parent_id', true)."'/>";
					endif;
						
					//Simple Course ID
					echo "<input type='hidden' id='student[{$i}][course_id]' name='student[{$i}][course_id]' value='$arrStudentsProducts[$i]' />";
					
					//Check for MOL Extended courses
					if( in_array('molextended', $term_Training_Values) ){

						echo "<input type='hidden' id='student[{$i}][molextended_course]' name='student[{$i}][molextended_course]' value='yes' />";
						?>
						
						<div id="<?php echo 'p_'.$i; ?>" data-product-id="<?php echo $arrStudentsProducts[$i]; ?>" class='participantdivcls'>						
							
							<h2 class="toggle togglesectionblendedcls closed" id="<?php echo $i; ?>" data-id="<?php echo $i; ?>" data-type="stu">Click here to enter participant personal information<span class="arrow"></span></h2>
						
							<div id="<?php echo $i; ?>" class="courseparticipantcls studentmolextendedcls">
								
								<?php woocommerce_form_field( "student[{$i}][same_info]", array( 'class' => array('billingmolextendedcopycls'), 'type' => 'checkbox', 'label' => 'Same as billing information?' ) ); ?>
							
								<?php if( in_array('isrefresher', $term_Training_Values) ): ?> 
									<label class="form-heading one-third-label" style="clear:both;"><?php _e( 'MOL Learner ID number (if known):', 'woocommerce-checkout-registration' ); ?></label>
									<?php woocommerce_form_field( "student[{$i}][mollearnerid]", array( 'class' => array( 'one-third', 'studentbcls' ), 'required' => false ) ); ?>
									<input type='hidden' id='student[<?php echo $i; ?>][isrefresher]' name='student[<?php echo $i; ?>][isrefresher]' value='yes' />
								<?php endif; ?>
								
								<label class="form-heading one-third-label" style="clear:both;"><?php _e( 'First Name', 'woocommerce-checkout-registration' ); ?>
									<abbr class="required" title="required">*</abbr></label>
								<?php woocommerce_form_field( "student[{$i}][first_name]", array( 'class' => array( 'one-third', 'studentbcls' ), 'required' => true ) ); ?>
								
								<label class="form-heading one-third-label"><?php _e( 'Last Name', 'woocommerce-checkout-registration' ); ?>
									<abbr class="required" title="required">*</abbr></label>
								<?php woocommerce_form_field( "student[{$i}][last_name]", array( 'class' => array( 'one-third', 'studentbcls' ), 'required' => true ) ); ?>
								
								<label class="form-heading one-third-label"><?php _e( 'Email', 'woocommerce-checkout-registration' ); ?>
									<abbr class="required" title="required">*</abbr></label>
								<?php woocommerce_form_field( "student[{$i}][email]", array( 'class' => array( 'one-third', 'studentemailbcls'), 'validate' => array( 'email'), 'required' => true) ); ?>				
								
								<label class="form-heading one-third-label" style='clear:both;'><?php _e( 'Street Number', 'woocommerce-checkout-registration' ); ?>
									<abbr class="required" title="required">*</abbr></label>
								<?php woocommerce_form_field( "student[{$i}][street_number]", array( 'class' => array( 'one-third', 'studentbcls' ), 'required' => true ) ); ?>
								
								<label class="form-heading one-third-label"><?php _e( 'Street Name', 'woocommerce-checkout-registration' ); ?>
									<abbr class="required" title="required">*</abbr></label>
								<?php woocommerce_form_field( "student[{$i}][street]", array( 'class' => array( 'one-third', 'studentbcls' ), 'required' => true ) ); ?>
								
								<label class="form-heading one-third-label"><?php _e( 'City', 'woocommerce-checkout-registration' ); ?>
									<abbr class="required" title="required">*</abbr></label>
								<?php woocommerce_form_field( "student[{$i}][city]", array( 'class' => array( 'one-third', 'studentbcls' ), 'required' => true ) ); ?>
								
								<label class="form-heading one-third-label" style='clear:both;'><?php _e( 'Province', 'woocommerce-checkout-registration' ); ?>
									<abbr class="required" title="required">*</abbr></label>				
								<?php woocommerce_form_field( "student[{$i}][province]", array( 'type' => 'select', 'required' => true, 
											'class' => array( 'one-third', 'studentbcls' ), 'options' => array(
											''   => __('Please Select a Province...', 'woocommerce'),
											'AB' => __('Alberta', 'woocommerce'),
											'BC' => __('British Columbia', 'woocommerce'),
											'MB' => __('Manitoba', 'woocommerce' ),
											'NB' => __('New Brunswick', 'woocommerce'),
											'NL' => __('Newfoundland and Labrador', 'woocommerce'),
											'NT' => __('Northwest Territories', 'woocommerce'),
											'NS' => __('Nova Scotia', 'woocommerce'),
											'NU' => __('Nunavut', 'woocommerce'),
											'ON' => __('Ontario', 'woocommerce'),
											'PE' => __('Prince Edward Island', 'woocommerce'),
											'QC' => __('Quebec', 'woocommerce'),
											'SK' => __('Saskatchewan', 'woocommerce'),
											'YT' => __('Yukon Territory', 'woocommerce')))); ?>
								
								<label class="form-heading one-third-label"><?php _e( 'Postal Code', 'woocommerce-checkout-registration' ); ?>
									<abbr class="required" title="required">*</abbr></label>
								<?php woocommerce_form_field( "student[{$i}][postal_code]", array( 'class' => array( 'one-third', 'studentbcls','studentbpostcls' ), 'required' => true ) ); ?>
								
								<label class="form-heading one-third-label"><?php _e( 'P.O. Box', 'woocommerce-checkout-registration' ); ?></label>
								<?php woocommerce_form_field( "student[{$i}][box_office]", array( 'class' => array( 'one-third' ) ) ); ?>
								
								<label class="form-heading one-third-label" style='clear:both;'><?php _e( 'Tel/Cell', 'woocommerce-checkout-registration' ); ?>
									<abbr class="required" title="required">*</abbr></label>
								<?php woocommerce_form_field( "student[{$i}][phone]", array( 'class' => array( 'one-third', 'studentbcls' ), 'required' => true ) ); ?>
							</div>
						</div>
						
					<?php 
					}
					// Regular courses - Not molextended course
					else{ 
						
						// Look for chained bundled courses with only discount promotion attribute
						if( in_array('promotion', $this->get_product_terms($arrStudentsProducts[$i], 'pa_discount')) &&
							$wc_chained_products->is_chained_product($arrStudentsProducts[$i]) && 
							$this->look_for_chained_product_in_cart_array( $arrStudentsProducts[$i], $chained_products ) ){ ?>
								<input name="student[<?php echo $i; ?>][first_name]" id="student[<?php echo $i; ?>][first_name]" type="hidden" value="<?php echo $arrStudentsProducts[$i].'_'.$i; ?>" />
								<input name="student[<?php echo $i; ?>][last_name]"  id="student[<?php echo $i; ?>][last_name]"  type="hidden" value="<?php echo $arrStudentsProducts[$i].'_'.$i; ?>" />
								<input name="student[<?php echo $i; ?>][email]"      id="student[<?php echo $i; ?>][email]"      type="hidden" value="<?php echo $arrStudentsProducts[$i].'_'.$i.'@example.com'; ?>" />
						<?php } else{ ?>
						
						<div id="<?php echo 'p_'.$i; ?>" data-product-id="<?php echo $arrStudentsProducts[$i]; ?>" class='participantdivcls'>							
							<h2 id="<?php echo $i; ?>" data-id="<?php echo $i; ?>" class="toggle togglesectioncls closed" data-type="stu">Click here to enter participant personal information<span class="arrow"></span></h2>
							
							<div id="<?php echo $i; ?>" class="courseparticipantcls studentregularcls">
							
								<?php woocommerce_form_field( "student[{$i}][same_info]", array( 'class' => array('billingregularcopycls'), 'type' => 'checkbox', 'label' => 'Same as billing information?' ) ); ?>

								<label class="form-heading one-third-label" style='clear:both;'><?php _e( 'First Name', 'woocommerce-checkout-registration' ); ?>
									<abbr class="required" title="required">*</abbr></label>
								<?php woocommerce_form_field( "student[{$i}][first_name]", array( 'class' => array( 'one-third', 'studentcls' ), 'required' => true ) ); ?>
								
								<label class="form-heading one-third-label"><?php _e( 'Last Name', 'woocommerce-checkout-registration' ); ?>
									<abbr class="required" title="required">*</abbr></label>
								<?php woocommerce_form_field( "student[{$i}][last_name]", array( 'class' => array( 'one-third', 'studentcls' ), 'required' => true ) ); ?>
								
								<?php $Email = (in_array('ishsms', $term_Training_Values)) ? 'Personal or Work Email' : 'Email'; ?>
								<label class="form-heading one-third-label"><?php _e( $Email, 'woocommerce-checkout-registration' ); ?>
									<abbr class="required" title="required">*</abbr></label>
								<?php woocommerce_form_field( "student[{$i}][email]", array( 'class' => array( 'one-third', 'studentemailcls'), 'validate' => array( 'email'), 'required' => true ) );	?>
								
								<?php if( in_array('ishsms', $term_Training_Values) ): ?> 
									<label class="form-heading one-third-label" style='clear:both;'><?php _e( 'Personal or Work Phone Number', 'woocommerce-checkout-registration' ); ?></label>
									<?php woocommerce_form_field( "student[{$i}][work_phone]", array( 'class' => array( 'one-third', 'studentcls' ), 'required' => false ) ); ?>
									<input type='hidden' id='student[<?php echo $i; ?>][ishsms]' name='student[<?php echo $i; ?>][ishsms]' value='yes' />
								<?php endif; ?>
							</div>
						</div>
				  <?php		
			  	 		}
		  	 		}
			   } //end for
			   
			   ?>
		   		<!-- Store the discounted promotion chained products if any 
				<input type="hidden" id='chained_products' name='chained_products' value='<?php echo json_encode($chained_products); ?>' /> -->
				<input type="hidden" id='chained_products' name='chained_products' value='<?php echo serialize($chained_products); ?>' />
				
			</div>
		</div>
	
		<?php
		
	}
	
	/**
	 * Validate checkout fields.
	 *
	 * Validate if checkout fields are not empty and if emails are valid.
	 *
	 * @since 1.0.0
	 */
	public function wcr_woocommerce_validate_checkout_fields( $posted ) {
		
		$errorRequired        = 0;
		$errorValidEmail      = 0;
		$errorValidPostalCode = 0;
		
		if ( ! isset( $_POST['student'] ) ) :
			return;
		endif;
		
		foreach ( $_POST['student'] as $key => $student ) :
			
			foreach ( $student as $key => $field ) :
				// Add an exception for P.O. Box, mollearnerid, and HSMS work_phone fields because they are optional
				if ( $key != 'box_office' && $key != 'mollearnerid' && $key != 'work_phone' ): 
					if ( empty( $field ) ) :                                //Validate required
						$errorRequired = 1;                                 
					elseif ( $key == 'email' && ! is_email( $field ) ) :    //Validate email
						$errorValidEmail = 1;
					elseif ($key == 'postal_code' && !preg_match( "/^([ABCDEFGHIJKLMNOPQRSTUVWXYZ]\d[ABCDEFGHIJKLMNOPQRSTUVWXYZ])\ {0,1}(\d[ABCDEFGHIJKLMNOPQRSTUVWXYZ]\d)$/i", $field ) ) :
						$errorValidPostalCode = 1;            //Validate Postal code
						wc_add_notice( __( 'Postal Code: '.$field.' is not valid in the Participant information section', 'woocommerce-checkout-registration' ), 'error' );
					endif;
				endif;
			endforeach;

		endforeach;
		
		// Required fields
		if ( $errorRequired ) :
			wc_add_notice( __( 'All student fields are required.', 'woocommerce-checkout-registration' ), 'error' );
		endif;
		
		// Validate email address field 
		if( $errorValidEmail ) :
			wc_add_notice( __( 'Email address should be in the format: example@example.com.', 'woocommerce-checkout-registration' ), 'error' );
		endif;
	    
		//No required or email validation or postal code errors
		if ( $errorRequired == 0 && $errorValidEmail == 0 && $errorValidPostalCode == 0 ) :
		
			// Fetch all courses fields
			$postFields = $this->wrc_woocommerce_get_fields($_POST);
			
			if (sizeof($postFields) > 0) :
				$this->wrc_woocommerce_validate_duplicate_emails($postFields);
			endif;
			
		endif;		
		
	}

	
	/**
	  * Fetch all courses fields
	  *
	  * @since 1.0.0
	*/
	private function wrc_woocommerce_get_fields($posted){
		$i          = 0;
		$postFields = [];

		foreach ( $posted['student'] as $key => $student ) :
		
			//$productCategories = wp_get_post_terms( $student['course_id'], 'product_cat' );
			
			//foreach ($productCategories as $k => $v) :
				//if ($v->name == 'eLearning') :                   //Hard coded for now
					//echo "found:".$student['course_id']."-".$student['email']."<br>";
					$postFields[$i]['course_id']  = $student['course_id'];
					$postFields[$i]['first_name'] = $student['first_name'];
					$postFields[$i]['last_name']  = $student['last_name'];
					$postFields[$i]['email']      = $student['email'];
				//endif;
			//endforeach;	
			$i++;
			
		endforeach;
		
		return $postFields;
	}
	

	/**
	 * Validate checkout fields.
	 *
	 * No emails can be repeated for the same course
	 *
	 * @since 1.0.0
	 */
	private function wrc_woocommerce_validate_duplicate_emails($posted){
		
		$error                   = 0;
		$count                   = 0;
		$uniqueCoursesIDs        = array();
		$coursesStudents         = array();
		$inputEmailsCourses      = array();
		$posAllEmailFieldsInForm = array();
		
		
		foreach ( $posted as $key => $student ) :
			
			if ($student['email'] != "") :
				$student['email']                         = str_replace(",", "",trim($student['email']));
				$inputEmailsCourses [$count]['email']     = $student['email'];
				$inputEmailsCourses [$count]['course_id'] = $student['course_id'];
				$posAllEmailFieldsInForm[$key]            = $student['email'];
				$count++;
			
				if(!array_key_exists($student['course_id'], $uniqueCoursesIDs)) : 	
					$uniqueCoursesIDs[$student['course_id']] = $student['course_id'];
					$coursesStudents[$student['course_id']]  = $student['email'];
				else :
					//echo "dup course ID: ". $student['course_id']. "=>". $student['email']."<br>";
					$coursesStudents[$student['course_id']] = $coursesStudents[$student['course_id']].",".$student['email'];
				endif;
			endif;
			
		endforeach;
		
		foreach($coursesStudents as $k=>$v) :
			
			if (count(array_unique(explode(",", $v))) < count(explode(",", $v))) :
				$error = 1;
				$this->wrc_woocommerce_script_validate_unique_courses_emails(explode(",", $v), $posAllEmailFieldsInForm, $k);
			endif;
		endforeach;
			
		if ($error == 0) :
			$this->wrc_woocommerce_validate_litmos_user_assigned_courses($inputEmailsCourses);
		endif;
			
	}
	
	/*
	 * Validate that the emails are unique per each course using script
	 * @since 1.0.0
	*/
	function wrc_woocommerce_script_validate_unique_courses_emails($postAllEmailsPerCourse, $posAllEmailFieldsInForm, $course_id){
		
		$posduplidateEmailsPerCourse  = array();
		$duplicatePerCourse           = array();
		$duplicatePerCourseIDs        = array();
		$duplidateEmailsPerCourse     = array_diff_assoc($postAllEmailsPerCourse, array_unique($postAllEmailsPerCourse));
		
		foreach($duplidateEmailsPerCourse as $key => $value){
			//echo "<br>".$key ."=>". $value."Course:". get_post($course_id)->post_title."<br>";
			$posduplidateEmailsPerCourse = array_keys($posAllEmailFieldsInForm, $value);
			$duplicatePerCourse[$value]    = get_post($course_id)->post_title;
			$duplicatePerCourseIDs[$value] = $course_id;
		}
		
		foreach ($duplicatePerCourse as $k => $v){
			//Fetch all the terms for this product
			$term_duplicate_course_Values = $this->get_attribute_values($duplicatePerCourseIDs[$k]);
			
			//Check for grouped products only for blended courses
			$duplicateCourseTitle = ( get_post_meta($duplicatePerCourseIDs[$k], 'product_custom_parent_id', true) != '' && in_array('blended', $term_duplicate_course_Values) )? get_post(get_post_meta($duplicatePerCourseIDs[$k], 'product_custom_parent_id', true))->post_title : $v;
			wc_add_notice( __( 'You can\'t enter the same Email: <b>'.$k.'</b> for the same Course: <b>'.$duplicateCourseTitle.'</b>.', 'woocommerce-checkout-registration' ), 'error' );
		}
			
	}
	
	 
	/**
	 * If we reached this point and passed all client validations then we need to server validate with Litmos:
	 * If the user is part of Litmos, he can't register for the same course twice
	 * @since 1.0.0
	*/
	function wrc_woocommerce_validate_litmos_user_assigned_courses($inputEmailsCourses){
		
		global $wc_litmos;
		$litmos_user_courses = array();
	
		foreach ( $inputEmailsCourses as $key => $value ) :
		
			if ($this->wrc_woocommerce_check_for_product_category($value['course_id'], 'elearning')){
			
				$inputEmail = sanitize_user($value['email'], true);
				
				//$litmos_user_id = $wc_litmos->get_api()->get_user_id_by_username( $inputEmail );
				$litmos_user_id = $this->apiLitmosCustom->get_user_id_by_username_custom( $inputEmail );
				
				//echo "<br><br>inputEmail=".$inputEmail.", course_id=".$value['course_id'].", litmos_user_id=".$litmos_user_id."<br><br>";
				
				//Check if the user is already registered with Litmos
				if ( !empty( $litmos_user_id ) && $litmos_user_id ) :
					$productmeta = wc_get_product($value['course_id']);
					$sku         = $productmeta->get_sku();
					
					$litmos_user_courses = $wc_litmos->get_api()->get_courses_assigned_to_user( $litmos_user_id );
					
					//Check if the user have registered for this course
					foreach ($litmos_user_courses as $kcourse => $vcourse) :
					    
						if ( ($vcourse['Code'] == $sku) && $sku != '' ) :
							//$course_id   = get_post_meta( $value , '_wc_litmos_course_id', true );
							//$course_info = $wc_litmos->get_api()->get_course_by_id( $course_id );
							
							$msg = "The email: <b>".$inputEmail."</b> is already registered with Litmos for Course: <b>".$vcourse['Name']."</b> You must choose another email.";
							wc_add_notice( __( $msg, 'woocommerce-checkout-registration' ), 'error' );
						endif;
					endforeach;			
				endif;
				
				$litmos_user_courses = array();
			
			}
			
		endforeach;
	
	}
	
	
	/**
	 * Validate checkout fields for the bundeled discounted products against Litmos. 
	 * Should be the last validation run.
	 * @since 1.0.0
	 */
	public function wcr_woocommerce_validate_checkout_fields_bundeled_courses( $posted ) {
		
		// Rebuild the $_POST fields with the new discounted promotion chained products and validate the new $_POST fields against Limtos
		if ( sizeof(unserialize($_POST['chained_products'])) > 0 && ! empty($_POST['student']) ) {
			
			$POST_student          = $_POST['student'];
			$POST_chained_products = unserialize($_POST['chained_products']);	
			$POST_chained_arr      = array();
			$bundeledEmailsCourses = array();
			
			foreach($POST_student as $k => $v){
				if ( array_key_exists($v['course_id'], $POST_chained_products) ){
					$POST_chained_arr[$k]['course_id']  = $_POST['student'][$k]['course_id'];
					$POST_chained_arr[$k]['first_name'] = $_POST['student'][$k]['first_name'];
					$POST_chained_arr[$k]['last_name']  = $_POST['student'][$k]['last_name'];
					$POST_chained_arr[$k]['email']      = $_POST['student'][$k]['email'];
				}
					
				foreach($POST_chained_products as $k2 => $v2){
					if (in_array($v['course_id'], $v2)){
						unset($_POST['student'][$k]);
					}
				}
			}
					
			$_POST['student'] = array_values($_POST['student']); // rearrange the array into numeric keys to get the correct size	
			$new_index        = sizeof($_POST['student']);
			
			foreach($POST_chained_arr as $k => $v){
				foreach($POST_chained_products[$v['course_id']] as $k2 => $v2){
					$_POST['student'][$new_index]['course_id']      = $v2;
					$_POST['student'][$new_index]['first_name']     = $v['first_name'];
					$_POST['student'][$new_index]['last_name']      = $v['last_name'];
					$_POST['student'][$new_index]['email']          = $v['email'];
					$bundeledEmailsCourses[$new_index]['email']     = $v['email'];
					$bundeledEmailsCourses[$new_index]['course_id'] = $v2;
					$new_index++;
				}
			}
			
			//print_r($_POST['student']); print_r($bundeledEmailsCourses);
			if (sizeof($bundeledEmailsCourses) > 0){
				$this->wrc_woocommerce_validate_litmos_user_assigned_courses($bundeledEmailsCourses);	
			}
		}
		
	}
		
	
	/**
	 * Save checkout fields.
	 *
	 * Get the checkout fields from POST and save in post_meta table.
	 * Added the bundeled promotion discount products if any
	 * @since 1.0.0
	 */
	public function wcr_save_checkout_fields( $order_id, $posted ) {
		
		if ( ! empty( $_POST['student'] ) ) :
			update_post_meta( $order_id, '_student_information', $_POST['student'] );
		endif;	

		
		/* If only 1 student is required, use the billing info // Omar
		if ( 1 == $this->wcr_num_student_fields() ) :

			$order = new WC_Order( $order_id );
			$students = array(
				array(
					'first_name' 	=> $order->billing_first_name,
					'last_name' 	=> $order->billing_last_name,
					'email' 		=> $order->billing_email
				)
			);
			update_post_meta( $order_id, '_student_information', $students );

		endif;
		*/
		
		// Save to remote SQL
		$this->wcr_save_student_info_remote_database($order_id);

	}

	/**
	 * Helper functions to update custom table: ckv_student_info with new order status and send an email for molextended courses only
	 * @since 1.0.0
	*/
	function wrc_order_status_processing($order_id) {
		$this->wrc_update_ckv_student_info('ckv_student_info', $order_id, 'processing');
		
		if ( !is_admin() ):
			$studentCourses = $this->FetchStudentDBRecords( $order_id );
			$order          = wc_get_order( $order_id );
			
			// Send an email only for MOL extended courses		
	 		foreach($studentCourses as $k => $v){
			 	if ($v->MOLExtended == 'yes' && $v->courseType != 'blended'):
			 	
					$course_title = ( $v->parentCourseID != 0 ) ? html_entity_decode(get_post($v->parentCourseID)->post_title) : html_entity_decode(get_post($v->CourseID)->post_title);
					$course_type['molextended'] = 'molextended';
					$course_type['blended']     = '';
					
					if ($v->isrefresher == 'yes'):
						$course_type['isrefresher'] = 'isrefresher';
					endif;

					if( $this->send_registerant_email($v->FirstName, $v->LastName, $v->Email, '', $course_type, '', $this->ccteamEmail, $course_title, $v->CourseID, $order) ):
						$this->debug_log('Email for processing order is successfully sent to student: '.$v->FirstName.' '.$v->LastName.', Email: '.$v->Email.' for course: '.$v->CourseID);
						$order->add_order_note( __( 'Email for processing order is successfully sent to student: '.$v->FirstName.' '.$v->LastName.', Email: '.$v->Email.' for course: '.$v->CourseID, 'woocommerce-checkout-registration' ) );		
					else:
						$this->debug_log('Error! Email for processing order is not sent to student: '.$v->FirstName.' '.$v->LastName.', Email: '.$v->Email.' for course: '.$v->CourseID);
						$order->add_order_note( __( 'Error! Email for processing order is not sent to student: '.$v->FirstName.' '.$v->LastName.', Email: '.$v->Email.' for course: '.$v->CourseID, 'woocommerce-checkout-registration' ) );		
					endif;	
				endif;	
																		
				// Increase stock only for blended products as woocommerce decreased it and it should not count towards number of available sessions
				if ( $v->MOLExtended == 'yes' && $v->courseType == 'blended' && wc_update_product_stock(wc_get_product($v->CourseID), 1, 'increase') ):
			 		$order->add_order_note( __( 'Blended product: '.$v->CourseID.' stock level is increased by 1, current stock level is: '.
							wc_get_product( $v->CourseID )->get_stock_quantity(), 'woocommerce-checkout-registration' ) );
				endif;	
			}
		endif;
    }
	
    function wrc_order_status_completed($order_id) {
	    $this->wrc_update_ckv_student_info('ckv_student_info', $order_id, 'completed');
	}
	
	function wrc_order_status_pending($order_id) {
    	$this->wrc_update_ckv_student_info('ckv_student_info', $order_id, 'pending');
	}
	
	/**
	 * Helper functions to update custom table: ckv_student_info with new order status and send an email for molextended courses only
	 * @since 1.0.0
	*/
	function wrc_order_status_failed($order_id) {
	    $this->wrc_update_ckv_student_info('ckv_student_info', $order_id, 'failed');
	    
	    if ( !is_admin() ):
		    $studentCourses = $this->FetchStudentDBRecords( $order_id ); 
			$order          = wc_get_order( $order_id );
			
			// Send an email for all failed odrers		
	 		foreach($studentCourses as $k => $v){
				$course_title = ( $v->parentCourseID != 0 ) ? html_entity_decode(get_post($v->parentCourseID)->post_title) : html_entity_decode(get_post($v->CourseID)->post_title);
		 		
				if ($v->MOLExtended == 'yes'):
					$course_type['molextended'] = 'molextended';
				endif;
				
				if ($v->courseType != 'blended'):
					$course_type['blended'] = '';
				endif;
				
				if ($v->isrefresher == 'yes'):
					$course_type['isrefresher'] = 'isrefresher';
				endif;
				
				if ($v->ishsms == 'yes'):
					$course_type['ishsms'] = 'ishsms';
				endif;

				if( $this->send_registerant_email($v->FirstName, $v->LastName, $v->Email, '', $course_type, '', $this->ccteamEmail, $course_title, $v->CourseID, $order) ):
					$this->debug_log('Email for failed order is successfully sent to student: '.$v->FirstName.' '.$v->LastName.', Email: '.$v->Email.' for course: '.$v->CourseID);
					$order->add_order_note( __( 'Email for failed order is successfully sent to student: '.$v->FirstName.' '.$v->LastName.', Email: '.$v->Email.' for course: '.$v->CourseID, 'woocommerce-checkout-registration' ) );		
				else:
					$this->debug_log('Error! Email for failed order is not sent to student: '.$v->FirstName.' '.$v->LastName.', Email: '.$v->Email.' for course: '.$v->CourseID);
					$order->add_order_note( __( 'Error! Email for failed order is not sent to student: '.$v->FirstName.' '.$v->LastName.', Email: '.$v->Email.' for course: '.$v->CourseID, 'woocommerce-checkout-registration' ) );		
				endif;		
			}
		endif;
		
	}
	
	function wrc_order_status_on_hold($order_id) {
	    $this->wrc_update_ckv_student_info('ckv_student_info', $order_id, 'on-hold');
	}
	
	function wrc_order_status_refunded($order_id) {
	    $this->wrc_update_ckv_student_info('ckv_student_info', $order_id, 'refunded');
	}
	
	function wrc_order_status_cancelled($order_id) {
		$this->wrc_update_ckv_student_info('ckv_student_info', $order_id, 'cancelled');
	}

	
	/**
	 *
	 * Update custom table: ckv_student_info with new order status and Litmos User ID and send a notification email for elearning courses only
	 *
	 * @since 1.0.0
	*/
	function wrc_update_ckv_student_info($table, $order_id, $orderStatus = '', $litmos_user_id = '', $litmos_username = '', $course_id = '', $first_name = '', $last_name = ''){
		 
		 global $wpdb;
		 $order = wc_get_order( $order_id );
		 
		 //Update the Litmos User ID and send an email only for blended/elearning courses
		 if ($litmos_user_id != '' && $litmos_username != '' && $course_id != ''){
			 
			 $wp_user_id  = 0;		 
			 $course_type = array();
			 
			 $update = $wpdb->update( 
				 $table, 
				 array('litmosUserID' => $litmos_user_id), 
				 array('OrderID' => $order_id, 'Email' => $litmos_username, 'CourseID' => $course_id), 
				 array('%s'), 
				 array('%d','%s','%d') 
			 );
			 
			if (false === $update ) :
				$this->debug_log( "Error! Litmos User ID: $litmos_user_id could not be saved for Email: $litmos_username for Course: $course_id for Order # $order_id for table: ckv_student_info" );
				$order->add_order_note( __( "Error! Litmos User ID: $litmos_user_id could not be saved for Email: $litmos_username  for Course: $course_id for Order # $order_id for table: ckv_student_info", 'woocommerce-checkout-registration' ) );
			else :

				// Check the course types
				$term_Training_Values = $this->get_attribute_values($course_id);
				$blended              = (in_array('blended', $term_Training_Values))?     'blended'     : '';	
				$molextended          = (in_array('molextended', $term_Training_Values))? 'molextended' : '';
				$ishsms               = (in_array('ishsms', $term_Training_Values))?      'ishsms'      : '';
				
				// Build the SSO Litmos Link
				$sso_link = get_home_url().'/sso-litmos?uid='.$litmos_user_id;
				
				// Fetch the user temporary password
				$wp_user_id = $this->get_wp_user_id($litmos_username);
				$password   = ( $wp_user_id > 0 )? get_user_meta( $wp_user_id, 'temporary_login_pwd', true ) : '';
				
				// Send an email only for Blended courses ( has an eLearning component )
				if ( $blended == 'blended' && $this->wrc_woocommerce_check_for_product_category($course_id, 'elearning') ){
					$course_type['blended']     = $blended;
					$course_type['molextended'] = $molextended;
					$course_type['ishsms']      = $ishsms;
				
					if( $this->send_registerant_email($first_name, $last_name, $litmos_username, $password, $course_type, $sso_link, $this->ccteamEmail, html_entity_decode(get_post(get_post_meta($course_id, 'product_custom_parent_id', true))->post_title), $course_id, $order) ) :
						$this->debug_log("An email containing Single Sign on Litmos link is successfully sent to student: ".$first_name." ".$last_name.", Email: ".$litmos_username." for course: ".$course_id);
						$order->add_order_note( __( "An email containing Single Sign on Litmos link is successfully sent to student: ".$first_name." ".$last_name.", Email: ".$litmos_username." for course: ".$course_id, 'woocommerce-checkout-registration' ) );		
					else:
						$this->debug_log("Error! Email containing Single Sign on Litmos link is not sent to student: ".$first_name." ".$last_name.", Email: ".$litmos_username." for course: ".$course_id);
						$order->add_order_note( __( "Error! Email containing Single Sign on Litmos link is not sent to student: ".$first_name." ".$last_name.", Email: ".$litmos_username." for course: ".$course_id, 'woocommerce-checkout-registration' ) );		
					endif;	
				}
							
				$this->debug_log( "Litmos SSO Link: $sso_link, Litmos User ID: $litmos_user_id is saved for Email: $litmos_username  for Course: $course_id for Order # $order_id for table: ckv_student_info" );
				$order->add_order_note( __( "Litmos SSO Link: $sso_link, Litmos User ID: $litmos_user_id is saved for Email: $litmos_username  for Course: $course_id for Order # $order_id for table: ckv_student_info", 'woocommerce-checkout-registration' ) );
			endif;
		 } //end if
		 
		 //Update the order status
		 if ($orderStatus != ''){
			 $update = $wpdb->update( 
				 $table, 
				 array('orderStatus' => $orderStatus), 
				 array('OrderID' => $order_id), 
				 array('%s'), 
				 array('%d') 
			 );
			 
			if (false === $update ) :
				$this->debug_log( "Error! Order # $order_id status could not be changed to $orderStatus for table: ckv_student_info" );
				$order->add_order_note( __( "Error! Order # $order_id status could not be changed to $orderStatus for table: ckv_student_info", 'woocommerce-checkout-registration' ) );
			else :
				$this->debug_log( "Order # $order_id status changed to $orderStatus for table: ckv_student_info" );
				$order->add_order_note( __( "Order # $order_id status changed to $orderStatus for table: ckv_student_info", 'woocommerce-checkout-registration' ) );
			endif;
	 	}
	 		 
	}
    
	/**
	 *
	 * Fetch the Student records from the DB for this order
	 *
	 * @since 1.0.0
	 */
	function FetchStudentDBRecords( $order_id ){
		global $wpdb;
		$table	    = get_option( 'wcr_sql_table' );
		$studentDBRecords = $wpdb->get_results( "SELECT * FROM ".$table." WHERE OrderID = ". $order_id );
		return $studentDBRecords;
	}
	
	/**
	 *
	 * Update manual payments from on-hold to processing for all orders
	 *
	 * @since 1.0.0
	 */
	function wrc_update_on_hold_manual_payment_to_processing($order_id){
		
		if ( !$order_id ) return;
		        
		$order         = wc_get_order( $order_id );
		$paymentMethod = get_post_meta( $order_id, '_payment_method', true );
		
		//Sanity check: as the hook is already fired for cheque payments only
		if ($paymentMethod == "cheque"){
			$order->update_status( 'processing' );
			update_post_meta( $order_id, '_paid_date', date( 'Y-m-d H:i:s' ) );  //added a DB field for manual payment to match credit cart payment
		} 		   
	}
	
	
	/**
	 * Save in 2nd SQL DB.
	 *
	 * Insert student information in 2nd database.
	 *
	 * @since 1.0.0
	 */
	public function wcr_save_student_info_remote_database( $order_id = '') {
		
		global $woocommerce;
		
		$username = get_option( 'wcr_sql_user' );
		$password = get_option( 'wcr_sql_password' );
		$database = get_option( 'wcr_sql_database' );
		$port 	  = get_option( 'wcr_sql_port' );
		$host 	  = get_option( 'wcr_sql_name' );
		$table	  = get_option( 'wcr_sql_table' );
		
		$order       = wc_get_order( $order_id );
		$orderStatus = ($order->get_status() != "")? $order->get_status() : 'N/A';
		
		$foundRecord = 0;
		
		$student_information = get_post_meta( $order_id, '_student_information', true );
		
		$db = new wpdb( $username, $password, $database, ($host . ':' . $port ) );
		
		if ( ! $db ) :
			$this->debug_log( 'Can\'t establish connection to database server ' . $db->last_error );
		else :
			$this->debug_log( 'Connection established to host ' . $host . ':' . $port . '. ' . $db->last_error );
		endif;

		//Fetch all the student records form the DB for this order
		$studentDBRecords = $this->FetchStudentDBRecords( $order_id );
			
		foreach( $studentDBRecords as $k => $v ) :
			if ($v->InfoID > 0) :
				//echo $v->InfoID."<br>";	
				$foundRecord = 1;
				$this->wrc_update_ckv_student_info( 'ckv_student_info', $order_id, $orderStatus );
			endif;
		endforeach;
			
		if ($foundRecord == 0) {
			if ( isset($student_information) && is_array($student_information) ){	
				foreach ( $student_information as $student ) :
					
					// Check for course type
					$courseType     = (array_key_exists('course_type', $student))?        $student['course_type']        : '';
					$parentCourseID = (array_key_exists('parent_course_id', $student))?   $student['parent_course_id']   : 0;
					$MOLExtended    = (array_key_exists('molextended_course', $student))? $student['molextended_course'] : 'no';
					
					// Organization info section applies only to molextended courses
					$company_name          = ($MOLExtended == 'yes')? $order->get_billing_company()    : '';
					$company_first_name    = ($MOLExtended == 'yes')? $order->get_billing_first_name() : '';
			 		$company_last_name     = ($MOLExtended == 'yes')? $order->get_billing_last_name()  : '';
			 		$company_email         = ($MOLExtended == 'yes')? $order->get_billing_email()      : '';
			 		$company_city          = ($MOLExtended == 'yes')? $order->get_billing_city()       : '';
			 		$company_province      = ($MOLExtended == 'yes')? $order->get_billing_state()      : '';
			 		$company_postal_code   = ($MOLExtended == 'yes')? $order->get_billing_postcode()   : '';
			 		$company_phone         = ($MOLExtended == 'yes')? $order->get_billing_phone()      : '';
			 		$company_street        = ($MOLExtended == 'yes')? $order->get_billing_address_1().' '.$order->get_billing_address_2() : '';
			 		
			 		$company_box_office    = ($MOLExtended == 'yes')? get_post_meta( $order->get_order_number(), '_billing_company_box_office', true ) : '';
			 		$company_street_number = ($MOLExtended == 'yes')? get_post_meta( $order->get_order_number(), '_billing_street_number', true )      : '';
	 		
			 		// molextended refresher courses
			 		$mollearnerid = ( $MOLExtended == 'yes' && array_key_exists('isrefresher', $student) )? $student['mollearnerid'] : '';
			 		$isrefresher  = ( $MOLExtended == 'yes' && array_key_exists('isrefresher', $student) )? $student['isrefresher']  : 'no';
			 		
			 		// HSMS courses
			 		$phone_field = ( array_key_exists('ishsms', $student) )? $student['work_phone'] : $student['phone'];
			 		$ishsms      = ( array_key_exists('ishsms', $student) )? $student['ishsms']     : 'no';
			 	
			 		// Default language: English
			 		$language_preference = ( get_post_meta( $student['course_id'], 'course_language', true ) == 'fr' ) ? 'fr': 'en';
			 		
					$data = array(
						'CourseID'            => $student['course_id'],                 //Course ID checked out in the cart
						'FirstName' 	      => $student['first_name'],
						'LastName' 		      => $student['last_name'],
						'Email' 		      => $student['email'],
						'OrderID'		      => (int) $order_id,
						//'OrderDate'         => date('l jS \of F Y h:i:s A'),
						'Company'		      => $order->get_billing_company(),
						'JobTitle'		      => '',
						'IsEnrolled'          => 1,
						'orderStatus'         => $orderStatus,
						'courseCompleted'     => 'No',
						'sessionID'           => 0,   //Store the student selected session from my account page only for blended courses otherwise 0
						'wpuserID'            => 0,
						'courseType'          => $courseType,
						'parentCourseID'      => $parentCourseID,
						'MOLExtended'         => $MOLExtended,
						'street'              => $student['street'],                  	// MOL Extended student fields
						'city'                => $student['city'],						// MOL Extended student fields
						'province'            => $student['province'],					// MOL Extended student fields
						'postal_code'         => $student['postal_code'],				// MOL Extended student fields
						'box_office'          => $student['box_office'],				// MOL Extended student fields
						'phone'               => $phone_field,						    // Phone field
						'company_name'        => $company_name,							// MOL Extended company fields
						'company_first_name'  => $company_first_name,      				// MOL Extended company fields
						'company_last_name'   => $company_last_name,			       	// MOL Extended company fields
						'company_email'       => $company_email,       			    	// MOL Extended company fields
						'company_street'      => $company_street,			          	// MOL Extended company fields
						'company_city'        => $company_city,			            	// MOL Extended company fields
						'company_province'    => $company_province,       			 	// MOL Extended company fields
						'company_postal_code' => $company_postal_code,     				// MOL Extended company fields
						'company_box_office'  => $company_box_office, 			     	// MOL Extended company fields
						'company_phone'       => $company_phone,						
						'litmosUserID'        => '',									// Litmos User ID for elearning courses
						'street_number'       => $company_street_number,             	// MOL Extended student fields
						'language_preference' => $language_preference,					// MOL Extended student fields
						'isrefresher'         => $isrefresher,							// Refresher student field
						'studentStatus'       => '',									// MOL Extended student fields
						'mollearnerid'        => $mollearnerid,							// MOL Extended student fields
						'ishsms'              => $ishsms,                               // HSMS student field
					);
			
					$insert = $db->insert( $table, $data );
		
					if ( $insert ) :
					
						// Get the newly generated InfoID auto number DB field value
						$InfoID = $db->insert_id;
						
						//Create the student word press login account only after successful insert to the ckv_student_info table
						$nickname        = $student['first_name'].' '.$student['last_name'];
						$random_password = wp_generate_password();
						$wpuserID        = $this->createStudentLoginAccount($student['email'], $random_password, $student['email'], $student['first_name'], $student['last_name'], $nickname, $order_id, $student['course_id']);
						
						//Update the ckv_student_info table with word press new generated user ID
						if ($wpuserID > 0){
							$updateStudent = $db->update( 
								 'ckv_student_info', 
								 array('wpuserID' => $wpuserID), 
								 array('InfoID' => $InfoID), 
								 array('%d'), 
								 array('%d') 
							 );
							 
						 	 if (false === $updateStudent) :
								 $this->debug_log('Error! wpuserID could not be updated for table: ckv_student_info for student: '.$student['email'].' at InfoID: '.$InfoID);
								 $order->add_order_note( __( 'Error! wpuserID could not be updated for table: ckv_student_info for student: '.$student['email'].' at InfoID: '.$InfoID, 'woocommerce-checkout-registration'));
							 else :
								 $this->debug_log('wpuserID: '.$wpuserID.' is updated successfully for table: ckv_student_info for student: '.$student['email'].' at InfoID: '.$InfoID);
								 $order->add_order_note( __( 'wpuserID: '.$wpuserID.' is updated successfully for table: ckv_student_info for student: '.$student['email'].' at InfoID: '.$InfoID, 'woocommerce-checkout-registration'));
							 endif;
						}
					
						$this->debug_log( "Row added for order # $order_id, {$order->get_billing_first_name()} {$order->get_billing_last_name()}: {$student['first_name']} {$student['last_name']}, email: {$student['email']}" );
						$order->add_order_note( __( "Course # {$student['course_id']} For Student: {$student['first_name']} {$student['last_name']}, email: {$student['email']} is inserted to ckv_student_info table.", 'woocommerce-checkout-registration' ) );
					else :
						$this->debug_log( "Error! Could NOT add row for order #$order_id, {$order->get_billing_first_name()} {$order->get_billing_last_name()}: {$student['first_name']} {$student['last_name']}, email: {$student['email']}" );
						$order->add_order_note( __( "Error! Course # {$student['course_id']} For Student: {$student['first_name']} {$student['last_name']}, email: {$student['email']} was not inserted to ckv_student_info table.", 'woocommerce-checkout-registration' ) );
					endif;
			
				endforeach;
			} // end if
		} // end if
		
		//reset for the next order
		$studentDBRecords = array();
			
	}

	/**
	 * Create Student Login Account
	 *
	 * Creates a word press login account for the student only if the student email does not exist
	 *
	 * @since 1.0.0
	 */
	function createStudentLoginAccount($user_name, $password, $user_email, $first_name, $last_name, $nickname, $order_id, $course_id){
        
		$order = wc_get_order( $order_id );
		
		$user_id = 0;
		
		if( !username_exists($user_name) && email_exists($user_email) == false ) {
			$user_id = wp_create_user( $user_name, $password, $user_email );
			
			wp_update_user(array('ID'           => $user_id, 
			  					 'first_name'   => $first_name,
			  					 'last_name'    => $last_name, 
			  					 'nickname'     => $nickname, 
			  					 'display_name' => $first_name.' '.$last_name));
			 
			$user = new WP_User($user_id);
			$user->set_role('customer');
			
			//Store the auto created login password in the user meta table - should be later changed by the user
			add_user_meta( $user_id, 'temporary_login_pwd', $password);
			
			$this->debug_log("Student: ".$first_name." ".$last_name." login account is successfully created with login info: ".$user_email." and password: ".$password);
			$order->add_order_note( __( "Student: ".$first_name." ".$last_name." login account is successfully created with login info: ".$user_email." and password: ".$password, 'woocommerce-checkout-registration' ) );		
		}
		else{
			$this->debug_log( "Login account already exist for student: ".$first_name." ".$last_name." with email: ".$user_email);
			$order->add_order_note( __( "Login account already exist for student: ".$first_name." ".$last_name." with email: ".$user_email, 'woocommerce-checkout-registration' ) );		
		}
		
		return $user_id;
	}
	
	/**
	 * Send notification emails to the registerant/student and to the CC Team/admin depending on the order status
	 *
	 * @since 1.0.0
	 */
	 
	function send_registerant_email( $student_first_name,$student_last_name,$student_email,$student_password='',$course_type=array(),$sso_link='',$receptient, $course_name, $course_id, $order ){
		
		$is_email_sent = false;
		$to            = array($student_email);
		$headers       = array('Content-Type: text/html; charset=UTF-8;','Bcc: '.$receptient);  //Switch to HTML
		$attachments   = array();
	
		$order_id    = $order->get_order_number();
		$orderStatus = $order->get_status(); 
		
		// Send a custom email only if the order is successful for both moneris payments and manual payments from the check out page
		if ( ( $orderStatus == 'processing' || $orderStatus == 'completed') && !is_admin() ){
			
			// MOL Extended and Blended and not Cert 1 and not Cert 2 courses (WAH)
			if ( in_array('molextended', $course_type) && in_array('blended', $course_type) && !$this->wrc_woocommerce_check_for_product_category($course_id, 'jhsc-certification-part-1') && !$this->wrc_woocommerce_check_for_product_category($course_id, 'jhsc-certification-part-2') ){
				//$attachments = array(WP_CONTENT_DIR . '/uploads/2019/05/Learner-Consent-Form.pdf');
				$msg         = $this->getWAHBlendedCourseEmailBody($student_email, $student_password, $student_first_name, $student_last_name, $sso_link, $course_name, $course_id);
				$subject     = $course_name.": Registration Confirmation and Requirements";
			}
			// MOL Extended only and not blended courses and not Cert 1 and not Cert 2 and not refresher courses
			else if( in_array('molextended', $course_type) && !in_array('blended', $course_type) && !$this->wrc_woocommerce_check_for_product_category($course_id, 'jhsc-certification-part-1') && !$this->wrc_woocommerce_check_for_product_category($course_id, 'jhsc-certification-part-2') && !in_array('isrefresher', $course_type) ){
				$attachments = array(WP_CONTENT_DIR . '/uploads/2019/05/Learner-Consent-Form.pdf');
				$msg         = $this->getMOLExtendedCourseEmailBody($student_email, $student_password, $student_first_name, $student_last_name, $course_name, $course_id);
				$subject     = $course_name.": Registration Confirmation and Requirements";
			}
			// MOL Extended and not blended courses and not refresher courses and Cert 1 courses - English version
			else if( in_array('molextended', $course_type) && !in_array('blended', $course_type) && !in_array('isrefresher', $course_type) && $this->wrc_woocommerce_check_for_product_category($course_id, 'jhsc-certification-part-1') && get_post_meta($course_id, 'course_language', true) != 'fr' ){
				$attachments = array(WP_CONTENT_DIR . '/uploads/2019/05/Learner-Consent-Form.pdf');
				$msg         = $this->getJHSCCert1MOLExtendedCourseEmailBodyEnglish($student_email, $student_password, $student_first_name, $student_last_name, $course_name, $course_id);
				$subject     = $course_name.": Registration Confirmation and Requirements";
			}
			// MOL Extended and not blended courses and not refresher courses and Cert 1 courses - French version
			else if( in_array('molextended', $course_type) && !in_array('blended', $course_type) && !in_array('isrefresher', $course_type) && $this->wrc_woocommerce_check_for_product_category($course_id, 'jhsc-certification-part-1') &&  get_post_meta($course_id, 'course_language', true) == 'fr' ){
				$headers     = array('Content-Type: text/html; charset=UTF-8; charset=iso-8859-1;','Bcc: '.$receptient);  // Switch to HTML - French enconding
				$attachments = array(WP_CONTENT_DIR . '/uploads/2019/05/Learner-Consent-Form.pdf');
				$msg         = $this->getJHSCCert1MOLExtendedCourseEmailBodyFrench($student_email, $student_password, $student_first_name, $student_last_name, $course_name, $course_id);
				$subject     = "Confirmation à l’inscription et exigences pour la formation de: ".$course_name;
			}
			// MOL Extended and not blended courses and refresher courses and Cert 1 courses
			else if( in_array('molextended', $course_type) && !in_array('blended', $course_type) && in_array('isrefresher', $course_type) && $this->wrc_woocommerce_check_for_product_category($course_id, 'jhsc-certification-part-1') ){
				$attachments = array(WP_CONTENT_DIR . '/uploads/2019/05/Learner-Consent-Form.pdf');
				$msg         = $this->getRefresherJHSCCert1MOLExtendedCourseEmailBody($student_email, $student_password, $student_first_name, $student_last_name, $course_name, $course_id);
				$subject     = $course_name.": Registration Confirmation and Requirements";
			}
			// MOL Extended and not blended courses and refresher courses and not Cert 1 courses and not Cert 2 courses
			else if( in_array('molextended', $course_type) && !in_array('blended', $course_type) && in_array('isrefresher', $course_type) && !$this->wrc_woocommerce_check_for_product_category($course_id, 'jhsc-certification-part-1') && !$this->wrc_woocommerce_check_for_product_category($course_id, 'jhsc-certification-part-2') ){
				$attachments = array(WP_CONTENT_DIR . '/uploads/2019/05/Learner-Consent-Form.pdf');
				$msg         = $this->getRefresherWAHMOLExtendedCourseEmailBody($student_email, $student_password, $student_first_name, $student_last_name, $course_name, $course_id);
				$subject     = $course_name.": Registration Confirmation and Requirements";
			}
			// MOL Extended and blended courses and Cert 1 courses
			else if( in_array('molextended', $course_type) && in_array('blended', $course_type) && $this->wrc_woocommerce_check_for_product_category($course_id, 'jhsc-certification-part-1') ){
				$attachments = array(WP_CONTENT_DIR . '/uploads/2019/05/Learner-Consent-Form.pdf');
				$msg         = $this->getJHSCCert1BlendedCourseEmailBody($student_email, $student_password, $student_first_name, $student_last_name, $course_name, $course_id);
				$subject     = $course_name.": Registration Confirmation and Requirements";
			}
			// MOL Extended and not blended courses and Cert 2 courses and not refresher courses
			else if( in_array('molextended', $course_type) && !in_array('blended', $course_type) && !in_array('isrefresher', $course_type) && $this->wrc_woocommerce_check_for_product_category($course_id, 'jhsc-certification-part-2') ){
				$attachments = array(WP_CONTENT_DIR . '/uploads/2019/05/Learner-Consent-Form.pdf');
				$msg         = $this->getJHSCCert2MOLExtendedCourseEmailBody($student_email, $student_password, $student_first_name, $student_last_name, $course_name, $course_id);
				$subject     = $course_name.": Registration Confirmation and Requirements";
			}
			// Not MOL Extended and blended courses and HSMS courses
			else if( !in_array('molextended', $course_type) && in_array('blended', $course_type) && in_array('ishsms', $course_type) ){
	 			$msg         = $this->getHSMSBlendedCourseEmailBody($student_email, $student_password, $student_first_name, $student_last_name, $course_name, $course_id);
	 			$subject     = $course_name.": Registration Confirmation and Requirements";
			}
			/* eLearning course only
			else{
				$msg     = $this->geteLearningCourseEmailBody($student_first_name, $sso_link);
				$subject = "Single Sign On Link For PSHSA Learning Management System";
			}
			*/
			
			// Footer
			$msg .= "<br><p><img src='http://".$_SERVER["SERVER_NAME"]."/wp-content/uploads/2015/11/pshsa_logo.jpg' alt='PSHSA Logo' width='120' height='25' />";
			$msg .= "<br><br><span style='color:#FF0000;'>T: 416-250-2131 Toll Free: 1-877-250-7444</span><br>customerservice@pshsa.ca<br><hr></p>";
			
			$is_email_sent = ( sizeof($to) > 0 ) ? wp_mail($to, $subject, $msg, $headers, $attachments) : false;
		}
		
		// Send a custom email only if the order has failed from the check out page
		if ( $orderStatus == 'failed' && !is_admin() ){
			$msg           = $this->getFailedOrderEmailBody($student_email, $student_first_name, $student_last_name);
			$subject       = "Your Registration Order # ".$order_id." Failed For Course: ". $course_name;
			$is_email_sent = wp_mail($to, $subject, $msg, $headers, $attachments);
		}
		
		return $is_email_sent;
	}

	/**
	 * Send confirmation email for all eLearning courses other than blended courses
	
	function geteLearningCourseEmailBody($student_first_name, $sso_link) {
	
		$msg  = "<h2>Thank you for registering!</h2>";
		$msg .= "<p>Welcome ".stripslashes($student_first_name).".</p>";
		
		if ($sso_link != ''){
			$msg .= "<p>To auto login onto PSHSA eLearning management system and start your courses. Please click on the following link: ";
			$msg .= "<a href='".$sso_link."'>Click here</a> or copy and paste this link: ".$sso_link." into the browser address field</p>";	
		}
		
		return $msg;
	}
	*/
	
	/** 
	 * Send confirmation email for MOL Extended courses only and not cert 1 and not cert2 and not blended courses and not isrefresher
	*/
	function getMOLExtendedCourseEmailBody($student_email, $student_password, $student_first_name, $student_last_name, $course_name, $course_id){
		
		$msg  = "<h2>Thank you for registering!</h2>";
		$msg .= "<p>Hello ".stripslashes($student_first_name).".</p>";
		$msg .= "<p>You have successfully registered for:<br><span style='color:#3366CC;font-weight:bolder;'>".$course_name."</span></p>";
		$msg .= "<p><b>Date:</b> " . date('M d, Y', strtotime(implode(" ", $this->get_product_terms($course_id, 'pa_start-date'))));
		$msg .= "<br><b>Time:</b> " . implode(" ", $this->get_product_terms($course_id, 'pa_time'));
		$msg .= "<br><b>Venue:</b> " . implode(" ", $this->get_product_terms($course_id, 'pa_session-info'));
		$msg .= "<br><b>Address:</b> " . implode(" ", $this->get_product_terms($course_id, 'pa_course-location')) . "</p>";
		
 		$msg .= "<p><b>About this Course:</b>To become certified to work at heights, participants must successfully complete the ".$course_name." Training ";
		$msg .= "Program and both the hands-on and written evaluations administered at the end of the Classroom session.</p>";
		
		$msg .= "<p><b>Mandatory requirements for the Classroom Session:</b> To take part in training:<br><ul>";
		$msg .= "<li>All registered participants <span style='text-decoration: underline;'>must</span> be present at the start of the session. ";
		$msg .= "Any registered participant who is more than <span style='text-decoration: underline;'>15 minutes late will not be permitted";
		$msg .= "</span> to attend the session.</li><li>Participants <span style='text-decoration: underline;'>must</span> bring a piece of ";
		$msg .= "government-issued photo ID (such as a Driver's License or a Health Card) with them to the session.</li>";
		$msg .= "<li>Participants <span style='text-decoration: underline;'>must</span> bring CSA-certified PPE which includes safety footwear, ";
		$msg .= "hard hat and protective eyewear to the session.</li></ul></p>";
		
		$msg .= "<p><b>Optional requirements for the Classroom Session:</b><br><ul>"; 
		$msg .= "<li>Participants may choose to work with their own personal fall protective equipment: harness and lanyard, and can bring these ";
		$msg .= "to the session.</li><li>Participants may use their own gloves to handle equipment, and can bring their own to the session.</li>";
		$msg .= "<li>Participants can complete a 'PSHSA Learner Consent form' (attached) ahead of the session; it must be completed at the start ";
		$msg .= "of the session otherwise.</li></ul></p>";

		$msg .= "<p>Be advised that your PPE that is to be worn and used during the training must be in good working condition and meet all ";
		$msg .= "necessary manufacturer and regulatory requirements. If you do not have appropriate equipment please contact us in advance ";
		$msg .= "so we can make arrangements for you to borrow some of PSHSA's equipment for that session, or to reschedule for another date.</p>";
		
		$msg .= "<p>Please visit our website http://".$_SERVER["SERVER_NAME"]."/working-at-heights-sign-up/ for helpful videos and sample checklists ";
		$msg .= "that can be used for inspecting and donning and doffing fall protection equipment.</p>";

		$msg .= "<p>For any questions regarding Working at Heights training, you can contact us at <span style='text-decoration: underline;'>";
		$msg .= "workingatheights@pshsa.ca</span></p>";

		$msg .= "<p><b>Food and Beverages:</b> We do not provide food with training, so please bring your own lunch/snacks. All training materials";
		$msg .= " will be provided to you upon arrival.</p>";

		$msg .= "<p><b>Special Requests:</b> Public Services Health and Safety Association is committed to providing accessible services. ";
		$msg .= "We encourage you to voluntarily self-identify if you require any form of enhanced accessibility. Any such disclosure is ";
		$msg .= "confidential, in accordance with the Freedom of Information and Protection of Privacy Act. If you require assistance, ";
		$msg .= "please contact <span style='text-decoration: underline;'>AODA@pshsa.ca</span> or 416-250-2134. A member of our team will contact ";
		$msg .= "you.</p>";
		
		$msg .= "<p><b>Please note Cancellation Policy:</b> Cancellations received up to <span style='color:#FF0000;font-weight:bolder;'>7</span>";
		$msg .= " business days prior to the scheduled session will be refunded in total, after that, cancellations are subject to the full ";
		$msg .= "registration fee. Transfers are accepted <span style='color:#FF0000;font-weight:bolder;'>7</span> business days prior to the ";
		$msg .= " scheduled session. Substitutions can be made at any time prior to the session.</p>";  

		$msg .= "<p><b>In the event of inclement weather, the scheduled training session may be cancelled. You will receive notification the day ";
		$msg .= "prior to the session providing the consultant's contact information with further instructions.</b></p>";

		$msg .= "<p>To eliminate health concerns arising from exposure to scented products please refrain from using or wearing scented products at ";
		$msg .= "all PSHSA training sessions.</p>";
 
		$msg .= "<p>If you have any questions please do not hesitate to contact PSHSA at the number below.</p>";

		return $msg;
	}
	
	/** 
	 * Send confirmation email for JHSC Cert1 and molextended courses and not blended courses - English version
	*/
	function getJHSCCert1MOLExtendedCourseEmailBodyEnglish($student_email, $student_password, $student_first_name, $student_last_name, $course_name, $course_id){
		
		$msg  = "<h2>Thank you for registering!</h2>";
		$msg .= "<p>Hello ".stripslashes($student_first_name).".</p>";
		
		$msg .= "<p>You have successfully registered for:<br><span style='color:#3366CC;font-weight:bolder;'>".$course_name."</span></p>";
		$msg .= "<p><b>Date:</b> ".date('M d, Y', strtotime(implode(" ", $this->get_product_terms($course_id, 'pa_start-date')))); 
 		$msg .= "<br><b>Time:</b> ".implode(" ", $this->get_product_terms($course_id, 'pa_time'));
		$msg .= "<br><b>Venue:</b> ".implode(" ", $this->get_product_terms($course_id, 'pa_session-info'));
		$msg .= "<br><b>Address:</b> " . implode(" ", $this->get_product_terms($course_id, 'pa_course-location'))."</p>";
 		
		$msg .= "<p><b>About This Course:</b> To finish the JHSC Certification Part 1 Program, participants must successfully complete the written ";
		$msg .= "evaluation administered at the end the Training Program. This evaluation will determine whether or not a participant passes the ";
		$msg .= "course.</p>";
		
		$msg .= "<p><b>Mandatory requirements for the Classroom Session:</b> To take part in training:<br><ul>";
		$msg .= "<li>Participants <span style='text-decoration: underline;'>must</span> bring a piece of government-issued photo ID (such as a Driver's ";
		$msg .= "License or a Health Card) with them to the session.</li>";
		$msg .= "<li>The course will begin promptly at the start time. We strongly recommend participants arrive 30 minutes prior to the course ";
		$msg .= "commencement to sign in.</li>";
		$msg .= "<li>In order to receive the required amount of instruction time as required by the MOL standard, participants will need to be present ";
		$msg .= "and participate through the entire day at the times scheduled. If you will need to miss more than 15 minutes per day, we encourage you to ";
		$msg .= "select an alternate date by contracting PSHSA's Customer Services at 416-250-2131 (Toll Free: 1-877-250-7444) or customerservice@pshsa.ca</li></ul></p>";

		$msg .= "<p><b>Optional requirements for the Classroom Session:</b><br><ul>"; 
		$msg .= "<li>Participants can complete a 'PSHSA Learner Consent form' (attached) ahead of the session; it must be completed at the start of ";
		$msg .= "the session otherwise.</li></ul></p>";
		
		$msg .= "<p><b>To Become Certified:</b> To become fully certified JHSC members, participants successfully complete both the JHSC ";
		$msg .= "Certification Part 1 Training Program and JHSC Certification Part 2 (Sector-Specific or Workplace-Specific hazard training).</p>";   
		
		$msg .= "<p><b>Food and Beverages:</b> We do not provide food with training, so please bring your own lunch/snacks. All training materials";
		$msg .= " will be provided to you upon arrival.</p>";     
		
		$msg .= "<p><b>Special Requests:</b> Public Services Health and Safety Association is committed to providing accessible services. We ";
		$msg .= "encourage you to voluntarily self-identify if you require any form of enhanced accessibility. Any such disclosure is confidential,";
		$msg .= " in accordance with the Freedom of Information and Protection of Privacy Act. If you require assistance, please contact ";
		$msg .= "<span style='text-decoration: underline;'>AODA@pshsa.ca</span> or 416-250-2134. A member of our team will contact you.</p>";
		
		$msg .= "<p><b>Please note Cancellation Policy:</b> Cancellations will be received up to <span style='color:#FF0000;font-weight:bolder;'>7</span> ";
		$msg .=  "business days prior to the scheduled session. Transfers are accepted <span style='color:#FF0000;font-weight:bolder;'>7</span> business ";
		$msg .= "days prior to the scheduled session.</p>";  
		
		$msg .= "<p><b>In the event of inclement weather, the scheduled training session may be cancelled. You will receive notification the day prior ";
		$msg .= "to the session providing the consultant's contact information with further instructions.</b></p>";
		
		$msg .= "<p>To eliminate health concerns arising from exposure to scented products please refrain from using or wearing scented products ";
		$msg .= "at all PSHSA training sessions.</p>"; 
		
		$msg .= "<p>If you have any questions please do not hesitate to contact PSHSA at the number below.</p>";

		return $msg;
	}
	
	/** 
	 * Send confirmation email for JHSC Cert1 and molextended courses and not blended courses - French version
	*/
	function getJHSCCert1MOLExtendedCourseEmailBodyFrench($student_email, $student_password, $student_first_name, $student_last_name, $course_name, $course_id){
		
		$msg  = "<h2>Merci pour votre inscription!</h2>";
		$msg .= "<p>Cher ".stripslashes($student_first_name).".</p>";
		 
		$msg .= "<p>Vous êtes inscrit pour:<br><br><span style='color:#3366CC;font-weight:bolder;'>".$course_name."</span></p>";
		$msg .= "<p>Date: ".date('M d, Y', strtotime(implode(" ", $this->get_product_terms($course_id, 'pa_start-date')))); 
 		$msg .= "<br>heure: ".implode(" ", $this->get_product_terms($course_id, 'pa_time'));
 		$msg .= "<br>".implode(" ", $this->get_product_terms($course_id, 'pa_session-info'))."</p>";
 		
		$msg .= "<p style='color:#3366CC;font-weight:bolder;'>Détails au sujet de cette formation- Pour réussir cette formation les participants ";
		$msg .= "doivent compléter avec succès une évaluation écrite qui sera administre à la fin de la formation. Cette évaluation déterminera si ";
		$msg .= "le participant passe le cours.</p><hr>";
		
		$msg .= "<p><b>Exigences obligatoires de la formation en classe:</b> : Pour participer à cette formation de base à l’agrément:<br><ul>"; 
		$msg .= "<li>Les participants doivent apporter une pièce d’identité avec photo émise par le gouvernement (comme conduire un permis ou une carte de santé) avec eux à la session.</li>";
		$msg .= "<li>La formation commencera précisément à l’heure indiquer.  Nous recommandons que les participants arrivent 30 minutes avant le début la formation pour s’inscrire sur la liste de présence.</li>";
		$msg .= "<li>Afin d’avoir la quantité requise de temps d’instruction tel qu’exigé par la norme de MOL, les participants devront être présents ";
		$msg .= "et de participer à travers toute la journée. Si vous devez manquer plus de 15 minutes par jour, nous vous invitons à choisir une autre ";
		$msg .= "date en contractant notre service à la clientèle de PSHSA à 416-250-2131 (ligne sans frais: 1-877-250-7444) ou ";
		$msg .= "customerservice@pshsa.ca</li></ul></p>";
		   
		$msg .= "<p><b>Exigences facultatives pour la séance en classe:</b><br><ul>"; 
		$msg .= "<li>Les participants peuvent remplir un formulaire 'PSHSA Learner Consent Form' (ci joint) avant la séance. Autrement, il faut le ";
		$msg .= "remplir au début de la séance.</li></ul></p>";
		 
		$msg .= "<p><b>Pour devenir membre agrée:</b>Pour devenir membre agrée pour votre comité mixte de santé et sécurité au travail (CMSST), les ";
		$msg .= "participants doivent terminer avec succès la formation d’agrément du CMSST  partie 1 et le programme de formation d’agrément partie 2 ";
		$msg .= "(formation des risques sectoriels ou spécifiques au travail).</p>";
		 
		$msg .= "<p><b>Les aliments et boissons:</b>Nous ne fournissons pas de nourriture avec cette formation, donc s’il vous plaît apporter votre ";
		$msg .= "propre repas/collations. Le guide de participant et une copie de la loi de SST vous seront fournies à l’arrivée</p>"; 
		 
		$msg .= "<p><b>Demandes spéciales:</b>Notre Association, PSHSA, s’engagent à fournir des services accessibles. Nous vous encourageons à ";
		$msg .= "volontairement s’identifier si vous avez besoin une forme d’accessibilité améliorée. Toute telle divulgation est confidentielle, ";
		$msg .= "conformément à la liberté de l’Information et Loi sur la Protection de la vie privée. Si vous avez besoin d’aide, veuillez communiquer ";
		$msg .= "avec AODA@pshsa.ca ou 416-250-2134. Un membre de notre équipe communiquera avec vous.</p>"; 
		 
		$msg .= "<p><b>Veuillez prendre note des conditions d’annulation:</b> Annulations seront reçues jusqu'à <span style='color:#FF0000;font-weight:bolder;'>";
		$msg .= "7</span> jours avant la formation. Les transferts de session de formation d’agrément sont acceptés <span style='color:#FF0000;font-weight:bolder;'>";
		$msg .= "7</span> jours ouvrables avant la date de formation prévue.</p>";
		 
		$msg .= "<p><b>En cas de conditions météorologiques défavorables, la formation peut être annulée. Vous recevrez le jour avant la session un ";
		$msg .= "courriel fournissant des informations de contact du formateur avec des instructions supplémentaires.</b></p>";
		 
		$msg .= "<p>Pour éliminer les problèmes de santé résultant de l’exposition aux produits parfumés veuillez s’abstenir d’utiliser ou de porter ";
		$msg .= "des produits parfumés aux sessions de formation de PSHSA</p>";
		 
		$msg .= "<p>Si vous avez des questions n’hésitent pas à contacter PSHSA au numéro ci-dessous.</p>";

		return $msg;
	}
	
	/** 
	 * Send confirmation email for refresher courses and JHSC Cert1 and molextended courses and not blended courses
	*/
	function getRefresherJHSCCert1MOLExtendedCourseEmailBody($student_email, $student_password, $student_first_name, $student_last_name, $course_name, $course_id){
		
		$msg  = "<h2>Thank you for registering!</h2>";
		$msg .= "<p>Hello ".stripslashes($student_first_name).".</p>";
		
		$msg .= "<p>You have successfully registered for:<br><span style='color:#3366CC;font-weight:bolder;'>".$course_name."</span></p>";
		$msg .= "<p><b>Date:</b> " . date('M d, Y', strtotime(implode(" ", $this->get_product_terms($course_id, 'pa_start-date'))));
		$msg .= "<br><b>Time:</b> " . implode(" ", $this->get_product_terms($course_id, 'pa_time'));
		$msg .= "<br><b>Venue:</b> " . implode(" ", $this->get_product_terms($course_id, 'pa_session-info'));
		$msg .= "<br><b>Address:</b> " . implode(" ", $this->get_product_terms($course_id, 'pa_course-location')) . "</p>";
 		
		$msg .= "<p><b>About This Course:</b> To finish the JHSC Certification Refresher Program, participants must successfully complete the written ";
		$msg .= "evaluation administered at the end the Training Program. This evaluation will determine whether or not a participant passes the ";
		$msg .= "course.</p>";
		
		$msg .= "<p><b>Mandatory requirements for the Classroom Session:</b> To take part in training:<br><ul>";
		$msg .= "<li>Participants <span style='text-decoration: underline;'>must</span> bring a piece of government-issued photo ID (such as a ";
		$msg .= "Driver's License or a Health Card) with them to the session.</li>";
		$msg .= "<li>The course will begin promptly at the start time. We strongly recommend ";
		$msg .= "participants arrive 30 minutes prior to the course commencement to sign in.</li>";
		$msg .= "<li>In order to receive the required amount of instruction time as required by ";
		$msg .= "the MOL standard, participants will need to be present and participate through the entire day at the times scheduled. If you ";
		$msg .= "will need to miss more than 15 minutes per day, we encourage you to select an alternate date by contracting PSHSA's Customer ";
		$msg .= "Services at 416-250-2131 (Toll Free: 1-877-250-7444) or customerservice@pshsa.ca.</li></ul></p>"; 
		
		$msg .= "<p><b>Optional requirements for the Classroom Session:</b><br>";
		$msg .= "<ul><li>Participants can complete a 'PSHSA Learner Consent form' (attached) ahead of the session; it must be completed at the start of ";
		$msg .= "the session otherwise.</li></ul></p>";
		
		$msg .= "<p><b>To Become Certified:</b> To become fully certified JHSC members, participants successfully complete both the JHSC ";
		$msg .= "Certification Part 1 Training Program and JHSC Certification Part 2 (Sector-Specific or Workplace-Specific hazard training). ";
		$msg .= "To support ongoing learning for certified members, the new Program Standard has introduced Refresher Training. Those certified ";
		$msg .= "by the CPO under the new training and other requirements established on October 1, 2015 are required to take Refresher Training ";
		$msg .= "every three years to maintain their certification</p>";   
		
		$msg .= "<p><b>Food and Beverages:</b> We do not provide food with training, so please bring your own lunch/snacks. All training materials";
		$msg .= " will be provided to you upon arrival.</p>";     
		
		$msg .= "<p><b>Special Requests:</b> Public Services Health and Safety Association is committed to providing accessible services. We ";
		$msg .= "encourage you to voluntarily self-identify if you require any form of enhanced accessibility. Any such disclosure is confidential,";
		$msg .= " in accordance with the Freedom of Information and Protection of Privacy Act. If you require assistance, please contact ";
		$msg .= "<span style='text-decoration: underline;'>AODA@pshsa.ca</span> or 416-250-2134. A member of our team will contact you.</p>";
		
		$msg .= "<p><b>Please note Cancellation Policy:</b> Cancellations will be received up to <span style='color:#FF0000;font-weight:bolder;'>";
		$msg .= "7</span> business days prior to the scheduled session. Transfers are accepted <span style='color:#FF0000;font-weight:bolder;'>";
		$msg .= "7</span> business days prior to the scheduled session.</p>";  
		
		$msg .= "<p><b>In the event of inclement weather, the scheduled training session may be cancelled. You will receive notification the day ";
		$msg .= "prior to the session providing the consultant's contact information with further instructions.</b></p>";
		
		$msg .= "<p>To eliminate health concerns arising from exposure to scented products please refrain from using or wearing scented products ";
		$msg .= "at all PSHSA training sessions.</p>"; 
		
		$msg .= "<p>If you have any questions please do not hesitate to contact PSHSA at the number below.</p>";

		return $msg;
	}
	
	/** 
	 * Send confirmation email for refresher, WAH, molextended and not blended courses and not Cert 1 courses and not Cert 2 courses
	*/
	function getRefresherWAHMOLExtendedCourseEmailBody($student_email, $student_password, $student_first_name, $student_last_name, $course_name, $course_id){
		
		$msg  = "<h2>Thank you for registering!</h2>";
		$msg .= "<p>Hello ".stripslashes($student_first_name).".</p>";
		
		$msg .= "<p>You have successfully registered for:<br><span style='color:#3366CC;font-weight:bolder;'>".$course_name."</span></p>";
		$msg .= "<p><b>Date:</b> " . date('M d, Y', strtotime(implode(" ", $this->get_product_terms($course_id, 'pa_start-date'))));
		$msg .= "<br><b>Time:</b> " . implode(" ", $this->get_product_terms($course_id, 'pa_time'));
		$msg .= "<br><b>Venue:</b> " . implode(" ", $this->get_product_terms($course_id, 'pa_session-info'));
		$msg .= "<br><b>Address:</b> " . implode(" ", $this->get_product_terms($course_id, 'pa_course-location')) . "</p>";
				
		$msg .= "<p><b>About This Course:</b>To become certified to work at heights, participants must successfully complete the Working at ";
		$msg .= "Heights Refresher Training Program and the hands-on evaluation administered at the end of the Classroom session.</p>";
		
		$msg .= "<p><b>Mandatory requirements for the Classroom Session:</b> To take part in training:<br><ul>";
		$msg .= "<li>All registered participants <span style='text-decoration: underline;'>must</span> be present at the start of the session. ";
		$msg .= "Any registered participant who is more than <span style='text-decoration: underline;'>15 minutes late will not be permitted ";
		$msg .= "</span>to attend the session.</li>"; 
		$msg .= "<li>Participants <span style='text-decoration: underline;'>must</span> bring a piece of government-issued photo ID (such as a ";
		$msg .= "Driver's License or a Health Card) with them to the session.</li>";
		$msg .= "<li>Participants <span style='text-decoration: underline;'>must</span> bring their MOL issued Working at Heights Wallet Card ";
		$msg .= "(Certificate) which is obtained after successful completion of a MOL approved provider Working at Heights course.</li>";
		$msg .= "<li>Participants <span style='text-decoration: underline;'>must</span> bring CSA-certified PPE which includes safety footwear, ";
		$msg .= "hard hat and protective eyewear to the session.</li></ul></p>"; 
		
		$msg .= "<p><b>Optional requirements for the Classroom Session:</b><br><ul>"; 
		$msg .= "<li>Participants may choose to work with their own personal fall protective equipment - harness and lanyard, and can bring these ";
		$msg .= "to the session.</li>";
		$msg .= "<li>Participants may use their own gloves to handle equipment, and can bring their own to the session.</li>";
		$msg .= "<li>Participants can complete a 'PSHSA Learner Consent form' (attached) ahead of the session; it must be completed at the start of ";
		$msg .= "the session otherwise.</li></ul></p>";
		
		$msg .= "<p>Be advised that your PPE that is to be worn and used during the training must be in good working condition and meet all ";
		$msg .= "necessary manufacturer and regulatory requirements. If you do not have appropriate equipment please contact us in advance so ";
		$msg .= "we can make arrangements for you to borrow some of PSHSA's equipment for that session, or to reschedule for another date.</p>";   
		
		$msg .= "<p>Please visit our website http://".$_SERVER["SERVER_NAME"]."/working-at-heights-sign-up/ for helpful videos and sample ";
		$msg .= "checklists that can be used for inspecting and donning and doffing fall protection equipment.</p>";
		
		$msg .= "<p>For any questions regarding Working at Heights training, you can contact us at workingatheights@pshsa.ca</p>";
		
		$msg .= "<p><b>Food and Beverages:</b> We do not provide food with training, so please bring your own lunch/snacks. All training materials";
		$msg .= " will be provided to you upon arrival.</p>";     
		
		$msg .= "<p><b>Special Requests:</b> Public Services Health and Safety Association is committed to providing accessible services. We ";
		$msg .= "encourage you to voluntarily self-identify if you require any form of enhanced accessibility. Any such disclosure is confidential,";
		$msg .= " in accordance with the Freedom of Information and Protection of Privacy Act. If you require assistance, please contact ";
		$msg .= "<span style='text-decoration: underline;'>AODA@pshsa.ca</span> or 416-250-2134. A member of our team will contact you.</p>";
		
		$msg .= "<p><b>Please note Cancellation Policy:</b> Cancellations received up to <span style='color:#FF0000;font-weight:bolder;'>";
		$msg .= "7</span> business days prior to the scheduled session will be refunded in total, after that, cancellations are subject to the ";
		$msg .= "full registration fee. Transfers are accepted <span style='color:#FF0000;font-weight:bolder;'>7</span> business days prior to ";
		$msg .= "the scheduled session. Substitutions can be made at any time prior to the session.</p>";  
		
		$msg .= "<p><b>In the event of inclement weather, the scheduled training session may be cancelled. You will receive notification the day ";
		$msg .= "prior to the session providing the consultant's contact information with further instructions.</b></p>";
		
		$msg .= "<p>To eliminate health concerns arising from exposure to scented products please refrain from using or wearing scented products ";
		$msg .= "at all PSHSA training sessions.</p>"; 
		
		$msg .= "<p>If you have any questions please do not hesitate to contact PSHSA at the number below.</p>";

		return $msg;
	}
	
	/** 
	 * Send confirmation email for JHSC Cert1 and molextended and blended courses
	*/
	function getJHSCCert1BlendedCourseEmailBody($student_email, $student_password, $student_first_name, $student_last_name, $course_name, $course_id){
		
		$msg  = "<h2>Thank you for registering!</h2>";
		$msg .= "<p>Welcome ".stripslashes($student_first_name)."!</p>";
		
		// Blended Cert 1 Try It course  
		if( in_array( 'sales-type-indicator-free', $this->get_attribute_values($course_id) ) ){
			$msg .= "<p>You have successfully registered for a FREE trial of the following course: <span style='color:#3366CC;font-weight:bolder;'>".$course_name."</span></p>"; 
		}
		// Blended Cert 1 regular course
		else if( in_array( 'sales-type-indicator-regular', $this->get_attribute_values($course_id) ) ){ 
			$msg .= "<p>You have successfully registered for the following course: <span style='color:#3366CC;font-weight:bolder;'>".$course_name."</span></p>"; 
		}
		
		$msg .= "<p><b>About This Course:</b> The".$course_name." Training program is made up of two parts:";
		$msg .= "<ul><li>".$course_name." eLearning (2 modules, 6.5 Hours total)</li>";
		$msg .= "<li>".$course_name." Classroom Session (2 Days) + Evaluation</li></ul></p>";

		/* $msg .= "<p><br><b>Date:</b> ".date('M d, Y', strtotime(implode(" ", $this->get_product_terms($course_id, 'pa_start-date')))); 
		$msg .= "<br><b>Time:</b> ".implode(" ", $this->get_product_terms($course_id, 'pa_time'));
		$msg .= "<br><b>Venue:</b> ".implode(" ", $this->get_product_terms($course_id, 'pa_session-info'));
		$msg .= "<br><b>Address:</b> " . implode(" ", $this->get_product_terms($course_id, 'pa_course-location'))."</p>"; */

		// Blended Cert 1 Try It course  
		if( in_array( 'sales-type-indicator-free', $this->get_attribute_values($course_id) ) ){
			$msg .= "<p>We encourage you to complete the eLearning component of our course for free. If you are happy with your trial you may purchase ";
			$msg .= "the full course at any time by clicking the link provided in the course, or by clicking <a href='".get_permalink(get_post_meta($course_id, 'product_custom_parent_id', true))."'>";
			$msg .= "here</a>. Prior to the Classroom Session, participants must successfully complete both eLearning Modules, including a ";
			$msg .= "quiz at the end of each. At the end of the Classroom Session participants must successfully complete a written evaluation that ";
			$msg .= "will test on knowledge obtained in both the eLearning Modules and the Classroom Session. This evaluation will determine whether ";
			$msg .= "or not a participant passes the course.</p>";
			$msg .= "<p><b>To Start the Trial:</b> You will receive a 2nd email from PSHSA Learning Management System, inviting you to begin the ";
			$msg .= "eLearning modules. Click the link provided in the invitation email to begin. Use this link each time you want to return to the ";
			$msg .= "eLearning modules. If you need to change your eLearning account password, use your Username: ".$student_email."</p>";
		} 
		// Blended Cert 1 regular course
		else if( in_array( 'sales-type-indicator-regular', $this->get_attribute_values($course_id) ) ){ 
			$msg .=	"<p>Prior to the Classroom Session, participants <span style='text-decoration: underline;'>must</span> successfully complete both eLearning Modules, including a quiz at the end of each. ";
			$msg .=	"At the end of the Classroom Session participants must successfully complete a written evaluation that will test on knowledge obtained ";
			$msg .= "in both the eLearning Modules and the Classroom Session. This evaluation will determine whether or not a participant passes the course.</p>";
			$msg .= "<p><b>To Start the Course:</b> You will receive a 2nd email from PSHSA's Learning Management System, inviting you to begin the "; 
			$msg .= "eLearning modules. Click the link provided in the invitation email to begin. Use this link each time you want to return to the ";
			$msg .= "eLearning modules. If you need to change your eLearning account password, use your Username: ".$student_email."</p>"; 
		}
		
		$msg .= "<p>If your browser's pop-up blocker prevents your course from opening, please click the 'Launch' button to allow pshsa.litmos.com in";
		$msg .= " your pop-up blocker options. If you require assistance, please email one of our Customer Coordinators at LMS@pshsa.ca, or ";
		$msg .= "call 416-250-2131 (1-877-250-7444) and press 1 for course information.</p>";

		// Blended Cert 1 Try It course  
		if( in_array( 'sales-type-indicator-free', $this->get_attribute_values($course_id) ) ){
			$msg .= "<p><b>Classroom Registration:</b> Registration for the Classroom Session will be open to participants, and a link to register for the "; 
			$msg .= "Classroom Session provided, once the eLearning Modules have been successfully completed and you have purchased the full version of the course.</p>";
		}
		// Blended Cert 1 regular course
		else if( in_array( 'sales-type-indicator-regular', $this->get_attribute_values($course_id) ) ){ 
			$msg .= "<p><b>Classroom Registration:</b> During the checkout and registration process, you were prompted to select an upcoming classroom ";
			$msg .= "course date. <span style='text-decoration: underline;font-weight:bolder;'>Please note, you must complete the eLearning prior to your selected classroom ";
			$msg .= "training date</span>. If you need to make a change to your classroom training date, please contact PSHSA Customer Service at ";
			$msg .= "416-250-2131 (1-877-250-7444)</p>";  
		}
		
		$msg .= "<p><b>Mandatory requirements for the Classroom Session:</b> To take part in training<br><ul>";
		$msg .= "<li>Participants <span style='text-decoration: underline;'>must</span> bring a piece of government-issued photo ID (such as a Driver's License or a Health Card) with them to the session.</li>";
		$msg .= "<li>The course will begin promptly at the start time. We strongly recommend participants arrive 30 minutes prior to the course commencement to sign in.</li>";  
		$msg .= "<li>In order to receive the required amount of instruction time as required by the MOL standard, participants will need to be present and ";
		$msg .= "participate through the entire day at the times scheduled. If you will need to miss more than 15 minutes per day, we encourage you to select ";
		$msg .= "an alternate date by contracting PSHSA's Customer Services at 416-250-2131 (Toll Free: 1-877-250-7444) or customerservice@pshsa.ca</li></ul></p>";

		$msg .= "<p><b>Optional requirements for the Classroom Session:</b><br>"; 
		$msg .= "<ul><li>Participants can complete a 'PSHSA Learner Consent form' (attached) ahead of the session; it must be completed at the start of ";
		$msg .= "the session otherwise.</li></ul></p>";

		$msg .= "<p><b>To Become Certified:</b> To become fully certified JHSC members, participants successfully complete both the JHSC Certification Part 1 ";
		$msg .= "Training Program and JHSC Certification Part 2 (Sector-Specific or Workplace-Specific hazard training).</p>";
		
		$msg .= "<p><b>Note cancellation policy:</b> Cancellations will be received up to <span style='color:#FF0000;font-weight:bolder;'>7</span> ";
		$msg .= "business days prior to the scheduled session.<span style='text-decoration: underline;font-weight:bolder;'>You must cancel this course prior to logging ";
		$msg .= "onto PSHSA's learning management system</span> by using the information above. Once the cancellation notice is received, ";
		$msg .= "the refund will be processed accordingly.</p>";
		
		$msg .= "<p><b>Classroom Training Course Transfers:</b> Transfers are accepted <span style='color:#FF0000;font-weight:bolder;'>7</span> ";
		$msg .= "business days prior to the scheduled session.</p>";

		$msg .= "<p><b>In the event of inclement weather, the scheduled training session may be cancelled. You will receive notification the day prior ";
		$msg .= "to the session providing the consultant's contact information with further instructions.</b></p>";
		
		$msg .= "<p>To eliminate health concerns arising from exposure to scented products please refrain from using or wearing scented products at ";
		$msg .= "all PSHSA training sessions.</p>";

		$msg .= "<p><b>Special Requests:</b> Public Services Health and Safety Association is committed to providing accessible services. ";
		$msg .= "We encourage you to voluntarily self-identify if you require any form of enhanced accessibility. Any such disclosure is ";
		$msg .= "confidential, in accordance with the Freedom of Information and Protection of Privacy Act. If you require assistance, please ";
		$msg .= "contact <span style='text-decoration: underline;'>AODA@pshsa.ca</span> or 416-250-2134. A member of our team will contact you.</p>";

		$msg .= "<p>To find a consultant in your area: http://".$_SERVER["SERVER_NAME"]."/consulting-support/find-your-consultant/ or for answers ";
		$msg .= "to your Health and Safety questions try our eConsulting service to chat with a live consultant or to submit a question: ";
		$msg .= "http://".$_SERVER["SERVER_NAME"]."/econsulting/</p>";

		$msg .= "<p>If you have any questions please do not hesitate to contact PSHSA at the number below.</p>";
		
		return $msg;
	}
	
	/** 
	 * Send confirmation email for JHSC Cert2 and molextended and not blended courses and not refresher courses
	*/
	function getJHSCCert2MOLExtendedCourseEmailBody($student_email, $student_password, $student_first_name, $student_last_name, $course_name, $course_id){
		
		$msg  = "<h2>Thank you for registering!</h2>";
		$msg .= "<p>Hello ".stripslashes($student_first_name).".</p>";
		
		$msg .= "<p>You have successfully registered for:<br><span style='color:#3366CC;font-weight:bolder;'>".$course_name."</span></p>";
		$msg .= "<p><b>Date:</b> ".date('M d, Y', strtotime(implode(" ", $this->get_product_terms($course_id, 'pa_start-date')))); 
 		$msg .= "<br><b>Time:</b> ".implode(" ", $this->get_product_terms($course_id, 'pa_time'));
		$msg .= "<br><b>Venue:</b> ".implode(" ", $this->get_product_terms($course_id, 'pa_session-info'));
		$msg .= "<br><b>Address:</b> " . implode(" ", $this->get_product_terms($course_id, 'pa_course-location')) . "</p>";
 		
		$msg .= "<p><b>About This Course:</b> To finish the JHSC Certification Part 2 Program, participants must successfully complete the written ";
		$msg .= "evaluation administered at the end the Training Program. This evaluation will determine whether or not a participant passes the course.</p>";
		
		$msg .= "<p><b>Mandatory requirements for the Classroom Session:</b> To take part in training<br><ul>";
		$msg .= "<li>Participants <span style='text-decoration: underline;'>must</span> bring a piece of government-issued photo ID (such as a Driver's ";
		$msg .= "License or a Health Card) with them to the session.</li>";
		$msg .= "<li>The course will begin promptly at the start time. We strongly recommend participants arrive 30 minutes prior to the course commencement to sign in.</li>";  
		$msg .= "<li>In order to receive the required amount of instruction time as required by the MOL standard, participants will need to be present and ";
		$msg .= "participate through the entire day at the times scheduled. If you will need to miss more than 15 minutes per day, we encourage you to select ";
		$msg .= "an alternate date by contracting PSHSA's Customer Services at 416-250-2131 (Toll Free: 1-877-250-7444) or customerservice@pshsa.ca</li></ul></p>";

		$msg .= "<p><b>Optional requirements for the Classroom Session:</b><br>";
		$msg .= "<ul><li>Participants can complete a 'PSHSA Learner Consent form' (attached) ahead of the session; it must be completed at the start of ";
		$msg .= "the session otherwise.</li></ul></p>";

		$msg .= "<p><b>To Become Certified:</b> To become fully certified JHSC members, participants successfully complete both the JHSC Certification ";
		$msg .= "Part 1 and JHSC Certification Part 2 (Sector-Specific or Workplace-Specific hazard training).</p>";   
		
		$msg .= "<p><b>Food and Beverages:</b> We do not provide food with training, so please bring your own lunch/snacks. All training materials will ";
		$msg .= "be provided to you upon arrival.</p>";     
		
		$msg .= "<p><b>Special Requests:</b> Public Services Health and Safety Association is committed to providing accessible services. We encourage ";
		$msg .= "you to voluntarily self-identify if you require any form of enhanced accessibility. Any such disclosure is confidential, in accordance ";
		$msg .= "with the Freedom of Information and Protection of Privacy Act. If you require assistance, please contact ";
		$msg .= "<span style='text-decoration: underline;'>AODA@pshsa.ca</span> or 416-250-2134. A member of our team will contact you.</p>";
		
		$msg .= "<p><b>Please note Cancellation Policy:</b> Cancellations will be received up to <span style='color:#FF0000;font-weight:bolder;'>7</span> ";
		$msg .= "business days prior to the scheduled session. Transfers are accepted <span style='color:#FF0000;font-weight:bolder;'>7</span> business ";
		$msg .= "days prior to the scheduled session.</p>";  
		
		$msg .= "<p><b>In the event of inclement weather, the scheduled training session may be cancelled. You will receive notification the day prior ";
		$msg .= "to the session providing the consultant's contact information with further instructions.</b></p>";
		
		$msg .= "<p>To eliminate health concerns arising from exposure to scented products please refrain from using or wearing scented products at all ";
		$msg .= "PSHSA training sessions.</p>"; 
		
		$msg .= "<p>If you have any questions please do not hesitate to contact PSHSA at the number below.</p>";

		return $msg;
	}
	
	/** 
	 * Send confirmation email for Blended & MOLExtended courses only
	*/
	function getWAHBlendedCourseEmailBody($student_email, $student_password, $student_first_name, $student_last_name, $sso_link, $course_name, $course_id){
			
		$msg  = "<h2>Thank you for registering!</h2>";
		$msg .= "<p>Welcome ".stripslashes($student_first_name)."!</p>";
		$msg .= "<p>You have successfully registered for the following course: <span style='color:#3366CC;font-weight:bolder;'>".$course_name."</span></p>"; 
		
		$msg .= "<p><b>About This Course:</b> The ".$course_name." Training program is made up of two parts:<br>";
		$msg .= "<ul><li>Part 1: ".$course_name." Basic Theory eLearning Module (3 hours)</li>";
		$msg .= "<li>Part 2: ".$course_name." Practical Classroom Module (1/2 day) + Evaluation</li></ul></p>";

		$msg .= "<p><b>To Start the Course:</b> You will receive a 2nd email from PSHSA's Learning Management System, inviting you to begin the "; 
		$msg .= "eLearning modules. Click the link provided in the invitation email to begin. Use this link each time you want to return to the eLearning ";
		$msg .= "modules. If you need to change your eLearning account password, use your Username: ".$student_email."</p>"; 
		
		$msg .= "<p>If your browser's pop-up blocker prevents your course from opening, please click the 'Launch' button to allow pshsa.litmos.com in";
		$msg .= " your pop-up blocker options. If you require assistance, please email one of our Customer Coordinators at LMS@pshsa.ca, or ";
		$msg .= "call 416-250-2131 (1-877-250-7444) and press 1 for course information.</p>";
		
		$msg .= "<p><b>To Become Certified:</b> To become certified to work at heights, participants must successfully complete the full ";
		$msg .= $course_name." Program and both the hands-on and written evaluations administered at the end of the Classroom Module (Part 2). ";
		$msg .= "These evaluations will test on knowledge and skills obtained in <span style='text-decoration: underline;'>both</span> the ";
		$msg .= "eLearning module and the classroom module.</p>";
		
		$msg .= "<p><b>Classroom Registration:</b> During the checkout and registration process, you were prompted to select an upcoming classroom course ";
		$msg .= "date. Please note, you must complete the eLearning prior to your selected classroom training date. If you need to make a change to your ";
		$msg .= "classroom training date, please contact PSHSA Customer Service at 416-250-2131 (1-877-250-7444)</p>";

		$msg .= "<p><b>Note cancellation policy:</b> You can cancel this course prior to logging onto PSHSA's learning management system by using the ";
		$msg .= "information above. Once the cancellation notice is received, the refund will be processed accordingly.</p>";

		$msg .= "<p>To find a consultant in your area: http://".$_SERVER["SERVER_NAME"]."/consulting-support/find-your-consultant/ or for answers ";
		$msg .= "to your Health and Safety questions try our eConsulting service to chat with a live consultant or to submit a question: ";
		$msg .= "http://".$_SERVER["SERVER_NAME"]."/econsulting/</p>";

		$msg .= "<p><b>Special Requests:</b> Public Services Health and Safety Association is committed to providing accessible services. ";
		$msg .= "We encourage you to voluntarily self-identify if you require any form of enhanced accessibility. Any such disclosure is ";
		$msg .= "confidential, in accordance with the Freedom of Information and Protection of Privacy Act. If you require assistance, please ";
		$msg .= "contact <span style='text-decoration: underline;'>AODA@pshsa.ca</span> or 416-250-2134. A member of our team will contact you.</p>";

		return $msg;
	}
	
	/** 
	 * Send confirmation email for Blended & HSMS courses only ( non molextended )
	*/
	function getHSMSBlendedCourseEmailBody($student_email, $student_password, $student_first_name, $student_last_name, $course_name, $course_id){
		
		$msg  = "<h2>Thank you for registering!</h2>";
		$msg .= "<p>Welcome ".stripslashes($student_first_name)."!</p>";
		$msg .= "<p>You have successfully registered for the following course: <span style='color:#3366CC;font-weight:bolder;'>".$course_name."</span></p>"; 
		
		$msg .= "<p><b>About This Course:</b><br>The ".$course_name." Training program is comprised of two parts:<br>";
		$msg .= "<ul><li>Part 1: eLearning module</li>";
		$msg .= "<li>Part 2: Classroom training</li></ul></p>";

		$msg .= "<p>This blended training program combines self-paced eLearning (approximately 3.5 hours) of online instruction, activities and ";
		$msg .= "knowledge checks with 1 day of face-to-face instruction, group discussions and exercises. <b>You must successfully complete the ";
		$msg .= "eLearning module before attending the classroom session.</b></p>"; 
		
		$msg .= "<p>This blended training program applies to the managers and supervisors in Ontario's education, emergency services, government and ";
		$msg .= "healthcare sectors. It is designed to provide a deeper understanding of occupational health and safety and how it contributes to ";
		$msg .= "supervisory responsibilities, impact workers and enhances the health and safety culture in the workplace. It covers what it means ";
		$msg .= "to be 'competent' and develops the knowledge and skills to effectively perform the role as a supervisor as stated in OHS ";
		$msg .= "legislation and in best practices.</p>";
		
		$msg .= "<p>The classroom session builds on the eLearning and explores the concept of moving beyond compliance from a supervisor to a health ";
		$msg .= "and safety leader and role model. It provides information and resources to aid in the development of critical thinking skills and ";
		$msg .= "better understanding of the supervisor's role as it relates to the OHSA.</p>";
		
		$msg .= "<p style='color:#3366CC;font-weight:bolder;'>Classroom course duration: 1 day (7.5 hours)</p>";
		$msg .= "<p style='color:#808080;font-weight:bolder;'>Upon completion of this program, you will be able to:</p>";
		
		$msg .= "<p><ul><li>Recognize OHS legislation with recall of key concepts covered in the eLearning module</li>";
		$msg .= "<li>Improve knowledge of supervisory roles and responsibilities and how this links to your workers, employer and workplace</li>";
		$msg .= "<li>Explore emergency preparedness and the key steps of an emergency response plan</li>";
		$msg .= "<li>Apply RACE/PEMEP to real life workplace examples (case studies)</li>";
		$msg .= "<li>Explore how to prevent and respond to incidents in the workplace</li>";
		$msg .= "<li>Understand the importance of health and safety culture</li>";
		$msg .= "<li>Identify leadership traits and practices that can contribute to making your workplace healthy and safe</li>";
		$msg .= "<li>Learn about practices and resources to assist with moving beyond compliance</li></ul></p>";
			
		$msg .= "<p><b>To Start the Course:</b>You will receive a 2nd email from PSHSA's Learning Management System (LMS), inviting you to begin ";
		$msg .= "the eLearning modules. Click the link provided in the invitation email to begin. Use this link each time you want to return to the ";
		$msg .= "eLearning. If you need to change your eLearning account password, use your Username: ".$student_email."</p>"; 
		
		$msg .= "<p>If your browser's pop-up blocker prevents your course from opening, please click the 'Launch' button to allow ";
		$msg .= "<span style='color:#3366CC;'>pshsa.litmos.com</span> in your pop-up blocker options. If you require assistance, please email one of ";
		$msg .= "our Customer Coordinators at <span style='color:#3366CC;'>LMS@pshsa.ca</span>, or call <span style='color:#3366CC;'>416-250-2131 ";
		$msg .= "(1-877-250-7444)</span> and press 1 for course information.</p>";
			
		$msg .= "<p><b>Classroom Registration:</b>During the checkout and registration process, you were prompted to select an upcoming classroom course ";
		$msg .= "date. Please note, you must complete the eLearning prior to your selected classroom training date. If you need to make a change to your ";
		$msg .= "classroom training date, please contact PSHSA Customer Service at 416-250-2131 (1-877-250-7444)</p>";

		$msg .= "<p><b>Special Requests:</b> Public Services Health and Safety Association is committed to providing accessible services. ";
		$msg .= "We encourage you to voluntarily self-identify if you require any form of enhanced accessibility. Any such disclosure is ";
		$msg .= "confidential, in accordance with the <i>Freedom of Information and Protection of Privacy Act.</i> If you require assistance, please ";
		$msg .= "contact <span style='color:#3366CC;text-decoration:underline;'>AODA@pshsa.ca</span> or 416-250-2134. A member of our team will contact you.</p>";
		
		$msg .= "<p><b><span style='text-decoration:underline;'>Note</span> -Cancellation Policy:</b> You can cancel this course prior to logging onto ";
		$msg .= "PSHSA's learning management system using the information that will be sent to you as outlined above. Once the cancellation notice ";
		$msg .= "is received, the refund will be processed accordingly.</p>";
		
		$msg .= "<p>To find a consultant in your area: http://".$_SERVER["SERVER_NAME"]."/consulting-support/find-your-consultant/ or for answers ";
		$msg .= "to your Health and Safety questions try our eConsulting service to chat with a live consultant or to submit a question: ";
		$msg .= "http://".$_SERVER["SERVER_NAME"]."/econsulting/</p>";
		
		return $msg;
	}	
	
	/** 
	 * Send a custom email only for Failed orders
	*/
	function getFailedOrderEmailBody($student_email, $student_first_name, $student_last_name){
		$msg = '';
		$failed_order_post = get_page_by_path( 'failed-order-post', OBJECT, 'post' );   //By post slug
		
		if ( $failed_order_post->post_status == 'publish' ):
			$msg .= "<p>Hello ".stripslashes($student_first_name)." ".stripslashes($student_last_name).",<p>";
			$msg .= $failed_order_post->post_content;
		endif;
		
		return $msg;
	}
	
	/**
	 * Fetch the word press user ID from the student table
	*/
	private function get_wp_user_id($username){
		
		global $wpdb;
		$wp_user_id = 0;
		$table	    = get_option( 'wcr_sql_table' );
		
		$student_record = $wpdb->get_results("SELECT * FROM ".$table." WHERE Email='". $username."'");
		foreach($student_record as $k=>$v){
			if ($v->wpuserID > 0){
				$wp_user_id = $v->wpuserID;
			}
		}
	
		return $wp_user_id;
	}
	
	/**
	 * Debug log.
	 *
	 * Add a debug log message to the debug file located in [plugin folder]/logs/.
	 *
	 * @since 1.0.0
	 */
	public function debug_log( $message ) {

		if ( get_option( 'enable_wcr_debug' ) ) :

			$log = fopen( plugin_dir_path( __FILE__ ) . '/logs/debug.txt', 'a+' );
			$message = '[' . date( 'd-m-Y H:i:s' ) . '] ' . $message . PHP_EOL;
			fwrite( $log, $message );
			fclose( $log );

		endif;

	}

	/*
	 **
	 * Fetch all the terms for the product
	*/
	private function get_attribute_values($product_id){
		$term_Training_Values = array();
		$term_Training        = get_the_terms( $product_id, 'pa_training' );
		
		if ( $term_Training && ! is_wp_error($term_Training) ) :
			foreach($term_Training as $oneterm){
				$term_Training_Values[] = $oneterm->name;
			}
		endif;
		
		return $term_Training_Values;
	}
	
	/*
	 **
	 * Fetch all the terms/attributes for the product
	*/
	private function get_product_terms($product_id, $attribute){
		$term_Values_Arr = array();
		$term_Values     = get_the_terms( $product_id, $attribute );
		
		if ( $term_Values && ! is_wp_error($term_Values) ) :
			foreach($term_Values as $oneterm){
				$term_Values_Arr[] = $oneterm->name;
			}
		endif;
		
		return $term_Values_Arr;
	}
	
}

if ( is_admin() ) :

	/**
	 * Admin classes
	 */
	require_once plugin_dir_path( __FILE__ ) . '/admin/class-wcr-admin-settings.php';
	require_once plugin_dir_path( __FILE__ ) . '/admin/class-wcr-admin.php';

endif;

global $checkout_registration;
$checkout_registration = new WooCommerce_Checkout_Registration();
