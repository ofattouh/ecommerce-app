<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_action( 'add_meta_boxes', array( 'PPS_PostEditUI', 'act_comments_metabox'), 10, 2 );
add_action( 'add_meta_boxes', array( 'PPS_PostEditUI', 'act_replace_publish_metabox'), 10, 2 );
add_action( 'admin_head', array( 'PPS_PostEditUI', 'act_object_edit_scripts'), 99 );	// needs to load after post.js to unbind handlers
add_action( 'admin_print_footer_scripts', array( 'PPS_PostEditUI', 'act_force_visibility_js'), 99 );

if ( $post_type = pp_find_post_type() )
	add_filter( "get_user_option_meta-box-order_{$post_type}", array( 'PPS_Submit_Metabox', 'flt_metabox_order' ), 10, 3 );

if ( ! empty($_REQUEST['message']) && ( 6 == $_REQUEST['message'] ) )
	add_filter( 'post_updated_messages', array( 'PPS_PostEditUI', 'flt_post_updated_messages' ), 50 );

function _pps_post_submit_meta_box( $post, $args = array() ) {
	PPS_Submit_Metabox::post_submit_meta_box( $post, $args );
}

class PPS_Submit_Metabox {
	public static function flt_metabox_order( $result, $option, $user ) {
		if ( isset($result['side']) )
			$result['side'] = str_replace( 'submitdiv_pps', 'submitdiv', $result['side'] );

		return $result;
	}

	public static function post_submit_meta_box( $post, $args = array() ) {
		global $pp_current_user;
		
		$is_administrator = pp_is_content_administrator();
		$type_obj = get_post_type_object($post->post_type);
		$post_status = $post->post_status;
		
		if ( 'auto-draft' == $post_status )
			$post_status = 'draft';
		
		if ( ! $post_status_obj = get_post_status_object($post_status) )
			$post_status_obj = get_post_status_object( 'draft' );
		
		$moderation_stati = apply_filters( 'pp_order_types', pp_get_post_stati( array( 'moderation' => true, 'internal' => false, 'post_type' => $post->post_type ), 'object' ), array( 'order_property' => 'order' ) );
		unset( $moderation_stati['future'] );
		
		$pp_attributes = pps_init_attributes();

		if ( ! isset($type_obj->cap->set_posts_status) )
			$type_obj->cap->set_posts_status = $type_obj->cap->publish_posts;
		
		foreach( array_keys($moderation_stati) as $_status ) { 
			$check_caps = $pp_attributes->get_condition_caps( $type_obj->cap->set_posts_status, $post->post_type, 'post_status', $_status );
			
			$can_set_status[$_status] = $is_administrator || ! array_diff( $check_caps, array_keys($pp_current_user->allcaps) ) || in_array( 'pp_moderate_any', array_keys($pp_current_user->allcaps) );
			
		if ( ( $_status != $post_status ) && ! empty( $type_obj->cap->set_posts_status ) && ! $is_administrator && ( 'pending' != $_status || ( pp_get_option( 'custom_pending_caps' ) && ! defined( 'PP_LEGACY_PENDING_STATUS' ) ) ) ) {  // NOTE: pending is currently settable with basic editing caps even if custom capabilities are enabled for it, so supression of pending is a UI guidance function
				if ( ! $can_set_status[$_status] ) {
					unset( $moderation_stati[$_status] );
				}
			}
		}
		
		$moderation_stati = apply_filters( 'pp_available_moderation_stati', $moderation_stati, $post );

		$can_publish = current_user_can($type_obj->cap->publish_posts);
		$_args = compact( 'is_administrator', 'type_obj', 'post_status_obj', 'can_publish', 'moderation_stati', 'can_set_status' );
		$_args = array_merge( $args, $_args );  // in case args passed into metabox are needed within static calls in the future
		?>
		<div class="submitbox" id="submitpost">

		<div id="minor-publishing">
			<div id="minor-publishing-actions">
			<div id="save-action">
			<?php self::post_save_button( $post, $_args ); ?>
			</div>
			<div id="preview-action">
			<?php self::post_preview_button( $post, $_args );?>
			</div>
			<div class="clear"></div>
			</div><?php // minor-publishing-actions ?>

			<div id="misc-publishing-actions">
			<div class="misc-pub-section">
			<?php self::post_status_display( $post, $_args ); ?>
			</div>
			<div class="misc-pub-section " id="visibility">
			<?php self::post_visibility_display( $post, $_args ); ?>
			</div>

			<?php if ( $type_obj->hierarchical ) : 
				$pp_attributes = pps_init_attributes();
				$ch_visibility = $pp_attributes->get_item_condition( 'post', 'force_visibility', array( 'assign_for' => 'children', 'id' => $post->ID ) );
				?>
				<div class="misc-pub-section<?php if (! $ch_visibility) echo ' hide-if-js';?>" id="ch_visibility" title="<?php printf( __('force visibility of all sub%s', 'pps'), strtolower($type_obj->labels->name) );?>">
				<?php 
				$_args['ch_visibility'] = $ch_visibility;
				self::subpost_visibility_display( $post, $_args ); ?>
				</div>
			<?php endif; ?>

			<?php do_action('post_submitbox_misc_sections'); ?>

			<?php if ( pp_wp_ver( '3.6-dev' ) ) :
				if ( ! empty( $args['args']['revisions_count'] ) ) :
					$revisions_to_keep = wp_revisions_to_keep( $post );
				?>
				<div class="misc-pub-section num-revisions">
				<?php
					if ( $revisions_to_keep > 0 && $revisions_to_keep <= $args['args']['revisions_count'] ) {
						echo '<span title="' . esc_attr( sprintf( __( 'Your site is configured to keep only the last %s revisions.' ),
							number_format_i18n( $revisions_to_keep ) ) ) . '">';
						printf( __( 'Revisions: %s' ), '<b>' . number_format_i18n( $args['args']['revisions_count'] ) . '+</b>' );
						echo '</span>';
					} else {
						printf( __( 'Revisions: %s' ), '<b>' . number_format_i18n( $args['args']['revisions_count'] ) . '</b>' );
					}
				?>
					<a class="hide-if-no-js" href="<?php echo esc_url( get_edit_post_link( $args['args']['revision_id'] ) ); ?>"><?php _ex( 'Browse', 'revisions' ); ?></a>
				</div>
				<?php endif;
			endif; ?>
			
			<?php
			if ( $can_publish ) : // Contributors don't get to choose the date of publish ?>
			<div class="misc-pub-section curtime misc-pub-section-last">
				<?php self::post_time_display( $post, $_args ); ?>
			</div>
			<?php endif; ?>

			<?php do_action('post_submitbox_misc_actions'); ?>
			</div> <?php // misc-publishing-actions ?>
			
			<div class="clear"></div>
		</div> <?php // minor-publishing ?>

		<div id="major-publishing-actions">
			<?php do_action('post_submitbox_start'); ?>
			<div id="delete-action">
			<?php // PP: no change from WP core
			if ( current_user_can( "delete_post", $post->ID ) ) {
				if ( !EMPTY_TRASH_DAYS )
					$delete_text = _pp_('Delete Permanently');
				else
					$delete_text = _pp_('Move to Trash');
				?>
			<a class="submitdelete deletion" href="<?php echo get_delete_post_link($post->ID); ?>"><?php echo $delete_text; ?></a><?php
			} ?>
			</div>

			<div id="publishing-action">
			<?php self::post_publish_ui( $post, $_args ); ?>
			</div>
			<div class="clear"></div>
		</div> <?php // major-publishing-actions ?>

		</div> <?php // submitpost ?>

		<?php
	} // end function _pps_post_submit_meta_box()

