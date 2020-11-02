<?php
/*
Plugin Name: LiveHelpNow Help Desk -- Chat Button
Plugin URI: http://livehelpnow.net
Description: LiveHelpNow Help Desk -- Chat Button
Version: 2.2.2
Author: LiveHelpNow
Author URI: http://livehelpnow.net
*/

// check version. only 2.8 WP support class multi widget system
global $wp_version;
if((float)$wp_version >= 2.8){

class LHNChat extends WP_Widget {

	/**
	 * constructor
	 */
	public function __construct() {
		parent::__construct(
			'lhn_chat',
			'LiveHelpNow Help Desk -- Chat Button',
			array(
				'description' => 'Widget to add Live Help Now\'s Chat Button to your Wordpress website.'
			)
		);
	}

	/**
	 * display widget
	 */
	public function widget($args, $instance) {
		extract($args, EXTR_SKIP);

		$account = empty($instance['account']) ? '' : $instance['account'];
		$account = str_replace("lhn", "", strtolower($account));
		$button = empty($instance['button']) ? '35' : $instance['button'];
		$invite = empty($instance['invite']) ? '0' : $instance['invite'];
		$window = empty($instance['window']) ? '0' : $instance['window'];
		$invitation = empty($instance['invitation']) ? '0' : $instance['invitation'];
		$department = empty($instance['department']) ? '0' : $instance['department'];
		$title = empty($instance['title']) ? '' : apply_filters('widget_title', $instance['title']);
		$position = empty($instance['position']) ? 'default' : $instance['position'];
		$xposition = empty($instance['xposition']) ? 'left' : $instance['xposition'];
		$yposition = empty($instance['yposition']) ? 'top' : $instance['yposition'];
		$xpositionval = empty($instance['xpositionval']) ? '0' : $instance['xpositionval'];
		$ypositionval = empty($instance['ypositionval']) ? '0' : $instance['ypositionval'];

		$lhnUserEmail = "";
		$lhnUserName = "";

		if(function_exists('get_current_user_id')){
			$userID = get_current_user_id();
			$user_data = get_userdata($userID);
			if(is_object($user_data)){
				$lhnUserEmail = $user_data->user_email;
				$lhnUserName = urlencode($user_data->user_firstname . " " . $user_data->user_lastname);
			}
		}else{
			global $current_user;
			get_currentuserinfo();
			$lhnUserEmail = $current_user->user_email;
			$lhnUserName = urlencode($current_user->user_firstname." ".$current_user->user_lastname);
		}

	    if($account && $button && is_numeric($button)){
		echo $before_widget;

		if($title && $title != ""){
			echo $before_title;
			echo $title;
			echo $after_title;
		}
		?>
		<script type="text/javascript" data-cfasync="false">
				var lhnAccountN = "<?php echo $account; ?>";
				var lhnButtonN = <?php echo $button; ?>;
				var lhnInviteEnabled = <?php echo ($invite == "on") ? "1" : "0"; ?>;
				var lhnWindowN = <?php echo $window; ?>;
				var lhnInviteN = <?php echo $invitation; ?>;
				var lhnDepartmentN = <?php echo $department; ?>;
				var lhnCustom1 = '<?php echo $lhnUserEmail; ?>';
				var lhnCustom2 = '<?php echo $lhnUserName; ?>';
				var lhnCustom3 = '';
				var lhnChatPosition = '<?php echo $position; ?>';
				<?php
					if($position == "custom"){
				?>
				var lhnChatPositionX = '<?php echo $xposition; ?>';
			    var lhnChatPositionY = '<?php echo $yposition; ?>';
			    var lhnChatPositionXVal = <?php echo $xpositionval; ?>;
			    var lhnChatPositionYVal = <?php echo $ypositionval; ?>;
				<?php
					}
				?>
				var lhnPlugin = 'WP-<?php echo get_bloginfo('version'); ?>-Chat';
		</script>
		<a href="http://www.LiveHelpNow.net/" target="_blank" style="font-size:10px;" id="lhnHelp">Help Desk Software</a>
		<script src="//www.livehelpnow.net/lhn/widgets/chatbutton/lhnchatbutton-current.min.js" type="text/javascript" data-cfasync="false" id="lhnscript"></script>
		<?php
		echo $after_widget;
		}

	}

	/**
	 *	update/save function
	 */
	public function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['account'] = strip_tags($new_instance['account']);
		$instance['button'] = strip_tags($new_instance['button']);
		$instance['invite'] = strip_tags($new_instance['invite']);
		$instance['invitation'] = strip_tags($new_instance['invitation']);
		$instance['window'] = strip_tags($new_instance['window']);
		$instance['department'] = strip_tags($new_instance['department']);
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['position'] = strip_tags($new_instance['position']);
		$instance['xposition'] = strip_tags($new_instance['xposition']);
		$instance['yposition'] = strip_tags($new_instance['yposition']);
		if(is_numeric(strip_tags($new_instance['xpositionval']))){
			if(strip_tags($new_instance['xpositionval']) < 0){
				$new_instance['xpositionval'] = 0;
			}else if(strip_tags($new_instance['xpositionval']) > 500){
				$new_instance['xpositionval'] = 500;
			}
			$instance['xpositionval'] = strip_tags($new_instance['xpositionval']);
		}
		$instance['ypositionval'] = strip_tags($new_instance['ypositionval']);
		return $instance;
	}

	/**
	 *	admin control form
	 */
	public function form($instance) {
		$account = isset($instance['account']) ? $instance['account'] : __('');
		$account = str_replace("lhn", "", strtolower($account));
		$button = isset($instance['button']) ? $instance['button'] : __('35');
		$invite = isset($instance['invite']) ? $instance['invite'] : __('off');
		$window = isset($instance['window']) ? $instance['window'] : __('0');
		$invitation = isset($instance['invitation']) ? $instance['invitation'] : __('0');
		$department = isset($instance['department']) ? $instance['department'] : __('0');
		$title = isset($instance['title']) ? $instance['title'] : __('Chat With Us');
		$position = isset($instance['position']) ? $instance['position'] : __('default');
		$xposition = isset($instance['xposition']) ? $instance['xposition'] : __('left');
		$yposition = isset($instance['yposition']) ? $instance['yposition'] : __('top');
		$xpositionval = isset($instance['xpositionval']) ? $instance['xpositionval'] : __('0');
		$ypositionval = isset($instance['ypositionval']) ? $instance['ypositionval'] : __('0');

		$customDisplay = "none";
		if($position == "custom"){
			$customDisplay = "block";
		}
		?>
		<script type="text/javascript">
			function showCustom(val){
				if(val == "custom"){
					document.getElementById("<?php echo $this->get_field_id('xposition'); ?>_container").style.display = "block";
					document.getElementById("<?php echo $this->get_field_id('yposition'); ?>_container").style.display = "block";
				}else{
					document.getElementById("<?php echo $this->get_field_id('xposition'); ?>_container").style.display = "none";
					document.getElementById("<?php echo $this->get_field_id('yposition'); ?>_container").style.display = "none";
				}
			}
		</script>
		<p>
			<label for="<?php echo $this->get_field_id('account'); ?>">
				<?php _e( 'LiveHelpNow Account#:' ); ?>
			</label>
			<input class="" id="<?php echo $this->get_field_id('account'); ?>" name="<?php echo $this->get_field_name('account'); ?>" type="text" value="<?php echo esc_attr($account); ?>" />
			<?php
				echo is_numeric(trim(str_replace("-1", "", esc_attr($account)))) ? '<span style="color:green;">Accepted</span>' : '<span style="color:red;">Invalid</span>';
			?>
			<br />
			* Must be numeric
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('button'); ?>">
				<?php echo 'LiveHelpNow Button#: &nbsp;&nbsp;<a href="http://www.livehelpnow.net/products/live_chat_system/buttons/" target="_blank" style="font-size:11px;text-decoration:none;">View Buttons</a>'; ?>
			</label>
			<input class="" id="<?php echo $this->get_field_id('button'); ?>" name="<?php echo $this->get_field_name('button'); ?>" type="text" value="<?php echo esc_attr($button); ?>" />
			<?php
				echo is_numeric(trim(esc_attr($button))) ? '<span style="color:green;">Accepted</span>' : '<span style="color:red;">Invalid</span>';
			?>
			<br />
			* Must be numeric
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('position'); ?>">
				<?php _e( 'Button Position:' ); ?>
			</label>
			<select class="" id="<?php echo $this->get_field_id('position'); ?>" name="<?php echo $this->get_field_name('position'); ?>" onchange="showCustom(this.value)">
				<option value="default" <?php if($position == "default"){ ?> selected="selected" <?php } ?>>Default</option>
				<option value="topleft" <?php if($position == "topleft"){ ?> selected="selected" <?php } ?>>Top Left</option>
				<option value="topright" <?php if($position == "topright"){ ?> selected="selected" <?php } ?>>Top Right</option>
				<option value="bottomleft" <?php if($position == "bottomleft"){ ?> selected="selected" <?php } ?>>Bottom Left</option>
				<option value="bottomright" <?php if($position == "bottomright"){ ?> selected="selected" <?php } ?>>Bottom Right</option>
				<option value="righttab" <?php if($position == "righttab"){ ?> selected="selected" <?php } ?>>Right Tab</option>
				<option value="custom" <?php if($position == "custom"){ ?> selected="selected" <?php } ?>>Custom</option>
			</select>
		</p>
		<p id="<?php echo $this->get_field_id('xposition'); ?>_container" style="display: <?php echo $customDisplay; ?>;">
			<label for="<?php echo $this->get_field_id('xposition'); ?>">
				<?php echo 'Button X Position:'; ?>
			</label>
			<select class="" id="<?php echo $this->get_field_id('xposition'); ?>" name="<?php echo $this->get_field_name('xposition'); ?>">
				<option value="left" <?php if($xposition == "left"){ ?> selected="selected" <?php } ?>>Left</option>
				<option value="right" <?php if($xposition == "right"){ ?> selected="selected" <?php } ?>>Right</option>
			</select>
			<input class="" id="<?php echo $this->get_field_id('xpositionval'); ?>" name="<?php echo $this->get_field_name('xpositionval'); ?>" type="text" value="<?php echo esc_attr($xpositionval); ?>" size="10" maxlength="3" /> (0 - 500)
		</p>
		<p id="<?php echo $this->get_field_id('yposition'); ?>_container" style="display: <?php echo $customDisplay; ?>;">
			<label for="<?php echo $this->get_field_id('yposition'); ?>">
				<?php echo 'Button Y Position:'; ?>
			</label>
			<select class="" id="<?php echo $this->get_field_id('yposition'); ?>" name="<?php echo $this->get_field_name('yposition'); ?>">
				<option value="top" <?php if($xposition == "top"){ ?> selected="selected" <?php } ?>>Top</option>
				<option value="bottom" <?php if($xposition == "bottom"){ ?> selected="selected" <?php } ?>>Bottom</option>
			</select>
			<input class="" id="<?php echo $this->get_field_id('ypositionval'); ?>" name="<?php echo $this->get_field_name('ypositionval'); ?>" type="text" value="<?php echo esc_attr($ypositionval); ?>" size="10" maxlength="3" /> (0 - 500)
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('invite'); ?>">
				<?php echo 'LiveHelpNow Automatic Chat Invitation:'; ?>
			</label>
			<input class="" id="<?php echo $this->get_field_id('invite'); ?>" name="<?php echo $this->get_field_name('invite'); ?>" type="checkbox" value="on" <?php if($invite == "on"){echo "checked";} ?> />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('window'); ?>">
				<?php echo 'LiveHelpNow Window ID:'; ?>
			</label>
			<input class="" id="<?php echo $this->get_field_id('window'); ?>" name="<?php echo $this->get_field_name('window'); ?>" type="text" value="<?php echo esc_attr($window); ?>" />
			<?php
				echo is_numeric(trim(esc_attr($window))) ? '<span style="color:green;">Accepted</span>' : '<span style="color:red;">Invalid</span>';
			?>
			<br />
			* Must be numeric
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('invitation'); ?>">
				<?php echo 'LiveHelpNow Invitation ID:'; ?>
			</label>
			<input class="" id="<?php echo $this->get_field_id('invitation'); ?>" name="<?php echo $this->get_field_name('invitation'); ?>" type="text" value="<?php echo esc_attr($invitation); ?>" />
			<?php
				echo is_numeric(trim(esc_attr($invitation))) ? '<span style="color:green;">Accepted</span>' : '<span style="color:red;">Invalid</span>';
			?>
			<br />
			* Must be numeric
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('department'); ?>">
				<?php echo 'LiveHelpNow Department ID:'; ?>
			</label>
			<input class="" id="<?php echo $this->get_field_id('department'); ?>" name="<?php echo $this->get_field_name('department'); ?>" type="text" value="<?php echo esc_attr($department); ?>" />
			<?php
				echo is_numeric(trim(esc_attr($department))) ? '<span style="color:green;">Accepted</span>' : '<span style="color:red;">Invalid</span>';
			?>
			<br />
			* Must be numeric
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>">
				<?php _e( 'LiveHelpNow Title:' ); ?>
			</label>
			<input class="" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
		</p>
		<p>
			<a href="http://www.livehelpnow.net/lnd/trial.aspx" target="_blank" style='font-size:14px; font-weight:bold;text-decoration:none;'>
				<strong>Get LiveHelpNow account</strong>
			</a><br />
			<a href="http://www.livehelpnow.net/alerter/" target="_blank" style='font-size:14px; font-weight:bold;text-decoration:none;'>
				<strong>Download Alterer Software</strong>
			</a><br />
			<a href="http://www.livehelpnow.net" target="_blank" style='font-size:14px; font-weight:bold;text-decoration:none;'>Get Help</a><br />
			<span style="font-size:9px;line-height:12px;">To remove the "Help Desk Software" link from your widget, add "-1" to the end of your Account #.</span>
		</p>
		<?php
	}
}

/* register widget when loading the WP core */
function just_register_widgets(){
	// curl need to be installed
	register_widget('LHNChat');
}
add_action('widgets_init', 'just_register_widgets');


}
