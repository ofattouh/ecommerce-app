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
 * Moneris API Base Response Class
 *
 * Note: the (string) casts here are critical, without these you'll tend to get untraceable
 * errors like "Serialization of 'SimpleXMLElement' is not allowed"
 *
 * @since 2.0
 * @see SV_WC_Payment_Gateway_API_Response
 */
class WC_Moneris_API_Response implements SV_WC_Payment_Gateway_API_Response, SV_WC_Payment_Gateway_API_Authorization_Response {


	/** @var WC_Moneris_API_Request the request that resulted in this response */
	protected $request;

	/** @var string string representation of this response */
	private $raw_response_xml;

	/** @var SimpleXMLElement response XML object */
	protected $response_xml;

	/** @var boolean used to mark this response as failed */
	private $failed = null;

	/** @var boolean used to mark this response as held */
	private $held = false;

	/** @var string overrides the response message */
	private $status_message = null;


	/**
	 * Build a response object from the raw response xml
	 *
	 * @since 2.0
	 * @param WC_Moneris_API_Request $request the request that resulted in this response
	 * @param string $raw_response_xml the raw response XML
	 */
	public function __construct( $request, $raw_response_xml ) {

		$this->request = $request;

		$this->raw_response_xml = $raw_response_xml;

		// LIBXML_NOCDATA ensures that any XML fields wrapped in [CDATA] will be included as text nodes
		$this->response_xml     = new SimpleXMLElement( $raw_response_xml, LIBXML_NOCDATA );
	}


	/**
	 * Checks if the transaction was successful
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Response::transaction_approved()
	 * @return bool true if approved, false otherwise
	 */
	public function transaction_approved() {

		// transaction success overridden?
		if ( true === $this->failed ) {
			return false;
		}

		return is_numeric( $this->get_status_code() ) && $this->get_status_code() < 50;
	}


	/**
	 * Returns true if the transaction was held, for instance due to AVS/CSC
	 * Fraud Settings.  This indicates that the transaction was successful, but
	 * did not pass a fraud check and should be reviewed.
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Response::transaction_held()
	 * @return bool true if approved, false otherwise
	 */
	public function transaction_held() {

		return $this->held;
	}


	/**
	 * Returns true if the transaction was cancelled, false otherwise
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Payment_Notification_Response::transaction_cancelled()
	 * @return bool true if cancelled, false otherwise
	 */
	public function transaction_cancelled() {
		// when completing an Interac transfer, this response object can stand in place of the Payment Notification Response
		return false;
	}


	/**
	 * Gets the response transaction id, or null if there is no transaction id
	 * associated with this transaction.
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Response::get_transaction_id()
	 * @return string transaction id
	 */
	public function get_transaction_id() {
		$transaction_id = (string) $this->response_xml->receipt->TransID;
		return 'null' === $transaction_id ? null : $transaction_id;
	}


	/**
	 * Gets the response reference number
	 *
	 * @since 2.1.2
	 * @return string transaction reference number
	 */
	public function get_reference_num() {
		$reference_num = (string) $this->response_xml->receipt->ReferenceNum;
		return 'null' === $reference_num ? null : $reference_num;
	}


	/**
	 * Gets the transaction status message.  This is intended for the merchant.
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Response::get_status_message()
	 * @return string status message
	 */
	public function get_status_message() {

		// message overridden?
		if ( ! is_null( $this->status_message ) ) {
			return $this->status_message;
		}

		$message = $this->get_message();

		// special handling for missing status code
		if ( ! is_numeric( $this->get_status_code() ) ) {

			if ( false !== strpos( $message, "No permission for:'cvd_info'" ) ) {
				$message .= __( '  Please enable CVD handling in your Moneris merchant profile, or contact your Moneris account rep to enable CVD handling for your account.', 'woocommerce-gateway-moneris' );
			}

			if ( $message ) {
				return sprintf( __( 'Transaction incomplete: %s', 'woocommerce-gateway-moneris' ), $message );
			} else {
				return __( 'Transaction incomplete', 'woocommerce-gateway-moneris' );
			}
		}

		return $message;
	}


