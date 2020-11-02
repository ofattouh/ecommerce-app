<?php
/**
 * Users administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */
 
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $pp_admin;

require_once( dirname(__FILE__).'/pp-status-helper.php' );
	
$attribute = 'post_status';

if ( isset( $_REQUEST['attrib_type'] ) ) {
	$attrib_type = pp_sanitize_key($_REQUEST['attrib_type']);
} else {
	if ( $links = apply_filters( 'pp_post_status_types', array() ) ) {
		$link = reset( $links );
		$attrib_type = $link->attrib_type;
	}
}

if ( ! current_user_can( 'pp_administer_content' ) && ( ! $attrib_type || ! current_user_can( "pp_define_{$attrib_type}" ) ) )
	wp_die( __( 'You are not permitted to do that.', 'pps' ) );

$pp_attributes = pps_init_attributes();

//$list_table = apply_filters( 'pp_conditions_list_table', false, $attribute ); 

global $pp_attributes_list_table;

if ( empty( $pp_attributes_list_table ) ) {
	require_once( dirname(__FILE__).'/includes/class-pp-stati-list-table.php' );
	$pp_attributes_list_table = new PP_Attributes_List_Table($attrib_type);
}

$pagenum = $pp_attributes_list_table->get_pagenum();

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
PP_Conditions_Helper::get_url_properties( $url, $referer, $redirect );

$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
if ( ! $action )
	$action = isset( $_REQUEST['pp_action'] ) ? $_REQUEST['pp_action'] : '';

