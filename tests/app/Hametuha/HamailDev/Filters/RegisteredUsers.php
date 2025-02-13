<?php

namespace Hametuha\HamailDev\Filters;


use Hametuha\Hamail\Pattern\Filters\UserFilterInputPattern;

/**
 * Filter users with registered date.
 *
 * @package hamail
 */
class RegisteredUsers extends UserFilterInputPattern {

	protected function type() {
		return 'date';
	}

	public function id(): string {
		return 'registered-date';
	}

	public function description(): string {
		return 'Registered Since';
	}

	protected function help_text() {
		return 'Filter users registered since specified date.';
	}

	protected function convert_users( $args, $values = [], $original_args = [] ) {
		if ( ! empty( $values ) ) {
			foreach ( $values as $value ) {
				if ( empty( $value ) ) {
					continue;
				}
				if ( ! isset( $args['date_query'] ) ) {
					$args['date_query'] = [];
				}
				$args['date_query'][] = [
					'column'    => 'user_registered',
					'after'     => $value,
					'inclusive' => true,
				];
			}
		}
		return $args;
	}

	public function validate_callback( $values, \WP_REST_Request $request ) {
		$error = false;
		foreach ( $values as $value ) {
			if ( '' === $value || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
				continue;
			}
			$error = true;
		}
		return ! $error;
	}
}
