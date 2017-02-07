<?php
/**
 * Include CLI migration commands for WP CLI Scripts.
 *
 * @package WP CLI Scripts
 */

/**
 * CLI migration commands
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) :
	// Include base migration class.
	require WPMU_PLUGIN_DIR . '/wp-cli-sripts/class-base.php';

	// Include images migration class.
	require WPMU_PLUGIN_DIR . '/wp-cli-sripts/class-images.php';
	WP_CLI::add_command( 'wds_migrate images', 'WP_CLI_Scripts_Migration_Images' );

	// Include categories migration class.
	require WPMU_PLUGIN_DIR . '/wp-cli-sripts/class-categories.php';
	WP_CLI::add_command( 'wds_migrate categories', 'WP_CLI_Scripts_Migration_Categories' );
endif;
