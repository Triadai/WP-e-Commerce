<?php

class WPSC_Email_TestCase extends WP_UnitTestCase {

	private $email;
	private $destination_email = 'joe@example.com';

	public static function setUpBeforeClass() {
		// Set the store config we need for these tests
		add_option( 'return_email', 'store@example.com' );
		add_option( 'return_name', 'Test store' );
	}

	public function setUp() {
		$this->email = new WPSC_Email();
	}

	public function tearDown() {

		global $phpmailer;

		unset( $phpmailer->mock_sent );
		unset( $this->email );
	}

	public function test_create_object() {
		$this->assertInstanceOf( 'WPSC_Email', $this->email );
	}

	/**
	 * Test the various forms of adding CC information.
	 */
	public function test_add_cc() {

		// Basic single email addition.
		$ccs = $this->email->add_cc( $this->destination_email );
		$this->assertCount( 1, $this->email->cc );
		$this->assertCount( 1, $ccs );
		$this->assertEquals( $ccs, $this->email->cc );

		// Check duplicates aren't added.
		$ccs = $this->email->add_cc( $this->destination_email );
		$this->assertCount( 1, $this->email->cc );
		$this->assertCount( 1, $ccs );
		$this->assertEquals( $ccs, $this->email->cc );

		// Check that invalid emails aren't added.
		$ccs = $this->email->add_cc( 'joe@' );
		$this->assertCount( 1, $this->email->cc );
		$this->assertFalse( $ccs );

		// Check non-duplicate send emails are added correctly.
		$ccs = $this->email->add_cc( 'bob@example.com' );
		$this->assertCount( 2, $this->email->cc );
		$this->assertCount( 2, $ccs );
		$this->assertEquals( $ccs, $this->email->cc );

		// Check adding multiple emails
		$ccs = $this->email->add_cc( array( 'claire@example.com', 'maisie@example.com' ) );
		$this->assertCount( 4, $this->email->cc );
		$this->assertCount( 4, $ccs );
		$this->assertEquals( $ccs, $this->email->cc );

		// Check adding by array an invalid address (Shouldn't be added)
		$ccs = $this->email->add_cc( array( 'claire' ) );
		$this->assertFalse( $ccs );
		$this->assertCount( 4, $this->email->cc );

		// Check adding an array where some are valid, and some are invalid (Shouldn't be added).
		$ccs = $this->email->add_cc( array( 'travis@example.com', 'claire' ) );
		$this->assertFalse( $ccs );
		$this->assertCount( 4, $this->email->cc );
	}

	/**
	 * Same as test_add_cc, but for BCC.
	 */
	public function test_add_bcc() {

		$bccs = $this->email->add_bcc( $this->destination_email );
		$this->assertCount( 1, $this->email->bcc );
		$this->assertCount( 1, $bccs );
		$this->assertEquals( $bccs, $this->email->bcc );

		$bccs = $this->email->add_bcc( $this->destination_email );
		$this->assertCount( 1, $this->email->bcc );
		$this->assertCount( 1, $bccs );
		$this->assertEquals( $bccs, $this->email->bcc );

		$bccs = $this->email->add_bcc( 'joe@' );
		$this->assertCount( 1, $this->email->bcc );
		$this->assertFalse( $bccs );

		$bccs = $this->email->add_bcc( 'bob@example.com' );
		$this->assertCount( 2, $this->email->bcc );
		$this->assertCount( 2, $bccs );
		$this->assertEquals( $bccs, $this->email->bcc );

		$bccs = $this->email->add_bcc( array( 'claire@example.com', 'maisie@example.com' ) );
		$this->assertCount( 4, $this->email->bcc );
		$this->assertCount( 4, $bccs );
		$this->assertEquals( $bccs, $this->email->bcc );

		$bccs = $this->email->add_bcc( array( 'claire' ) );
		$this->assertFalse( $bccs );
		$this->assertCount( 4, $this->email->bcc );

		$bccs = $this->email->add_bcc( array( 'travis@example.com', 'claire' ) );
		$this->assertFalse( $bccs );
		$this->assertCount( 4, $this->email->bcc );
	}

