<?php

class WPSC_Email_TestCase extends WP_UnitTestCase {

	public static function setUpBeforeClass() {
		// Set the store config we need for these tests
		add_option( 'return_email', 'store@example.com' );
		add_option( 'return_name', 'Test store' );
	}

	public function test_create_object() {
		$email = new WPSC_Email();
		$this->assertInstanceOf( 'WPSC_Email', $email );
	}

	/**
	 * Test the various forms of adding CC information.
	 */
	public function test_add_cc() {

		$email = new WPSC_Email();

		// Basic single email addition.
		$ccs = $email->add_cc( 'joe@example.com' );
		$this->assertCount( 1, $email->cc );
		$this->assertCount( 1, $ccs );
		$this->assertEquals( $ccs, $email->cc );

		// Check duplicates aren't added.
		$ccs = $email->add_cc( 'joe@example.com' );
		$this->assertCount( 1, $email->cc );
		$this->assertCount( 1, $ccs );
		$this->assertEquals( $ccs, $email->cc );

		// Check that invalid emails aren't added.
		$ccs = $email->add_cc( 'joe@' );
		$this->assertCount( 1, $email->cc );
		$this->assertFalse( $ccs );

		// Check non-duplicate send emails are added correctly.
		$ccs = $email->add_cc( 'bob@example.com' );
		$this->assertCount( 2, $email->cc );
		$this->assertCount( 2, $ccs );
		$this->assertEquals( $ccs, $email->cc );

		// Check adding multiple emails
		$ccs = $email->add_cc( array( 'claire@example.com', 'maisie@example.com' ) );
		$this->assertCount( 4, $email->cc );
		$this->assertCount( 4, $ccs );
		$this->assertEquals( $ccs, $email->cc );

		// Check adding by array an invalid address (Shouldn't be added)
		$ccs = $email->add_cc( array( 'claire' ) );
		$this->assertFalse( $ccs );
		$this->assertCount( 4, $email->cc );

		// Check adding an array where some are valid, and some are invalid (Shouldn't be added).
		$ccs = $email->add_cc( array( 'travis@example.com', 'claire' ) );
		$this->assertFalse( $ccs );
		$this->assertCount( 4, $email->cc );
	}

	/**
	 * Same as test_add_cc, but for BCC.
	 */
	public function test_add_bcc() {

		$email = new WPSC_Email();

		$bccs = $email->add_bcc( 'joe@example.com' );
		$this->assertCount( 1, $email->bcc );
		$this->assertCount( 1, $bccs );
		$this->assertEquals( $bccs, $email->bcc );

		$bccs = $email->add_bcc( 'joe@example.com' );
		$this->assertCount( 1, $email->bcc );
		$this->assertCount( 1, $bccs );
		$this->assertEquals( $bccs, $email->bcc );

		$bccs = $email->add_bcc( 'joe@' );
		$this->assertCount( 1, $email->bcc );
		$this->assertFalse( $bccs );

		$bccs = $email->add_bcc( 'bob@example.com' );
		$this->assertCount( 2, $email->bcc );
		$this->assertCount( 2, $bccs );
		$this->assertEquals( $bccs, $email->bcc );

		$bccs = $email->add_bcc( array( 'claire@example.com', 'maisie@example.com' ) );
		$this->assertCount( 4, $email->bcc );
		$this->assertCount( 4, $bccs );
		$this->assertEquals( $bccs, $email->bcc );

		$bccs = $email->add_bcc( array( 'claire' ) );
		$this->assertFalse( $bccs );
		$this->assertCount( 4, $email->bcc );

		$bccs = $email->add_bcc( array( 'travis@example.com', 'claire' ) );
		$this->assertFalse( $bccs );
		$this->assertCount( 4, $email->bcc );
	}

