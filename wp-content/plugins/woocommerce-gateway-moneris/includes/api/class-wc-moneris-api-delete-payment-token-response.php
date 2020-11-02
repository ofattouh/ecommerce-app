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
 * Moneris API Delete Payment Token Response
 *
 * Note: the (string) casts here are critical, without these you'll tend to get untraceable
 * errors like "Serialization of 'SimpleXMLElement' is not allowed"
 *
 * @since 2.0
 */
class WC_Moneris_API_Delete_Payment_Token_Response extends WC_Moneris_API_Response {


	/**
	 * Checks if the transaction was successful
	 *
	 * @since 2.0
	 * @see WC_Moneris_API_Response::transaction_approved()
	 * @return bool true if approved, false otherwise
	 */
	public function transaction_approved() {

		$approved = parent::transaction_approved();

		// if token could not be found, that's good enough for us
		return $approved || 983 == $this->get_status_code();
	}


} // end WC_Moneris_API_Delete_Payment_Token_Response class
