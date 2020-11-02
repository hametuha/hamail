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
	 * SearchResultItem constructor.
	 *
	 * @param string $id
	 * @param string $label
	 */
	public function __construct( $id, $label ) {
		$this->id    = $id;
		$this->label = $label;
	}

	/**
	 * Convert data.
	 */
	public function convert() {
		return [
			'id'    => $this->id,
			'label' => $this->label,
		];
	}
}