	public static function post_save_button( $post, $args ) {
		extract( $args, EXTR_SKIP );  // $is_administrator, $type_obj, $post_status_obj, $can_publish, $moderation_stati, $can_set_status
	?>
		<?php //if ( 'publish' != $post_status && 'future' != $post_status && 'pending' != $post_status )  { 
		if ( ! $post_status_obj->public && ! $post_status_obj->private && ! $post_status_obj->moderation && ( 'future' != $post_status_obj->name ) ) : ?> <?php  // @todo: confirm we don't need a hidden save button when current status is private */
			$draft_status_obj = get_post_status_object( 'draft' );
		?>
			<input type="submit" name="save" id="save-post" value="<?php echo $draft_status_obj->labels->save_as ?>" tabindex="4" class="button button-highlighted" />
		<?php elseif ( $post_status_obj->moderation ) :?>
			<input type="submit" name="save" id="save-post" value="<?php echo $post_status_obj->labels->save_as ?>" tabindex="4" class="button button-highlighted" />
		<?php else :?>
			<input type="submit" name="save" id="save-post" value="<?php esc_attr_e('Save'); ?>" class="button button-highlighted" style="display:none" />
		<?php endif; ?>

		<span class="spinner"></span>
	<?php
	}	

	public static function post_preview_button( $post, $args ) {
		extract( $args, EXTR_SKIP );  // $is_administrator, $type_obj, $post_status_obj, $can_publish, $moderation_stati, $can_set_status
	?>
		<?php
		if ( $post_status_obj->public ) {
			$preview_link = esc_url(get_permalink($post->ID));
			$preview_button = _pp_('Preview Changes');
		} else {
			$preview_link = esc_url(apply_filters('preview_post_link', add_query_arg('preview', 'true', get_permalink($post->ID))));
			$preview_button = _pp_('Preview');
		}
		?>
		<a class="preview button" href="<?php echo $preview_link; ?>" target="wp-preview" id="post-preview" tabindex="4"><?php echo $preview_button; ?></a>
		<input type="hidden" name="wp-preview" id="wp-preview" value="" />
	<?php
	}

