<?php

class WPSC_Email {

	// User accessible .
	public $from_name;
	public $from_address;
	public $subject       = '';
	public $plain_content = '';
	public $html_content  = '';
	public $template_tag  = '';

	// Publically settable via specific setter functions / API calls.
	private $to           = array(); // @TODO
	private $cc           = array(); // @TODO
	private $bcc          = array(); // @TODO

	// Internal use only.
	private $content_type;
	private $headers;

	// FIXME - do we need to support attachments?

	/**
	 * Constructor.
	 * Set a default from address.
	 */
	public function __construct() {
		// Initialise "from" details to defaults
		$this->from_address = apply_filters( 'wpsc_email_default_from_address', get_option( 'return_email' ) );
		$this->from_name    = apply_filters( 'wpsc_email_default_from_name', get_option( 'return_name' ) );
	}


	/**
	 * Magic getter. Allows us to retrieve properties from the class, without exposing
	 * them all as public, and thus allow direct write access.
	 * @param  string $key The property to retrieve.
	 * @return mixed       The value of the selected property, or null.
	 */
	public function __get( $key ) {
		if ( isset ( $this->$key ) ) {
			return $this->$key;
		}
		$trace = debug_backtrace();
        trigger_error(
            'Undefined property: ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE
        );
		return null;
	}

	/**
	 * Magic setter. Let some properties be written directly, ccontrol access to others.
	 *
	 * @param  string $key   The property to be written.
	 * @param  mixed  $value The value to be written to the property.
	 * @return mixed         The value written to the property.
	 */
	public function __set( $key, $value ) {

		// CC, and BCC should be managed by dedicated setter function.
		// "to" is handled via a parameter to send().
		if ( $key == 'cc' || $key == 'bcc' || $key == 'to' ) {
			$trace = debug_backtrace();
	        trigger_error(
	            'Cannot access private property: ' . $key .
	            ' in ' . $trace[0]['file'] .
	            ' on line ' . $trace[0]['line'],
	            E_USER_ERROR
	        );
			return false;
		}
		return $this->$key = $value;
	}

	/**
	 * Add a CC to the email. If the same address is added multiple times, duplicates
	 * will be ignored.
	 *
	 * @param   string|array  $cc  A single email address, or array of email addresses.
	 * @return  array              The current list of email CCs.
	 */
	public function add_cc( $cc = array() ) {
		return $this->add_destination( 'cc', $cc );
	}


	/**
	 * Add a BCC to the email. If the same address is added multiple times, duplicates
	 * will be ignored.
	 *
	 * @param   string|array  $bcc  A single email address, or array of email addresses.
	 * @return  array               The current list of email BCCs.
	 */
	public function add_bcc( $bcc = array() ) {
		return $this->add_destination( 'bcc', $bcc );
	}

	/**
	 * Add a To address to the email. If the same address is added multiple times,
	 * duplicates will be ignored.
	 *
	 * @param   string|array  $to   A single email address, or array of email addresses.
	 * @return  array               The current list of email Tos.
	 */
	public function add_to( $to = array() ) {
		return $this->add_destination( 'to', $to );
	}

	/**
	 * Send the email to the requested "to" address (string, or array of strings).
	 * This method will validate that it has enough information to proceed.
	 *
	 * @param  string|array  $to  One, or more addresses to send the email to.
	 * @return bool               True or success, false on failure.
	 */
	public function send() {

		if ( ! $this->can_send() ) {
			return false;
		}
		$this->prepare();

		do_action( 'wpsc_email_before_send', $this );

		add_action( 'phpmailer_init', array( $this, '_action_phpmailer_init_multipart' ), 10, 1 );
		add_filter( 'wp_mail_from', array( $this, 'get_from_address' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );
		add_filter( 'wp_mail_content_type', array( $this, 'get_content_type' ) );
		$email_sent = wp_mail( $this->to, $this->subject, $this->html_content, $this->headers );
		remove_filter( 'wp_mail_from', array( $this, 'get_from_address' ) );
		remove_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );
		remove_filter( 'wp_mail_content_type', array( $this, 'get_content_type' ) );
		remove_action( 'phpmailer_init', array( $this, '_action_phpmailer_init_multipart' ), 10, 1 );

		do_action( 'wpsc_email_after_send', $this );

	}

	public function _action_phpmailer_init_multipart( $phpmailer ) {
		// FIXME - Only if we have both?
		$phpmailer->AltBody = $this->plaintext_message;
	}

	/**
	 * Add an address to the email.
	 * @param string       $type       Where to add the address. 'to', 'cc', or 'bcc'.
	 * @param array|false  $addresses  The revised list of addresses, or false on failure.
	 */
	private function add_destination( $type, $addresses ) {
		if ( $type != 'to' && $type != 'cc' && $type != 'bcc' ) {
			return false;
		}
		if ( is_string( $addresses ) ) {
			$addresses = array( $addresses );
		}
		foreach ( $addresses as $address ) {
			if ( filter_var( $address, FILTER_VALIDATE_EMAIL ) === false ) {
				return false;
			}
		}
		$this->$type = array_merge( $this->$type, $addresses );
		return $this->$type = array_unique( $this->$type );

	}

	/**
	 * Prepare to send an email based on the information provided. This includes
	 * generating text content from HTML, setting default subjects, and calculating headers.
	 */
	private function prepare() {
		// Set a default subject if none provided.
		if ( empty( $this->subject ) ) {
			$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
			$this->subject = __( 'Mail from ' . $site_name, 'wpsc' );
		}
		// $this->headers = 'From: "' . $from_name . '" <' . $from_email . ">\n"; // FIXME
		$this->headers .= 'Content-Type: ' . $this->content_type;

		// Generate a plain text part if we only have HTML.
		if ( empty( $this->plain_content ) ) {
			$this->html_to_plain_text();
		}
	}

	/**
	 * Check that we're able to send the email with the information we have.
	 *
	 * @return bool True if we have the information we need. False if not.
	 */
	private function can_send() {

		// Can't send if we don't have any to addresses.
		if ( count($this->to) < 1 ) {
			return false;
		}

		// Can't send unless we have content.
		if ( empty( $this->plain_content ) && empty( $this->html_content ) ) {
			return false;
		}

		return true;
	}

	/**
	 *Determine the appropriate content type and add it to the headers
	 */
	private function set_content_type() {
		if ( ! empty( $this->html_content ) ) {
			$content_type = 'multipart/alternative';
		} else {
			$content_type = 'text/plain';
		}
		$this->content_type = apply_filter( 'wpsc_email_content_type', $content_type, $this );
	}

	private function html_to_plain_text() {
		// @TODO
	}

}