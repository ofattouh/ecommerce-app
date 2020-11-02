<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_filter( 'pp_default_options', 'ppce_default_options' );
add_filter( 'ppx_default_options', 'ppce_default_advanced_options' );

function ppce_default_options( $def ) {
	$new = array(
		'lock_top_pages' => 0,
		'admin_others_attached_files' => 0,
		'admin_others_attached_to_readable' => 0,
		'admin_others_unattached_files' => 0,
		'edit_others_attached_files' => 0,
		'own_attachments_always_editable' => 1,
		'admin_nav_menu_filter_items' => 1,
		'admin_nav_menu_lock_custom' => 1,
		'limit_user_edit_by_level' => 1,
		'add_author_pages' => 1,
		'publish_author_pages' => 0,
		'editor_hide_html_ids' => '', //password-span; slugdiv; edit-slug-box; authordiv; commentstatusdiv; trackbacksdiv; postcustom; revisionsdiv; pageparentdiv',
		'editor_ids_sitewide_requirement' => 0,
		/*'prevent_default_forking_caps' => 0,*/
		'fork_published_only' => 0,
		'fork_require_edit_others' => 0,
		'force_taxonomy_cols' => 0,
		'default_privacy' => array(),
		'force_default_privacy' => array(),
	);
	
	return array_merge( $def, $new );
}

function ppce_default_advanced_options( $def = array() ) {
	$new = array( 
		'role_usage' => array(),	// note: this stores user-defined pattern role and direct role enable
		'non_admins_set_edit_exceptions' => 0,
	);
	
	return array_merge( $def, $new );
}