	public static function post_status_display( $post, $args ) {
		extract( $args, EXTR_SKIP );  // $is_administrator, $type_obj, $post_status_obj, $can_publish, $moderation_stati, $can_set_status
		?>
		<label for="post_status"><?php echo _pp_('Status:'); ?></label>
		<?php
		$post_status = $post_status_obj->name;
	?>
		<span id="post-status-display">
		<?php
		if ( $post_status_obj->private )
			echo( _pp_( 'Privately Published' ) );
		elseif ( $post_status_obj->private )
			echo( _pp_( 'Published' ) );
		elseif ( ! empty($post_status_obj->labels->caption) )
			echo $post_status_obj->labels->caption;
		else
			echo $post_status_obj->label;
	?>
		</span>&nbsp;
	<?php
		$select_moderation = ( count($moderation_stati) > 1 || ( $post_status != key($moderation_stati) ) );  // multiple moderation stati are selectable or a single non-current moderation stati is selectable

		if ( $post_status_obj->public || $post_status_obj->private || $can_publish || $select_moderation ) { ?>
			<a href="#post_status" <?php if ( $post_status_obj->private || ( $post_status_obj->public && 'publish' != $post_status ) ) { ?>style="display:none;" <?php } ?>class="edit-post-status hide-if-no-js" tabindex='4'><?php echo _pp_('Edit') ?></a>
	<?php
		if ( current_user_can( 'pp_create_groups' ) ) : 
			$url = admin_url('admin.php?page=pp-groups');
	?>
			<span style="float:right; margin-top: -5px;">
			<a href="<?php echo $url;?>" class="visibility-customize pp-submitbox-customize" target="_blank"><img src="<?php echo( constant('PP_URLPATH') . '/admin/images/users-24.png' );?>" title="<?php _e('Define Permission Groups');?>" alt="<?php _e('groups', 'pp');?>"></a>
			</span>
		<?php endif; ?>
		
		<div id="post-status-select" class="hide-if-js">
		<input type="hidden" name="hidden_post_status" id="hidden_post_status" value="<?php echo $post_status; ?>" />
		<select name='post_status' id='post_status' tabindex='4'>

		<?php if ( $post_status_obj->public || $post_status_obj->private || ( 'future' == $post_status ) ) : ?>
			<option<?php selected( true, true ); ?> value='publish'><?php echo $post_status_obj->labels->caption ?></option>
		<?php endif; ?>

		<?php 
		foreach( $moderation_stati as $_status => $_status_obj ) : ?>
			<option<?php selected( $post_status, $_status ); ?> value='<?php echo $_status ?>'><?php echo $_status_obj->labels->caption ?></option>
		<?php endforeach ?>

		<?php 
		$draft_status_obj = get_post_status_object( 'draft' );
		?>
		<option<?php selected( $post_status, 'draft' ); ?> value='draft'><?php echo $draft_status_obj->label ?></option>

		</select>
		 <a href="#post_status" class="save-post-status hide-if-no-js button"><?php echo _pp_('OK'); ?></a>
		 <a href="#post_status" class="cancel-post-status hide-if-no-js"><?php echo _pp_('Cancel'); ?></a>
		<?php
			if ( ( 'draft' == $post_status_obj->name || $post_status_obj->moderation ) && ( current_user_can( 'pp_define_post_status' ) || current_user_can( 'pp_define_moderation' ) ) ) {
				$url = admin_url('admin.php?page=pp-stati&amp;attrib_type=moderation');
				echo "<br /><a href='$url' class='pp-postsubmit-add-privacy' target='_blank'>" . __( 'add moderation status', 'pps' ) . '</a>';
			} 
		?>
		</div>

		<?php } // endif status editable
	}

