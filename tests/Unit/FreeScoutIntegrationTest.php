<?php
/**
 * Tests for GF_FreeScout integration.
 *
 * Tests critical boundaries:
 * - FreeScout API calls (wp_remote_post to /api/conversations)
 * - API credential validation (wp_remote_get to /api/mailboxes)
 * - Field mapping (email, name, subject, message)
 *
 * @package WWMC_GF_FreeScout
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * FreeScoutIntegrationTest - Tests for FreeScout API integration.
 */
class FreeScoutIntegrationTest extends TestCase {

	/**
	 * Instance of GF_FreeScout.
	 *
	 * @var GF_FreeScout
	 */
	private $addon;

	/**
	 * Flag to track if class is loaded.
	 *
	 * @var bool
	 */
	private static $class_loaded = false;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		FreeScoutApiMock::reset();

		// Mock wp_remote_post to use our mock handler.
		Functions\when( 'wp_remote_post' )->alias( array( 'FreeScoutApiMock', 'handle_post' ) );
		Functions\when( 'wp_remote_get' )->alias( array( 'FreeScoutApiMock', 'handle_get' ) );

		// Load the plugin class only once.
		if ( ! self::$class_loaded ) {
			require_once dirname( __DIR__, 2 ) . '/class-gf-freescout.php';
			self::$class_loaded = true;
		}

		// Create fresh instance for each test.
		$this->addon = new GF_FreeScout();
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Create a test form array.
	 *
	 * @return array
	 */
	private function create_test_form() {
		return array(
			'id'     => 1,
			'title'  => 'Contact Form',
			'fields' => array(
				array(
					'id'    => 1,
					'type'  => 'email',
					'label' => 'Email',
				),
				array(
					'id'    => 2,
					'type'  => 'name',
					'label' => 'Name',
				),
				array(
					'id'    => 3,
					'type'  => 'textarea',
					'label' => 'Message',
				),
			),
		);
	}

	/**
	 * Create a test entry array.
	 *
	 * Note: Uses $overrides + $base to preserve numeric string keys.
	 * array_merge re-indexes numeric string keys ('1' => ...) which breaks GF field lookups.
	 *
	 * @param array $overrides Values to override.
	 * @return array
	 */
	private function create_test_entry( $overrides = array() ) {
		$base = array(
			'id'           => 123,
			'form_id'      => 1,
			'date_created' => '2025-01-15 10:30:00',
			'source_url'   => 'https://example.com/contact',
			'1'            => 'customer@example.com', // Email field.
			'2'            => 'John Doe',              // Name field.
			'3'            => 'This is my message.',   // Message field.
		);
		return $overrides + $base;
	}

	/**
	 * Create a test feed array.
	 *
	 * @param array $overrides Values to override.
	 * @return array
	 */
	private function create_test_feed( $overrides = array() ) {
		return array_merge(
			array(
				'id'   => 1,
				'meta' => array_merge(
					array(
						'feedName'      => 'Test Feed',
						'customerEmail' => '1', // Field ID for email.
						'customerName'  => '2', // Field ID for name.
						'subject'       => 'Contact Form: {form_title}',
						'messageField'  => '3', // Field ID for message.
						'mailboxId'     => '1',
					),
					$overrides['meta'] ?? array()
				),
			),
			array_diff_key( $overrides, array( 'meta' => null ) )
		);
	}

	// -------------------------------------------------------------------------
	// BOUNDARY TESTS: API Configuration
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * Test that process_feed returns error when API URL is not configured.
	 */
	public function process_feed_returns_error_when_api_not_configured() {
		$this->addon->set_plugin_settings( array() );

		$result = $this->addon->process_feed(
			$this->create_test_feed(),
			$this->create_test_entry(),
			$this->create_test_form()
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'api_not_configured', $result->get_error_code() );
		$this->assertCount( 1, $this->addon->feed_errors );
		$this->assertStringContainsString( 'not configured', $this->addon->feed_errors[0]['message'] );
	}

