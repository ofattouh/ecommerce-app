<?php
/**
 * WooCommerce Moneris
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Moneris to newer
 * versions in the future. If you wish to customize WooCommerce Moneris for your
 * needs please refer to http://docs.woocommerce.com/document/moneris/ for more information.
 *
 * @package   WC-Gateway-Moneris/API
 * @author    SkyVerge
 * @copyright Copyright (c) 2012-2017, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Moneris API Class
 *
 * Handles sending/receiving/parsing of Moneris XML, this is the main API
 * class responsible for communication with the Moneris API
 *
 * @since 2.0
 */
class WC_Moneris_API implements SV_WC_Payment_Gateway_API {

	/** @var string identifier */
	private $id;

	/** @var string API URL endpoint */
	private $endpoint;

	/** @var string Moneris store id */
	private $store_id;

	/** @var string Moneris API token for store id */
	private $api_token;

	/** @var string integration country, one of 'ca' or 'us' */
	private $integration;

	/** @var SV_WC_Payment_Gateway_API_Request most recent request */
	private $request;

	/** @var SV_WC_Payment_Gateway_API_Response most recent response */
	private $response;

	/** @var \WC_Order order associated with the request */
	protected $order;


	/**
	 * Constructor - setup request object and set endpoint
	 *
	 * @since 2.0
	 * @param string $id identifier
	 * @param string $api_endpoint API URL endpoint
	 * @param string $store_id Moneris store id
	 * @param string $api_token Moneris API token for store id
	 * @param string $integration optional integration country, one of 'ca' or 'us', defaults to 'ca'
	 */
	public function __construct( $id, $api_endpoint, $store_id, $api_token, $integration = 'ca' ) {

		$this->id          = $id;
		$this->endpoint    = $api_endpoint;
		$this->store_id    = $store_id;
		$this->api_token   = $api_token;
		$this->integration = $integration;
	}


	/** Request methods ******************************************************/


	/**
	 * Create a new cc charge (purchase) transaction using Moneris XML API
	 *
	 * This request, if successful, causes a charge to be incurred by the
	 * specified credit card. Notice that the authorization for the charge is
	 * obtained when the card issuer receives this request. The resulting
	 * authorization code is returned in the response to this request.
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API::credit_card_charge()
	 * @param WC_Order $order the order
	 * @return WC_Moneris_Credit_Card_Charge_Response Moneris API credit card charge response object
	 * @throws Exception network timeouts, etc
	 */
	public function credit_card_charge( WC_Order $order ) {

		$this->order = $order;

		$request = $this->get_new_request();
		$request->credit_card_charge( $order );

		return $this->perform_request( $request, 'WC_Moneris_API_Response' );
	}


	/**
	 * Create a new cc auth (pre-auth) transaction using Moneris XML API
	 *
	 * This request is used for a transaction in which the merchant needs
	 * authorization of a charge, but does not wish to actually make the charge
	 * at this point in time. For example, if a customer orders merchandise to
	 * be shipped, you could issue this request at the time of the order to
	 * make sure the merchandise will be paid for by the card issuer. Then at
	 * the time of actual merchandise shipment, you perform the actual charge
	 * using the capture requst.
	 *
	 * Note: A PreAuth transaction must be reversed within 72 hours by sending
	 * a $0 capture if funds are not to be captured
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API::credit_card_authorization()
	 * @param WC_Order $order the order
	 * @return WC_Moneris_API_Credit_Card_Authorization_Response Moneris API credit card auth response object
	 * @throws Exception network timeouts, etc
	 */
	public function credit_card_authorization( WC_Order $order ) {

		$this->order = $order;

		$request = $this->get_new_request();
		$request->credit_card_auth( $order );

		return $this->perform_request( $request, 'WC_Moneris_API_Response' );
	}


	/**
	 * Capture funds for a credit card authorization (pre-auth) using Moneris XML API
	 *
	 * This request can be made only after a previous and successful
	 * authorization (pre-auth) request, where the card issuer has authorized a
	 * charge to be made against the specified credit card in the future. The
	 * order_id and txn_number from that prior transaction must be used in this
	 * subsequent and related transaction. This request actually causes that
	 * authorized charge to be incurred against the customer's credit card.
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API::credit_card_capture()
	 * @param WC_Order $order the order
	 * @return SV_WC_Payment_Gateway_API_Response credit card capture response
	 * @throws Exception network timeouts, etc
	 */
	public function credit_card_capture( WC_Order $order ) {

		$this->order = $order;

		$request = $this->get_new_request();
		$request->credit_card_capture( $order );

		return $this->perform_request( $request, 'WC_Moneris_API_Response' );
	}


