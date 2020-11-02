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
 * Moneris API Request Class
 *
 * Generates XML required by API specs to perform an API request
 *
 * Data Validation: Moneris defines maximum lengths and allowed characters for
 * all request fields, however these rules do not seem to be enforced, so we're
 * not bothering with them.
 *
 * @since 2.0
 */
class WC_Moneris_API_Request extends XMLWriter implements SV_WC_Payment_Gateway_API_Request {


	/** @var string the request xml */
	private $request_xml;

	/** @var string Moneris store id */
	private $store_id;

	/** @var string Moneris API token for store id */
	private $api_token;

	/** @var string integration country, one of 'ca' or 'us' */
	private $integration;

	/** @var WC_Order optional order object if this request was associated with an order */
	protected $order;

	/** @var string the type of this request, one of 'preauth', 'purchase', or 'completion' */
	protected $request_type;

	/** e-commerce indicator */
	const CRYPT_TYPE_SSL_ENABLED_MERCHANT = 7;


	/**
	 * Construct a Moneris request object
	 *
	 * @since 2.0
	 * @param string $store_id Moneris store id
	 * @param string $api_token Moneris API token for store id
	 * @param string $integration integration country, one of 'ca' or 'us'
	 */
	public function __construct( $store_id, $api_token, $integration ) {

		$this->store_id    = $store_id;
		$this->api_token   = $api_token;
		$this->integration = $integration;
	}


	/**
	 * Returns the transaction type prefix
	 *
	 * @since 2.0
	 * @return string transaction type prefix based on current integration country
	 */
	protected function get_type_prefix() {
		return $this->integration == 'us' ? 'us_' : '';
	}


	/**
	 * Creates a credit card auth request for the payment method/
	 * customer associated with $order
	 *
	 * @since 2.0
	 * @param WC_Order $order the order object
	 */
	public function credit_card_auth( $order ) {

		if ( isset( $order->payment->token ) && $order->payment->token ) {
			$this->request_type = 'res_preauth_cc';
		} else {
			$this->request_type = 'preauth';
		}

		$this->credit_card_charge_auth_request( $this->get_type_prefix() . $this->request_type, $order );
	}


	/**
	 * Creates a credit card charge request for the payment method/
	 * customer associated with $order
	 *
	 * @since 2.0
	 * @param WC_Order $order the order object
	 */
	public function credit_card_charge( $order ) {

		if ( isset( $order->payment->token ) && $order->payment->token ) {
			$this->request_type = 'res_purchase_cc';
		} else {
			$this->request_type = 'purchase';
		}

		$this->credit_card_charge_auth_request( $this->get_type_prefix() . $this->request_type, $order );
	}


	/**
	 * Creates a credit card capture request for the payment method/
	 * customer associated with $order
	 *
	 * @since 2.0
	 * @param WC_Order $order the order object
	 */
	public function credit_card_capture( $order ) {

		// store the order object for later use
		$this->order = $order;
		$this->request_type = 'completion';

		$this->init_document();

		// <completion|us_completion>
		$this->startElement( $this->get_type_prefix() . $this->request_type );

		// the order of these elements CANNOT be changed.  THIS MEANS YOU, FUTURE JUSTIN
		$this->writeElement( 'order_id',    $order->capture->receipt_id );
		$this->writeElement( 'comp_amount', $order->capture->amount );
		$this->writeElement( 'txn_number',  $order->capture->trans_id );
		$this->writeElement( 'crypt_type',  self::CRYPT_TYPE_SSL_ENABLED_MERCHANT );

		// </completion|us_completion>
		$this->endElement();

		$this->close_document();
	}


