<?php

/**
 * My Account page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/my-account.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woothemes.com/document/template-structure/
 * @author  WooThemes
 * @package WooCommerce/Templates
 * @version 2.6.0
 * Override by Omar: Allow the user to register for session dates for the blended course(s) and changed the page layout
*/
 

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

wc_print_notices(); 


/**
 * My Account navigation.
 * @since 2.6.0
 */
// Changd by Omar
//do_action( 'woocommerce_account_navigation' ); ?>


<!-- changed by Omar 
<div class="woocommerce-MyAccount-content"> -->
	<?php
		/**
		 * My Account content.
		 * @since 2.6.0
		 */
		// Changd by Omar
		do_action( 'woocommerce_account_content' );
	?>
<!-- </div> -->

<!--
<p class="myaccount_user">
	<?php
	printf(
		__( 'Hello <strong>%1$s</strong><span style="float:right;">(not %1$s? <a href="%2$s">Sign out</a>)</span>', 'woocommerce' ) . ' ',
		$current_user->display_name,
		wc_get_endpoint_url( 'customer-logout', '', wc_get_page_permalink( 'myaccount' ) )
	);
	
	printf( __( '<br><a href="%s">Edit your Account Details</a>.', 'woocommerce' ), wc_customer_edit_account_url() );
	
	?>
</p>
-->

