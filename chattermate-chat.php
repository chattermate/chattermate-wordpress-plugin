<?php
/**
 * Plugin Name:       ChatterMate Chat
 * Plugin URI:        https://github.com/chattermate/chattermate-wordpress-plugin
 * Description:       AI customer support on your WordPress site. ChatterMate answers from your own content, hands over to your team, and turns hard questions into tickets.
 * Version:           1.1.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            ChatterMate
 * Author URI:        https://chattermate.chat
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       chattermate-chat
 *
 * @package ChatterMate
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CHATTERMATE_VERSION', '1.1.0' );
define( 'CHATTERMATE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CHATTERMATE_URL', plugin_dir_url( __FILE__ ) );

// Stored data, every name in one place. uninstall.php removes each of them.
define( 'CHATTERMATE_OPTION', 'chattermate_options' );
define( 'CHATTERMATE_SETUP_FLAG', 'chattermate_setup_pending' );
define( 'CHATTERMATE_CONNECTION_TRANSIENT', 'chattermate_connection' );
define( 'CHATTERMATE_API_KEY_OPTION', 'chattermate_api_key' );
define( 'CHATTERMATE_REQUIRES_TOKEN_OPTION', 'chattermate_requires_token' );
define( 'CHATTERMATE_CONNECT_STATES_OPTION', 'chattermate_connect_states' );
define( 'CHATTERMATE_KEY_REJECTED_TRANSIENT', 'chattermate_key_rejected' );
// Per site: stored with update_user_option(), so it carries the site's table prefix.
define( 'CHATTERMATE_TOKEN_META', 'chattermate_token' );

// Hosted ChatterMate defaults. Self-hosters override the script and API URLs under Advanced.
define( 'CHATTERMATE_DEFAULT_SCRIPT_URL', 'https://app.chattermate.chat/webclient/chattermate.min.js' );
define( 'CHATTERMATE_DEFAULT_API_URL', 'https://api.chattermate.chat/api/v1' );
define( 'CHATTERMATE_APP_URL', 'https://app.chattermate.chat' );
define( 'CHATTERMATE_DOCS_URL', 'https://docs.chattermate.chat/features/widget' );
define( 'CHATTERMATE_SUPPORT_URL', 'https://support.chattermate.chat/' );

// Admin screen slug.
define( 'CHATTERMATE_PAGE', 'chattermate' );

require_once CHATTERMATE_DIR . 'includes/options.php';
require_once CHATTERMATE_DIR . 'includes/connection.php';
require_once CHATTERMATE_DIR . 'includes/site-link.php';
require_once CHATTERMATE_DIR . 'includes/orders.php';
require_once CHATTERMATE_DIR . 'includes/identity.php';
require_once CHATTERMATE_DIR . 'includes/context.php';
require_once CHATTERMATE_DIR . 'includes/frontend.php';
require_once CHATTERMATE_DIR . 'includes/blocks.php';

if ( is_admin() ) {
	require_once CHATTERMATE_DIR . 'includes/admin-page.php';
	require_once CHATTERMATE_DIR . 'includes/notices.php';
}

/**
 * On activation, ask for setup until a Widget ID is saved, on every site of a
 * network when activated network-wide.
 *
 * @param bool $network_wide Whether the plugin is being activated for the whole network.
 */
function chattermate_activate( $network_wide = false ) {
	if ( ! is_multisite() || ! $network_wide ) {
		chattermate_flag_setup();
		return;
	}

	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
		switch_to_blog( $site_id );
		chattermate_flag_setup();
		restore_current_blog();
	}
}
register_activation_hook( __FILE__, 'chattermate_activate' );

/**
 * A site added to a network where the plugin is network-active starts set up
 * like the others.
 *
 * @param WP_Site $site The new site.
 */
function chattermate_new_network_site( $site ) {
	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( ! is_plugin_active_for_network( plugin_basename( __FILE__ ) ) ) {
		return;
	}

	switch_to_blog( $site->blog_id );
	chattermate_flag_setup();
	restore_current_blog();
}
add_action( 'wp_initialize_site', 'chattermate_new_network_site', 20 );

/**
 * Raise the setup notice on the current site while it has no Widget ID.
 */
function chattermate_flag_setup() {
	// A first install starts with page details for the AI on; a site that already
	// has settings keeps what its admin chose.
	if ( null === get_option( CHATTERMATE_OPTION, null ) ) {
		add_option( CHATTERMATE_OPTION, array_merge( chattermate_default_options(), array( 'share_context' => true ) ) );
	}

	$options = chattermate_get_options();
	if ( '' === $options['widget_id'] ) {
		update_option( CHATTERMATE_SETUP_FLAG, 1, false );
	}
}

/**
 * Add a "Settings" link on the Plugins screen row.
 *
 * @param array $links Existing action links.
 * @return array
 */
function chattermate_plugin_action_links( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( chattermate_admin_page_url() ),
		esc_html__( 'Settings', 'chattermate-chat' )
	);
	array_unshift( $links, $settings_link );

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'chattermate_plugin_action_links' );
