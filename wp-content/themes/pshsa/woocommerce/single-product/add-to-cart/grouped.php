<?php
/**
 * 
 * Grouped product add to cart
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/add-to-cart/grouped.php.
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
 * @version     3.0.7
 * Override by: Omar M. 
 * Subject:     Display the grouped product according to the products filter plugin and the custom product attributes, added the blended product 
 *              sales-type-indicator-regular setup and the blended product sales-type-indicator-free setup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product, $post, $number_session_records;

do_action( 'woocommerce_before_add_to_cart_form' ); 

// Training grouped product
$term_Training_grouped = get_the_terms( $post->ID, 'pa_training' );
if ( $term_Training_grouped && ! is_wp_error( $term_Training_grouped ) ) : 
	foreach ( $term_Training_grouped as $term ):
		$term_Training_Values_Grouped[] = $term->name;
	endforeach;
endif;

$elearning_blended_products_arr = array(); // check for elearning product only for blended grouped courses and don't show it inside the table

$remove_hyper_link_blended_sessions = 0;  // remove the hyper link from the sessions only for blended grouped courses with an elearning session
	
?>

<form class="cart" method="post" enctype='multipart/form-data'>

	<?php
		
		if (in_array('blended', $term_Training_Values_Grouped)){
			foreach($grouped_products as $grouped_product){
				
				$term_Training_Values = array(); // Reset training children
				$term_Training        = get_the_terms( $grouped_product->get_id(), 'pa_training' );
				if ( $term_Training && ! is_wp_error( $term_Training ) ) : 
					foreach ( $term_Training as $term ):
						$term_Training_Values[] = $term->slug;
					endforeach;
				endif;	
				
				// I: Regular blended product setup - customer pay for elearning blended product
				if ( in_array('sales-type-indicator-regular', $term_Training_Values) && in_array('elearning', $term_Training_Values) ){	
						
					if ( $grouped_product->get_status() === 'publish' && $grouped_product->is_in_stock() ){	
						
						$per_value = ( in_array('ishsms', $term_Training_Values) ) ? 'person' : 'course';
						echo "<p>Please indicate the number of students registering for the course (<b>$".$grouped_product->get_price()."</b> per $per_value).</p>";
						
						if ( ! $grouped_product->is_purchasable() || $grouped_product->has_options() ){
							woocommerce_template_loop_add_to_cart();
						} else{	
							woocommerce_quantity_input( array(
								'input_name'  => 'quantity[' . $grouped_product->get_id() . ']',
								'input_value' => 0,
								'min_value'   => apply_filters( 'woocommerce_quantity_input_min', 0, $grouped_product ),
								'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $grouped_product->get_max_purchase_quantity(), $grouped_product ),
							) ); ?>
                            <button type="submit" class="single_add_to_cart_button button alt"><?php echo $product->single_add_to_cart_text(); ?></button>
						<?php }						
					 }
				}
				
				// II: Free blended product setup - customer only pay for in-class blended product
				if ( in_array('sales-type-indicator-free', $term_Training_Values) && in_array('elearning', $term_Training_Values) ){
					$grouped_products_arr     = explode( ",", get_post_meta($post->ID, 'product_in_class_ids', true) );
                    $product_custom_parent_id = get_post_meta($grouped_products_arr[0], 'product_custom_parent_id', true); // select the first in-class product
					$product_in_class_price   = get_post_meta($grouped_products_arr[0], 'product_in_class_price', true); 
					
					if ( $grouped_product->get_status() === 'publish' && $grouped_product->is_in_stock() ){
						echo "<p>Course price: <b>$".$product_in_class_price."</b></p>";
						
						if ( ! $grouped_product->is_purchasable() || $grouped_product->has_options() ){
							woocommerce_template_loop_add_to_cart();
						} 
                        else{
 							woocommerce_quantity_input( array(
								'input_name'  => 'quantity[' . $grouped_products_arr[0] . ']',
								'input_value' => 1,
								'min_value'   => apply_filters( 'woocommerce_quantity_input_min', 0, $grouped_product ),
								'max_value'   => 1,
							) ); ?>
							
 							<a href="<?php echo get_permalink($grouped_product->get_id()); ?>" style="margin-left:10px;padding:12px !important;" class="single_add_to_cart_button button alt" target="_blank">Try it</a>
 							<button type="submit" style="margin-left:10px;" class="single_add_to_cart_button button alt">I 've tried it, I'd like to buy it</button>
 							<a href="<?php echo get_permalink($product_custom_parent_id); ?>" style="margin-left:10px;padding:12px !important;" class="single_add_to_cart_button button alt" target="_blank">Buy it. No trial needed</a>
						<?php }	
					}
                    
                    // convert grouped product array string ids back to grouped products objects
                    foreach($grouped_products_arr as $k => $v){
                        $grouped_products[] = wc_get_product($v);
                    }
				}
				
				// Look for the elearning course on both blended products setup
				if (in_array('elearning', $term_Training_Values) ){
					if ($grouped_product->get_status() === 'publish'){ 
						$remove_hyper_link_blended_sessions           = 1;
						$elearning_blended_products_arr [$grouped_product->get_id()] = $grouped_product->get_id(); ?>
						<p style="clear:both;margin-top:5%;font-weight:bold;font-size:16px;">
						Though you will not register for your classroom session until you have completed the eLearning component of the course, 
						the following upcoming session dates can help you plan your time.</p> 	
				<?php }
				}									
			}
		}
	?>

	<table cellspacing="5" data-page-size="10" class="group_table footable groupedproducttablecls">
		
		<thead>
		  <tr>
		  	<th style="font-weight:bold;" data-sort-initial="true" data-type="numeric">START DATE</th>
		  	
			<th style="font-weight:bold;">SECTOR</th>
			
			<th style="font-weight:bold;">CITY</th>
			
			<th style="font-weight:bold;">VENUE</th>
			
			<th style="font-weight:bold;">FACILITATOR</th>
			
			<th style="font-weight:bold;">TIME</th>
			
			<?php if (!in_array('blended', $term_Training_Values_Grouped)): ?>
				<th style="font-weight:bold;" data-sort-ignore="true">QUANTITY</th>
			<?php endif; ?>
			
			<?php if (!in_array('blended', $term_Training_Values_Grouped)): ?>
				<th style="font-weight:bold;" data-type="numeric">COST(CAD)</th>
			<?php endif; ?>
			
		  </tr>
		</thead>
		
		<tbody>
			<?php
			
				$showMonth       = 1;  //Should match the date field
				$showSector      = 1;
				$showCourse      = 1;
				$showRegion      = 1;
				$showCity        = 1;  //should match the region field
				$showTraining    = 1;
				$showTrainingCat = 1;
				$number_session_records = 0;
				
				//Intialize
				$termMonthValues       = "";
				$termDateValues        = "";
				$termSessionInfoValues = "";   //Session specific info
				$termSectorValues      = "";
				$termCourseValues      = "";
				$termRegionValues      = "";
				$termCityValues        = "";
				$termVenueValues       = "";
				$termFacilitatorValues = "";
				$termTimeValues        = "";
				$termTrainingValues    = "";
				$termTrainingCatValues = "";
				
				//Parse the url to get the product filter custom attribute parameters
				parse_str($_SERVER['QUERY_STRING'], $output);
		
				foreach($output as $k=>$v):
					if (preg_match("/^pa_/i", $k)):
						$arr_params [$k] = $v;
					endif;
				endforeach;
					
				$quantites_required = false;
				$previous_post      = $post;
            
				foreach ( $grouped_products as $grouped_product ) {
					$post_object        = get_post( $grouped_product->get_id() );
					$quantites_required = $quantites_required || ( $grouped_product->is_purchasable() && ! $grouped_product->has_options() );

					setup_postdata( $post =& $post_object );
					
					/* Fetch woo commerce product custom attributes values *****************************/
					
					//Month
					$termMonth = get_the_terms( $post->ID, 'pa_month' );
					if ( $termMonth && ! is_wp_error( $termMonth ) ) : 
						foreach ( $termMonth as $term ) {
							$termMonthValues .= $term->name."<br>";
							if (isset($arr_params['pa_month']) && strtoupper($term->slug) != strtoupper($arr_params['pa_month'])) :
								$showMonth = 0;
							endif;
						}
						$termMonthValues = substr($termMonthValues, 0, -1);	
					else :
						$showMonth = 0;
					endif;
					
					//Start Date
					$termDate = get_the_terms( $post->ID, 'pa_start-date' );
					if ( $termDate && ! is_wp_error( $termDate ) ) : 
						foreach ( $termDate as $term ) {
							$termDateValues .= $term->name;
						}
						//$termDateValues = substr($termDateValues, 0, -1);
						//echo $product_id.": ".$termDateValues."<br>";
					endif;
					
					//Session Info
					$termSessionInfo = get_the_terms( $post->ID, 'pa_session-info' );
					if ( $termSessionInfo && ! is_wp_error( $termSessionInfo ) ) : 
						foreach ( $termSessionInfo as $term ) {
							$termSessionInfoValues .= $term->name."<br>";;
						}
						$termSessionInfoValues = substr($termSessionInfoValues, 0, -1);	
					endif;
					
					/*Sector
					$termSector = get_the_terms( $post->ID, 'pa_sector' );
					if ( $termSector && ! is_wp_error( $termSector ) ) : 
						foreach ( $termSector as $term ) {
							$termSectorValues .= $term->name."<br>";
							if (isset($arr_params['pa_sector']) && strtoupper($term->slug) != strtoupper($arr_params['pa_sector'])) :
								$showSector = 0;
							endif;
						}	
						$termSectorValues = substr($termSectorValues, 0, -1);
					else :
						$showSector = 0;
					endif;
					*/
					
					//Sector - more than one value
					$termSector = get_the_terms( $post->ID, 'pa_sector' );
					
					if ( $termSector && ! is_wp_error( $termSector ) ) : 
						foreach ( $termSector as $term ) {
							$termSectorValues .= $term->name."<br>";
							$termSectorArr [strtoupper($term->slug)] = strtoupper($term->slug);
						}
						if (isset($arr_params['pa_sector'])) :
							if ( in_array(strtoupper($arr_params['pa_sector']), $termSectorArr) || $arr_params['pa_sector'] == 'all-sectors' ) :	
	// 							echo "<br>equal, slug4".strtoupper($term->slug)."=>";
	// 							echo "url4=".strtoupper($arr_params['pa_sector']);
								$showSector = 1;
							else :
								$showSector = 0;
							endif;
						endif ;
						
						$termSectorValues = substr($termSectorValues, 0, -1);
					else :
						$showSector = 0;
					endif;
					
					//Course - more than one value
					$termCourse = get_the_terms( $post->ID, 'pa_all-courses' );
					
					if ( $termCourse && ! is_wp_error( $termCourse ) ) : 
						foreach ( $termCourse as $term ) {
							$termCourseValues .= $term->name."<br>";
							$termCourseArr [strtoupper($term->slug)] = strtoupper($term->slug);
						}
						
						if (isset($arr_params['pa_all-courses'])) :	
							if ( in_array(strtoupper($arr_params['pa_all-courses']), $termCourseArr) ) :	
	// 							echo "<br>equal, slug4".strtoupper($term->slug)."=>";
	// 							echo "url4=".strtoupper($arr_params['pa_all-courses']);
								$showCourse = 1;
							else :
								$showCourse = 0;
							endif;
						endif;
						
						$termCourseValues = substr($termCourseValues, 0, -1);
					else :
						$showCourse = 0;
					endif;
					
					//Region
					$termRegion = get_the_terms( $post->ID, 'pa_region' );
					if ( $termRegion && ! is_wp_error( $termRegion ) ) : 
						foreach ( $termRegion as $term ) {
							$termRegionValues .= $term->name."<br>";
							if (isset($arr_params['pa_region']) && strtoupper($term->slug) != strtoupper($arr_params['pa_region'])) :
								$showRegion = 0;
							endif;
						}
						$termRegionValues = substr($termRegionValues, 0, -1);	
					else :
						$showRegion = 0;
					endif;
					
					//City
					$termCity = get_the_terms( $post->ID, 'pa_course-city' );
					if ( $termCity && ! is_wp_error( $termCity ) ) : 
						foreach ( $termCity as $term ) {
							$termCityValues .= $term->name."<br>";
							if (isset($arr_params['pa_course-city']) && strtoupper($term->slug) != strtoupper($arr_params['pa_course-city'])) :
								$showCity = 0;
							endif;
						}
						$termCityValues = substr($termCityValues, 0, -1);	
					else :
						$showCity = 0;
					endif;
					
					//Venue
					$termVenue = get_the_terms( $post->ID, 'pa_course-location' );
					if ( $termVenue && ! is_wp_error( $termVenue ) ) : 
						foreach ( $termVenue as $term ) {
							$termVenueValues .= $term->name."<br>";
						}
						$termVenueValues = substr($termVenueValues, 0, -1);	
					endif;
					
					//Facilitator
					$termFacilitator = get_the_terms( $post->ID, 'pa_facilitator' );
					if ( $termFacilitator && ! is_wp_error( $termFacilitator ) ) : 
						foreach ( $termFacilitator as $term ) {
							$termFacilitatorValues .= $term->name."<br>";
						}
						$termFacilitatorValues = substr($termFacilitatorValues, 0, -1);	
					endif;
					
					//Time
					$termTime = get_the_terms( $post->ID, 'pa_time' );
					if ( $termTime && ! is_wp_error( $termTime ) ) : 
						foreach ( $termTime as $term ) {
							$termTimeValues .= $term->name."<br>";
						}
						$termTimeValues = substr($termTimeValues, 0, -1);	
					endif;
					
					//Training
