<?php
/**
 * Single Product Sale Flash
 *
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     1.6.4
 * Override by Omar: to display the product title on a seperate line after the sales discount image only for grouped products so they won't overlap
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $post, $product;

?>
<?php if ( $product->is_on_sale() ) : ?>
	<?php 
		if ($product->product_type == "grouped") : 
			echo "<br><br>";
		endif;
	?>
	<?php echo apply_filters( 'woocommerce_sale_flash', '<span class="onsale">' . __( 'Sale!', 'woocommerce' ) . '</span>', $post, $product ); ?>

<?php endif; ?>
