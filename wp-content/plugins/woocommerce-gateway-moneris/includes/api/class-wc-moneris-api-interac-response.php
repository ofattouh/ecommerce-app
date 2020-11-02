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
 * Moneris API IPN Response Class
 *
 * @since 2.0
 * @see SV_WC_Payment_Gateway_API_Payment_Notification_Response
 */
class WC_Moneris_API_Interac_Response implements SV_WC_Payment_Gateway_API_Payment_Notification_Response {


	/** @var array the response data */
	protected $response;

	/** @var string the shop API token */
	protected $api_token;


	/**
	 * Build a response object from the raw response request
	 *
	 * @since 2.0
	 * @param array $response the response request
	 * @param string $api_token the shop API token
	 */
	public function __construct( $response, $api_token ) {

		$this->response  = $response;
		$this->api_token = $api_token;
	}


	/**
	 * Returns the order id associated with this response
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Payment_Notification_Response::get_order_id()
	 * @return int the order id associated with this response, or null if it could not be determined
	 * @throws Exception if the order id signature can not be validated, or does not pass validation
	 */
	public function get_order_id() {

		// make sure we received some merch data
		if ( ! isset( $this->response['IDEBIT_MERCHDATA'] ) || ! $this->response['IDEBIT_MERCHDATA'] ) {
			throw new SV_WC_Payment_Gateway_Exception( __( 'Request-back data incomplete: order id signature could not be validated', 'woocommerce-gateway-moneris' ) );
		}

		$merch_data = array();

		parse_str( $this->response['IDEBIT_MERCHDATA'], $merch_data );

		// required fields
		if ( ! isset( $merch_data['order_id'] ) || ! $merch_data['order_id'] || ! isset( $merch_data['hash'] ) || ! $merch_data['hash'] ) {
			throw new SV_WC_Payment_Gateway_Exception( __( 'Request-back data incomplete: order id signature could not be validated', 'woocommerce-gateway-moneris' ) );
		}

		// verify the order id signature
		if ( sha1( $this->api_token . '.' . $merch_data['order_id'] ) !== $merch_data['hash'] ) {
			throw new SV_WC_Payment_Gateway_Exception( __( 'Request-back order id signature validation failed', 'woocommerce-gateway-moneris' ) );
		}

		// security hash passed, return the order id
		return $merch_data['order_id'];
	}


	/**
	 * Returns the order associated with this response
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Payment_Notification_Response::get_order()
	 * @return WC_Order the order associated with this response, or null if it could not be determined
	 * @throws Exception if the order id signature can not be validated, or does not pass validation
	 */
	public function get_order() {

		// security hash passed, return the order object
		return wc_get_order( $this->get_order_id() );
	}


	/**
	 * Checks if the transaction was successful
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Response::transaction_approved()
	 * @return bool true if approved, false otherwise
	 */
	public function transaction_approved() {

		return $this->is_funded() && SV_WC_Payment_Gateway_Helper::luhn_check( $this->get_account_number() ) && $this->valid_response_data();
	}


	/**
	 * Returns true if the transaction was held, false otherwise
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Response::transaction_held()
	 * @return bool true if held, false otherwise
	 */
	public function transaction_held() {
		// transaction will never be held for interac redirect-back response requests
		return false;
	}


	/**
	 * Returns true if the transaction was cancelled, false otherwise
	 *
	 * @since 2.1.0
	 * @see SV_WC_Payment_Gateway_API_Payment_Notification_Response::transaction_cancelled()
	 * @return bool true if cancelled, false otherwise
	 */
	public function transaction_cancelled() {
		// transaction will never be cancelled for interac redirect-back response requests
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

		if ( $this->get_track2() && false !== strpos( $this->get_track2(), '=' ) ) {
			list( $pan, $suffix ) = explode( '=', $this->get_track2() );

			if ( $suffix ) {
				return substr( $suffix, 4 );
			}
		}

		return null;
	}


	/**
	 * Gets the transaction status message.  This is intended for the merchant.
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Response::get_status_message()
	 * @return string status message
	 */
	public function get_status_message() {
		if ( $this->transaction_approved() ) {
			return __( 'Interac idebit transaction funded', 'woocommerce-gateway-moneris' );
		} elseif ( ! SV_WC_Payment_Gateway_Helper::luhn_check( $this->get_account_number() ) ) {
			return __( 'Interac idebit PAN number failed mod 10 check', 'woocommerce-gateway-moneris' );
		} elseif ( ! $this->valid_response_data() ) {
			return implode( '. ', $this->get_response_data_error_messages() );
		} elseif ( $this->is_not_funded() ) {
			return __( 'Interac idebit transaction not funded', 'woocommerce-gateway-moneris' );
		} else {
			return __( 'Interac idebit transaction response error', 'woocommerce-gateway-moneris' );
		}
	}


