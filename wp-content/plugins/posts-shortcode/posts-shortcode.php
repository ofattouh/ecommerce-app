<?php 
/*
 * Plugin Name: Posts ShortCode
 * Description: Create shortcodes for word press posts used in the front page and in the success stories section
 * Version: 1.0
 * Author: Omar M.
 * Author URI: http://www.pshsa.ca/
 * Copyright: (c) 2015 PSHSA 
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

//I:
function create_posts_frontpage_shortcode(){
	
	$posts_shortcode = '';

	$args = array( 
				'post_type' => 'post', 
				'post_status' => 'publish', 
				'posts_per_page' => 1,
				'orderby' => 'date',
				'order' => 'DESC',
				'suppress_filters' => true,
				'tax_query' => array(			
					array(
						'taxonomy' => 'category',
						'field' => 'slug',
						'terms' => 'new-release',
					)
				)
			);

	$wp_query = new WP_Query( $args );
	
	if ( $wp_query->have_posts() ) :
		
		while ($wp_query->have_posts()) : $wp_query->the_post();
	
		$featured_image         = ( get_the_post_thumbnail( $wp_query->post->ID, 'large') != "" ) ? get_the_post_thumbnail( $wp_query->post->ID, 'large') : '<img src="/wp-content/plugins/woocommerce/assets/images/placeholder.png" width="350px" height="350px" alt="Featured Post Image" />';
		$postmeta_external_link = get_post_meta($wp_query->post->ID, 'read_more_external_link', true);
		$read_more_link         = ($postmeta_external_link != "") ? $postmeta_external_link : get_permalink($wp_query->post->ID);
		
		$posts_shortcode .= '<br><a href="'.get_permalink($wp_query->post->ID).'" target="_blank">'.$featured_image.'</a><br>';
		$posts_shortcode .= '<a href="'.get_permalink($wp_query->post->ID).'" target="_blank">'.$wp_query->post->post_title.'</a><br>';
		$posts_shortcode .= read_more_content($wp_query->post->post_content, $read_more_link, 100);
		//$posts_shortcode .= '<br><br><a href="/blog/" style="color:#53ABE2;font-weight:bold;" target="_blank">Show All Stories</a>';
		
		endwhile;
	
	else :
		_e( 'Sorry, no posts matched your criteria.' );
	endif;
	
	wp_reset_postdata();
    
    return $posts_shortcode;
			
}

add_shortcode('posts_frontpage_shortcode', 'create_posts_frontpage_shortcode');


//II:
function create_posts_blog_shortcode(){
	
	$posts_shortcode = '';    
		
	$args = array( 
				'post_type' => 'post', 
				'post_status' => 'publish', 
				'posts_per_page' => 10,
				'paged' => (get_query_var('paged')) ? get_query_var('paged') : 1,
				//'category' => 8,  //Category ID: cat in WP_Query - News Release
				'orderby' => 'date',
				'order' => 'DESC',
				'suppress_filters' => true,
				'tax_query' => array(			
					array(
						'taxonomy' => 'category',
						'field' => 'slug',
						'terms' => 'new-release',
					)
				)
			);
		
	//$postslist = get_posts( $args );
	$custom_query = new WP_Query( $args );
	
	// Pagination fix
	$temp_query = $wp_query;
	$wp_query   = NULL;
	$wp_query   = $custom_query;

	if ( $custom_query->have_posts() ) :
		
		while ($custom_query->have_posts()) : $custom_query->the_post();
		//foreach ( $postslist as $post ) :

			$featured_image         = (get_the_post_thumbnail( $custom_query->post->ID, array(150,150)) != "") ? get_the_post_thumbnail( $custom_query->post->ID, array(150,150)) : '<img src="/wp-content/plugins/woocommerce/assets/images/placeholder.png" width="150px" height="150px" alt="Featured Post Image" />';
			$postmeta_external_link = get_post_meta($custom_query->post->ID, 'read_more_external_link', true);
			$read_more_link         = ($postmeta_external_link != "") ? $postmeta_external_link : get_permalink($custom_query->post->ID);
			
			$posts_shortcode .= '<br><div style="width:100%;margin:0 0 5% 0;clear:both;">';
			$posts_shortcode .= '<div style="float:left;width:20%;"><a href="'.get_permalink($custom_query->post->ID).'" target="_blank">'.$featured_image.'</a></div>';
			$posts_shortcode .= '<div style="float:left;width:80%;margin-top:2%;"><b>Date:</b> '.get_the_date('M d, Y').'</div></div>';
			$posts_shortcode .= '<br><a href="'.get_permalink($custom_query->post->ID).'" target="_blank" style="clear:both;">'.$custom_query->post->post_title.'</a><br>';
			$posts_shortcode .= read_more_content($custom_query->post->post_content, $read_more_link, 100);
			$posts_shortcode .= '<div style="clear:both;"><hr style="border-color:#606060;border-width:1px;margin-top:3%;border-style:dotted;"></div>';
			
		//endforeach; 
		endwhile;

	else :
		_e( 'Sorry, no posts matched your criteria.' );
	endif;

	wp_reset_postdata();
    
	//$posts_shortcode .= next_posts_link( '&laquo;&laquo;Older posts' );
	//$posts_shortcode .= previous_posts_link( 'Newer posts&raquo;&raquo;' );
	
	// Custom query loop pagination
	$posts_shortcode .= previous_posts_link( '&laquo;&laquo;&nbsp;Previous&nbsp;&nbsp;' );
	$posts_shortcode .= next_posts_link( '&nbsp;&nbsp;Next&nbsp;&raquo;&raquo;', $custom_query->max_num_pages );

	// Reset main query object
	$wp_query = NULL;
	$wp_query = $temp_query;

    return $posts_shortcode;
			
}

add_shortcode('posts_blog_shortcode', 'create_posts_blog_shortcode');


//Read More Helper function
function read_more_content($content, $read_more_link, $lengthShown){

	$string = strip_tags($content);
	
	if (strlen($string) > $lengthShown) {
	    $stringCut = substr($string, 0, $lengthShown);
	    // make sure it ends in a word so assassinate doesn't become ass...
	    $string = substr($stringCut, 0, strrpos($stringCut, ' ')).'<br><a href="'.$read_more_link.'" style="color:#53ABE2;font-weight:bold;" target="_blank">Read More ...</a>'; 
	}
	
	return $string;
}