<?php
/**
 * Uninstall cleanup for ChatterMate Chat.
 *
 * Runs when the plugin is deleted from the WordPress admin. Removes every option
 * and transient the plugin stores, on every site of a network.
 *
 * @package ChatterMate
 */

// Only run in a genuine uninstall context.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete the plugin's data on the current site.
 */
function chattermate_uninstall_site() {
	delete_option( 'chattermate_options' );
	delete_option( 'chattermate_setup_pending' );
	delete_option( 'chattermate_api_key' );
	delete_option( 'chattermate_requires_token' );
	delete_transient( 'chattermate_connection' );
	delete_transient( 'chattermate_key_rejected' );
	delete_option( 'chattermate_connect_states' );

	// Cached visitor tokens: user options, stored under this site's table prefix.
	global $wpdb;
	delete_metadata( 'user', 0, $wpdb->get_blog_prefix() . 'chattermate_token', '', true );
}

if ( is_multisite() ) {
	$chattermate_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $chattermate_site_ids as $chattermate_site_id ) {
		switch_to_blog( $chattermate_site_id );
		chattermate_uninstall_site();
		restore_current_blog();
	}
} else {
	chattermate_uninstall_site();
}
