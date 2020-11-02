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
 * The checkout page credit card form
 *
 * @param array $payment_method_defaults optional card defaults to pre-populate the form fields
 * @param boolean $enable_csc true if the Card Security Code (CVV) field should be rendered
 * @param array $tokens optional associative array of credit card token string to SV_WC_Payment_Gateway_Payment_Token object
 * @param boolean $tokenization_allowed true if tokenization is allowed (enabled in gateway), false otherwise
 * @param boolean $tokenization_forced true if tokenization is forced (new card must be tokenized, ie for subscriptions/pre-orders)
 *
 * @version 2.0
 * @since 2.0
 */

defined( 'ABSPATH' ) or exit;

?>
<style type="text/css">#payment ul.payment_methods li label[for='payment_method_moneris'] img:nth-child(n+2) { margin-left:1px; } .woocommerce #payment ul.payment_methods li .payment_method_moneris img, .woocommerce-page #payment ul.payment_methods li .payment_method_moneris img { margin-left:0; }</style>
<fieldset>
	<?php
	if ( $tokens ) : ?>
		<p class="form-row form-row-wide">
			<a class="button" style="float:right;" href="<?php echo get_permalink( wc_get_page_id( 'myaccount' ) ); ?>#wc-moneris-my-payment-methods"><?php echo wp_kses_post( apply_filters( 'wc_gateway_moneris_manage_my_payment_methods', __( "Manage My Cards", 'woocommerce-gateway-moneris' ) ) ); ?></a>
			<?php foreach( $tokens as $token ) : ?>
				<input type="radio" id="wc-moneris-payment-token-<?php echo esc_attr( $token->get_id() ); ?>" name="wc-moneris-payment-token" class="js-wc-moneris-payment-token js-wc-payment-gateway-payment-token" style="width:auto;" value="<?php echo esc_attr( $token->get_id() ); ?>" <?php checked( $token->is_default() ); ?>/>
				<label style="display:inline;" for="wc-moneris-payment-token-<?php echo esc_attr( $token->get_id() ); ?>"><?php printf( __( '%1$s ending in %2$s (expires %3$s)', 'woocommerce-gateway-moneris' ), $token->get_image_url() ? '<img width="32" height="20" title="' . esc_attr( $token->get_type_full() ) . '" src="' . esc_url( $token->get_image_url() ) . '" />' : esc_html( $token->get_type_full() ), esc_html( $token->get_last_four() ), esc_html( $token->get_exp_month() . '/' . $token->get_exp_year() ) ); ?></label><br />
			<?php endforeach; ?>
			<input type="radio" id="wc-moneris-use-new-payment-method" name="wc-moneris-payment-token" class="js-wc-moneris-payment-token" style="width:auto;" value="" <?php checked( $default_new_card ); ?> /> <label style="display:inline;" for="wc-moneris-use-new-payment-method"><?php esc_html_e( 'Use a new credit card', 'woocommerce-gateway-moneris' ); ?></label>
		</p>
		<div class="clear"></div>
	<?php endif; ?>
	<div class="wc-moneris-new-payment-method-form js-wc-moneris-new-payment-method-form" <?php echo ( $tokens ? 'style="display:none;"' : '' ); ?>>
		<p class="form-row form-row-first">
			<label for="wc-moneris-account-number"><?php esc_html_e( 'Credit Card Number', 'woocommerce-gateway-moneris' ); ?> <span class="required">*</span></label>
			<input type="text" class="input-text js-wc-payment-gateway-account-number" id="wc-moneris-account-number" name="wc-moneris-account-number" maxlength="19" autocomplete="off" value="<?php echo esc_attr( $payment_method_defaults['account-number'] ); ?>" />
		</p>

		<p class="form-row form-row-last">
			<label for="wc-moneris-exp-month"><?php esc_html_e( 'Expiration Date', 'woocommerce-gateway-moneris' ); ?> <span class="required">*</span></label>
			<select name="wc-moneris-exp-month" id="wc-moneris-exp-month" class="js-wc-payment-gateway-card-exp-month" style="width:auto;">
				<option value=""><?php esc_html_e( 'Month', 'woocommerce-gateway-moneris' ) ?></option>
				<?php foreach ( range( 1, 12 ) as $month ) : ?>
					<option value="<?php printf( '%02d', $month ) ?>" <?php selected( $payment_method_defaults['exp-month'], $month ); ?>><?php printf( '%02d', $month ) ?></option>
				<?php endforeach; ?>
			</select>
			<select name="wc-moneris-exp-year" id="wc-moneris-exp-year" class="js-wc-payment-gateway-card-exp-year" style="width:auto;">
				<option value=""><?php esc_html_e( 'Year', 'woocommerce-gateway-moneris' ) ?></option>
				<?php foreach ( range( date( 'Y' ), date( 'Y' ) + 10 ) as $year ) : ?>
					<option value="<?php echo $year ?>" <?php selected( $payment_method_defaults['exp-year'], $year ); ?>><?php echo $year ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<div class="clear"></div>

		<?php if ( $enable_csc ) : ?>
			<p class="form-row form-row-wide">
				<label for="wc-moneris-csc"><?php esc_html_e( 'Card Security Code', 'woocommerce-gateway-moneris' ) ?> <span class="required">*</span></label>
				<input type="text" class="input-text js-wc-moneris-csc js-wc-payment-gateway-csc" id="wc-moneris-csc" name="wc-moneris-csc" maxlength="4" style="width:60px" autocomplete="off" value="<?php echo esc_attr( $payment_method_defaults['csc'] ); ?>" />
			</p>
			<div class="clear js-wc-moneris-csc-clear"></div>
		<?php endif; ?>

		<?php
		if ( $tokenization_allowed || $tokenization_forced ) :
			if ( $tokenization_forced ) :
				?>
				<input name="wc-moneris-tokenize-payment-method" id="wc-moneris-tokenize-payment-method" type="hidden" value="true" />
				<?php
			else:
				?>
				<p class="form-row">
					<input name="wc-moneris-tokenize-payment-method" id="wc-moneris-tokenize-payment-method" class="js-wc-moneris-tokenize-payment-method" type="checkbox" value="true" style="width:auto;" />
					<label for="wc-moneris-tokenize-payment-method" style="display:inline;"><?php echo wp_kses_post( apply_filters( 'wc_gateway_moneris_tokenize_payment_method_text', __( "Securely Save Card to Account", 'woocommerce-gateway-moneris' ) ) ); ?></label>
				</p>
				<div class="clear"></div>
				<?php
			endif;
		endif;
		?>
	</div>
</fieldset>