	public static function post_visibility_display( $post, $args ) {
		extract( $args, EXTR_SKIP );  // $is_administrator, $type_obj, $post_status_obj, $can_publish, $moderation_stati, $can_set_status
		
		$pp_attributes = pps_init_attributes();
		
		echo _pp_('Visibility:'); ?>
		<span id="post-visibility-display"><?php

		if ( 'future' == $post_status_obj->name ) {	// indicate eventual visibility of scheduled post
			if ( ! $vis_status = get_post_meta( $post->ID, '_scheduled_status', true ) )
				$vis_status = 'publish';	

			$vis_status_obj = get_post_status_object( $vis_status );
		} else {
			$vis_status = $post_status_obj->name;
			$vis_status_obj = $post_status_obj;
		}

		if ( $vis_status_obj->private ) {
			$visibility = $vis_status;
			$post->post_password = '';
			$visibility_trans = $vis_status_obj->labels->visibility;
		} elseif ( !empty( $post->post_password ) ) {
			$visibility = 'password';
			$visibility_trans = _pp_('Password protected');
		} elseif ( 'publish' == $vis_status ) {
			$post->post_password = '';
			$visibility = 'public';
			
			if ( ( 'post' == $post->post_type || post_type_supports( $post->post_type, 'sticky' ) ) && is_sticky( $post->ID ) ) {
				$visibility_trans = _pp_('Public, Sticky');
			} else {
				$visibility_trans = _pp_('Public');
			}
		} elseif ( $vis_status_obj->public  ) {
			$post->post_password = '';
			$visibility = $vis_status;

			if ( ( 'post' == $post->post_type || post_type_supports( $post->post_type, 'sticky' ) ) && is_sticky( $post->ID ) ) {
				$visibility_trans = sprintf( __('%s, Sticky', 'pps'), $vis_status_obj->label );
			} else {
				$visibility_trans = $vis_status_obj->labels->visibility;
			}
		} else {
			$visibility = 'public';
			$visibility_trans = _pp_('Public');
		}

		echo esc_html( $visibility_trans ); ?>
		</span>
		
		<?php if ( $can_publish ) { ?>
		<a href="#visibility" class="edit-visibility hide-if-no-js"><?php echo _pp_('Edit'); ?></a>

		<div id="post-visibility-select" class="hide-if-js">
		<input type="hidden" name="hidden_post_password" id="hidden-post-password" value="<?php echo esc_attr($post->post_password); ?>" />
		<?php if ( post_type_supports( $post->post_type, 'sticky' ) ): ?>
		<input type="checkbox" style="display:none" name="hidden_post_sticky" id="hidden-post-sticky" value="sticky" <?php checked(is_sticky($post->ID)); ?> />
		<?php endif; ?>
		<input type="hidden" name="hidden_post_visibility" id="hidden-post-visibility" value="<?php echo esc_attr( $visibility ); ?>" />

		<input type="radio" name="visibility" id="visibility-radio-public" value="public" <?php checked( $visibility, 'public' ); ?> /> <label for="visibility-radio-public" class="selectit"><?php echo _pp_('Public'); ?></label><br />

		<?php 
		if ( ( ( $post->post_type == 'post' ) || post_type_supports( $post->post_type, 'sticky' ) ) && current_user_can( 'edit_others_posts' ) ) : ?>
		<span id="sticky-span"><input id="sticky" name="sticky" type="checkbox" value="sticky" <?php checked(is_sticky($post->ID)); ?> tabindex="4" /> <label for="sticky" class="selectit"><?php echo _pp_('Stick this to the front page') ?></label><br /></span>
		<?php endif; ?>

		<input type="radio" name="visibility" id="visibility-radio-password" value="password" <?php checked( $visibility, 'password' ); ?> /> <label for="visibility-radio-password" class="selectit"><?php echo _pp_('Password protected'); ?></label><br />
		<span id="password-span"><label for="post_password"><?php echo _pp_('Password:'); ?></label> <input type="text" name="post_password" id="post_password" value="<?php echo esc_attr($post->post_password); ?>" /><br /></span>

		<?php if ( $_status_obj = get_post_status_object('private') ) : ?>
		<input type="radio" name="visibility" id="visibility-radio-private" value="private" <?php checked( $visibility, 'private' ); ?> /> <label for="visibility-radio-private" class="selectit"><?php echo $_status_obj->label ?></label>
		<br />
		<?php endif;?>

		<?php
		$i = 0;
		$pvt_stati = apply_filters( 'pp_order_types', pp_get_post_stati( array( 'private' => true, 'post_type' => $post->post_type ), 'object' ), array( 'order_property' => 'label' ) );
		foreach( $pvt_stati as $_status => $status_obj ) :
			$i++;

			if ( 'private' == $_status )
				continue;

			if ( ! $is_administrator ) {
				if ( empty( $type_obj->cap->set_posts_status ) ) {
					$set_status_cap = $type_obj->cap->publish_posts;
				} else {
				$_caps = $pp_attributes->get_condition_caps( $type_obj->cap->set_posts_status, $post->post_type, 'post_status', $_status );
					if ( ! $set_status_cap = reset( $_caps ) ) {
						$set_status_cap = $type_obj->cap->set_posts_status;
					}
				}

				if ( ! current_user_can( $set_status_cap ) )
					continue;
			}
		?>
		<input type="radio" name="visibility" class="pvt-custom" id="visibility-radio-<?php echo $_status ?>" value="<?php echo $_status ?>" <?php checked( $visibility, $_status ); ?> /> <label for="visibility-radio-<?php echo $_status ?>" class="selectit"><?php echo $status_obj->label ?></label>
		
		<?php 
		if ( $i == count($pvt_stati) ) {
			if ( ( current_user_can( 'pp_define_post_status' ) || current_user_can( 'pp_define_privacy' ) ) ) {
				$url = admin_url('admin.php?page=pp-stati&amp;attrib_type=private');
				echo "<a href='$url' class='pp-postsubmit-add-privacy' target='_blank'>" . __( 'define privacy types', 'pps' ) . '</a>';
			}
		}
		?>
		<br />	
		<?php 
		endforeach;
		
		?>
		<?php if ( $type_obj->hierarchical ) : ?>
		<p>
		<span id="pp-propagate-privacy-span"><input id="pp-propagate-privacy" name="pp-propagate-privacy" class="pp-submitbox-customize " type="checkbox" value="1" /><label for="pp-propagate-privacy" class="selectit"> <?php printf( __('mirror selection to %1$s sub%2$s%3$s', 'pps'), '<a href="#child-visibility" class="pp-edit-ch-visibility">', strtolower( $type_obj->labels->name ), '</a>' ); ?></label>
		</span>
		</p>
		<?php endif;?>
		<p>
		 <a href="#visibility" class="save-post-visibility hide-if-no-js button"><?php echo _pp_('OK'); ?></a>
		 <a href="#visibility" class="cancel-post-visibility hide-if-no-js"><?php echo _pp_('Cancel'); ?></a>
		</p>
		</div>
		<?php }
	}

