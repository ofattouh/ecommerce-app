<?php
/**
 * Edit user administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once( dirname(__FILE__).'/pp-role-usage-helper.php' );
require_once( dirname(__FILE__).'/includes/role-usage-query_ppce.php' );
	
$url = apply_filters( 'pp_role_usage_base_url', 'admin.php' );

if ( isset( $_REQUEST['wp_http_referer'] ) )
	$wp_http_referer = $_REQUEST['wp_http_referer'];
elseif ( isset($_SERVER['HTTP_REFERER']) )
	$wp_http_referer = remove_query_arg( array('update', 'edit', 'delete_count'), stripslashes($_SERVER['HTTP_REFERER']) );
else
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

if ( ! current_user_can( 'pp_manage_settings' ) )
	wp_die( __( 'You are not permitted to do that.', 'pp' ) );

if ( ! isset($_REQUEST['role']) )
	wp_die( 'No role specified.' );
	
$role_name = sanitize_text_field( $_REQUEST['role'] );

global $pp_role_defs, $wp_roles;

$pp_cap_caster = pp_init_cap_caster();
$pp_cap_caster->define_pattern_caps();

if ( isset( $pp_role_defs->pattern_roles[$role_name] ) ) {
	$role_obj = $pp_role_defs->pattern_roles[$role_name];
} elseif ( isset( $wp_roles->role_names[$role_name] ) ) {
	$role_obj = (object) array( 'labels' => (object) array( 'singular_name' => $wp_roles->role_names[$role_name] ) );
} else
	wp_die( 'Role does not exist.' );

if ( ! empty($_POST) )
	$_GET['update'] = 1; // temp workaround
	
if ( isset($_GET['update']) && empty( $pp_admin->errors ) ) : ?>
	<div id="message" class="updated">
	<p><strong><?php _e('Role Usage updated.', 'pp') ?>&nbsp;</strong>
	</p></div>
<?php endif; ?>

<?php 
if ( ! empty( $pp_admin->errors ) && is_wp_error( $pp_admin->errors ) ) : ?>
<div class="error"><p><?php echo implode( "</p>\n<p>", $pp_admin->errors->get_error_messages() ); ?></p></div>
<?php endif; ?>

<div class="wrap" id="usage-profile-page">
<?php pp_icon(); ?>
<h1><?php echo esc_html( sprintf( __('Role Usage: %s', 'pp' ), $role_obj->labels->singular_name ) );
?></h1>

<form action="" method="post" id="edit_role_usage" name="edit_role_usage">
<input name="action" type="hidden" value="update" />
<?php wp_nonce_field('pp-update-role-usage_' . $role_name) ?>

<?php if ( $wp_http_referer ) : ?>
	<input type="hidden" name="wp_http_referer" value="<?php echo esc_url($wp_http_referer); ?>" />
<?php endif; ?>

<table class="form-table">
<tr class="form-field">
<th><label for="role_usage_label"><?php _e('Usage', 'pp')?></label></th>
<td>
<div id='pp_role_usage_limitations'>
	<div>
	<?php
	$usage = PPCE_Role_Usage_Query::get_role_usage($role_name);
	?>
	<select id='pp_role_usage' name='pp_role_usage'/>
	<option value='0' <?php if ( $usage == 0 ) echo 'selected="selected"';?>><?php _e('no supplemental assignment', 'pp');?></option>
	<option value='pattern' <?php if ( $usage == 'pattern' ) echo 'selected="selected"';?>><?php _e('Pattern Role', 'pp');?></option>
	<option value='direct' <?php if ( $usage == 'direct' ) echo 'selected="selected"';?>><?php _e('Direct Assignment', 'pp');?></option>
	</select>
	</div>
</div>
</td>
</tr>

<?php 
if ( ! empty($pp_cap_caster->pattern_role_type_caps[$role_name]) ) : ?>
<tr class="form-field">
	<th><label for="post_caps_label"><?php _e('Post Capabilities', 'pp')?></label></th>
	<td class='pp-cap_list'>
	<?php 
	printf( __( 'Type-specific and/or status-specific equivalents of the following capabilities are included in supplemental %s roles:', 'pp'), $role_obj->labels->singular_name );
	$cap_names = array_keys( $pp_cap_caster->pattern_role_type_caps[$role_name] );
	sort($cap_names);
	echo "<ul><li>" . implode("</li><li>", $cap_names) . "</li></ul>";
	?>
	</td>
</tr>
<?php endif;?>

<?php 
if ( ! empty($pp_cap_caster->pattern_role_arbitrary_caps[$role_name]) ) :?>
<tr><th></th><td></td></tr>
<tr class="form-field">
	<th><label for="arbitrary_caps_label"><?php _e('Arbitrary Capabilities', 'pp')?></label></th>
	<td class='pp-cap_list'>
	<?php 
	printf( __( 'The following capabilities are included in supplemental %s roles:', 'pp'), $role_obj->labels->singular_name );
	$site_caps = array_keys($pp_cap_caster->pattern_role_arbitrary_caps[$role_name]);
	sort( $site_caps );
	echo "<ul><li>" . implode("</li><li>", $site_caps) . "</li></ul>";
	?>
	</td>
</tr>
<?php endif;?>

<?php if ( empty( $pp_role_defs->pattern_roles[$role_name]) && ! empty($wp_roles->role_objects[$role_name]) ) :?>
<tr><th></th><td></td></tr>
<tr class="form-field">
	<th><label for="role_caps_label"><?php _e('Role Capabilities', 'pp')?></label></th>
	<td class='pp-cap_list'>
	<?php 
	_e( 'All capabilities defined for this WordPress role will be applied in supplemental assignments:', 'pp' );
	$role_caps = array_keys($wp_roles->role_objects[$role_name]->capabilities);
	sort($role_caps);
	echo "<ul><li>" . implode("</li><li>", $role_caps) . "</li></ul>";
	?>
	</td>
</tr>
<?php endif;?>
</table>

<br />
<?php
do_action( 'pp_edit_role_usage_ui', $role_name );

if ( ( $usage == 'pattern' ) && pp_get_option('display_hints') ) {
	//$hint = __( 'Note: you can use a WP role editor such as User Role Editor, Members or Capability Manager to directly edit this role\'s capabilities. In the future, they will be editable here.', 'pp' );
	echo '<br />';
	$hint = '';
	PP_Role_Usage_Helper::other_notes( __( 'Notes regarding Pattern Roles', 'pp' ) );
}
?>

<?php
submit_button( _pp_('Update', 'pp'), 'primary large pp-submit' ); 
?>

<p>
<a href="<?php echo( esc_url( add_query_arg( 'page', 'pp-role-usage', admin_url($url) ) ) );?>"><?php _e('Back to Role Usage List', 'pp'); ?></a>
</p>

</form>
</div>
