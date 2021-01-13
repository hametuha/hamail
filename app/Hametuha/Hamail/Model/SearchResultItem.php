<?php

namespace Hametuha\Hamail\Model;

/**
 * Model of search results.
 *
 * @package hamail
 */
class SearchResultItem {

	/**
	 * @var string ID of item.
	 */
	protected $id = '';

	/**
	 * @var string Label of item.
	 */
	protected $label = '';

	/**
	 * @var string Type of data.
	 */
	protected $type = 'user';

	/**
	 * SearchResultItem constructor.
	 *
	 * @param string $id
	 * @param string $label
	 * @param string $type
	 */
	public function __construct( $id, $label, $type = 'user' ) {
		$this->id    = $id;
		$this->label = $label;
		$this->type  = $type;
	}

	/**
	 * Convert data.
	 */
	public function convert() {
		return [
			'id'    => $this->id,
			'label' => $this->label,
			'type'  => $this->type,
		];
	}
}
