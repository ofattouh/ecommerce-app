<?php
add_filter( 'ppx_default_options', 'pps_default_advanced_options' );

function pps_default_advanced_options( $def = array() ) {
	$new = array(
		'custom_privacy_edit_caps' => 1,
		'supplemental_cap_moderate_any' => 0,
		'moderation_stati_default_by_sequence' => 0,
		'draft_reading_exceptions' => 0,
	);

	return array_merge( $def, $new );
}
