<?php
class PPCE_Options {
	function __construct() {
		add_filter( 'pp_option_tabs', array( &$this, 'option_tabs' ), 4 );
		add_filter( 'pp_section_captions', array( &$this, 'section_captions' ) );
		add_filter( 'pp_option_captions', array( &$this, 'option_captions' ) );
		add_filter( 'pp_option_sections', array( &$this, 'option_sections' ), 15 );
		
		add_action( 'pp_editing_options_pre_ui', array( &$this, 'options_pre_ui' ) );
		add_action( 'pp_editing_options_ui', array( &$this, 'options_ui' ) );
		add_action( 'pp_options_ui_insertion', array( &$this, 'advanced_tab_permissions_options_ui' ), 5, 2 ); // hook for UI insertion on Settings > Advanced tab
		
		add_filter( 'pp_cap_descriptions', array( &$this, 'flt_cap_descriptions' ), 3 );  // priority 3 for ordering before PPS and PPCC additions in caps list
	}

	function option_tabs( $tabs ) {
		$tabs['editing'] = __( 'Editing', 'ppce' );
		return $tabs;
	}

	function section_captions( $sections ) {
		$new = array(
			'content_management'=> 	  	  __('Content Management', 'ppce'),
			'page_structure' =>			  __('Page Structure', 'ppce'),
			//'custom_columns' =>			  __('Custom Columns', 'pp'),
			'limited_editing_elements' => __('Limited Editing Elements', 'ppce'),
			'media_library' => 			  __('Media Library', 'ppce'),
			'nav_menu_management' => 	  __('Nav Menu Management', 'ppce' ),
			'user_management' => 		  __('User Management', 'ppce'),
			'post_forking' =>			  __('Post Forking', 'ppce'),
		);
		
		$key = 'editing';
		$sections[$key] = ( isset($sections[$key]) ) ? array_merge( $sections[$key], $new ) : $new;
		return $sections;
	}

	function option_captions( $captions ) {
		$opt = array(
			'lock_top_pages' => 				__('Pages can be set or removed from Top Level by:', 'ppce'),
			'editor_hide_html_ids' => 			__('Limited Editing Elements', 'ppce'),
			'editor_ids_sitewide_requirement' =>__('Specified element IDs also require the following site-wide Role:', 'ppce'),
			'admin_others_attached_files' => 	__('List other users&apos; uploads if attached to an editable post', 'ppce'),
			'admin_others_attached_to_readable' => 	__('List other users&apos; uploads if attached to a readable post', 'ppce'),
			'admin_others_unattached_files' => 	__('Other users&apos; unattached uploads listed by default', 'ppce'),
			'edit_others_attached_files' =>		__('Edit other user&apos; uploads if attached to an editable post', 'ppce'),
			'own_attachments_always_editable' =>__('Users can always edit their own attachments', 'ppce'),
			'admin_nav_menu_filter_items' => 	__('List only user-editable content as available items', 'ppce' ),
			'admin_nav_menu_lock_custom' => 	__('Lock custom menu items', 'ppce' ),
			'limit_user_edit_by_level' => 		__('Limit User Edit by Level', 'ppce'),
			'default_privacy' =>				__('Default visibility for new posts:', 'ppce' ),
			'add_author_pages' =>				__('Bulk-Add Author Pages (on Users screen)', 'ppce' ),
			'publish_author_pages' =>			__('Publish Author Pages at bulk creation', 'ppce' ),
			/*'prevent_default_forking_caps' =>	__('Prevent Post Forking plugin from forcing default capabilities into WP role definitions', 'ppce'),*/
			'fork_published_only' =>			__('Fork published posts only', 'ppce' ),
			'fork_require_edit_others' =>		__('Forking enforces edit_others_posts capability', 'ppce' ),
			'force_taxonomy_cols' =>			__('Force taxonomy columns on Edit Posts screen', 'ppce' ),
			'non_admins_set_edit_exceptions' => __('Non-Administrators can set Editing Exceptions for their editable posts', 'pp'),
			//'limit_object_editors' => 			__('Limit eligible users for object-specific editing roles', 'ppce'),
		);
		
		return array_merge($captions, $opt);
	}

