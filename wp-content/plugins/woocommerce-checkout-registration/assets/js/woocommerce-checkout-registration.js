jQuery(document).ready(function($) {
    /* //Omar
    // Load them in the fields when page is loaded.
    $( 'input[id="student[0][first_name"]' ).val( $( '#billing_first_name' ).val() );
    $( 'input[id="student[0][last_name"]' ).val( $( '#billing_last_name' ).val() );
    $( 'input[id="student[0][email]"]' ).val( $( '#billing_email' ).val() );


    // When focusing off billing fields
    $( '#billing_first_name' ).focusout( function() {
    	$( 'input[id="student[0][first_name]"]' ).val( $( '#billing_first_name' ).val() );
    });
    $( '#billing_last_name' ).focusout( function() {
    	$( 'input[id="student[0][last_name]"]' ).val( $( '#billing_last_name' ).val() );
    });
    $( '#billing_email' ).focusout( function() {
    	$( 'input[id="student[0][email]"]' ).val( $( '#billing_email' ).val() );
    });
    */

    //Set the section headers
    //$('div.participantdivcls').css({'border-radius' : '10px' , 'border' : '1px solid #7A2531', 'padding' : '20px'});

    //checkForChainedProducts( $.parseJSON($('#chained_products').val()));
    
    // Show hide toggle for organization and student data
    $('.toggle').on('click', function(e) {
        var $this = $(this),
            $type = $this.data('type');  // this will tell us what type of section we have, either Orginization, or Student          

        if ($type === 'stu') { // student show/hide
            if ($this.hasClass('closed')) {
                $this.removeClass('closed').addClass('open').siblings('.courseparticipantcls').show();
            } else {
                $this.removeClass('open').addClass('closed').siblings('.courseparticipantcls').hide();
            }
        }
    });
 

    // Re-validate the check out form fields when form submit
    $('form[name="checkout"]').submit(function(event) {
                                 
    	//checkForChainedProducts( $.parseJSON($('#chained_products').val()) );
 
        var pattern = new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i);
 
        //I: Blended Course/molextended: Students section fields
        //*******************************************************
        var errorbstudent = [];
        var errorbstudentU = [];

        //Students text fields			
        $('p.studentbcls').each(function(index) {
            if ($(this).children().val() === '') {
                errorbstudent.push($(this).parent().attr('id'));
            }
        });

        //Students email fields
        $('p.studentemailbcls').each(function(index) {
            if (!pattern.test($(this).children().val())) {
                errorbstudent.push($(this).parent().attr('id'));
            }
        });

        errorbstudentU = unique(errorbstudent);

        // Expand the error sections
        for (var i in errorbstudentU) {
	        if ($('h2#' + errorbstudentU[i] + '.togglesectionblendedcls').hasClass('closed')) {
                $('h2#' + errorbstudentU[i] + '.togglesectionblendedcls').removeClass('closed').addClass('open').siblings('.courseparticipantcls').show();
            } 
            //$('h2#' + errorbstudentU[i] + '.togglesectionblendedcls').removeClass().addClass('open');
            $('div#' + errorbstudentU[i] + '.courseparticipantcls').show();
            $('span#s_' + errorbstudentU[i] + '.participantspancls').html('&#8679;');
        }

        //II: Regular Course: Students section fields
        //*****************************************************
        var errorstudent = [];
        var errorstudentU = [];

        //Students text fields			
        $('p.studentcls').each(function(index) {
            if ($(this).children().val() === '') {
                errorstudent.push($(this).parent().attr('id'));
            }
        });

        //Students email fields
        $('p.studentemailcls').each(function(index) {
            if (!pattern.test($(this).children().val())) {
                errorstudent.push($(this).parent().attr('id'));
            }
        });

        errorstudentU = unique(errorstudent);

        // Expand the error sections
        for (var i in errorstudentU) {
	        if ($('h2#' + errorstudentU[i] + '.togglesectioncls').hasClass('closed')) {
                $('h2#' + errorstudentU[i] + '.togglesectioncls').removeClass('closed').addClass('open').siblings('.courseparticipantcls').show();
            } 
            //$('h2#' + errorstudentU[i] + '.togglesectioncls').removeClass().addClass('open');
            $('div#' + errorstudentU[i] + '.courseparticipantcls').show();
            $('span#s_' + errorstudentU[i] + '.participantspancls').html('&#8679;');
        }

    }); //End submit()

    // Loop through all the elements to copy the billing section info into the corresponding student section info only for molextended courses
    $('.billingmolextendedcopycls').each(function(index) {
        var ID = $(this).parent().attr('id');
        
        $(this).children().click(function() {
            if ($(this).children().is(":checked")) {
                copyBillingInfo(ID, getBillingFieldsInfo(), 'molextended');
            }
            else{
	            clearCoursesStudentInfo(ID, 'molextended');   
            }
        });
    });

    // Loop through all the elements to copy the billing section info into the corresponding student section info only for regular courses
    $('.billingregularcopycls').each(function(index) {
        var ID = $(this).parent().attr('id');

        $(this).children().click(function() {
            if ($(this).children().is(":checked")) {
                copyBillingInfo(ID, getBillingFieldsInfo(), 'regular');
            }
            else{
	         	clearCoursesStudentInfo(ID, 'regular');   
            }
        });
    });
    
    // Copy the billing info into the corresponding student section info
    function copyBillingInfo(ID, billingFieldsInfo, courseType) {
        
        // MOLextended courses
        if ( courseType === 'molextended') { 
	        $('div#' + ID + '.studentmolextendedcls').children().each(function(i) {
	            if ($(this).children().is("input") || $(this).children().is("select")) {
		            
	                if ( $(this).children().attr('id') === 'student[' + ID + '][first_name]' ){
	                	$(this).children().val(billingFieldsInfo['billing_first_name']);
	            	}
	     
	            	if ( $(this).children().attr('id') === 'student[' + ID + '][last_name]' ){
	                	$(this).children().val(billingFieldsInfo['billing_last_name']);
	            	}
	            	
	            	if ( $(this).children().attr('id') === 'student[' + ID + '][email]' ){
	                	$(this).children().val(billingFieldsInfo['billing_email']);
	            	}
	            	
	            	if ( $(this).children().attr('id') === 'student[' + ID + '][street_number]' ){
	                	$(this).children().val(billingFieldsInfo['billing_street_number']);
	            	}
	            	
	            	if ( $(this).children().attr('id') === 'student[' + ID + '][street]' ){
	                	$(this).children().val(billingFieldsInfo['billing_address_1'] + ' ' + billingFieldsInfo['billing_address_2']);
	            	}
	            	
	            	if ( $(this).children().attr('id') === 'student[' + ID + '][city]' ){
	                	$(this).children().val(billingFieldsInfo['billing_city']);
	            	}
	            	
	            	if ( $(this).children().attr('id') === 'student[' + ID + '][province]' ){
	                	$(this).children().val(billingFieldsInfo['billing_state']);
	            	}
	            	
	            	if ( $(this).children().attr('id') === 'student[' + ID + '][postal_code]' ){
	                	$(this).children().val(billingFieldsInfo['billing_postcode']);
	            	}
	            	
 	            	if ( $(this).children().attr('id') === 'student[' + ID + '][box_office]' ){
 	                	$(this).children().val(billingFieldsInfo['billing_box_office']);
 	            	}
	            	
	            	if ( $(this).children().attr('id') === 'student[' + ID + '][phone]' ){
	                	$(this).children().val(billingFieldsInfo['billing_phone']);
	            	}
	            	
	            	if ( $(this).children().attr('id') === 'student[' + ID + '][box_office]' ){
	                	$(this).children().val(billingFieldsInfo['billing_company_box_office']);
	            	}
	            	
	                $(this).children().attr("readonly", true);
	            }
	        });
    	} // end if
    	
    	// Regular courses
    	if ( courseType === 'regular') {
	    	$('div#' + ID + '.studentregularcls').children().each(function(i) {
	    	   	if ( $(this).children().attr('id') === 'student[' + ID + '][first_name]' ){
	            	$(this).children().val(billingFieldsInfo['billing_first_name']);
	        	}
	 
	        	if ( $(this).children().attr('id') === 'student[' + ID + '][last_name]' ){
	            	$(this).children().val(billingFieldsInfo['billing_last_name']);
	        	}
	        	
	        	if ( $(this).children().attr('id') === 'student[' + ID + '][email]' ){
	            	$(this).children().val(billingFieldsInfo['billing_email']);
	        	}
	        	
	        	$(this).children().attr("readonly", true);
        	});
    	} // end if  	
    }

    // Get all the billing fields info
    function getBillingFieldsInfo(){
	    var billingfieldsinfo = [];

        $("div.woocommerce-billing-fields").children().children().each(function(i) {
            if ($(this).is("p")) {
	            $.each($(this).children(), function(i, ele) { 
		            if ( $('#' + ele.id).is("input") || $('#' + ele.id).is("select") ){
				    	billingfieldsinfo[$('#' + ele.id).attr('id')] = $('#' + ele.id).val();
		    		}
				});
            }
        });
        
        return billingfieldsinfo;
    }
    
     //Clear the student section information
    function clearCoursesStudentInfo(ID, courseType) {
	    
	    // MOLextended courses
        if ( courseType === 'molextended') { 
	        $('div#' + ID + '.studentmolextendedcls').children().each(function(i) {
		    	if ($(this).children().is("input")) {
	                //$(this).children().val('');
	                $(this).children().removeAttr('readonly');
	           	}
	        });
    	} // end if
    	
    	// Regular courses
    	if ( courseType === 'regular') {
	    	$('div#' + ID + '.studentregularcls').children().each(function(i) {
	    	   	if ($(this).children().is("input")) {
	                //$(this).children().val('');
	                $(this).children().removeAttr('readonly');
	           	}
        	});
    	} // end if  	
    }
    
    // Helper Function
    var unique = function(origArr) {
        var newArr = [],
            origLen = origArr.length,
            found, x, y;

        for (x = 0; x < origLen; x++) {
            found = undefined;
            for (y = 0; y < newArr.length; y++) {
                if (origArr[x] === newArr[y]) {
                    found = true;
                    break;
                }
            }
            if (!found) {
                newArr.push(origArr[x]);
            }
        }
        return newArr;
   }
	
   
   /* Look for chained products, hide the child linked product, and copy parent linked product info to corresponding child linked product
   function checkForChainedProducts(chained_products){	   
	   console.log(chained_products);
		  
// 	   $.each( $('div.participantdivcls').children().children().children(), function (k,v) {  // Parent product 		       
// 		   if ($(v).is('input')){  // Child product  
// 			   if ( /first_name/i.test($(v).attr('id')) == true){ // First name 	      
// 	 			   console.log( 'parent first name: ' + $('div.participantdivcls').attr('data-product-id') + ', value: ' + $(v).val() + ': ' + $(v).attr('id') + ': ' + chained_products[$('div.participantdivcls').attr('data-product-id')] );
// 	 			   
// 	 			   $.each( $('input.bundledinputcls'), function (k2,v2) { 	 		
// 					   if ( (typeof chained_products[$(v).parent().parent().attr('id')] !== "undefined") && (chained_products[$(v).parent().parent().attr('id')] !== null) ) {
// 						   if ( $.inArray(parseInt($(v2).attr('data-product-id')), chained_products[$(v).parent().parent().attr('id')][$(v).parent().parent().parent().attr('data-product-id')]) != '-1' && /first_name/i.test($(v2).attr('id')) == true ){
// 							   console.log(chained_products[$(v).parent().parent().attr('id')][$(v).parent().parent().parent().attr('data-product-id')]);
// 		 			           //$(v2).val($('#student\\[' + $(v).parent().parent().attr('id') + '\\]\\[first_name\\]').val());
// 						   }
// 	 			   	   } 
// 		 		   });
// 			   }	    
//    	   } 	   	  
//    	 });
	   	   	 			
//     	$.each( $('div#' + $(v).attr('id').substring(2) + '.courseparticipantcls'), function(k2,v2) {
// 	    	$.each( $(v2).children().children(), function(k3,v3) {
// 		    	
// 		    	// look only for the text fields in the linked child product
// 		    	if ($(v3).is('input')){   	
// 		    		$.each( $("div[data-product-id='" + i + "']").children().children().children(), function(k4,v4) { 
// 			    		
// 		    			// look only for the text fields in the linked parent product
// 			    		if ($(v4).is('input')){   
// 			    			// First Name
// 		 			    	if ( /first_name/i.test($(v4).attr('id')) == true && /first_name/i.test($(v3).attr('id')) == true ){
// 			 			    	$(v3).val($(v4).val());
// 			 			    	//console.log('parent: ' + $(v4).attr('id') + ', value: ' + $(v4).val() + ' ,child: ' + $(v3).attr('id') + ', value: ' + $(v3).val());
// 		 			    	}
// 		 			    	// Last Name
// 		 			    	if ( /last_name/i.test($(v4).attr('id')) == true && /last_name/i.test($(v3).attr('id')) == true ){
// 			 			    	$(v3).val($(v4).val());
// 			 			    	//console.log('parent: ' + $(v4).attr('id') + ', value: ' + $(v4).val() + ' ,child: ' + $(v3).attr('id') + ', value: ' + $(v3).val());
// 		 			    	}
// 		 			    	// Email
// 		 			    	if ( /email/i.test($(v4).attr('id')) == true && /email/i.test($(v3).attr('id')) == true ){
// 			 			    	$(v3).val($(v4).val());
// 			 			    	//console.log('parent: ' + $(v4).attr('id') + ', value: ' + $(v4).val() + ' ,child: ' + $(v3).attr('id') + ', value: ' + $(v3).val());
// 		 			    	}
// 		    			}
// 		    		});
// 	    		}
// 	    	});
// 		});		 
	   				  
	} 
	*/
	
});