<?php
/**
 * Test recipients data processing.
 */

/**
 * Test recipients data.
 */
class TestRecipientsData extends WP_UnitTestCase {

	/**
	 * Test flat array detection.
	 */
	public function test_flat_array_detection() {
		// Test with flat email array
		$flat_emails = [ 'test1@example.com', 'test2@example.com', 'test3@example.com' ];
		$result = hamail_get_recipients_data( $flat_emails );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );

		// Check that each recipient has correct structure
		foreach ( $result as $recipient ) {
			$this->assertArrayHasKey( 'id', $recipient );
			$this->assertArrayHasKey( 'email', $recipient );
			$this->assertArrayHasKey( 'name', $recipient );
			$this->assertArrayHasKey( 'substitutions', $recipient );
			$this->assertArrayHasKey( 'custom_args', $recipient );
		}

		// Test with associative array (should not be converted)
		$assoc_array = [
			'test1@example.com' => [ 'custom' => 'value1' ],
			'test2@example.com' => [ 'custom' => 'value2' ]
		];
		$result = hamail_get_recipients_data( $assoc_array );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
	}

	/**
	 * Test recipients data conversion with duplicates.
	 */
	public function test_recipients_data_with_duplicates() {
		// Test with duplicate emails
		$emails_with_duplicates = [
			'test1@example.com',
			'test2@example.com',
			'test1@example.com', // duplicate
			'test3@example.com',
			'test2@example.com'  // duplicate
		];

		$result = hamail_get_recipients_data( $emails_with_duplicates );

		// Should have 3 unique entries (duplicates are automatically removed)
		$this->assertCount( 3, $result );

		// Check that all entries have valid email addresses
		foreach ( $result as $recipient ) {
			$this->assertNotFalse( is_email( $recipient['email'] ) );
		}
	}

	/**
	 * Test array_unique with sequential keys.
	 */
	public function test_array_unique_sequential() {
		// Simulate the array_unique scenario from hamail_get_message_recipients
		$recipients = [
			0 => 'test1@example.com',
			1 => 'test2@example.com',
			2 => 'test2@example.com', // duplicate
			3 => 'test3@example.com',
			4 => 'test1@example.com'  // duplicate
		];

		// Apply array_unique (preserves keys)
		$unique_with_gaps = array_unique( $recipients );

		// Keys should be non-sequential: [0, 1, 3]
		$this->assertEquals( [ 0, 1, 3 ], array_keys( $unique_with_gaps ) );

		// Apply array_values to make sequential
		$sequential = array_values( $unique_with_gaps );

		// Keys should now be sequential: [0, 1, 2]
		$this->assertEquals( [ 0, 1, 2 ], array_keys( $sequential ) );
		$this->assertEquals( range( 0, count( $sequential ) - 1 ), array_keys( $sequential ) );
	}

	/**
	 * Test substitutions generation.
	 */
	public function test_substitutions_generation() {
		$emails = [ 'test@example.com' ];
		$result = hamail_get_recipients_data( $emails );

		$this->assertCount( 1, $result );

		$recipient = $result[0];
		$substitutions = $recipient['substitutions'];

		// Check required substitution keys
		$this->assertArrayHasKey( '-id-', $substitutions );
		$this->assertArrayHasKey( '-name-', $substitutions );
		$this->assertArrayHasKey( '-email-', $substitutions );
		$this->assertArrayHasKey( '-login-', $substitutions );
		$this->assertArrayHasKey( '-first_name-', $substitutions );
		$this->assertArrayHasKey( '-last_name-', $substitutions );

		// Check values
		$this->assertEquals( 0, $substitutions['-id-'] );
		$this->assertEquals( 'test@example.com', $substitutions['-email-'] );
		$this->assertEquals( 'V/A', $substitutions['-login-'] );
	}

	/**
	 * Test mixed recipients (emails and user IDs).
	 */
	public function test_mixed_recipients() {
		// Create a test user
		$user_id = $this->factory->user->create( [
			'user_email' => 'testuser@example.com',
			'display_name' => 'Test User'
		] );

		$mixed_recipients = [
			'guest@example.com',  // email
			$user_id,             // user ID
			'another@example.com' // email
		];

		$result = hamail_get_recipients_data( $mixed_recipients );

		$this->assertCount( 3, $result );

		// Check guest email
		$guest = $result[0];
		$this->assertEquals( 0, $guest['id'] );
		$this->assertEquals( 'guest@example.com', $guest['email'] );

		// Check registered user
		$user = $result[1];
		$this->assertEquals( $user_id, $user['id'] );
		$this->assertEquals( 'testuser@example.com', $user['email'] );
		$this->assertEquals( 'Test User', $user['name'] );

		// Check another guest email
		$another = $result[2];
		$this->assertEquals( 0, $another['id'] );
		$this->assertEquals( 'another@example.com', $another['email'] );
	}

	/**
	 * Test associative array with extra data.
	 */
	public function test_associative_array_with_extra_data() {
		$recipients = [
			'test1@example.com' => [ 'custom_field' => 'value1', 'another' => 'data1' ],
			'test2@example.com' => [ 'custom_field' => 'value2' ]
		];

		$result = hamail_get_recipients_data( $recipients );

		$this->assertCount( 2, $result );

		// Check that extra data is included in substitutions
		$first = $result[0];
		$this->assertArrayHasKey( '-custom_field-', $first['substitutions'] );
		$this->assertEquals( 'value1', $first['substitutions']['-custom_field-'] );
		$this->assertArrayHasKey( '-another-', $first['substitutions'] );
		$this->assertEquals( 'data1', $first['substitutions']['-another-'] );

		$second = $result[1];
		$this->assertArrayHasKey( '-custom_field-', $second['substitutions'] );
		$this->assertEquals( 'value2', $second['substitutions']['-custom_field-'] );
	}

	/**
	 * Test empty and invalid inputs.
	 */
	public function test_empty_and_invalid_inputs() {
		// Empty array
		$result = hamail_get_recipients_data( [] );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );

		// Invalid email addresses
		$invalid_emails = [ 'not-an-email', 'also@not@valid', '' ];
		$result = hamail_get_recipients_data( $invalid_emails );

		// Should filter out invalid emails
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );

		// Mix of valid and invalid
		$mixed = [ 'valid@example.com', 'invalid-email', 'another@example.com' ];
		$result = hamail_get_recipients_data( $mixed );

		$this->assertCount( 2, $result );
		$this->assertEquals( 'valid@example.com', $result[0]['email'] );
		$this->assertEquals( 'another@example.com', $result[1]['email'] );
	}

	/**
	 * Test the bug fix: ensure no wrong email in substitutions.
	 */
	public function test_bug_fix_no_wrong_email_in_substitutions() {
		// Simulate the original bug scenario
		$emails = [
			'first@example.com',
			'second@example.com',
			'third@example.com'
		];

		$result = hamail_get_recipients_data( $emails );

		$this->assertCount( 3, $result );

		// Check that each recipient has correct email in substitutions
		foreach ( $result as $index => $recipient ) {
			$expected_email = $emails[$index];
			$this->assertEquals( $expected_email, $recipient['email'] );
			$this->assertEquals( $expected_email, $recipient['substitutions']['-email-'] );

			// Ensure no wrong email appears in other substitution keys
			foreach ( $recipient['substitutions'] as $key => $value ) {
				if ( $key !== '-email-' && is_string( $value ) && is_email( $value ) ) {
					// If there's an email in substitutions, it should be the correct one
					$this->assertEquals( $expected_email, $value, "Wrong email found in substitution key: {$key}" );
				}
			}
		}
	}
}
