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

		$user_id = absint( $_GET['user_id'] );

		if( empty( $_GET['verify_email'] ) || empty( $_GET['user_id'] ) || ! preg_match( '/^[a-f0-9]{32}$/', $_GET['verify_email'] ) || ! DWEmailVerify::instance()->needs_validation( $user_id ) ) {
			$this->validation_status = 'invalid_request';
			return;
		}


		if( DWEmailVerify::instance()->verify_if_valid() ) {
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
			echo '<meta http-equiv="refresh" content="5;url='. $redirect .'" />';
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

		switch ( $this->validation_status ) {
			case 'invalid_request':
				$output .= __('Invalid request', 'dwverify');
				break;

			case 'validated' :
				$output .= sprintf( __('Your email has been verified, you will be redirected in a few seconds <a href="%s">click here</a> if your browser does not redirect you automatically.', 'dwverify'), DWEmailVerify::instance()->redirect_url() );
				break;

			case 'invalid_hash':
				$output .= __('Sorry we could not verify your email address.', 'dwverify');
				break;
		}

		return $output;
	}
}

new DWEmailVerifyShortcode();
