<?php
/**
 * Uninstall cleanup for ChatterMate Chat.
 *
 * Runs when the plugin is deleted from the WordPress admin. Removes the single
 * option the plugin stores. No other data is created by this plugin.
 *
 * @package ChatterMate
 */

// Only run in a genuine uninstall context.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'chattermate_options' );

// Multisite: also clear the option on every site in the network.
if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		delete_option( 'chattermate_options' );
		restore_current_blog();
	}
}