	/**
	 * Reverse a credit card authorization (pre-auth) using Moneris XML API
	 *
	 * This request can be made only after a previous and successful
	 * authorization (pre-auth) request. The order_id and txn_number from that
	 * prior transaction must be used in this subsequent and related transaction.
	 *
	 * An authorization must either be captured or reversed within 72 hours
	 *
	 * @since 2.0
	 * @see WC_Moneris_API::credit_card_capture()
	 * @param WC_Order $order the order
	 * @return SV_WC_Payment_Gateway_API_Response credit card capture response
	 * @throws Exception network timeouts, etc
	 */
	public function credit_card_authorization_reverse( WC_Order $order ) {

		$this->order = $order;

		// an authorization is reversed by capturing $0
		$order->capture->amount = '0.00';

		$request = $this->get_new_request();
		$request->credit_card_capture( $order );

		return $this->perform_request( $request, 'WC_Moneris_API_Response' );
	}


	/**
	 * Perform a refund for the given order
	 *
	 * If the gateway does not support refunds, this method can be a no-op.
	 *
	 * @since 2.2.0
	 * @see SV_WC_Payment_Gateway_API::refund()
	 * @param WC_Order $order order object
	 * @return SV_WC_Payment_Gateway_API_Response refund response
	 * @throws SV_WC_Payment_Gateway_Exception network timeouts, etc
	 */
	public function refund( WC_Order $order ) {
		// TODO
	}


	/**
	 * Perform a void for the given order
	 *
	 * If the gateway does not support voids, this method can be a no-op.
	 *
	 * @since 2.2.0
	 * @see SV_WC_Payment_Gateway_API::void()
	 * @param WC_Order $order order object
	 * @return SV_WC_Payment_Gateway_API_Response void response
	 * @throws SV_WC_Payment_Gateway_Exception network timeouts, etc
	 */
	public function void( WC_Order $order ) {
		// TODO
	}


	/**
	 * Store sensitive payment information for a particular customer.  If the
	 * $order object has both `wc_moneris_trans_id` and `wc_moneris_receipt_id`
	 * members, the ResTokenizeCC request will be used to tokenize an existing
	 * transaction.  Otherwise, the ResAddCC requet will be used.
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API::tokenize_payment_method()
	 * @param WC_Order $order the order with associated payment and customer info
	 * @return WC_Moneris_API_Response Moneris API wallet add response
	 * @throws Exception network timeouts, etc
	 */
	public function tokenize_payment_method( WC_Order $order ) {

		$this->order = $order;

		$request = $this->get_new_request();
		$request->tokenize_payment_method( $order );

		return $this->perform_request( $request, 'WC_Moneris_API_Create_Payment_Token_Response' );

	}


	/**
	 * Removes the tokenized payment method
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API::remove_tokenized_payment_method()
	 * @param string $token the payment method token
	 * @param string $customer_id unique Moneris customer id
	 * @return WC_Moneris_API_Response remove tokenized payment method response
	 * @throws Exception network timeouts, etc
	 */
	public function remove_tokenized_payment_method( $token, $customer_id ) {

		$request = $this->get_new_request();
		$request->delete_tokenized_payment_method( $token, $customer_id );

		return $this->perform_request( $request, 'WC_Moneris_API_Delete_Payment_Token_Response' );

	}


	/**
	 * Returns true, as Moneris supports a tokenized payment method remove request
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API::supports_remove_tokenized_payment_method()
	 * @return boolean true
	 */
	public function supports_remove_tokenized_payment_method() {
		return true;
	}


	/** Interac methods ******************************************************/


	/**
	 * Confirm an Interac idebit transaction by sending a purchase request
	 *
	 * @since 2.0
	 * @param WC_Order $order the order
	 * @return SV_WC_Payment_Gateway_API_Response credit card capture response
	 * @throws Exception network timeouts, etc
	 */
	public function idebit_purchase( WC_Order $order ) {

		$this->order = $order;

		$request = $this->get_new_request();
		$request->idebit_purchase( $order );

		return $this->perform_request( $request, 'WC_Moneris_API_iDebit_Response' );
	}


	/** Helper methods ******************************************************/


