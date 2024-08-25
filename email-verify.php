<?php
/**
 * Plugin Name: 	  Email Verification on Signups
 * Description: 	  Send a verification email to newly registered users.
 * Version:           1.1.5
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
	const PLUGIN_VERSION = '1.1.5';

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
			'confirm_text'	=> esc_html__('Are you sure you want to re-send verification link?', 'dwverify')
		]);
		wp_enqueue_script( 'dw-verify-js' );
	}

	/**
	 * Create default pages
	 */
	public function create_plugin_pages() {
		$pages = [
			'authorize' => [
				'title' => esc_html__( 'Authorize', 'dwverify' ),
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
		$skip_verification = filter_input(INPUT_POST, 'skip_verification', FILTER_SANITIZE_SPECIAL_CHARS);
		if( is_admin() && current_user_can( 'create_users') && ! empty( $skip_verification ) ){
			return; // ignore adding verify lock
		}

		$this->send_verification_link( $user_id );

		/**
		 * Default behavior is to redirect to the authorize page and exit. However, that prevents other plugins that hook into the
		 * registration process from being run. For example, a plugin that, after registration, bestows special privileges on
		 * the new user, would be short-circuited if execution exits here. Therefore, make it possible to override the redirect
		 * via this filter.
		 */
		$should_redirect = true;
		$should_redirect = apply_filters( 'dw_verify_should_redirect_after_register', $should_redirect );
		if( $should_redirect ) {
			wp_redirect( add_query_arg( 'awaiting-verification', 'true', $this->authorize_page_url() ) );
			exit;
		} else {
			return;
		}
	}

	/**
	 * Lock user's account, send a verification email and ask them to verify their email address
	 * @param int  $user_id
	 */
	public function send_verification_link( $user_id ){
		$user = get_user_by('id', $user_id);

		$token = $this->lock_user( $user_id );
		$this->send_email( $token, $user );
	}

	/**
	 * Lock user
	 * @param int  $user_id
	 */
	public function lock_user( $user_id ){
		$user = get_user_by('id', $user_id);
		$token = $this->generate_token();
		$hash_method = 'sha256';
		//update_user_meta( $user_id, 'verify-lock', $token );
		update_user_meta( $user_id, 'verify-lock', hash( $hash_method, $token ) );
		update_user_meta( $user_id, 'verify-lock-hash-method', $hash_method );
		return $token;
	}

	/**
	 * Unlock user
	 * @param int  $user_id
	 */
	public function unlock_user( $user_id ){
		update_user_meta( $user_id, 'verify-lock', self::UNLOCKED );
	}

	/**
	 * Generate a cryptographically-secure, url-friendly verification token
	 *
	 * @param str $email
	 */
	public function generate_token(){
		$bytes = random_bytes( 16 );
		return bin2hex( $bytes );
	}

	/**
	 * Prevents users from loggin in, if they have not verified their email address
	 *
	 * @param WP_User   $user
	 * @param str       $username
	 */
	public function check_active_user( $user, $username ){
		$needs_verification = $this->needs_validation( $user->ID );
		if( $needs_verification !== false ) {
			$username = esc_attr($username);
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
	public function send_email( $token, $user = false ){
		if( ! $user || ! $user instanceof WP_User )
			return;

		$lock = $this->needs_validation( $user->ID );

		// Ignore if user is not locked
		if( $lock === false )
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
		    ->subject( esc_html__('Verify your email address', 'dwverify') )
		    ->template( $template, apply_filters( 'dw_verify_email_template_args', [
		        'name' => $user->data->display_name,
		        'link' => add_query_arg( ['user_id' => $user->ID, 'verify_email' => $token], $this->authorize_page_url() ),
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
	 * Return false if user doesn't need validation, stored hash if it does
	 */
	public function needs_validation( $user_id ){
		$needs_verification = false;
		// If the verification metadata doesn't even exist, assume token hasn't been issued. That would indicate that
		// the user registered at a time when this plugin was not installed/active. Don't require verification in that case.
		if( metadata_exists( 'user', $user_id, "verify-lock" ) ) {
			$lock = get_user_meta( $user_id, "verify-lock", true );
			if( $lock !== self::UNLOCKED ) {
				$needs_verification = $lock;
			}
		}
		return $needs_verification;
	}

	/**
	 * Validate hash
	 */
	public function hash_valid( $user_token, $user_id ){

		// user already verified
		$stored_hash = $this->needs_validation( $user_id );
		if( $stored_hash === false )
			return;

		// If the token stored for this user was hashed, hash the received token using the same method
		// Retrieving the method like this means the plugin will be backwards-compatible with versions that didn't hash the token
		$hash_method = get_user_meta( $user_id, 'verify-lock-hash-method', true );
		if( $hash_method ) {
			$user_token = hash( $hash_method, $user_token );
		}

		return hash_equals( $stored_hash, $user_token );
	}

	/**
	 * Verify user's email
	 */
	public function verify_if_valid( $user_hash, $user_id, $signon = false ){
		if( ! $this->hash_valid( $user_hash, $user_id ) ) return;

		$user = get_user_by('id', $user_id);

		// Unlock user from logging in
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
