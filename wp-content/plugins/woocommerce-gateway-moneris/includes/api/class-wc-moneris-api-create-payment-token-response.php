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
 * Moneris API Create Payment Token Response
 *
 * Note: the (string) casts here are critical, without these you'll tend to get untraceable
 * errors like "Serialization of 'SimpleXMLElement' is not allowed"
 *
 * @since 2.0
 * @see SV_WC_Payment_Gateway_API_Create_Payment_Token_Response
 */
class WC_Moneris_API_Create_Payment_Token_Response extends WC_Moneris_API_Response implements SV_WC_Payment_Gateway_API_Create_Payment_Token_Response {


	/**
	 * Returns the payment token
	 *
	 * @since 2.0
	 * @return SV_WC_Payment_Gateway_Payment_Token payment token
	 */
	public function get_payment_token() {

		$order = $this->get_request()->get_order();

		$token = array(
			'default'        => true,
			'type'           => 'credit_card',
			'account_number' => $this->get_masked_pan(),
			'last_four'      => substr( $this->get_masked_pan(), -4 ),
			'card_type'      => $order->payment->card_type,
			'exp_month'      => $order->payment->exp_month,
			'exp_year'       => $order->payment->exp_year,
		);

		return new SV_WC_Payment_Gateway_Payment_Token( (string) $this->response_xml->receipt->DataKey, $token );
	}


	/**
	 * Checks if the transaction was successful
	 *
	 * @since 2.0
	 * @see WC_Moneris_API_Response::transaction_approved()
	 * @return bool true if approved, false otherwise
	 */
	public function transaction_approved() {

		$approved = parent::transaction_approved();

		return $approved && 'true' === (string) $this->response_xml->receipt->ResSuccess;
	}


} // end WC_Moneris_API_Create_Payment_Token_Response class
