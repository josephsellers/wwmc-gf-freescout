<?php
/**
 * Migration script: Rename gravityformsfreescout to wwmc-gf-freescout
 *
 * This script migrates GF add-on settings when renaming the plugin folder/slug.
 *
 * Usage:
 *   # Dry run (see what would change, no modifications):
 *   docker exec wwmc-wordpress wp eval-file /var/www/html/wp-content/plugins/wwmc-gf-freescout/migrate-gf-freescout.php
 *
 *   # Actually run the migration:
 *   docker exec wwmc-wordpress wp eval-file /var/www/html/wp-content/plugins/wwmc-gf-freescout/migrate-gf-freescout.php --run
 *
 * What it migrates:
 *   1. Plugin settings (wp_options): API key, URL, default mailbox
 *   2. Form feeds (wp_gf_addon_feed table): addon_slug column
 *   3. Active plugins list: swaps old path for new path
 *
 * @package WWMC_GF_FreeScout
 */

// Ensure we're running in WordPress context.
if ( ! defined( 'ABSPATH' ) ) {
	echo "Error: This script must be run via WP-CLI: wp eval-file migrate-gf-freescout.php\n";
	exit( 1 );
}

// Configuration.
$old_slug    = 'gravityformsfreescout';
$new_slug    = 'wwmc-gf-freescout';
$old_path    = 'gravityformsfreescout/freescout.php';
$new_path    = 'wwmc-gf-freescout/freescout.php';
$old_option  = 'gravityformsaddon_' . $old_slug . '_settings';
$new_option  = 'gravityformsaddon_' . $new_slug . '_settings';

// Check for run mode.
// Set GF_MIGRATE_RUN=1 environment variable to actually run the migration.
$dry_run = true;
if ( getenv( 'GF_MIGRATE_RUN' ) === '1' ) {
	$dry_run = false;
}

echo "\n";
echo "=======================================================\n";
echo " GF FreeScout Migration: {$old_slug} -> {$new_slug}\n";
echo "=======================================================\n";
echo "\n";

if ( $dry_run ) {
	echo ">>> DRY RUN MODE - No changes will be made <<<\n";
	echo "    Add --run flag to execute the migration\n\n";
} else {
	echo ">>> LIVE MODE - Changes will be applied <<<\n\n";
}

// Track results.
$results = array(
	'settings_migrated'     => false,
	'feeds_updated'         => 0,
	'active_plugins_updated' => false,
);

// -------------------------------------------------------------------------
// 1. Migrate plugin settings (wp_options)
// -------------------------------------------------------------------------
echo "1. Plugin Settings (wp_options)\n";
echo "   --------------------------------\n";

$old_settings = get_option( $old_option );
$new_settings = get_option( $new_option );

if ( false === $old_settings ) {
	echo "   Old settings ({$old_option}): NOT FOUND\n";
	echo "   -> Nothing to migrate\n";
} else {
	echo "   Old settings ({$old_option}): FOUND\n";

	// Show what we found.
	if ( is_array( $old_settings ) ) {
		$api_url = isset( $old_settings['freescout_url'] ) ? $old_settings['freescout_url'] : '(not set)';
		$api_key = isset( $old_settings['api_key'] ) ? '****' . substr( $old_settings['api_key'], -4 ) : '(not set)';
		$mailbox = isset( $old_settings['default_mailbox_id'] ) ? $old_settings['default_mailbox_id'] : '(not set)';
		echo "      - FreeScout URL: {$api_url}\n";
		echo "      - API Key: {$api_key}\n";
		echo "      - Default Mailbox ID: {$mailbox}\n";
	}

	if ( false !== $new_settings ) {
		echo "   New settings ({$new_option}): ALREADY EXISTS\n";
		echo "   -> Skipping to avoid overwrite (delete new option first if needed)\n";
	} else {
		echo "   New settings ({$new_option}): Does not exist\n";

		if ( ! $dry_run ) {
			// Copy to new option.
			$added = add_option( $new_option, $old_settings );
			if ( $added ) {
				echo "   -> MIGRATED: Settings copied to new option\n";
				$results['settings_migrated'] = true;

				// Delete old option.
				delete_option( $old_option );
				echo "   -> CLEANED: Old option deleted\n";
			} else {
				echo "   -> ERROR: Failed to create new option\n";
			}
		} else {
			echo "   -> Would migrate settings to new option\n";
			echo "   -> Would delete old option\n";
		}
	}
}

echo "\n";

// -------------------------------------------------------------------------
// 2. Migrate form feeds (wp_gf_addon_feed table)
// -------------------------------------------------------------------------
echo "2. Form Feeds (wp_gf_addon_feed table)\n";
echo "   ------------------------------------\n";