	/**
	 * Gets the response status code, where < 50 indicates success and >= 50 failure
	 *
	 * The response code can be returned as null for a variety of reasons. A
	 * majority of the time the explanation is contained within the Message
	 * field. When a 'null' response is returned it can indicate that the
	 * Issuer, the credit card host, or the gateway is unavailable, either
	 * because they are offline or you are unable to connect to the internet.
	 * A 'null' can also be returned when a transaction message is improperly
	 * formatted.
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Response::get_status_message()
	 * @return string status code or null
	 */
	public function get_status_code() {
		$status_code = (string) $this->response_xml->receipt->ResponseCode;
		return 'null' === $status_code ? null : $status_code;
	}


	/**
	 * Returns a message appropriate for a frontend user.  This should be used
	 * to provide enough information to a user to allow them to resolve an
	 * issue on their own, but not enough to help nefarious folks fishing for
	 * info.
	 *
	 * @since 2.0.3
	 * @see SV_WC_Payment_Gateway_API_Response_Message_Helper
	 * @see SV_WC_Payment_Gateway_API_Response::get_user_message()
	 * @return string user message, if there is one
	 */
	public function get_user_message() {
		return null;
	}


	/**
	 * Returns the string representation of this response
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Response::to_string()
	 * @return string response
	 */
	public function to_string() {

		$string = $this->raw_response_xml;

		$dom = new DOMDocument();

		// suppress errors for invalid XML syntax issues
		if ( @$dom->loadXML( $string ) ) {
			$dom->formatOutput = true;
			$string = $dom->saveXML();
		}

		return $string;
	}


	/**
	 * Returns the string representation of this response with any and all
	 * sensitive elements masked or removed
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Response::to_string_safe()
	 * @return string response safe for logging/displaying
	 */
	public function to_string_safe() {

		// no sensitive data to mask
		return $this->to_string();
	}


	/**
	 * The authorization code is returned from the credit card processor to
	 * indicate that the charge will be paid by the card issuer.
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Authorization_Response::get_authorization_code()
	 * @return string credit card authorization code
	 */
	public function get_authorization_code() {
		return (string) $this->response_xml->receipt->AuthCode;
	}


	/**
	 * Returns the result of the AVS check:
	 *
	 * + `Z` - zip match, locale no match
	 * + `A` - zip no match, locale match
	 * + `N` - zip no match, locale no match
	 * + `Y` - zip match, locale match
	 * + `U` - zip and locale could not be verified
	 * + null - unsupported card, or unknown response
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Authorization_Response::get_avs_result()
	 * @return string result of the AVS check, if any
	 */
	public function get_avs_result() {

		$card_type = $this->request->get_order()->payment->card_type;
		$code      = $this->get_avs_result_code();

		return self::get_avs_code_mapping( $card_type, $code );
	}


	/**
	 * Indicates the address verification result
	 *
	 * @since 2.0
	 * @return string result of the AVS check, or empty
	 */
	public function get_avs_result_code() {
		return trim( (string) $this->response_xml->receipt->AvsResultCode );
	}


	/**
	 * Returns the result of the CSC check.  One of our standardized codes:
	 *
	 * + `M` - CSC match
	 * + `N` - CSC no match
	 * + `U` - CSC could not be verified, or unknown result code
	 * + null - unsupported card
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Authorization_Response::get_csc_result()
	 * @return string result of CSC check, or null for unsupported card type
	 */
	public function get_csc_result() {

		$card_type   = $this->request->get_order()->payment->card_type;
		$result_code = $this->get_csc_result_code();

		// pluck off the alphabetical response code, if there is one
		if ( is_null( $result_code ) ) {
			$response_code = null;
		} else {
			$response_code = trim( $result_code{1} );
		}

		return self::get_csc_code_mapping( $card_type, $response_code );
	}


	/**
	 * Indicates the raw CSC result
	 *
	 * @since 2.0
	 * @return string result of the CSC check, or empty
	 */
	public function get_csc_result_code() {
		$result = (string) $this->response_xml->receipt->CvdResultCode;

		if ( 'null' === $result ) {
			$result = null;
		}

		return $result;
	}


	/**
	 * Returns true if the CSC check was successful
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Authorization_Response::csc_match()
	 * @return boolean true if the CSC check was successful
	 */
	public function csc_match() {
		return 'M' == $this->get_csc_result();
	}


