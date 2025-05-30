<?php

namespace Hametuha\Hamail\Ui;


use Hametuha\Hamail\API\MarketingEmail;
use Hametuha\Hamail\Pattern\Singleton;
use Hametuha\Hamail\Service\TemplateSelector;
use Hametuha\Hamail\Utility\ApiUtility;

/**
 * Settings screen.
 */
class SettingsScreen extends Singleton {

	use ApiUtility;

	/**
	 * @var string Slug of this screen.
	 */
	public $slug = 'hamail-dashboard';

	/**
	 * Constructor.
	 */
	protected function init() {
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		add_action( 'admin_menu', [ $this, 'admin_sub_menu' ], 20 );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
		add_action( 'admin_init', [ $this, 'settings_fields' ] );
		add_action( 'admin_init', [ $this, 'test_mail' ], 11 );
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public function admin_menu() {
		// Register menu page.
		add_menu_page( __( 'Mail Marketing', 'hamail' ), __( 'Mail Marketing', 'hamail' ), 'edit_posts', $this->slug, [ $this, 'render' ], 'dashicons-buddicons-pm', 40 );
	}

	/**
	 * Register admin sub menu.
	 *
	 * @return void
	 */
	public function admin_sub_menu() {
		// Marketing category.
		$slug     = sprintf( 'edit-tags.php?taxonomy=%s&post_type=%s', hamail_marketing_category_taxonomy(), MarketingEmail::POST_TYPE );
		$taxonomy = get_taxonomy( hamail_marketing_category_taxonomy() );
		add_submenu_page( $this->slug, $taxonomy->label, $taxonomy->label, 'manage_categories', $slug, null, 20 );
		// Register menu page.
		add_submenu_page( $this->slug, __( 'Settings', 'hamail' ), __( 'Settings', 'hamail' ), 'manage_options', $this->slug, [ $this, 'render' ], 40 );
	}

	/**
	 * Render screen.
	 *
	 * @return void
	 */
	public function render() {
		?>
		<div class="wrap">
			<h2>
				<span class="dashicons dashicons-email-alt"></span>
				<?php esc_html_e( 'Hamail Setting', 'hamail' ); ?>
			</h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php
				settings_fields( 'hamail-setting' );
				do_settings_sections( 'hamail-setting' );
				submit_button();
				?>
			</form>

			<hr/>

			<h2><?php esc_html_e( 'Export Users CSV', 'hamail' ); ?></h2>

			<?php if ( ! hamail_enabled() ) : ?>
				<p class="description">
					<?php esc_html_e( 'To export user list, please enter SendGrid API Key', 'hamail' ); ?>
				</p>
			<?php else : ?>
				<p class="description">
					<?php esc_html_e( 'Export users as CSV with the field format above.', 'hamail' ); ?>
				</p>
				<form method="post" action="<?php echo rest_url( 'hamail/v1/users/data' ); ?>" target="hamail-csv-downloader">
					<?php
					wp_nonce_field( 'wp_rest' );
					?>
					<table class="form-table">
						<tr>
							<th><label for="path"><?php esc_html_e( 'Server Path', 'hamail' ); ?></label></th>
							<td>
								<input type="text" name="path" id="path" class="regular-text" value="" placeholder="e.g. <?php echo esc_attr( dirname( ABSPATH ) . '/tmp' ); ?>"/>
								<p class="description">
									<?php
									printf( esc_html__( 'If set, the CSV will be generated as %s in the specified directory. If the generation succeeds, send notification to admin email.', 'hamail' ), '<code>user-csv-yyyymmddHHiiss.csv</code>' );
									esc_html_e( 'If your host\'s resource is enough(e.g. no timeout), leave this option empty.', 'hamail' )
									?>
								</p>
							</td>
						</tr>
					</table>
					<?php submit_button( 'Download' ); ?>
				</form>
				<iframe id="hamail-csv-downloader" name="hamail-csv-downloader" height="0" onload="console.log( this );"></iframe>
			<?php endif; ?>
			<hr />

			<h2><?php esc_html_e( 'Test Mail', 'hamail' ); ?></h2>

			<?php if ( ! hamail_enabled() ) : ?>
				<p class="description">
					<?php esc_html_e( 'You can test email after setting up SendGrid API key.', 'hamail' ); ?>
				</p>
			<?php else : ?>

				<?php if ( filter_input( INPUT_GET, 'mail_sent' ) ) : // Show message if mail is sent. ?>
					<div class="updated">
						<p><?php esc_html_e( 'Mail sent successfully. Please check how it looks like on your mail client.', 'hamail' ); ?></p>
					</div>
				<?php endif; ?>

				<p class="description">
					<?php esc_html_e( 'Try sending mail via SendGrid.', 'hamail' ); ?>
				</p>

				<form action="<?php echo esc_attr( admin_url( 'admin.php?page=' . $this->slug ) ); ?>"
						method="post">
					<?php wp_nonce_field( 'hamail_test' ); ?>
					<table class="form-table">
						<tr>
							<th>
								<label for="hamail_subject"><?php esc_html_e( 'Subject', 'hamail' ); ?></label>
							</th>
							<td>
								<input type="text" name="hamail_subject" id="hamail_subject" class="regular-text"
										value=""/>
							</td>
						</tr>
						<tr>
							<th>
								<label for="hamail_to"><?php esc_html_e( 'Mail to', 'hamail' ); ?></label>
							</th>
							<td>
								<input type="email" name="hamail_to" id="hamail_to" class="regular-text" value=""/>
							</td>
						</tr>
						<tr>
							<th>
								<label for="hamail_body"><?php esc_html_e( 'Mail Body', 'hamail' ); ?></label>
							</th>
							<td>
								<textarea rows="5" type="text" name="hamail_body" id="hamail_body"
											style="width: 90%"></textarea>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Send mail', 'hamail' ) ); ?>
				</form>
			<?php endif; ?>
		</div><!-- //.wrap -->
		<?php
	}

	/**
	 * Show error if no api key is set.
	 */
	public function admin_notices() {
		if ( ! hamail_enabled() && current_user_can( 'manage_options' ) ) {
			printf(
				'<div class="error"><p>[Hamail] %s</p></div>',
				wp_kses_post( sprintf(
					// translators: %s is link.
					__( 'No API key is set. Please go to <a href="%s">Setting Page</a>.', 'hamail' ),
					admin_url( 'admin.php?page=' . $this->slug )
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
					wp_kses_post( __( 'Hamail is now <strong>debug mode</strong>. SendGrid API will never be used. To disable debug mode, change <code>define( \'HAMAIL_DEBUG\', false )</code> in your wp-config.php.', 'hamail' ) )
				);
			} elseif ( WP_DEBUG ) {
				printf(
					'<div class="notice notice-warning"><p>%s</p></div>',
					wp_kses_post( __( 'WordPress debug mode <code>WP_DEBUG</code> detected. If this is development production, please consider <code>define( \'HAMAIL_DEBUG\', true )</code> in your wp-config.php. Hamail may sent real email to your users via Web API even if you don\'t have local mail server!', 'hamail' ) )
				);
			}
		}
	}

	/**
	 * Enqueue setting scripts
	 */
	public function admin_scripts( $slug ) {
		if ( 'toplevel_page_' . $this->slug === $slug ) {
			wp_enqueue_style( 'hamail-setting' );
			wp_enqueue_script( 'hamail-setting' );
		}
	}

	/**
	 * Register settings fields.
	 */
	public function settings_fields() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}
		//
		// Add setting section for hamail api.
		//
		add_settings_section( 'hamail_api_setting', __( 'SendGrid API Setting', 'hamail' ), function () {
			printf( '<p class="description">%s</p>', esc_html__( 'Setting value for Hamail. Please enter SendGrid API key. Mail Send permission is minimal requirement.', 'hamail' ) );
		}, 'hamail-setting' );
		foreach (
			[
				'hamail_api_key'                   => [
					__( 'SendGrid API key', 'hamail' ),
					'',
					'',
				],
				'hamail_default_from'              => [
					__( 'Default Mail From', 'hamail' ),
					'',
					get_option( 'admin_email' ),
				],
				'hamail_keep_wp_mail'              => [
					__( 'wp_mail function', 'hamail' ),
					__( 'Hamail can override all mail sent with <code>wp_mail</code> function. SMTP API works fine with other plugins that send emails(e.g. WooCommerce or Contact Form 7).', 'hamail' ),
					'',
				],
				TemplateSelector::OPTION_KEY       => [
					__( 'Template ID', 'hamail' ),
					sprintf(
					// translators: %s document URL.
						__( 'Default template is used when you choose "%3$s". For more detail, see <a href="%1$s" target="_blank">SendGrid API doc</a>. This feature work only with legacy templates. If you have none, create one via <a href="%2$s" target="_blank" rel="noopener noreferrer">SendGrind Legacy Templates</a>.', 'hamail' ),
						'https://sendgrid.com/docs/Glossary/transactional_email_templates.html',
						'https://sendgrid.com/templates',
						esc_html__( 'Override with Template', 'hamail' )
					),
					'',
				],
				'hamail_default_unsubscribe_group' => [
					__( 'Default Unsubscribe Group', 'hamail' ),
					sprintf(
						__( 'You can choose default unsubscribe group. This affects <code>%s</code>.', 'hamail' ),
						esc_html( '<%asm_group_unsubscribe_url%>' )
					),
					'',
				],
				'hamail_unsubscribe_group'         => [
					__( 'Unsubscribe Group', 'hamail' ),
					esc_html__( 'Optional. This helps in case you have multiple list in your SendGrid account. If you choose one, subscription links will be automatically added to your email. For design perfection, choose nothing or add CSS for additional HTML. This is available in future update.', 'hamail' ),
					'',
				],
			] as $key => $labels
		) {
			list( $label, $description, $placeholder ) = $labels;
			add_settings_field( $key, $label, function () use ( $key, $description, $placeholder ) {
				// Set message and input type.
				$message = '';
				$type    = 'text';
				$input   = '';
				switch ( $key ) {
					case TemplateSelector::OPTION_KEY:
						// If API key is not set,
						// Hide style option.
						if ( ! hamail_enabled() ) {
							$type    = 'hidden';
							$message = __( 'If SendGrid API key is valid, you can set template.', 'hamail' );
						} else {
							$value     = TemplateSelector::get_default_template();
							$templates = TemplateSelector::get_template_pull_down( 0, '', 'legacy' );
							if ( is_wp_error( $templates ) ) {
								$message = $templates->get_error_message();
							} else {
								$input = $templates;
							}
							$styles = hamail_get_mail_css();
							if ( $styles ) {
								// translators: %s is csv list of stylesheets path.
								$message = sprintf(
								// translators: %s is stylesheet.
									__( 'Stylesheet %s will be applied to your mail body.', 'hamail' ),
									implode( ', ', array_map( function ( $path ) {
										return sprintf( '<code>%s</code>', esc_html( $path ) );
									}, $styles ) )
								);
							} else {
								$message = __( 'If you put <code>hamail.css</code> in your theme\'s directory, they will be applied to mail body as inline css.', 'hamail' );
							}
						}
						break;
					case 'hamail_keep_wp_mail':
						$current = get_option( $key, '' );
						$options = array_map(
							function ( $option ) use ( $current ) {
								list( $value, $label ) = $option;
								return sprintf(
									'<option value="%s"%s>%s</option>',
									esc_attr( $value ),
									selected( $value, $current, false ),
									esc_html( $label )
								);
							},
							[
								[ '', __( 'Override with Template', 'hamail' ) ],
								[ '2', __( 'Use SMTP API for wp_mail()', 'hamail' ) ],
								[ '1', __( 'Keep defaut', 'hamail' ) ],
							]
						);
						$input   = sprintf( '<select name="%s">%s</select>', esc_attr( $key ), implode( ' ', $options ) );
						break;
					case 'hamail_default_unsubscribe_group':
					case 'hamail_unsubscribe_group':
						if ( ! hamail_enabled() ) {
							// No API key, display just message.
							$type     = 'hidden';
							$message .= __( 'No API key is set.', 'hamail' );
						} else {
							$groups = $this->get_unsubscribe_group( true );
							if ( is_wp_error( $groups ) ) {
								$message = $groups->get_error_message();
								$type    = 'hidden';
							} else {
								switch ( $key ) {
									case 'hamail_default_unsubscribe_group':
										$input .= sprintf( '<select name="%s"><option value="">%s</option>', esc_html( $key ), esc_html__( 'Not Set', 'hamail' ) );
										foreach ( $groups as $group ) {
											$input .= sprintf(
												'<option value="%s" %s>%s</option>',
												esc_attr( $group['id'] ),
												selected( (string) $group['id'], get_option( 'hamail_default_unsubscribe_group' ), false ),
												esc_html( $group['name'] )
											);
										}
										$input .= '</select>';
										break;
									case 'hamail_unsubscribe_group':
										foreach ( $groups as $group ) {
											$name = $group['name'];
											if ( $group['is_default'] ) {
												$name .= __( '(Default)', 'hamail' );
											}
											$unsubscribe_group = (array) get_option( 'hamail_unsubscribe_group', [] );
											$input            .= sprintf(
												'<label style="display: block; margin: 1em 0;"><input type="checkbox" name="hamail_unsubscribe_group[]" value="%s" %s/> %s<br /><span class="description">%s</span></label>',
												esc_attr( $group['id'] ),
												checked( in_array( (string) $group['id'], $unsubscribe_group, true ), true, false ),
												esc_html( $name ),
												esc_html( $group['description'] )
											);
										}
										break;
								}
							}
						}
						break;
				}
				if ( ! $input ) {
					$input = sprintf(
						'<input type="%4$s" name="%1$s" id="%1$s" class="regular-text" value="%2$s" placeholder="%3$s" />',
						$key,
						get_option( $key, '' ),
						$placeholder,
						esc_attr( $type )
					);
				}
				echo $input;
				if ( $message ) {
					echo wp_kses_post( "<p>{$message}</p>" );
				}
				if ( $description ) {
					echo wp_kses_post( sprintf( '<p class="description">%s</p>', $description ) );
				}
			}, 'hamail-setting', 'hamail_api_setting' );
			register_setting( 'hamail-setting', $key );
		}
		// If enabled, sync field is available.
		if ( hamail_enabled() ) {
			// List sync.
			add_settings_section( 'hamail_list_setting', __( 'List Sync', 'hamail' ), function () {
				printf( '<p class="description">%s</p>', esc_html__( 'To sync your user list to SendGrid, choose list and conditions.', 'hamail' ) );
			}, 'hamail-setting' );
			// List to be synced.
			add_settings_field( 'hamail_list_to_sync', __( 'List to sync', 'hamail' ), function () {
				$lists = hamail_available_lists();
				?>
				<p class="description"><?php esc_html_e( 'If you have no lists, please make it first on SendGrid.', 'hamail' ); ?></p>
				<select name="hamail_list_to_sync" id="hamail_list_to_sync">
					<?php foreach ( $lists as $value => $label ) : ?>
						<option
							value="<?php echo esc_attr( $value ); ?>"<?php selected( $value, hamail_active_list() ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php
			}, 'hamail-setting', 'hamail_list_setting' );
			register_setting( 'hamail-setting', 'hamail_list_to_sync' );

			// Field to sync.
			add_settings_field( 'hamail_fields_to_sync', __( 'Fields Mapping', 'hamail' ), function () {
				if ( hamail_enabled() ) {
					$fields         = hamail_get_custom_fields();
					$current_fields = hamail_fields_array();
					?>
					<textarea rows="2" id="hamail_fields_to_sync" name="hamail_fields_to_sync"
								placeholder="<?php esc_attr_e( 'Put CSV here in 2 lines.', 'hamail' ); ?>"
					><?php echo esc_textarea( get_option( 'hamail_fields_to_sync', '' ) ); ?></textarea>
					<?php if ( is_wp_error( $current_fields ) && 200 !== $current_fields->get_error_data()['status'] ) : ?>
						<p class="hamail-format-error"><?php echo esc_html( $current_fields->get_error_message() ); ?></p>
					<?php endif; ?>
					<p class="description">
						<?php esc_html_e( 'Write in CSV format. Line 1 is SendGrid, Line 2 is your WordPress. Absent fields are ignored.', 'hamail' ); ?>
						<?php echo wp_kses_post( __( 'WordPress <code>site</code> field is required for multiple site. You can add or edit custom fields on SendGrid.', 'hamail' ) ); ?>
					</p>
					<table class="hamail-csv-preview">
						<caption><?php esc_html_e( 'CSV Preview', 'hamail' ); ?></caption>
						<tr>
							<th>SendGrid</th>
						</tr>
						<tr>
							<th>WordPress</th>
						</tr>
					</table>
					<dl class="hamail-description">
						<dt>SendGrid</dt>
						<dd>
							<?php
							esc_html_e( 'Available Fields(* is default): ', 'hamail' );
							echo implode( ' ', array_map( function ( $field, $id ) {
								return sprintf( '<code>%s%s</code>', esc_html( $field ), $id ? '' : '<sup>*</sup>' );
							}, array_keys( $fields ), array_values( $fields ) ) );
							?>
						</dd>
						<dt>WordPress</dt>
						<dd>
							<?php
							esc_html_e( 'Available Fields: ', 'hamail' );
							echo wp_kses_post( __( 'User object property(ex. <code>user_email</code>, <code>ID</code>) and user_meta key. <code>role</code> is also available.', 'hamail' ) );
							?>
						</dd>
					</dl>
					<?php
				} else {
					printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html__( 'SendGrid is not connected.', 'hamail' ) );
				}
			}, 'hamail-setting', 'hamail_list_setting' );
			register_setting( 'hamail-setting', 'hamail_fields_to_sync' );
			// Site key.
			add_settings_field( 'hamail_site_key', __( 'Site Specific Field', 'hamail' ), function () {
				printf( '<input type="text" class="regular-text" name="%1$s" id="%1$s" value="%2$s" />', 'hamail_site_key', esc_attr( get_option( 'hamail_site_key' ) ) );
				printf(
					'<p class="description">%s</p>',
					esc_html__( 'This field will determine which user to put into list.', 'hamail' )
				);
			}, 'hamail-setting', 'hamail_list_setting' );
			register_setting( 'hamail-setting', 'hamail_site_key' );
		}
	}

	/**
	 * Send test email.
	 *
	 * @return void
	 */
	public function test_mail() {
		if ( ! wp_verify_nonce( filter_input( INPUT_POST, '_wpnonce' ), 'hamail_test' ) ) {
			return;
		}
		$to       = filter_input( INPUT_POST, 'hamail_to' );
		$subject  = filter_input( INPUT_POST, 'hamail_subject' );
		$body     = filter_input( INPUT_POST, 'hamail_body' );
		$response = hamail_simple_mail( $to, $subject, $body );
		if ( is_wp_error( $response ) ) {
			$response = $response->get_error_message();
			$json     = json_decode( $response, true );
			if ( $json ) {
				$code    = $response->get_error_code();
				$message = var_export( $json, true );
			} else {
				$code    = 500;
				$message = $response;
			}
			wp_die( sprintf( '<pre>%s</pre>', esc_html( $message ) ), get_status_header_desc( $code ), [
				'back_link' => true,
				'status'    => $code,
			] );
		} else {
			wp_redirect( admin_url( 'admin.php?page=' . $this->slug . '&mail_sent=true' ) );
			exit;
		}
	}
}
