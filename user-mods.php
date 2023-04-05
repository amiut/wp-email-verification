<?php
/**
 * Things related to user modification stuff
 * Such as ignoring email verifications when admins add a new user
 * Re-send confirmation link both in admin area and by users themselves.
 * Completely lock users after x attempts of link re-send requests
 *
 * @copyright dornaweb.com
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DWUserVerifyMods extends DWEmailVerify{
	/**
	 * __construct
	 */
	public function __construct(){
		add_action( 'user_new_form', [ $this, 'ignore_field_html' ] );
		add_action( 'edit_user_profile', [ $this, 'verification_control_html' ] );
		add_action( 'edit_user_profile_update', [ $this, 'profile_update' ] );
		add_action( 'wp_ajax_dw_resend_verify', [ $this, 'resend_verify_ajaxcb' ] );
		add_action( 'wp_ajax_nopriv_dw_resend_verify', [ $this, 'resend_verify_ajaxcb' ] );
	}

	/**
	 * Profile update actions
	 */
	public function profile_update( $user_id ){
		if ( ! current_user_can( 'edit_users' ) ) return;

		// Custom unlock un-verified users
		if( $this->needs_validation( $user_id )  && ! empty( $_POST['dw_unlock_user'] ) ) {
			$this->unlock_user( $user_id );
		}
	}

	/**
	 * Resend verification link
	 * Ajax callback
	 */
	public function resend_verify_ajaxcb(){
		$user_id = absint( $_GET['user_id'] );

		if( ! $user_id ) {
			$user_login = trim( esc_attr( $_GET['user_login'] ) );
			$user = get_user_by('login', $user_login);
			$user_id = (int) $user->ID;
		}

		if( ! $user_id ){
			die( json_encode([
				'type'		=> 'error',
				'code'		=> 'invalid_request',
				'message'	=> esc_html__('Inavlid request.', 'dwverify')
			], JSON_PRETTY_PRINT ) );
		}

		// Admin request
		if( current_user_can( 'edit_users' ) ){
			$this->send_verification_link( $user_id );
			$message = [
				'type'		=> 'success',
				'code'		=> 'verify_link_sent',
				'message'	=> esc_html__('Verification link sent to user\'s email address', 'dwverify')
			];

		} elseif( $this->needs_validation( $user_id ) ) {
			$attempts = (int) get_user_meta( $user_id, 'verify-link-attempts', true );

			// Avoid repeatively asking for re-send the verification link
			if( $attempts <= (int) get_option('dw_verify_max_resend_allowed') ) {
				$this->send_verification_link( $user_id );
				$message = [
					'type'		=> 'success',
					'code'		=> 'verify_link_sent',
					'message'	=> esc_html__('Verification link sent to your email address', 'dwverify')
				];

				update_user_meta( $user_id, 'verify-link-attempts', $attempts + 1 );

			} else{
				$message = [
					'type'		=> 'error',
					'code'		=> 'max_resend_attempts_reached',
					'message'	=> esc_html__('You have tried re-sending verification link too many times, please contact site administrators.', 'dwverify')
				];

			}

		} else{
			$message = [
				'type'		=> 'error',
				'code'		=> 'user_already_verified',
				'message'	=> esc_html__('Your email address is already verified.', 'dwverify')
			];

		}

		die( json_encode( $message, JSON_PRETTY_PRINT ) );
	}

	/**
	 * HTML for ignore email verification checkbox
	 * Administrators can allow users to not confirm their email address
	 */
	public function ignore_field_html(){ ?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php echo esc_html__( 'Skip email verification' ); ?></th>
				<td>
					<input type="checkbox" name="skip_verification" id="skip_verification" value="1" checked="checked" />
					<label for="skip_verification"><?php echo esc_html__( 'No need for this user to verify their email address.', 'dwverify' ); ?></label>
				</td>
			</tr>
		</table>
	<?php
	}

	/**
	 * Options to re-send verification link, custom verification,... on profile edit pages
	 */
	public function verification_control_html( $user ){ ?>
		<h2><?php echo esc_html__('Email verification options', 'dwverify'); ?></h2>
		<table class="form-table">
			<tbody>
				<tr id="dw_resend_verification" class="user-pass1-wrap">
					<th><label><?php echo esc_html__('Resend verification email: ', 'dwverify'); ?></label></th>
					<td>
						<button type="button" class="button"><?php echo esc_html__('Resend verification email', 'dwverify'); ?></button>
						<span class="note" style="padding-top: 10px; display: block; color: 90%; font-style: italic; color: #dd2c2c80;">
							<?php echo esc_html__('This will lock user\'s account and asks them to verify their email address, once again.', 'dwverify'); ?>
						</span>
					</td>
				</tr>

				<?php if( $this->needs_validation( $user->ID ) ) : ?>
					<tr id="dw_unlock_user_wrap" class="user-pass1-wrap">
						<th><label for="pass1-text"><?php echo esc_html__('Unlock user: ', 'dwverify'); ?></label></th>
						<td>
							<label for="dw_unlock_user">
							<input name="dw_unlock_user" id="dw_unlock_user" value="1" type="checkbox">
							<?php echo esc_html__('Exclude this user from verification of their email.', 'dwverify'); ?></label>
							<span class="note" style="padding-top: 10px; display: block; color: 90%; font-style: italic; color: #787878;">
								<?php echo esc_html__('This user has not verified his/her email address, you can allow him/her, not to.', 'dwverify'); ?>
							</span>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<script type="text/javascript">
			jQuery('#dw_resend_verification button').click( function(e){
				e.preventDefault();
				var resend = confirm("<?php echo esc_attr__('This user won\'t be able to sign-in, until they verify their email address again, Are you sure you want to do that?', 'dwverify'); ?>");

				if( resend === true ){
					jQuery.get( ajaxurl, { action: 'dw_resend_verify', user_id: <?php echo esc_attr($user->ID); ?> }, function( response ){
						var response = jQuery.parseJSON( response );
						alert( response.message );
					});
				}
			});
		</script>
	<?php
	}
}

new DWUserVerifyMods();
