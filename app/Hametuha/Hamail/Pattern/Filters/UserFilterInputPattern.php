<?php

namespace Hametuha\Hamail\Pattern\Filters;


/**
 * User filter with input pattern.
 *
 */
abstract class UserFilterInputPattern extends UserFilterPattern implements UserFilterWithValidatorInterface {

	protected function type() {
		return 'text';
	}

	/**
	 * Placeholder text.
	 *
	 * @return string
	 */
	protected function placeholder() {
		return '';
	}

	/**
	 * Always empty.
	 *
	 * @return array|string[]
	 */
	public function options(): array {
		return [];
	}

	protected function render( $values, $filter ) {
		switch ( $filter['type'] ) {
			case 'url':
			case 'text':
			case 'email':
			case 'password':
			case 'date':
				$type = $filter['type'];
				break;
			default:
				$type = 'text';
				break;
		}
		$value = empty( $values ) ? '' : $values[0];
		printf(
			'<label class="block"><input type="%4$s" name="hamail_user_filters[%1$s][]" class="regular-text" value="%2$s" placeholder="%3$s" /></label>',
			esc_attr( $filter['id'] ),
			esc_attr( $value ),
			esc_attr( $this->placeholder() ),
			esc_attr( $type )
		);
		$help_text = $this->help_text();
		if ( ! empty( $help_text ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html( $help_text )
			);
		}
	}
}
