<?php
/**
 * Signed-in customers: visitors who are logged in to WordPress chat under their
 * own name and email instead of as anonymous visitors.
 *
 * The site swaps its ChatterMate Widget app API key for a signed token per user
 * (POST {api}/generate-token). The key never leaves the server. The token is never
 * printed into a page, which a cache might serve to someone else: the browser asks
 * admin-ajax.php for it (no-store), and the widget asks again when it runs out.
 *
 * @package ChatterMate
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CHATTERMATE_API_KEY_OPTION', 'chattermate_api_key' );
define( 'CHATTERMATE_REQUIRES_TOKEN_OPTION', 'chattermate_requires_token' );
// Per site: stored with update_user_option(), so it carries the site's table prefix.
define( 'CHATTERMATE_TOKEN_META', 'chattermate_token' );
// Set for an hour when ChatterMate refuses the key, so no user retries meanwhile.
define( 'CHATTERMATE_KEY_REJECTED_TRANSIENT', 'chattermate_key_rejected' );

// Tokens live a day and are renewed once less than half of that remains, so a
// tab left open never runs out mid-chat.
define( 'CHATTERMATE_TOKEN_TTL', DAY_IN_SECONDS );
define( 'CHATTERMATE_TOKEN_RENEW', 12 * HOUR_IN_SECONDS );
// After a failure that isn't the key's fault (an outage), wait before asking again.
define( 'CHATTERMATE_TOKEN_RETRY', 10 * MINUTE_IN_SECONDS );

/**
 * The saved Widget app API key, or ''.
 *
 * @return string
 */
function chattermate_api_key() {
	return (string) get_option( CHATTERMATE_API_KEY_OPTION, '' );
}

/**
 * Whether signed-in customers are switched on and can work.
 *
 * @param array $options Settings from chattermate_get_options().
 * @return bool
 */
function chattermate_identity_ready( $options ) {
	return $options['identify_users'] && '' !== chattermate_api_key();
}

/**
 * Whether this visitor should chat as themselves.
 *
 * @param array $options Settings.
 * @return bool
 */
function chattermate_identity_for_visitor( $options ) {
	return chattermate_identity_ready( $options ) && is_user_logged_in() && ! get_transient( CHATTERMATE_KEY_REJECTED_TRANSIENT );
}

/**
 * Store, keep or remove the API key from a settings submission.
 *
 * Called from the settings sanitize callback. The key is kept in its own option,
 * not autoloaded, and never printed back into the form. A newly pasted key wins
 * over "remove".
 *
 * @param array $input Raw settings input.
 */
function chattermate_save_api_key( $input ) {
	$key = isset( $input['api_key'] ) ? trim( sanitize_text_field( (string) $input['api_key'] ) ) : '';

	if ( '' !== $key && chattermate_api_key() !== $key ) {
		update_option( CHATTERMATE_API_KEY_OPTION, $key, false );
		delete_transient( CHATTERMATE_KEY_REJECTED_TRANSIENT );
		return;
	}

	if ( '' === $key && ! empty( $input['api_key_remove'] ) ) {
		delete_option( CHATTERMATE_API_KEY_OPTION );
		delete_transient( CHATTERMATE_KEY_REJECTED_TRANSIENT );
	}
}

/**
 * The current user's token, or '' when ChatterMate could not issue one.
 *
 * @param array $options Settings from chattermate_get_options().
 * @return string
 */
function chattermate_current_user_token( $options ) {
	if ( ! chattermate_identity_for_visitor( $options ) || get_transient( CHATTERMATE_KEY_REJECTED_TRANSIENT ) ) {
		return '';
	}

	$user = wp_get_current_user();
	if ( ! $user->exists() || ! is_email( $user->user_email ) ) {
		return '';
	}

	$owner  = chattermate_token_owner( $options, $user );
	$cached = get_user_option( CHATTERMATE_TOKEN_META, $user->ID );
	if ( is_array( $cached ) && isset( $cached['owner'], $cached['token'], $cached['expires'], $cached['retry_after'] ) && $cached['owner'] === $owner ) {
		if ( '' !== $cached['token'] && $cached['expires'] > time() + CHATTERMATE_TOKEN_RENEW ) {
			return $cached['token'];
		}
		if ( '' === $cached['token'] && $cached['retry_after'] > time() ) {
			return '';
		}
	}

	$issued = chattermate_request_token( $options, $user );
	update_user_option(
		$user->ID,
		CHATTERMATE_TOKEN_META,
		array(
			'owner'       => $owner,
			'token'       => $issued['token'],
			'expires'     => $issued['expires'],
			'retry_after' => '' === $issued['token'] ? time() + CHATTERMATE_TOKEN_RETRY : 0,
		)
	);

	return $issued['token'];
}

/**
 * What a cached token was issued for: a change to any part means a new token.
 *
 * @param array   $options Settings.
 * @param WP_User $user    The user.
 * @return string
 */
