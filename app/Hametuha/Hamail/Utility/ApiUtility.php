<?php

namespace Hametuha\Hamail\Utility;

use SendGrid\Response;

/**
 * SendGrid API utility.
 *
 * @package hamail
 */
trait ApiUtility {

	/**
	 * Get list of custom fields.
	 *
	 * @param array $args     Arguments. [ 'reserved' => true, 'custom' => true, ].
	 * @param bool  $wp_error If true, return WP_Error on failure.
	 * @return array|\WP_Error
	 */
	protected function get_custom_fields( $args = [], $wp_error = false ) {
		$targets       = wp_parse_args( $args, [
			'reserved' => true,
			'custom'   => true,
		] );
		$custom_fields = [];
		$errors        = new \WP_Error();
		$sg            = hamail_client();
		foreach ( $targets as $key => $include ) {
			if ( ! $include ) {
				continue;
			}
			switch ( $key ) {
				case 'custom':
					$response = $sg->client->contactdb()->custom_fields()->get();
					$result   = $this->convert_response_to_error( $response );
					if ( is_wp_error( $result ) ) {
						$errors->add( $result->get_error_code(), $result->get_error_message() );
					} else {
						foreach ( $result['custom_fields'] as $field ) {
							$custom_fields[] = [
								'id'       => $field['id'],
								'name'     => $field['name'],
								'type'     => $field['type'],
								'reserved' => false,
							];
						}
					}
					break;
				case 'reserved':
					$response = $sg->client->contactdb()->reserved_fields()->get();
					$result   = $this->convert_response_to_error( $response );
					if ( is_wp_error( $result ) ) {
						$errors->add( $result->get_error_code(), $result->get_error_message() );
					} else {
						foreach ( $result['reserved_fields'] as $field ) {
							$custom_fields[] = [
								'id'       => 0,
								'name'     => $field['name'],
								'type'     => $field['type'],
								'reserved' => true,
							];
						}
					}
					break;
			}
		}
		if ( $wp_error ) {
			return $errors->get_error_messages() ? $errors : $custom_fields;
		} else {
			return $custom_fields;
		}
	}

	/**
	 * Get list of unsubscibe group.
	 *
	 * @param bool $wp_error If set to true, returns WP_Error.
	 * @return array|\WP_Error
	 */
	public function get_unsubscribe_group( $wp_error = false ) {
		$errors   = new \WP_Error();
		$client   = hamail_client();
		$response = $client->client->asm()->groups()->get();
		$result   = $this->convert_response_to_error( $response );
		if ( is_wp_error( $result ) ) {
			return $wp_error ? $result : [];
		} else {
			return $result;
		}
	}

	/**
	 * If response is error, convert it to WP_Error
	 *
	 * @param Response $response
	 * @return \WP_Error|array
	 */
	protected function convert_response_to_error( $response ) {
		$status = $response->statusCode();
		$body   = json_decode( $response->body(), true );
		if ( 200 <= $status && 300 > $status ) {
			// Success.
			return $body;
		} else {
			$message = __( 'Sendgrid API returns error. Please try again.', 'namail' );
			if ( ! empty( $body['errors'] ) ) {
				$messages = [];
				foreach ( $body['errors'] as $error ) {
					$messages[] = $error['message'];
				}
				$message = implode( ' / ', $messages );
			}
			return new \WP_Error( 'hamail_sendgrid_api_error', $message, [
				'status' => $status,
			] );
		}
	}

	/**
	 * Log error.
	 *
	 * @param string $message Error message.
	 */
	protected function log( $message ) {
		$messaage = sprintf(
			'[HAMAIL %s] %s %s',
			date_i18n( \DateTIme::ATOM ),
			$_SERVER['REQUEST_URI'] ?? 'URL_UNKNOWN',
			$message
		);
		if ( WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( $message );
		}
	}
}
