<?php

class WPSC_Email_TestCase extends WP_UnitTestCase {

	function test_create_object() {
		$email = new WPSC_Email();
		$this->assertInstanceOf( 'WPSC_Email', $email );
	}

	function test_add_cc() {
		$email = new WPSC_Email();
		$ccs = $email->add_cc( 'joe@example.com' );
		$this->assertCount( 1, $email->cc );
		$this->assertCount( 1, $ccs );
		$this->assertEquals( $ccs, $email->cc );
		$ccs = $email->add_cc( 'joe@example.com' );
		$this->assertCount( 1, $email->cc );
		$this->assertCount( 1, $ccs );
		$this->assertEquals( $ccs, $email->cc );
		$ccs = $email->add_cc( 'bob@example.com' );
		$this->assertCount( 2, $email->cc );
		$this->assertCount( 2, $ccs );
		$this->assertEquals( $ccs, $email->cc );
	}

	function test_add_bcc() {
		$email = new WPSC_Email();
		$bccs = $email->add_bcc( 'joe@example.com' );
		$this->assertCount( 1, $email->bcc );
		$this->assertCount( 1, $bccs );
		$this->assertEquals( $bccs, $email->bcc );
		$bccs = $email->add_bcc( 'joe@example.com' );
		$this->assertCount( 1, $email->bcc );
		$this->assertCount( 1, $bccs );
		$this->assertEquals( $bccs, $email->bcc );
		$bccs = $email->add_bcc( 'bob@example.com' );
		$this->assertCount( 2, $email->bcc );
		$this->assertCount( 2, $bccs );
		$this->assertEquals( $bccs, $email->bcc );
	}

	function test_add_to() {
		$email = new WPSC_Email();
		$tos = $email->add_to( 'joe@example.com' );
		$this->assertCount( 1, $email->to );
		$this->assertCount( 1, $tos );
		$this->assertEquals( $tos, $email->to );
		$tos = $email->add_to( 'joe@example.com' );
		$this->assertCount( 1, $email->to );
		$this->assertCount( 1, $tos );
		$this->assertEquals( $tos, $email->to );
		$tos = $email->add_to( 'bob@example.com' );
		$this->assertCount( 2, $email->to );
		$this->assertCount( 2, $tos );
		$this->assertEquals( $tos, $email->to );
	}

	function test_prepare() {
		$email = new WPSC_Email();
		// @TODO - flesh this out
	}

	// function test_can_send_content_validation() {
	// 	$email = new WPSC_Email();
	// 	$result = $email->send();
	// 	$this->assertFalse( $result );
	// }
}

