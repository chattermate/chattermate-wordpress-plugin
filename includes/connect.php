<?php
/**
 * One-click connect: the admin picks an agent in ChatterMate, which sends them
 * back here with its Widget ID, so nobody copies and pastes it.
 *
 * Out: {app}/connect/wordpress?return_url=<admin-post.php?action=chattermate_connect>
 * &state=<random>&site_name=<site name>&site_url=<home URL>. Back: the same
 * admin-post URL with widget_id and state, or error=cancelled.
 *
 * The state is random, single-use, tied to the admin who started and kept for an
 * hour, so a forged or replayed return changes nothing. Unlike a WordPress nonce
 * it survives the admin having to log in to WordPress again on the way back.
 *
 * @package ChatterMate
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CHATTERMATE_CONNECT_STATE_TTL', HOUR_IN_SECONDS );

/**
 * Whether one-click connect is offered: on ChatterMate cloud. A self-hosted app
 * may predate the connect page, so self-hosters paste the Widget ID.
 *
 * @param array $options Settings from chattermate_get_options().
 * @return bool
 */
function chattermate_connect_available( $options ) {
	return '' === $options['script_url'];
}

/**
 * The "Connect with ChatterMate" link: starts a connection when clicked.
 *
 * @return string
 */
function chattermate_connect_start_url() {
	return wp_nonce_url( admin_url( 'admin-post.php?action=chattermate_connect_start' ), 'chattermate_connect_start' );
}

/**
 * Clicked "Connect with ChatterMate": issue a state and go to ChatterMate.
 */
function chattermate_handle_connect_start() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'chattermate-chat' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'chattermate_connect_start' );

	$options = chattermate_get_options();
	if ( ! chattermate_connect_available( $options ) ) {
		wp_safe_redirect( chattermate_admin_page_url() );
		exit;
	}
	$app     = wp_parse_url( chattermate_app_url( $options ), PHP_URL_HOST );
	add_filter(
		'allowed_redirect_hosts',
		function ( $hosts ) use ( $app ) {
			$hosts[] = $app;
			return $hosts;
		}
	);

	wp_safe_redirect( chattermate_connect_url( $options ) );
	exit;
}
add_action( 'admin_post_chattermate_connect_start', 'chattermate_handle_connect_start' );

/**
 * The ChatterMate page that starts a connection, with a fresh state.
 *
 * @param array $options Settings from chattermate_get_options().
 * @return string
 */
function chattermate_connect_url( $options ) {
	return add_query_arg(
		array(
			'return_url' => rawurlencode( admin_url( 'admin-post.php?action=chattermate_connect' ) ),
			'state'      => chattermate_issue_connect_state(),
			'site_name'  => rawurlencode( wp_strip_all_tags( get_bloginfo( 'name' ) ) ),
			'site_url'   => rawurlencode( home_url( '/' ) ),
		),
		chattermate_app_url( $options ) . '/connect/wordpress'
	);
}

/**
 * Start a connection for the current admin.
 *
 * @return string The state to carry through ChatterMate.
 */
function chattermate_issue_connect_state() {
	$states           = chattermate_live_connect_states();
	$state            = wp_generate_password( 32, false, false );
	$states[ $state ] = array(
		'user'    => get_current_user_id(),
		'expires' => time() + CHATTERMATE_CONNECT_STATE_TTL,
	);
	update_option( CHATTERMATE_CONNECT_STATES_OPTION, $states, false );

	return $state;
}

/**
 * Started connections that have not run out.
 *
 * @return array<string, array{user:int, expires:int}>
 */
function chattermate_live_connect_states() {
	$states = get_option( CHATTERMATE_CONNECT_STATES_OPTION, array() );
	if ( ! is_array( $states ) ) {
		return array();
	}

	$now = time();
	return array_filter(
		$states,
		function ( $entry ) use ( $now ) {
			return is_array( $entry ) && isset( $entry['user'], $entry['expires'] ) && $entry['expires'] > $now;
		}
	);
}

/**
 * Use up a state: true when the current admin started it and it has not run out.
 *
 * @param string $state State from the return.
 * @return bool
 */
function chattermate_consume_connect_state( $state ) {
	$states = chattermate_live_connect_states();
	$valid  = '' !== $state && isset( $states[ $state ] ) && (int) $states[ $state ]['user'] === get_current_user_id();
	if ( isset( $states[ $state ] ) ) {
		unset( $states[ $state ] );
	}
	update_option( CHATTERMATE_CONNECT_STATES_OPTION, $states, false );

	return $valid;
}

/**
 * The return from ChatterMate: save the chosen Widget ID and show the result.
 */
function chattermate_handle_connect_return() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'chattermate-chat' ), '', array( 'response' => 403 ) );
	}

	// The single-use state stands in for a nonce here, see the file comment.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
	if ( ! chattermate_consume_connect_state( $state ) ) {
		chattermate_connect_redirect( 'expired' );
	}

	if ( isset( $_GET['error'] ) ) {
		chattermate_connect_redirect( 'cancelled' );
	}

	$widget_id = chattermate_sanitize_widget_id( isset( $_GET['widget_id'] ) ? sanitize_text_field( wp_unslash( $_GET['widget_id'] ) ) : '' );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	if ( '' === $widget_id ) {
		chattermate_connect_redirect( 'missing' );
	}

	$options              = chattermate_get_options();
	$options['widget_id'] = $widget_id;
	// Runs the sanitize callback and the save hooks, which check the widget.
	update_option( CHATTERMATE_OPTION, $options );

	chattermate_connect_redirect( 'connected' );
}
add_action( 'admin_post_chattermate_connect', 'chattermate_handle_connect_return' );

/**
 * Back from ChatterMate while logged out of WordPress: log in, then carry on.
 */
function chattermate_handle_connect_return_logged_out() {
	auth_redirect();
}
add_action( 'admin_post_nopriv_chattermate_connect', 'chattermate_handle_connect_return_logged_out' );

/**
 * Back to the ChatterMate screen with the outcome of a connection.
 *
 * @param string $outcome One of chattermate_connect_messages()'s keys.
 */
function chattermate_connect_redirect( $outcome ) {
	wp_safe_redirect( add_query_arg( 'chattermate_connect', $outcome, chattermate_admin_page_url() ) );
	exit;
}

/**
 * What each outcome says, and how it looks.
 *
 * @return array<string, array{0:string, 1:string}> Outcome => [ notice type, message ].
 */
function chattermate_connect_messages() {
	return array(
		'connected' => array( 'success', __( 'Connected. Your Widget ID is saved and the chat is live on your site.', 'chattermate-chat' ) ),
		'cancelled' => array( 'info', __( 'Connection cancelled. Nothing was changed.', 'chattermate-chat' ) ),
		'expired'   => array( 'warning', __( 'That connection link has expired or was already used. Click Connect with ChatterMate again.', 'chattermate-chat' ) ),
		'missing'   => array( 'warning', __( 'ChatterMate did not send a Widget ID. Click Connect with ChatterMate again, or paste the ID.', 'chattermate-chat' ) ),
	);
}

/**
 * The connection outcome notice on the ChatterMate screen, if one is due.
 */
function chattermate_render_connect_notice() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only; the action itself was verified.
	$outcome  = isset( $_GET['chattermate_connect'] ) ? sanitize_key( wp_unslash( $_GET['chattermate_connect'] ) ) : '';
	$messages = chattermate_connect_messages();
	if ( ! isset( $messages[ $outcome ] ) ) {
		return;
	}

	printf(
		'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
		esc_attr( $messages[ $outcome ][0] ),
		esc_html( $messages[ $outcome ][1] )
	);
}
