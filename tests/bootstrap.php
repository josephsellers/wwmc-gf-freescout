<?php
/**
 * PHPUnit bootstrap file for WWMC GF FreeScout.
 *
 * Sets up test environment with Brain Monkey for WordPress function mocking.
 *
 * @package WWMC_GF_FreeScout
 * @since 1.0.0
 */

// Load Composer autoloader (includes Brain Monkey, PHPUnit, Mockery).
require_once '/opt/dev-tools/vendor/autoload.php';

// Load test helper classes.
require_once __DIR__ . '/Helpers/GravityFormsMock.php';
require_once __DIR__ . '/Helpers/FreeScoutApiMock.php';

// Define plugin constants.
define( 'GF_FREESCOUT_VERSION', '1.0.0' );
define( 'GF_FREESCOUT_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'GF_FREESCOUT_PLUGIN_URL', 'http://localhost/wp-content/plugins/wwmc-gf-freescout/' );

// Define ABSPATH if not defined.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/var/www/html/' );
}

// Define Gravity Forms helper functions as real PHP functions.
// These need to exist outside of Brain\Monkey because they're called in the plugin class.
if ( ! function_exists( 'rgar' ) ) {
	/**
	 * Get a value from an array.
	 *
	 * @param array  $array   Array to search.
	 * @param string $key     Key to find.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function rgar( $array, $key, $default = '' ) {
		return isset( $array[ $key ] ) ? $array[ $key ] : $default;
	}
}

if ( ! function_exists( 'rgars' ) ) {
	/**
	 * Get a nested value from an array using path notation.
	 *
	 * @param array  $array   Array to search.
	 * @param string $path    Path like "meta/feedName".
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function rgars( $array, $path, $default = '' ) {
		$parts   = explode( '/', $path );
		$current = $array;
		foreach ( $parts as $part ) {
			if ( ! isset( $current[ $part ] ) ) {
				return $default;
			}
			$current = $current[ $part ];
		}
		return $current;
	}
}

// Define WordPress functions that need to exist globally.
if ( ! function_exists( 'is_email' ) ) {
	/**
	 * Check if email is valid.
	 *
	 * @param string $email Email to check.
	 * @return bool
	 */
	function is_email( $email ) {
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * Parse URL (wrapper for parse_url).
	 *
	 * @param string $url       URL to parse.
	 * @param int    $component Component to return.
	 * @return mixed
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * JSON encode (wrapper for json_encode).
	 *
	 * @param mixed $data  Data to encode.
	 * @param int   $flags JSON flags.
	 * @return string
	 */
	function wp_json_encode( $data, $flags = 0 ) {
		return json_encode( $data, $flags );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * Get home URL.
	 *
	 * @param string $path Path to append.
	 * @return string
	 */
	function home_url( $path = '' ) {
		return 'https://example.com' . $path;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Escape HTML.
	 *
	 * @param string $text Text to escape.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Escape HTML with translation.
	 *
	 * @param string $text   Text to escape.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( $text );
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Translate string.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Add action hook.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Args.
	 * @return bool
	 */
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	/**
	 * Get plugin directory path.
	 *
	 * @param string $file File.
	 * @return string
	 */
	function plugin_dir_path( $file ) {
		return GF_FREESCOUT_PLUGIN_DIR;
	}
}

// Note: Brain\Monkey functions need to be set up in each test's setUp() method.
// The bootstrap only defines functions that need to exist globally.
