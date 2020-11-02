<?php
/**
 * This file is used for creating dbHelper class.
 *
 * @author  Tech Banker
 * @package wp-mail-bank/lib
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}// Exit if accessed directly.
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
		 * This Class is used for Insert, Update and Delete operations.
		 *
		 * @package    wp-mail-bank
		 * @subpackage lib
		 *
		 * @author  Tech Banker
		 */
		class Db_Helper_Mail_Bank {
			/**
			 * This Function is used for Insert data in database.
			 *
			 * @param string $table_name represent tables name.
			 * @param string $data represent data.
			 */
			public function insert_command( $table_name, $data ) {
				global $wpdb;
				$wpdb->insert( $table_name, $data );// db call ok; no-cache ok.
				return $wpdb->insert_id;
			}
			/**
			 * This function is used for Update data in database.
			 *
			 * @param string $table_name represent tables name.
			 * @param string $data represent data.
			 * @param string $where represent where condition.
			 */
			public function update_command( $table_name, $data, $where ) {
				global $wpdb;
				$wpdb->update( $table_name, $data, $where );// db call ok; no-cache ok.
			}
			/**
			 * This function is used for delete data from database.
			 *
			 * @param string $table_name .
			 * @param string $where .
			 */
			public function delete_command( $table_name, $where ) {
				global $wpdb;
				$wpdb->delete( $table_name, $where );// db call ok; no-cache ok.
			}
		}
		/**
		 * This Class is used for Get host and Port.
		 *
		 * @package    wp-mail-bank
		 * @subpackage lib
		 *
		 * @author  Tech Banker
		 */
		class Mail_Bank_Discover_Host { // @codingStandardsIgnoreLine
			/**
			 * Host domain name.
			 *
			 * @access   public
			 * @var      string    $domain  domain name.
			 */
			public $domain;
			/**
			 * Host domain name array.
			 *
			 * @access   public
			 * @var      array   $email_domains  domain name list.
			 */
			public $email_domains = array(
				'1and1.com'      => 'smtp.1and1.com',
				'airmail.net'    => 'smtp.airmail.net',
				'aol.com'        => 'smtp.aol.com',
				'Bluewin.ch'     => 'smtpauths.bluewin.ch',
				'Comcast.net'    => 'smtp.comcast.net',
				'Earthlink.net'  => 'smtpauth.earthlink.net',
				'gmail.com'      => 'smtp.gmail.com',
				'Gmx.com'        => 'mail.gmx.com',
				'Gmx.net'        => 'mail.gmx.com',
				'Gmx.us'         => 'mail.gmx.com',
				'hotmail.com'    => 'smtp-mail.outlook.com',
				'outlook.com'    => 'smtp-mail.outlook.com',
				'icloud.com'     => 'smtp.mail.me.com',
				'mail.com'       => 'smtp.mail.com',
				'ntlworld.com'   => 'smtp.ntlworld.com',
				'rocketmail.com' => 'smtp.mail.yahoo.com',
				'rogers.com'     => 'smtp.broadband.rogers.com',
				'yahoo.ca'       => 'smtp.mail.yahoo.ca',
				'yahoo.co.id'    => 'smtp.mail.yahoo.co.id',
				'yahoo.co.in'    => 'smtp.mail.yahoo.co.in',
				'yahoo.co.kr'    => 'smtp.mail.yahoo.com',
				'yahoo.com'      => 'smtp.mail.yahoo.com',
				'yahoo.com.ar'   => 'smtp.mail.yahoo.com.ar',
				'yahoo.com.au'   => 'smtp.mail.yahoo.com.au',
				'yahoo.com.br'   => 'smtp.mail.yahoo.com.br',
				'yahoo.com.cn'   => 'smtp.mail.yahoo.com.cn',
				'yahoo.com.hk'   => 'smtp.mail.yahoo.com.hk',
				'yahoo.com.mx'   => 'smtp.mail.yahoo.com',
				'yahoo.com.my'   => 'smtp.mail.yahoo.com.my',
				'yahoo.com.ph'   => 'smtp.mail.yahoo.com.ph',
				'yahoo.com.sg'   => 'smtp.mail.yahoo.com.sg',
				'yahoo.com.tw'   => 'smtp.mail.yahoo.com.tw',
				'yahoo.com.vn'   => 'smtp.mail.yahoo.com.vn',
				'yahoo.co.nz'    => 'smtp.mail.yahoo.com.au',
				'yahoo.co.th'    => 'smtp.mail.yahoo.co.th',
				'yahoo.co.uk'    => 'smtp.mail.yahoo.co.uk',
				'ymail.com'      => 'smtp.mail.yahoo.com',
				'yahoo.de'       => 'smtp.mail.yahoo.de',
				'yahoo.es'       => 'smtp.correo.yahoo.es',
				'yahoo.fr'       => 'smtp.mail.yahoo.fr',
				'yahoo.ie'       => 'smtp.mail.yahoo.co.uk',
				'yahoo.it'       => 'smtp.mail.yahoo.it',
				'zoho.com'       => 'smtp.zoho.com',
				'ameritech.net'  => 'outbound.att.net',
				'att.net'        => 'outbound.att.net',
				'bellsouth.net'  => 'outbound.att.net',
				'flash.net'      => 'outbound.att.net',
				'nvbell.net'     => 'outbound.att.net',
				'pacbell.net'    => 'outbound.att.net',
				'prodigy.net'    => 'outbound.att.net',
				'sbcglobal.net'  => 'outbound.att.net',
				'snet.net'       => 'outbound.att.net',
				'swbell.net'     => 'outbound.att.net',
				'wans.net'       => 'outbound.att.net',
			);
			/**
			 * This Function is used for getting hostname.
			 *
			 * @param string $hostname name of hosts.
			 */
			public function get_smtp_from_email( $hostname ) {
				reset( $this->email_domains );
				if ( array_key_exists( $hostname, $this->email_domains ) ) {
						$email_domains_array = $this->email_domains;
						return $email_domains_array[ $hostname ];
				}
				return false;
			}
		}
		/**
		 * This Class is used for getting plugins information.
		 *
		 * @package    wp-mail-bank
		 * @subpackage lib
		 *
		 * @author  Tech Banker
		 */
		class Plugin_Info_Wp_Mail_Bank { // @codingStandardsIgnoreLine
			/**
			 * This function is used to return the information about plugins.
			 */
			public function get_plugin_info_wp_mail_bank() {
				$active_plugins = (array) get_option( 'active_plugins', array() );
				if ( is_multisite() ) {
					$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
				}
				$plugins = array();
				if ( count( $active_plugins ) > 0 ) {
					$get_plugins = array();
					foreach ( $active_plugins as $plugin ) {
						$plugin_data = @get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin ); // @codingStandardsIgnoreLine

						$get_plugins['plugin_name']    = strip_tags( $plugin_data['Name'] );
						$get_plugins['plugin_author']  = strip_tags( $plugin_data['Author'] );
						$get_plugins['plugin_version'] = strip_tags( $plugin_data['Version'] );
						array_push( $plugins, $get_plugins );
					}
					return $plugins;
				}
			}
		}
	}
}