	/**
	 * Same as test_add_cc, but for "to" addresses.
	 */
	public function test_add_to() {

		$email = new WPSC_Email();

		$tos = $email->add_to( 'joe@example.com' );
		$this->assertCount( 1, $email->to );
		$this->assertCount( 1, $tos );
		$this->assertEquals( $tos, $email->to );

		$tos = $email->add_to( 'joe@example.com' );
		$this->assertCount( 1, $email->to );
		$this->assertCount( 1, $tos );
		$this->assertEquals( $tos, $email->to );

		$tos = $email->add_to( 'joe@' );
		$this->assertCount( 1, $email->to );
		$this->assertFalse( $tos );

		$tos = $email->add_to( 'bob@example.com' );
		$this->assertCount( 2, $email->to );
		$this->assertCount( 2, $tos );
		$this->assertEquals( $tos, $email->to );

		$tos = $email->add_to( array( 'claire@example.com', 'maisie@example.com' ) );
		$this->assertCount( 4, $email->to );
		$this->assertCount( 4, $tos );
		$this->assertEquals( $tos, $email->to );

		$tos = $email->add_to( array( 'claire' ) );
		$this->assertFalse( $tos );
		$this->assertCount( 4, $email->to );

		$tos = $email->add_to( array( 'travis@example.com', 'claire' ) );
		$this->assertFalse( $tos );
		$this->assertCount( 4, $email->to );
	}

	public function test_prepare() {
		$email = new WPSC_Email();
		// @TODO - flesh this out
	}

	/**
	 * Test that emails can't be sent without a valid "to" address.
	 */
	public function test_can_send_email_validation() {

		$email = new WPSC_Email();
		$email->plain_content = 'This is a test, please ignore.';

		// Send with no to address specified.
		$result = $email->send();
		$this->assertFalse( $result );
		$this->assertNull( $email->sent );

		// Add an invalid email, and try to send.
		$email->add_to( 'fred' );
		$result = $email->send();
		$this->assertFalse( $result );
		$this->assertNull( $email->sent );

		// Add a valid eail, should now send.
		$email->add_to( 'joe@example.com' );
		$result = $email->send();
		$this->assertTrue( $result );
		$this->assertTrue( $email->sent );
	}

	/**
	 * Test that emails can't be sent without any content.
	 */
	public function test_can_send_content_validation() {

		$email = new WPSC_Email();
		$email->add_to( 'joe@example.com' );

		// Send with no content - should fail.
		$result = $email->send();
		$this->assertFalse( $result );
		$this->assertNull( $email->sent );

		$email->plain_content = 'This is a test, please ignore.';
		$result = $email->send();
		$this->assertTrue( $result );
		$this->assertTrue( $email->sent );

	}

	/**
	 * Test that subject line is autogenerated correctly if not provided.
	 */
	public function test_subject_generation() {

		$email = new WPSC_Email();
		$email->html_content = '<html><body>This is a test.</body></html>';
		$email->add_to( 'joe@example.com' );
		$result = $email->send();
		$this->assertEquals( 'Mail from Test Blog', $email->subject );

	}

	/**
	 * @TODO - test that the from address is defaulted correctly.
	 * @return [type] [description]
	 */
	public function test_default_from_address() {

		$email = new WPSC_Email();
		$email->html_content = '<html><body>This is a test.</body></html>';
		$email->add_to( 'joe@example.com' );
		$result = $email->send();
		$this->assertTrue( $result );
		$this->assertTrue( $email->sent );
		$this->assertContains( 'From: "Test store" <store@example.com>', $email->headers );

	}

	/**
	 * @TODO Test that the content type gets set correctly.
	 */
	public function test_content_type_generation() {

	}

	/**
	 * Test that the plain text content gets generated correctly.
	 */
	public function test_auto_plain_text_generation() {

		$email = new WPSC_Email();
		$email->html_content = '<html><body>This is a test.</body></html>';
		$email->add_to( 'joe@example.com' );

		$result = $email->send();
		$this->assertTrue( $result );
		$this->assertTrue( $email->sent );
		$this->assertNotEmpty( $email->plain_content );
		$this->assertEquals( 'This is a test.', $email->plain_content );

		// @TODO - better tests on more complex content.
	}
}