	/**
	 * Gets the response status code, or null if there is no status code
	 * associated with this transaction.
	 *
	 * @since 2.0
	 * @return string status code
	 */
	public function get_status_code() {
		// no status code for interac redirect-back response requests
		return null;
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
		return print_r( $this->response, true );
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

		$safe_response = $this->response;

		if ( isset( $safe_response['IDEBIT_TRACK2'] ) && $safe_response['IDEBIT_TRACK2'] ) {

			list( $pan, $suffix ) = explode( '=', $safe_response['IDEBIT_TRACK2'] );

			// mask the sensitive PAN
			$pan = substr( $pan, 0, 1 ) . str_repeat( '*', strlen( $pan ) - 5 ) . substr( $pan, -4 );

			$safe_response['IDEBIT_TRACK2'] = $pan . '=' . $suffix;
		}

		return print_r( $safe_response, true );
	}


	/**
	 * This value will be returned by the issuer. It includes the PAN, expiry
	 * date, and transaction ID. Characters allowed are ISO-8859-1, restricted
	 * to single-byte codes, hex 20 to 7E (consistent with US-ASCII and
	 * ISO-8859-1 Latin-1). As part of the validation process, a MOD 10 check
	 * will have to be performed on the PAN portion (i.e., all characters
	 * before the '=' sign) of the track2 value.
	 *
	 * @since 2.0
	 * @return string track2 data
	 */
	public function get_track2() {

		if ( isset( $this->response['IDEBIT_TRACK2'] ) ) {
			return $this->response['IDEBIT_TRACK2'];
		}

		return null;
	}


	/**
	 * Returns the payment type: 'credit-card', 'echeck', etc
	 *
	 * @since 2.0.3
	 * @see SV_WC_Payment_Gateway_API_Payment_Notification_Response::get_payment_type()
	 * @return string payment type or null if not available
	 */
	public function get_payment_type() {
		return 'bank_transfer';
	}


	/**
	 * Returns the PAN portion of the track2 data.  Not sure how useful this
	 * stuff really is, at least Moneris says that it can't be used to guess
	 * the card brand.
	 *
	 * @since 2.0
	 * @see SV_WC_Payment_Gateway_API_Payment_Notification_Response::get_account_number()
	 * @return string PAN portion of track2 data or null if not available
	 */
	public function get_account_number() {

		$pan = null;

		if ( $this->get_track2() ) {
			list( $pan ) = explode( '=', $this->get_track2() );
		}

		return $pan;
	}


	/**
	 * Returns the expiration month portion of the track2 data
	 *
	 * @since 2.0
	 * @return string expiration month portion of track2 data or null if not available
	 */
	public function get_exp_month() {

		$exp_month = null;

		if ( $this->get_track2() ) {
			list( $_, $suffix ) = explode( '=', $this->get_track2() );
			$exp_month = substr( $suffix, 0, 2 );
		}

		return $exp_month;
	}


	/**
	 * Returns the expiration year portion of the track2 data
	 *
	 * @since 2.0
	 * @return string expiration year portion of track2 data or null if not available
	 */
	public function get_exp_year() {

		$exp_year = null;

		if ( $this->get_track2() ) {
			list( $_, $suffix ) = explode( '=', $this->get_track2() );
			$exp_year = substr( $suffix, 2, 2 );
		}

		return $exp_year;
	}


	/**
	 * Confirmation number returned from the issuer to be displayed on the
	 * merchant's confirmation page and on the receipt.
	 *
	 * @since 2.0
	 * @return confirmation number
	 */
	public function get_issconf() {

		if ( isset( $this->response['IDEBIT_ISSCONF'] ) ) {
			return $this->response['IDEBIT_ISSCONF'];
		}

		return null;
	}


	/**
	 * Issuer Name to be displayed on the merchant's confirmation page and on
	 * the receipt.
	 *
	 * @since 2.0
	 * @return the issuer name
	 */
	public function get_issname() {

		if ( isset( $this->response['IDEBIT_ISSNAME'] ) ) {
			return $this->response['IDEBIT_ISSNAME'];
		}

		return null;
	}


	/** Validation methods ******************************************************/


	/**
	 * Returns true if the response is funded
	 *
	 * @since 2.0
	 * @return boolean true if the response is funded
	 */
	public function is_funded() {
		return $this->get_track2() && $this->get_issconf() && $this->get_issname();
	}


	/**
	 * Returns true if the response is not funded
	 *
	 * @since 2.0
	 * @return boolean true if the response is not funded
	 */
	public function is_not_funded() {
		// not funded means: no track2, no issconf, no issname
		return ! $this->get_track2() && ! $this->get_issconf() && ! $this->get_issname();
	}


