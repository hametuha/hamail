<?php

namespace Hametuha\Hamail\API\Helper;


use Hametuha\Hamail\Pattern\Singleton;

/**
 * User filter.
 */
class UserFilter extends Singleton {

	/**
	 * {@inheritDoc}
	 */
	protected function init() {
		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
	}

	/**
	 * Register REST API.
	 *
	 * @return void
	 */
	public function rest_api_init() {
		$args = [
			'roles' => [
				'required' => false,
				'default'  => [],
				'type'     => 'array',
			],
		];
		foreach ( $this->filters() as $filter ) {
			$validate_callback = apply_filters( 'hamail_user_filter_validate_callback', null, $filter['id'] );
			if ( ! is_callable( $validate_callback ) ) {
				$validate_callback = function ( $values ) use ( $filter ) {
					$enums = array_keys( $filter['options'] );
					foreach ( $values as $filter_value ) {
						if ( ! in_array( $filter_value, $enums, true ) ) {
							// translators: %1$s is filter value, %2$s is filter label.
							return new \WP_Error( 'hamail_invalid_filter', sprintf( __( 'Invalid filter %1$s for %2$s: ', '%s' ), $filter_value, $filter['label'] ) );
						}
					}
					return true;
				};
			}
			$args[ $filter['id'] ] = [
				'required'          => false,
				'default'           => [],
				'type'              => 'array',
				'validate_callback' => $validate_callback,
			];
		}
		register_rest_route( 'hamail/v1', '/users/filter', [
			'methods'             => 'POST',
			'args'                => $args,
			'callback'            => [ $this, 'filter_users' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );
	}

	/**
	 * Get user count.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function filter_users( $request ) {
		$filters = [];
		foreach ( $this->filters() as $filter ) {
			$values = $request->get_param( $filter['id'] );
			if ( ! empty( $values ) ) {
				$filters[ $filter['id'] ] = $values;
			}
		}
		$roles = $request->get_param( 'roles' );
		$query = $this->user_query( [
			'number'      => 1,
			'count_total' => true,
		], $roles, $filters );
		return new \WP_REST_Response( [
			'total' => $query ? $query->get_total() : 0,
		] );
	}

	/**
	 * Permission callback
	 *
	 * @return bool
	 */
	public function permission_callback() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * User filters.
	 *
	 * @return array<array{id:string, label:string,options:array<string,string>}, type:string>
	 */
	public function filters() {
		$filters = (array) apply_filters( 'hamail_user_filters', [] );
		$filters = array_map( function ( $filter ) {
			$filter = (array) $filter;
			return wp_parse_args( $filter, [
				'id'      => '',
				'label'   => '',
				'options' => [],
				'type'    => 'checkbox',
			] );
		}, $filters );
		return array_filter( $filters, [ $this, 'validate_filter' ] );
	}

	/**
	 * Get filter for the post.
	 *
	 * @param null|int|\WP_Post $post
	 * @return array<array{string, string[]}>
	 */
	public function get_filter( $post = null ) {
		$post = get_post( $post );
		return (array) get_post_meta( $post->ID, '_hamail_user_filter', true );
	}

	/**
	 * Validate filter.
	 *
	 * @param array{id:string, label:string,options:array<string,string>} $filter Filter object.
	 *
	 * @return bool
	 */
	protected function validate_filter( $filter ) {
		try {
			// todo: Support text input in future.
			foreach ( [ 'id', 'label', 'options' ] as $key ) {
				if ( empty( $filter[ $key ] ) ) {
					throw new \Exception( sprintf( '%s is required.', $key ) );
				}
				return true;
			}
		} catch ( \Exception $e ) {
			trigger_error( $e->getMessage(), E_USER_WARNING );
			return false;
		}
	}

	/**
	 * Query arguments.
	 *
	 * @param array                   $args    Query args.
	 * @param string[]                $roles   Roles.
	 * @param array<string, string[]> $filters Filters.
	 * @return \WP_User_Query|null
	 */
	public function user_query( $args = [], $roles = [], $filters = [] ) {
		$new_args = [];
		if ( ! empty( $roles ) ) {
			$new_args['role__in'] = $roles;
		}
		foreach ( $filters as $key => $values ) {
			$new_args = apply_filters( 'hamail_user_args', $new_args, $key, $values, $args );
		}
		if ( empty( $new_args ) ) {
			// No condition is set.
			return null;
		}
		return new \WP_User_Query( array_merge( $args, $new_args ) );
	}
}
