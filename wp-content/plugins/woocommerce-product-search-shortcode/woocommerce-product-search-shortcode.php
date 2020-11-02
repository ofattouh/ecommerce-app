<?php 
/*
 * Plugin Name: WooCommerce Product Search ShortCode
 * Description: Create shortcode for WooCommerce product search
 * Version: 1.0
 * Author: Omar M.
 * Author URI: http://www.pshsa.ca/
 * Copyright: (c) 2015 PSHSA 
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
 
function create_product_search_shortcode(){

	include_once( ABSPATH . 'wp-content/plugins/ajax-search-pro/ajax-search-pro.php' );

	if ( !is_plugin_active( 'ajax-search-pro/ajax-search-pro.php' ) ) {
  		echo "Error! Ajax Search Pro plugin has to be installed and activated!";
  		exit;
	} 

	if ( isset($_GET['pa_training']) && $_GET['pa_training'] == 'elearning' ):
		$my_short_code = '<div class="wpdreamselearningcls">'.do_shortcode('[wpdreams_ajaxsearchpro id=3]').'</div>';
	elseif ( isset($_GET['pa_training']) && $_GET['pa_training'] == 'in-class-training' ):
		$my_short_code = '<div class="wpdreamsinclasscls">'.do_shortcode('[wpdreams_ajaxsearchpro id=4]').'</div>';
	elseif ( isset($_GET['pa_training']) && $_GET['pa_training'] == 'webinar' ):
		$my_short_code = '<div class="wpdreamswebinarcls">'.do_shortcode('[wpdreams_ajaxsearchpro id=5]').'</div>';
	elseif ( isset($_GET['pa_training']) && $_GET['pa_training'] == 'blended' ):
		$my_short_code = '<div class="wpdreamswebinarcls">'.do_shortcode('[wpdreams_ajaxsearchpro id=6]').'</div>';
	//elseif ( isset($_GET['pa_training-category']) && $_GET['pa_training-category'] == 'training-cat' ):
	else:
		$my_short_code = '<div class="wpdreamstrainingcls">'.do_shortcode('[wpdreams_ajaxsearchpro id=2]').'</div>';
	endif;

	
	return $my_short_code;
			
}

add_shortcode('woocommerce_product_search_shortcode', 'create_product_search_shortcode');

?>