// 					$termTraining = get_the_terms( $post->ID, 'pa_training' );
// 					if ( $termTraining && ! is_wp_error( $termTraining ) ) : 
// 						foreach ( $termTraining as $term ) {
// 							$termTrainingValues .= $term->name;
// 							if (isset($arr_params['pa_training']) && strtoupper($term->slug) != strtoupper($arr_params['pa_training'])) :
// 								$showTraining = 0;
// 							endif;
// 						}
// 						//$termTrainingValues = substr($termTrainingValues, 0, -1);	
// 					else :
// 						$showTraining = 0;
// 					endif;
					
					//Training
					$termTraining = get_the_terms( $post->ID, 'pa_training' );
					if ( $termTraining && ! is_wp_error( $termTraining ) ) : 
						foreach ( $termTraining as $term ) {
							$termTrainingValues .= $term->name."<br>";
							$termTrainingArr [strtoupper($term->slug)] = strtoupper($term->slug);
						}
						if (isset($arr_params['pa_training'])) :
							if ( in_array(strtoupper($arr_params['pa_training']), $termTrainingArr) ) :	
								$showTraining = 1;
							else :
								$showTraining = 0;
							endif;
						endif ;
						
						$termTrainingValues = substr($termTrainingValues, 0, -1);
					else :
						$showTraining = 0;
					endif;
					
					//Training category
					$termTrainingCat = get_the_terms( $post->ID, 'pa_training-category' );
					if ( $termTrainingCat && ! is_wp_error( $termTrainingCat ) ) : 
						foreach ( $termTrainingCat as $term ) {
							$termTrainingCatValues .= $term->name."<br>";
							if (isset($arr_params['pa_training-category']) && strtoupper($term->slug) != strtoupper($arr_params['pa_training-category'])) :
								$showTrainingCat = 0;
							endif;
						}
						$termTrainingCatValues = substr($termTrainingCatValues, 0, -1);	
					else :
						$showTrainingCat = 0;
					endif;
					
					/* Fetch product custom attributes values *****************************/
					
					//Determine the session visiblity according to the session cut off date
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
			
					//Check for open sessions	
					$is_open_session = get_post_meta($post->ID, 'open_session', true);
					 
 					//echo "<br><br>ID=".$post->ID.", status=".$post->post_status.", is_open_session=".$is_open_session.", action=".$action;
 					//echo ", days=".$num_days.", start: ".$termDateValues."<br>cutoff: ";print_r($srt_date_obj);echo "<br>now: ";print_r($cur_date_obj);
 					//echo "<br>showMonth=".$showMonth.", showSector=".$showSector.", showCourse=".$showCourse;echo ", showCity=".$showCity;
 					//echo ", showRegion=".$showRegion.", showTraining=".$showTraining.", showTrainingCat=".$showTrainingCat; 
 					//echo ", is_in_stock=".$grouped_product->is_in_stock(); 
					
					if ( (
						  $post->post_status === 'publish' && 
						  $action != "hide" && ! in_array($post->ID, $elearning_blended_products_arr) &&
						( $showMonth == 1 || in_array('blended', $term_Training_Values_Grouped) ) && 
						( $showSector == 1 || in_array('blended', $term_Training_Values_Grouped) ) && 
						( $showCourse == 1 || in_array('blended', $term_Training_Values_Grouped) ) &&
						( $showCity == 1 ) && 
						( $showRegion == 1 || in_array('blended', $term_Training_Values_Grouped) ) && 
						$showTraining == 1
						) && $showTrainingCat == 1 ) { $number_session_records++; 
					?>
					
					<tr id="product-<?php the_ID(); ?>" <?php post_class(); ?>>			
						
						<td data-value=<?php echo strtotime($termDateValues); ?>><?php
								$termDateFormatedValues = (trim($termDateValues) != "")? date('M d, Y', strtotime($termDateValues)) : 'N/A'; 
								if ($remove_hyper_link_blended_sessions == 0):
									echo '&nbsp;&nbsp;<a href="'.get_permalink($grouped_product->get_id()).'">'.$termDateFormatedValues.'</a> '.$termSessionInfoValues; 
								else:
									echo '&nbsp;&nbsp;'.$termDateFormatedValues.' '.$termSessionInfoValues; 
								endif;
								?>
						</td>					
						
						<td><?php echo $termSectorValues; ?></td>
						
						<td><?php echo $termCityValues; ?></td>
						
						<td><?php echo $termVenueValues; ?></td>
						
						<td><?php echo $termFacilitatorValues; ?></td>
						
						<td><?php echo $termTimeValues; ?></td>

						<?php if ( ($action == 'buy' || strtolower($is_open_session) == 'yes') && !in_array('blended', $term_Training_Values_Grouped) && $grouped_product->is_in_stock() ): ?>
							<td>
							<?php if ( ! $grouped_product->is_purchasable() || $grouped_product->has_options() ) : ?>
								<?php woocommerce_template_loop_add_to_cart(); ?>
								
							<?php elseif ( $grouped_product->is_sold_individually() ) : ?>
								<input type="checkbox" name="<?php echo esc_attr( 'quantity[' . $grouped_product->get_id() . ']' ); ?>" value="1" class="wc-grouped-product-add-to-cart-checkbox" />
								
							<?php else : ?>
								<?php
									/**
									 * @since 3.0.0.
									 */
									do_action( 'woocommerce_before_add_to_cart_quantity' );

									woocommerce_quantity_input( array(
										'input_name'  => 'quantity[' . $grouped_product->get_id() . ']',
										'input_value' => isset( $_POST['quantity'][ $grouped_product->get_id() ] ) ? wc_stock_amount( $_POST['quantity'][ $grouped_product->get_id() ] ) : 0,
										'min_value'   => apply_filters( 'woocommerce_quantity_input_min', 0, $grouped_product ),
										'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $grouped_product->get_max_purchase_quantity(), $grouped_product ),
									) );
									
									/**
									 * @since 3.0.0.
									 */
									do_action( 'woocommerce_after_add_to_cart_quantity' );
									
								?>
							<?php endif; ?>
							</td>
						<?php endif; ?>
						
						
						<!--
						<td class="label">
							<label for="product-<?php echo $grouped_product->get_id(); ?>">
								<?php echo $grouped_product->is_visible() ? '<a href="' . esc_url( apply_filters( 'woocommerce_grouped_product_list_link', get_permalink($grouped_product->get_id()), $grouped_product->get_id() ) ) . '">' . $grouped_product->get_name() . '</a>' : $grouped_product->get_name(); ?>
							</label>
						</td>
						-->
						
						<?php do_action ( 'woocommerce_grouped_product_list_before_price', $grouped_product ); ?>
						
						<?php if (!in_array('blended', $term_Training_Values_Grouped)): ?>
							<td class="price">
							<?php
								echo $grouped_product->get_price_html();
								$availability = $grouped_product->get_availability();
								
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
						       
								echo wc_get_stock_html( $grouped_product );
							?>	
							</td>				
						<?php endif; ?>
						
					</tr>
					
					<?php 
						
					} //end if
					
					//reset the custom attruibutes for next simple product and the other variables
					$termMonthValues       = "";
					$termDateValues        = "";
					$termSessionInfoValues = "";
					$termSectorValues      = "";
					$termCourseValues      = "";
					$termRegionValues      = "";
					$termCityValues        = "";
					$termVenueValues       = "";
					$termFacilitatorValues = "";
					$termTimeValues        = "";
					$termTrainingValues    = "";
					$termTrainingCatValues = "";
					
					$showMonth       = 1;
					$showSector      = 1;
					$showCourse      = 1;
					$showRegion      = 1;
					$showCity        = 1;
					$showTraining    = 1;
					$showTrainingCat = 1;
					
					$termSectorArr   = array();
					$termCourseArr   = array();
					$termTrainingArr = array();
				
				} //end foreach
				
				// Return data to original post.
				setup_postdata( $post =& $previous_post );
			?>
		</tbody>
		
		<?php
			$colspan = 8;
			
			if($number_session_records > 0):
				echo "<tfoot><tr style='background-color:#52B9E9;'><td colspan='".$colspan."' style='color:#FFFFFF;font-weight:bold;'>&nbsp;&nbsp;Found (".$number_session_records. ") training session(s)</td></tr></tfoot>";
			else :
				$nosessions_post = get_page_by_path('no-sessions-found', OBJECT, 'post');  // Get the no sessions post from the back end
				echo "<tfoot><tr style='background-color:#52B9E9;'><td colspan='".$colspan."' style='color:#FFFFFF;font-weight:bold;'>";
				echo $nosessions_post->post_content."</td></tr></tfoot>";
			endif;
		?>
				
	</table>

	<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" />

	<?php if ( $quantites_required ) : ?>

		<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

		<?php if ( $remove_hyper_link_blended_sessions == 0 && $product->is_in_stock() ): ?>
			<button type="submit" class="single_add_to_cart_button button alt groupedproductaddtocartbtncls"><?php echo $product->single_add_to_cart_text(); ?></button>
		<?php endif; ?>
		
		<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>

	<?php endif; ?>
</form>

<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>