	/**
	 * Perform the request post to the active endpoint
	 *
	 * @since 2.0
	 * @param WC_Moneris_API_Request $request the request object
	 * @param string $response_class_name the class name of the response
	 * @return WC_Moneris_API_Response response object
	 * @throws Exception network timeouts
	 */
	private function perform_request( $request, $response_class_name ) {

		// save the request object
		$this->request = $request;

		/**
		 * Moneris API Request URI Filter.
		 *
		 * Allow the Moneris API request URI to be filtered on a per-request basis,
		 * primarily for multi-currency support.
		 *
		 * @TODO once this SV_WC_API_Base, this can be removed @MR 2015-08-27
		 *
		 * @since 2.3.2
		 * @param string $url request URI
		 * @param \WC_Moneris_API $this class instance
		 */
		$this->endpoint = apply_filters( 'wc_moneris_api_request_uri', $this->endpoint, $this );

		$method = 'POST';

		// perform the request
		$wp_http_args = array(
			'method'      => $method,
			'timeout'     => 60, // seconds
			'redirection' => 0,
			'httpversion' => '1.0',
			'sslverify'   => true,
			'blocking'    => true,
			'user-agent'  => "WooCommerce/" . WC_VERSION,
			'body'        => trim( $request->to_xml() ),
			'cookies'     => array(),
		);

		$start_time = microtime( true );
		$response = wp_safe_remote_post( $this->endpoint, $wp_http_args );
		$time = round( microtime( true ) - $start_time, 5 );

		// prepare the request/response data for the request performed action
		$request_data  = array( 'method' => $method, 'uri' => $this->endpoint, 'body' => $request->to_string_safe(), 'time' => $time );
		$response_data = null;

		// Check for Network timeout, etc.
		if ( is_wp_error( $response ) ) {

			do_action( 'wc_' . $this->id . '_api_request_performed', $request_data, $response_data );

			throw new SV_WC_Payment_Gateway_Exception( $response->get_error_message() );
		}

		// now we know the response isn't an error
		$response_data = array( 'code' => ( isset( $response['response']['code'] ) ) ? $response['response']['code'] : '', 'body' => ( isset( $response['body'] ) ) ? $response['body'] : '' );

		// Status Codes:
		// 200 - success
		if ( 200 != $response['response']['code'] ) {

			// response will include the http status code/message
			$message = sprintf( "HTTP %s: %s", $response['response']['code'], $response['response']['message'] );

			// the body (if any)
			if ( trim( $response['body'] ) ) {
				$message .= ' - ' . $response['body'];
			}

			do_action( 'wc_' . $this->id . '_api_request_performed', $request_data, $response_data );

			throw new SV_WC_Payment_Gateway_Exception( $message );
		}

		// return blank XML document if response body doesn't exist
		$response = ( isset( $response[ 'body' ] ) ) ? $response[ 'body' ] : '<?xml version="1.0" encoding="utf-8"?>';

		// create the response and tie it to the request
		$response = $this->parse_response( $response_class_name, $request, $response );

		// full response object
		$response_data['body'] = $response->to_string_safe();

		do_action( 'wc_' . $this->id . '_api_request_performed', $request_data, $response_data );

		return $response;
	}


	/**
	 * Return a new WC_Moneris_API_Response object from the response XML
	 *
	 * @since 2.0
	 * @param string $response_class_name the class name of the response
	 * @param WC_Moneris_API_Request $request the request
	 * @param string $response xml response
	 * @return WC_Moneris_API_Response API response object
	 */
	private function parse_response( $response_class_name, $request, $response ) {

		// save the most recent response object
		return $this->response = new $response_class_name( $request, $response );

	}


	/**
	 * Builds and returns a new API request object
	 *
	 * @since 2.0
	 * @return WC_Intuit_QBMS_API_Request API request object
	 */
	private function get_new_request() {

		/**
		 * Filters the new API request args.
		 *
		 * @since 2.7.1
		 *
		 * @param array {
		 *
		 *
		 *    @type string $store_id    configured store ID
		 *    @type string $api_token   configured API token
		 *    @type string $integration configured integration
		 * }
		 */
		$args = apply_filters( 'wc_moneris_api_new_request_args', array(
			'store_id'    => $this->store_id,
			'api_token'   => $this->api_token,
			'integration' => $this->integration,
		), $this->get_order(), $this );

		return new WC_Moneris_API_Request( $args['store_id'], $args['api_token'], $args['integration'] );
	}


	/**
	 * Returns the most recent request object
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API::get_request()
	 * @return SV_WC_Payment_Gateway_API_Request the most recent request object
	 */
	public function get_request() {

		return $this->request;
	}


	/**
	 * Returns the most recent response object
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API::get_response()
	 * @return SV_WC_Payment_Gateway_API_Response the most recent response object
	 */
	public function get_response() {

		return $this->response;
	}


	/**
	 * Returns the order associated with a request, if any
	 *
	 * @since 2.3.2
	 * @return \WC_Order
	 */
	public function get_order() {

		return $this->order;
	}


	/**
	 * Returns the gateway instance associated with this request
	 *
	 * @since 2.3.2
	 * @return string
	 */
	public function get_gateway() {

		return wc_moneris()->get_gateway( $this->id );
	}



	/** No-op methods ******************************************************/


	/**
	 * Perform a customer ACH check debit transaction using the Moneris XML API
	 *
	 * An amount will be debited from the customer's account to the merchant's account.
	 *
	 * @since 2.0
	 * @param WC_Order $order the order
	 * @return SV_WC_Payment_Gateway_API_Response check debit response
	 * @throws Exception network timeouts, etc
	 */
	public function check_debit( WC_Order $order ) {
		// no-op: not implemented yet
	}


	/**
	 * Moneris does not support retrieving all tokenized payment methods for a
	 * profile.
	 *
	 * It does however have a request to verify a payment token, which we could
	 * do something with
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API::get_tokenized_payment_methods()
	 * @param string $customer_id unique Moneris customer id
	 * @return SV_WC_API_Get_Tokenized_Payment_Methods_Response get tokenized payment methods response
	 * @throws Exception network timeouts, etc
	 */
	public function get_tokenized_payment_methods( $customer_id ) {
		// no-op
	}


	/**
	 * Returns false, as Moneris does not support a tokenized payment method query request
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API::supports_get_tokenized_payment_methods()
	 * @return boolean true
	 */
	public function supports_get_tokenized_payment_methods() {
		return false;
	}


}