	public static function subpost_visibility_display( $post, $args ) {
		extract( $args, EXTR_SKIP );  // $is_administrator, $type_obj, $post_status_obj, $can_publish, $moderation_stati, $can_set_status, $ch_visibility
		
		$pp_attributes = pps_init_attributes();
		
		printf( _x( 'Sub%1$s %2$s: ', 'restriction_attribute', 'pps'), strtolower($type_obj->labels->singular_name), _pp_('Visibility') ); ?> <span id="ch_post-visibility-display"><?php

		if ( $ch_visibility ) {
			$child_status_obj = get_post_status_object($ch_visibility);
			$visibility_trans = $child_status_obj->labels->visibility;
		} else {
			$visibility_trans = __( '(manual)', 'pps' );
		}

		echo esc_html( $visibility_trans ); ?></span>
		<?php if ( $can_publish ) { ?>
		<a href="#ch_visibility" class="ch_edit-visibility hide-if-no-js"><?php echo _pp_('Edit'); ?></a>

		<div id="ch_post-visibility-select" class="hide-if-js item-condition-select ">
		<input type="hidden" name="ch_hidden_post_visibility" id="ch_hidden-post-visibility" value="<?php echo esc_attr( $ch_visibility ); ?>" />

		<input type="radio" name="ch_visibility" id="ch_visibility-radio-manual" value="" <?php checked( $ch_visibility, '' ); ?> /> <label for="ch_visibility-radio-manual" class="selectit" title="<?php _e( 'visibility of subpages set individually', 'pps' );?>"><?php _e('(manual)', 'pps'); ?></label><br />

		<input type="radio" name="ch_visibility" id="ch_visibility-radio-publish" value="publish" <?php checked( $ch_visibility, 'publish' ); ?> /> <label for="ch_visibility-radio-publish" class="selectit" title="<?php _e( 'visibility of subpages set individually', 'pps' );?>"><?php echo _pp_('Public'); ?></label><br />

		<?php if ( $_status_obj = get_post_status_object('private') ) : ?>
		<input type="radio" name="ch_visibility" id="ch_visibility-radio-private" value="private" <?php checked( $ch_visibility, 'private' ); ?> /> <label for="ch_visibility-radio-private" class="selectit"><?php echo $_status_obj->label ?></label>
		<br />
		<?php endif;?>

		<?php
		$pvt_stati = apply_filters( 'pp_order_types', pp_get_post_stati( array( 'private' => true, 'post_type' => $post->post_type ), 'object' ), array( 'order_property' => 'label' ) );
		reset( $pvt_stati );
		for( $i=0; $i < count($pvt_stati); $i++ ) :
			$arr = each( $pvt_stati );
			$_status = $arr[0];

			if ( 'private' == $_status )
				continue;

			if ( ! $is_administrator ) {
				if ( empty( $type_obj->cap->set_posts_status ) ) {
					$set_status_cap = $type_obj->cap->publish_posts;
				} else {
					$cond_caps = $pp_attributes->get_condition_caps( $type_obj->cap->set_posts_status, $post->post_type, 'post_status', $_status );
					if ( ! $set_status_cap = reset( $cond_caps ) ) {
						$set_status_cap = $type_obj->cap->set_posts_status;
					}
				}

				if ( ! current_user_can( $set_status_cap ) )
					continue;
			}
		?>
		<input type="radio" name="ch_visibility" class="pvt-custom" id="ch_visibility-radio-<?php echo $_status ?>" value="<?php echo $_status ?>" <?php checked( $ch_visibility, $_status ); ?> /> <label for="ch_visibility-radio-<?php echo $_status ?>" class="selectit"><?php echo $arr[1]->label ?></label>
		<br />	
		<?php 
		endfor; ?>
		<p>
		<a href="#child-visibility" class="ch_save-post-visibility hide-if-no-js button"><?php echo _pp_('OK'); ?></a>
		</p>
		</div>
		<?php }
	}