<style>
	.blendedtablethcls{background-color: #7a2531;color: #ffffff;padding: 3%;font-weight:bold;font-size:14px;}
	.booksessionbtncls{background-color: #7a2531 !important;color: #ffffff;padding: 2%;font-weight:bold;font-size:16px;}
</style>

<?php

	global $wpdb;
	
	// Get all the blended completed courses that belong to this student
	$studentCourses = $wpdb->get_results( "SELECT * FROM `ckv_student_info` WHERE `courseType` LIKE '%blended%' AND `courseCompleted` LIKE '%yes%' AND `email`='".$current_user->user_email."'" );
	
	if(sizeof($studentCourses) > 0){
		
		$countSessions = 0;      //Keep count of how many sessions are there per each blended course (simple products/sessions)
		$countBlended  = 0;      //Keep count of how many blended courses (grouped products)
		
		//Intialize
		$termDateValues        = "";
		$termTimeValues        = "";
		$termSectorValues      = "";
		$termCityValues        = "";
		$termVenueValues       = "";
		$termFacilitatorValues = "";

		// Loop through all the ckv_student_info table records and match the course ID
		foreach($studentCourses as $k => $v){
			
			// Check for blended courses of both cases
			if( ( in_array('blended', get_attribute_values($v->parentCourseID)) && in_array('sales-type-indicator-regular', get_attribute_values($v->CourseID)) && in_array('elearning', get_attribute_values($v->CourseID)) )
				|| ( in_array('blended', get_attribute_values($v->parentCourseID)) && in_array('sales-type-indicator-free', get_attribute_values($v->CourseID)) && in_array('in-class-training', get_attribute_values($v->CourseID)) ) 
				){
				
				// Get the grouped blended product
				$grouped_blended_product = wc_get_product($v->parentCourseID);

				if ($countBlended == 0){ ?>
					<h2 style="font-size:20px;">Please select a session to book</h2>
					<span style="font-size:12px;">* Note: You may only book one session at a time.</span>
					
					<table>
						<tr>
							<th class="blendedtablethcls">DATE</th>
							<th class="blendedtablethcls">TIME</th>
							<th class="blendedtablethcls">SECTOR</th>
							<th class="blendedtablethcls">CITY</th>
							<th class="blendedtablethcls">VENUE</th>
							<th class="blendedtablethcls">FACILITATOR</th>
							<th class="blendedtablethcls">BOOKED SESSION</th>
							<th class="blendedtablethcls">* SELECT A SESSION</th>
						</tr>	
				<?php }
				
				if ( sizeof($grouped_blended_product->get_children()) > 0 ) {
				
					$showBookingBtnSection = 'no';  // By default hide the blended booking button section
					 
					foreach($grouped_blended_product->get_children() as $blended_product_id) {
				
						//Get all the simple products sessions that belong to this blended grouped product
						$simple_blended_product = wc_get_product($blended_product_id);
						
						$blended_post = $simple_blended_product->post;
						setup_postdata($blended_post);				
						
						// Check for elearning product and exclude it from the sessions output
						if (! is_elearning_product($blended_post->ID) ){
							
							//Check if the student has booked the session
							$is_session_booked = ($v->sessionID == $blended_post->ID && $v->sessionID > 0)? 'Yes': 'No'; 
								
							//Start Date
							$termDate = get_the_terms( $blended_post->ID, 'pa_start-date' );
							if ( $termDate && ! is_wp_error( $termDate ) ) : 
								foreach ( $termDate as $term ) {
									$termDateValues .= $term->name;
								}
							endif;
							
							//Time
							$termTime = get_the_terms( $blended_post->ID, 'pa_time' );
							if ( $termTime && ! is_wp_error( $termTime ) ) : 
								foreach ( $termTime as $term ) {
									$termTimeValues .= $term->name."<br>";
								}
							endif;
							
							//Sector
							$termSector = get_the_terms( $blended_post->ID, 'pa_sector' );
							if ( $termSector && ! is_wp_error( $termSector ) ) : 
								foreach ( $termSector as $term ) {
									$termSectorValues .= $term->name."<br>";
								}
							endif;
					
							//City
							$termCity = get_the_terms( $blended_post->ID, 'pa_course-city' );
							if ( $termCity && ! is_wp_error( $termCity ) ) : 
								foreach ( $termCity as $term ) {
									$termCityValues .= $term->name."<br>";
								}
							endif;
							
							//Venue
							$termVenue = get_the_terms( $blended_post->ID, 'pa_course-location' );
							if ( $termVenue && ! is_wp_error( $termVenue ) ) : 
								foreach ( $termVenue as $term ) {
									$termVenueValues .= $term->name."<br>";
								}
							endif;
							
							//Facilitator
							$termFacilitator = get_the_terms( $blended_post->ID, 'pa_facilitator' );
							if ( $termFacilitator && ! is_wp_error( $termFacilitator ) ) : 
								foreach ( $termFacilitator as $term ) {
									$termFacilitatorValues .= $term->name."<br>";
								}
							endif;
							
							//Determine the session visiblity according to the session cut off date
							$srt_date_obj = new DateTime($termDateValues);
							$cur_date_obj = new DateTime('now');
							$srt_date_obj->modify("-7 days");
							$val_obj  = $srt_date_obj->diff($cur_date_obj);
							$num_days = $val_obj->format('%r%a');
							$action   = "N/A";
							
							if ($num_days < 0):
								$action = "buy";
							elseif (0 <= $num_days && $num_days <= 7):
								$action = "view";
							elseif($num_days > 7):
								$action = "hide";
							endif;
							
							?>

							<?php if ( $action != 'hide' && $blended_post->post_status == 'publish' && $simple_blended_product->is_in_stock() ): ?>
								<tr>
									<td><?php echo date('M d, Y', strtotime($termDateValues)); ?></td>
									<td><?php echo $termTimeValues; ?></td>
									<td><?php echo $termSectorValues; ?></td>
									<td><?php echo $termCityValues; ?></td>
									<td><?php echo $termVenueValues; ?></td>
									<td><?php echo $termFacilitatorValues; ?></td>
									<td id="tblcell_<?php echo $countBlended; ?>"><span class="iscoursebookedspancls" id="iscoursebookedspan_<?php echo $blended_post->ID; ?>" 
										name="iscoursebookedspan_<?php echo $blended_post->ID; ?>"> <?php echo $is_session_booked; ?></span></td>
									<td>
										<input type="radio" id="bookradio_<?php echo $countSessions; ?>" name="bookradio_<?php echo $countBlended; ?>" 
											value="<?php echo $blended_post->ID; ?>" onclick="SelectSession(this, <?php echo $countBlended; ?>);" />&nbsp;
										<img id="ajaxprocessing_<?php echo $blended_post->ID; ?>" src="/wp-content/uploads/2016/05/script-loader.gif" 
											alt="Processing..." width="15" height="15" style="display:none;" />
										<span class="coursebookedspanmsgcls" id="coursebookedspanmsg_<?php echo $blended_post->ID; ?>" 
											name="coursebookedspanmsg_<?php echo $blended_post->ID; ?>"></span>
									</td>
								</tr>
							<?php endif; ?>
					<?php 
					
							//echo "<br><br><br>Grouped Course ID=".$v->parentCourseID.',session_id='.$blended_post->ID.",status=".$blended_post->post_status.", action=".$action.", days=".$num_days;
							//echo "<br>start date: ".$termDateValues."<br>cutoff: ";print_r($srt_date_obj);echo "<br>now: ";print_r($cur_date_obj);echo "<br>";
							
							//Reset for next session
							$termDateValues        = "";
							$termTimeValues        = "";
							$termSectorValues      = "";
							$termCityValues        = "";
							$termVenueValues       = "";
							$termFacilitatorValues = "";
							
				    		$countSessions++;
		
		    			} // end if
		    			else{
			    			// If elearning course check its publish status to hide the blended button product section
			    			if ( wc_get_product($blended_post->ID)->get_status() === 'publish' ){
			    				$showBookingBtnSection = 'yes';
		    				}
		    			}
				    } // end foreach 
				   
				 ?>   
				 
			 	<?php if ($showBookingBtnSection == 'yes'): ?>
				    <tr><td></td><td></td><td></td><td></td><td></td><td></td>
				    	<td><input type="hidden" id="selectedsessionID_<?php echo $countBlended; ?>" name="selectedsessionID_<?php echo $countBlended; ?>" /></td>
				    	<td><input type="button" id="bookbtn_<?php echo $countBlended; ?>" name="bookbtn_<?php echo $countBlended; ?>" value="Book A Session" class="booksessionbtncls" 
				    		onclick='BookSession(<?php echo $v->InfoID; ?>,<?php echo $v->parentCourseID; ?>,<?php echo json_encode($current_user->user_email); ?>,<?php echo json_encode($current_user->display_name, JSON_HEX_APOS); ?>,<?php echo json_encode($v->OrderID); ?>,<?php echo $countBlended; ?>);' /></td>
				    </tr>	
				    <tr>
				    	<td colspan="8">
				    		<?php if (sizeof($studentCourses) > 1): ?>
				    			<hr size="30" style="background-color:#7A2531;width:100%;">
				    		<?php endif; ?>
				    	</td>
				    </tr>
			    <?php endif; ?>				
			<?php 
				} // end if
				else{ ?> <tr><td colspan="8">There are no active session(s) for this course!</td></tr><?php }
				
				$countBlended++;
	
			} //end if	
		} //end foreach
		
		if ($countBlended > 0){ ?></table><br><hr><?php }
		
	} //end if 
	

	
?>

<?php
	
	// Check for elearning product
	function is_elearning_product($product_id){
		$found = false;
		$blendedProductCategories = wp_get_post_terms( $product_id, 'product_cat' );
		
		foreach ($blendedProductCategories as $k => $v) :
			if ($v->name == 'eLearning') :
				$found = true;
			endif;
		endforeach;
		
		return $found;
	}
	

	// Helper function: Fetch all the terms for the product
	function get_attribute_values($product_id){
		$term_Training_Values = array();
		$term_Training        = get_the_terms( $product_id, 'pa_training' );
		
		if ( $term_Training && ! is_wp_error($term_Training) ) :
			foreach($term_Training as $oneterm){
				$term_Training_Values[] = $oneterm->slug;
			}
		endif;
		
		return $term_Training_Values;
	}
	
	

?>