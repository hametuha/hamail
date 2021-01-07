<?php

namespace Hametuha\Hamail\Pattern;


/**
 * List table pattern.
 *
 * @package hamail
 */
abstract class ListTablePattern extends Singleton {

	/**
	 * Constructor
	 */
	protected function init() {
		add_action( 'admin_init', [ $this, 'admin_init' ] );
	}

	/**
	 * Register hooks.
	 */
	public function admin_init() {
		foreach ( $this->filtered_post_types() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", [ $this, 'add_columns' ] );
			add_action( "manage_{$post_type}_posts_custom_column", [ $this, 'render_columns' ], 10, 2 );
		}
	}

	/**
	 * Add columns for post type.
	 *
	 * @param string[] $columns
	 * @return string[]
	 */
	abstract public function add_columns( $columns );

	/**
	 * Render posts list.
	 *
	 * @param string $column
	 * @param int    $post_id
	 * @return void
	 */
	abstract public function render_columns( $column, $post_id );

	/**
	 * Ensure post type list.
	 *
	 * @return string[]
	 */
	protected function filtered_post_types() {
		return array_filter( (array) $this->post_types(), function( $post_type ) {
			return is_string( $post_type ) && post_type_exists( $post_type );
		} );
	}

	/**
	 * Post type this columns for.
	 *
	 * @return string[]
	 */
	abstract protected function post_types();
}
