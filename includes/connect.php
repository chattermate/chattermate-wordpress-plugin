<?php
/**
 * One-click connect: the admin picks an agent in ChatterMate, which sends them
 * back here with its Widget ID, so nobody copies and pastes it.
 *
 * Out: {app}/connect/wordpress?return_url=<admin-post.php?action=chattermate_connect>
 * &state=<nonce>&site_name=<site name>. Back: the same admin-post URL with
 * widget_id and state, or error=cancelled. The nonce ties the return to the admin
 * who started it, so a forged link changes nothing.
 *
 * @package ChatterMate
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The ChatterMate page that starts a connection.
 *
 * @param array $options Settings from chattermate_get_options().
 * @return string
 */
function chattermate_connect_url( $options ) {
	return add_query_arg(
		array(
			'return_url' => rawurlencode( admin_url( 'admin-post.php?action=chattermate_connect' ) ),
			'state'      => wp_create_nonce( 'chattermate_connect' ),
			'site_name'  => rawurlencode( wp_strip_all_tags( get_bloginfo( 'name' ) ) ),
		),
		chattermate_app_url( $options ) . '/connect/wordpress'
	);
}

/**
 * The return from ChatterMate: save the chosen Widget ID and show the result.
 */
function chattermate_handle_connect_return() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'chattermate-chat' ), '', array( 'response' => 403 ) );
	}

	$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
	if ( ! wp_verify_nonce( $state, 'chattermate_connect' ) ) {
		chattermate_connect_redirect( 'expired' );
	}

	if ( isset( $_GET['error'] ) ) {
		chattermate_connect_redirect( 'cancelled' );
	}

	$widget_id = chattermate_sanitize_widget_id( isset( $_GET['widget_id'] ) ? wp_unslash( $_GET['widget_id'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by chattermate_sanitize_widget_id().
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
		'expired'   => array( 'warning', __( 'That connection link has expired. Click Connect with ChatterMate again.', 'chattermate-chat' ) ),
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
