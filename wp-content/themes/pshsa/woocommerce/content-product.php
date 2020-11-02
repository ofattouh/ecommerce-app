<?php
/**
 * The template for displaying product content within loops
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you (the theme developer).
 * will need to copy the new files to your theme to maintain compatibility. We try to do this.
 * as little as possible, but it does happen. When this occurs the version of the template file will.
 * be bumped and the readme will list any important changes.
 *
 * @see     http://docs.woothemes.com/document/template-structure/
 * @author  WooThemes
 * @package WooCommerce/Templates
 * @version 3.0.0
 * Override by Omar: Add the product filter plugin query parameters for grouped products only
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $product;

// Ensure visibility
if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}

?>
<li <?php post_class(); ?>>

	<?php 
	/**
	 * woocommerce_before_shop_loop_item hook.
	 *
	 * @hooked woocommerce_template_loop_product_link_open - 10
	 */
	do_action( 'woocommerce_before_shop_loop_item' ); 
	?>
	
	<?php
		
		//Parse the url to get the product filter custom attribute parameters
		parse_str($_SERVER['QUERY_STRING'], $output);
		
		foreach($output as $k=>$v):
			if (preg_match("/^pa_/i", $k)):
				$arr_params [$k] = $v;
			endif;
		endforeach;
		
		$filter_permalink = ( sizeof($arr_params) > 0 ) ? esc_url(add_query_arg($arr_params, get_permalink())) : get_permalink();
		
	?>
	
	<?php if ($product->get_type() != "grouped"): ?>
		<a href="<?php the_permalink(); ?>">
	<?php else: ?>
		<a href="<?php echo $filter_permalink; ?>">
	<?php endif; ?>
	
	<?php
			
		//Changed by Omar
		/**
		 * woocommerce_before_shop_loop_item_title hook.
		 *
		 * @hooked woocommerce_show_product_loop_sale_flash - 10
		 * @hooked woocommerce_template_loop_product_thumbnail - 10
		 */
		//do_action( 'woocommerce_before_shop_loop_item_title' );
			 
		//Changed by Omar
		/**
		 * woocommerce_shop_loop_item_title hook.
		 *
		 * @hooked woocommerce_template_loop_product_title - 10
		 */
		//do_action( 'woocommerce_shop_loop_item_title' );
			 
		wc_get_template( 'loop/sale-flash.php' );
	?>
	
	<?php if ($product->get_type() != "grouped"): ?>
			<a href="<?php echo get_permalink(); ?>"><?php echo get_the_post_thumbnail( $product->get_id(), array(200,200) ); ?></a> 
		<?php else: ?>
			<a href="<?php echo $filter_permalink; ?>"><?php echo get_the_post_thumbnail( $product->get_id(), array(200,200) ); ?></a> 
		<?php endif; ?>
		
		<div class="contentproductcustomcls">
			<?php 
				//Changed by Omar to open the link in new tab
				//the_title(); 
			?>
			
			<?php if ($product->get_type() != "grouped"): ?>
				<a href="<?php echo get_permalink(); ?>"><?php echo get_the_title( $product->get_id() ); ?></a>
			<?php else: ?>
				<a href="<?php echo $filter_permalink; ?>"><?php echo get_the_title( $product->get_id() ); ?></a>
			<?php endif; ?>
			
		</div>

	</a>
	
	<?php

		/**
		 * woocommerce_after_shop_loop_item hook.
		 *
		 * @hooked woocommerce_template_loop_product_link_close - 5
		 * @hooked woocommerce_template_loop_add_to_cart - 10
		 */
		do_action( 'woocommerce_after_shop_loop_item' );
	?>
	
	<?php 
	/**
	 * woocommerce_after_shop_loop_item_title hook.
	 *
	 * @hooked woocommerce_template_loop_rating - 5
	 * @hooked woocommerce_template_loop_price - 10
	 */
	do_action( 'woocommerce_after_shop_loop_item_title' );

	
	?>

</li>
