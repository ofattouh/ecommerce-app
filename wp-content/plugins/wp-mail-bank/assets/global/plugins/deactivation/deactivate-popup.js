jQuery(document).ready(function($) {
	$( '#the-list #mail-bank-plugin-disable-link' ).click(function(e) {
		e.preventDefault();

		var reason = $( '#mail-bank-feedback-content .mail-bank-reason' ),
			deactivateLink = $( this ).attr( 'href' );

			$( "#mail-bank-feedback-content" ).dialog({
				title: 'Quick Feedback Form',
				dialogClass: 'mail-bank-feedback-form',
					resizable: false,
					minWidth: 430,
					minHeight: 300,
					modal: true,
					buttons: {
						'go' : {
							text: 'Continue',
							icons: { primary: "dashicons dashicons-update" },
							id: 'mail-bank-feedback-dialog-continue',
					class: 'button',
							click: function() {
								var dialog = $(this),
									go = $('#mail-bank-feedback-dialog-continue'),
										form = dialog.find('form').serializeArray(),
							result = {};
						$.each( form, function() {
							if ( '' !== this.value )
									result[ this.name ] = this.value;
						});
								if ( ! jQuery.isEmptyObject( result ) ) {
									result.action = 'post_user_feedback_mail_bank';
										$.ajax({
												url: post_feedback.admin_ajax,
												type: 'POST',
												data: result,
												error: function(){},
												success: function(msg){},
												beforeSend: function() {
													go.addClass('mail-bank-ajax-progress');
												},
												complete: function() {
													go.removeClass('mail-bank-ajax-progress');
														dialog.dialog( "close" );
														location.href = deactivateLink;
												}
										});
								}
							},
						},
						'cancel' : {
							text: 'Cancel',
							id: 'mail-bank-feedback-cancel',
							class: 'button button-primary',
							click: function() {
									$( this ).dialog( "close" );
							}
						},
						'skip' : {
							text: 'Skip',
							id: 'mail-bank-feedback-dialog-skip',
							click: function() {
									$( this ).dialog( "close" );
									location.href = deactivateLink;
							}
						},
					}
			});
	});
});
