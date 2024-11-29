<?php

namespace Hametuha\HamailDev\Filters;


use Hametuha\Hamail\Pattern\UserFilterPattern;

/**
 * Gmail users.
 *
 * @package hamail
 */
class GmailUsers extends UserFilterPattern {

	protected function type() {
		return 'radio';
	}

	public function description(): string {
		return 'Mail Hosting';
	}

	public function id(): string {
		return 'gmail';
	}

	public function options(): array {
		return [
			''        => 'Do not filter',
			'gmail'   => 'Gmail',
			'yahoo'   => 'Yahoo',
			'hotmail' => 'Hotmail',
			'example' => 'Example',
		];
	}

	protected function convert_users( $args, $values = [], $original_args = [] ) {
		foreach ( $values as $value ) {
			if ( $value ) {
				$args[ 'search_columns' ] = [ 'user_email' ];
				foreach ( $values as $value ) {
					$args[ 'search' ] = '*@' . $value . '*';
				}
			}
		}
		return $args;
	}
}
