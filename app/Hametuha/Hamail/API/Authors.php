<?php

namespace Hametuha\Hamail\API;


use Hametuha\Hamail\Pattern\RecipientSelector;

/**
 *
 *
 * @package Hametuha\Hamail\API
 */
class Authors extends RecipientSelector {

	
	protected function get_args( $http_method ) {
		// TODO: Implement get_args() method.
	}

	/**
	 * Get field label.
	 *
	 * @return string
	 */
	protected function get_field_label() {
		return __( 'Authors of Posts', 'hamail' );
	}

	protected function route() {
		return 'search/users';
	}
}
