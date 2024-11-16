<?php

namespace Hametuha\Hamail\API;


use Hametuha\Hamail\Pattern\Singleton;

/**
 * User data generator.
 *
 *
 */
class UserDataGenerator extends Singleton {

	protected function init() {
		add_action( 'rest_api_init', [ $this, 'register_rest_api' ] );
		add_action( 'hamail_generate_csv_background', [ $this, 'generate_csv_in_background' ] );
	}

	public function register_rest_api() {
		register_rest_route( 'hamail/v1', '/users/data', [
			'methods'             => 'POST',
			'args'                => [
				'path' => [
					'type'              => 'string',
					'required'          => false,
					'default'           => '',
					'validate_callback' => function ( $param ) {
						if ( empty( $param ) ) {
							// Allow empty.
							return true;
						}
						if ( ! is_dir( $param ) || ! is_writable( $param ) ) {
							return new \WP_Error( 'hamail_api_error', __( 'Directory must exist and be writable.', 'hamail' ) );
						}
						return true;
					},
				],
			],
			'callback'            => [ $this, 'generate_users_via_api' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );
	}

	/**
	 * Generate users via API.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate_users_via_api( $request ) {
		$path = $request->get_param( 'path' );
		if ( empty( $path ) ) {
			// Generate CSV.
			header( 'Content-Type: text/csv; charset=UTF-8;' );
			header( sprintf( 'Content-Disposition: attachment; filename="%s"', $this->file_name() ) );
			$result = $this->to_path( 'php://output' );
			if ( is_wp_error( $result ) ) {
				return $result;
			} else {
				exit;
			}
		} else {
			$result = wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'hamail_generate_csv_background', [
				'path' => $path,
			] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return new \WP_REST_Response( [
				'message' => __( 'CSV generation is scheduled.', 'hamail' ),
			] );
		}
	}

	/**
	 * Generate CSV.
	 *
	 * @return string
	 */
	private function file_name() {
		return sprintf( 'user-csv-%s.csv', date_i18n( 'YmdHis' ) );
	}

	/**
	 * Generate CSV to path.
	 *
	 * @param string $path Path to save.
	 * @return bool|\WP_Error
	 */
	public function to_path( $path ) {
		if ( empty( $path ) || ! is_dir( $path ) || ! is_writable( $path ) ) {
			return new \WP_Error( 'hamail_api_error', __( 'Directory must exist and be writable.', 'hamail' ) );
		}
		$handle = new \SplFileObject( $path, 'w' );
		$fields = hamail_fields_array();
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		$header = array_keys( $fields );
		$handle->fputcsv( $header );
		$paged          = 1;
		$per_page       = 500;
		$csv_user_query = [
			'number' => $per_page,
		];
		$csv_user_query = apply_filters( 'hamail_csv_query', $csv_user_query );
		while ( $paged ) {
			$per_page_query = array_merge( $csv_user_query, [
				'paged' => $paged,
			] );
			$query          = new \WP_User_Query( $per_page_query );
			$users          = $query->get_results();
			if ( count( $users ) < $per_page ) {
				$paged = 0;
				if ( empty( $users ) ) {
					break;
				}
			} else {
				++$paged;
			}
			// Get user data.
			foreach ( $users as $user ) {
				$row = hamail_fields_to_save( $user );
				if ( is_wp_error( $row ) ) {
					return $row;
				}
				$handle->fputcsv( array_values( $row ) );
			}
		}
		return true;
	}

	/**
	 * Generate CSV in background.
	 *
	 * @param string $path
	 */
	public function generate_csv_in_background( $path = '' ) {
		if ( empty( $path ) || ! is_dir( $path ) || ! is_writable( $path ) ) {
			return new \WP_Error( 'hamail_api_error', __( 'Invalid directory: ', 'hamail' ) . $path );
		}
		$path    = trailingslashit( $path ) . $this->file_name();
		$result  = $this->to_path( $path );
		$subject = sprintf( __( '[Hamail] CSV generation result for %s', 'hamail' ), $path );
		if ( is_wp_error( $result ) ) {
			$body = <<<TXT
Failed to generate CSV.

TXT;

			$body .= implode( "\r\n", $result->get_error_messages() );
		} else {
			$body = <<<TXT
CSV is generated successfully.

{$path}
TXT;
		}
		return wp_mail( get_option( 'admin_email' ), $subject, $body ) ?: new \WP_Error( 'hamail_api_error', __( 'Failed to send email.', 'hamail' ) );
	}

	/**
	 * Restrict user data generation.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool
	 */
	public function permission_callback( $request ) {
		return current_user_can( 'list_users' );
	}
}
