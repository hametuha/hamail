<?php

namespace Hametuha\Hamail\API\Helper;


use Hametuha\Hamail\Pattern\Singleton;

/**
 * Recipients list in CSV format.
 */
class RecipientsList extends Singleton {

	/**
	 * {@inheritDoc}
	 */
	protected function init() {
		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
	}

	/**
	 * Register REST API
	 *
	 * @return void
	 */
	public function rest_api_init() {
		register_rest_route( 'hamail/v1', '/recipients/(?P<post_id>\d+)', [
			[
				'methods'             => 'GET',
				'args'                => [
					'post_id' => [
						'required'          => true,
						'type'              => 'integer',
						'validate_callback' => function ( $param ) {
							$post = get_post( $param );
							return ( $post && 'hamail' === $post->post_type );
						},
					],
				],
				'permission_callback' => [ $this, 'permission_callback' ],
				'callback'            => [ $this, 'callback' ],
			],
		] );
	}

	/**
	 * Handle REST API.
	 *
	 * @param \WP_REST_Request $request
	 * @return void|\WP_Error
	 */
	public function callback( $request ) {
		$post       = get_post( $request->get_param( 'post_id' ) );
		$recipients = hamail_get_message_recipients( $post );
		if ( empty( $recipients ) ) {
			return new \WP_Error( 'hamail_invalid_email', __( 'No recipients are listed in this post.', 'hamail' ) );
		}
		// Send HTTP headers.
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( sprintf( 'Content-Disposition: attachment; filename="recipients-%d-%s.csv"', $post->ID, date_i18n( 'YmdHis' ) ) );
		// User stream for CSV.
		$output = new \SplFileObject( 'php://output', 'w' );
		// Write header.
		$output->fputcsv( [ 'Email', 'Name', 'User ID', 'Role', 'User Exists' ] );
		// Write recipients.
		foreach ( $recipients as $id_or_email ) {
			$user = null;
			if ( is_numeric( $id_or_email ) ) {
				$user = get_userdata( $id_or_email );
			} else {
				$user_id = email_exists( $id_or_email );
				if ( $user_id ) {
					$user = get_userdata( $user_id );
				}
			}
			if ( $user ) {
				// User exists.
				$output->fputcsv( [ $user->user_email, $user->display_name, $id_or_email, implode( '/', $user->roles ), 'True' ] );
			} else {
				if ( is_numeric( $id_or_email ) ) {
					// This is ID, but no user found.
					$output->fputcsv( [ '', '', $id_or_email, '', 'False' ] );
				} else {
					// This is email, and user not found.
					$output->fputcsv( [ $id_or_email, '', '0', '', 'False' ] );
				}
			}
		}
		exit;
	}

	/**
	 * Permission callback.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool
	 */
	public function permission_callback( $request ) {
		return current_user_can( 'edit_post', $request->get_param( 'post_id' ) );
	}
}
