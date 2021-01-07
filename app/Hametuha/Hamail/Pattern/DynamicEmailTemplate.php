<?php

namespace Hametuha\Hamail\Pattern;


/**
 * Dynamic email template.
 *
 * @package hamail
 */
abstract class DynamicEmailTemplate extends Singleton {

	/**
	 * Get list of class names of dynamic emails.
	 *
	 * @return string[]
	 */
	protected function get_option() {
		return (array) get_option( 'hamail_dynamic_emails', [] );
	}

	/**
	 * Constructor.
	 */
	protected function init() {
		if ( $this->is_active() ) {
			$this->init_on_active();
		} else {
			$this->init_on_deactivated();
		}
	}

	/**
	 * Executed if this email is active.
	 *
	 * @return void
	 */
	abstract protected function init_on_active();

	/**
	 * Do something if not active.
	 */
	protected function init_on_deactivated() {
		// Do something if registered.
	}

	/**
	 * Activate dynamic emails.
	 *
	 * @return bool|\WP_Error
	 */
	public function activate() {
		if ( $this->is_active() ) {
			// translators: %s is dynamic email name.
			return new \WP_Error( 'hamail_dynamics_error', sprintf( __( '%s is already active.', 'hamail' ), $this->get_label() ) );
		} else {
			$option   = $this->get_option();
			$option[] = $this->key();
			return update_option( 'hamail_dynamic_emails', $option );
		}
	}

	/**
	 * Deactivate dynamic email.
	 *
	 * @return bool|\WP_Error
	 */
	public function deactivate() {
		if ( $this->is_active() ) {
			$option     = $this->get_option();
			$new_option = [];
			foreach ( $this->get_option() as $key ) {
				if ( $key !== $this->key() ) {
					$new_option[] = $key;
				}
			}
			return update_option( 'hamail_dynamic_emails', $new_option );
		} else {
			// translators: %s is dynamic email name.
			return new \WP_Error( 'hamail_dynamics_error', sprintf( __( '%s is not active.', 'hamail' ), $this->get_label() ) );
		}
	}

	/**
	 * Detect if this email is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return in_array( $this->key(), $this->get_option(), true );
	}

	/**
	 * Get name of email. Should be unique and alphanumeric in kebab case.
	 *
	 * @return string
	 */
	abstract protected function get_key();

	/**
	 * Get label of email.
	 *
	 * @return string
	 */
	abstract public function get_label();

	/**
	 * Description of email.
	 *
	 * @return string
	 */
	abstract public function get_description();

	/**
	 * If this mail is opt in.
	 *
	 * @return bool
	 */
	public function is_opt_in() {
		return true;
	}

	/**
	 * Get email condition.
	 *
	 * @return string
	 */
	abstract public function get_condition();

	/**
	 * Get key.
	 *
	 * @return string
	 */
	public function key() {
		return preg_replace( '/[^a-z\-0-9]/u', '', strtolower( $this->get_key() ) );
	}

	/**
	 * Save log.
	 *
	 * @param string $message
	 */
	public function log( $message ) {
		$handler = apply_filters( 'hamail_dynamic_log_handler', null, $message, $this->key() );
		if ( is_callable( $handler ) ) {
			$handler( $message, $this );
		} else {
			$result = wp_insert_comment( [
				'comment_content' => $message,
				'comment_post_ID' => 0,
				'comment_date'    => current_time( 'mysql' ),
				'comment_type'    => 'hamail-log',
			] );
		}
	}
}