	/**
	 * Same as test_add_cc, but for "to" addresses.
	 */
	public function test_add_to() {

		$tos = $this->email->add_to( $this->destination_email );
		$this->assertCount( 1, $this->email->to );
		$this->assertCount( 1, $tos );
		$this->assertEquals( $tos, $this->email->to );

		$tos = $this->email->add_to( $this->destination_email );
		$this->assertCount( 1, $this->email->to );
		$this->assertCount( 1, $tos );
		$this->assertEquals( $tos, $this->email->to );

		$tos = $this->email->add_to( 'joe@' );
		$this->assertCount( 1, $this->email->to );
		$this->assertFalse( $tos );

		$tos = $this->email->add_to( 'bob@example.com' );
		$this->assertCount( 2, $this->email->to );
		$this->assertCount( 2, $tos );
		$this->assertEquals( $tos, $this->email->to );

		$tos = $this->email->add_to( array( 'claire@example.com', 'maisie@example.com' ) );
		$this->assertCount( 4, $this->email->to );
		$this->assertCount( 4, $tos );
		$this->assertEquals( $tos, $this->email->to );

		$tos = $this->email->add_to( array( 'claire' ) );
		$this->assertFalse( $tos );
		$this->assertCount( 4, $this->email->to );

		$tos = $this->email->add_to( array( 'travis@example.com', 'claire' ) );
		$this->assertFalse( $tos );
		$this->assertCount( 4, $this->email->to );
	}

	public function test_prepare() {
		// @TODO - flesh this out
	}

	/**
	 * Test that emails can't be sent without a valid "to" address.
	 */
	public function test_can_send_email_validation() {

		$this->email->subject = __FUNCTION__;
		$this->email->plain_content = 'This is a test, please ignore.';

		// Send with no to address specified.
		$result = $this->email->send();
		$this->assertFalse( $result );
		$this->assertNull( $this->email->sent );

		// Add an invalid email, and try to send.
		$this->email->add_to( 'fred' );
		$result = $this->email->send();
		$this->assertFalse( $result );
		$this->assertNull( $this->email->sent );

		// Add a valid email, should now send.
		$this->email->add_to( $this->destination_email );
		$result = $this->email->send();
		$this->assertTrue( $result );
		$this->assertTrue( $this->email->sent );
	}

	/**
	 * Test that emails can't be sent without any content.
	 */
	public function test_can_send_content_validation() {

		$this->email->add_to( $this->destination_email );
		$this->email->subject = __FUNCTION__;

		// Send with no content - should fail.
		$result = $this->email->send();
		$this->assertFalse( $result );
		$this->assertNull( $this->email->sent );

		$this->email->plain_content = 'This is a test, please ignore.';
		$result = $this->email->send();
		$this->assertTrue( $result );
		$this->assertTrue( $this->email->sent );

	}

	/**
	 * Test that subject line is autogenerated correctly if not provided.
	 */
	public function test_subject_generation() {

		$this->email->add_to( $this->destination_email );
		$this->email->plain_content = 'This is a test.';
		$result = $this->email->send();
		$this->assertEquals( 'Mail from Test Blog', $this->email->subject );

	}

	/**
	 * @TODO - test that the from address is defaulted correctly.
	 * @return [type] [description]
	 */
	public function test_default_from_address() {

		$this->email->add_to( $this->destination_email );
		$this->email->subject = __FUNCTION__;
		$this->email->html_content = '<p>This <b>is</b> a test.</p>';
		$result = $this->email->send();
		$this->assertTrue( $result );
		$this->assertTrue( $this->email->sent );
		$this->assertContains( 'From: "Test store" <store@example.com>', $this->email->headers );

	}

	/**
	 * @TODO Test that the content type gets set correctly.
	 */
	public function test_content_type_generation_html() {

		$this->email->add_to( $this->destination_email );
		$this->email->subject = __FUNCTION__;
		$this->email->html_content = '<p>This <b>is</b> a test.</p>';
		$result = $this->email->send();
		$this->assertEquals( 'multipart/alternative', $this->email->content_type );
		$this->assertContains( 'Content-Type: multipart/alternative', $this->email->headers );
	}

	public function test_content_type_generation_plain() {
		$this->email->plain_content = 'This is a test.';
		$this->email->add_to( $this->destination_email );
		$result = $this->email->send();
		$this->assertEquals( 'text/plain', $this->email->content_type );
		$this->assertContains( 'Content-Type: text/plain', $this->email->headers );
	}

	/**
	 * Test that the plain text content gets generated correctly.
	 */
	public function test_auto_plain_text_generation() {

		$this->email->add_to( $this->destination_email );
		$this->email->subject = __FUNCTION__;
		$this->email->html_content = <<<CONTENT
		    <p id="first">This is a <strong>bold</strong> test. It includes some <em>emphasis</em>.</p>
		    <p>Link <a href="http://wordpress.org">handling is important</a>. Even <a href="http://google.com/?arg=one&amp;arg2=two" rel="nofollow" target="_blank"><b>complicated</b> links</a>.</p>
CONTENT;
		$result = $this->email->send();
		$this->assertTrue( $result );
		$this->assertTrue( $this->email->sent );
		$this->assertNotEmpty( $this->email->plain_content );
		$this->assertEquals( "This is a bold test. It includes some emphasis.\n\nLink handling is important [http://wordpress.org]. Even complicated links [http://google.com/?arg=one&amp;arg2=two].", $this->email->plain_content );
		// @TODO - better tests on more complex content.
	}

}
