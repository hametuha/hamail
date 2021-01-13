<?php

namespace Hametuha\HamailDev\Stab;


use Hametuha\Hamail\Pattern\DynamicEmailCronTemplate;

/**
 * Weekly Report
 *
 * @package hamail
 */
class WeeklyReport extends DynamicEmailCronTemplate {

	protected function get_event_name() {
		return 'hamail_test_weekly_report';
	}

	public function get_description() {
		return 'Send weekly report for all subscriber every saturday.';
	}

	public function get_label() {
		return 'Weekly Report';
	}

	public function get_condition() {
		return 'Every Saturday @ 10:00 AM';
	}

	public function get_key() {
		return 'hamail-test-weekly';
	}

	/**
	 * Get next timestamp in GMT.
	 *
	 * @reurn int
	 */
	protected function get_next() {
		// Get next saturday morning.
		// 10:00 Sat. 01:00 Sat in GMT.
		$target   = 4;
		$at       = 6;
		$timezone = new \DateTimeZone( 'UTC' );
		$now      = new \DateTime( current_time( 'mysql', true ), $timezone );
		$now_hour = (int) $now->format( 'H' );
		$now_day  = (int) $now->format( 'N' );
		if ( $at > $now_hour && ( $now_day === $target ) ) {
			// This is saturday and 01 AM has not been past.
			$now->setTime( $at, 0 );
		} else {
			// Get next saturday.
			// If this is saturday, 1 AM has been past,
			// Diff should 7 days.
			$diff = $target - $now_day;
			if ( 1 > $diff ) {
				$diff += 7;
			}
			$now->add( new \DateInterval( "P{$diff}D" ) );
			$now->setTime( $at, 0 );
		}
		return $now->getTimestamp();
	}

	/**
	 * Execute cron.
	 */
	public function do_cron() {
		$users = $this->get_users();
		if ( ! $users ) {
			$this->log( 'No user found.' );
			return;
		}
		$ranking = implode( "\n", array_map( function( \WP_Post $post ) {
			return sprintf( '<li><a href="%s">%s (%dPV)</a></li>', get_permalink( $post ), get_the_title( $post ), absint( rand( 1, 100 ) * 1000 ) );
		}, get_posts( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 5,
		] ) ) );
		$body = <<<HTML
Dear -name-,

Below is our 

<ol>
{$ranking}
</ol>

Regards,
HTML;
		$user_ids = array_values( array_map( function( $user ) {
			return $user->ID;
		}, $users ) );
		$subject = sprintf( 'Weekly Report: %s', date_i18n( 'Y-m-d' ) );
		$result  = hamail_simple_mail( $user_ids, $subject, $body );
		if ( is_wp_error( $result ) ) {
			$this->log( implode( "\n", array_merge( $result->get_error_codes(), $result->get_error_messages() ) ) );
		} else {
			$this->log( sprintf( 'Success: %s', implode( ', ', $user_ids ) ) );
		}
	}

	/**
	 * Get user list.
	 *
	 * @return \WP_User[]
	 */
	protected function get_users() {
		$query = new \WP_User_Query( [
			'number' => -1,
		] );
		return array_slice( array_values( array_filter( $query->get_results(), function( $user ) {
			return false === strpos( $user->user_email, 'example.com' );
		} ) ), 0, 2 );
	}
}
