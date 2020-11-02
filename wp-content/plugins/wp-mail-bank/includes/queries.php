<?php
/**
 * This file is used for fetching data from database.
 *
 * @author  Tech-Banker
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
			/**
			 * This function is used to get logs data.
			 *
			 * @param string $data holds data.
			 * @param string $start_date holds start date.
			 * @param string $end_date holds end date.
			 */
		function get_mail_bank_log_data_maybe_unserialize( $data, $start_date, $end_date ) {
			$array_details = array();
			foreach ( $data as $raw_row ) {
				$unserialize_data       = maybe_unserialize( $raw_row->email_data );
				$unserialize_data['id'] = $raw_row->id;
				if ( $unserialize_data['timestamp'] >= $start_date && $unserialize_data['timestamp'] <= $end_date ) {
					array_push( $array_details, $unserialize_data );
				}
			}
			return $array_details;
		}
		/**
		 * This function used to get the data.
		 *
		 * @param string $meta_key this parameter is used to fetch data on the basis of this key.
		 */
		function get_mail_bank_meta_value( $meta_key ) {
			global $wpdb;
			$meta_value = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT meta_value FROM ' . $wpdb->prefix . 'mail_bank_meta  WHERE meta_key=%s', $meta_key
				)
			); // db call ok; no-cache ok.
			return maybe_unserialize( $meta_value );
		}
		if ( isset( $_REQUEST['page'] ) ) {
			$page = sanitize_text_field( wp_unslash( $_REQUEST['page'] ) );// Input var okay, CSRF ok.
		}
		$check_wp_mail_bank_wizard = get_option( 'mail-bank-welcome-page' );
		$page_url                  = false === $check_wp_mail_bank_wizard ? 'mb_mail_bank_welcome_page' : $page;
		if ( isset( $_REQUEST['page'] ) ) {// Input var okay, CSRF ok.
			switch ( $page_url ) {
				case 'mb_roles_and_capabilities':
					$details_roles_capabilities = get_mail_bank_meta_value( 'roles_and_capabilities' );
					$other_roles_access_array   = array(
						'manage_options',
						'edit_plugins',
						'edit_posts',
						'publish_posts',
						'publish_pages',
						'edit_pages',
						'read',
					);
					$other_roles_array          = isset( $details_roles_capabilities['capabilities'] ) && '' !== $details_roles_capabilities['capabilities'] ? $details_roles_capabilities['capabilities'] : $other_roles_access_array;
					break;

				case 'mb_settings':
					$settings_data_array = get_mail_bank_meta_value( 'settings' );
					break;

				case 'mb_email_logs':
					$end_date                     = MAIL_BANK_LOCAL_TIME + 86400;
					$start_date                   = $end_date - 604800;
					$email_logs_data              = $wpdb->get_results(
						'SELECT * FROM ' . $wpdb->prefix . 'mail_bank_email_logs ORDER BY id DESC LIMIT 1000'
					); // db call ok; no-cache ok.
					$unserialized_email_logs_data = get_mail_bank_log_data_maybe_unserialize( $email_logs_data, $start_date, $end_date );
					break;


				case 'mb_email_configuration':
					$email_configuration_array = get_mail_bank_meta_value( 'email_configuration' );
					if ( ! empty( $_REQUEST['access_token'] ) && isset( $_REQUEST['access_token'] ) ) {// Input var okay, CSRF ok.
						$code                            = esc_attr( $_REQUEST['access_token'] ); // @codingStandardsIgnoreLine
						$update_email_configuration_data = get_option( 'update_email_configuration' );
						$mail_bank_auth_host             = new Mail_Bank_Auth_Host( $update_email_configuration_data );
						if ( 'smtp.gmail.com' === $update_email_configuration_data['hostname'] ) {
							$test_secret_key_error = $mail_bank_auth_host->google_authentication_token( $code );
							if ( isset( $test_secret_key_error->error ) ) {
								$test_secret_key_error = $test_secret_key_error->error_description;
								break;
							}
						} elseif ( in_array( $update_email_configuration_data['hostname'], $mail_bank_auth_host->yahoo_domains, true ) ) {
							$test_secret_key_error = $mail_bank_auth_host->yahoo_authentication_token( $code );
							if ( isset( $test_secret_key_error->error ) ) {
								$test_secret_key_error = $test_secret_key_error->error_description;
								break;
							}
						} else {
							$test_secret_key_error = $mail_bank_auth_host->microsoft_authentication_token( $code );
							if ( isset( $test_secret_key_error->error ) ) {
								$test_secret_key_error = $test_secret_key_error->error_description;
								break;
							}
						}
						$obj_db_helper_mail_bank = new Db_Helper_Mail_Bank();

						$update_email_configuration_array = array();
						$where                            = array();
						$where['meta_key']                = 'email_configuration'; // WPCS: slow query ok.
						$update_email_configuration_array['meta_value'] = maybe_serialize( $update_email_configuration_data ); // WPCS: slow query ok.
						$obj_db_helper_mail_bank->update_command( mail_bank_meta(), $update_email_configuration_array, $where );
						if ( '1' === $update_email_configuration_data['automatic_mail'] ) {
							$automatically_send_mail = 'true';
						} else {
							$automatically_not_send_mail = 'true';
						}
					}
					break;
			}
		}
	}
}
