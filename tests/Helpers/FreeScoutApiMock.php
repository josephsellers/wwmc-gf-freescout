<?php
/**
 * FreeScout API mock for testing.
 *
 * @package WWMC_GF_FreeScout
 */

/**
 * FreeScoutApiMock - Captures and simulates HTTP requests to FreeScout API.
 */
class FreeScoutApiMock {
	/**
	 * Captured requests.
	 *
	 * @var array
	 */
	private static $captured_requests = array();

	/**
	 * Configured responses.
	 *
	 * @var array
	 */
	private static $configured_responses = array();

	/**
	 * Default response.
	 *
	 * @var array|WP_Error|null
	 */
	private static $default_response = null;

	/**
	 * Reset all state.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$captured_requests     = array();
		self::$configured_responses  = array();
		self::$default_response      = null;
	}

	/**
	 * Configure a response for a specific URL pattern.
	 *
	 * @param string           $url_pattern URL pattern (can be partial).
	 * @param array|WP_Error   $response    Response to return.
	 * @return void
	 */
	public static function set_response( $url_pattern, $response ) {
		self::$configured_responses[ $url_pattern ] = $response;
	}

	/**
	 * Set the default response for unmatched URLs.
	 *
	 * @param array|WP_Error $response Default response.
	 * @return void
	 */
	public static function set_default_response( $response ) {
		self::$default_response = $response;
	}

	/**
	 * Create a successful response array.
	 *
	 * @param array $body    Response body data.
	 * @param int   $code    HTTP status code.
	 * @return array
	 */
	public static function success_response( $body, $code = 201 ) {
		return array(
			'response' => array(
				'code'    => $code,
				'message' => 'OK',
			),
			'body'     => json_encode( $body ),
		);
	}

	/**
	 * Create an error response array.
	 *
	 * @param int    $code    HTTP status code.
	 * @param string $message Error message.
	 * @return array
	 */
	public static function error_response( $code, $message ) {
		return array(
			'response' => array(
				'code'    => $code,
				'message' => $message,
			),
			'body'     => json_encode( array( 'error' => $message ) ),
		);
	}

	/**
	 * Create a WP_Error response.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @return WP_Error
	 */
	public static function wp_error( $code, $message ) {
		return new WP_Error( $code, $message );
	}

	/**
	 * Handle a wp_remote_post request.
	 *
	 * @param string $url  Request URL.
	 * @param array  $args Request arguments.
	 * @return array|WP_Error
	 */
	public static function handle_post( $url, $args = array() ) {
		self::$captured_requests[] = array(
			'method' => 'POST',
			'url'    => $url,
			'args'   => $args,
		);

		return self::get_response_for_url( $url );
	}

	/**
	 * Handle a wp_remote_get request.
	 *
	 * @param string $url  Request URL.
	 * @param array  $args Request arguments.
	 * @return array|WP_Error
	 */
	public static function handle_get( $url, $args = array() ) {
		self::$captured_requests[] = array(
			'method' => 'GET',
			'url'    => $url,
			'args'   => $args,
		);

		return self::get_response_for_url( $url );
	}

	/**
	 * Get response for a URL.
	 *
	 * @param string $url Request URL.
	 * @return array|WP_Error
	 */
	private static function get_response_for_url( $url ) {
		// Check configured responses.
		foreach ( self::$configured_responses as $pattern => $response ) {
			if ( strpos( $url, $pattern ) !== false ) {
				return $response;
			}
		}

		// Return default response.
		if ( self::$default_response !== null ) {
			return self::$default_response;
		}

		// Default: return a generic success.
		return self::success_response( array( 'id' => 1 ) );
	}

	/**
	 * Get all captured requests.
	 *
	 * @return array
	 */
	public static function get_requests() {
		return self::$captured_requests;
	}

	/**
	 * Get the last captured request.
	 *
	 * @return array|null
	 */
	public static function get_last_request() {
		if ( empty( self::$captured_requests ) ) {
			return null;
		}
		return end( self::$captured_requests );
	}

	/**
	 * Get count of captured requests.
	 *
	 * @return int
	 */
	public static function get_request_count() {
		return count( self::$captured_requests );
	}

	/**
	 * Check if a specific endpoint was called.
	 *
	 * @param string $endpoint Endpoint to check for.
	 * @return bool
	 */
	public static function was_called( $endpoint ) {
		foreach ( self::$captured_requests as $request ) {
			if ( strpos( $request['url'], $endpoint ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get the decoded body from the last POST request.
	 *
	 * @return array|null
	 */
	public static function get_last_request_body() {
		$request = self::get_last_request();
		if ( $request && isset( $request['args']['body'] ) ) {
			return json_decode( $request['args']['body'], true );
		}
		return null;
	}
}

/**
 * Mock WP_Error class if not defined.
 */
if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Mock WP_Error class.
	 */
	class WP_Error {
		/**
		 * Error code.
		 *
		 * @var string
		 */
		private $code;

		/**
		 * Error message.
		 *
		 * @var string
		 */
		private $message;

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 */
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		/**
		 * Get error code.
		 *
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
		}

		/**
		 * Get error message.
		 *
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}
	}

	/**
	 * Check if value is a WP_Error.
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	function is_wp_error( $value ) {
		return $value instanceof WP_Error;
	}
}

/**
 * Mock wp_remote_retrieve_response_code.
 *
 * @param array $response Response array.
 * @return int
 */
function wp_remote_retrieve_response_code( $response ) {
	if ( is_wp_error( $response ) ) {
		return 0;
	}
	return isset( $response['response']['code'] ) ? $response['response']['code'] : 0;
}

/**
 * Mock wp_remote_retrieve_body.
 *
 * @param array $response Response array.
 * @return string
 */
function wp_remote_retrieve_body( $response ) {
	if ( is_wp_error( $response ) ) {
		return '';
	}
	return isset( $response['body'] ) ? $response['body'] : '';
}
