<?php

class WPSC_Email {

	// User accessible .
	public $from_name;
	public $from_address;
	public $subject       = '';
	public $plain_content = '';
	public $html_content  = '';

	/**
	 * @see  apply_template().
	 */
	public $template = null;
	public $sent     = null;

	// Publically settable via specific setter functions / API calls.
	private $to           = array();
	private $cc           = array();
	private $bcc          = array();

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
            'Undefined property: ' . $key .
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

		// Set the status to false to indicate we've tried to send the mail.
		$this->sent = false;
		$this->prepare();

		// Allow plugins to interfere.
		do_action( 'wpsc_email_before_send', $this );

		add_action( 'phpmailer_init', array( $this, '_action_phpmailer_init_multipart' ), 10, 1 );
		// add_filter( 'wp_mail_from', array( $this, 'get_from_address' ) );
		// add_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );
		// add_filter( 'wp_mail_content_type', array( $this, 'get_content_type' ) );
		$content_property = !empty( $this->html_content ) ? 'html_content' : 'plain_content';
		$email_sent = wp_mail( $this->to, $this->subject, $this->$content_property, $this->headers );
		// remove_filter( 'wp_mail_from', array( $this, 'get_from_address' ) );
		// remove_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );
		// remove_filter( 'wp_mail_content_type', array( $this, 'get_content_type' ) );
		remove_action( 'phpmailer_init', array( $this, '_action_phpmailer_init_multipart' ), 10, 1 );

		// Update the status to show the results of sending the email
		$this->sent = $email_sent;

		// Allow plugins to interfere.
		do_action( 'wpsc_email_after_send', $this );

		return $email_sent;
	}

	/**
	 * Add the plaintext fallback if required.
	 * @param  PHPMailer  $phpmailer  The PHPMailer object.
	 */
	public function _action_phpmailer_init_multipart( $phpmailer ) {
		if ( ! empty( $this->html_content ) ) {
			$phpmailer->AltBody = $this->plain_content;
		}
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

		$this->set_content_type();

		// Generate a plain text part if we only have HTML.
		if ( empty( $this->plain_content ) ) {
			$this->html_to_plain_text();
		}

		// Parse into the selected templates
		$this->apply_template();

		// Set the content type.
		$this->headers = 'From: "' . $this->from_name . '" <' . $this->from_address . ">\n"; // FIXME
		$this->headers .= 'Content-Type: ' . $this->content_type;

	}

	/**
	 * Retrieve a template fragment based on the format, postion, and
	 * the $template property of the class.
	 *
	 * {theme_dir}/wpsc-{format}-email-{position}-{template}.php
	 *
	 * If template == null, use
	 * {theme_dir}/wpsc-{format}-email-{position}.php
	 */
	private function get_template_part( $format, $position ) {

		// Template is null to use the standard template
		if ( $this->template === null ) {
			$suffixes[] = '';
		} else {
			// If template isn't null, prefer a template specific one ...
			$suffixes[] = '-'.(string)$this->template;
			// ... but fall back to general if not found.
			$suffixes[] = '';
		}

		$base_template = 'wpsc-' . $format . '-email-' . $position
		;
		foreach ( $suffixes as $suffix ) {
			$path = wpsc_get_template_file_path();
			// FIXME - check it exists, if so use it, but filter
			// is_readable? WPSCs template loader?
			$template_path = apply_filters( 'wpsc_email_template', $base_template . $suffix . '.php', $format, $position, $this );

		}
		return $template_path;
	}

	/**
	 * If $this->template === false
	 *     Then no templates are applied.
	 * If $this->template === null
	 *     The default template are used.
	 * Otherwise
	 *     The precise template will depend on the value of $this->template
	 */
	private function apply_template() {

		// Set the template to false to leave content unaltered
		if ( $this->template === false ) {
			return;
		}

		// HTML
		if ( !empty( $this->html_content ) ) {
			ob_start();
			$template_path = $this->get_template_part( 'html', 'header' );
			include wpsc_get_template_file_path( $template_path );
			echo $this->html_content;
			$template_path = $this->get_template_part( 'html', 'footer' );
			include wpsc_get_template_file_path( $template_path );
			$this->html_content = ob_get_clean();
		}

		// Plain text
		ob_start();
		$template_path = $this->get_template_part( 'plain', 'header' );
		include wpsc_get_template_file_path( $template_path );
		echo $this->plain_content;
		$template_path = $this->get_template_part( 'plain', 'footer' );
		include wpsc_get_template_file_path( $template_path );
		$this->plain_content = ob_get_clean();
	}

	/**
	 * Check that we're able to send the email with the information we have.
	 *
	 * @return bool True if we have the information we need. False if not.
	 */
	private function can_send() {

		// Can't send if we don't have any to addresses.
		if ( count( $this->to ) < 1 ) {
			return false;
		}
		// Can't send unless we have content.
		if ( empty( $this->plain_content ) && empty( $this->html_content ) ) {
			return false;
		}
		return true;

	}

	/**
	 * Determine the appropriate content type and add it to the headers
	 */
	private function set_content_type() {
		if ( ! empty( $this->html_content ) ) {
			$content_type = 'multipart/alternative';
		} else {
			$content_type = 'text/plain';
		}
		$this->content_type = apply_filters( 'wpsc_email_content_type', $content_type, $this );
	}

	/**
	 * Generate a plain text version of the HTML email.
	 * @todo  - this will undoubtedly need improvement.
	 */
	private function html_to_plain_text() {

		// First parse the DOM to extract links to plain text
		$fake_html = '<html><body><div id="fakedomwpec">';
		$fake_html .= $this->html_content;
		$fake_html .= '</div></body></html>';

		$dom = new DomDocument();
		$dom->recover = true;
		$dom->strictErrorChecking = false;
		libxml_use_internal_errors( true );
		$dom->loadHTML( $fake_html );
		$links = $dom->getElementsByTagName( 'a' );
		foreach ( $links as $link ) {
			$node_value = $link->nodeValue . ' ['.$link->getAttribute( 'href' ).']';
			$link->nodeValue = (string)htmlentities( $node_value );
		}

		// Once we're on PHP 5.3.6 we could use $fake_container->saveHTML($dom->getElementById('fakedomwpec'))
		// Until then , string replacements it is.
		$plain_content = $dom->saveHTML();
		$pos = stripos( $plain_content, 'fakedomwpec' );
		$plain_content = substr( $plain_content, $pos + 13 );
		$plain_content = str_replace( '</div></body></html>', '', $plain_content );

		// Replace BRs with new lines
		$plain_content = preg_replace( '/\<br(\s*)?\/?\>/i', "\n", $plain_content );
		// Add a newline after every </p>
		$plain_content = preg_replace( '/\<\/p(\s*)?\>/i', "\n", $plain_content );
		// Now drop stylesheets.
		$plain_content = preg_replace( '#<style(.*?)</style>#s', '', $plain_content );
		// Now drop scripts.
		$plain_content = preg_replace( '#<script(.*?)</script>#s', '', $plain_content );
		// Strip tags.
		$plain_content = strip_tags( $plain_content );
		// Flatten whitespace.
		$plain_content = preg_replace( '#[ \t][ \t]*#s', ' ', $plain_content );
		$plain_content = preg_replace( '#\n[ \t]*#s', "\n", $plain_content );
		$plain_content = trim( $plain_content );

		$this->plain_content = $plain_content;
	}

}