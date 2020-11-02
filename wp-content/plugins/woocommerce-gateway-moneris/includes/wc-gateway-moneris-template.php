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
 * needs please refer to http://docs.woocommerce.com/document/moneris/
 *
 * @package   WC-Gateway-Moneris/Templates
 * @author    SkyVerge
 * @copyright Copyright (c) 2012-2017, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

/**
 * Template Function Overrides
 *
 * @version 2.0
 * @since 2.0
 */

defined( 'ABSPATH' ) or exit;


if ( ! function_exists( 'woocommerce_moneris_payment_fields' ) ) {

	/**
	 * Pluggable function to render the checkout page payment fields form
	 *
	 * @since 2.0
	 * @param WC_Gateway_Moneris_Credit_Card $gateway gateway object
	 */
	function woocommerce_moneris_payment_fields( $gateway ) {

		// safely display the description, if there is one
		if ( $gateway->get_description() ) {
			echo '<p>' . wp_kses_post( $gateway->get_description() ) . '</p>';
		}

		$payment_method_defaults = array(
			'account-number' => '',
			'exp-month'      => '',
			'exp-year'       => '',
			'csc'            => '',
		);

		// for the demo environment, display a notice and supply a default test payment method
		if ( $gateway->is_test_environment() ) {
			echo '<p>' . __( 'TEST MODE ENABLED', 'woocommerce-gateway-moneris' ) . '</p>';

			$payment_method_defaults = array(
				'account-number' => '4502285070000007',
				'exp-month'      => '1',
				'exp-year'       => date( 'Y' ) + 1,
				'csc'            => '123',
			);

		}

		// tokenization is allowed if tokenization is enabled on the gateway
		$tokenization_allowed = $gateway->tokenization_enabled();

		// on the pay page there is no way of creating an account, so disallow tokenization for guest customers
		if ( $tokenization_allowed && is_checkout_pay_page() && ! is_user_logged_in() ) {
			$tokenization_allowed = false;
		}

		$tokens = array();
		$default_new_card = true;
		if ( $tokenization_allowed && is_user_logged_in() ) {
			$tokens = $gateway->get_payment_tokens_handler()->get_tokens( get_current_user_id() );

			foreach ( $tokens as $token ) {
				if ( $token->is_default() ) {
					$default_new_card = false;
					break;
				}
			}
		}

		// load the payment fields template file
		wc_get_template(
			'checkout/moneris-payment-fields.php',
			array(
				'payment_method_defaults' => $payment_method_defaults,
				'enable_csc'              => $gateway->csc_enabled(),
				'tokens'                  => $tokens,
				'tokenization_allowed'    => $tokenization_allowed,
				'tokenization_forced'     => $gateway->get_payment_tokens_handler()->tokenization_forced(),
				'default_new_card'        => $default_new_card,
			),
			'',
			$gateway->get_plugin()->get_plugin_path() . '/templates/'
		);

	}
}


if ( ! function_exists( 'woocommerce_moneris_hosted_tokenization_payment_fields' ) ) {

	/**
	 * Pluggable function to render the checkout page form with iframe hosted
	 * tokenization credit card account number field
	 *
	 * @since 2.0
	 * @param WC_Gateway_Moneris_Credit_Card $gateway gateway object
	 */
	function woocommerce_moneris_hosted_tokenization_payment_fields( $gateway ) {

		// safely display the description, if there is one
		if ( $gateway->get_description() ) {
			echo '<p>' . wp_kses_post( $gateway->get_description() ) . '</p>';
		}

		$payment_method_defaults = array(
			'exp-month'      => '',
			'exp-year'       => '',
			'csc'            => '',
		);

		// for the demo environment, display a notice and supply a default test payment method
		if ( $gateway->is_test_environment() ) {
			echo '<p>' . __( 'TEST MODE ENABLED', 'woocommerce-gateway-moneris' ) . '</p>';
			echo '<p>' . sprintf( __( 'Sample Test Credit Card Number: %s', 'woocommerce-gateway-moneris' ), '4502285070000007' ) . '</p>';

			$payment_method_defaults = array(
				'exp-month'      => '1',
				'exp-year'       => date( 'Y' ) + 1,
				'csc'            => '123',
			);
		}

		// tokenization is allowed if tokenization is enabled on the gateway
		$tokenization_allowed = $gateway->tokenization_enabled();

		// on the pay page there is no way of creating an account, so disallow tokenization for guest customers
		if ( $tokenization_allowed && is_checkout_pay_page() && ! is_user_logged_in() ) {
			$tokenization_allowed = false;
		}

		$tokens = array();
		$default_new_card = true;
		if ( $tokenization_allowed && is_user_logged_in() ) {
			$tokens = $gateway->get_payment_tokens_handler()->get_tokens( get_current_user_id() );

			foreach ( $tokens as $token ) {
				if ( $token->is_default() ) {
					$default_new_card = false;
					break;
				}
			}
		}

		// load the payment fields template file
		wc_get_template(
			'checkout/moneris-hosted-tokenization-payment-fields.php',
			array(
				'iframe_src'              => $gateway->get_hosted_tokenization_iframe_url(),
				'enable_csc'              => $gateway->csc_enabled(),
				'payment_method_defaults' => $payment_method_defaults,
				'tokens'                  => $tokens,
				'tokenization_allowed'    => $tokenization_allowed,
				'tokenization_forced'     => $gateway->get_payment_tokens_handler()->tokenization_forced(),
				'default_new_card'        => $default_new_card,
			),
			'',
			$gateway->get_plugin()->get_plugin_path() . '/templates/'
		);
	}
}