	/**
	 * @test
	 * Test that process_feed returns error when API key is missing.
	 */
	public function process_feed_returns_error_when_api_key_missing() {
		$this->addon->set_plugin_settings(
			array(
				'freescout_url' => 'https://support.example.com',
				// api_key is missing.
			)
		);

		$result = $this->addon->process_feed(
			$this->create_test_feed(),
			$this->create_test_entry(),
			$this->create_test_form()
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'api_not_configured', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// BOUNDARY TESTS: Email Validation
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * Test that process_feed returns error when customer email is missing.
	 */
	public function process_feed_returns_error_when_email_missing() {
		$this->addon->set_plugin_settings(
			array(
				'freescout_url' => 'https://support.example.com',
				'api_key'       => 'test-api-key',
			)
		);

		$entry = $this->create_test_entry(
			array(
				'1' => '', // Empty email.
			)
		);

		$result = $this->addon->process_feed(
			$this->create_test_feed(),
			$entry,
			$this->create_test_form()
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'invalid_email', $result->get_error_code() );
	}

	/**
	 * @test
	 * Test that process_feed returns error when customer email is invalid.
	 */
	public function process_feed_returns_error_when_email_invalid() {
		$this->addon->set_plugin_settings(
			array(
				'freescout_url' => 'https://support.example.com',
				'api_key'       => 'test-api-key',
			)
		);

		$entry = $this->create_test_entry(
			array(
				'1' => 'not-a-valid-email', // Invalid email.
			)
		);

		$result = $this->addon->process_feed(
			$this->create_test_feed(),
			$entry,
			$this->create_test_form()
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'invalid_email', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// BOUNDARY TESTS: Message Validation
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * Test that process_feed returns error when message field is empty.
	 */
	public function process_feed_returns_error_when_message_empty() {
		$this->addon->set_plugin_settings(
			array(
				'freescout_url' => 'https://support.example.com',
				'api_key'       => 'test-api-key',
			)
		);

		$entry = $this->create_test_entry(
			array(
				'3' => '', // Empty message.
			)
		);

		$result = $this->addon->process_feed(
			$this->create_test_feed(),
			$entry,
			$this->create_test_form()
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'empty_message', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// BOUNDARY TESTS: API Communication
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * Test successful API call creates conversation and stores ID.
	 */
	public function process_feed_creates_conversation_successfully() {
		global $gform_entry_meta;
		$gform_entry_meta = array();

		$this->addon->set_plugin_settings(
			array(
				'freescout_url'    => 'https://support.example.com',
				'api_key'          => 'test-api-key',
				'default_mailbox_id' => '1',
			)
		);

		FreeScoutApiMock::set_response(
			'/api/conversations',
			FreeScoutApiMock::success_response(
				array(
					'id'     => 456,
					'number' => 789,
				),
				201
			)
		);

		$result = $this->addon->process_feed(
			$this->create_test_feed(),
			$this->create_test_entry(),
			$this->create_test_form()
		);

		// Should return entry on success.
		$this->assertIsArray( $result );
		$this->assertEquals( 123, $result['id'] );

		// Should store conversation ID in meta.
		$this->assertEquals( 456, gform_get_meta( 123, 'freescout_conversation_id' ) );
		$this->assertEquals( 789, gform_get_meta( 123, 'freescout_conversation_number' ) );

		// Should add success note.
		$this->assertCount( 1, $this->addon->notes );
		$this->assertStringContainsString( '456', $this->addon->notes[0]['note'] );

		// Should have made exactly one API call.
		$this->assertEquals( 1, FreeScoutApiMock::get_request_count() );
		$this->assertTrue( FreeScoutApiMock::was_called( '/api/conversations' ) );
	}

	/**
	 * @test
	 * Test that API payload contains correct field mappings.
	 */
	public function process_feed_sends_correct_api_payload() {
		$this->addon->set_plugin_settings(
			array(
				'freescout_url' => 'https://support.example.com',
				'api_key'       => 'test-api-key',
			)
		);

		FreeScoutApiMock::set_response(
			'/api/conversations',
			FreeScoutApiMock::success_response( array( 'id' => 1 ) )
		);

		$this->addon->process_feed(
			$this->create_test_feed(
				array(
					'meta' => array(
						'mailboxId' => '5',
					),
				)
			),
			$this->create_test_entry(),
			$this->create_test_form()
		);

		$body = FreeScoutApiMock::get_last_request_body();

		$this->assertEquals( 'email', $body['type'] );
		$this->assertEquals( 5, $body['mailboxId'] );
		$this->assertEquals( 'Contact Form: Contact Form', $body['subject'] );
		$this->assertEquals( 'customer@example.com', $body['customer']['email'] );
		$this->assertEquals( 'John', $body['customer']['firstName'] );
		$this->assertEquals( 'Doe', $body['customer']['lastName'] );
		$this->assertStringContainsString( 'This is my message.', $body['threads'][0]['text'] );
		$this->assertEquals( 'customer', $body['threads'][0]['type'] );
		$this->assertTrue( $body['imported'] );
		$this->assertEquals( 'active', $body['status'] );
	}

	/**
	 * @test
	 * Test that API key is sent in correct header.
	 */
	public function process_feed_sends_api_key_in_header() {
		$this->addon->set_plugin_settings(
			array(
				'freescout_url' => 'https://support.example.com',
				'api_key'       => 'my-secret-api-key',
			)
		);

		FreeScoutApiMock::set_response(
			'/api/conversations',
			FreeScoutApiMock::success_response( array( 'id' => 1 ) )
		);

		$this->addon->process_feed(
			$this->create_test_feed(),
			$this->create_test_entry(),
			$this->create_test_form()
		);

		$request = FreeScoutApiMock::get_last_request();

		$this->assertEquals( 'my-secret-api-key', $request['args']['headers']['X-FreeScout-API-Key'] );
		$this->assertEquals( 'application/json', $request['args']['headers']['Content-Type'] );
	}

	// -------------------------------------------------------------------------
	// FAILURE MODE TESTS: API Errors
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * Test handling of API timeout (WP_Error response).
	 */
	public function process_feed_handles_api_timeout() {
		$this->addon->set_plugin_settings(
			array(
				'freescout_url' => 'https://support.example.com',
				'api_key'       => 'test-api-key',
			)
		);

		FreeScoutApiMock::set_response(
			'/api/conversations',
			FreeScoutApiMock::wp_error( 'http_request_failed', 'Connection timed out after 15000 milliseconds' )
		);

		$result = $this->addon->process_feed(
			$this->create_test_feed(),
			$this->create_test_entry(),
			$this->create_test_form()
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'http_request_failed', $result->get_error_code() );
		$this->assertStringContainsString( 'timed out', $result->get_error_message() );
		$this->assertCount( 1, $this->addon->feed_errors );
	}

	/**
	 * @test
	 * Test handling of HTTP 401 Unauthorized response.
	 */
	public function process_feed_handles_unauthorized_response() {
		$this->addon->set_plugin_settings(
			array(
				'freescout_url' => 'https://support.example.com',
				'api_key'       => 'invalid-api-key',
			)
		);

		FreeScoutApiMock::set_response(
			'/api/conversations',
			FreeScoutApiMock::error_response( 401, 'Unauthorized' )
		);

		$result = $this->addon->process_feed(
			$this->create_test_feed(),
			$this->create_test_entry(),
			$this->create_test_form()
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'api_error', $result->get_error_code() );
		$this->assertCount( 1, $this->addon->feed_errors );
		$this->assertStringContainsString( '401', $this->addon->feed_errors[0]['message'] );
	}

	/**
	 * @test
	 * Test handling of HTTP 400 Bad Request response.
	 */
	public function process_feed_handles_bad_request_response() {
		$this->addon->set_plugin_settings(
			array(
				'freescout_url' => 'https://support.example.com',
				'api_key'       => 'test-api-key',
			)
		);

		FreeScoutApiMock::set_response(
			'/api/conversations',
			FreeScoutApiMock::error_response( 400, 'Invalid mailbox ID' )
		);

		$result = $this->addon->process_feed(
			$this->create_test_feed(),
			$this->create_test_entry(),
			$this->create_test_form()
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'api_error', $result->get_error_code() );
	}

	/**
	 * @test
	 * Test handling of HTTP 500 Server Error response.
	 */
	public function process_feed_handles_server_error_response() {
		$this->addon->set_plugin_settings(
			array(
				'freescout_url' => 'https://support.example.com',
				'api_key'       => 'test-api-key',
			)
		);

		FreeScoutApiMock::set_response(
			'/api/conversations',
			FreeScoutApiMock::error_response( 500, 'Internal Server Error' )
		);

		$result = $this->addon->process_feed(
			$this->create_test_feed(),
			$this->create_test_entry(),
			$this->create_test_form()
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'api_error', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// BOUNDARY TESTS: URL Validation
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * Test that validate_freescout_url accepts valid HTTPS URL.
	 */
	public function validate_freescout_url_accepts_valid_https_url() {
		$this->assertTrue( $this->addon->validate_freescout_url( 'https://support.example.com' ) );
	}

	/**
	 * @test
	 * Test that validate_freescout_url accepts valid HTTP URL.
	 */
	public function validate_freescout_url_accepts_valid_http_url() {
		$this->assertTrue( $this->addon->validate_freescout_url( 'http://localhost:8080' ) );
	}

	/**
	 * @test
	 * Test that validate_freescout_url rejects empty value.
	 */
	public function validate_freescout_url_rejects_empty_value() {
		$this->assertFalse( $this->addon->validate_freescout_url( '' ) );
	}

	/**
	 * @test
	 * Test that validate_freescout_url rejects invalid URL.
	 *
	 * Note: filter_var(FILTER_VALIDATE_URL) accepts FTP URLs as valid.
	 * The plugin relies on users providing HTTP/HTTPS URLs, which is
	 * enforced by the FreeScout API itself returning errors for non-HTTP URLs.
	 */
	public function validate_freescout_url_rejects_invalid_url() {
		$this->assertFalse( $this->addon->validate_freescout_url( 'not-a-url' ) );
		$this->assertFalse( $this->addon->validate_freescout_url( 'just text with spaces' ) );
	}

	// -------------------------------------------------------------------------
	// BOUNDARY TESTS: API Credential Validation
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * Test that validate_api_credentials returns true for valid credentials.
	 */
	public function validate_api_credentials_returns_true_for_valid_credentials() {
		$this->addon->set_plugin_settings(
			array(
				'freescout_url' => 'https://support.example.com',
			)
		);

		FreeScoutApiMock::set_response(
			'/api/mailboxes',
			FreeScoutApiMock::success_response(
				array(
					array(
						'id'   => 1,
						'name' => 'Support',
					),
				),
				200
			)
		);

		$result = $this->addon->validate_api_credentials( 'valid-api-key' );

		$this->assertTrue( $result );
		$this->assertTrue( FreeScoutApiMock::was_called( '/api/mailboxes' ) );
	}

	/**
	 * @test
	 * Test that validate_api_credentials returns false for invalid credentials.
	 */
	public function validate_api_credentials_returns_false_for_invalid_credentials() {
		$this->addon->set_plugin_settings(
			array(
				'freescout_url' => 'https://support.example.com',
			)
		);

		FreeScoutApiMock::set_response(
			'/api/mailboxes',
			FreeScoutApiMock::error_response( 401, 'Unauthorized' )
		);

		$result = $this->addon->validate_api_credentials( 'invalid-api-key' );

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 * Test that validate_api_credentials returns false for network error.
	 */
	public function validate_api_credentials_returns_false_for_network_error() {
		$this->addon->set_plugin_settings(
			array(
				'freescout_url' => 'https://support.example.com',
			)
		);

		FreeScoutApiMock::set_response(
			'/api/mailboxes',
			FreeScoutApiMock::wp_error( 'http_request_failed', 'Connection failed' )
		);

		$result = $this->addon->validate_api_credentials( 'test-api-key' );

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 * Test that validate_api_credentials returns null when URL not set.
	 */
	public function validate_api_credentials_returns_null_when_url_not_set() {
		$this->addon->set_plugin_settings( array() );

		$result = $this->addon->validate_api_credentials( 'test-api-key' );

		$this->assertNull( $result );
	}

	/**
	 * @test
	 * Test that validate_api_credentials returns false for empty key.
	 */
	public function validate_api_credentials_returns_false_for_empty_key() {
		$this->addon->set_plugin_settings(
			array(
				'freescout_url' => 'https://support.example.com',
			)
		);

		$result = $this->addon->validate_api_credentials( '' );

		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// EDGE CASES: Customer Name Parsing
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * Test that customer name is correctly split into first and last.
	 */
	public function process_feed_parses_full_name_correctly() {
		$this->addon->set_plugin_settings(
			array(
				'freescout_url' => 'https://support.example.com',
				'api_key'       => 'test-api-key',
			)
		);

		FreeScoutApiMock::set_response(
			'/api/conversations',
			FreeScoutApiMock::success_response( array( 'id' => 1 ) )
		);

		$this->addon->process_feed(
			$this->create_test_feed(),
			$this->create_test_entry(
				array(
					'2' => 'Jane Marie Smith', // Multi-part name.
				)
			),
			$this->create_test_form()
		);

		$body = FreeScoutApiMock::get_last_request_body();

		$this->assertEquals( 'Jane', $body['customer']['firstName'] );
		$this->assertEquals( 'Marie Smith', $body['customer']['lastName'] );
	}

	/**
	 * @test
	 * Test handling of single-word name (first name only).
	 */
	public function process_feed_handles_single_word_name() {
		$this->addon->set_plugin_settings(
			array(
				'freescout_url' => 'https://support.example.com',
				'api_key'       => 'test-api-key',
			)
		);

		FreeScoutApiMock::set_response(
			'/api/conversations',
			FreeScoutApiMock::success_response( array( 'id' => 1 ) )
		);

		$this->addon->process_feed(
			$this->create_test_feed(),
			$this->create_test_entry(
				array(
					'2' => 'Prince', // Single name.
				)
			),
			$this->create_test_form()
		);

		$body = FreeScoutApiMock::get_last_request_body();

		$this->assertEquals( 'Prince', $body['customer']['firstName'] );
		$this->assertArrayNotHasKey( 'lastName', $body['customer'] );
	}

	/**
	 * @test
	 * Test handling of empty customer name (optional field).
	 */
	public function process_feed_handles_empty_name() {
		$this->addon->set_plugin_settings(
			array(
				'freescout_url' => 'https://support.example.com',
				'api_key'       => 'test-api-key',
			)
		);

		FreeScoutApiMock::set_response(
			'/api/conversations',
			FreeScoutApiMock::success_response( array( 'id' => 1 ) )
		);

		$this->addon->process_feed(
			$this->create_test_feed(),
			$this->create_test_entry(
				array(
					'2' => '', // Empty name.
				)
			),
			$this->create_test_form()
		);

		$body = FreeScoutApiMock::get_last_request_body();

		// Name fields should not be included when empty.
		$this->assertArrayNotHasKey( 'firstName', $body['customer'] );
		$this->assertArrayNotHasKey( 'lastName', $body['customer'] );
	}

	// -------------------------------------------------------------------------
	// EDGE CASES: Mailbox ID
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * Test that default mailbox ID is used when not specified in feed.
	 */
	public function process_feed_uses_default_mailbox_id() {
		$this->addon->set_plugin_settings(
			array(
				'freescout_url'      => 'https://support.example.com',
				'api_key'            => 'test-api-key',
				'default_mailbox_id' => '99',
			)
		);

		FreeScoutApiMock::set_response(
			'/api/conversations',
			FreeScoutApiMock::success_response( array( 'id' => 1 ) )
		);

		$feed                    = $this->create_test_feed();
		$feed['meta']['mailboxId'] = ''; // Empty mailbox ID in feed.

		$this->addon->process_feed(
			$feed,
			$this->create_test_entry(),
			$this->create_test_form()
		);

		$body = FreeScoutApiMock::get_last_request_body();

		$this->assertEquals( 99, $body['mailboxId'] );
	}

	/**
	 * @test
	 * Test that feed mailbox ID overrides default.
	 */
	public function process_feed_uses_feed_mailbox_id_over_default() {
		$this->addon->set_plugin_settings(
			array(
				'freescout_url'      => 'https://support.example.com',
				'api_key'            => 'test-api-key',
				'default_mailbox_id' => '99',
			)
		);

		FreeScoutApiMock::set_response(
			'/api/conversations',
			FreeScoutApiMock::success_response( array( 'id' => 1 ) )
		);

		$feed                    = $this->create_test_feed();
		$feed['meta']['mailboxId'] = '42';

		$this->addon->process_feed(
			$feed,
			$this->create_test_entry(),
			$this->create_test_form()
		);

		$body = FreeScoutApiMock::get_last_request_body();

		$this->assertEquals( 42, $body['mailboxId'] );
	}
}