global $wpdb;
$feed_table = $wpdb->prefix . 'gf_addon_feed';

// Check if table exists.
$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$feed_table}'" );

if ( ! $table_exists ) {
	echo "   Feed table does not exist (Gravity Forms not installed?)\n";
} else {
	// Count feeds with old slug.
	$old_feed_count = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$feed_table} WHERE addon_slug = %s",
			$old_slug
		)
	);

	// Count feeds with new slug.
	$new_feed_count = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$feed_table} WHERE addon_slug = %s",
			$new_slug
		)
	);

	echo "   Feeds with old slug ({$old_slug}): {$old_feed_count}\n";
	echo "   Feeds with new slug ({$new_slug}): {$new_feed_count}\n";

	if ( $old_feed_count > 0 ) {
		// Show which forms have feeds.
		$feeds = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, form_id, is_active FROM {$feed_table} WHERE addon_slug = %s",
				$old_slug
			)
		);

		echo "   Feeds to migrate:\n";
		foreach ( $feeds as $feed ) {
			$status = $feed->is_active ? 'active' : 'inactive';
			echo "      - Feed ID {$feed->id} (Form ID {$feed->form_id}, {$status})\n";
		}

		if ( ! $dry_run ) {
			$updated = $wpdb->update(
				$feed_table,
				array( 'addon_slug' => $new_slug ),
				array( 'addon_slug' => $old_slug ),
				array( '%s' ),
				array( '%s' )
			);

			if ( false !== $updated ) {
				echo "   -> MIGRATED: {$updated} feed(s) updated to new slug\n";
				$results['feeds_updated'] = $updated;
			} else {
				echo "   -> ERROR: Failed to update feeds\n";
			}
		} else {
			echo "   -> Would update {$old_feed_count} feed(s) to new slug\n";
		}
	} else {
		echo "   -> No feeds to migrate\n";
	}
}

echo "\n";

// -------------------------------------------------------------------------
// 3. Update active plugins list
// -------------------------------------------------------------------------
echo "3. Active Plugins List\n";
echo "   ---------------------\n";

$active_plugins = get_option( 'active_plugins', array() );

$has_old = in_array( $old_path, $active_plugins, true );
$has_new = in_array( $new_path, $active_plugins, true );

echo "   Old plugin path ({$old_path}): " . ( $has_old ? 'ACTIVE' : 'not active' ) . "\n";
echo "   New plugin path ({$new_path}): " . ( $has_new ? 'ACTIVE' : 'not active' ) . "\n";

if ( $has_old && ! $has_new ) {
	if ( ! $dry_run ) {
		// Replace old path with new path.
		$key = array_search( $old_path, $active_plugins, true );
		$active_plugins[ $key ] = $new_path;
		update_option( 'active_plugins', $active_plugins );
		echo "   -> MIGRATED: Swapped plugin paths in active_plugins\n";
		$results['active_plugins_updated'] = true;
	} else {
		echo "   -> Would swap old path for new path\n";
	}
} elseif ( ! $has_old && $has_new ) {
	echo "   -> Already using new path, no change needed\n";
} elseif ( $has_old && $has_new ) {
	echo "   -> WARNING: Both paths are active! Manual cleanup needed.\n";
} else {
	echo "   -> Neither plugin is active\n";
}

echo "\n";

// -------------------------------------------------------------------------
// Summary
// -------------------------------------------------------------------------
echo "=======================================================\n";
echo " Summary\n";
echo "=======================================================\n";

if ( $dry_run ) {
	echo "\n";
	echo "This was a DRY RUN. To apply changes, run:\n";
	echo "\n";
	echo "  docker exec -e GF_MIGRATE_RUN=1 CONTAINER wp eval-file \\\n";
	echo "    /var/www/html/wp-content/plugins/wwmc-gf-freescout/migrate-gf-freescout.php --allow-root\n";
	echo "\n";
} else {
	echo "\n";
	echo "Migration complete!\n";
	echo "  - Settings migrated: " . ( $results['settings_migrated'] ? 'Yes' : 'No (already done or not found)' ) . "\n";
	echo "  - Feeds updated: {$results['feeds_updated']}\n";
	echo "  - Active plugins updated: " . ( $results['active_plugins_updated'] ? 'Yes' : 'No (already done)' ) . "\n";
	echo "\n";
	echo "Next steps:\n";
	echo "  1. Verify the plugin is working: check Forms -> Settings -> FreeScout\n";
	echo "  2. Test a form submission to ensure feeds still work\n";
	echo "  3. Remove the old plugin folder if it still exists:\n";
	echo "     rm -rf /var/www/html/wp-content/plugins/gravityformsfreescout\n";
	echo "\n";
}
