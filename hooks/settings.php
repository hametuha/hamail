<?php
/**
 * Create settings screen
 */

/**
 * Show error if no api key is set.
 */
add_action( 'admin_notices', function () {
	if ( ! get_option( 'hamail_api_key' ) && current_user_can( 'manage_options' ) ) {
		printf(
			'<div class="error"><p>%s</p></div>',
			sprintf(
				__( 'No API key is set. Please go to <a href="%s">Setting Page</a>.', 'hamail' ),
				admin_url( 'options-general.php?page=hamail-setting' )
			)
		);
	}
} );

/**
 * Add Setting page
 */
add_action( 'admin_menu', function () {
	add_options_page( __( 'Hamail Setting', 'hamail' ), __( 'Hamail Setting', 'hamail' ), 'manage_options', 'hamail-setting', function () {
		?>
        <div class="wrap">
            <h2>
                <span class="dashicons dashicons-email-alt"></span>
				<?php _e( 'Hamail Setting', 'hamail' ) ?>
            </h2>

            <p class="description">
				<?php esc_html_e( 'Setting value for Hamail. Please enter SendGrid API key. Mail Send permission is minimal requirement.', 'hamail' ) ?>
            </p>

            <form action="<?php echo esc_attr( admin_url( 'options-general.php' ) ) ?>">
                <input type="hidden" name="page" value="hamail-setting">
				<?php wp_nonce_field( 'hamail_setting' ) ?>
                <table class="form-table">
                    <tr>
                        <th>
                            <label for="hamail_api_key"><?php _e( 'SendGrid API key', 'hamail' ) ?></label>
                        </th>
                        <td>
                            <input type="text" name="hamail_api_key" id="hamail_api_key" class="regular-text"
                                   value="<?php echo esc_attr( get_option( 'hamail_api_key', '' ) ) ?>"/>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="hamail_default_from"><?php _e( 'Default Mail From', 'hamail' ) ?></label>
                        </th>
                        <td>
                            <input type="email" name="hamail_default_from" id="hamail_default_from" class="regular-text"
                                   value="<?php echo esc_attr( get_option( 'hamail_default_from', '' ) ) ?>"
                                   placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ) ?>"/>
                        </td>
                    </tr>
                </table>
				<?php submit_button( __( 'Update Setting', 'hamail' ) ) ?>
            </form>
        </div>
		<?php
	} );
} );

/**
 * Save settings
 */
add_action( 'admin_init', function () {
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return;
	}
	if ( ! isset( $_REQUEST['_wp_http_referer'] ) || '/wp-admin/options-general.php?page=hamail-setting' !== $_REQUEST['_wp_http_referer'] ) {
		return;
	}
	if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'hamail_setting' ) ) {
		return;
	}
	foreach (
		[
			'hamail_api_key',
			'hamail_default_from',
		] as $key
	) {
		update_option( $key, $_REQUEST[ $key ] );
	}
	wp_redirect( admin_url( 'options-general.php?page=hamail-setting&updated=true' ) );
} );