	/**
	 * Tokenize the payment method associated with $order.  This can be used to
	 * tokenize a brand new credit card, if $order has members
	 * `payment->account_number` and `payment->exp_year`, or it can be used to
	 * tokenize the payment method used with a previous transaction if $order
	 * has the members `cc_moneris_receipt_id` and `wc_moneris_trans_id`
	 *
	 * @since 2.0
	 * @param WC_Order $order the order object
	 */
	public function tokenize_payment_method( $order ) {

		// store the order object for later use
		$this->order = $order;

		if ( isset( $order->payment->trans_id ) && $order->payment->trans_id &&
			isset( $order->payment->receipt_id ) && $order->payment->receipt_id ) {
			// Tokenize a previous transaction
			$this->request_type = 'res_tokenize_cc';

			$this->init_document();

			// <res_tokenize_cc|us_res_tokenize_cc>
			$this->startElement( $this->get_type_prefix() . $this->request_type );

			$this->writeElement( 'order_id',           $order->payment->receipt_id );
			$this->writeElement( 'txn_number',         $order->payment->trans_id );
			$this->writeElement( 'cust_id',            $order->customer_id );
			$this->writeElement( 'phone',              SV_WC_Order_Compatibility::get_prop( $order, 'billing_phone' ) );
			$this->writeElement( 'email',              SV_WC_Order_Compatibility::get_prop( $order, 'billing_email' ) );
			$this->writeElement( 'note',               SV_WC_Order_Compatibility::get_prop( $order, 'customer_note' ) );
		} else {
			// Add a new credit card or make temporary token permanent
			if ( isset( $order->payment->account_number ) && $order->payment->account_number ) {
				$this->request_type = 'res_add_cc';
			} elseif ( isset( $order->payment->token ) && $order->payment->token ) {
				$this->request_type = 'res_add_token';
			}

			$this->init_document();

			// <res_add_cc|us_res_add_cc>
			$this->startElement( $this->get_type_prefix() . $this->request_type );

			$this->writeElement( 'cust_id',            $order->customer_id );
			$this->writeElement( 'phone',              SV_WC_Order_Compatibility::get_prop( $order, 'billing_phone' ) );
			$this->writeElement( 'email',              SV_WC_Order_Compatibility::get_prop( $order, 'billing_email' ) );
			$this->writeElement( 'note',               SV_WC_Order_Compatibility::get_prop( $order, 'customer_note' ) );
			if ( isset( $order->payment->account_number ) && $order->payment->account_number ) {
				$this->writeElement( 'pan',            $order->payment->account_number  );
			} elseif ( isset( $order->payment->token ) && $order->payment->token ) {
				$this->writeElement( 'data_key',       $order->payment->token  );
			}
			$this->writeElement( 'expdate',            substr( $order->payment->exp_year, -2 ) . $order->payment->exp_month ); // YYMM
			$this->writeElement( 'crypt_type',         self::CRYPT_TYPE_SSL_ENABLED_MERCHANT );
		}

		// include avs fields?
		if ( $order->perform_avs ) {
			$this->add_avs_elements( $order );
		}

		// </res_tokenize_cc|us_res_tokenize_cc|res_add_cc|us_res_add_cc>
		$this->endElement();

		$this->close_document();
	}


	/**
	 * Request to delete a payment token
	 *
	 * @since 2.0
	 * @param string $token the token to delete
	 * @param string $customer_id the associated customer id
	 */
	public function delete_tokenized_payment_method( $token, $customer_id ) {

		// Delete an existing tokenized credit card
		$this->request_type = 'res_delete';

		$this->init_document();

		// <res_delete|us_res_delete>
		$this->startElement( $this->get_type_prefix() . $this->request_type );

		$this->writeElement( 'data_key', $token );

		// </res_delete|us_res_delete>
		$this->endElement();

		$this->close_document();
	}


	/** Interac Methods ******************************************************/