	public static function post_time_display( $post, $args ) {
		global $action;
		
		extract( $args, EXTR_SKIP );  // $is_administrator, $type_obj, $post_status_obj, $can_publish, $moderation_stati, $can_set_status, $ch_visibility
		?>
		<span id="timestamp">
		<?php 
		// translators: Publish box date formt, see http://php.net/date
		$datef = _pp_( 'M j, Y @ G:i' );
		
		if ( 0 != $post->ID ) {
			$published_stati = get_post_stati( array( 'public' => true, 'private' => true ), 'names', 'or' );
		
			if ( 'future' == $post_status_obj->name ) { // scheduled for publishing at a future date
				$stamp = _pp_('Scheduled for: <b>%1$s</b>');
			} else if ( in_array( $post_status_obj->name, $published_stati ) ) { // already published
				$stamp = _pp_('Published on: <b>%1$s</b>');
			} else if ( '0000-00-00 00:00:00' == $post->post_date_gmt ) { // draft, 1 or more saves, no date specified
				$stamp = _pp_('Publish <b>immediately</b>');
			} else if ( time() < strtotime( $post->post_date_gmt . ' +0000' ) ) { // draft, 1 or more saves, future date specified
				$stamp = _pp_('Schedule for: <b>%1$s</b>');
			} else { // draft, 1 or more saves, date specified
				$stamp = _pp_('Publish on: <b>%1$s</b>');
			}
			$date = date_i18n( $datef, strtotime( $post->post_date ) );
		} else { // draft (no saves, and thus no date specified)
			$stamp = _pp_('Publish <b>immediately</b>');
			$date = date_i18n( $datef, strtotime( current_time('mysql') ) );
		}
		printf($stamp, $date); ?></span>
		<a href="#edit_timestamp" class="edit-timestamp hide-if-no-js" tabindex='4'><?php echo _pp_('Edit') ?></a>
		<div id="timestampdiv" class="hide-if-js"><?php touch_time(($action == 'edit'),1,4); ?></div>
	<?php
	}

