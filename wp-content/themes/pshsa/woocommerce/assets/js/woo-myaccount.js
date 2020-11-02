
/*
 * Custom js script to book the blended courses session using WP Ajax call
 * Author: Omar M.
*/


//Select the session
function SelectSession(radioBtn, countBlended){
	jQuery('input#selectedsessionID_' + countBlended).val(radioBtn.value);
}

//Book the session
function BookSession(InfoID, CourseID, userEmail, userFullName, orderID, countBlended){	
	
	//The student has to select a session first
	if (jQuery('input#selectedsessionID_' + countBlended).val() === ''){
		alert('You must select a session.');
	}
	else{

		jQuery('img#ajaxprocessing_' + jQuery('input#selectedsessionID_' + countBlended).val()).css('display', 'inline');
		
		jQuery.ajax({	 
		     type: "POST",
		     url: booksession_Ajax.ajaxurl, 
		     data: { 
			     action:      "ajax-booksessionSubmit",
			     'InfoID':    InfoID,
		 		 'CourseID':  CourseID,
		 		 'userEmail': userEmail,
		 		 'FullName':  userFullName,
		 		 'OrderID':   orderID,
		 		 'sessionID': jQuery('input#selectedsessionID_' + countBlended).val(),
		 		 'dataType':  'json',
		 		 'nextNonce': booksession_Ajax.nextNonce
			 },
		     success: function(response){ 
		         reBuildPageAjax(countBlended);
		         jQuery( 'span#iscoursebookedspan_'  + jQuery('input#selectedsessionID_' + countBlended).val() ).text('Yes');
		         jQuery( 'img#ajaxprocessing_'       + jQuery('input#selectedsessionID_' + countBlended).val() ).css('display', 'none');
		         jQuery( 'span#coursebookedspanmsg_' + jQuery('input#selectedsessionID_' + countBlended).val() ).html('You have successfully booked this session.<br>A confirmation email has been sent to: ' + userEmail);
 		         jQuery( 'span#coursebookedspanmsg_' + jQuery('input#selectedsessionID_' + countBlended).val() ).css('display', 'inline');
		         jQuery( 'span#coursebookedspanmsg_' + jQuery('input#selectedsessionID_' + countBlended).val() ).css('font-size', '11px');
 		         jQuery( 'span#coursebookedspanmsg_' + jQuery('input#selectedsessionID_' + countBlended).val() ).css('font-weight', 'bold');
 		         jQuery( 'span#coursebookedspanmsg_' + jQuery('input#selectedsessionID_' + countBlended).val() ).fadeOut(15000, "linear");
		     },
		     error: function(MLHttpRequest, textStatus, errorThrown){  
		         alert('Error! session is not booked. please contact PSHSA customer service.');  
		     },
		     timeout: 120000  // 2 minutes time out
	   });
	}

} //end function	


//Reload the page with the new booking session availability after the DB Ajax call
function reBuildPageAjax(countBlended){
	
	jQuery('td#tblcell_' + countBlended).children().each(function( i ) {
		jQuery(this).text('No');
	});
	
	//Loop through all the span elements and set them all to No
	//jQuery('span#' + sessionID + ' .iscoursebookedspancls').each(function( i ) {
		//jQuery(this).text('No');
	//});	
}
	

	
