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
	delete_transient( 'chattermate_connection' );
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
