<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class WCR_Admin
 *
 * Admin class
 *
 * @class       WCR_Admin
 * @version     1.0.0
 * @author      Grow Development
 */
class WCR_Admin extends WooCommerce_Checkout_Registration {


	/**
	 * __construct function.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

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

		// Add extra action 'Resend student information'
		//add_filter( 'woocommerce_order_actions', array( $this, 'wcr_order_actions' ) );

		// Fire action
		//add_action( 'woocommerce_order_action_resend_student_data', array( $this, 'wcr_action_resend_student_data' ) );

	}


	/**
	 * Order action.
	 *
	 * Add an extra Order action to resend student data.
	 *
	 * @since 1.0.0
	 */
	public function wcr_order_actions( $actions ) {

		$actions['resend_student_data'] = __( 'Resend student data to DB', 'woocommerce-checkout-registration' );
		return $actions;

	}


	/**
	 * Save checkout fields.
	 *
	 * Get the checkout fields from POST and save in post_meta table.
	 *
	 * @since 1.0.0
	 */
	public function wcr_action_resend_student_data( $order ) {

		WooCommerce_Checkout_Registration::wcr_save_student_info_remote_database( $order->id );

	}

}

global $wcr_admin;
$wcr_admin = new WCR_Admin();