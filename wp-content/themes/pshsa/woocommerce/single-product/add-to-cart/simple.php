<?php
/**
 * Simple product add to cart
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/add-to-cart/simple.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     3.0.0
 * Override by Omar: Determine the add to cart availability according to specific conditions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

if ( ! $product->is_purchasable() ) {
	return;
}

?>

<?php

	/* Match any of these 4 rules to enable the Add to Cart functionality */
	
	//I: Look for physical products
	$productCategories = wp_get_post_terms( $product->get_id(), 'product_cat' );
	$physicalProduct = false;
	foreach ($productCategories as $k => $v) :
		if ($v->name == 'Products') :
			$physicalProduct = true;
		endif;
	endforeach;
		
	//II: Start Date
	$termDate = get_the_terms( $product->get_id(), 'pa_start-date' );
	if ( $termDate && ! is_wp_error( $termDate ) ) : 
		foreach ( $termDate as $term ) {
			$termDateValues .= $term->name;
		}
	endif;
		
	//III: Training
	$termTrainingValues = array();   //reset
	$termTraining = get_the_terms( $product->get_id(), 'pa_training' );
	if ( $termTraining && ! is_wp_error( $termTraining ) ) : 
		foreach ( $termTraining as $term ) {
			$termTrainingValues[] = $term->name;
		}
	endif;
						
	// Determine the session visiblity according to the session cut off date
	date_default_timezone_set('America/New_York');
	$srt_date_obj = new DateTime($termDateValues);
	$cur_date_obj = new DateTime('now');
	$srt_date_obj->modify("-2 days");
	$val_obj  = $srt_date_obj->diff($cur_date_obj);
	$num_days = $val_obj->format('%r%a');
	$action   = "N/A";
	
	if ($num_days < 0 || ($srt_date_obj->getTimestamp() - $cur_date_obj->getTimestamp()) > 0 ):
		$action = "buy";
	elseif (0 <= $num_days && $num_days <= 2):
		$action = "view";
	elseif($num_days > 2):
		$action = "hide";
	endif;
	
	//IV: Check for open sessions	
	$is_open_session = get_post_meta($product->get_id(), 'open_session', true);
	
	//V: Check for exclude from add to cart post meta
	$disable_add_to_cart = get_post_meta($product->get_id(), 'disable_add_to_cart', true);
	

// 	echo "<br>ID=".$product->get_id().", action=".$action.",days=".$num_days.", start: ".$termDateValues.", ".print_r($termTrainingValues);
// 	echo ",physicalProduct=".$physicalProduct.", is_open_session=".$is_open_session."<br>cutoff: ";print_r($srt_date_obj);echo "<br>now: ";
// 	print_r($cur_date_obj); echo ", disable_add_to_cart: ".$disable_add_to_cart;
	
	$availability = $product->get_availability();
	
   // Look for out of stock products and override the default out of stock message
	if ( ! function_exists( 'custom_override_woocommerce_get_stock_html' ) ) {
		function custom_override_woocommerce_get_stock_html($html, $product) {
            if( ! $product->is_in_stock() ){
		        return '<p class="stock ' . esc_attr( $availability['class'] ) . '">Course full, please call us for alternate dates</p>';
            }
            else{
	            return $html;
            }
        }
	}          								
    add_filter( 'woocommerce_get_stock_html' , 'custom_override_woocommerce_get_stock_html', 10, 2 );
							        									
	if ( ($action == "buy" || in_array('eLearning', $termTrainingValues) || $physicalProduct == true || strtolower($is_open_session) == 'yes') 
			&& strtolower($disable_add_to_cart) != 'yes' ):
		echo wc_get_stock_html( $product );
	endif;
	
?>

<?php if ( $product->is_in_stock() ) : ?>

	<?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>

	<?php if ( ($action == "buy" || in_array('eLearning', $termTrainingValues) || $physicalProduct == true || strtolower($is_open_session) == 'yes') 
				&& strtolower($disable_add_to_cart) != 'yes' ): ?>
	
		<form class="cart" method="post" enctype='multipart/form-data'>
		 	<?php 
                /**
                 * @since 2.1.0.
                 */
                do_action( 'woocommerce_before_add_to_cart_button' );
		 	
                /**
                 * @since 3.0.0.
                 */
                do_action( 'woocommerce_before_add_to_cart_quantity' );

                woocommerce_quantity_input( array(
                    'min_value'   => apply_filters( 'woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product ),
                    'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product ),
                    'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( $_POST['quantity'] ) : $product->get_min_purchase_quantity(),
                ) );
		 		
                /**
                 * @since 3.0.0.
                 */
                do_action( 'woocommerce_after_add_to_cart_quantity' );
		 	?>
	
            <button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button button alt"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button>
	
			<?php
                /**
                 * @since 2.1.0.
                 */
                do_action( 'woocommerce_after_add_to_cart_button' );
		    ?>
		</form>
		
	<?php endif; ?>
	
	<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>

<?php endif; ?>
