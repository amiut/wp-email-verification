<?php
/**
 * Settings
 *
 * @copyright dornaweb.com
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DWEmailVerifySettings{
	/**
	 * List of all wordpress pages
	 *
	 * @var array
	 */
	private $wp_pages;


	/**
	 * __construct
	 */
	public function __construct(){
		$this->wp_pages = $this->get_pages_array();

		add_action( 'admin_init', array( $this, 'settings' ) );
		add_action( 'admin_menu', [ $this, 'menus' ] );
	}

	/**
	 * Settings fields
	 */
	public function settings(){
		register_setting(
            'dwvrf_options',
            'dwvrf_options',
            NULL
        );

		/*
        register_setting(
            'dwvrf_options',
            'dw_email_verifications',
            NULL
        );*/

		register_setting(
            'dwvrf_options',
            'dw_verify_authorize_page',
            NULL
        );

		register_setting(
            'dwvrf_options',
            'dw_verify_autologin',
            NULL
        );

        register_setting(
            'dwvrf_options',
            'dw_verify_redirect_page',
            NULL
        );

		register_setting(
			'dwvrf_options',
			'dw_verify_max_resend_allowed',
			NULL
		);
	}

	/**
	 * Add menu pages
	 */
	public function menus(){
		add_options_page(
			__( 'Email verification settings', 'dwverify' ),
			__( 'email verification', 'dwverify' ),
			'manage_options',
			'dw-email-verifications.php',
			[ $this, 'settings_view' ]
		);
	}


	/**
	 * Settings HTML output
	 */
	public function settings_view(){ ?>

		<h1><?php echo esc_html__( 'Email verification settings', 'dwverify' ); ?></h1>

		<form method="post" action="options.php">
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row"><label for="dw_verify_authorize_page"><?php echo esc_html__('Authorize page: ', 'dwverify'); ?></label></th>
						<td>
							<select name="dw_verify_authorize_page" id="dw_verify_authorize_page" class="regular-text">
								<?php
								$pages = $this->wp_pages;
								foreach( $pages as $id => $page_name )
									printf('<option value="%d" %s>%s</option>', $id, selected( get_option('dw_verify_authorize_page'), $id ) , $page_name ); ?>
							</select>

						</td>
					</tr>

					<tr>
						<th scope="row"><?php echo esc_html__('Auto login' ,'dwverify'); ?></th>
						<td> <fieldset><legend class="screen-reader-text"><span><?php echo esc_html__('Auto login: ' ,'dwverify'); ?></span></legend><label for="dw_verify_autologin">
						<input name="dw_verify_autologin" value="1" id="dw_verify_autologin" <?php checked( get_option('dw_verify_autologin'), 1 ); ?> type="checkbox">
						<?php echo esc_html__('Automatically sign-in after verification' ,'dwverify'); ?></label>
						</fieldset></td>
					</tr>

					<tr>
						<th scope="row"><label for="dw_verify_redirect_page"><?php echo esc_html__('Redirect page: ', 'dwverify'); ?></label></th>
						<td>
							<select name="dw_verify_redirect_page" id="dw_verify_redirect_page" class="regular-text">
								<?php
								$pages = $this->wp_pages;
								foreach( $pages as $id => $page_name )
									printf('<option value="%d" %s>%s</option>', $id, selected( get_option('dw_verify_redirect_page'), $id ) , $page_name ); ?>
							</select>

						</td>
					</tr>

					<tr>
						<th scope="row"><label for="dw_verify_max_resend_allowed"><?php echo esc_html__('Max Re-send attempts: ', 'dwverify'); ?></label></th>
						<td>
							<input type="number" step="1" min="1" max="15" value="<?php echo esc_attr( get_option('dw_verify_max_resend_allowed') ); ?>" name="dw_verify_max_resend_allowed" id="dw_verify_max_resend_allowed" class="regular-text">
							<span style="padding-top: 10px; display: block; color: 90%; font-style: italic; color: #787878;" class="note"><?php echo esc_html__('Max number of re-send requests a user can make, more than that, his account will be locked.', 'dwverify'); ?></span>
						</td>
					</tr>
				</tbody>
			</table>

			<?php settings_fields( 'dwvrf_options' ); ?>
			<?php submit_button(); ?>
		</form>

	<?php }

	/**
	 * Get array of wordpress pages
	 *
	 * @return array
	 */
	private function get_pages_array() {
		$pages = array();
		$get_pages = get_pages('sort_column=post_parent,menu_order');

		foreach ($get_pages as $page) {
			$pages[$page->ID] = $page->post_title;
		}

		$none_selected = array("0" => esc_html__("-Select a page-", 'dwverify') );
		$wp_pages = $none_selected + $pages;

		return $wp_pages;
	}
}

new DWEmailVerifySettings();
