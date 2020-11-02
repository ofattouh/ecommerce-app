<?php
/**
 * The Template for displaying product archives, including the main shop page which is a post type archive.
 *
 * Override this template by copying it to yourtheme/woocommerce/archive-product.php
 *
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     2.0.0
 * Override the template to create the new shop archive page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

get_header( 'shop' ); ?>

	<?php
		/**
		 * woocommerce_before_main_content hook
		 *
		 * @hooked woocommerce_output_content_wrapper - 10 (outputs opening divs for the content)
		 * @hooked woocommerce_breadcrumb - 20
		 */
		do_action( 'woocommerce_before_main_content' );
	?>

		<?php if ( apply_filters( 'woocommerce_show_page_title', true ) ) : ?>

			<h1 class="page-title"><?php woocommerce_page_title(); ?></h1>

		<?php endif; ?>

		<?php 
		
			//do_action( 'woocommerce_archive_description' ); 
			
			//show the shop word press back end page for all the pages and not just page 0
			if ( is_post_type_archive( 'product' ) ) {
				$shop_page   = get_post( wc_get_page_id( 'shop' ) );
				if ( $shop_page ) {
					$description = wc_format_content( $shop_page->post_content );
					if ( $description ) {
						echo '<div class="page-description">' . $description . '</div>';
					}
				}
			}
			
		?>

		<?php if ( have_posts() ) : ?>

			<?php
				
				/**
				 * woocommerce_before_shop_loop hook
				 *
				 * @hooked woocommerce_result_count - 20
				 * @hooked woocommerce_catalog_ordering - 30
				 */
				do_action( 'woocommerce_before_shop_loop' );
			?>
		
			<?php woocommerce_product_loop_start(); ?>

				<?php woocommerce_product_subcategories(); ?>

				<?php while ( have_posts() ) : the_post(); ?>

					<?php wc_get_template_part( 'content', 'product' ); ?>

				<?php endwhile; // end of the loop. ?>

			<?php woocommerce_product_loop_end(); ?>

			<?php
				/**
				 * woocommerce_after_shop_loop hook
				 *
				 * @hooked woocommerce_pagination - 10
				 */
				do_action( 'woocommerce_after_shop_loop' );
			?>

		<?php elseif ( ! woocommerce_product_subcategories( array( 'before' => woocommerce_product_loop_start( false ), 'after' => woocommerce_product_loop_end( false ) ) ) ) : ?>

			<?php wc_get_template( 'loop/no-products-found.php' ); ?>

		<?php endif; ?>

	<?php
		/**
		 * woocommerce_after_main_content hook
		 *
		 * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs for the content)
		 */
		do_action( 'woocommerce_after_main_content' );
	?>


<?php 
	// Shop page Desclaimer
	//$shop_pre_footer = get_post(24030);          //By post ID
	$shop_pre_footer = get_page_by_path( 'shop-page-desclaimer', OBJECT, 'post' );   //By post slug
	
	if ( $shop_pre_footer->post_status == 'publish' ):
		echo $shop_pre_footer->post_content;
	endif;
	
?>

<?php get_footer( 'shop' ); ?>