	function option_sections( $sections ) {
		// Editing tab
		$new = array(
			'page_structure' =>		 array( 'lock_top_pages' ),
			'user_management' => 	 array( 'limit_user_edit_by_level' ),
			'content_management' =>  array( 'default_privacy', 'force_default_privacy', 'force_taxonomy_cols', 'add_author_pages', 'publish_author_pages' ),
			'media_library' =>		 array( 'admin_others_attached_files', 'admin_others_attached_to_readable', 'admin_others_unattached_files', 'edit_others_attached_files', 'own_attachments_always_editable' ),
			'nav_menu_management' => array( 'admin_nav_menu_filter_items', 'admin_nav_menu_lock_custom' ),
			'post_forking' =>		 array( /*'prevent_default_forking_caps',*/ 'fork_published_only', 'fork_require_edit_others' ),
		);
		
		if ( pp_get_option('advanced_options') )
			$new['limited_editing_elements'] = array( 'editor_hide_html_ids', 'editor_ids_sitewide_requirement' );

		$tab = 'editing';
		$sections[$tab] = ( isset($sections[$tab]) ) ? array_merge( $sections[$tab], $new ) : $new;
		
		// Advanced tab
		$new = array( 'permissions_admin' =>   array( 'non_admins_set_edit_exceptions' ) );

		$tab = 'advanced';
		if ( ! isset($sections[$tab]) )
			$sections[$tab] = array();

		foreach( array_keys( $new ) as $section )
			$sections[$tab][$section] = ( isset($sections[$tab][$section]) ) ? array_merge( $sections[$tab][$section], $new[$section] ) : $new[$section];
		
		return $sections;
	}
	
	function options_pre_ui() {
		if ( pp_get_option( 'display_hints' ) ) :?>
		<div class="pp-optionhint">
		<?php 
			printf( __( 'Settings related to content editing permissions, provided by the %s plugin.', 'pp'), __('PP Collaborative Editing Pack', 'ppce') );
		?>
		</div>
		<?php endif;
	}
	
