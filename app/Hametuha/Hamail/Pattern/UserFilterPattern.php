<?php

namespace Hametuha\Hamail\Pattern;

/**
 * User Filter pattern.
 *
 * Extend this class to add user filter.
 */
abstract class UserFilterPattern extends Singleton {

	/**
	 * Get type of filters
	 *
	 * @return string Checkbox or radio.
	 */
	protected function type() {
		return 'checkbox';
	}

	/**
	 * Id of this filter should be alpha-numeric and hyphens.
	 *
	 * @return string
	 */
	abstract public function id(): string;

	/**
	 * Label of this filter.
	 *
	 * @return string
	 */
	abstract public function description(): string;

	/**
	 * Key value pairs of options.
	 *
	 * @return array<string,string>
	 */
	abstract public function options(): array;

	/**
	 * Register filters.
	 *
	 * @return void
	 */
	protected function init() {
		add_filter( 'hamail_user_filters', [ $this, 'add_filter' ] );
		add_filter( 'hamail_user_args', [ $this, 'user_args' ], 10, 4 );
	}

	/**
	 * Add user filter.
	 *
	 * @param array $filters
	 * @return array
	 */
	public function add_filter( $filters ) {
		$filters[] = [
			'id'      => $this->id(),
			'label'   => $this->description(),
			'options' => $this->options(),
			'type'    => $this->type(),
		];
		return $filters;
	}

	/**
	 * If this filter is registered, trigger filter.
	 *
	 * @param array   $args          Arguments to WP_User_Query.
	 * @param string  $key           Key of filter.
	 * @param string[] $values       Values of filter.
	 * $param array   $original_args Original arguments.
	 * @return array
	 */
	public function user_args( $args, $key, $values = [], $original_args = [] ) {
		if ( $key !== $this->id() || empty( $values ) ) {
			return $args;
		}
		return $this->convert_users( $args, $values, $original_args );
	}

	/**
	 * Filter user query.
	 *
	 * @param array    $args          User query args.
	 * @param string[] $values        Values of filter.
	 * @param array    $original_args Original arguments.
	 * @return array
	 */
	abstract protected function convert_users( $args, $values = [], $original_args = [] );
}