	/**
	 * A normal decline result, this is most likely due to information provided
	 * by and fixable by the customer.
	 *
	 * @since 2.0
	 * @return boolean true if the error was likely caused by the customer
	 */
	public function is_customer_error() {
		return ! $this->transaction_approved() && ! $this->transaction_held() && $this->is_complete();
	}


	/**
	 * Returns true if transaction was sent to authorization host and a response was received
	 *
	 * @since 2.0
	 * @return boolean true if transaction was sent to authorization host and a response was received
	 */
	public function is_complete() {
		return 'true' == (string) $this->response_xml->receipt->Complete;
	}


	/**
	 * Returns true if transaction failed due to a process timing out
	 *
	 * @since 2.0
	 * @return boolean true if transaction failed due to a process timing out
	 */
	public function is_timed_out() {

		if ( 'null' == (string) $this->response_xml->receipt->TimedOut ) {
			return null;
		}

		return 'true' == (string) $this->response_xml->receipt->TimedOut;
	}


	/**
	 * Determines if the the transaction authorization was invalid.
	 *
	 * @since 2.6.3
	 * @return bool
	 */
	public function is_authorization_invalid() {

		return 'Pre/Re-Auth Completion Amount Over Limit' === $this->get_message();
	}


	/**
	 * Gets the receipt id (order number)
	 *
	 * @since 2.0
	 * @return string receipt id
	 */
	public function get_receipt_id() {
		return (string) $this->response_xml->receipt->ReceiptId;
	}


	/**
	 * Gets the response description returned from issuing institution
	 *
	 * @since 2.0
	 * @return string response description returned from issuing institution
	 */
	public function get_message() {
		return (string) $this->response_xml->receipt->Message;
	}


	/**
	 * Gets the card type, one of 'visa', 'mastercard', 'amex', 'discover',
	 * 'dinersclub', or 'jcb'.
	 *
	 * @since 2.0
	 * @return string normalized card type, or null if it can't be determined
	 */
	public function get_card_type() {

		switch ( (string) $this->response_xml->receipt->CardType ) {

			case 'V':  return SV_WC_Payment_Gateway_Helper::CARD_TYPE_VISA;
			case 'M':  return SV_WC_Payment_Gateway_Helper::CARD_TYPE_MASTERCARD;
			case 'AX': return SV_WC_Payment_Gateway_Helper::CARD_TYPE_AMEX;
			case 'C1': return SV_WC_Payment_Gateway_Helper::CARD_TYPE_JCB;
			case 'DC': return SV_WC_Payment_Gateway_Helper::CARD_TYPE_DINERSCLUB;
			case 'DI': return SV_WC_Payment_Gateway_Helper::CARD_TYPE_DISCOVER;  // this one is a guess, can't find a test discover number that returns a CardType other than 'NO'

			default: return null;
		}
	}


	/**
	 * Get the masked pan number from a tokenized request response
	 *
	 * @since 2.0
	 * @return string masked credit card account number if this was the response
	 *         of a tokenized request, otherwise null
	 */
	public function get_masked_pan() {
		if ( isset( $this->response_xml->receipt->ResolveData->masked_pan ) ) {
			return (string) $this->response_xml->receipt->ResolveData->masked_pan;
		}

		return null;
	}


	/**
	 * Marks this response as failed
	 *
	 * @since 2.0
	 */
	public function failed() {
		$this->failed = true;
	}


	/**
	 * Marks this response as held
	 *
	 * @since 2.0
	 */
	public function held() {
		$this->held = true;
	}


	/**
	 * Overrides the response message returned by Moneris
	 *
	 * @since 2.0
	 * @param string $status_message the new response message
	 */
	public function set_status_message( $status_message ) {
		$this->status_message = $status_message;
	}


	/**
	 * Returns the request object that resulted in this response
	 *
	 * @since 2.0
	 * @return WC_Moneris_API_Request request object
	 */
	public function get_request() {
		return $this->request;
	}


