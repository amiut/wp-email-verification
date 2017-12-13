<?php
/**
 * Plugin Name: 	  Email Verification on Signups
 * Description: 	  Send a verification email to newly registered users.
 * Version:           1.1.2
 * Author:            Am!n
 * Author URI: 		  http://www.dornaweb.com
 * License:           MIT
 * Text Domain:       dwverify
 * Domain Path:   	  /lang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/vendor/autoload.php';
load_plugin_textdomain( 'dwverify', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );

class DWEmailVerify{

	/**
	 * Version
	 */
	const PLUGIN_VERSION = '1.1.2';

	/**
	 * @var str $secret
	 */
	public $secret = "25#-asdv8+abox";

	/**
	 * Value of all user meta rows w/ `verify-lock` key AFTER email has been verified
	 */
	const UNLOCKED = 'unlocked';

	/**
	 * Construct
	 */
	public function __construct(){
		$this->includes();

		add_action( 'user_register', [ $this, 'user_register' ] );
		add_filter( 'authenticate', [ $this, 'check_active_user' ], 100, 2 );
		add_action( 'wp_enqueue_scripts', [ $this, 'assets' ] );
		add_action( 'login_enqueue_scripts', [ $this, 'assets' ] );
	}

	/**
	 * Include required fiels
	 */
	public function includes(){
		include_once $this->path() . 'settings.php';
		include_once $this->path() . 'shortcode.php';
		include_once $this->path() . 'user-mods.php';
	}

	/**
	 * Instanciating
	 */
	public static function instance(){
		return new self();
	}

	/**
	 * Triggers when plugin gets activated
	 */
	public static function plugin_activated() {
		self::instance()->create_plugin_pages();
		self::instance()->set_default_settings();
	}

	/**
	 * Plugin assets
	 */
	public function assets(){
		wp_register_script( 'dw-verify-js', $this->url() . 'assets/js/verify-email.js', ['jquery'], null, true );
		wp_localize_script( 'dw-verify-js', 'dwverify', [
			'ajaxurl'		=> admin_url('admin-ajax.php'),
			'confirm_text'	=> __('Are you sure you want to re-send verification link?', 'dwverify')
		]);
		wp_enqueue_script( 'dw-verify-js' );
	}

	/**
	 * Create default pages
	 */
	public function create_plugin_pages() {
		$pages = [
			'authorize' => [
				'title' => __( 'Authorize', 'dwverify' ),
				'content' => '[dw-verify-email]',
				'option_id' => 'dw_verify_authorize_page'
			]
		];

		$pages_option = array();

		foreach( $pages as $slug => $page ) {
			$query = new WP_Query( 'pagename=' . $slug );
			if ( ! $query->have_posts() ) {

				// Add the page using the data from the array above
				update_option( $page['option_id'],
					wp_insert_post(
						[
							'post_content'   => $page['content'],
							'post_name'      => $slug,
							'post_title'     => $page['title'],
							'post_status'    => 'publish',
							'post_type'      => 'page',
							'ping_status'    => 'closed',
							'comment_status' => 'closed',
						]
					)
				);
			}
		}
	}

	/**
	 * Set default settings
	 */
	public function set_default_settings(){
		update_option('dw_verify_max_resend_allowed', 5);
	}

	/**
	 * Creates a hash when new user registers and stores the hash as a meta value
	 *
	 * @param int $user_id
	 */
	public function user_register( $user_id ){
		if( is_admin() && current_user_can( 'create_users') && ! empty( $_POST['skip_verification'] ) ){
			return; // ignore adding verify lock
		}

		$this->send_verification_link( $user_id );
		wp_redirect( add_query_arg( 'awaiting-verification', 'true', $this->authorize_page_url() ) );
		exit;
	}

	/**
	 * Lock user's account, send a verification email and ask them to verify their email address
	 * @param int  $user_id
	 */
	public function send_verification_link( $user_id ){
		$user = get_user_by('id', $user_id);

		$this->lock_user( $user_id );
		$this->send_email( $user );
	}

	/**
	 * Lock user
	 * @param int  $user_id
	 */
	public function lock_user( $user_id ){
		$user = get_user_by('id', $user_id);
		update_user_meta( $user_id, 'verify-lock', $this->generate_hash( $user->data->user_email ) );
	}

	/**
	 * Unlock user
	 * @param int  $user_id
	 */
	public function unlock_user( $user_id ){
		update_user_meta( $user_id, 'verify-lock', self::UNLOCKED );
	}

	/**
	 * Generate a url-friendly verification hash
	 *
	 * @param str $email
	 */
	public function generate_hash( $email = '' ){
		$key = $email.$this->secret . rand(0, 1000);

		return MD5( $key );
	}

	/**
	 * Prevents users from loggin in, if they have not verified their email address
	 *
	 * @param WP_User   $user
	 * @param str       $username
	 */
	public function check_active_user( $user, $username ){
		$lock = get_user_meta( $user->ID, "verify-lock", true );

		if( $lock && ! empty( $lock ) && $lock !== self::UNLOCKED ) {
			return new WP_Error( 'email_not_verified', sprintf(
				__('You have not verified your email address, please check your email and click on verification link we sent you, <a href="#resend" onClick="%s">Re-send the link</a>', 'dwverify'),
				"resend_verify_link('{$username}'); return false;"
			));
		}

		return $user;
	}

	/**
	 * Send verification email
	 *
	 * @param WP_User $user
	 */
	public function send_email( $user = false ){
		if( ! $user || ! $user instanceof WP_User )
			return;

		$lock = get_user_meta( $user->ID, "verify-lock", true );

		// Ignore if there is no lock
		if( ! $lock || empty( $lock ) )
			return;

		$user_email = $user->data->user_email;

		/**
		 * Add support for localized templates, just append your locale code to your template file name
		 *     - eg. verify-fa_IR.php
		 *			 verify-en-GB.php
		 */
		$template = file_exists( $this->path() .'tpl/emails/verify-'. get_locale() .'.php' ) ? $this->path() .'tpl/emails/verify-'. get_locale() .'.php' : $this->path() .'tpl/emails/verify.php';

		$template = apply_filters( 'dw_verify_email_template_path', $template );

		$email = (new WP_Mail)
		    ->to( $user_email )
		    ->subject( __('Verify your email address', 'dwverify') )
		    ->template( $template, apply_filters( 'dw_verify_email_template_args', [
		        'name' => $user->data->display_name,
		        'link' => add_query_arg( ['user_id' => $user->ID, 'verify_email' => $lock], $this->authorize_page_url() ),
		    ]) )
		    ->send();
	}

	/**
	 * Authorize page url
	 * This is a regular wordpress page that contains the [dw-verify-email] shortcode
	 */
	public function authorize_page_url(){
		return apply_filters( 'dw_verify_authorize_page_url', get_permalink( $this->authorize_page_id() ) );
	}

	/**
	 * Authorize page ID
	 * This is a regular wordpress page that contains the [dw-verify-email] shortcode
	 */
	public function authorize_page_id(){
		return get_option('dw_verify_authorize_page');
	}

	/**
	 * Does user needs email validation?
	 */
	public function needs_validation( $user_id ){
		$lock_value = get_user_meta( $user_id, 'verify-lock', true );
		
		/**
		 * This check allows segregating users into 3 groups: those who haven't validated b/c they signed up after
		 * this functionality was added, those who haven't validated b/c they simply haven't clicked the link in
		 * their email yet, and those who *have* validated. Users who existed before this plugin will continue to
		 * have access, but keeping these three groups separate allows for requiring them to validate in the future.
		 */
		if ( empty( $lock_value ) || $lock_value === self::UNLOCKED ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Validate hash
	 */
	public function hash_valid(){
		if( empty( $_GET['verify_email'] ) || empty( $_GET['user_id'] ) || ! preg_match( '/^[a-f0-9]{32}$/', $_GET['verify_email'] ) ) return;

		$user_id = absint( $_GET['user_id'] );

		// user already verified
		if( ! $this->needs_validation( $user_id ) ) {
			return;
		}

		$hash =  $_GET['verify_email'];

		if( $hash === get_user_meta( $user_id, 'verify-lock', true ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Verify user's email
	 */
	public function verify_if_valid( $signon = false ){
		if( ! $this->hash_valid() ) return;

		$user_id = absint( $_GET['user_id'] );
		$user = get_user_by('id', $user_id);

		// Unlock user from loggin in
		$this->unlock_user( $user_id );

		if( get_option('dw_verify_autologin') ) {
			wp_clear_auth_cookie();
		    wp_set_current_user ( $user->ID );
		    wp_set_auth_cookie  ( $user->ID );
		}

		return true;
	}

	/**
	 * Redirect after verification
	 */
	public function redirect_url(){
		return ( $red_page = get_option('dw_verify_redirect_page') ) ?
			apply_filters( 'dw_verify_redirect_url', get_permalink( $red_page ) ) :
			apply_filters( 'dw_verify_redirect_url', home_url() );

	}

	/**
	 * Return the plugin's path
	 */
	public function path(){
		return plugin_dir_path( __FILE__ );
	}

	/**
	 * Return the plugin's url
	 */
	public function url(){
		return plugins_url( '', __FILE__ ) . '/';
	}
}

new DWEmailVerify();

// hook plugin activated!
register_activation_hook( __FILE__, [ 'DWEmailVerify', 'plugin_activated' ] );


// functions
/**
 * Var_dump pre-ed!
 * For debugging purposes
 *
 * @param mixed $val desired variable to var_dump
 * @uses var_dump
 *
 * @return string
*/
if( !function_exists('dumpit') ) {
	function dumpit( $val ) {
		echo '<pre style="direction:ltr;text-align:left;">';
		var_dump( $val );
		echo '</pre>';
	}
}
