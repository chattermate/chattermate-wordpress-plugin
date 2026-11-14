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
 * Tell ChatterMate this site is gone, so it stops reading it and looking up its
 * orders. Signed with the site link's secret, as site-link.php signs.
 */
function chattermate_uninstall_unlink() {
	$link    = get_option( 'chattermate_site_link' );
	$options = get_option( 'chattermate_options' );
	if ( ! is_array( $link ) || empty( $link['site_id'] ) || empty( $link['secret'] ) ) {
		return;
	}
	$api       = is_array( $options ) && ! empty( $options['api_url'] ) ? untrailingslashit( $options['api_url'] ) : 'https://api.chattermate.chat/api/v1';
	$body      = '{}';
	$timestamp = (string) time();
	wp_safe_remote_post(
		$api . '/enterprise/wordpress/sites/' . (int) $link['site_id'] . '/disconnect',
		array(
			'timeout'  => 5,
			'blocking' => false,
			'headers'  => array(
				'Content-Type'            => 'application/json',
				'X-ChatterMate-Timestamp' => $timestamp,
				'X-ChatterMate-Signature' => hash_hmac( 'sha256', $timestamp . '.disconnect.' . $body, $link['secret'] ),
			),
			'body'     => $body,
		)
	);
}

/**
 * Delete the plugin's data on the current site.
 */
function chattermate_uninstall_site() {
	chattermate_uninstall_unlink();
	delete_option( 'chattermate_options' );
	delete_option( 'chattermate_setup_pending' );
	delete_option( 'chattermate_api_key' );
	delete_option( 'chattermate_requires_token' );
	delete_transient( 'chattermate_connection' );
	delete_transient( 'chattermate_key_rejected' );
	delete_option( 'chattermate_connect_states' );
	delete_option( 'chattermate_site_link' );
	wp_clear_scheduled_hook( 'chattermate_content_changed' );

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