	/**
	 * Confirms an Interac idebit purchase for the given order
	 *
	 * $order is expected to have a `payment->track2` member, which is used to
	 * confirm the payment
	 *
	 * @since 2.0
	 * @param WC_Order $order the order
	 */
	public function idebit_purchase( $order ) {

		$this->order = $order;

		// Delete an existing tokenized credit card
		$this->request_type = 'idebit_purchase';

		$this->init_document();

		// <idebit_purchase>
		$this->startElement( $this->request_type );

		$this->writeElement( 'order_id',      preg_replace( '/[^a-zA-Z0-9-]/', '', $order->unique_transaction_ref ) );
		$this->writeElement( 'cust_id',       $order->customer_id );
		$this->writeElement( 'amount',        number_format( $order->payment_total, 2, '.', '' ) );
		$this->writeElement( 'idebit_track2', $order->payment->track2 );

		// </idebit_purchase>
		$this->endElement();

		$this->close_document();
	}


	/** Helper Methods ******************************************************/


	/**
	 * Helper to return completed XML document
	 *
	 * @since 2.0
	 * @return string XML
	 */
	public function to_xml() {
		return $this->request_xml;
	}


	/**
	 * Returns the string representation of this request
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Request::to_string()
	 * @return string request XML
	 */
	public function to_string() {

		$string = $this->to_xml();

		$dom = new DOMDocument();

		// suppress errors for invalid XML syntax issues
		if ( @$dom->loadXML( $string ) ) {
			$dom->formatOutput = true;
			$string = $dom->saveXML();
		}

		return $string;
	}


	/**
	 * Returns the string representation of this request with any and all
	 * sensitive elements masked or removed
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Request::to_string_safe()
	 * @return string the request XML, safe for logging/displaying
	 */
	public function to_string_safe() {

		$request = $this->to_string();

		// replace merchant authentication
		if ( preg_match( '/<api_token>(.*)<\/api_token>/', $request, $matches ) ) {
			$request = preg_replace( '/<api_token>.*<\/api_token>/', '<api_token>' . str_repeat( '*', strlen( $matches[1] ) ) . '</api_token>', $request );
		}

		// replace real card number
		if ( preg_match( '/<pan>(.*)<\/pan>/', $request, $matches ) ) {
			$request = preg_replace( '/<pan>.*<\/pan>/', '<pan>' . substr( $matches[1], 0, 1 ) . str_repeat( '*', strlen( $matches[1] ) - 5 ) . substr( $matches[1], -4 ) . '</pan>', $request );
		}

		// replace real CSC code
		if ( preg_match( '/<cvd_value>(.*)<\/cvd_value>/', $request, $matches ) ) {
			$request = preg_replace( '/<cvd_value>.*<\/cvd_value>/', '<cvd_value>' . str_repeat( '*', strlen( $matches[1] ) ) . '</cvd_value>', $request );
		}

		// replace interac account number
		if ( preg_match( '/<idebit_track2>(.*)<\/idebit_track2>/', $request, $matches ) ) {
			list( $pan, $suffix ) = explode( '=', $matches[1] );
			$pan = substr( $pan, 0, 1 ) . str_repeat( '*', strlen( $pan ) - 5 ) . substr( $pan, -4 );
			$request = preg_replace( '/<idebit_track2>.*<\/idebit_track2>/', '<idebit_track2>' . $pan . '=' . $suffix . '</idebit_track2>', $request );
		}

		return $request;

	}


	/**
	 * Initialize the document by opening memory, adding doc encoding, qbmsxml
	 * version, opening the QBMSXML root element, and adding the auth element
	 *
	 * @since 2.0
	 */
	private function init_document() {

		// Create XML document in memory
		$this->openMemory();

		// Set XML version & encoding
		$this->startDocument( '1.0', 'UTF-8' );

		// root element <request>
		$this->startElement( 'request' );

		// add the common authentication elements
		$this->add_auth_elements( $this->store_id, $this->api_token );
	}


	/**
	 * Closes the XML document and saves the request XML
	 *
	 * @since 2.0
	 */
	private function close_document() {

		// </request>
		$this->endElement();

		$this->endDocument();

		// save the request xml
		$this->request_xml = $this->outputMemory();
	}


