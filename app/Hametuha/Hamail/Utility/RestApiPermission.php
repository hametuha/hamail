<?php

namespace Hametuha\Hamail\Utility;

/**
 * Permission handler for REST API.
 *
 * @package Hametuha\Hamail\Utility
 */
trait RestApiPermission {

	/**
	 * Permission callback.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function preview_permission( $request ) {
		return current_user_can( 'edit_post', $request->get_param( 'post_id' ) );
	}
}
