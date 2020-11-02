<?php
/**
 * Single product short description
 *
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     1.6.4
 * Override By Omar: Show either the post content ot the post short description 
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $post;

if ( ! $post->post_excerpt ) {
	return;
}

?>
<div itemprop="description">
	<?php 
		if (is_shop()):
		
			$excerpt_string = strip_tags($post->post_excerpt);
			
			if (strlen($excerpt_string) > 200):
			    $stringCut      = substr($excerpt_string, 0, 200);
			    $excerpt_string = substr($stringCut, 0, strrpos($stringCut, ' ')).' ...'; 
			    echo apply_filters( 'woocommerce_short_description', $excerpt_string );
			else:
				echo apply_filters( 'woocommerce_short_description', $post->post_excerpt );
			endif;
			
		else:
			echo $post->post_content;
			//echo apply_filters( 'woocommerce_short_description', $post->post_excerpt );
		endif;
	?>
</div>