	/**
	 * Adds the authentication information to the request
	 *
	 * @since 2.0
	 * @param string $store_id Moneris store id
	 * @param string $api_token Moneris API token for store id
	 */
	private function add_auth_elements( $store_id, $api_token ) {

		/**
		 * Filter the authentication info used for API requests.
		 *
		 * This can be used to vary the authentication on a per-order basis, for
		 * things like multi-currency support :)
		 *
		 * Warning: this may be removed in a future release and replace with a
		 * filter at the request data level (e.g. a filter around an array of request data)
		 *
		 * @since 2.2.2
		 * @param array $auth {
		 *   @type string $store_id store ID
		 *   @type string $api_token API token
		 * }
		 * @param \WC_Moneris_API_Request $this Moneris API request class instance
		 */
		$auth = apply_filters( 'wc_moneris_api_request_auth_info', array( 'store_id' => $store_id, 'api_token' => $api_token ), $this->get_order(), $this );

		$this->writeElement( 'store_id',  $auth['store_id'] );
		$this->writeElement( 'api_token', $auth['api_token'] );
	}


	/**
	 * Adds the avs elements to the request
	 *
	 * @since 2.0
	 * @param WC_Order $order the order
	 */
	private function add_avs_elements( $order ) {

		// <avs_info>
		$this->startElement( 'avs_info' );

		$this->writeElement( 'avs_street_name', SV_WC_Order_Compatibility::get_prop( $order, 'billing_address_1' ) );

		// Added this filter because http://woothemes.zendesk.com/agent/tickets/230635 claimed that zip+4 failed
		$this->writeElement( 'avs_zipcode', apply_filters( 'wc_gateway_moneris_request_avs_zipcode', str_replace( '-', '', SV_WC_Order_Compatibility::get_prop( $order, 'billing_postcode' ) ), $order ) );

		if ( 'purchase' == $this->request_type || 'preauth' == $this->request_type ) {
			$this->writeElement( 'avs_shiptocountry', SV_WC_Order_Compatibility::get_prop( $order, 'shipping_country' ) );
			$this->writeElement( 'avs_custphone',     SV_WC_Order_Compatibility::get_prop( $order, 'billing_phone' ) );
			$this->writeElement( 'avs_custip',        SV_WC_Order_Compatibility::get_prop( $order, 'customer_ip_address' ) );
			$this->writeElement( 'avs_browser',       SV_WC_Order_Compatibility::get_prop( $order, 'customer_user_agent' ) );
		}

		// <avs_info>
		$this->endElement();
	}


	/**
	 * Adds the csc elements to the request
	 *
	 * @since 2.0
	 * @param WC_Order $order the order
	 */
	private function add_csc_elements( $order ) {

		// <cvd_info>
		$this->startElement( 'cvd_info' );

		$this->writeElement( 'cvd_indicator', 1 );
		$this->writeElement( 'cvd_value',     $order->payment->csc );

		// </cvd_info>
		$this->endElement();
	}


	/**
	 * Adds the billing elements to the request
	 *
	 * @since 2.0
	 * @param WC_Order $order the order
	 */
	private function add_billing_elements( $order ) {

		$address = $this->get_order_address( $order, 'billing' );

		// <billing>
		$this->startElement( 'billing' );

		$this->writeElement( 'first_name',    $address['first_name'] );
		$this->writeElement( 'last_name',     $address['last_name'] );
		$this->writeElement( 'company_name',  $address['company'] );
		$this->writeElement( 'address',       trim( $address['address_1'] . ' ' . $address['address_2'] ) );
		$this->writeElement( 'city',          $address['city'] );
		$this->writeElement( 'province',      $address['state'] );
		$this->writeElement( 'postal_code',   $address['postcode'] );
		$this->writeElement( 'country',       $address['country'] );
		$this->writeElement( 'phone_number',  SV_WC_Order_Compatibility::get_prop( $order, 'billing_phone' ) );
		$this->writeElement( 'fax',           '' );
		$this->writeElement( 'tax1',          '' );
		$this->writeElement( 'tax2',          '' );
		$this->writeElement( 'tax3',          '' );
		$this->writeElement( 'shipping_cost', number_format( SV_WC_Order_Compatibility::get_prop( $order, 'shipping_total', 'view' ), 2, '.', '' ) );

		// </billing>
		$this->endElement();
	}