	/**
	 * Translates the card-specific issuer AVS code to our simplified, unified
	 * scheme
	 *
	 * + `Z` - zip match, locale no match
	 * + `A` - zip no match, locale match
	 * + `N` - zip no match, locale no match
	 * + `Y` - zip match, locale match
	 * + `U` - zip and locale could not be verified
	 * + null - unsupported card, or unknown result code
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Authorization_Response::get_avs_result()
	 * @return string result of the AVS check, if any
	 */
	public static function get_avs_code_mapping( $card_type, $avs_code ) {

		// shared mapping for visa/discover/jcb
		$visa_disc_jcb = array(
			'D' => 'Y',
			'F' => 'Y',
			'M' => 'Y',
			'Y' => 'Y',
			'N' => 'N',
			'P' => 'Z',
			'Z' => 'Z',
			'B' => 'A',
			'A' => 'A',
			'C' => 'U',
			'G' => 'U',
			'I' => 'U',
			'R' => 'U',
			'U' => 'U',

		);

		$lookup = array(
			SV_WC_Payment_Gateway_Helper::CARD_TYPE_VISA       => $visa_disc_jcb,
			SV_WC_Payment_Gateway_Helper::CARD_TYPE_DISCOVER   => $visa_disc_jcb,
			SV_WC_Payment_Gateway_Helper::CARD_TYPE_JCB        => $visa_disc_jcb,
			SV_WC_Payment_Gateway_Helper::CARD_TYPE_MASTERCARD => array(
				'X' => 'Y',
				'Y' => 'Y',
				'N' => 'N',
				'W' => 'Z',
				'Z' => 'Z',
				'A' => 'A',
				'R' => 'U',
				'S' => 'U',
				'U' => 'U',
			),
			SV_WC_Payment_Gateway_Helper::CARD_TYPE_AMEX => array(
				'E' => 'Y',
				'M' => 'Y',
				'Y' => 'Y',
				'W' => 'N',
				'N' => 'N',
				'D' => 'Z',
				'L' => 'Z',
				'Z' => 'Z',
				'F' => 'A',
				'O' => 'A',
				'A' => 'A',
				'R' => 'U',
				'S' => 'U',
				'U' => 'U',
			),
		);

		if ( ! isset( $lookup[ $card_type ][ $avs_code ] ) ) {
			return null;
		}

		return $lookup[ $card_type ][ $avs_code ];
	}


	/**
	 * Translates the card-specific issuer CSC code to our simplified, unified
	 * scheme
	 *
	 * + `M` - CSC match
	 * + `N` - CSC no match
	 * + `U` - CSC could not be verified, or unknown result code
	 * + null - unsupported card
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Authorization_Response::get_cvd_result()
	 * @return string result of the CSC check, or null for unsupported card
	 */
	public static function get_csc_code_mapping( $card_type, $csc_code ) {

		// shared mapping for visa/mc/discover/jcb
		$all = array(
			'M' => 'M',
			'N' => 'N',
			'P' => 'U',
			'U' => 'U',
		);

		$lookup = array(
			SV_WC_Payment_Gateway_Helper::CARD_TYPE_VISA       => $all,
			SV_WC_Payment_Gateway_Helper::CARD_TYPE_DISCOVER   => $all,
			SV_WC_Payment_Gateway_Helper::CARD_TYPE_JCB        => $all,
			SV_WC_Payment_Gateway_Helper::CARD_TYPE_MASTERCARD => $all,
			SV_WC_Payment_Gateway_Helper::CARD_TYPE_AMEX       => $all + array(
				'Y' => 'M',
				'R' => 'U',
			),
		);

		// unsupported card type
		if ( ! isset( $lookup[ $card_type ] ) ) {
			return null;
		}

		// map invalid code
		if ( ! isset( $lookup[ $card_type ][ $csc_code ] ) ) {
			return 'U';
		}

		return $lookup[ $card_type ][ $csc_code ];
	}


	/**
	 * Get account number
	 *
	 * Match API Payment Notification Response signature for Interac transactions
	 *
	 * @since 2.0.3
	 */
	public function get_account_number() {
		return null;
	}


	/**
	 * Get payment type
	 *
	 * Match API Payment Notification Response signature for Interac transactions
	 *
	 * @since 2.0.3
	 */
	public function get_payment_type() {
		return 'bank_transfer';
	}


} // end WC_Moneris_API_Response class
