<?php
/**
 * This Template is used for displaying email logs.
 *
 * @author  Tech Banker
 * @package wp-mail-bank/views/email-logs
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
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
	} elseif ( EMAIL_LOGS_MAIL_BANK === '1' ) {
		$mb_email_logs_delete_log = wp_create_nonce( 'mb_email_logs_delete' );
		$end_date                 = MAIL_BANK_LOCAL_TIME;
		$start_date               = $end_date - 604800;
		?>
		<style>

		</style>
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
					<?php echo esc_attr( $mb_email_logs ); ?>
				</span>
			</li>
		</ul>
	</div>
	<div class="row">
		<div class="col-md-12">
			<div class="portlet box vivid-green">
				<div class="portlet-title">
					<div class="caption">
						<i class="icon-custom-note"></i>
						<?php echo esc_attr( $mb_email_logs ); ?>
					</div>
					<p class="premium-editions">
						<a href="https://mail-bank.tech-banker.com/" target="_blank" class="premium-edition-text"><?php echo esc_attr( $mb_full_features ); ?></a> <?php echo esc_attr( $mb_chek_our ); ?>  <a href="https://mail-bank.tech-banker.com/backend-demos/" target="_blank" class="premium-edition-text"><?php echo esc_attr( $mb_online_demos ); ?></a>
					</p>
				</div>
				<div class="portlet-body form">
					<form id="ux_frm_email_logs">
						<div class="form-body">
						<div class="row">
							<div class="col-md-4">
								<div class="form-group">
									<label class="control-label">
									<?php echo esc_attr( $mb_start_date_title ); ?> :
									<span class="required" aria-required="true">* <?php echo ' ( ' . esc_attr( $mb_premium_edition_label ) . ' ) '; ?></span>
								</label>
								<input type="text" class="form-control" name="ux_txt_mb_start_date" id="ux_txt_mb_start_date" value="<?php echo esc_attr( date( 'm/d/Y', $start_date ) ); ?>" placeholder="<?php echo esc_attr( $mb_start_date_placeholder ); ?>" onfocus="prevent_datepicker_mail_bank(this.id);">
								<i class='controls-description'><?php echo esc_attr( $mb_start_date_tooltip ); ?></i>
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-group">
									<label class="control-label">
									<?php echo esc_attr( $mb_end_date_title ); ?> :
									<span class="required" aria-required="true">* <?php echo ' ( ' . esc_attr( $mb_premium_edition_label ) . ' ) '; ?></span>
								</label>
								<input type="text" class="form-control" name="ux_txt_mb_end_date" id="ux_txt_mb_end_date" value="<?php echo esc_attr( date( 'm/d/Y', $end_date ) ); ?>" placeholder="<?php echo esc_attr( $mb_end_date_placeholder ); ?>" onfocus="prevent_datepicker_mail_bank(this.id);">
								<i class='controls-description'><?php echo esc_attr( $mb_end_date_tooltip ); ?></i>
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-group">
									<label class="control-label">
									<?php echo esc_attr( $mb_limit_records_title ); ?> :
									<span class="required" aria-required="true">* <?php echo ' ( ' . esc_attr( $mb_premium_edition_label ) . ' ) '; ?></span>
								</label>
								<select name="ux_ddl_limit_email_logs" id="ux_ddl_limit_email_logs" class="form-control">
									<option value="100"><?php echo intval( $mb_hundred_records ); ?></option>
									<option value="500"><?php echo intval( $mb_five_hundred_records ); ?></option>
									<option value="1000" selected="selected"><?php echo intval( $mb_thousand_records ); ?></option>
									<option value="all"><?php echo esc_attr( $mb_all_records ); ?></option>
								</select>
								<i class='controls-description'><?php echo esc_attr( $mb_limit_records_tooltip ); ?></i>
								</div>
							</div>
						</div>
						<div class="form-actions">
							<div class="pull-right">
								<input type="submit" class="btn vivid-green" name="ux_btn_email_logs" id="ux_btn_email_logs" value="<?php echo esc_attr( $mb_submit ); ?>">
							</div>
						</div>
						<div class="line-separator"></div>
						<div class="table-top-margin">
							<select name="ux_ddl_email_logs" id="ux_ddl_email_logs" class="custom-bulk-width">
								<option value=""><?php echo esc_attr( $mb_email_logs_bulk_action ); ?></option>
								<option value="delete" style="color:red;"><?php echo esc_attr( $mb_email_logs_delete ); ?><span><?php echo ' ( ' . esc_attr( $mb_premium_edition_label ) . ' ) '; ?></span></option>
								<option value="resend_email" style="color:red;"><?php echo esc_attr( $mb_email_logs_resend_email ); ?><span><?php echo ' ( ' . esc_attr( $mb_premium_edition_label ) . ' ) '; ?></span></option>
							</select>
							<input type="button" class="btn vivid-green" name="ux_btn_apply" id="ux_btn_apply" value="<?php echo esc_attr( $mb_email_logs_apply ); ?>" onclick="premium_edition_notification_mail_bank();">
						</div>
						<table class="table table-striped table-bordered table-hover table-margin-top" id="ux_tbl_email_logs">
							<thead>
								<tr>
									<th style="text-align: center;" class="chk-action" style="width:5%">
									<input type="checkbox" name="ux_chk_all_email_logs" id="ux_chk_all_email_logs">
								</th>
								<th style="width:65%">
									<label>
										<?php echo esc_attr( $mb_email_logs_email_to ); ?>
									</label>
								</th>
								<th style="width:30%">
									<label>
										<?php echo esc_attr( $mb_email_logs_actions ); ?>
									</label>
								</th>
								</tr>
							</thead>
							<tbody id="ux_dynamic_email_logs_table_filter">
								<?php
								foreach ( $unserialized_email_logs_data as $value ) {
									?>
									<tr>
									<td style="text-align: center;">
										<input type="checkbox" name="ux_chk_email_logs_<?php echo intval( $value['id'] ); ?>" id="ux_chk_email_logs_<?php echo intval( $value['id'] ); ?>" onclick="check_email_logs(<?php echo intval( $value['id'] ); ?>)" value="<?php echo intval( $value['id'] ); ?>">
									</td>
									<td id="ux_email_sent_to_<?php echo intval( $value['id'] ); ?>">
										<div>
											<label><?php echo esc_attr( $mb_email_logs_email_to ); ?> :
												<?php echo esc_html( $value['email_to'] ); ?>
											</label>
										</div>
										<div>
											<label>
												<?php echo esc_attr( $mb_subject ); ?> :
												<?php echo isset( $value['subject'] ) !== '' ? esc_html( $value['subject'] ) : 'N/A'; ?>
											</label>
										</div>
										<div>
											<label>
												<?php echo esc_attr( $mb_date_time ); ?> :
												<?php echo esc_attr( date_i18n( 'd M Y h:i A', doubleval( $value['timestamp'] ) ) ); ?>
											</label>
										</div>
										<div style="margin:5px 0px;">
											<?php
											if ( 'Not Sent' === $value['status'] ) {
												?>
													<label class="mb-email-not-sent">
														<?php echo 'Sent' === $value['status'] ? esc_attr( $mb_status_sent ) : esc_attr( $mb_status_not_sent ); ?>
													</label>
												<?php
											} else {
												?>
													<label class="mb-email-sent">
														<?php echo 'Sent' === $value['status'] ? esc_attr( $mb_status_sent ) : esc_attr( $mb_status_not_sent ); ?>
													<label>
												<?php
											}
											?>
										</div>
																			</td>
									<td id="ux_email_action_<?php echo intval( $value['id'] ); ?>">
										<a href="javascript:void(0);" class="btn mail-bank-buttons" onclick="premium_edition_notification_mail_bank();"><?php echo esc_attr( $mb_resend ); ?>
										</a>
										<?php
										if ( isset( $value['debug_mode'] ) ) {
											?>
											<a href="javascript:void(0);" onclick="premium_edition_notification_mail_bank();" class="btn mail-bank-buttons"><?php echo esc_attr( $mb_email_logs_show_outputs ); ?>
											</a>
											<?php
										}
										?>
										<a href="javascript:void(0);" onclick="premium_edition_notification_mail_bank();" class="btn mail-bank-buttons"><?php echo esc_attr( $mb_email_logs_show_details ); ?>
										</a>
										<a href="javascript:void(0);" onclick="delete_email_logs(<?php echo intval( $value['id'] ); ?>)" class="btn mail-bank-buttons"><?php echo esc_attr( $mb_email_logs_delete ); ?>
										</a>
									</td>
									</tr>
									<?php
								}
								?>
							</tbody>
						</table>
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
					<?php echo esc_attr( $mb_email_logs ); ?>
				</span>
			</li>
		</ul>
	</div>
	<div class="row">
		<div class="col-md-12">
			<div class="portlet box vivid-green">
				<div class="portlet-title">
					<div class="caption">
						<i class="icon-custom-note"></i>
						<?php echo esc_attr( $mb_email_logs ); ?>
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
	</div>
		<?php
	}
}
