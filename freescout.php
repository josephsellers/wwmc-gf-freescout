<?php
/**
 * Plugin Name: WWMC Gravity Forms Helpdesk Add-On
 * Plugin URI: https://github.com/josephsellers/wwmc-gf-freescout
 * Description: Integrates Gravity Forms with LibreDesk helpdesk by creating conversations from form submissions.
 * Version: 2.0.0
 * Author: Joseph Sellers
 * Author URI: https://rokesmith.com
 * License: GPL-2.0+
 * Text Domain: wwmc-gf-freescout
 * Domain Path: /languages
 *
 * @package WWMC_GF_FreeScout
 */

defined( 'ABSPATH' ) || die();

// Define plugin version constant.
define( 'GF_FREESCOUT_VERSION', '2.0.0' );

// Bootstrap the add-on when Gravity Forms is loaded.
add_action( 'gform_loaded', array( 'GF_FreeScout_Bootstrap', 'load' ), 5 );

/**
 * Class GF_FreeScout_Bootstrap
 *
 * Handles the loading of the FreeScout Add-On and registers with the Add-On Framework.
 */
class GF_FreeScout_Bootstrap {

	/**
	 * If the Feed Add-On Framework exists, FreeScout Add-On is loaded.
	 *
	 * @return void
	 */
	public static function load() {
		// Check if the Feed Add-On Framework is available.
		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		// Require the main class file.
		require_once plugin_dir_path( __FILE__ ) . 'class-gf-freescout.php';

		// Register the add-on.
		GFAddOn::register( 'GF_FreeScout' );
	}
}

/**
 * Returns an instance of the GF_FreeScout class.
 *
 * @see    GF_FreeScout::get_instance()
 * @return GF_FreeScout
 */
function gf_freescout() {
	return GF_FreeScout::get_instance();
}
