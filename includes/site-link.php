<?php
/**
 * The site's link with ChatterMate, made by one-click connect when the admin
 * ticks "Teach the AI my pages and products".
 *
 * Connect returns a one-time token. This site claims it from its own server with a
 * secret it makes here, and ChatterMate then reads the site's published pages,
 * posts and products through the public REST API. The secret signs everything
 * after: this site's "content changed" pings to ChatterMate, and ChatterMate's
 * order lookups to this site (see orders.php). It never leaves the server.
 *
 * Signature: hex HMAC-SHA256 of "<unix time>.<purpose>.<raw body>" with the secret,
 * in the X-ChatterMate-Signature header, the time in X-ChatterMate-Timestamp.
 *
 * @package ChatterMate
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CHATTERMATE_SITE_LINK_OPTION', 'chattermate_site_link' );
define( 'CHATTERMATE_CHANGE_EVENT', 'chattermate_content_changed' );
// Saves within this long make one ping: ChatterMate reads the whole site each time.
define( 'CHATTERMATE_CHANGE_DELAY', 2 * MINUTE_IN_SECONDS );
// How far a signed call's clock may be from ours.
define( 'CHATTERMATE_SIGNATURE_MAX_SKEW', 5 * MINUTE_IN_SECONDS );

/**
 * The link, or null when this site isn't linked.
 *
 * @return array{site_id:int, secret:string}|null
 */
function chattermate_site_link() {
	$link = get_option( CHATTERMATE_SITE_LINK_OPTION );
	if ( ! is_array( $link ) || empty( $link['site_id'] ) || empty( $link['secret'] ) ) {
		return null;
	}

	return array(
		'site_id' => (int) $link['site_id'],
		'secret'  => (string) $link['secret'],
	);
}

/**
 * Signature of a call.
 *
 * @param string $secret    Shared secret.
 * @param string $timestamp Unix time, as sent.
 * @param string $purpose   What the call is for.
 * @param string $body      Raw body.
 * @return string
 */
function chattermate_signature( $secret, $timestamp, $purpose, $body ) {
	return hash_hmac( 'sha256', $timestamp . '.' . $purpose . '.' . $body, $secret );
}

/**
 * Whether a REST request was signed by ChatterMate for this purpose, recently.
 *
 * @param WP_REST_Request $request Request.
 * @param string          $purpose What the call is for.
 * @return bool
 */
function chattermate_request_is_signed( $request, $purpose ) {
	$link      = chattermate_site_link();
	$timestamp = (string) $request->get_header( 'x_chattermate_timestamp' );
	$given     = (string) $request->get_header( 'x_chattermate_signature' );
	if ( ! $link || ! ctype_digit( $timestamp ) || '' === $given ) {
		return false;
	}
	if ( abs( time() - (int) $timestamp ) > CHATTERMATE_SIGNATURE_MAX_SKEW ) {
		return false;
	}

	return hash_equals( chattermate_signature( $link['secret'], $timestamp, $purpose, $request->get_body() ), $given );
}

/**
 * POST a signed JSON call to the ChatterMate API.
 *
 * @param string $path    Path under the API URL, starting with /.
 * @param string $purpose What the call is for.
 * @param array  $payload JSON body.
 * @param bool   $wait    Whether to wait for the answer.
 * @return array|WP_Error The response.
 */
function chattermate_signed_post( $path, $purpose, $payload, $wait = true ) {
	$link = chattermate_site_link();
	if ( ! $link ) {
		return new WP_Error( 'chattermate_not_linked', 'This site is not linked to ChatterMate.' );
	}
	$body      = wp_json_encode( $payload );
	$timestamp = (string) time();

	return wp_safe_remote_post(
		chattermate_api_url( chattermate_get_options() ) . $path,
		array(
			'timeout'    => 8,
			'blocking'   => $wait,
			'user-agent' => 'ChatterMate-WordPress/' . CHATTERMATE_VERSION,
			'headers'    => array(
				'Content-Type'            => 'application/json',
				'X-ChatterMate-Timestamp' => $timestamp,
				'X-ChatterMate-Signature' => chattermate_signature( $link['secret'], $timestamp, $purpose, $body ),
			),
			'body'       => $body,
		)
	);
}

