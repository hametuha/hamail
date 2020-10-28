<?php

namespace Hametuha\Hamail\Pattern;

/**
 * Extract recipients
 *
 * @package hamail
 * @since 2.2.0
 */
abstract class RecipientSelector extends AbstractRest {

	protected $slug = '';

	protected function init() {
		parent::init();
		add_action( 'hamail_recipients_selector', [ $this, 'hamail_recipients_selector' ] );
	}

	/**
	 * Get field label.
	 *
	 * @return string
	 */
	abstract protected function get_field_label();
}
