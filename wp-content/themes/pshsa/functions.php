<?php


    // Add grunt icon loader script
    function svgLoader(){
        ?>
        <script>
	        /* grunticon Stylesheet Loader | https://github.com/filamentgroup/grunticon | (c) 2012 Scott Jehl, Filament Group, Inc. | MIT license. */
	        window.grunticon=function(e){if(e&&3===e.length){var t=window,n=!(!t.document.createElementNS||!t.document.createElementNS("http://www.w3.org/2000/svg","svg").createSVGRect||!document.implementation.hasFeature("http://www.w3.org/TR/SVG11/feature#Image","1.1")||window.opera&&-1===navigator.userAgent.indexOf("Chrome")),o=function(o){var a=t.document.createElement("link"),r=t.document.getElementsByTagName("script")[0];a.rel="stylesheet",a.href=e[o&&n?0:o?1:2],a.media="only x",r.parentNode.insertBefore(a,r),setTimeout(function(){a.media="all"})},a=new t.Image;a.onerror=function(){o(!1)},a.onload=function(){o(1===a.width&&1===a.height)},a.src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=="}};
	
	        grunticon([
	          "<?php echo get_stylesheet_directory_uri() ?>/ui/images/icons/icons.data.svg.css",
	          "<?php echo get_stylesheet_directory_uri() ?>/ui/images/icons/icons.data.png.css",
	          "<?php echo get_stylesheet_directory_uri() ?>/ui/images/icons/icons.fallback.css"]);
        </script>
      <?php
    }
    add_action('wp_head', 'svgLoader');

	// Add Google Manager Tag support to the Header
	function google_tag_manager_support_header(){ ?>
		<!-- Google Tag Manager -->
		<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
		new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
		j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
		'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
		})(window,document,'script','dataLayer','GTM-5DCLRK3');</script>
		<!-- End Google Tag Manager -->
		<?php
	}
	add_action('wp_head', 'google_tag_manager_support_header');

	// Add Google Manager Tag support to the Body
	function google_tag_manager_support_body(){ ?>
		<!-- Google Tag Manager (noscript) -->
		<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5DCLRK3"
		height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
		<!-- End Google Tag Manager (noscript) -->
		<?php
	}
	add_action('presscore_body_top', 'google_tag_manager_support_body');

    // load custom JS
    function customJS(){
        wp_register_script('customJS', get_stylesheet_directory_uri().'/main.js', array('jquery'), null, true);
        wp_enqueue_script('customJS');
    }
    add_action('wp_enqueue_scripts', 'customJS');
    
  	// Ajax WP call to Book the blended courses sessions
    add_action( 'wp_enqueue_scripts', 'booksession_ajax_scripts' ); //  
    add_action( 'wp_ajax_ajax-booksessionSubmit', 'booksession_ajax_submit_func' );
    add_action( 'wp_ajax_nopriv_ajax-booksessionSubmit', 'booksession_ajax_submit_func' );
  
    function booksession_ajax_scripts() {
        wp_enqueue_script( 'booksessionJS', get_stylesheet_directory_uri().'/woocommerce/assets/js/woo-myaccount.js', array( 'json2', 'jquery' ));	
        wp_localize_script( 'booksessionJS', 'booksession_Ajax', array(
            'ajaxurl'   => admin_url( 'admin-ajax.php' ),
            'nextNonce' => wp_create_nonce( 'booksession-next-nonce' ))
       );
    }
  
    // Process the Ajax request on the server side and parse the Ajax response
	function booksession_ajax_submit_func() {
		
		global $wpdb;
		
		$nonce         = $_POST['nextNonce']; 	//security
		$is_email_sent = false;
		
		if ( ! wp_verify_nonce( $nonce, 'booksession-next-nonce' ) )
			die ( 'You are not authorized to book this session!');
			
		$response            = json_decode(json_encode($_POST), true);
		$order 				 = wc_get_order( $response['OrderID'] );
		$session_product     = wc_get_product( $response['sessionID'] );
		$parent_course_title = html_entity_decode(get_the_title(get_post_meta($response['sessionID'], 'product_custom_parent_id', true)));  //convert to html entities
		
		header("Content-Type: application/json");
	
		//Store the session booked by the student in the table: ckv_student_info
 		if ( isset($response['CourseID']) && isset($response['InfoID']) && isset($response['OrderID']) && isset($response['sessionID']) ){
	 		
	 		// Increase the product count by 1 for inventory management if the student have already booked a session before
			$studentSessionInfo = $wpdb->get_results( "SELECT * FROM `ckv_student_info` WHERE InfoID = ".$response['InfoID'] );
														  
			if ( $studentSessionInfo[0]->sessionID > 0 && wc_update_product_stock(wc_get_product($studentSessionInfo[0]->sessionID), 1, 'increase') ){
				$order->add_order_note( __( "Product: ".$studentSessionInfo[0]->sessionID." stock level is increased by 1, current stock level is: ".
							wc_get_product( $studentSessionInfo[0]->sessionID )->get_stock_quantity(), 'woocommerce-checkout-registration' ) );
			}	
				
			$update = $wpdb->update( 
				 'ckv_student_info', 
				 array('sessionID' => $response['sessionID']), 
				 array('InfoID' => $response['InfoID']), 
				 array('%s'), 
				 array('%d') 
			);
			 
			 if (false === $update ) :
				$order->add_order_note( __( "Error! Student: ". $response['FullName'].",email: ".$response['userEmail']." could not book Session: ".$session_product->get_title().",Course ID: ". $response['sessionID']." at InfoID: ".$response['InfoID']." for table: ckv_student_info", 'woocommerce-checkout-registration' ) );
			 else :
				$order->add_order_note( __( "Student: ".$response['FullName'].",email: ".$response['userEmail']." has successfully confirmed booking the Session: ".$session_product->get_title().",Course ID: ".$response['sessionID']." at InfoID: ".$response['InfoID']." for table: ckv_student_info", 'woocommerce-checkout-registration' ) );
		
				// Decrease the product count by 1 for inventory management if the student hasn't booked a session before 
				if ( wc_update_product_stock($session_product, 1, 'decrease') ){
					$order->add_order_note( __( "Product: ".$response['sessionID']." stock level is decreased by 1, current stock level is: ".
												$session_product->get_stock_quantity(), 'woocommerce-checkout-registration' ) );
				}
				
				// Session booked for Blended regional Cert 1 courses only
				if ( in_array('blended', get_course_attributes($response['CourseID'], 'pa_training')) && fns_woocommerce_check_for_product_category($response['CourseID'], 'jhsc-certification-part-1') && ! in_array('ishsms', get_course_attributes($response['CourseID'], 'pa_training')) ){
					$is_email_sent = send_student_booking_Blended_Cert1_confirmation_email($response['FullName'], $response['userEmail'], $response['sessionID'], html_entity_decode($session_product->get_title()), $parent_course_title);
				}
				// Session booked for Blended regional WAH (not Cert 1 and not Cert 2) courses only 
				else if ( in_array('blended', get_course_attributes($response['CourseID'], 'pa_training')) && ! fns_woocommerce_check_for_product_category($response['CourseID'], 'jhsc-certification-part-1') && ! in_array('ishsms', get_course_attributes($response['CourseID'], 'pa_training')) ){
					$is_email_sent = send_student_booking_Blended_WAH_confirmation_email($response['FullName'], $response['userEmail'], $response['sessionID'], html_entity_decode($session_product->get_title()), $parent_course_title);
				}
				// Session booked for Blended regional HSMS courses only
				else if ( in_array('blended', get_course_attributes($response['CourseID'], 'pa_training')) && in_array('ishsms', get_course_attributes($response['CourseID'], 'pa_training')) ){
					$is_email_sent = send_student_booking_Blended_HSMS_confirmation_email($response['FullName'], $response['userEmail'], $response['sessionID'], html_entity_decode($session_product->get_title()), $parent_course_title);
				}
				
				// Verify email is sent
				if ($is_email_sent):
					$order->add_order_note( __( "A confirmation email is successfully sent to Student: ".$response['FullName'].",email: ".$response['userEmail']." to confirm booking the Course: ".$session_product->get_title()." at Course ID: ".$response['sessionID'], 'woocommerce-checkout-registration' ) );
				else:
					$order->add_order_note( __( "Error! sending booking course confirmation email to Student: ".$response['FullName'].",email: ".$response['userEmail']." for Course: ".$session_product->get_title().", Course ID: ".$response['sessionID'], 'woocommerce-checkout-registration' ) );
				endif;
			 endif;
			 
 		} //end if
		
		die();	
	}
	
	// Send a confirmation email to the student with the new Course booking info for the blended course booked in-class session Cert 1 courses only
	function send_student_booking_Blended_Cert1_confirmation_email($student_full_name, $student_email, $session_id, $course_title, $parent_course_title){
		
		$is_email_sent = false;
		$ccteamEmail   = 'omohamed@pshsa.ca';
		$to            = array($student_email);
		$subject       = $parent_course_title." Classroom Registration Confirmation & Requirements";
		$headers       = array('Content-Type: text/html; charset=UTF-8;', 'Bcc: '.$ccteamEmail);  //Switch to HTML
 		$attachments   = array(WP_CONTENT_DIR.'/uploads/2019/05/Learner-Consent-Form.pdf');
 		
		$msg  = "<h2>Thank you for registering!</h2>";
		$msg .= "<p>Hello ".stripslashes($student_full_name).".</p>"; 
		
		$msg .= "<p>You have successfully registered for:<br>";  
		$msg .= "<span style='color:#3366CC;font-weight:bolder;'>" . $course_title . " - Classroom Session</span></p>";
		$msg .= "<p><b>Date:</b> " . date('M d, Y', strtotime(implode(" ", get_course_attributes($session_id, 'pa_start-date'))));
		$msg .= "<br><b>Time:</b> " . implode(" ", get_course_attributes($session_id, 'pa_time'));
		$msg .= "<br><b>Venue:</b> " . implode(" ", get_course_attributes($session_id, 'pa_session-info'));
		$msg .= "<br><b>Address:</b> " . implode(" ", get_course_attributes($session_id, 'pa_course-location')) . "</p>";

		$msg .= "<p><b>About This Course:</b> To finish the full JHSC Certification Part 1 Program, participants must successfully complete the ";
		$msg .= "written evaluation administered at the end of the Classroom Session. This evaluation will test on knowledge obtained in both ";
		$msg .= "the eLearning module and the Classroom Session.</p>";
		
		$msg .= "<p><b>Mandatory requirements for the Classroom Session:</b> To take part in training:<br><ul>";
		$msg .= "<li>Participants <span style='text-decoration: underline;'>must</span> bring a piece of government-issued photo ID (such as a Driver's License or a Health Card) with them to the session.</li>";
		$msg .= "<li><span style='color:#FF0000;'>The course will begin promptly at the start time. We strongly recommend participants arrive 15-30 minutes prior to the course commencement to sign in.</span></li>";
		$msg .= "<li><span style='color:#FF0000;'>In order to receive the required amount of instruction time as required by the MOL standard, participants will need to be present and participate through the entire day at the times scheduled. If you will need to miss more than 15 minutes per day, we encourage you to select an alternate date by contacting PSHSA's Customer Services at 416-250-2131 (Toll Free: 1-877-250-7444) or</span> <span style='text-decoration: underline;'>customerservice@pshsa.ca</span></li></ul></p>";
		
		$msg .= "<p><b>Optional requirements for the Classroom Session:</b><br>";
		$msg .= "<ul><li>Participants can complete a 'PSHSA Learner Consent form' (attached) ahead of the session; it must be completed at the start of ";
		$msg .= "the session otherwise.</li></ul></p>";

		$msg .= "<p><b>To Become Certified:</b> To become fully certified JHSC members, participants successfully complete both the JHSC ";
		$msg .= "Certification Part 1 Training Program and JHSC Certification Part 2 (Sector-Specific or Workplace-Specific hazard training).</p>";
		
		$msg .= "<p><b>Food and Beverages:</b><br>We do not provide food with training, so please bring your own lunch/snacks. All training materials";
		$msg .= " will be provided to you upon arrival.</p>";
		
		$msg .= "<p><b>Special Requests:</b><br> Public Services Health and Safety Association is committed to providing accessible services. ";
		$msg .= "We encourage you to voluntarily self-identify if you require any form of enhanced accessibility. Any such disclosure is ";
		$msg .= "confidential, in accordance with the Freedom of Information and Protection of Privacy Act. If you require assistance, please contact ";
		$msg .= "<span style='text-decoration: underline;'>AODA@pshsa.ca</span> or 416-250-2134. A member of our team will contact you.</p>";
		
		$msg .= "<p><b>Please note Cancellation Policy:</b> Cancellations will be received up to <span style='color:#FF0000;font-weight:bolder;'>7";
		$msg .= "</span> business days prior to the scheduled session. Transfers are accepted <span style='color:#FF0000;font-weight:bolder;'>7";
		$msg .= "</span> business days prior to the scheduled session.</p>";  
		
		$msg .= "<p><b>In the event of inclement weather, the scheduled training session may be cancelled. You will receive notification the day ";
		$msg .= "prior to the session providing the consultant's contact information with further instructions.</b></p>";
		
		$msg .= "<p>To eliminate health concerns arising from exposure to scented products please refrain from using or wearing scented products ";
		$msg .= "at all PSHSA training sessions.</p>";

		$msg .= "<p>If you have any questions please do not hesitate to contact PSHSA at the number below.</p>";
 		
		// Footer
		$msg .= "<br><p><img src='http://".$_SERVER["SERVER_NAME"]."/wp-content/uploads/2015/11/pshsa_logo.jpg' alt='PSHSA Logo' width='120' height='25' />";
		$msg .= "<br><br><span style='color:#FF0000;'>T: 416-250-2131 Toll Free: 1-877-250-7444</span><br>customerservice@pshsa.ca<br><hr></p>";
		
		$is_email_sent = wp_mail($to, $subject, $msg, $headers, $attachments);
		return $is_email_sent;
	}
		
	// Send a confirmation email to the student with the new Course booking info for the blended course booked in-class session WAH courses only
	function send_student_booking_Blended_WAH_confirmation_email($student_full_name, $student_email, $session_id, $course_title, $parent_course_title){
		
		$is_email_sent = false;
		$ccteamEmail   = 'omohamed@pshsa.ca';
		$to            = array($student_email);
		$subject       = $parent_course_title." Classroom Registration Confirmation & Requirements";
		$headers       = array('Content-Type: text/html; charset=UTF-8;', 'Bcc: '.$ccteamEmail);  //Switch to HTML
 		$attachments   = array(WP_CONTENT_DIR . '/uploads/2019/05/Learner-Consent-Form.pdf');

		$msg  = "<h2>Thank you for registering!</h2>";
		$msg .= "<p>Hello ".stripslashes($student_full_name).".</p>"; 
		
		$msg .= "<p>You have successfully registered for:<br>";  
		$msg .= "<span style='color:#3366CC;font-weight:bolder;'>".$course_title." Classroom Module (Part 2)</span></p>";
		$msg .= "<p><b>Date:</b> " . date('M d, Y', strtotime(implode(" ", get_course_attributes($session_id, 'pa_start-date'))));
		$msg .= "<br><b>Time:</b> " . implode(" ", get_course_attributes($session_id, 'pa_time'));
		$msg .= "<br><b>Venue:</b> " . implode(" ", get_course_attributes($session_id, 'pa_session-info'));
		$msg .= "<br><b>Address:</b> " . implode(" ", get_course_attributes($session_id, 'pa_course-location'))."</p>";
 			
		$msg .= "<p><b>About this course:</b>To become certified to work at heights, participants must successfully complete the full ".$parent_course_title." Training ";
		$msg .= "Program and both the hands-on and written evaluations administered at the end of the Classroom Module (Part 2). These ";
		$msg .= "evaluations will test on knowledge and skills obtained in both the eLearning module and the classroom module. To help you ";
		$msg .= "prepare for the in-class session, please find attached the <span style='text-decoration: underline;'><b>";
		$msg .= "Working at Heights Refresher Package</b></span>, which you are encouraged to review.</p>";
		
		$msg .= "<p><b>Mandatory requirements for the Classroom Session:</b> To take part in training:<br><ul>";
		$msg .= "<li>All registered participants <span style='text-decoration: underline;'>must</span> be present at the start of the session. ";
		$msg .= "Any registered participant who is more than <span style='text-decoration: underline;'>15 minutes late will not be permitted";
		$msg .= "</span> to attend the session.</li><li>Participants <span style='text-decoration: underline;'>must</span> bring a piece of ";
		$msg .= "government-issued photo ID (such as a Driver's License or a Health Card) with them to the session.</li>";
		$msg .= "<li>Participants <span style='text-decoration: underline;'>must</span> bring CSA-certified PPE which includes safety footwear, ";
		$msg .= "hard hat and protective eyewear to the session.</li></ul></p>";
		
		$msg .= "<p><b>Optional requirements for the Classroom Session:</b><br><ul>"; 
		$msg .= "<li>Participants may choose to work with their own personal fall protective equipment - harness and lanyard, and can bring these ";
		$msg .= "to the session.</li><li>Participants may use their own gloves to handle equipment, and can bring their own to the session.</li>";
		$msg .= "<li>Participants can complete a 'PSHSA Learner Consent form' (attached) ahead of the session; it must be completed at the start of ";
		$msg .= "the session otherwise.</li></ul></p>";

		$msg .= "<p>Be advised that your PPE that is to be worn and used during the training must be in good working condition and meet all ";
		$msg .= "necessary manufacturer and regulatory requirements. If you do not have appropriate equipment please contact us in advance ";
		$msg .= "so we can make arrangements for you to borrow some of PSHSA's equipment for that session, or to reschedule for another date.</p>";
		
		$msg .= "<p>Please visit our website http://".$_SERVER["SERVER_NAME"]."/working-at-heights-sign-up/ for helpful videos and sample checklists";
		$msg .= " that can be used for inspecting and donning and doffing fall protection equipment.</p>";

		$msg .= "<p>For any questions regarding ".$parent_course_title." training, you can contact us at ";
		$msg .= "<span style='text-decoration: underline;'>workingatheights@pshsa.ca</span></p>";

		$msg .= "<p><b>Food and Beverages:</b> We do not provide food with training, so please bring your own lunch/snacks. All training materials";
		$msg .= " will be provided to you upon arrival.</p>";

		$msg .= "<p><b>Special Requests:</b> Public Services Health and Safety Association is committed to providing accessible services. ";
		$msg .= "We encourage you to voluntarily self-identify if you require any form of enhanced accessibility. Any such disclosure is ";
		$msg .= "confidential, in accordance with the Freedom of Information and Protection of Privacy Act. If you require assistance, please contact ";
		$msg .= "<span style='text-decoration: underline;'>AODA@pshsa.ca</span> or 416-250-2134. A member of our team will contact you.</p>";
		
		$msg .= "<p><b>Please note Cancellation Policy:</b> Cancellations will be received up to <span style='color:#FF0000;font-weight:bolder;'>7";
		$msg .= "</span> business days prior to the scheduled session. Transfers are accepted <span style='color:#FF0000;font-weight:bolder;'>7";
		$msg .= "</span> business days prior to the scheduled session.</p>";  

		$msg .= "<p><b>In the event of inclement weather, the scheduled training session may be cancelled. You will receive notification the day ";
		$msg .= "prior to the session providing the consultant's contact information with further instructions.</b></p>";

		$msg .= "<p>To eliminate health concerns arising from exposure to scented products please refrain from using or wearing scented products at ";
		$msg .= "all PSHSA training sessions.</p>";
 
		$msg .= "<p>If you have any questions please do not hesitate to contact PSHSA at the number below.</p>";
 		
		// Footer
		$msg .= "<br><p><img src='http://".$_SERVER["SERVER_NAME"]."/wp-content/uploads/2015/11/pshsa_logo.jpg' alt='PSHSA Logo' width='120' height='25' />";
		$msg .= "<br><br><span style='color:#FF0000;'>T: 416-250-2131 Toll Free: 1-877-250-7444</span><br>customerservice@pshsa.ca<br><hr></p>";
		
		$is_email_sent = wp_mail($to, $subject, $msg, $headers, $attachments);
		return $is_email_sent;
	}
	
	// Send a confirmation email to the student with the new Course booking info for the blended course booked in-class session HSMS courses only
	function send_student_booking_Blended_HSMS_confirmation_email($student_full_name, $student_email, $session_id, $course_title, $parent_course_title){
		
		$is_email_sent = false;
		$ccteamEmail   = 'omohamed@pshsa.ca';
		$to            = array($student_email);
		$subject       = $parent_course_title." Classroom Registration Confirmation & Requirements";
		$headers       = array('Content-Type: text/html; charset=UTF-8;', 'Bcc: '.$ccteamEmail);  //Switch to HTML
 		$attachments   = array();
 			
		$msg  = "<h2>Thank you for registering!</h2>";
		$msg .= "<p>Hello ".stripslashes($student_full_name).".</p>"; 
		 
		$msg .= "<p>You have successfully registered for:<br>";
		$msg .= "<span style='color:#3366CC;font-weight:bolder;'>" . $course_title . " - Classroom Session</span></p>";
		$msg .= "<p><b>Date:</b> " . date('M d, Y', strtotime(implode(" ", get_course_attributes($session_id, 'pa_start-date'))));
		$msg .= "<br><b>Time:</b> " . implode(" ", get_course_attributes($session_id, 'pa_time'));
		$msg .= "<br><b>Venue:</b> " . implode(" ", get_course_attributes($session_id, 'pa_session-info'));
		$msg .= "<br><b>Address:</b> " . implode(" ", get_course_attributes($session_id, 'pa_course-location')) . "</p>";
 			
		$msg .= "<p><b>About this course:</b><br>Now that you have completed the eLearning portion of the course, you are ready for Part 2: Classroom ";
		$msg .= "training. The classroom session builds on the eLearning and explores the concept of moving beyond compliance - from a supervisor to ";
		$msg .= "a health and safety leader and role model. It provides information and resources to aid in the development of critical thinking ";
		$msg .= "skills and better understanding of the supervisor's role as it relates to the OHSA.</p>";
		
		$msg .= "<p style='color:#3366CC;font-weight:bolder;'>Classroom course duration: 1 day (7.5 hours)</p>";
		$msg .= "<p style='color:#808080;font-weight:bolder;'>Upon completion of this program, you will be able to:</p>";
		
		$msg .= "<p><ul><li>Recognize OHS legislation with recall of key concepts covered in the eLearning module</li>";
		$msg .= "<li>Improve knowledge of supervisory roles and responsibilities and how this links to your workers, employer and workplace</li>";
		$msg .= "<li>Explore emergency preparedness and the key steps of an emergency response plan</li>";
		$msg .= "<li>Apply RACE/PEMEP to real life workplace examples (case studies)</li>";
		$msg .= "<li>Explore how to prevent and respond to incidents in the workplace</li>";
		$msg .= "<li>Understand the importance of health and safety culture</li>";
		$msg .= "<li>Identify leadership traits and practices that can contribute to making your workplace healthy and safe</li>";
		$msg .= "<li>Learn about practices and resources to assist with moving beyond compliance</li></ul></p>";
		
		$msg .= "<p><b>Food and Beverages:</b> We do not provide food/beverages with training, so please bring your own lunch/snacks. All training ";
		$msg .= "materials will be provided to you upon arrival.</p>";
		
		$msg .= "<p><b>Special Requests:</b> Public Services Health and Safety Association is committed to providing accessible services. ";
		$msg .= "We encourage you to voluntarily self-identify if you require any form of enhanced accessibility. Any such disclosure is ";
		$msg .= "confidential, in accordance with the <i>Freedom of Information and Protection of Privacy Act.</i> If you require assistance, please contact ";
		$msg .= "<span style='text-decoration: underline;'>AODA@pshsa.ca</span> or 416-250-2134. A member of our team will contact you.</p>";
		
		$msg .= "<p><b>Please note Cancellation Policy:</b> Cancellations will be received up to <span style='color:#FF0000;font-weight:bolder;'>7";
		$msg .= "</span> business days prior to the scheduled session. Transfers are accepted <span style='color:#FF0000;font-weight:bolder;'>7";
		$msg .= "</span> business days prior to the scheduled session.</p>";
		
		$msg .= "<p><b>In the event of inclement weather, the scheduled training session may be cancelled. You will receive notification the day ";
		$msg .= "prior to the session providing the consultant's contact information with further instructions.</b></p>";

		$msg .= "<p>To eliminate health concerns arising from exposure to scented products please refrain from using or wearing scented products at ";
		$msg .= "all PSHSA training sessions.</p>";
 
		$msg .= "<p>If you have any questions please do not hesitate to contact PSHSA at the number below.</p>";
 		
		// Footer
		$msg .= "<br><p><img src='http://".$_SERVER["SERVER_NAME"]."/wp-content/uploads/2015/11/pshsa_logo.jpg' alt='PSHSA Logo' width='120' height='25' />";
		$msg .= "<br><br><span style='color:#FF0000;'>T: 416-250-2131 Toll Free: 1-877-250-7444</span><br>customerservice@pshsa.ca<br><hr></p>";
		
		$is_email_sent = wp_mail($to, $subject, $msg, $headers, $attachments);
		return $is_email_sent;
	}
	
	/*
	 **
	 * Fetch all the terms/attributes for the course
	*/
	function get_course_attributes($product_id, $attribute){
		$term_values_arr = array();
		$term_values     = get_the_terms( $product_id, $attribute );
		
		if ( $term_values && ! is_wp_error($term_values) ) :
			foreach($term_values as $oneterm){
				$term_values_arr[] = $oneterm->name;
			}
		endif;
		
		return $term_values_arr;
	}
	
    // Custom tracking code added by Omar based on Emad's/Laurraine request
    add_action( 'woocommerce_thankyou', 'my_custom_tracking' );
    
    function my_custom_tracking( $order_id ) {
		$order = wc_get_order( $order_id );
		echo '<img src="https://ads.eqads.com/a.aspx?o=20362&st=1000" width="1" height="1" style="display:none" >';
    }

    // Set sidebar default location to left. See post here: http://support.dream-theme.com/knowledgebase/change-default-sidebar-position/
    function dt_change_default_sidebar() {

    	global $DT_META_BOXES;

    	if ( $DT_META_BOXES ) {
	        if ( isset($DT_META_BOXES[ 'dt_page_box-sidebar' ]) ) {
	        	$DT_META_BOXES[ 'dt_page_box-sidebar' ]['fields'][0]['std'] = 'left';
	        }
        }
    }
  
    add_action( 'admin_init', 'dt_change_default_sidebar', 9 );  
    
    // Fetch affiliate_id and save it to cookie and send it as an Event to GA
	//add_action( 'woocommerce_after_shop_loop_item', 'get_affiliate_id' );
	add_action('woocommerce_after_add_to_cart_form', 'get_affiliate_id');
	function get_affiliate_id(){

		// https: //www.pshsa.ca/?affiliate_id=ontario-market-place&product_id=1
		if (isset($_GET['affiliate_id']) && $_GET['affiliate_id'] != '' && isset($_GET['product_id']) && $_GET['product_id'] != ''):

			$product = wc_get_product($_GET['product_id']);

			if (!$product instanceof WC_Product) {
				return;
			}

			// Create cookie with 3 years expiration period for the affiliate ID
			setcookie('affiliate_id', trim($_GET['affiliate_id']), time() + 60 * 60 * 24 * 30 * 12 * 3, COOKIEPATH, COOKIE_DOMAIN, false);

			if (isset($_COOKIE['affiliate_id']) && $_COOKIE['affiliate_id'] != ''):
				$ga_data = $product->get_title() . " referred from " . $_GET['affiliate_id'] . " is viewed";?>
				<script>__gaTracker('send', 'event', 'Products', 'Affiliate Impression', '<?php echo $ga_data; ?>');</script>
			<?php endif;
		endif;
	}
	
    // Override the check out fields for different course types
	add_filter( 'woocommerce_checkout_fields' , 'custom_override_checkout_fields' );
	
	function custom_override_checkout_fields( $fields ){
		
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {	
			$termTrainingValues = array();
			$termTrainingValues = get_course_attributes($cart_item['product_id'], 'pa_training');
			 
			if( in_array('molextended', $termTrainingValues) ){ // MOL Extended courses
				//echo "<br><br>".$cart_item['product_id'].$cart_item['data]."<br>";
				add_action( 'woocommerce_after_order_notes', 'custom_after_order_notes_fields' );
				return get_new_checkout_fields($fields, 'molextended');
			}
			else{
				return $fields;
			}
		}
	}
	
	// Get the new check out fields and reorder them depending on the course type
	function get_new_checkout_fields($fields, $course_type){
	
		$new_ordered_fields = array();
		
		if ( $course_type == 'molextended' ){
			
			// Make the company field required
			$fields['billing']['billing_company']['required'] = true;
			
			// Add a new company box office field as optional field
			$fields['billing']['billing_company_box_office'] = array(
				'type'        => 'text',
		        'label'       => __('P.O Box', 'woocommerce'),
			    'placeholder' => _x('P.O Box', 'placeholder', 'woocommerce'),
			    'required'    => false,
			    'class'       => array('form-row-wide'),
			    'clear'       => true
		    );
		    
			// Add a new street number field as required field
			$fields['billing']['billing_street_number'] = array(
				'type'        => 'text',
		        'label'       => __('Street number', 'woocommerce'),
			    'placeholder' => _x('Street number', 'placeholder', 'woocommerce'),
			    'required'    => true,
			    'class'       => array('form-row-wide'),
			    'clear'       => true
		    );
	    	 
		    // Change the street address field label and place holder
		    $fields['billing']['billing_address_1']['label']       = 'Street name';
		    $fields['billing']['billing_address_1']['placeholder'] = 'Street name';
    	}
    	
    	// Reorder the checkout fields 
		$new_ordered_fields['billing']['billing_first_name']         = $fields['billing']['billing_first_name'];
        $new_ordered_fields['billing']['billing_last_name']          = $fields['billing']['billing_last_name'];
        $new_ordered_fields['billing']['billing_company']            = $fields['billing']['billing_company'];
        $new_ordered_fields['billing']['billing_email']              = $fields['billing']['billing_email'];
        $new_ordered_fields['billing']['billing_phone']              = $fields['billing']['billing_phone'];
        $new_ordered_fields['billing']['billing_country']            = $fields['billing']['billing_country'];
        $new_ordered_fields['billing']['billing_street_number']      = $fields['billing']['billing_street_number'];
        $new_ordered_fields['billing']['billing_address_1']          = $fields['billing']['billing_address_1'];
        $new_ordered_fields['billing']['billing_address_2']          = $fields['billing']['billing_address_2'];
        $new_ordered_fields['billing']['billing_city']               = $fields['billing']['billing_city'];
        $new_ordered_fields['billing']['billing_state']              = $fields['billing']['billing_state'];
        $new_ordered_fields['billing']['billing_company_box_office'] = $fields['billing']['billing_company_box_office'];
        $new_ordered_fields['billing']['billing_postcode']           = $fields['billing']['billing_postcode'];
        $new_ordered_fields['shipping']                              = $fields['shipping'];  // same order
        $new_ordered_fields['account']                               = $fields['account'];	 // same order
        $new_ordered_fields['order']                                 = $fields['order'];	 // same order
    	
	    return $new_ordered_fields;	    	
	}
	
	// Add a desclaimer for molextended courses only
	function custom_after_order_notes_fields($checkout){
		?>
		<div style='border:2px solid #7A2531;padding:3%;margin:5% 0 5% 0;'>
		<p>The Ministry of Labour is collecting the personal information under the authority of section 7.5 of the Occupational Health and Safety Act 
		and in compliance with the Freedom of Information and Protection of Privacy Act S.38(2).  This information is collected in order to maintain a 
		record of your successful completion of an approved training program and will not be disclosed or used for any other purpose unless expressly 
		required by law.</p>
		
		<p>As an approved Ministry of Labour Training Provider, PSHSA is requesting this information on behalf of the Ministry for the purpose of meeting 
		MOL documentation requirements.</p>
		</div>
	<?php 		
	}
	
	// cart updated Hook
	add_action( 'woocommerce_cart_updated', 'woocommerce_add_to_cart_custom_fn');

	function woocommerce_add_to_cart_custom_fn(){
		check_for_product_discounts_in_cart(WC()->cart);
	}
	
	// Look for any special discounted products in the cart
	function check_for_product_discounts_in_cart($my_cart) {
		$sales_type_indicator_free_products_in_cart = array();
		$cart_items_quantities                      = $my_cart->get_cart_item_quantities();
		
		foreach ( $my_cart->get_cart() as $cart_item_key => $cart_item ) {
			
			// Look for blended products in the cart that have the sales-type-indicator-free training attribute
			if( in_array('sales-type-indicator-free', get_course_attributes($cart_item['product_id'], 'pa_training')) &&
				in_array('blended', get_course_attributes($cart_item['product_id'], 'pa_training')) ){
				$sales_type_indicator_free_products_in_cart[$cart_item['product_id']] = $cart_item_key;
				
				 // Set the in class blended try it product to new price
				if ( in_array('In-class Training', get_course_attributes($cart_item['product_id'], 'pa_training')) ){   
					$cart_item['data']->price = get_post_meta($cart_item['product_id'], 'product_in_class_price', true);		
				}
			}
		}
		
		// only one blended product that has the training attribute: sales-type-indicator-free can be added to the same cart
		foreach($sales_type_indicator_free_products_in_cart as $k => $v){
			//echo "<br>sales_type_indicator_free_products_in_cart: ".$k.': '.$v.", quantity: ".$cart_items_quantities[$k];
			
			if ($cart_items_quantities[$k] > 1) {	
				$my_cart->set_quantity($v, 1, true);
				add_filter( 'wc_add_to_cart_message', '__return_empty_string' ); 
				wc_add_notice( 'You may have <b>no more than 1</b> '.wc_get_product($k)->get_title().' added to the same cart. Only <b>1</b> product added.', 'error' );
			}
		}
		
	}
	
	// Check for the product category
	function fns_woocommerce_check_for_product_category($postID, $lookForCategory){
		$found = false;
		
		$productCategories = wp_get_post_terms( $postID, 'product_cat' );
		
		foreach ($productCategories as $k => $v) :
			if ( $v->slug == 'elearning' && $lookForCategory == 'elearning' ) :                
				$found = true;
			elseif ( $v->slug == 'jhsc-certification-part-1' && $lookForCategory == 'jhsc-certification-part-1' ) :                
				$found = true;
			elseif ( $v->slug == 'jhsc-certification-part-2' && $lookForCategory == 'jhsc-certification-part-2' ) :                
				$found = true;
			endif;
		endforeach;
		
		return $found;
	}
	
	// When woo commerce order is cancelled from the back end cancel all students status and update the product inventory levels in this order 
	add_action( 'woocommerce_order_status_cancelled', 'wrc_order_status_cancelled_update_inventory', 10, 1);
	
	function wrc_order_status_cancelled_update_inventory($order_id) {
	
		if ( $order_id != '' ){   
			    
			global $wpdb, $woocommerce;	
				
			$order = wc_get_order( $order_id );
			
			$update = $wpdb->update( 
				'ckv_student_info', 
			 	array('studentStatus' => 'cancelled'), 
			 	array('OrderID' => $order_id), 
			 	array('%s'), 
			 	array('%d') 
		    ); 
		
			if (false === $update ){
				$order->add_order_note( __( 'Error! Student(s) status is not updated for Order ID: '.$order_id, 'woocommerce-checkout-registration' ) );
			}
			else{
				$order->add_order_note( __( 'Student(s) status is successfully updated to: cancel for Order ID: '.$order_id, 'woocommerce-checkout-registration' ) );	
				
				$studentInfo = $wpdb->get_results( "SELECT * FROM `ckv_student_info` WHERE OrderID = ".$order_id );	
	
				// Update the inventory levels for each product in the student order if the stock inventory level management is enabled
				foreach ($studentInfo as $k =>$v){
					if ( $v->CourseID > 0 && wc_update_product_stock( wc_get_product( $v->CourseID ), 1, 'increase' ) && wc_get_product( $v->CourseID )->managing_stock() ){
						$order->add_order_note( __( 'Product: '.$v->CourseID.' stock level is now increased by 1, current stock level is: '. 
							wc_get_product( $v->CourseID )->get_stock_quantity(), 'woocommerce-checkout-registration' ) );
					}
				}
			}
		}
	}
	
	// Force the user to login to the backend only for the Dev and staging sites and exclude the CRM API scripts
	add_action('init','force_user_login');	
	
	function force_user_login(){
		$url_dev     = 'dev.pshsa.ca';
		$url_staging = 'staging.pshsa.ca';
		
	    if ( !is_user_logged_in() && !in_array( $GLOBALS['pagenow'], 
	    	array( 'wp-login.php', 'wp-register.php', 'create-simple-product.php', 'update-product.php', 'change-status-product.php', 'transfer-student-order.php', 'cancel-student-order' ) ) && 
	    	( $_SERVER['HTTP_HOST'] == $url_dev || $_SERVER['HTTP_HOST'] == $url_staging ) ) {	
            wp_safe_redirect(wp_login_url(), 301);
            exit; 
	    }
	}
	
	// Hide the products search top panel only for the traning category page to remove the CSS before rule that display 'Your Filters' text
	add_action('init','hide_woof_products_top_panel');
	
	function hide_woof_products_top_panel(){
	 
        //Parse the url to get the product filter custom attribute parameters
		parse_str($_SERVER['QUERY_STRING'], $output);
		
		foreach($output as $k=>$v):
			if (preg_match("/^pa_/i", $k)):
				$arr_params [$k] = $v;
			endif;
		endforeach;
		
		if ( sizeof($arr_params) == 1 && $arr_params['pa_training-category'] == 'training-cat' ){ ?>
			<style> div.woof_products_top_panel { display: none !important; } </style> <?php 
		} 
	}
	
	// WC Membership customization
	function sv_members_area_products_table_columns( $columns ) {
	    if ( isset( $columns['membership-product-accessible'] ) ) {
	        unset( $columns['membership-product-accessible'] );
	    }
	        $new_columns = array();
	    
	    foreach( $columns as $column_id => $column_name ) {
	    
	        $new_columns[$column_id] = $column_name;
	        
	        // insert our new column after the "Title" column
	        if ( 'membership-product-title' === $column_id ) {
	            $new_columns['membership-product-sku'] = __( 'SKU', 'my-theme-text-domain' );
	        }
	    }
	    
	    return $new_columns;
	}
	add_filter('wc_memberships_members_area_my_membership_products_column_names', 'sv_members_area_products_table_columns', 10, 1 );
	
	// Fills the "SKU" column with the sku
	function sv_members_area_product_sku( $product ) {
	
	    echo $product->get_sku();
	}
	add_action( 'wc_memberships_members_area_my_membership_products_column_membership-product-sku', 'sv_members_area_product_sku' );
	
	// Remove Query Strings from Static Resources
	function _remove_script_version( $src ){
		$parts = explode( '?ver', $src );
		return $parts[0];
	}
	
	add_filter( 'script_loader_src', '_remove_script_version', 15, 1 );
	add_filter( 'style_loader_src', '_remove_script_version', 15, 1 );

	// Display custom text only for Beyond Silence product to let the customer apply the 50% discount coupon
	function woocommerce_cart_item_name_custom_fn( $product_name , $cart_item, $cart_item_key ){		
		$custom_text       = '';
		$sku_parent        = 'SPYKTAEN0418';
		$parent_product_id = get_post_meta($cart_item['product_id'], 'product_custom_parent_id', true);
			
		if ( $parent_product_id && wc_get_product_id_by_sku( $sku_parent ) && wc_get_product_id_by_sku( $sku_parent ) == $parent_product_id ):
			$custom_text   = '<p style="font-size:12px;font-style:italic;">If you are a small healthcare workplace (100 employees or less), ';
			$custom_text  .= 'enter "SmallBusiness" in the Coupon Code section upon check out. The coupon will apply a 50% discount</p>.';
			$product_name .= $custom_text;
		endif;	
		
		return $product_name;	
	}
	add_filter( 'woocommerce_cart_item_name', 'woocommerce_cart_item_name_custom_fn', 10, 3 );
	
	// Change add to cart message only for certain products in the cart 
	function custom_wc_add_to_cart_message_html( $message, $products ) { 
		
		$cert1_products = array();
		
		foreach ( $products as $product_id => $qty ) {
			if ( fns_woocommerce_check_for_product_category($product_id, 'jhsc-certification-part-1') ) {
				$cert1_products [$product_id] = 'jhsc-certification-part-1';
			}
		}
		
		if ( sizeof($cert1_products) > 0 ){
			$message .= '<br><a href="/shop/?swoof=1&pa_training-category=training-cat&pa_all-courses=certification-part-2" target="_blank"><b>Click here</b></a>'; 
			$message .= ' to add Certification Part 2 and take advantage of our BUNDLED OFFER of $700 (plus HST) for Certification 1 and 2 together.';
		}
			
	    return $message; 
	} 
	add_filter( 'wc_add_to_cart_message_html', 'custom_wc_add_to_cart_message_html', 10, 2 );
	
	// try
	/**
	 * Add a 1% surcharge to your cart / checkout
	 * change the $percentage to set the surcharge to a value to suit
	 */
	/* add_action( 'woocommerce_cart_calculate_fees','woocommerce_custom_surcharge' );
	function woocommerce_custom_surcharge() {
	global $woocommerce;

		if ( is_admin() && ! defined( 'DOING_AJAX' ) )
			return;

		$percentage = 0.01;
		$surcharge = -500;	
		$woocommerce->cart->add_fee( 'Surcharge', $surcharge, true, '' );

	} */

?>