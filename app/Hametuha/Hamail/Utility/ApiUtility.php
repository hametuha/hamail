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
	 * If response is error, convert it to WP_Error
	 *
	 * @param Response $response
	 * @return \WP_Error|array
	 */
	protected function convert_response_to_error( $response ) {
		$status = $response->statusCode();
		$body = json_decode( $response->body(), true );
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
				$message = implode( " / ", $messages );
			}
			return new \WP_Error( 'hamail_sendgrid_api_error', $message,  [
				'status' => $status,
			] );
		}
	}
}