	function options_ui() {
		global $pp_options_ui;
		$ui = $pp_options_ui;	// shorten syntax
		$tab = 'editing';
		
		$section = 'content_management';								// --- CONTENT MANAGEMENT SECTION ---
		if ( ! empty( $ui->form_options[$tab][$section] ) ) :?>
			<tr><th scope="row"><?php echo $ui->section_captions[$tab][$section]; ?></th><td>

			<span><?php echo $ui->option_captions['default_privacy']; ?></span>
			<br />

			<div class="agp-vspaced_input default_privacy" style="margin-left: 2em;">
			<?php
			$option_name = 'default_privacy';
			$ui->all_otype_options []= $option_name;
			
			$opt_values = array_merge( array_fill_keys( pp_get_enabled_types( 'post' ), 0 ), $ui->get_option_array( $option_name ) );  // add enabled types whose settings have never been stored
			$opt_values = array_intersect_key( $opt_values, array_fill_keys( pp_get_enabled_types( 'post' ), 0 ) );	 // skip stored types that are not enabled
			$opt_values = array_diff_key( $opt_values, array_fill_keys( apply_filters( 'pp_disabled_default_privacy_types', array( 'forum', 'topic', 'reply' ) ), true ) );
			
			if ( defined( 'PPS_VERSION' ) ) {
				$do_force_option = true;
				$ui->all_otype_options []= 'force_default_privacy';
				$force_values = array_merge( array_fill_keys( pp_get_enabled_types( 'post' ), 0 ), $ui->get_option_array( 'force_default_privacy' ) );  // add enabled types whose settings have never been stored
			} else
				$do_force_option = false;
			?>
			<table class='agp-vtight_input agp-rlabel'>
			<?php
			
			foreach ( $opt_values as $object_type => $setting ) :
				if ( 'attachment' == $object_type ) continue;
				
				$id = $option_name . '-' . $object_type;
				$name = "{$option_name}[$object_type]";
				?>
				<tr><td class="rlabel">
				<input name='<?php echo $name;?>' type='hidden' value='' />
				<label for='<?php echo $id;?>'><?php echo ( $type_obj = get_post_type_object( $object_type ) ) ? $type_obj->labels->name : $object_type; ?></label>
				</td>
				
				<td><select name='<?php echo $name;?>' id='<?php echo $id;?>'>
				<option value=''><?php _e('Public');?></option>
				<?php foreach( get_post_stati( array( 'private' => true ), 'object' ) as $status_obj ) :
					$selected = ( $setting === $status_obj->name ) ? ' selected="selected"' : '';
					?>
					<option value='<?php echo $status_obj->name;?>'<?php echo $selected;?>><?php echo $status_obj->label;?></option>
				<?php endforeach; ?>
				</select>
				<?php 
				if( $do_force_option ) :?>
					<?php
					$id = 'force_default_privacy-' . $object_type;
					$name = "force_default_privacy[$object_type]";
					$style = ( $setting ) ? '' : ' style="display:none"';
					$checked = ( ! empty($force_values[$object_type]) ) ? 'checked="checked" ' : '';
					?>
					<input name='<?php echo $name;?>' type='hidden' value='0' />
					&nbsp;<label<?php echo $style;?> for="<?php echo $id;?>"><input type="checkbox" <?php echo $checked;?>id="<?php echo $id;?>" name="<?php echo $name;?>" value="1" /><?php if( $do_force_option ): ?>&nbsp;<?php _e('force', 'ppce');?><?php endif;?></label>
				<?php endif; ?>
				
				</td></tr>
			<?php endforeach; ?>
			</table>
			</div>
			
			<script type="text/javascript">
			/* <![CDATA[ */
			jQuery(document).ready( function($) {
				$('div.default_privacy select').click( function() {
					$(this).parent().find('label').toggle( $(this).val() != '' );
				});
			});
			/* ]]> */
			</script>
			
			<br />
			<?php
			$hint = __( 'Display a custom column on Edit Posts screen for all related taxonomies which are enabled for PP filtering.', 'ppce' );
			$ui->option_checkbox( 'force_taxonomy_cols', $tab, $section, $hint, '<br />' );
			
			$hint = __( 'Allows creation of a new post (of any type) for each selected user, using an existing post as the pattern.', 'ppce' );
			$ui->option_checkbox( 'add_author_pages', $tab, $section, $hint, '' );
			
			$ui->option_checkbox( 'publish_author_pages', $tab, $section, '', '' );
			
			/*
			$hint = __('If enabled, Post Author and Page Author selection dropdowns will be filtered based on scoped roles.', 'ppce');
			$ui->option_checkbox( 'filter_users_dropdown', $tab, $section, $hint, '' );	
			*/
			?>
			</td>
			</tr>
		<?php endif; // any options accessable in this section
		
		
		$section = 'page_structure';									// --- PAGE STRUCTURE SECTION ---
		if ( ! empty( $ui->form_options[$tab][$section] ) ) : ?>
			<tr><th scope="row"><?php echo $ui->section_captions[$tab][$section];?></th><td>
			<?php
			$id = 'lock_top_pages';
			$ui->all_options []= $id;
			$current_setting = strval( $ui->get_option($id) );  // force setting and corresponding keys to string, to avoid quirks with integer keys

			echo $ui->option_captions['lock_top_pages'];

			$captions = array( 'no_parent_filter' => __( 'no Page Parent filter' , 'ppce' ), 'author' => __('Page Authors, Editors and Administrators', 'ppce'), '' => __('Page Editors and Administrators', 'ppce'), '1' => __('Administrators', 'ppce') );
			
			foreach ( $captions as $key => $value) {
				$key = strval($key);
				echo "<div style='margin: 0 0 0.5em 2em;'><label for='{$id}_{$key}'>";
				$checked = ( $current_setting === $key ) ? "checked='checked'" : '';
			
				echo "<input name='$id' type='radio' id='{$id}_{$key}' value='$key' $checked /> ";
				echo $value;
				echo '</label></div>';
			}
			
			echo '<span class="pp-subtext">';
			if ( $ui->display_hints )
				_e('Users who do not meet this site-wide role requirement may still be able to save and/or publish pages, but will not be able to publish a new page with a Page Parent setting of "Main Page".  Nor will they be able to move a currently published page from "Main Page" to a different Page Parent.', 'ppce');
			
			echo '</span>';
			?>

			</td></tr>
		<?php endif; // any options accessable in this section
		
		
		$section = 'limited_editing_elements';							// --- LIMITED EDITING ELEMENTS SECTION ---
		if ( ! empty( $ui->form_options[$tab][$section] ) ) : ?>
			<tr><th scope="row"><?php echo $ui->section_captions[$tab][$section];		?></th><td>
			<?php if ( in_array( 'editor_hide_html_ids', $ui->form_options[$tab][$section] ) ) :?>
				<div class="agp-vspaced_input">
				<?php
					if ( $ui->display_hints) {
						echo ('<div class="agp-vspaced_input">');
						_e('Remove Edit Form elements with these html IDs from users who do not have full editing capabilities for the post/page. Separate with&nbsp;;', 'ppce');
						echo '</div>';
					}
				?>
				</div>
				<?php
				$option_name = 'editor_hide_html_ids';
				$ui->all_options []= $option_name;
				
				$opt_val = $ui->get_option( $option_name );

				// note: 'post:post' otype option is used for all non-page types
				$sample_ids = '<span id="pp_sample_ids" class="pp-gray" style="display:none">' . 'password-span; slugdiv; edit-slug-box; authordiv; commentstatusdiv; trackbacksdiv; postcustom; revisionsdiv; pageparentdiv;' . '</span>';

				echo('<div class="agp-vspaced_input">');
				echo('<span class="pp-vtight">');
				_e('Edit Form HTML IDs:', 'ppce');
				?>
				<label for="<?php echo($option_name);?>">
				<input name="<?php echo($option_name);?>" type="text" size="45" style="width: 95%" id="<?php echo($option_name);?>" value="<?php echo($opt_val);?>" />
				</label>
				</span>
				<br />
				<?php
					$js_call = "jQuery(document).ready(function($){ $('#pp_sample_ids').show(); });";
					printf(__('%1$s sample IDs:%2$s %3$s', 'ppce'), "<a href='javascript:void(0)' onclick=\"$js_call\">", '</a>', $sample_ids );
				?>
				</div>
				<br />
			<?php endif;?>
			
			<?php if ( in_array( 'editor_ids_sitewide_requirement', $ui->form_options[$tab][$section] ) ) :
				$id = 'editor_ids_sitewide_requirement';
				$ui->all_options []= $id;
				
				if ( ! $current_setting = strval( $ui->get_option($id) ) )  // force setting and corresponding keys to string, to avoid quirks with integer keys
					$current_setting = '0';
				?>
				<div class="agp-vspaced_input">
				<?php
				_e('Specified element IDs also require the following site-wide Role:', 'ppce');

				$admin_caption = ( ! empty($custom_content_admin_cap) ) ? __('Content Administrator', 'ppce') : _pp_('Administrator');
				
				$captions = array( '0' => __('no requirement', 'ppce'), '1' => __('Contributor / Author / Editor', 'ppce'), 'author' => __('Author / Editor', 'ppce'), 'editor' => _pp_('Editor'), 'admin_content' => __('Content Administrator', 'ppce'), 'admin_user' => __('User Administrator', 'ppce'), 'admin_option' => __('Option Administrator', 'ppce') );
				
				foreach ( $captions as $key => $value) {
					$key = strval($key);
					echo "<div style='margin: 0 0 0.5em 2em;'><label for='{$id}_{$key}'>";
					$checked = ( $current_setting === $key ) ? "checked='checked'" : '';
				
					echo "<input name='$id' type='radio' id='{$id}_{$key}' value='$key' $checked /> ";
					echo $value;
					echo '</label></div>';
				}
				?>
				</div>
			<?php endif;?>
				
			</td>
			</tr>
		<?php endif; // any options accessable in this section

		
		$section = 'media_library';										// --- MEDIA LIBRARY SECTION ---
		if ( ! empty( $ui->form_options[$tab][$section] ) ) :?>
			<tr><th scope="row"><?php echo $ui->section_captions[$tab][$section]; ?></th><td>
			<?php
			
			if ( defined( 'PP_MEDIA_LIB_UNFILTERED' ) ) :?>
				<p><span class="pp-important">
				<?php _e( 'The following settings are currently overridden by the constant PP_MEDIA_LIB_UNFILTERED (defined in wp-config.php or some other file you maintain). Media Library access will not be altered by Press Permit exceptions.', 'pp' );?>
				</span></p>
			<?php else: ?>
				<p><span style="font-weight:bold">
				<?php _e( 'The following settings apply to users who have the upload_files or edit_files capability:', 'pp' );?>
				</span></p>
			<?php endif;
			
			$hint = __("For non-Administrators, determines visibility of files uploaded by another user and now attached to a post which the logged user can read. To force a user to view all media regardless of this setting, add the pp_list_all_files capability to their role.", 'ppce');
			$ret = $ui->option_checkbox( 'admin_others_attached_to_readable', $tab, $section, $hint, '' );	
			
			$hint = __("For non-Administrators, determines visibility of files uploaded by another user and now attached to a post which the logged user can edit. To force a user to view all media regardless of this setting, add the pp_list_all_files capability to their role.", 'ppce');
			$ret = $ui->option_checkbox( 'admin_others_attached_files', $tab, $section, $hint, '' );	
			
			$hint = __("For non-Administrators, determines editing access to files uploaded by another user and now attached to a post which the logged user can edit.", 'ppce');
			$ret = $ui->option_checkbox( 'edit_others_attached_files', $tab, $section, $hint, '' );	
			
			$hint = __("If enabled, all users who have Media Library access will be implicitly granted the list_others_unattached_files capability. Media Editors can view and edit regardless of this setting.", 'ppce');
			$ret = $ui->option_checkbox( 'admin_others_unattached_files', $tab, $section, $hint, '' );	
			
			$hint = __("Ensures users can always edit attachments they have uploaded, even if they are later attached to a post which the user cannot edit. If disabled, you can grant individual users the edit_own_attachments capability or assign Media editing Exceptions for individual files.", 'ppce');
			$ret = $ui->option_checkbox( 'own_attachments_always_editable', $tab, $section, $hint, '' );	
			?>
			</td>
			</tr>
		<?php endif; // any options accessable in this section
		
		
		$section = 'nav_menu_management';								// --- NAV MENU MANAGEMENT SECTION ---
		if ( ! empty( $ui->form_options[$tab][$section] ) ) : ?>
			<tr><th scope="row"><?php 				echo $ui->section_captions[$tab][$section];		?></th><td>
			<?php
			$hint = '';
			$ui->option_checkbox( 'admin_nav_menu_filter_items', $tab, $section, $hint, '', array( 'val' => true, 'disabled' => true ) );
			
			$hint = __('Prevent creation or editing of custom items for non-Administrators who lack edit_theme_options capability.', 'pp');
			$ui->option_checkbox( 'admin_nav_menu_lock_custom', $tab, $section, $hint, '' );
			?>
			</td></tr>
		<?php endif; // any options accessable in this section
		
		
		$section = 'user_management';									// --- USER MANAGEMENT SECTION ---
		if ( ! empty( $ui->form_options[$tab][$section] ) ) : ?>
			<tr><th scope="row"><?php echo $ui->section_captions[$tab][$section];?></th><td>
			<?php
			$hint =  __('If enabled, prevents those with edit_users capability from editing a user with a higher level or assigning a role higher than their own.', 'ppce');
			$ui->option_checkbox( 'limit_user_edit_by_level', $tab, $section, $hint, '' );
			?>
			</td></tr>
		<?php endif; // any options accessable in this section
		
		
		if ( class_exists( 'Fork', false ) ) {
			$section = 'post_forking';										// --- POST FORKING SECTION ---
			if ( ! empty( $ui->form_options[$tab][$section] ) ) : ?>
				<tr><th scope="row"><?php echo $ui->section_captions[$tab][$section];?></th><td>
				<?php
				$hint =  __('Fork published posts only.', 'ppce');
				$ui->option_checkbox( 'fork_published_only', $tab, $section, $hint, '' );
				
				$hint =  __('If a user lacks the edit_others_posts capability for the post type, they cannot fork other&apos;s posts either.', 'ppce');
				$ui->option_checkbox( 'fork_require_edit_others', $tab, $section, $hint, '' );
				
				//$hint =  __('Enable this setting if you have used Capability Manager Enhanced to add/remove fork_posts or merge_posts capabilities from roles and/or want to use supplemental roles.', 'ppce');
				//$ui->option_checkbox( 'prevent_default_forking_caps', $tab, $section, $hint, '' );
				?>
				</td></tr>
			<?php endif; // any options accessable in this section
		}
	} // end function options_ui()
	
