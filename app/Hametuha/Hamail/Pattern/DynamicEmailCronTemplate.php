<?php

namespace Hametuha\Hamail\Pattern;

/**
 * Abstract template for dynamic email with cron.
 *
 * @package hamail
 */
abstract class DynamicEmailCronTemplate extends DynamicEmailTemplate {

	/**
	 * Event name.
	 *
	 * @return string
	 */
	abstract protected function get_event_name();

	/**
	 * Should return timestamp in GMT fot next schedule.
	 *
	 * @return int
	 */
	abstract protected function get_next();

	/**
	 * Event recurrence.
	 *
	 * @return string
	 */
	protected function recurrence() {
		return 'weekly';
	}

	/**
	 * Register cron if activated.
	 */
	protected function init_on_active() {
		$timestamp = $this->get_next();
		// Register schedules.
		if ( ! wp_next_scheduled( $this->get_event_name() ) ) {
			wp_schedule_event( $this->get_next(), $this->recurrence(), $this->get_event_name() );
		}
		// Add acton action.
		add_action( $this->get_event_name(), [ $this, 'do_cron' ] );
	}


	/**
	 * Remove cron event if exists.
	 */
	protected function init_on_deactivated() {
		$timestamp = wp_next_scheduled( $this->get_event_name() );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $this->get_event_name() );
		}
	}

	/**
	 * Executed on action hook.
	 *
	 * @return void
	 */
	abstract public function do_cron();
}
