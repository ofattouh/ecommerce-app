<?php
/**
 * Order details table shown in emails.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/email-order-details.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates/Emails
 * @version     3.0.0
 * Changed by:  Omar M.
 * Added:       Student registeration information section and the unit price column for the header
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$text_align = is_rtl() ? 'right' : 'left';

do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email ); ?>

<?php if ( ! $sent_to_admin ) : ?>
	<h2><?php printf( __( 'Order #%s', 'woocommerce' ), $order->get_order_number() ); ?></h2>
<?php else : ?>
	<h2><a class="link" href="<?php echo esc_url( admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) ); ?>"><?php printf( __( 'Order #%s', 'woocommerce' ), $order->get_order_number() ); ?></a> (<?php printf( '<time datetime="%s">%s</time>', $order->get_date_created()->format( 'c' ), wc_format_datetime( $order->get_date_created() ) ); ?>)</h2>
<?php endif; ?>

<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
	<thead>
		<tr>
			<th class="td" scope="col" style="text-align:<?php echo $text_align; ?>;"><?php _e( 'Product', 'woocommerce' ); ?></th>
			<th class="td" scope="col" style="text-align:<?php echo $text_align; ?>;"><?php _e( 'Quantity', 'woocommerce' ); ?></th>
			<th class="td" scope="col" style="text-align:<?php echo $text_align; ?>;"><?php _e( 'Unit Price', 'woocommerce' ); ?></th>
			<th class="td" scope="col" style="text-align:<?php echo $text_align; ?>;"><?php _e( 'Price', 'woocommerce' ); ?></th>
			
		</tr>
	</thead>
	<tbody>
	
    <?php
		
    	// Add the student section info for both emails: the customer copy and the admin copy
    	if ( ! has_action( 'woocommerce_order_item_meta_start' ) ) :
			add_action( 'woocommerce_order_item_meta_start', 'get_student_info', 10, 4);
		endif;
		
		if ( ! function_exists( 'get_student_info' ) ) {
			function get_student_info( $item_id, $item, $order, $plain_text=false ) {
				global $wc_chained_products;
				
				$my_product   = $order->get_product_from_item( $item );
				$student_info = get_post_meta( $order->get_order_number(), '_student_information', true );
				
				if ( isset($student_info) && is_array($student_info) ){
					foreach ( $student_info as $student ){
						$term_Values_Arr = array();
							
						if ( get_the_terms( $student['course_id'], 'pa_discount' ) && ! is_wp_error(get_the_terms( $student['course_id'], 'pa_discount' )) ) :
							foreach(get_the_terms( $student['course_id'], 'pa_discount' ) as $oneterm){
								$term_Values_Arr[] = $oneterm->name;
							}
						endif;
					
						// Start date
						if ( get_the_terms( $student['course_id'], 'pa_start-date' ) && ! is_wp_error(get_the_terms( $student['course_id'], 'pa_start-date' )) ) :
							foreach(get_the_terms( $student['course_id'], 'pa_start-date' ) as $oneterm){
								if ( $my_product->get_id() == $student['course_id'] ){
									echo '<br><br>Start Date: '.date('M d, Y', strtotime($oneterm->name));
								}
							}
						endif;
						
						if( in_array('promotion', $term_Values_Arr ) && $wc_chained_products->has_chained_products( $student['course_id'] ) ){
						}
						else{	
							if ( $my_product->get_id() == $student['course_id'] ){
								echo '<br><br>'.$student['first_name'].' '.$student['last_name'].'<br>'.$student['email'];
							}
						}
					}
				}
			}
		}
		
		echo wc_get_email_order_items( $order, array(
			'show_sku'      => $sent_to_admin,
			'show_image'    => false,
			'image_size'    => array( 32, 32 ),
			'plain_text'    => $plain_text,
			'sent_to_admin' => $sent_to_admin,
		) );
		
	?>
		
	</tbody>
	<tfoot>
		<?php
			if ( $totals = $order->get_order_item_totals() ) {
				$i = 0;
				foreach ( $totals as $total ) {
					$i++;
					?><tr>
						<th class="td" scope="row" colspan="2" style="text-align:<?php echo $text_align; ?>; <?php echo ( 1 === $i ) ? 'border-top-width: 4px;' : ''; ?>"><?php echo $total['label']; ?></th>
						<td class="td" style="text-align:<?php echo $text_align; ?>; <?php echo ( 1 === $i ) ? 'border-top-width: 4px;' : ''; ?>"><?php echo $total['value']; ?></td>
					</tr><?php
				}
			}
		?>
	</tfoot>
</table>

<?php do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email ); ?>