	public static function post_publish_ui( $post, $args ) {
		extract( $args, EXTR_SKIP );  // $is_administrator, $type_obj, $post_status_obj, $can_publish, $moderation_stati, $can_set_status, $ch_visibility
	?>
		<?php if ( pp_wp_ver( '3.6' ) ) : ?>
		<span class="spinner"></span>
		<?php else: ?>
		<img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" class="ajax-loading" id="ajax-loading" alt="" />
		<?php endif; ?>
		
		<?php
		if ( ( ! $post_status_obj->public && ! $post_status_obj->private && ( 'future' != $post_status_obj->name ) ) ) {
			if ( $can_publish ) :
				if ( !empty($post->post_date_gmt) && time() < strtotime( $post->post_date_gmt . ' +0000' ) ) : 
				$future_status_obj = get_post_status_object( 'future' );
				?>
				<input name="original_publish" type="hidden" id="original_publish" value="<?php echo $future_status_obj->labels->publish ?>" />
				<input name="publish" type="submit" class="button button-primary button-large" id="publish" tabindex="5" accesskey="p" value="<?php echo $future_status_obj->labels->publish ?>" />
		<?php	else : 
				$publish_status_obj = get_post_status_object( 'publish' );
				?>
				<input name="original_publish" type="hidden" id="original_publish" value="<?php echo $publish_status_obj->labels->publish ?>" />
				<input name="publish" type="submit" class="button button-primary button-large" id="publish" tabindex="5" accesskey="p" value="<?php echo $publish_status_obj->labels->publish ?>" />
		<?php	endif;
			else : ?>
				<?php
				$moderation_order = array();
				$current_order_key = 0;
				$current_status = $post_status_obj->name;

				$default_by_sequence = pp_get_option( 'moderation_stati_default_by_sequence' );
				
				foreach( $moderation_stati as $_status => $status_obj ) {
					if ( 'future' == $_status )
						continue;
					
					if ( isset($status_obj->order) )		// custom / default priorities were set at init
						$order = $status_obj->order;
					else
						continue;

					if ( $default_by_sequence ) {
						if ( $order <= $post_status_obj->order )
							continue;
						
						if ( ! isset( $moderation_order[$order] ) )
							$moderation_order[$order] = array();
						
						$moderation_order[$order] []= $_status;
						
						if ( $_status == $current_status )
							$current_order_key = $order;
					
					} else { // default to highest permitted moderation status
						if ( ! isset( $moderation_order[10000 - $order] ) )
							$moderation_order[10000 - $order] = array();
						
						$moderation_order[10000 - $order] []= $_status;
						
						if ( $_status == $current_status )
							$current_order_key = 10000 - $order;
					}
				}
				
				if ( empty($moderation_order) )
					$moderation_order []= $current_status;
				
				ksort($moderation_order);
				
				foreach( array_keys($moderation_order) as $_order_key ) {
					foreach( $moderation_order[$_order_key] as $_status ) {
						//$default_approval_status = apply_filters( 'pp_default_approval_status', 'approved', $post->ID );
						
						// if already set to a moderation status, don't display button for another moderation status of the same order
						if ( ( $_order_key == $current_order_key ) && ( $_status != $current_status ) )
							continue;
						
						$status_obj = get_post_status_object( $_status );

						if ( $status_obj && $can_set_status[$_status] ) {
							break 2;
						}
					}
				}
				
				// otherwise default to pending
				if ( empty($status_obj) ) {
					if ( defined( 'PP_LEGACY_PENDING_STATUS' ) || $can_set_status['pending'] )
						$status_obj = get_post_status_object( 'pending' );
					else
						$status_obj = get_post_status_object( 'draft' );
				} else
					echo '<input name="default_approval_status" type="hidden" id="default_approval_status" value="' . $status_obj->name . '" />';
				?>
				<input name="original_publish" type="hidden" id="original_publish" value="<?php echo $status_obj->labels->publish ?>" />
				<input name="publish" type="submit" class="button button-primary button-large" id="publish" tabindex="5" accesskey="p" value="<?php echo $status_obj->labels->publish ?>" />
		<?php
			endif;
		} else { ?>
				<input name="original_publish" type="hidden" id="original_publish" value="<?php echo esc_attr( _pp_('Update') );?>" />
				<input name="save" type="submit" class="button button-primary button-large" id="publish" tabindex="5" accesskey="p" value="<?php echo esc_attr( _pp_('Update') );?>" />
		<?php
		}	
	}
} // end class PPS_Submit_Metabox
	
class PPS_PostEditUI {
	public static function flt_post_updated_messages( $messages ) {
		if ( ! pp_is_content_administrator() ) {
			if ( $type_obj = pp_get_type_object( 'post', pp_find_post_type() ) ) {
				if ( ! current_user_can($type_obj->cap->publish_posts) ) {
					global $post;
					
					if ( $post ) {
						if ( $status_obj = get_post_status_object( $post->post_status ) ) {
							$messages['post'][6] = esc_attr( sprintf( __( 'Post set as %s', 'pp' ), $status_obj->label ) );
							$messages['page'][6] = esc_attr( sprintf( __( 'Page set as %s', 'pp' ), $status_obj->label ) );
						}
					}
				}
			}
			return $messages;
		}
	}
	
	public static function act_object_edit_scripts() {
		global $typenow;

		$stati = array();
		foreach( array( 'public', 'private', 'moderation' ) as $prop ) {
			foreach( pp_get_post_stati( array( $prop => true, 'post_type' => $typenow ), 'object' ) as $status => $status_obj ) {
				$stati[$prop][] = array( 'name' => $status, 'label' => $status_obj->labels->name, 'save_as' => $status_obj->labels->save_as );
			}
		}

		$draft_obj = get_post_status_object( 'draft' );

		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
		wp_enqueue_script( 'pp_object_edit', PPS_URLPATH . "/admin/js/pps_object-edit{$suffix}.js", array('jquery', 'jquery-form'), PPS_VERSION, true );
		wp_localize_script( 'pp_object_edit', 'ppObjEdit', array( 
							//'ajaxurl' => admin_url(''),
							'pubStati' => json_encode( $stati['public'] ),
							'pvtStati' => json_encode( $stati['private'] ),
							'modStati' => json_encode( $stati['moderation'] ),
							'draftSaveAs' =>$draft_obj->labels->save_as,
						) );
		
		global $wp_scripts;
		$wp_scripts->in_footer []= 'pp_object_edit';  // otherwise it will not be printed in footer, as of WP 3.2.1
	}

