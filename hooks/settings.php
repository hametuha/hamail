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
			wp_kses_post( sprintf(
				__( 'No API key is set. Please go to <a href="%s">Setting Page</a>.', 'hamail' ),
				admin_url( 'options-general.php?page=hamail-setting' )
			) )
		);
	}
	if ( hamail_enabled() ) {
	    // Display only hamail related page.
	    $screen = get_current_screen();
        if ( ! ( ( 'edit-hamail' === $screen->id ) || ( 'hamail' === $screen->post_type ) || ( 'settings_page_hamail-setting' === $screen->id ) ) ) {
            return;
		}
	    if ( hamail_is_debug() ) {
			printf(
				'<div class="notice notice-info"><p>%s</p></div>',
				wp_kses_post( __( 'Hameil is now <strong>debug mode</strong>. Sendgrid API will never used. To disabled debug mode, change <code>define( \'HAMAIL_DEBUG\', false )</code> in your wp-config.php.', 'hamail' ) ) );
        } elseif ( WP_DEBUG ) {
		    printf(
			    '<div class="notice notice-warning"><p>%s</p></div>',
			    wp_kses_post( __( 'WordPress debug mode <code>WP_DEBUG</code> detected. If this is development production, please consider <code>define( \'HAMAIL_DEBUG\', true )</code> in your wp-config.php. Hamail may sent real email to your users via Web API even if you don\'t have local mail server!', 'hamail' ) ) );
        }
	}
} );

/**
 * Enqueue setting scripts
 */
add_action( 'admin_enqueue_scripts', function( $slug ) {
    if ( 'settings_page_hamail-setting' === $slug ) {
		wp_enqueue_style( 'hamail-setting' );
        wp_enqueue_script( 'hamail-setting' );
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
				<?php esc_html_e( 'Hamail Setting', 'hamail' ) ?>
            </h2>

            <form method="post" action="options.php">
				<?php
                settings_fields( 'hamail-setting' );
				do_settings_sections( 'hamail-setting' );
				submit_button();
				?>
            </form>
	
			<hr/>
			
			<h2><?php esc_html_e( 'Test Mail', 'hamail' ) ?></h2>
			
			<?php if ( ! hamail_enabled() ) : ?>
				<p class="description">
					<?php esc_html_e( 'You can test email after setting up SendGrid API key.', 'hamail' ) ?>
				</p>
			<?php else : ?>
		
				<?php if ( filter_input( INPUT_GET, 'mail_sent' ) ) : // Show message if mail is sent. ?>
					<div class="updated">
						<p><?php esc_html_e( 'Mail sent successfully. Please check how it looks like on your mail client.', 'hamail' ) ?></p>
					</div>
				<?php endif; ?>
			
				<p class="description">
					<?php esc_html_e( 'Try sending mail via SendGrid.', 'hamail' ) ?>
				</p>

				<form action="<?php echo esc_attr( admin_url( 'options-general.php' ) ) ?>" method="post">
					<input type="hidden" name="page" value="hamail-setting">
					<?php wp_nonce_field( 'hamail_test' ) ?>
					<table class="form-table">
						<tr>
							<th>
								<label for="hamail_subject"><?php esc_html_e( 'Subject', 'hamail' ) ?></label>
							</th>
							<td>
								<input type="text" name="hamail_subject" id="hamail_subject" class="regular-text"
									   value=""/>
							</td>
						</tr>
						<tr>
							<th>
								<label for="hamail_to"><?php esc_html_e( 'Mail to', 'hamail' ) ?></label>
							</th>
							<td>
								<input type="email" name="hamail_to" id="hamail_to" class="regular-text"
									   value=""/>
							</td>
						</tr>
						<tr>
							<th>
								<label for="hamail_body"><?php esc_html_e( 'Mail Body', 'hamail' ) ?></label>
							</th>
							<td>
                            <textarea rows="5" type="text" name="hamail_body" id="hamail_body"
									  style="width: 90%"></textarea>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Send mail', 'hamail' ) ) ?>
				</form>
			<?php endif; ?>
        </div><!-- //.wrap -->
		<?php
	} );
} );

/**
 * Settings fields.
 */
