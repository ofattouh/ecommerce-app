<?php
/**
 * Users administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */
 
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once( dirname(__FILE__).'/pp-role-usage-helper.php' );

global $pp_admin;

if ( ! current_user_can( 'pp_manage_settings' ) )
	wp_die( __( 'You are not permitted to do that.', 'pp' ) );

global $pp_role_usage_table;
	
if ( empty($pp_role_usage_table) ) {
	require_once( dirname(__FILE__).'/includes/class-pp-role-usage-list-table.php' );
	$pp_role_usage_table = new PP_Role_Usage_List_Table();
}

// contextual help - choose Help on the top right of admin panel to preview this.
/*
add_contextual_help($current_screen,
    '<p>' . __('This screen lists all the existing users for your site. Each user has one of five defined roles as set by the site admin: Site Administrator, Editor, Author, Contributor, or Subscriber. Users with roles other than Administrator will see fewer options in the dashboard navigation when they are logged in, based on their role.') . '</p>' .
    '<p>' . __('You can customize the display of information on this screen as you can on other screens, by using the Screen Options tab and the on-screen filters.') . '</p>' .
    '<p>' . __('To add a new user for your site, click the Add New button at the top of the screen or Add New in the Users menu section.') . '</p>' .
    '<p><strong>' . __('For more information:') . '</strong></p>' .
    '<p>' . __('<a href="http://codex.wordpress.org/Users_Screen" target="_blank">Documentation on Managing Users</a>') . '</p>' .
    '<p>' . __('<a href="http://codex.wordpress.org/Roles_and_Capabilities" target="_blank">Descriptions of Roles and Capabilities</a>') . '</p>' .
    '<p>' . __('<a href="http://wordpress.org/support/" target="_blank">Support Forums</a>') . '</p>'
);
*/

$url = $referer = $redirect = $update = '';
PP_Role_Usage_Helper::get_url_properties( $url, $referer, $redirect );

$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
if ( ! $action )
	$action = isset( $_REQUEST['pp_action'] ) ? $_REQUEST['pp_action'] : '';

$pp_role_usage_table->prepare_items();
$total_pages = $pp_role_usage_table->get_pagination_arg( 'total_pages' );

$messages = array();
if ( isset($_GET['update']) ) :
	switch($_GET['update']) {
	case 'edit':
		$messages[] = '<div id="message" class="updated"><p>' . __('Role Usage edited.', 'pp') . '</p></div>';
		break;
	}
endif; ?>

<?php if ( isset($pp_admin->errors) && is_wp_error( $pp_admin->errors ) ) : ?>
	<div class="error">
		<ul>
		<?php
			foreach ( $pp_admin->get_error_messages() as $err )
				echo "<li>$err</li>\n";
		?>
		</ul>
	</div>
<?php endif;

if ( ! empty($messages) ) {
	foreach ( $messages as $msg )
		echo $msg;
}
?>

<div class="wrap pp-role-usage">
<?php pp_icon(); ?>
<h1>
<?php

$caption = __('Edit Role Usage', 'pp');

echo esc_html( $caption );
?>
</h1>

<?php 
if ( pp_get_option('display_hints') ) {
	echo '<div class="pp-hint">';
	_e( "These <strong>optional</strong> settings customize how Press Permit applies <strong>supplemental roles</strong>. Your existing WP Role Definitions can be applied in two different ways:", 'pp' );
	echo '<ul style="list-style-type:disc;list-style-position:outside;margin:1em 0 0 2em"><li>' . __( "Pattern Roles convert 'post' capabilities to the corresponding type-specific capability.  In a normal WP installation, this is the easiest solution.", 'pp' ) . '</li>';
	echo '<li>' . __( "With Direct Assignment, capabilities are applied without modification (leaving you responsible to add custom type caps to the WP Role Definitions).", 'pp' ) . '</li></ul>';
	echo '</div>';
}

$pp_role_usage_table->views(); 
$pp_role_usage_table->display(); 
?>
<form method="post" action="">
<?php
$msg = __( "All Role Usage settings will be reset to DEFAULTS.  Are you sure?", 'pp' );
$js_call = "javascript:if (confirm('$msg')) {return true;} else {return false;}";
?>
<p class="submit" style="border:none;float:left">
<input type="submit" name="pp_role_usage_defaults" value="<?php _e('Revert to Defaults', 'pp') ?>" onclick="<?php echo $js_call;?>" />
</p>
<br style="clear:both" />
</form>
<?php

if ( pp_get_option('display_hints') ) {
	PP_Role_Usage_Helper::other_notes();
}
?>

</div>