switch ( $action ) {
//switch ( $pp_attributes_list_table->current_action() ) {

case 'delete':
case 'bulkdelete':
	if ( empty($_REQUEST['statuses']) )
		$conds = array ($_REQUEST['status']);
	else
		$conds = (array) $_REQUEST['statuses'];
?>
<form action="" method="post" name="updateconditions" id="updateconditions">
<?php wp_nonce_field('delete-conditions') ?>
<?php echo $referer; ?>

<div class="wrap">
<?php pp_icon(); ?>
<h1><?php _e('Delete Statuses'); ?></h1>
<p><?php echo _n( 'You have specified this status for deletion:', 'You have specified these statuses for deletion:', count( $conds ), 'pps' ); ?></p>
<ul>
<?php
	$go_delete = 0;
	foreach ( $conds as $cond ) {
		if ( $cond_obj = _pp_get_condition($attribute, $cond) ) {
			echo "<li><input type=\"hidden\" name=\"users[]\" value=\"" . esc_attr($cond) . "\" />" . $cond_obj->label . "</li>\n";
			$go_delete++;
		}
	}

	?>
	</ul>
<?php if ( $go_delete ) : ?>
	<input type="hidden" name="action" value="dodelete" />
	<input type="hidden" name="pp_attribute" value="<?php echo $attribute; ?>" />
	<input type="hidden" name="attrib_type" value="<?php echo $attrib_type; ?>" />
	<?php submit_button( __('Confirm Deletion'), 'secondary' ); ?>
<?php else : ?>
	<p><?php _e('There are no valid statuses selected for deletion.', 'pps'); ?></p>
<?php endif; ?>
</div>
</form>
<?php

break;

default:

if ( ! empty($_REQUEST['update']) && empty( $pp_admin->errors ) && ! empty($_SERVER['HTTP_REFERER']) && strpos( $_SERVER['HTTP_REFERER'], 'pp-status-new' ) ) : 
?>
	<div id="message" class="updated">
	<p><strong><?php _e('Post Status Created.', 'pps') ?>&nbsp;</strong>
	</p></div>
<?php endif;

	$pp_attributes_list_table->prepare_items();
	$total_pages = $pp_attributes_list_table->get_pagination_arg( 'total_pages' );

	$messages = array();
	if ( isset($_GET['update']) ) :
		switch($_GET['update']) {
		case 'del':
		case 'del_many':
			$delete_count = isset($_GET['delete_count']) ? (int) $_GET['delete_count'] : 0;
			$messages[] = '<div id="message" class="updated"><p>' . sprintf(_n('%s status deleted', '%s status deleted', $delete_count, 'pps'), $delete_count) . '</p></div>';
			break;
		case 'edit':
			$messages[] = '<div id="message" class="updated"><p>' . __('Status edited.', 'pps') . '</p></div>';
			break;
		case 'add':
			$messages[] = '<div id="message" class="updated"><p>' . __('New status created.', 'pps') . '</p></div>';
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
} ?>

<div class="wrap pp-conditions">
<?php pp_icon(); ?>
<h1>
<?php
$attrib_obj = $pp_attributes->attributes[$attribute];

if ( 'post_status' == $attribute ) {
	if ( 'private' == $attrib_type ) {
		$attrib_caption = __('Define Post Privacy Statuses', 'pps');
		$hint = __( "Statuses enabled here are available as Visibility options for post publishing. Affected posts become inaccessable without a corresponding status-specific role assignment.", 'pps' );
	} elseif ( 'moderation' == $attrib_type ) {
		$attrib_caption = __('Define Post Moderation Statuses', 'pps');
		$hint = __( "Statuses enabled here are available as pre-publication Moderation statuses - additional steps in between 'Pending Review' and 'Published'.  Affected posts become uneditable without a corresponding status-specific role assignment.", 'pps' );
	} else
		$attrib_caption = __('Define Post Statuses', 'pps');
} else {
	$attrib_caption = sprintf( __('Define Statuses: %s', 'pps'), $attrib_obj->label );
	$hint = __( "Statuses alter your content's accessibility by imposing additional capability requirements.", 'pps' );
}

echo esc_html( $attrib_caption );

/*
if ( current_user_can( 'pp_edit_groups' ) ) {
	if ( MULTISITE && pp_get_option('ms_netwide_groups') )
		$url = 'users.php';
	else
		$url = 'admin.php';
}
*/

?>

<a href="<?php echo $url;?>?page=pp-status-new&amp;attrib_type=<?php echo $attrib_type; ?>" class="add-new-h2"><?php echo esc_html( _pp_( 'Add New' ) ); ?></a>

</h1>
<?php
if ( pp_get_option('display_hints') ) {
	echo '<div class="pp-hint">';
	echo esc_html( $hint );
	echo '</div><br />';
}

/* if ( current_user_can( 'create_users' ) ) { ?> */

?>

<ul class="subsubsub">
<?php 
$links = apply_filters( 'pp_post_status_types', array() );

if ( count($links) > 1 ) {
	foreach( $links as $link_obj ) :
		if ( empty($stepped) ) { $stepped = true; } else { echo '|'; }
	?>
		<li><a href="<?php echo $link_obj->url;?>" <?php if( $attrib_type == $link_obj->attrib_type ) echo 'class="current"';?> ><?php echo $link_obj->label;?></a></li>
	<?php endforeach;
} // endif more than one attribute	
?>
</ul>
<?php

//dump($pp_attributes_list_table->items);

$pp_attributes_list_table->views(); 
$pp_attributes_list_table->display(); 
?>
<a href="#show_cap_map" class="show-cap-map"><?php echo __( 'show capability mapping', 'pps' ); ?></a> <span class="cap-map-note" style="display:none">&nbsp;&nbsp;&bull;&nbsp;&nbsp;<?php _e( '<strong>Note</strong>: Capabilities are also mapped uniquely per post type', 'pps' );?></span>

<br class="clear" />

<?php
if ( ! defined( 'PPCE_VERSION' ) && pp_get_option('display_hints') ) {	
	if ( 0 === validate_plugin( "pp-compatibility/pp-compatibility.php" ) )
		$msg = __( 'To define moderation statuses, activate the PP Compatibility Pack plugin.', 'pp' );
	elseif( true == pp_key_status() )
		$msg = sprintf( __( 'To define moderation statuses, %1$sinstall%2$s the PP Compatibility Pack plugin.', 'pp' ), '<a href="admin.php?page=pp-settings&pp_tab=install">', '</a>' );
	else
		$msg = sprintf( __( 'To define moderation statuses, %1$spurchase a support key%2$s and install the PP Compatibility Pack plugin.', 'pp' ), '<a href="http://presspermit.com/purchase">', '</a>' );

	echo "<div class='pp-ext-promo'>$msg</div>";
}
?>

</div>
<?php

break;

} // end of the $doaction switch

function _pp_get_condition( $attrib, $cond ) {
	$pp_attributes = pps_init_attributes();

	if ( ! isset( $pp_attributes->attributes[$attrib] ) || ! isset( $pp_attributes->attributes[$attrib]->conditions[$cond]) )
		return false;

	return $pp_attributes->attributes[$attrib]->conditions[$cond];
}