	/**
	 * Returns true if all response data is valid
	 *
	 * @since 2.0
	 * @return boolean true if all response data is valid
	 */
	private function valid_response_data() {

		foreach ( $this->get_invalid_response_data() as $errors ) {
			if ( count( $errors ) > 0 ) {
				return false;
			}
		}
		return true;
	}


	/**
	 * Returns any invalid response value names
	 *
	 * @since 2.0
	 * @return array associative array containing the names of any invalid
	 *         response values with an array of associated error messages
	 */
	private function get_invalid_response_data() {

		$invalid_response_data = array();

		foreach ( $this->get_data_validation_rules() as $name => $rule ) {

			$invalid_response_data[ $name ] = array();

			if ( ! isset( $this->response[ $name ] ) ) {
				// looks a little funky, but only mark as invalid if not one of
				// the funded variables, or not not funded (meaning one or two
				// but not all three of the funded variables are missing)
				if ( ! in_array( $name, array( 'IDEBIT_TRACK2', 'IDEBIT_ISSCONF', 'IDEBIT_ISSNAME' ) ) || ! $this->is_not_funded() ) {
					$invalid_response_data[ $name ][] = 'missing';
				}
			} else {
				$value = $this->response[ $name ];

				// value min/max string length
				if ( strlen( $value ) < $rule['min'] || strlen( $value ) > $rule['max'] ) {
					$invalid_response_data[ $name ][] = sprintf( 'bad length (min: %s, max: %s)', $rule['min'], $rule['max'] );
				}

				// allowed values
				if ( isset( $rule['values'] ) && ! in_array( $value, $rule['values'] ) ) {
					$invalid_response_data[ $name ][] = sprintf( 'bad value (%s allowed)', implode( ', ', $rule['values'] ) );
				}

				// allowed characters?
				if ( isset( $rule['regex'] ) && ! preg_match( $rule['regex'], $value ) ) {
					$invalid_response_data[ $name ][] = 'invalid character(s)';
				} elseif ( isset( $rule['char_range'] ) ) {
					for ( $i = 0, $ix = strlen( $value ); $i < $ix && ! isset( $invalid_response_data[ $name ] ); $i++ ) {
						if ( ord( $value{ $i } ) < $rule['char_range'][0] || ord( $value{ $i } ) > $rule['char_range'][1] ) {
							$invalid_response_data[ $name ][] = 'invalid character(s)';
						}
					}
				}
			}
		}

		return $invalid_response_data;
	}


	/**
	 * Get the data validation rules, keyed off of response variable name
	 *
	 * @since 2.0
	 * @return array associative array of response data field name to validation rules
	 */
	private function get_data_validation_rules() {

		// this is my best attempt, based on the IOP guide
		$allowed_accented_chars = array( 192, 193, 194, 196, 200, 201, 202, 203, 206, 207, 212, 217, 219, 220, 199, 224, 225, 226, 228, 232, 233, 234, 235, 238, 239, 244, 249, 251, 252, 255, 231 );
		$accents = '';
		foreach ( $allowed_accented_chars as $code ) {
			$accents .= chr( $code );
		}

		$disp = "/^[A-Za-z0-9" . $accents . " #$.,\-\/=?@']+$/";
		$ans  = array( 32, 126 );

		$rules = array(
			'IDEBIT_INVOICE'   => array( 'min' => 1,  'max' => 20,   'regex' => $disp ),
			'IDEBIT_MERCHDATA' => array( 'min' => 0,  'max' => 1024, 'char_range' => $ans ),
			'IDEBIT_VERSION'   => array( 'min' => 1,  'max' => 3,    'values' => array( 1 ) ),
			//'IDEBIT_ISSLANG'   => array( 'min' => 2,  'max' => 2,    'values' => array( 'en', 'fr' ) ),
		);

		if ( $this->is_funded() ) {
			$rules = array_merge( $rules, array(
				'IDEBIT_TRACK2'    => array( 'min' => 37, 'max' => 37,   'char_range' => $ans ),
				'IDEBIT_ISSCONF'   => array( 'min' => 1,  'max' => 15,   'regex' => $disp ),
				'IDEBIT_ISSNAME'   => array( 'min' => 1,  'max' => 30,   'regex' => $disp ),
			) );
		}

		return $rules;
	}


	/**
	 * Returns an array of response data validation error messages, if any
	 *
	 * @since 2.0
	 * @return array of response data validate error message strings, if any
	 */
	private function get_response_data_error_messages() {
		$messages = array();

		foreach ( $this->get_invalid_response_data() as $name => $errors ) {
			if ( count( $errors ) > 0 ) {
				$messages[] = sprintf( __( 'Invalid %1$s data: %2$s', 'woocommerce-gateway-moneris' ), $name, implode( ', ', $errors ) );
			}
		}

		return $messages;
	}


	/**
	 * Determine if this is an IPN response.
	 *
	 * @since 2.5.1
	 * @return bool
	 */
	public function is_ipn() {

		return false;
	}


}
