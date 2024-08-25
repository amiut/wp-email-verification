<?php
/**
 * Shortcode for email verification and redirect to account page
 *
 * @copyright dornaweb.com
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DWEmailVerifyShortcode{

	/**
	 * Check validation status
	 */
	public $validation_status = '';

	/**
	 * $shortcode_tag
	 * holds the name of the shortcode tag
	 * @var string
	 */
	public $shortcode_tag = 'dw-verify-email';

	/**
	 * $token_cookie_name
	 * holds the name of the verify token cookie
	 * @var string
	 */
	public $token_cookie_name = 'dw_verify_token';

	/**
	 * $cookie_path
	 * holds the path of the token cookie. MUST be the same when setting and unsetting the cookie, or else it won't be unset properly
	 * @var string
	 */
	public $cookie_path = '/';

	/**
	 * __construct
	 * class constructor will set the needed filter and action hooks
	 *
	 */
	function __construct(){
		//add shortcode
		add_shortcode( $this->shortcode_tag, [$this, 'shortcode_handler'] );
		add_action( 'wp', [$this, 'verify_stuff'] );
	}

	public function verify_stuff(){
		if( ! is_page( get_option('dw_verify_authorize_page') ) ) return;

		/**
		 * This page strips the verification token from the URL and redirects back to itself, to guard against referrer leakage
		 * See https://security.stackexchange.com/a/117871
		*/

		$user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

		if( empty( $user_id ) || ! DWEmailVerify::instance()->needs_validation( $user_id ) ) {
			$this->validation_status = 'invalid_request';
			return;
		}

		$verification_token = filter_input(INPUT_GET, 'verify_email', FILTER_SANITIZE_SPECIAL_CHARS);
		// Check if URL contains verification token
		if( ! preg_match( '/^[a-f0-9]{32}$/', $verification_token ) ) {
			// If it *doesn't*, check for it in a cookie
			$cookie_value = filter_input(INPUT_COOKIE, $this->token_cookie_name, FILTER_SANITIZE_SPECIAL_CHARS);
			if( ! $cookie_value ) {
				// If it isn't there, validation attempt has failed
				$this->validation_status = 'invalid_request';
				return;
			}
		} else {
			// If it does, store it in a cookie and redirect back here, stripping token from URL in process
			setcookie( $this->token_cookie_name, $verification_token, time() + 120, $this->cookie_path );
			$redirect_to = add_query_arg( array(
				'user_id' => $user_id
			), get_permalink( get_option( 'dw_verify_authorize_page' ) ) );
			wp_safe_redirect( $redirect_to );
			exit;
		}

		// If we get this far, we've found the token in a cookie. Unset the cookie first, then attempt to validate the token
		$token = filter_input(INPUT_COOKIE, $this->token_cookie_name, FILTER_SANITIZE_SPECIAL_CHARS)
		setcookie( $this->token_cookie_name, '', time() - 3600, $this->cookie_path ); // cookie path must be included and match what was set or it won't delete
		if( DWEmailVerify::instance()->verify_if_valid( $token, $user_id ) ) {
			$this->validation_status = 'validated';

			add_action( 'wp_head', [$this, 'page_redirect'] );

		} else {
			$this->validation_status = 'invalid_hash';
		}
	}

	/**
	 * Redirect page
	 */
	public function page_redirect(){
		if( $redirect = DWEmailVerify::instance()->redirect_url() ) {
			echo '<meta http-equiv="refresh" content="5;url='. esc_url($redirect) .'" />';
		}
	}

	/**
	 * shortcode_handler
	 * @param  array  $atts shortcode attributes
	 * @param  string $content shortcode content
	 * @return string
	 */
	function shortcode_handler( $atts, $content = null ) {
		$output = '';

		$awaiting_verification = (string) filter_input(INPUT_GET, 'awaiting-verification', FILTER_SANITIZE_SPECIAL_CHARS);
		if( ! empty( $awaiting_verification ) && 'true' == $awaiting_verification ) {
			return esc_html__('You have successfully registered on our website, Please check your email and click on the link we sent you to verify your email address.', 'dwverify');
		}

		switch ( $this->validation_status ) {
			case 'invalid_request':
				$output .= esc_html__('Invalid request', 'dwverify');
				break;

			case 'validated' :
				$output .= sprintf( esc_html__('Your email has been verified, you will be redirected in a few seconds <a href="%s">click here</a> if your browser does not redirect you automatically.', 'dwverify'), DWEmailVerify::instance()->redirect_url() );
				break;

			case 'invalid_hash':
				$output .= esc_html__('Sorry we could not verify your email address.', 'dwverify');
				break;
		}

		return $output;
	}
}

new DWEmailVerifyShortcode();