	/**
	 * Adds the shipping elements to the request
	 *
	 * @since 2.0
	 * @param WC_Order $order the order
	 */
	private function add_shipping_elements( $order ) {

		$address = $this->get_order_address( $order, 'shipping' );

		// <shipping>
		$this->startElement( 'shipping' );

		$this->writeElement( 'first_name',    $address['first_name'] );
		$this->writeElement( 'last_name',     $address['last_name'] );
		$this->writeElement( 'company_name',  $address['company'] );
		$this->writeElement( 'address',       trim( $address['address_1'] . ' ' . $address['address_2'] ) );
		$this->writeElement( 'city',          $address['city'] );
		$this->writeElement( 'province',      $address['state'] );
		$this->writeElement( 'postal_code',   $address['postcode'] );
		$this->writeElement( 'country',       $address['country'] );
		$this->writeElement( 'phone_number',  '' );
		$this->writeElement( 'fax',           '' );
		$this->writeElement( 'tax1',          '' );
		$this->writeElement( 'tax2',          '' );
		$this->writeElement( 'tax3',          '' );
		$this->writeElement( 'shipping_cost', number_format( SV_WC_Order_Compatibility::get_prop( $order, 'shipping_total', 'view' ), 2, '.', '' ) );

		// </shipping>
		$this->endElement();
	}


	/**
	 * Gets an order's address of a certain type.
	 *
	 * If getting the shipping address on virtual orders, this falls back to the
	 * billing address since WC 3.0+ doesn't set shipping address data for
	 * virtual orders.
	 *
	 * @since 2.7.1
	 *
	 * @param \WC_Order $order order object
	 * @param string $type address type, either 'billing' or 'shipping'
	 *
	 * @return array $address order address
	 */
	private function get_order_address( $order, $type = 'billing' ) {

		if ( 'shipping' === $type && ! SV_WC_Order_Compatibility::get_prop( $order, 'shipping_country' ) ) {
			$type = 'billing';
		}

		return array(
			'first_name' => SV_WC_Order_Compatibility::get_prop( $order, "{$type}_first_name" ),
			'last_name'  => SV_WC_Order_Compatibility::get_prop( $order, "{$type}_last_name" ),
			'company'    => SV_WC_Order_Compatibility::get_prop( $order, "{$type}_company" ),
			'address_1'  => SV_WC_Order_Compatibility::get_prop( $order, "{$type}_address_1" ),
			'address_2'  => SV_WC_Order_Compatibility::get_prop( $order, "{$type}_address_2" ),
			'city'       => SV_WC_Order_Compatibility::get_prop( $order, "{$type}_city" ),
			'state'      => SV_WC_Order_Compatibility::get_prop( $order, "{$type}_state" ),
			'postcode'   => SV_WC_Order_Compatibility::get_prop( $order, "{$type}_postcode" ),
			'country'    => SV_WC_Order_Compatibility::get_prop( $order, "{$type}_country" ),
		);
	}


	/**
	 * Adds the item elements to the request
	 *
	 * @since 2.0
	 * @param WC_Order $order the order
	 */
	private function add_item_elements( $order ) {

		foreach ( $order->get_items() as $item ) {

			$product = $order->get_product_from_item( $item );

			// <item>
			$this->startElement( 'item' );

			// note: the documentation make it look like 'description' should be used for the US integration rather than 'name', but this does not seem to be accurate
			$this->writeElement( 'name',            $item['name'] );
			$this->writeElement( 'quantity',        $item['qty'] );
			$this->writeElement( 'product_code',    $product->get_sku() );
			$this->writeElement( 'extended_amount', number_format( $item['line_total'], 2, '.', '' ) ); // This must contain at least 3 digits with two penny values. The minimum value passed can be 0.01 and the maximum 9999999.99

			// </item>
			$this->endElement();

		}
	}