	function advanced_tab_permissions_options_ui( $tab, $section ) {			
		if ( ( 'advanced' == $tab ) && ( 'permissions_admin' == $section ) ) {
			global $pp_options_ui;

			$hint =  __('If enabled, presence of the pp_set_edit_exceptions, pp_set_associate_exceptions, etc. capabilities in the WP role will be honored. See list of capabilities below.', 'ppce');
			$pp_options_ui->option_checkbox( 'non_admins_set_edit_exceptions', 'advanced', 'permissions_admin', $hint );
		}
	}
	
	function flt_cap_descriptions( $pp_caps ) {
		if ( class_exists('Fork', false ) )
			$pp_caps['pp_set_fork_exceptions'] = __( 'Set Forking Exceptions on Edit Post/Term screen (where applicable)', 'ppce' );

		if ( defined('RVY_VERSION') )
			$pp_caps['pp_set_revise_exceptions'] = __( 'Set Forking Exceptions on Edit Post/Term screen (where applicable)', 'ppce' );

		$pp_caps['pp_set_edit_exceptions'] = 			__( 'Set Editing Exceptions on Edit Post/Term screen (where applicable)', 'ppce' );
		$pp_caps['pp_set_associate_exceptions'] = 		__( 'Set Association (Parent) Exceptions on Edit Post screen (where applicable)', 'ppce' );
		$pp_caps['pp_set_term_assign_exceptions'] = 	__( 'Set Term Assignment Exceptions on Edit Term screen (in relation to an editable post type)', 'ppce' );
		$pp_caps['pp_set_term_manage_exceptions'] = 	__( 'Set Term Management Exceptions on Edit Term screen', 'ppce' );
		$pp_caps['pp_set_term_associate_exceptions'] = 	__( 'Set Term Association (Parent) Exceptions on Edit Term screen', 'ppce' );
		
		$pp_caps['edit_own_attachments'] = __( 'Edit own file uploads, even if they become attached to an uneditable post', 'ppce' );
		$pp_caps['list_others_unattached_files'] = __( 'See other user&apos;s unattached file uploads in Media Library', 'ppce' );
		$pp_caps['pp_associate_any_page'] = __( 'Disregard association exceptions (for all hierarchical post types)', 'ppce' );
		
		$pp_caps['pp_list_all_files'] = __( 'Do not alter the Media Library listing provided by WordPress', 'ppce' );
		$pp_caps['pp_force_quick_edit'] = __( 'Make Quick Edit and Bulk Edit available to non-Administrators even though some inappropriate selections may be possible', 'ppce' );

		return $pp_caps;
	}
}
