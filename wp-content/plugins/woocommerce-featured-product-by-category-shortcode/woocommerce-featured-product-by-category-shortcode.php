<?php 
/*
 * Plugin Name: WooCommerce Featured Product by Category ShortCode
 * Description: Create shortcodes: to show WooCommerce Featured products by category: e-learning and another to show products by category e-learning
 * Version: 1.2
 * Author: Omar M.
 * Author URI: http://www.pshsa.ca/
 * Copyright: (c) 2015 PSHSA 
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

//I
function create_featured_products_by_category_shortcode(){
	
	$featuredCategoryProducts = '';
	
    $args = array(  
        'post_type'   => 'product',  
        'post_status' => 'publish',
        'ignore_sticky_posts'	=> 1,
        'posts_per_page' => 1,   //Number of featured products to show
        'tax_query' => array(			
			array(
				'taxonomy' => 'product_cat',
				'field' => 'slug',
				'terms' => 'elearning',
			)
		),
        'meta_query' => array(
			//Get visible products
			array(
				'key' => '_visibility',
				'value' => array('search', 'visible'),
				'compare' => 'IN'
			),
			// get only products marked as featured
			array(
				'key' => '_featured',
				'value' => 'yes'
			)
		)     
    );  
      
    $featured_query = new WP_Query( $args );  
          
    if ($featured_query->have_posts()) :   
      
        while ($featured_query->have_posts()) : $featured_query->the_post();  
              
            $product = get_product( $featured_query->post->ID );  
            $thumb   = get_the_post_thumbnail( $featured_query->post->ID, 'full');
   
            // Product information 
            $featuredCategoryProducts .= '<section class="featuredProduct">';
            $featuredCategoryProducts .= '<a href='.get_permalink().' class="thumbnailLink">'.$thumb.'</a>';
            $featuredCategoryProducts .= '<h1><a href='.get_permalink().'>'.$product->post->post_title.'</a></h1>';
            $featuredCategoryProducts .= '<p>'.read_more_excerpt($product->post->post_excerpt, get_permalink(), 100).'</p>';
            $featuredCategoryProducts .= '</section>';
         	
        endwhile;  
        
    else :
		_e( 'Sorry, no posts matched your criteria.' );
	endif;       
      
    wp_reset_query(); // Remember to reset 
    
    return $featuredCategoryProducts;
			
}

add_shortcode('featured_products_by_category', 'create_featured_products_by_category_shortcode');

//II
function create_products_by_category_shortcode(){
	
	$categoryProducts = '<section class="productByCategory"><ul>';
	
    $args = array(  
        'post_type'   => 'product',  
        'post_status' => 'publish',
        'ignore_sticky_posts'	=> 1,
        'posts_per_page' => 3,   //Number of products to show
        'tax_query' => array(			
			array(
				'taxonomy' => 'product_cat',
				'field' => 'slug',
				'terms' => 'elearning',
			)
		),
        'meta_query' => array(
			//Get visible products
			array(
				'key' => '_visibility',
				'value' => array('search', 'visible'),
				'compare' => 'IN'
			)
		)     
    );  
      
    $products_query = new WP_Query( $args );  
          
    if ($products_query->have_posts()) :   
      
        while ($products_query->have_posts()) : $products_query->the_post();  
              
            $product = get_product( $products_query->post->ID );  
            $thumb   = get_the_post_thumbnail( $products_query->post->ID, 'full');
   
            // Product information 
            
            $categoryProducts .= '<li>';
            $categoryProducts .= '<div class="thumbnail"><a href='.get_permalink().'>'.$thumb.'</a></div>';
            $categoryProducts .= '<div class="copy"><a href='.get_permalink().'>'.$product->post->post_title.'</a></div>';
            $categoryProducts .= '</li>';

         	
        endwhile;  
          
    endif;  
      
    wp_reset_query(); // Remember to reset 

    // close tag

    $categoryProducts .= '</section></ul>';
    return $categoryProducts;
			
}

add_shortcode('woo_products_by_category', 'create_products_by_category_shortcode');

//Read More Helper function. This will truncatate the product except, ensure that works are not cut off.
function read_more_excerpt($excerpt, $permalink, $lengthShown){

	$string = strip_tags($excerpt);
	
	if (strlen($string) > $lengthShown) {
	    $stringCut = substr($string, 0, $lengthShown);
	    // make sure it ends in a word so assassinate doesn't become ass...
	    $string = substr($stringCut, 0, strrpos($stringCut, ' ')).'... <a href="'.$permalink.'">Read More</a></p>'; 
	}
	
	return $string;
}

?>