	/**
	 * Adds the customer info elements to the request
	 *
	 * @since 2.0
	 * @param WC_Order $order the order
	 */
	private function add_cust_info_elements( $order ) {

		// <cust_info>
		$this->startElement( 'cust_info' );

		$this->writeElement( 'email',        SV_WC_Order_Compatibility::get_prop( $order, 'billing_email' ) );
		$this->writeElement( 'instructions', SV_WC_Order_Compatibility::get_prop( $order, 'customer_note' ) );

		$this->add_billing_elements( $order );

		$this->add_shipping_elements( $order );

		$this->add_item_elements( $order );

		// </cust_info>
		$this->endElement();
	}


	/**
	 * Add the credit credit card charge or auth elements
	 *
	 * @since 2.0
	 * @param string $request_type one of preauth, purchase, us_preauth, us_purchase
	 * @param WC_Order $order the order object
	 */
	private function credit_card_charge_auth_request( $request_type, $order ) {

		// store the order object for later use
		$this->order = $order;

		$this->init_document();

		// <preauth|purchase|us_preauth|us_purchase|res_preauth_cc|us_res_preauth_cc|res_purchase_cc|us_res_purchase_cc>
		$this->startElement( $request_type );

		if ( isset( $order->payment->token ) && $order->payment->token ) {
			$this->writeElement( 'data_key',       $order->payment->token );
		}

		$this->writeElement( 'order_id',           preg_replace( '/[^a-zA-Z0-9-]/', '', $order->unique_transaction_ref ) );
		if ( $order->customer_id ) {
			// empty value results in "Cancelled: Transaction data cannot have empty elements"
			$this->writeElement( 'cust_id', $order->customer_id );
		}
		$this->writeElement( 'amount',             number_format( $order->payment_total, 2, '.', '' ) );

		if ( ! isset( $order->payment->token ) || ! $order->payment->token ) {
			$this->writeElement( 'pan',            $order->payment->account_number  );
		}

		if ( isset( $order->payment->exp_year ) && $order->payment->exp_year &&  isset( $order->payment->exp_month ) && $order->payment->exp_month ) {
			$this->writeElement( 'expdate',        substr( $order->payment->exp_year, -2 ) . $order->payment->exp_month ); // YYMM
		}

		$this->writeElement( 'crypt_type',         self::CRYPT_TYPE_SSL_ENABLED_MERCHANT );
		$this->writeElement( 'dynamic_descriptor', $order->dynamic_descriptor );

		// include avs fields?
		if ( $order->perform_avs ) {
			$this->add_avs_elements( $order );
		}

		// include csc fields?
		if ( isset( $order->payment->csc ) ) {
			$this->add_csc_elements( $order );
		}

		$this->add_cust_info_elements( $order );

		// </preauth|purchase|us_preauth|us_purchase|res_preauth_cc|us_res_preauth_cc|res_purchase_cc|us_res_purchase_cc>
		$this->endElement();

		$this->close_document();
	}


	/**
	 * Returns the method for this request. Moneris uses the API default
	 * (POST)
	 *
	 * @since 2.3.0
	 * @return null
	 */
	public function get_method() { }


	/**
	 * Returns the request path for this request. Moneris request paths
	 * do not vary per request.
	 *
	 * @since 2.3.0
	 * @return string
	 */
	public function get_path() {
		return '';
	}


	/**
	 * Returns the order associated with this request, if there was one
	 *
	 * @since 2.0
	 * @return WC_Order the order object
	 */
	public function get_order() {
		return $this->order;
	}


	/**
	 * Gets the type of this request
	 *
	 * @since 2.0
	 * @return string the type of this request, one of 'preauth', 'purchase', or 'completion'
	 */
	public function get_type() {
		return $this->request_type;
	}


}
