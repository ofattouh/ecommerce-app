<?php 
/*
 * Plugin Name: WooCommerce Product of the Day ShortCode
 * Description: Create shortcode for WooCommerce product of the day plugin
 * Version: 1.0
 * Author: Omar M.
 * Author URI: http://www.pshsa.ca/
 * Copyright: (c) 2015 PSHSA 
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
 
function create_product_of_the_day_shortcode(){
	
	$widget_products_of_the_Day = get_option('widget_woocommerce_products_of_the_day');
	$widgetValues               = array_values($widget_products_of_the_Day);
	$instance                   = array();
	$popularProductsWidget      = '';
	
	$instance['title']            = $widgetValues[0]['title'];
	$instance['number']           = $widgetValues[0]['number'];
	$instance['show_thumbs']      = $widgetValues[0]['show_thumbs'];
	$instance['show_add_to_cart'] = $widgetValues[0]['show_add_to_cart'];
	
	// Set number of products to fetch
    $number = filter_var( $instance['number'], FILTER_VALIDATE_INT ) ? absint($instance['number']) : 10;
    $day    = strtolower(date('D'));

    $q = new WP_Query( array(
        'posts_per_page'    => $number,
        'post_type'         => 'product',
        'post_status'       => 'publish',
        'meta_key'          => 'product_of_the_day_' . $day,
        'orderby'           => 'meta_value_num',
        'order'             => 'ASC',
        'nopaging'          => false,
        'meta_query'        => array( array(
            'key'       => '_visibility',
            'value'     => array( 'catalog', 'visible' ),
            'compare'   => 'IN',
        ) )
    ) );

    // If there are products
    if( $q->have_posts() ) {
    
        // Print out each product
        while( $q->have_posts() ) : $q->the_post();
           
        	$product      = new WC_Product( get_the_ID() );
        	$productPrice = get_post_meta( get_the_ID(), '_regular_price', true);
        	
            // Print the product image & title with a link to the permalink
            $popularProductsWidget .= '<div class="productofthedaydiv">';
            $popularProductsWidget .= '<a class="productofthedayanchor" href="' . esc_attr( get_permalink() ) . '" title="' . 
            							esc_attr( get_the_title() ) . '">';
            
            // Print the product image
            if (isset($instance['show_thumbs']) && $instance['show_thumbs']):
                $popularProductsWidget .= woocommerce_get_product_thumbnail();
            endif;

            $popularProductsWidget .= '<br><span class="productofthedaytitle">'.get_the_title().'</span>';
            $popularProductsWidget .= '</a>';

            // Print the price with html wrappers
            //$popularProductsWidget .= "<br><span class='productofthedayprice'> $".$productPrice."</span><br>";
            
            if (isset($instance['show_add_to_cart']) && $instance['show_add_to_cart']):
            	//$popularProductsWidget .= '<br><a class="button add_to_cart_button product_type_simple" data-quantity="1" ';
	            //$popularProductsWidget .= 'data-product_sku="'.$product->get_sku().'" data-product_id="'.get_the_ID().'" rel="nofollow" ';
	            //$popularProductsWidget .= 'href="/shop/?add-to-cart='.get_the_ID().'">Add to cart</a></div>';						
	            $popularProductsWidget .= '<br><a class="button add_to_cart_button product_type_simple featuredproductscls" href="'.get_permalink($product->id).'">View Details</a></div>';
            endif;
            
        endwhile;

        // Reset the global $the_post as this query will have stomped on it
        wp_reset_postdata();
        
    } //end if
    else{
	    $popularProductsWidget .= '<p>There are currently no active popular products. Check back later!</p>';
    }
     		
    return $popularProductsWidget;
    
} //end fn

add_shortcode('woocommerce_product_of_the_day_shortcode', 'create_product_of_the_day_shortcode');

?>