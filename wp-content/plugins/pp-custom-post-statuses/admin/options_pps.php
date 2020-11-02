<?php
class PPS_Options {
	var $advanced_enabled;

	function __construct() {
		$this->advanced_enabled = pp_get_option( 'advanced_options' );
		add_filter( 'pp_section_captions', array( &$this, 'section_captions' ) );
		add_filter( 'pp_option_captions', array( &$this, 'option_captions' ) );
		add_filter( 'pp_option_sections', array( &$this, 'option_sections' ) );
		
		add_action( 'pp_options_ui_insertion', array( &$this, 'advanced_tab_options_ui' ), 5, 2 );  // hook for UI insertion on Settings > Advanced tab
		add_filter( 'pp_cap_descriptions', array( &$this, 'flt_cap_descriptions' ), 5 );  // priority 5 for ordering between PPS and PPCC additions in caps list
	}

	function section_captions( $sections ) {
		if ( $this->advanced_enabled ) {
			$new = array(
				'custom_statuses' => __('Custom Statuses', 'pps'),
			);
			
			$key = 'advanced';
			$sections[$key] = ( isset($sections[$key]) ) ? array_merge( $sections[$key], $new ) : $new;
		}

		return $sections;
	}
	
	function option_captions( $captions ) {
		if ( $this->advanced_enabled ) {
			$captions['custom_privacy_edit_caps'] =	__( 'Custom Privacy Statuses require status-specific editing capabilities', 'pps' );
			$captions['draft_reading_exceptions'] =	__( 'Drafts visible on front end if Reading Exception assigned', 'pps' );
			
			if ( defined('PPCE_VERSION') ) {
				$captions['supplemental_cap_moderate_any'] = __( 'Supplemental Editor Role for "standard statuses" also grants capabilities for Moderation statuses', 'pps' );
				$captions['moderation_stati_default_by_sequence'] = __( 'Publish button defaults to next moderation status (instead of highest available)', 'pps' );
			}
		}
		
		return $captions;
	}
	
	function option_sections( $sections ) {
		if ( $this->advanced_enabled ) {
			$new = array(		
				'custom_statuses' => array( 'custom_privacy_edit_caps', 'draft_reading_exceptions' ),
			);
			
			if ( defined('PPCE_VERSION') ) {
				$new['custom_statuses'][]= 'supplemental_cap_moderate_any';
				$new['custom_statuses'][]= 'moderation_stati_default_by_sequence';
			}
			
			$tab = 'advanced';
			if ( ! isset($sections[$tab]) )
				$sections[$tab] = array();
				
			foreach( array_keys( $new ) as $section )
				$sections[$tab][$section] = ( isset($sections[$tab][$section]) ) ? array_merge( $sections[$tab][$section], $new[$section] ) : $new[$section];
		}
	
		return $sections;
	}
	
	function advanced_tab_options_ui( $tab, $section ) {
		if ( ( 'advanced' == $tab ) && ( 'custom_statuses' == $section ) ) {
			global $pp_options_ui;
			
			$hint =  __('For example, should pages with custom privacy status "member" require the set_pages_member and edit_member_pages capabilities (to be supplied by a supplemental status-specific Page Editor role)?', 'pps');
			$args = ( defined( 'PP_SUPPRESS_PRIVACY_EDIT_CAPS' ) ) ? array( 'val' => 0, 'no_storage' => true, 'disabled' => true ) : array();
			$pp_options_ui->option_checkbox( 'custom_privacy_edit_caps', $tab, $section, $hint, '', $args );
			
			if ( defined( 'PPCE_VERSION' ) ) {
				$hint =  __('Note that this applies only applies if the role definition includes the pp_moderate_any capability', 'pps');
				$pp_options_ui->option_checkbox( 'supplemental_cap_moderate_any', $tab, $section, $hint );
				
				$hint =  __('Moderation sequence is defined by Permissions > Post Statuses > Moderation > Order', 'pps');
				$pp_options_ui->option_checkbox( 'moderation_stati_default_by_sequence', $tab, $section, $hint );
			}
			
			$pp_options_ui->option_checkbox( 'draft_reading_exceptions', $tab, $section );
		}
	}
	
	function flt_cap_descriptions( $pp_caps ) {
		$pp_caps['pp_define_post_status'] = __( 'Create or edit custom Privacy or Moderation statuses', 'pps' );
		$pp_caps['pp_define_moderation'] = __( 'Create or edit custom Moderation statuses', 'pps' );
		$pp_caps['pp_define_privacy'] = __( 'Create or edit custom Privacy statuses', 'pps' );
		$pp_caps['set_posts_status'] = __( 'Pertains to assignment of a custom privacy or moderation status. This capability in a WP role enables PP to assign a type-specific supplemental role with custom capabilities such as "set_pages_approved"', 'pps' );
		$pp_caps['pp_moderate_any']	= __( 'Editors can edit posts having a moderation status (i.e. Approved) without a supplemental status-specific role', 'pps' );
		
		return $pp_caps;
	}
}