add_action( 'admin_init', function () {
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return;
	}
	//
	// Add setting section for hamail api.
    //
 	add_settings_section(
		'hamail_api_setting',
	    __( 'SendGrid API Setting', 'hamail' ),
		function() {
		    printf( '<p class="description">%s</p>', esc_html__(  'Setting value for Hamail. Please enter SendGrid API key. Mail Send permission is minimal requirement.', 'hamail' ) );
		},
		'hamail-setting'
	);
	foreach ( [
	        'hamail_api_key' => [ __( 'SendGrid API key', 'hamail' ), '', '' ],
	        'hamail_default_from' => [ __( 'Default Mail From', 'hamail' ), '', get_option( 'admin_email' ) ],
	        'hamail_template_id' => [ __( 'Template ID', 'hamail' ), sprintf(
                __( 'If you set template ID, all your default mail will be HTML. For more detail, see <a href="%s" target="_blank">SendGrid API doc</a>.', 'hamail' ),
                'https://sendgrid.com/docs/Glossary/transactional_email_templates.html'
            ), '' ],
              ] as $key => $labels ) {
	    list( $label, $description, $placeholder ) = $labels;
		add_settings_field(
			$key,
			$label,
			function() use ( $key, $description, $placeholder ) {
			    printf(
                    '<input type="text" name="%1$s" id="%1$s" class="regular-text" value="%2$s" placeholder="%3$s" />',
                    $key,
                    get_option( $key, '' ),
                    $placeholder
                );
			    if ( $description ) {
			        printf( '<p class="description">%s</p>', $description );
                }
			},
			'hamail-setting',
			'hamail_api_setting'
		);
		register_setting( 'hamail-setting', $key );
	}
	// If enabled, sync field is available.
	if ( hamail_enabled() ) {
		// List sync.
		add_settings_section(
			'hamail_list_setting',
			__( 'List Sync', 'hamail' ),
			function () {
				printf( '<p class="description">%s</p>', esc_html__( 'To sync your user list to SendGrid, choose list and conditions.', 'hamail' ) );
			},
			'hamail-setting'
		);
		// List to be synced.
		add_settings_field(
			'hamail_list_to_sync',
			__( 'List to sync', 'hamail' ),
			function () {
				$lists = hamail_available_lists();
				if  ( ! $lists ) : ?>
					<p class="description"><?php esc_html_e( 'If you have no lists, please make it first on SendGrid.', 'hamail' ) ?></p>
				<?php else : ?>
					<select name="hamail_list_to_sync" id="hamail_list_to_sync">
						<?php foreach ( $lists as $value => $label ) : ?>
							<option value="<?= esc_attr( $value ) ?>"<?php selected( $value, hamail_active_list() ) ?>><?php echo esc_html( $label ) ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif;
			},
			'hamail-setting',
			'hamail_list_setting'
		);
		register_setting( 'hamail-setting', 'hamail_list_to_sync' );
		
		// Field to sync.
		add_settings_field(
			'hamail_fields_to_sync',
			__( 'Fields Mapping',  'hamail' ),
			function(){
				if ( hamail_enabled() ) {
					$fields = hamail_get_custom_fields();
					$current_fields = hamail_fields_array();
					?>
					<textarea rows="2" id="hamail_fields_to_sync" name="hamail_fields_to_sync"
							  placeholder="<?php esc_attr_e( 'Put CSV here in 2 lines.', 'hamail' ) ?>"
					><?= esc_textarea( get_option( 'hamail_fields_to_sync', '' ) ) ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Write in CSV format. Line 1 is SendGrid, Line 2 is your WordPress. Absent fields are ignored.', 'hamail' ) ?>
						<?php _e( 'WordPress <code>site</code> field is required for multiple site. You can add or edit custom fields on SendGrid.', 'hamail' ) ?>
					</p>
					<table class="hamail-csv-preview">
						<caption><?php esc_html_e( 'CSV Preview', 'hamail' ) ?></caption>
						<tr>
							<th>SendGrid</th>
						</tr>
						<tr>
							<th>WordPress</th>
						</tr>
					</table>
					<?php if ( is_wp_error( $current_fields ) ) : ?>
						<div class="notice notice-error">
							<p><?= esc_html( $current_fields->get_error_message() ) ?></p>
						</div>
					<?php endif; ?>
					<dl class="hamail-description">
						<dt>SendGrid</dt>
						<dd>
							<?php esc_html_e( 'Available Fields(* is default): ', 'hamail' ) ?>
							<?= implode( ' ', array_map( function( $field, $id ) {
								return sprintf( '<code>%s%s</code>', esc_html( $field ), $id ? '' : '<sup>*</sup>' );
							}, array_keys( $fields ), array_values( $fields ) ) ) ?>
						</dd>
						<dt>WordPress</dt>
						<dd>
							<?php esc_html_e( 'Available Fields: ', 'hamail' ) ?>
							<strong><?= wp_kses_post( __( 'User object property(ex. <code>user_email</code>, <code>ID</code>) and user_meta key. <code>role</code> is also available.', 'hamail' ) ) ?></strong>
						</dd>
					</dl>
					<?php
				} else {
					printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html__( 'SendGrid is not connected.', 'hamail' ) );
				}
			},
			'hamail-setting',
			'hamail_list_setting'
		);
		register_setting( 'hamail-setting', 'hamail_fields_to_sync' );
		// Site key
		add_settings_field(
			'hamail_site_key',
			__( 'Site Specific Field',  'hamail' ),
			function() {
				printf( '<input type="text" class="regular-text" name="%1$s" id="%1$s" value="%2$s" />', 'hamail_site_key', esc_attr( get_option( 'hamail_site_key' ) ) );
				printf(
					'<p class="description">%s</p>',
					esc_html__( 'This field will determine which user to put into list.', 'hamail' )
				);
			},
			'hamail-setting',
			'hamail_list_setting'
		);
		register_setting( 'hamail-setting', 'hamail_site_key' );
	}
	
} );

/**
 * Send test mail of current setting
 */
add_action( 'admin_init', function() {
	if ( ! wp_verify_nonce( filter_input( INPUT_POST, '_wpnonce' ), 'hamail_test' ) ) {
		return;
	}
	$response = hamail_simple_mail( $_REQUEST[ 'hamail_to' ], $_REQUEST[ 'hamail_subject' ], $_REQUEST[ 'hamail_body' ] );
	if ( is_wp_error( $response ) ) {
		$message = json_decode( $response->get_error_message(), true );
		wp_die( sprintf( '<pre>%s</pre>', var_export( $message, true ) ), get_status_header_desc( $response->get_error_code() ), [
			'back_link' => true,
			'status'    => $response->get_error_code(),
		] );
	} else {
		wp_redirect( admin_url( 'edit.php?post_type=hamail&page=hamail-setting&mail_sent=true' ) );
		exit;
	}
}, 11 );
