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
}
