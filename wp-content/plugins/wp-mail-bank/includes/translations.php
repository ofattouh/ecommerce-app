<?php
/**
 * This file is used for translation strings.
 *
 * @author  Tech Banker
 * @package wp-mail-bank/includes
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}// Exit if accessed directly
if ( ! is_user_logged_in() ) {
	return;
} else {
	$access_granted = false;
	foreach ( $user_role_permission as $permission ) {
		if ( current_user_can( $permission ) ) {
			$access_granted = true;
			break;
		}
	}
	if ( ! $access_granted ) {
		return;
	} else {
		$mb_delete_log                                     = __( 'Email Log has been deleted Successfully', 'wp-mail-bank' );
		$mb_confirm_single_delete                          = __( 'Are you sure you want to delete Email Log?', 'wp-mail-bank' );
		$mb_email_configuration_send_email_via_mailgun_api = __( 'MailGun API', 'wp-mail-bank' );
		$mb_email_installed_firewall_message               = __( 'Your host may have installed a firewall between you and the server. Ask them to open the ports.', 'wp-mail-bank' );
		$mb_email_incorrect_port_message                   = __( 'You may have tried to (incorrectly) use SSL over port 587. Check your encryption and port settings.', 'wp-mail-bank' );
		$mb_email_poor_connectivity_message                = __( 'Your host may have poor connectivity to the mail server. Try doubling the Read Timeout.', 'wp-mail-bank' );
		$mb_email_drop_packets_message                     = __( 'Your host may have installed a firewall (DROP packets) between you and the server. Ask them to open the ports.', 'wp-mail-bank' );
		$mb_email_encryption_message                       = __( 'Your host may have tried to (incorrectly) use TLS over port 465. Check your encryption and port settings.', 'wp-mail-bank' );
		$mb_email_open_port_message                        = __( 'Your host has likely installed a firewall (REJECT packets) between you and the server. Ask them to open the ports.', 'wp-mail-bank' );
		$mb_email_debug_output_firewall_message            = __( 'Your Web Host provider may have installed a firewall between you and the server.', 'wp-mail-bank' );
		$mb_email_debug_output_host_provider_message       = __( 'Contact the admin of the server and ask if they allow outgoing communication on port 25,465,587.', 'wp-mail-bank' );
		$mb_email_debug_output_contact_admin_message       = __( 'It seems like they are blocking certain traffic. Ask them to open the ports.', 'wp-mail-bank' );
		$mb_email_blocked_message                          = __( 'Your Web Host provider may have blocked the use of mail() function on your server.', 'wp-mail-bank' );
		$mb_enable_mail_message                            = __( 'Ask them to enable the mail() function to start sending emails.', 'wp-mail-bank' );

		// Disclaimer.
		$mb_premium_edition_label   = __( 'Premium Edition', 'wp-mail-bank' );
		$mb_message_premium_edition = __( 'This feature is available only in Premium Editions! <br> Kindly Purchase to unlock it!', 'wp-mail-bank' );

		// fetch settings.
		$mb_fetch_settings         = __( 'Fetch Settings', 'wp-mail-bank' );
		$mb_fetch_settings_tooltip = __( 'Choose options for Fetch Settings', 'wp-mail-bank' );
		$mb_indivisual_site        = __( 'Individual Site', 'wp-mail-bank' );
		$mb_multiple_site          = __( 'Network Site', 'wp-mail-bank' );

		// wizard.
		$mb_wizard_basic_info    = __( 'Basic Info', 'wp-mail-bank' );
		$mb_wizard_account_setup = __( 'Account Setup', 'wp-mail-bank' );
		$mb_wizard_confirm       = __( 'Confirm', 'wp-mail-bank' );

		// Menus.
		$wp_mail_bank              = 'Mail Bank';
		$mb_email_configuration    = __( 'Email Setup', 'wp-mail-bank' );
		$mb_email_logs             = __( 'Email Logs', 'wp-mail-bank' );
		$mb_test_email             = __( 'Test Email', 'wp-mail-bank' );
		$mb_settings               = __( 'Plugin Settings', 'wp-mail-bank' );
		$mb_system_information     = __( 'System Information', 'wp-mail-bank' );
		$mb_support_forum          = __( 'Ask For Help', 'wp-mail-bank' );
		$mb_roles_and_capabilities = __( 'Roles & Capabilities', 'wp-mail-bank' );

		// Footer.
		$mb_success                                = __( 'Success!', 'wp-mail-bank' );
		$mb_update_email_configuration             = __( 'Email Setup has been updated Successfully', 'wp-mail-bank' );
		$mb_test_email_sent                        = __( 'Test Email was sent Successfully!', 'wp-mail-bank' );
		$mb_test_email_not_send                    = __( 'Test Email was not sent!', 'wp-mail-bank' );
		$mb_update_settings                        = __( 'Plugin Settings have been updated Successfully', 'wp-mail-bank' );
		$oauth_not_supported                       = __( 'The OAuth is not supported by providing SMTP Host, kindly provide username and password', 'wp-mail-bank' );
		$mb_feature_requests_your_name             = __( 'Your Name', 'wp-mail-bank' );
		$mb_feature_requests_your_name_placeholder = __( 'Please provide your Name', 'wp-mail-bank' );
		$mb_update_roles_and_capabilities          = __( 'Roles & Capabilities have been updated Successfully', 'wp-mail-bank' );

		// Common Variables.
		$mb_status_sent         = __( 'Email Sent', 'wp-mail-bank' );
		$mb_status_not_sent     = __( 'Email Not Sent', 'wp-mail-bank' );
		$mb_user_access_message = __( "You don't have Sufficient Access to this Page. Kindly contact the Administrator for more Privileges", 'wp-mail-bank' );
		$mb_enable              = __( 'Enable', 'wp-mail-bank' );
		$mb_disable             = __( 'Disable', 'wp-mail-bank' );
		$mb_override            = __( 'Override', 'wp-mail-bank' );
		$mb_dont_override       = __( "Don't Override", 'wp-mail-bank' );
		$mb_save_changes        = __( 'Save Settings', 'wp-mail-bank' );
		$mb_subject             = __( 'Subject', 'wp-mail-bank' );
		$mb_next_step           = __( 'Next Step', 'wp-mail-bank' );
		$mb_previous_step       = __( 'Previous Step', 'wp-mail-bank' );
		$mb_full_features       = __( 'Know about Full Features', 'wp-mail-bank' );
		$mb_chek_our            = __( 'or', 'wp-mail-bank' );
		$mb_online_demos        = __( 'Check our Online Demos', 'wp-mail-bank' );
		$mb_not_applicable      = __( 'N/A', 'wp-mail-bank' );

		// Email Setup.
		$mb_email_configuration_cc_label                       = 'CC';
		$mb_email_configuration_bcc_label                      = 'BCC';
		$mb_email_configuration_cc_email_address_tooltip       = __( 'A valid Email Address that will be used in the "CC" field of the email. Use Comma "," for including multiple email addresses in the "CC" field', 'wp-mail-bank' );
		$mb_email_configuration_bcc_email_address_tooltip      = __( 'A valid Email Address that will be used in the \'BCC\' field of the email. Use Comma \',\' for including multiple email addresses in the \'BCC\' field', 'wp-mail-bank' );
		$mb_email_configuration_cc_email_placeholder           = __( 'Please provide CC Email Address', 'wp-mail-bank' );
		$mb_email_configuration_bcc_email_placeholder          = __( 'Please provide BCC Email Address', 'wp-mail-bank' );
		$mb_email_configuration_from_name                      = __( 'From Name', 'wp-mail-bank' );
		$mb_email_configuration_from_name_placeholder          = __( 'Please provide From Name', 'wp-mail-bank' );
		$mb_email_configuration_from_email                     = __( 'From Email', 'wp-mail-bank' );
		$mb_email_configuration_from_email_placeholder         = __( 'Please provide From Email Address', 'wp-mail-bank' );
		$mb_email_configuration_mailer_type                    = __( 'Mailer Type', 'wp-mail-bank' );
		$mb_email_configuration_mailer_type_tooltip            = __( 'Choose among the variety of options for routing emails.', 'wp-mail-bank' );
		$mb_email_configuration_send_email_via_smtp            = __( 'Send Email via SMTP', 'wp-mail-bank' );
		$mb_email_configuration_use_php_mail_function          = __( 'Use The PHP mail() Function', 'wp-mail-bank' );
		$mb_email_configuration_smtp_host                      = __( 'SMTP Host', 'wp-mail-bank' );
		$mb_email_configuration_smtp_host_tooltip              = __( 'Server that will send the email', 'wp-mail-bank' );
		$mb_email_configuration_smtp_host_placeholder          = __( 'Please provide SMTP Host', 'wp-mail-bank' );
		$mb_email_configuration_smtp_port                      = __( 'SMTP Port', 'wp-mail-bank' );
		$mb_email_configuration_smtp_port_tooltip              = __( 'Port to connect to the email server', 'wp-mail-bank' );
		$mb_email_configuration_smtp_port_placeholder          = __( 'Please provide SMTP Port', 'wp-mail-bank' );
		$mb_email_configuration_encryption                     = __( 'Encryption', 'wp-mail-bank' );
		$mb_email_configuration_encryption_tooltip             = __( 'Encrypt the email when sent to the email server using the different methods available', 'wp-mail-bank' );
		$mb_email_configuration_no_encryption                  = 'No Encryption';
		$mb_email_configuration_use_ssl_encryption             = 'SSL Encryption';
		$mb_email_configuration_use_tls_encryption             = 'TLS Encryption';
		$mb_email_configuration_authentication                 = __( 'Authentication', 'wp-mail-bank' );
		$mb_email_configuration_authentication_tooltip         = __( 'Method for authentication (almost always Login)', 'wp-mail-bank' );
		$mb_email_configuration_test_email_address             = __( 'Email Address', 'wp-mail-bank' );
		$mb_email_configuration_test_email_address_tooltip     = __( 'A valid Email Address on which you would like to send a Test Email', 'wp-mail-bank' );
		$mb_email_configuration_test_email_address_placeholder = __( 'Please provide Email Address', 'wp-mail-bank' );
		$mb_email_configuration_subject_test_tooltip           = __( 'Subject Line for your Test Email', 'wp-mail-bank' );
		$mb_email_configuration_subject_test_placeholder       = __( 'Please provide Subject', 'wp-mail-bank' );
		$mb_email_configuration_content                        = __( 'Email Content', 'wp-mail-bank' );
		$mb_email_configuration_content_tooltip                = __( 'Email Content for your Test Email', 'wp-mail-bank' );
		$mb_email_configuration_send_test_email                = __( 'Send Test Email', 'wp-mail-bank' );
		$mb_email_configuration_send_test_email_textarea       = __( 'Checking your settings', 'wp-mail-bank' );
		$mb_email_configuration_result                         = __( 'Result', 'wp-mail-bank' );
		$mb_email_configuration_send_another_test_email        = __( 'Send Another Test Email', 'wp-mail-bank' );
		$mb_email_configuration_enable_from_name               = __( 'From Name Configuration', 'wp-mail-bank' );
		$mb_email_configuration_enable_from_name_tooltip       = __( 'Do you want to override the Default Name that tells email recipient about who sent the email?', 'wp-mail-bank' );
		$mb_email_configuration_enable_from_email              = __( 'From Email Configuration', 'wp-mail-bank' );
		$mb_email_configuration_enable_from_email_tooltip      = __( 'Do you want to override the Default Email Address that tells email recipient about the sender?', 'wp-mail-bank' );
		$mb_email_configuration_username                       = __( 'Username', 'wp-mail-bank' );
		$mb_email_configuration_username_tooltip               = __( ' Login is typically the full email address (Example: mailbox@yourdomain.com)', 'wp-mail-bank' );
		$mb_email_configuration_username_placeholder           = __( 'Please provide username', 'wp-mail-bank' );
		$mb_email_configuration_password                       = __( 'Password', 'wp-mail-bank' );
		$mb_email_configuration_password_tooltip               = __( 'Password is typically the same as the password to retrieve the email', 'wp-mail-bank' );
		$mb_email_configuration_password_placeholder           = __( 'Please provide password', 'wp-mail-bank' );
		$mb_email_configuration_redirect_uri                   = __( 'Redirect URI', 'wp-mail-bank' );
		$mb_email_configuration_redirect_uri_tooltip           = __( 'Please copy this Redirect URI and Paste into Redirect URI field when creating your app', 'wp-mail-bank' );
		$mb_email_configuration_use_oauth                      = 'OAuth (Client Id and Secret Key required)';
		$mb_email_configuration_none                           = 'None';
		$mb_email_configuration_use_plain_authentication       = 'Plain Authentication';
		$mb_email_configuration_cram_md5                       = 'Cram-MD5';
		$mb_email_configuration_login                          = 'Login';
		$mb_email_configuration_client_id                      = __( 'Client Id', 'wp-mail-bank' );
		$mb_email_configuration_client_secret                  = __( 'Secret Key', 'wp-mail-bank' );
		$mb_email_configuration_client_id_tooltip              = __( 'Client Id issued by your SMTP Host', 'wp-mail-bank' );
		$mb_email_configuration_client_secret_tooltip          = __( 'Secret Key issued by your SMTP Host', 'wp-mail-bank' );
		$mb_email_configuration_client_id_placeholder          = __( 'Please provide Client Id', 'wp-mail-bank' );
		$mb_email_configuration_client_secret_placeholder      = __( 'Please provide Secret Key', 'wp-mail-bank' );
		$mb_email_configuration_tick_for_sent_mail             = __( 'Yes, automatically send a Test Email upon clicking on the Next Step Button to verify settings', 'wp-mail-bank' );
		$mb_email_configuration_email_address                  = __( 'Email Address', 'wp-mail-bank' );
		$mb_email_configuration_email_address_tooltip          = __( 'A valid Email Address account from which you would like to send Emails', 'wp-mail-bank' );
		$mb_email_configuration_email_address_placeholder      = __( 'Please provide valid Email Address', 'wp-mail-bank' );
		$mb_email_configuration_reply_to                       = __( 'Reply To', 'wp-mail-bank' );
		$mb_email_configuration_reply_to_tooltip               = __( 'A valid Email Address that will be used in the \'Reply-To\' field of the email', 'wp-mail-bank' );
		$mb_email_configuration_reply_to_placeholder           = __( 'Please provide Reply To Email Address', 'wp-mail-bank' );
		$mb_email_configuration_get_credentials                = __( 'Get API Key', 'wp-mail-bank' );
		$mb_email_configuration_how_to_set_up                  = __( 'How to setup?', 'wp-mail-bank' );
		$mb_email_configuration_send_email_via_sendgrid_api    = __( 'SendGrid API', 'wp-mail-bank' );
		// Email Logs.
		$mb_start_date_title        = __( 'Start Date', 'wp-mail-bank' );
		$mb_resend                  = __( 'Resend Email', 'wp-mail-bank' );
		$mb_start_date_placeholder  = __( 'Please provide Start Date', 'wp-mail-bank' );
		$mb_start_date_tooltip      = __( 'Start Date for Email Logs', 'wp-mail-bank' );
		$mb_end_date_title          = __( 'End Date', 'wp-mail-bank' );
		$mb_limit_records_title     = __( 'Limit Records', 'wp-mail-bank' );
		$mb_limit_records_tooltip   = __( 'Number of Logs to view', 'wp-mail-bank' );
		$mb_hundred_records         = '100';
		$mb_five_hundred_records    = '500';
		$mb_thousand_records        = '1000';
		$mb_all_records             = 'All';
		$mb_end_date_placeholder    = __( 'Please provide End Date', 'wp-mail-bank' );
		$mb_end_date_tooltip        = __( 'End Date for Email Logs', 'wp-mail-bank' );
		$mb_submit                  = __( 'Submit', 'wp-mail-bank' );
		$mb_email_logs_bulk_action  = __( 'Bulk Action', 'wp-mail-bank' );
		$mb_email_logs_delete       = __( 'Delete', 'wp-mail-bank' );
		$mb_email_logs_resend_email = __( 'Resend Email', 'wp-mail-bank' );
		$mb_email_logs_apply        = __( 'Apply', 'wp-mail-bank' );
		$mb_email_logs_email_to     = __( 'Email To', 'wp-mail-bank' );
		$mb_email_logs_actions      = __( 'Action', 'wp-mail-bank' );
		$mb_email_logs_show_details = __( 'Email Content', 'wp-mail-bank' );
		$mb_email_logs_show_outputs = __( 'Debug Output', 'wp-mail-bank' );
		$mb_date_time               = __( 'Date/Time', 'wp-mail-bank' );

		// Settings.
		$mb_settings_debug_mode          = __( 'Debug Mode', 'wp-mail-bank' );
		$mb_settings_debug_mode_tooltip  = __( 'Do you want to see Debugging Output for your emails?', 'wp-mail-bank' );
		$mb_remove_tables_title          = __( 'Remove Database at Uninstall', 'wp-mail-bank' );
		$mb_remove_tables_tooltip        = __( 'Do you want to remove database at Uninstall of the Plugin?', 'wp-mail-bank' );
		$mb_monitoring_email_log_title   = __( 'Monitor Email Logs', 'wp-mail-bank' );
		$mb_monitoring_email_log_tooltip = __( 'Do you want to monitor your all Outgoing Emails?', 'wp-mail-bank' );

		// Roles and Capabilities.
		$mb_roles_capabilities_show_menu                        = __( 'Show Mail Bank Menu', 'wp-mail-bank' );
		$mb_roles_capabilities_show_menu_tooltip                = __( 'Choose among the following roles who would be able to see the Mail Bank Menu?', 'wp-mail-bank' );
		$mb_roles_capabilities_administrator                    = __( 'Administrator', 'wp-mail-bank' );
		$mb_roles_capabilities_author                           = __( 'Author', 'wp-mail-bank' );
		$mb_roles_capabilities_editor                           = __( 'Editor', 'wp-mail-bank' );
		$mb_roles_capabilities_contributor                      = __( 'Contributor', 'wp-mail-bank' );
		$mb_roles_capabilities_subscriber                       = __( 'Subscriber', 'wp-mail-bank' );
		$mb_roles_capabilities_others                           = __( 'Others', 'wp-mail-bank' );
		$mb_roles_capabilities_topbar_menu                      = __( 'Show Mail Bank Top Bar Menu', 'wp-mail-bank' );
		$mb_roles_capabilities_topbar_menu_tooltip              = __( 'Do you want to show Mail Bank menu in Top Bar?', 'wp-mail-bank' );
		$mb_roles_capabilities_administrator_role               = __( 'An Administrator Role can do the following ', 'wp-mail-bank' );
		$mb_roles_capabilities_administrator_role_tooltip       = __( 'Choose what pages would be visible to the users having Administrator Access', 'wp-mail-bank' );
		$mb_roles_capabilities_full_control                     = __( 'Full Control', 'wp-mail-bank' );
		$mb_roles_capabilities_author_role                      = __( 'An Author Role can do the following ', 'wp-mail-bank' );
		$mb_roles_capabilities_author_role_tooltip              = __( 'Choose what pages would be visible to the users having Author Access', 'wp-mail-bank' );
		$mb_roles_capabilities_editor_role                      = __( 'An Editor Role can do the following ', 'wp-mail-bank' );
		$mb_roles_capabilities_editor_role_tooltip              = __( 'Choose what pages would be visible to the users having Editor Access', 'wp-mail-bank' );
		$mb_roles_capabilities_contributor_role                 = __( 'A Contributor Role can do the following ', 'wp-mail-bank' );
		$mb_roles_capabilities_contributor_role_tooltip         = __( 'Choose what pages would be visible to the users having Contributor Access', 'wp-mail-bank' );
		$mb_roles_capabilities_other_role                       = __( 'Other Roles can do the following ', 'wp-mail-bank' );
		$mb_roles_capabilities_other_role_tooltip               = __( 'Choose what pages would be visible to the users having Others Role Access', 'wp-mail-bank' );
		$mb_roles_capabilities_other_roles_capabilities         = __( 'Please tick the appropriate capabilities for security purposes ', 'wp-mail-bank' );
		$mb_roles_capabilities_other_roles_capabilities_tooltip = __( 'Only users with these capabilities can access Mail Bank', 'wp-mail-bank' );
		$mb_roles_capabilities_subscriber_role                  = __( 'A Subscriber Role can do the following', 'wp-mail-bank' );
		$mb_roles_capabilities_subscriber_role_tooltip          = __( 'Choose what pages would be visible to the users having Subscriber Access', 'wp-mail-bank' );

		// Test Email.
		$mb_test_email_sending_test_email = __( 'Sending Test Email to', 'wp-mail-bank' );
		$mb_test_email_status             = __( 'Email Status', 'wp-mail-bank' );

		// Connectivity Test.
		$mb_connectivity_test   = __( 'Connectivity Test', 'wp-mail-bank' );
		$mb_transport           = __( 'Transport', 'wp-mail-bank' );
		$mb_socket              = __( 'Socket', 'wp-mail-bank' );
		$mb_status              = __( 'Status', 'wp-mail-bank' );
		$mb_smtp                = __( 'SMTP', 'wp-mail-bank' );
		$mb_mail_server_host    = __( 'SMTP Host', 'wp-mail-bank' );
		$mb_begin_test          = __( 'Begin Test', 'wp-mail-bank' );
		$mb_localhost           = 'localhost';
		$mb_mail_server_tooltip = __( 'SMTP Server that will be used for a Connectivity Test', 'wp-mail-bank' );

		// Email Setup.
		$mb_additional_header         = __( 'Additional Headers', 'wp-mail-bank' );
		$mb_additional_header_tooltip = __( 'You also can insert additional headers in this optional field in order to include in your email', 'wp-mail-bank' );
	}
}