/**
 * Claim a connect token: link this site, from this server, with a new secret.
 *
 * @param string $token One-time token from the connect page.
 * @return string What ChatterMate did about learning the site: queued, off,
 *                source_limit, or failed when the claim didn't go through.
 */
function chattermate_claim_site( $token ) {
	$secret  = wp_generate_password( 64, false, false );
	$payload = array(
		'token'          => $token,
		'site_url'       => home_url( '/' ),
		'rest_url'       => get_rest_url(),
		'secret'         => $secret,
		'woocommerce'    => chattermate_woocommerce_active(),
		'price_decimals' => function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2,
	);

	$response = wp_safe_remote_post(
		chattermate_api_url( chattermate_get_options() ) . '/enterprise/wordpress/sites/claim',
		array(
			'timeout'    => 15,
			'user-agent' => 'ChatterMate-WordPress/' . CHATTERMATE_VERSION,
			'headers'    => array( 'Content-Type' => 'application/json' ),
			'body'       => wp_json_encode( $payload ),
		)
	);
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return 'failed';
	}
	$answer = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $answer ) || empty( $answer['site_id'] ) ) {
		return 'failed';
	}

	update_option(
		CHATTERMATE_SITE_LINK_OPTION,
		array(
			'site_id' => (int) $answer['site_id'],
			'secret'  => $secret,
		),
		false
	);

	return isset( $answer['learning'] ) && is_string( $answer['learning'] ) ? $answer['learning'] : 'off';
}

/**
 * Something visitors can see was saved, published or unpublished: ask ChatterMate
 * to read the site again, once, a little later.
 *
 * @param string  $new_status New status.
 * @param string  $old_status Old status.
 * @param WP_Post $post       The post.
 */
function chattermate_note_content_change( $new_status, $old_status, $post ) {
	if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
		return;
	}
	if ( ! in_array( $post->post_type, array( 'page', 'post', 'product' ), true ) || wp_is_post_revision( $post ) ) {
		return;
	}
	chattermate_schedule_change_ping();
}
add_action( 'transition_post_status', 'chattermate_note_content_change', 10, 3 );

/**
 * A product's stock status changed (an order sold the last one, say).
 */
function chattermate_note_stock_change() {
	chattermate_schedule_change_ping();
}
add_action( 'woocommerce_product_set_stock_status', 'chattermate_note_stock_change' );
add_action( 'woocommerce_variation_set_stock_status', 'chattermate_note_stock_change' );

/**
 * One ping for the changes of the next few minutes.
 */
function chattermate_schedule_change_ping() {
	if ( chattermate_site_link() && ! wp_next_scheduled( CHATTERMATE_CHANGE_EVENT ) ) {
		wp_schedule_single_event( time() + CHATTERMATE_CHANGE_DELAY, CHATTERMATE_CHANGE_EVENT );
	}
}

/**
 * The scheduled ping.
 */
function chattermate_send_change_ping() {
	$link = chattermate_site_link();
	if ( $link ) {
		chattermate_signed_post(
			'/enterprise/wordpress/sites/' . $link['site_id'] . '/changed',
			'content_changed',
			array( 'url' => home_url( '/' ) ),
			false
		);
	}
}
add_action( CHATTERMATE_CHANGE_EVENT, 'chattermate_send_change_ping' );

/**
 * Unlink: tell ChatterMate (it stops reading the site and looking up orders), and
 * forget the secret. What ChatterMate learned stays until removed there.
 */
function chattermate_unlink_site() {
	$link = chattermate_site_link();
	if ( $link ) {
		chattermate_signed_post( '/enterprise/wordpress/sites/' . $link['site_id'] . '/disconnect', 'disconnect', array(), false );
	}
	delete_option( CHATTERMATE_SITE_LINK_OPTION );
	wp_clear_scheduled_hook( CHATTERMATE_CHANGE_EVENT );
}
