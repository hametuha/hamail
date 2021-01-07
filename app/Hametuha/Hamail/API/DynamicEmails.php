<?php

namespace Hametuha\Hamail\API;


use Hametuha\Hamail\Pattern\DynamicEmailTemplate;
use Hametuha\Hamail\Pattern\Singleton;

/**
 * Dyanmic email bootstrap.
 *
 * @package hamail
 */
class DynamicEmails extends Singleton {

	/**
	 * Instances of dynamic template.
	 *
	 * @var DynamicEmailTemplate[]
	 */
	protected $emails = [];

	/**
	 * Constructor.
	 */
	protected function init() {
		add_action( 'init', [ $this, 'register_dynamic_emails' ], 20 );
	}

	/**
	 * Enable dynamic emails.
	 */
	public function register_dynamic_emails() {
		foreach ( $this->get_dynamic_emails() as $class_name ) {
			if ( class_exists( $class_name ) && is_subclass_of( $class_name, DynamicEmailTemplate::class ) ) {
				/* @var DynamicEmailTemplate $instance */
				$instance = $class_name::get_instance();
				$key      = $instance->key();
				if ( ! isset( $this->emails[ $key ] ) ) {
					$this->emails[ $key ] = $instance;
				}
			}
		}
		if ( empty( $this->emails ) || ! hamail_enabled() ) {
			// No dynamic email registered, skip.
			return;
		}
		// Register admin screen.
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		// Register REST API.
		add_action( 'rest_api_init', [ $this, 'register_rest_api' ] );
	}

	/**
	 * Add menu page for dynamic emails.
	 */
	public function add_menu_page() {
		add_submenu_page( 'edit.php?post_type=hamail', __( 'Dynamic Emails', 'hamail' ), __( 'Dynamic Emails', 'hamail' ), 'edit_others_posts', 'hamai-dynamic', function() {
			wp_enqueue_style( 'hamail-dynamics' );
			wp_enqueue_script( 'hamail-dynamics' );
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Dynamic Emails', 'hamail' ); ?></h1>
				<p class="description">
					<?php esc_html_e( 'Dynamic Emails are the email sent programmatically.', 'hamail' ); ?>
				</p>
				<table class="wp-list-table widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'hamail' ); ?></th>
							<th><?php esc_html_e( 'Condition', 'hamail' ); ?></th>
							<th><?php esc_html_e( 'Active', 'hamail' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $this->emails as $email ) : ?>
						<tr>
							<th>
								<strong><?php echo esc_html( $email->get_label() ); ?></strong><br />
								<span>
									<?php echo esc_html( $email->get_description() ); ?>
								</span>
							</th>
							<td>
								<?php echo esc_html( $email->get_condition() ); ?>
							</td>
							<td>
								<input type="checkbox" value="1" class="hamail-dynamics-toggle"
									name="<?php echo esc_attr( $email->key() ); ?>"
									id="<?php echo esc_attr( $email->key() ); ?>"
									<?php checked( $email->is_active() ); ?>/>
								<label for="<?php echo esc_attr( $email->key() ); ?>">
									<span class="active">
										<?php esc_html_e( 'Active', 'hamail' ); ?>
									</span>
									<span class="inactive">
										<?php esc_html_e( 'Inactive', 'hamail' ); ?>
									</span>
									<span class="dashicons dashicons-update"></span>
									<span class="message"></span>
								</label>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php
		} );
	}

	/**
	 * Dynamic emails
	 *
	 * @return string[]
	 */
	protected function get_dynamic_emails() {
		return apply_filters( 'hamail_dynamic_emails', [] );
	}

	/**
	 * Register REST API
	 */
	public function register_rest_api() {
		$args = [
			'mail_key' => [
				'type'              => 'string',
				'required'          => true,
				'description'       => __( 'Dynamic email key', 'hamail' ),
				'validate_callback' => function( $var ) {
					return ! empty( $var ) && array_key_exists( $var, $this->emails );
				},
			],
		];
		register_rest_route( 'hamail/v1', 'dynamics/(?P<mail_key>[a-z0-9\-]+)', [
			[
				'methods'             => 'POST',
				'args'                => $args,
				'callback'            => [ $this, 'activate_callback' ],
				'permission_callback' => [ $this, 'rest_api_permission' ],
			],
			[
				'methods'             => 'DELETE',
				'args'                => $args,
				'callback'            => [ $this, 'deactivate_callback' ],
				'permission_callback' => [ $this, 'rest_api_permission' ],
			],
		] );
	}

	/**
	 * Activate callback.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function activate_callback( $request ) {
		$instance = $this->emails[ $request->get_param( 'mail_key' ) ];
		$result   = $instance->activate();
		if ( is_wp_error( $result ) ) {
			return $result;
		} elseif ( true === $result ) {
			return new \WP_REST_Response( [
				// translators: %s is mail label.
				'message' => sprintf( __( '%s is activated.', 'hamail' ), $instance->get_label() ),
			] );
		} else {
			return new \WP_Error( 'hamail_api_error', __( 'Undefined error occurred.', 'hamail' ) );
		}
	}

	/**
	 * Deactivate callback.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function deactivate_callback( $request ) {
		$instance = $this->emails[ $request->get_param( 'mail_key' ) ];
		$result   = $instance->deactivate();
		if ( is_wp_error( $result ) ) {
			return $result;
		} elseif ( true === $result ) {
			return new \WP_REST_Response( [
				// translators: %s is mail label.
				'message' => sprintf( __( '%s is deactivated.', 'hamail' ), $instance->get_label() ),
			] );
		} else {
			return new \WP_Error( 'hamail_api_error', __( 'Undefined error occurred.', 'hamail' ) );
		}
	}

	/**
	 * Permission callback for REST API
	 *
	 * @param \WP_REST_Request $request
	 * @return bool
	 */
	public function rest_api_permission( $request ) {
		return current_user_can( 'edit_others_posts' );
	}
}
