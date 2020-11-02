<?php
/*
 * Plugin Name: WooCommerce Change Add to Cart Link
 * Description: Add the product filter custom attributes to the Add to cart url and change the add to cart text/url
 * Version: 1.0
 * Author: Omar M.
 * Author URI: http://www.pshsa.ca/
 * Copyright: (c) 2015 PSHSA
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/*
add_filter( 'woocommerce_product_add_to_cart_text' , 'custom_woocommerce_product_add_to_cart_text' );

//Change the Add to cart text
function custom_woocommerce_product_add_to_cart_text() {
	global $product;
	
	$product_type = $product->product_type;
	
	switch ( $product_type ) {
		case 'grouped':
			return __( 'View Details', 'woocommerce' );
		break;
		case 'external':
			return __( 'Buy product', 'woocommerce' );
		break;
		case 'simple':
			return __( 'Add to cart', 'woocommerce' );
		break;
		case 'variable':
			return __( 'Select options', 'woocommerce' );
		break;
		default:
			return __( 'Read more', 'woocommerce' );
	}
	
}


add_filter( 'woocommerce_product_add_to_cart_url' , 'custom_woocommerce_product_add_to_cart_url' );

//Change the Add to cart url
function custom_woocommerce_product_add_to_cart_url() {
	global $product;
		
	switch ( $product->product_type ) {
		case 'grouped':
			//Parse the url to get the product filter custom attribute parameters
			parse_str($_SERVER['QUERY_STRING'], $output);
			
			foreach($output as $k=>$v):
				if (preg_match("/^pa_/i", $k)):
					$arr_params [$k] = $v;
				endif;
			endforeach;
			
			return ( sizeof($arr_params) > 0 ) ? esc_url(add_query_arg($arr_params, get_permalink($product->id))) : get_permalink($product->id);
		break;
		
		case 'external':
			return $product->get_product_url();
		break;
		
		case 'simple':
			$url = $product->is_purchasable() && $product->is_in_stock() ? remove_query_arg( 'added-to-cart', add_query_arg( 'add-to-cart', $product->id ) ) : get_permalink( $product->id );
			return $url;
		break;
		
		case 'variable':
			$url = $product->is_purchasable() && $product->is_in_stock() ? remove_query_arg( 'added-to-cart', add_query_arg( array_merge( array( 'variation_id' => $product->variation_id, 'add-to-cart' => $product->id ), $product->variation_data ) ) ) : get_permalink( $product->id );
			return $url;
		break;
		default:
			return get_permalink($product->id);
		
	}
	
}

*/


//Remove the built in Add to cart button in the product archive/shop page
add_action('init','remove_loop_button');
 
function remove_loop_button(){
	remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
}
 
 
//Add new button that links to product page for each product in the product archive/shop page
add_action('woocommerce_after_shop_loop_item','replace_add_to_cart');

function replace_add_to_cart() {
	global $product;
		
	switch ( $product->product_type ) {
		case 'grouped':
			//Parse the url to get the product filter custom attribute parameters
			parse_str($_SERVER['QUERY_STRING'], $output);
			
			foreach($output as $k=>$v):
				if (preg_match("/^pa_/i", $k)):
					$arr_params [$k] = $v;
				endif;
			endforeach;
			$url = ( sizeof($arr_params) > 0 ) ? esc_url(add_query_arg($arr_params, get_permalink($product->id))) : get_permalink($product->id);
		break;
		
		case 'external':
			//$url = $product->get_product_url();
			$url = get_permalink( $product->id );
		break;
		
		case 'simple':
			//$url = $product->is_purchasable() && $product->is_in_stock() ? remove_query_arg( 'added-to-cart', add_query_arg( 'add-to-cart', $product->id ) ) : get_permalink( $product->id );
			$url = get_permalink( $product->id );
		break;
		
		case 'variable':
			$url = $product->is_purchasable() && $product->is_in_stock() ? remove_query_arg( 'added-to-cart', add_query_arg( array_merge( array( 'variation_id' => $product->variation_id, 'add-to-cart' => $product->id ), $product->variation_data ) ) ) : get_permalink( $product->id );
		break;
		default:
			$url = get_permalink($product->id);
		
	}
	
	echo "<br><a href='".$url."' class='productcataloglinkcls'>View Details</a>";
	
}


 