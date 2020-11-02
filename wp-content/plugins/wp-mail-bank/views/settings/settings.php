<?php
/**
 * This Template is used for managing settings.
 *
 * @author  Tech Banker
 * @package wp-mail-bank/views/settings
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}// Exit if accessed directly
if ( ! is_user_logged_in() ) {
	return;
} else {
	$access_granted = false;
	foreach ( $user_role_permission as $permission ) {
		if ( current_user_can( $permission ) ) {
			$access_granted = true;
			break;
		}
	}
	if ( ! $access_granted ) {
		return;
	} elseif ( SETTINGS_MAIL_BANK === '1' ) {
		$mail_bank_settings = wp_create_nonce( 'mail_bank_settings' );
		?>
		<div class="page-bar">
			<ul class="page-breadcrumb">
			<li>
				<i class="icon-custom-home"></i>
				<a href="admin.php?page=mb_email_configuration">
					<?php echo esc_attr( $wp_mail_bank ); ?>
				</a>
			<span>></span>
			</li>
			<li>
				<span>
					<?php echo esc_attr( $mb_settings ); ?>
				</span>
			</li>
		</ul>
	</div>
	<div class="row">
		<div class="col-md-12">
			<div class="portlet box vivid-green">
				<div class="portlet-title">
					<div class="caption">
						<i class="icon-custom-paper-clip"></i>
						<?php echo esc_attr( $mb_settings ); ?>
					</div>
					<p class="premium-editions">
						<a href="https://mail-bank.tech-banker.com/" target="_blank" class="premium-edition-text"><?php echo esc_attr( $mb_full_features ); ?></a> <?php echo esc_attr( $mb_chek_our ); ?>  <a href="https://mail-bank.tech-banker.com/backend-demos/" target="_blank" class="premium-edition-text"><?php echo esc_attr( $mb_online_demos ); ?></a>
					</p>
				</div>
				<div class="portlet-body form">
					<form id="ux_frm_settings">
						<div class="form-body">
						<div class="form-group">
							<label class="control-label">
								<?php echo esc_attr( $mb_settings_debug_mode ); ?> :
								<span class="required" aria-required="true">*</span>
							</label>
							<select name="ux_ddl_debug_mode" id="ux_ddl_debug_mode" class="form-control" >
								<option value="enable"><?php echo esc_attr( $mb_enable ); ?></option>
								<option value="disable"><?php echo esc_attr( $mb_disable ); ?></option>
							</select>
							<i class='controls-description'><?php echo esc_attr( $mb_settings_debug_mode_tooltip ); ?></i>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label class="control-label">
									<?php echo esc_attr( $mb_remove_tables_title ); ?> :
									<span class="required" aria-required="true">*</span>
								</label>
								<select name="ux_ddl_remove_tables" id="ux_ddl_remove_tables" class="form-control" >
									<option value="enable"><?php echo esc_attr( $mb_enable ); ?></option>
									<option value="disable"><?php echo esc_attr( $mb_disable ); ?></option>
								</select>
								<i class='controls-description'><?php echo esc_attr( $mb_remove_tables_tooltip ); ?></i>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label class="control-label">
									<?php echo esc_attr( $mb_monitoring_email_log_title ); ?> :
									<span class="required" aria-required="true">*</span>
								</label>
								<select name="ux_ddl_monitor_email_logs" id="ux_ddl_monitor_email_logs" class="form-control">
									<option value="enable"><?php echo esc_attr( $mb_enable ); ?></option>
									<option value="disable"><?php echo esc_attr( $mb_disable ); ?></option>
								</select>
								<i class='controls-description'><?php echo esc_attr( $mb_monitoring_email_log_tooltip ); ?></i>
								</div>
							</div>
						</div>
						<?php
						if ( is_multisite() && is_main_site() ) {
							?>
							<div class="form-group">
								<label class="control-label">
									<?php echo esc_attr( $mb_fetch_settings ); ?> :
									<span class="required" aria-required="true">*</span>
								</label>
								<select name="ux_ddl_fetch_settings" id="ux_ddl_fetch_settings" class="form-control">
									<option value="individual_site"><?php echo esc_attr( $mb_indivisual_site ); ?></option>
									<option value="network_site"><?php echo esc_attr( $mb_multiple_site ); ?></option>
								</select>
								<i class="controls-description"><?php echo esc_attr( $mb_fetch_settings_tooltip ); ?></i>
							</div>
							<?php
						}
						?>
						<div class="line-separator"></div>
						<div class="form-actions">
							<div class="pull-right">
								<input type="submit" class="btn vivid-green" name="ux_btn_save_changes" id="ux_btn_save_changes" value="<?php echo esc_attr( $mb_save_changes ); ?>">
							</div>
						</div>
					</div>
					</form>
				</div>
			</div>
		</div>
	</div>
		<?php
	} else {
		?>
		<div class="page-bar">
			<ul class="page-breadcrumb">
			<li>
				<i class="icon-custom-home"></i>
				<a href="admin.php?page=mb_email_configuration">
					<?php echo esc_attr( $wp_mail_bank ); ?>
				</a>
				<span>></span>
			</li>
			<li>
				<span>
					<?php echo esc_attr( $mb_settings ); ?>
				</span>
			</li>
		</ul>
	</div>
	<div class="row">
		<div class="col-md-12">
			<div class="portlet box vivid-green">
				<div class="portlet-title">
					<div class="caption">
						<i class="icon-custom-paper-clip"></i>
						<?php echo esc_attr( $mb_settings ); ?>
					</div>
				</div>
				<div class="portlet-body form">
					<div class="form-body">
						<strong><?php echo esc_attr( $mb_user_access_message ); ?></strong>
					</div>
				</div>
			</div>
		</div>
	</div>
		<?php
	}
}