	// ensure Comments metabox for custom published / private stati
	public static function act_comments_metabox( $post_type, $post ) {
		global $wp_meta_boxes;
		if ( isset( $wp_meta_boxes[$post_type]['normal']['core']['commentsdiv'] ) )
			return;

		if ( $post_status_obj = get_post_status_object( $post->post_status ) ) {
			if ( ('publish' == $post->post_status || 'private' == $post->post_status) && post_type_supports($post_type, 'comments') )
				add_meta_box('commentsdiv', _pp_('Comments'), 'post_comment_meta_box', $post_type, 'normal', 'core');
		}
	}

	public static function act_replace_publish_metabox( $post_type, $post ) {
		global $wp_meta_boxes;
	
		//remove_meta_box('submitdiv', $post_type, 'side');
		//add_meta_box('submitdiv', _pp_('Publish'), 'pp_post_submit_meta_box', $post_type, 'side', 'core');
		
		if ( ! in_array( $post_type, pp_get_enabled_post_types() ) )
			return;

		if ( 'attachment' != $post_type ) {
			if ( ! empty($wp_meta_boxes[$post_type]['side']['core']['submitdiv']) ) {
				$wp_meta_boxes[$post_type]['side']['core']['submitdiv']['callback'] = '_pps_post_submit_meta_box';
			}
		}
	}

	public static function act_force_visibility_js() {
		global $post;
		
		if ( empty( $post ) || $post->post_password )
			return;
		
		$current_status_obj = get_post_status_object($post->post_status);
		$attribute_defs = pps_init_attributes();
		
		$_args = is_post_type_hierarchical( $post->post_type ) ? array( 'id' => $post->ID ) : array( 'default_only' => true );
		$_args['post_type'] = $post->post_type;
		$_args['return_meta'] = true;  // this causes PPCE filter to return object to indicate whether the forced status is due to a "Subpage visibility" setting or a forced default privacy
		
		if ( ! $force = $attribute_defs->get_item_condition( 'post', 'force_visibility', $_args ) )
			return;
		
		if ( ! is_object($force) ) {
			$force = (object) array( 'force_status' => $force, 'force_basis' => 'direct' );
		}

		if ( 'publish' == $force->force_status ) 
			$status_label = __( 'Public' );
		else {
			$force_status_object = get_post_status_object( $force->force_status );
			$status_label = $force_status_object->label;
		}
		
		$post_type_object = get_post_type_object($post->post_type);
		//$force_caption = sprintf(__('Visibility forced to %1$s by parent %2$s', 'pps'), $force_status_object->label, $post_type_object->labels->singular_name);
		$force_caption = sprintf(__('Visibility forced to %1$s', 'pps'), $status_label, $post_type_object->labels->singular_name);
		
		$vis = ( 'publish' == $force->force_status ) ? 'public' : $force->force_status;

// @todo: move to .js
?>
<script type="text/javascript">
/* <![CDATA[ */
jQuery(document).ready( function($) {
<?php if ( $current_status_obj->public || $current_status_obj->private ) : ?>
$('#visibility-radio-<?php echo($vis)?>').prop('checked',true);
$('#post-visibility-display').html( $('#visibility-radio-<?php echo($vis)?>').next('label').html() );
updateStatusDropdownElements();
updateStatusCaptions();
<?php endif;?>

$('input[name="visibility"][value!="<?php echo($vis)?>"][value!="public"][value!="password"]').prop('disabled',true).siblings('label').addBack().attr('title', '<?php echo $force_caption;?>');
$('input[name="visibility"][value="public"]').siblings('label').attr('title', '<?php echo $force_caption;?>'); <?php /* can't disable public option because it's needed to select unpublished stati, but do alter its title */ ?>

<?php if ( 'default' != $force->force_basis ) :  /* if the visibility forcing stems from the default privacy setting for the post type, still allow subpages to be custom-forced to a different privacy */?>
$('input[name="ch_visibility"][value!="<?php echo($vis)?>"]').prop('disabled',true).siblings('label').addBack().attr('title', '<?php echo $force_caption;?>');
<?php endif; ?>
});
/* ]]> */
</script>
<?php
	} // end function act_force_visibility_js

} // end class
	