function chattermate_token_owner( $options, $user ) {
	return md5( implode( '|', array( chattermate_api_url( $options ), chattermate_api_key(), $options['widget_id'], $user->user_email, chattermate_user_display_name( $user ) ) ) );
}

/**
 * Ask ChatterMate for a token naming this user.
 *
 * @param array   $options Settings.
 * @param WP_User $user    The signed-in user.
 * @return array{token:string, expires:int}
 */
function chattermate_request_token( $options, $user ) {
	$failed = array(
		'token'   => '',
		'expires' => 0,
	);

	$response = wp_safe_remote_post(
		chattermate_api_url( $options ) . '/generate-token',
		array(
			'timeout'    => 5,
			'user-agent' => 'ChatterMate-WordPress/' . CHATTERMATE_VERSION,
			'headers'    => array(
				'Authorization' => 'Bearer ' . chattermate_api_key(),
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
			'body'       => wp_json_encode(
				array(
					'widget_id'      => $options['widget_id'],
					'customer_email' => $user->user_email,
					'customer_name'  => chattermate_user_display_name( $user ),
					'ttl_seconds'    => CHATTERMATE_TOKEN_TTL,
				)
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		return $failed;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	// 404 "Widget '...' not found" means the key is for another organization; a
	// bare "Not Found" is a wrong API URL, which is not the key's fault.
	$wrong_org = 404 === $code && is_array( $body ) && isset( $body['detail'] ) && 0 === strpos( (string) $body['detail'], 'Widget ' );
	if ( 401 === $code || 403 === $code || $wrong_org ) {
		// The key is wrong, revoked or for another organization's widget: stop
		// every user from retrying, and tell the admin on the settings screen.
		set_transient( CHATTERMATE_KEY_REJECTED_TRANSIENT, $code, HOUR_IN_SECONDS );
		return $failed;
	}

	if ( 201 !== $code || empty( $body['data']['token'] ) || ! is_string( $body['data']['token'] ) ) {
		return $failed;
	}

	$lifetime = isset( $body['data']['expires_in'] ) ? absint( $body['data']['expires_in'] ) : CHATTERMATE_TOKEN_TTL;

	return array(
		'token'   => $body['data']['token'],
		'expires' => time() + $lifetime,
	);
}

/**
 * The name the team sees for this user: first and last name, else the display name.
 *
 * @param WP_User $user The user.
 * @return string
 */
function chattermate_user_display_name( $user ) {
	$name = trim( $user->first_name . ' ' . $user->last_name );

	return '' !== $name ? $name : (string) $user->display_name;
}

/**
 * admin-ajax.php?action=chattermate_token: the signed-in user's own token.
 *
 * Logged-in users only (there is no nopriv handler), never cached.
 */
function chattermate_ajax_token() {
	nocache_headers();
	check_ajax_referer( 'chattermate_token', 'nonce' );

	// Each answer carries the nonce for the next request, so a tab left open
	// long enough for the page's nonce to lapse can still renew its token.
	$next  = wp_create_nonce( 'chattermate_token' );
	$token = chattermate_current_user_token( chattermate_get_options() );
	if ( '' === $token ) {
		wp_send_json_error( array( 'nonce' => $next ), 503 );
	}

	wp_send_json_success(
		array(
			'token' => $token,
			'nonce' => $next,
		)
	);
}
add_action( 'wp_ajax_chattermate_token', 'chattermate_ajax_token' );

/**
 * Where the browser asks for the user's token, for chat.js and the live preview.
 *
 * @param array $options Settings.
 * @return array|null Null when this visitor doesn't chat as themselves.
 */
function chattermate_identity_config( $options ) {
	if ( ! chattermate_identity_for_visitor( $options ) ) {
		return null;
	}

	return array(
		'url'   => esc_url_raw( admin_url( 'admin-ajax.php?action=chattermate_token' ) ),
		'nonce' => wp_create_nonce( 'chattermate_token' ),
	);
}

/**
 * JavaScript, run before the widget, that forgets a signed-in customer's stored
 * token in a browser where they no longer are (a shared computer after logout,
 * someone else logged in, or the feature since turned off), so nobody chats as
 * the previous person. Printed for every visitor who doesn't chat as themselves.
 *
 * @param array $options Settings.
 * @return string JavaScript, or '' when chat.js handles identity.
 */
function chattermate_signed_out_js( $options ) {
	if ( chattermate_identity_for_visitor( $options ) ) {
		return '';
	}

	return 'try{if(localStorage.getItem("chattermate_wp_identified")){localStorage.removeItem("ctid");localStorage.removeItem("chattermate_wp_identified")}}catch(e){}';
}

/**
 * Whether this visitor can chat with a widget that only takes signed tokens.
 * Decided without calling ChatterMate, so pages never wait on it.
 *
 * @param array $options Settings.
 * @return bool
 */
function chattermate_visitor_can_use_widget( $options ) {
	if ( ! get_option( CHATTERMATE_REQUIRES_TOKEN_OPTION ) ) {
		return true;
	}

	return chattermate_identity_for_visitor( $options );
}
