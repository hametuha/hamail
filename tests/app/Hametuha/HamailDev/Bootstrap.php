<?php

namespace Hametuha\HamailDev;


use Hametuha\Hamail\Pattern\Singleton;
use Hametuha\Hamail\Pattern\UserGroup;
use Hametuha\HamailDev\Filters\GmailUsers;
use Hametuha\HamailDev\Groups\TagAuthor;
use Hametuha\HamailDev\Stab\WeeklyReport;

/**
 * Class Bootstrap
 *
 * @package Hametuha\HamailDev
 */
class Bootstrap extends Singleton {

	/**
	 * Initialize test object.
	 */
	protected function init() {
		add_filter( 'hamail_user_groups', [ $this, 'user_groups' ] );
		add_filter( 'hamail_generic_user_group', [ $this, 'generic_user_group' ] );
		// Enable rest api.
		TagAuthor::get_instance();
		// Change bulk email filter for debug.
		add_filter( 'hamail_bulk_limit', [ $this, 'bulk_limit' ] );
		// Register stab.
		add_filter( 'hamail_dynamic_emails', function( $classes ) {
			$classes[] = WeeklyReport::class;
			return $classes;
		} );
		// Add filter for fields.
		add_filter( 'hamail_user_field', function( $fields, \WP_User $user ) {
			$fields['hametuha'] = 'hamail';
			$fields['pseudo']   = preg_match( '/(example\.com|@pesuedo)/u', $user->user_email ) ? 'pseudo' : 'valid';
			return $fields;
		}, 10, 2 );
		// Add shortcode.
		add_shortcode( 'hamail-date', function( $atts, $content = '' ) {
			$atts = shortcode_atts( [], $atts, 'hamail-date' );
			return sprintf( '<p>This email #%d created at %s</p>', get_the_ID(), get_the_date() );
		} );
		// Register user filter.
		GmailUsers::get_instance();
	}

	/**
	 * Add user groups.
	 *
	 * @param array $groups Array of class name.
	 * @return UserGroup[];
	 */
	public function user_groups( $groups ) {
		$dir = __DIR__ . '/Groups';
		if ( ! is_dir( $dir ) ) {
			return $groups;
		}
		foreach ( scandir( $dir ) as $file ) {
			if ( ! preg_match( '/^(.*)\.php$/u', $file, $match ) ) {
				continue;
			}
			$class_name = 'Hametuha\\HamailDev\\Groups\\' . $match[1];
			$groups[]   = $class_name::get_instance();
		}
		return $groups;
	}

	/**
	 * Add generic user group.
	 *
	 * @param array $groups
	 * @return array
	 */
	public function generic_user_group( $groups ) {
		$groups[] = [
			'id'       => 'hamail_tag_authors',
			'label'    => 'Tag Author',
			'endpoint' => 'hamail/v1/search/tag-authors',
		];
		return $groups;
	}

	/**
	 * Filter bulk limit for debug.
	 *
	 * @return int
	 */
	public function bulk_limit() {
		if ( defined( 'HAMAIL_BULK_LIMIT' ) && is_numeric( HAMAIL_BULK_LIMIT ) ) {
			return (int) HAMAIL_BULK_LIMIT;
		} else {
			return 3;
		}
	}
}
