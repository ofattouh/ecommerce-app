<?php
/**
 * Edit user administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$url = apply_filters( 'pp_conditions_base_url', 'admin.php' );

if ( isset( $_REQUEST['wp_http_referer'] ) )
	$wp_http_referer = $_REQUEST['wp_http_referer'];
elseif ( isset($_SERVER['HTTP_REFERER']) ) {
	if ( ! strpos( $_SERVER['HTTP_REFERER'], 'page=pp-status-new' ) )
		$wp_http_referer = $_SERVER['HTTP_REFERER'];

	$wp_http_referer = remove_query_arg( array('update', 'edit', 'delete_count'), stripslashes($wp_http_referer) );
} else
	$wp_http_referer = '';
	
// contextual help - choose Help on the top right of admin panel to preview this.
/*
add_contextual_help($current_screen,
    '<p>' . __('Your profile contains information about you (your &#8220;account&#8221;) as well as some personal options related to using WordPress.') . '</p>' .
    '<p>' . __('Required fields are indicated; the rest are optional. Profile information will only be displayed if your theme is set up to do so.') . '</p>' .
    '<p>' . __('Remember to click the Update Profile button when you are finished.') . '</p>' .
    '<p><strong>' . __('For more information:') . '</strong></p>' .
    '<p>' . __('<a href="http://codex.wordpress.org/Users_Your_Profile_Screen" target="_blank">Documentation on User Profiles</a>') . '</p>' .
    '<p>' . __('<a href="http://wordpress.org/support/" target="_blank">Support Forums</a>') . '</p>'
);
*/
?>

<?php 
global $pp_admin;

$attribute = 'post_status';
$attrib_type = ( isset( $_REQUEST['attrib_type'] ) ) ? pp_sanitize_key($_REQUEST['attrib_type']) : '';

if ( ! current_user_can( 'pp_define_post_status' ) && ( ! $attrib_type || ! current_user_can( "pp_define_{$attrib_type}" ) ) )
	wp_die( __( 'You are not permitted to do that.', 'pps' ) );

if ( ! isset($_REQUEST['status']) )
	wp_die( 'No status specified.' );

$status = pp_sanitize_key($_REQUEST['status']);

if ( ! $status_obj = get_post_status_object( $status ) )
	wp_die( 'Status does not exist.' );

if ( $status_obj->private )
	$attrib_type = 'private';
elseif( ! empty( $status_obj->moderation ) )
	$attrib_type = 'moderation';
	
if ( ( ! empty($_POST) || isset($_GET['update']) ) && empty( $pp_admin->errors ) ) : 
	$url = add_query_arg( array( 'page' => 'pp-stati', 'attrib_type' => $attrib_type ), admin_url( apply_filters( 'pp_role_usage_base_url', 'admin.php' ) ) );
?>
	<div id="message" class="updated">
	<p><strong><?php _e('Status updated.', 'pps') ?>&nbsp;</strong>
	<?php if ( $wp_http_referer ) : ?>
		<a href="<?php echo( esc_html($url) );?>"><?php _e('Back to Statuses', 'pps'); ?></a>
	<?php endif; ?>
	</p></div>
<?php endif; ?>

<?php 
if ( ! empty( $pp_admin->errors ) && is_wp_error( $pp_admin->errors ) ) : ?>
<div class="error"><p><?php echo implode( "</p>\n<p>", $pp_admin->errors->get_error_messages() ); ?></p></div>
<?php endif; ?>

<div class="wrap" id="condition-profile-page">
<?php pp_icon(); ?>
<h1><?php echo esc_html( __('Edit Post Status', 'pps' ) );
?></h1>

<form action="" method="post" id="editcondition" name="editcondition" class="pp-admin" <?php do_action('pp_condition_create_form_tag'); ?>>
<input name="action" type="hidden" value="update" />
<input name="pp_attribute" type="hidden" value="<?php echo $attribute; ?>" />
<input name="attrib_type" type="hidden" value="<?php echo $attrib_type; ?>" />
<?php wp_nonce_field('pp-update-condition_' . $status) ?>

<?php if ( $wp_http_referer ) : ?>
	<input type="hidden" name="wp_http_referer" value="<?php echo esc_url($wp_http_referer); ?>" />
<?php endif; ?>

<?php 
require_once( dirname(__FILE__).'/status-ui_ppp.php' );
PPP_StatusEditUI::status_edit_ui( $status, compact( 'attrib_type' ) );
?>

<?php
do_action( 'pp_edit_condition_ui', $attrib_type, $attribute, $status );
?>

<?php 
submit_button( _pp_('Update', 'pps'), 'primary large pp-submit' ); 
?>

</form>
</div>
<?php
