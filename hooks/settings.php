<?php
/**
 * Create settings screen
 */

/**
 * Show error if no api key is set.
 */
add_action( 'admin_notices', function () {
	if ( ! hamail_enabled() && current_user_can( 'manage_options' ) ) {
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

            <?php if ( isset( $_GET['mail_sent'] ) && $_GET['mail_sent'] ) : ?>
            <div class="updated">
                <p><?php _e( 'Mail sent successfully. Please check how it looks like on your mail client.', 'hamail' ) ?></p>
            </div>
            <?php endif; ?>

            <p class="description">
				<?php esc_html_e( 'Setting value for Hamail. Please enter SendGrid API key. Mail Send permission is minimal requirement.', 'hamail' ) ?>
            </p>

            <form action="<?php echo esc_attr( admin_url( 'options-general.php' ) ) ?>" method="post">
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
                    <tr>
                        <th>
                            <label for="hamail_template_id"><?php _e( 'Template ID', 'hamail' ) ?></label>
                        </th>
                        <td>
                            <input type="text" name="hamail_template_id" id="hamail_template_id" class="regular-text"
                                   value="<?php echo esc_attr( get_option( 'hamail_template_id', '' ) ) ?>"/>
                            <p class="description">
								<?php printf(
									__( 'If you set template ID, all your default mail will be HTML. For more detail, see <a href="%s" target="_blank">SendGrid API doc</a>.', 'hamail' ),
									'https://sendgrid.com/docs/Glossary/transactional_email_templates.html'
								); ?>
                            </p>
                        </td>
                    </tr>
                </table>
				<?php submit_button( __( 'Update Setting', 'hamail' ) ) ?>
            </form>

            <hr/>

            <h2><?php _e( 'Test Mail', 'hamail' ) ?></h2>

            <p class="description">
                <?php _e( 'Try sending ', 'hamail' ) ?>
            </p>

            <form action="<?php echo esc_attr( admin_url( 'options-general.php' ) ) ?>" method="post">
                <input type="hidden" name="page" value="hamail-setting">
				<?php wp_nonce_field( 'hamail_test' ) ?>
                <table class="form-table">
                    <tr>
                        <th>
                            <label for="hamail_subject"><?php _e( 'Subject', 'hamail' ) ?></label>
                        </th>
                        <td>
                            <input type="text" name="hamail_subject" id="hamail_subject" class="regular-text"
                                   value=""/>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="hamail_to"><?php _e( 'Mail to', 'hamail' ) ?></label>
                        </th>
                        <td>
                            <input type="email" name="hamail_to" id="hamail_to" class="regular-text"
                                   value=""/>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="hamail_body"><?php _e( 'Mail Body', 'hamail' ) ?></label>
                        </th>
                        <td>
                            <textarea rows="5" type="text" name="hamail_body" id="hamail_body"
                                      style="width: 90%"></textarea>
                        </td>
                    </tr>
                </table>
				<?php submit_button( __( 'Send mail', 'hamail' ) ) ?>
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
	// Save setting
	if ( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'hamail_setting' ) ) {
		foreach (
			[
				'hamail_api_key',
				'hamail_default_from',
				'hamail_template_id',
			] as $key
		) {
			update_option( $key, $_REQUEST[ $key ] );
		}
		wp_redirect( admin_url( 'options-general.php?page=hamail-setting&updated=true' ) );
		exit;
	}
	// Send test mail of current setting
	if ( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'hamail_test' ) ) {
		$response = hamail_simple_mail( $_REQUEST['hamail_to'], $_REQUEST['hamail_subject'], $_REQUEST['hamail_body'] );
		if ( is_wp_error( $response ) ) {
		    $message = json_decode( $response->get_error_message(), true );
			wp_die( sprintf( '<pre>%s</pre>', var_export( $message, true ) ), get_status_header_desc( $response->get_error_code() ), [
				'back_link' => true,
				'status'    => $response->get_error_code(),
			] );
		} else {
			wp_redirect( admin_url( 'options-general.php?page=hamail-setting&mail_sent=true' ) );
			exit;
		}
	}
} );
