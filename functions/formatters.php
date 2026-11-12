<?php
/**
 * Formatting functions
 */

/**
 * Validate email list in CSV format.
 *
 * @param string $emails Comma-separated email addresses.
 * @return string[] List of valid email addresses.
 */
function hamail_validate_email_csv( $emails ) {
	return array_values( array_unique( array_filter( array_map( 'trim', explode( ',', $emails ) ), function ( $email ) {
		return is_email( $email );
	} ) ) );
}
