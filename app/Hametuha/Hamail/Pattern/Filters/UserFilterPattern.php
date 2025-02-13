<?php

namespace Hametuha\Hamail\Pattern\Filters;

use Hametuha\Hamail\Pattern\Singleton;

/**
 * User Filter pattern.
 *
 * Extend this class to add user filter.
 * @method public \WP_Error|bool validate_callback( $values:array )
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
	 * If help text is required, override this method.
	 *
	 * @return mixed
	 */
	protected function help_text() {
		return '';
	}

	/**
	 * Register filters.
	 *
	 * @return void
	 */
	protected function init() {
		add_filter( 'hamail_user_filters', [ $this, 'add_filter' ] );
		add_filter( 'hamail_user_args', [ $this, 'user_args' ], 10, 4 );
		add_filter( 'hamail_user_filter_validate_callback', [ $this, 'get_validate_callback' ], 10, 2 );
		add_action( 'hamail_user_filter_rendering', [ $this, 'render_filter_interface' ], 10, 2 );
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

	/**
	 * Retrun callback if this filter has validate_callback method.
	 *
	 * @param null|callable $callback
	 * @param string $id
	 * @return null|callable
	 */
	public function get_validate_callback( $callback, $id ) {
		if ( method_exists( $this, 'validate_callback' ) && $id === $this->id() ) {
			$callback = [ $this, 'validate_callback' ];
		}
		return $callback;
	}

	/**
	 * If this is registered, render filter interface.
	 *
	 * @param array{id:string, options:array, type:string} $filter
	 * @param array $values
	 * @return void
	 */
	public function render_filter_interface( $filter, $values ) {
		if ( $filter['id'] === $this->id() ) {
			$this->render( $values, $filter );
		}
	}

	/**
	 * Render filter interface.
	 *
	 * @param array $values
	 * @param array{id:string, options:array, type:string} $filter
	 * @return void
	 */
	protected function render( $values, $filter ) {
		$type = ( 'radio' === $filter['type'] ) ? $filter['type'] : 'checkbox';
		foreach ( $filter['options'] as $value => $label ) {
			printf(
				'<label class="inline-block"><input type="%5$s" name="hamail_user_filters[%1$s][]" value="%2$s" %3$s /> %4$s</label>',
				esc_attr( $filter['id'] ),
				esc_attr( $value ),
				checked( ( ! empty( $values ) && in_array( $value, $values, true ) ), true, false ),
				esc_html( $label ),
				esc_attr( $type )
			);
		}
		$help_text = $this->help_text();
		if ( ! empty( $help_text ) ) {
			printf( '<p class="description">%s</p>', $help_text );
		}
	}
}
