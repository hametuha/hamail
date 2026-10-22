<?php
/**
 * Test string utilities.
 */

use Hametuha\Hamail\Ui\MarketingTemplate;

class TestStringHelpers extends WP_UnitTestCase {

	/**
	 * Test string replacers.
	 */
	public function test_replacer() {
		// Body.
		$body = <<<TXT
<html>
<body>
This is a body.
</body>
TXT;
		$template = <<<TXT
<html>
<body>
{%body%}
</body>
TXT;
		$body_string = 'This is a body.';
		$this->assertEquals( $body, MarketingTemplate::get_instance()->replace_body( $template, $body_string ) );

		// Subject.
		$subject  = 'Re: This is a subject';
		$template = 'Re: {%subject%}';
		$replace  = 'This is a subject';
		$this->assertEquals( $subject, MarketingTemplate::get_instance()->replace_subject( $template, $replace ) );
	}

	/**
	 * Test email CSV validator.
	 */
	public function test_email_validator() {
		// CSV strings with valid emails
		$valid_emails = 'test1@example.com,test2@example.com,test3@example.com';
		$expected = array( 'test1@example.com', 'test2@example.com', 'test3@example.com' );
		$this->assertEquals( $expected, hamail_validate_email_csv( $valid_emails ) );

		// CSV with invalid strings.
		$mixed_emails = 'test1@example.com,invalid-email,test3@example.com';
		$expected = array( 'test1@example.com', 'test3@example.com' );
		$this->assertEquals( $expected, hamail_validate_email_csv( $mixed_emails ) );

		// CSV with duplicate emails
		$duplicate_emails = 'test1@example.com,test2@example.com,test1@example.com';
		$expected = array( 'test1@example.com', 'test2@example.com' );
		$this->assertEquals( $expected, hamail_validate_email_csv( $duplicate_emails ) );

		// CSV with spaces
		$emails_with_spaces = ' test1@example.com , test2@example.com ';
		$expected = array( 'test1@example.com', 'test2@example.com' );
		$this->assertEquals( $expected, hamail_validate_email_csv( $emails_with_spaces ) );

		// CSV with empty strings
		$empty_string = '';
		$this->assertEquals( array(), hamail_validate_email_csv( $empty_string ) );
	}
}
