<?php
/**
 * Signed-in customers: visitors who are logged in to WordPress chat under their
 * own name and email instead of as anonymous visitors.
 *
 * The site exchanges its ChatterMate Widget app API key for a short-lived signed
 * token per user (POST {api}/generate-token). The key never reaches the browser;
 * only the user's own token does.
 *
 * @package ChatterMate
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CHATTERMATE_API_KEY_OPTION', 'chattermate_api_key' );
define( 'CHATTERMATE_REQUIRES_TOKEN_OPTION', 'chattermate_requires_token' );
define( 'CHATTERMATE_TOKEN_META', 'chattermate_token' );

// A token lives 12 hours; a fresh one is fetched once less than 10 minutes remain.
define( 'CHATTERMATE_TOKEN_TTL', 12 * HOUR_IN_SECONDS );
define( 'CHATTERMATE_TOKEN_MARGIN', 10 * MINUTE_IN_SECONDS );
// After a failed exchange, wait this long before asking again, so an outage
// never slows every page a signed-in user opens.
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
 * Store, keep or remove the API key from a settings submission.
 *
 * Called from the settings sanitize callback. The key is kept in its own option,
 * not autoloaded, and never printed back into the form.
 *
 * @param array $input Raw settings input.
 */
function chattermate_save_api_key( $input ) {
	if ( ! empty( $input['api_key_remove'] ) ) {
		delete_option( CHATTERMATE_API_KEY_OPTION );
		chattermate_forget_tokens();
		return;
	}

	$key = isset( $input['api_key'] ) ? trim( sanitize_text_field( (string) $input['api_key'] ) ) : '';
	if ( '' === $key || chattermate_api_key() === $key ) {
		return;
	}

	update_option( CHATTERMATE_API_KEY_OPTION, $key, false );
	chattermate_forget_tokens();
}

/**
 * Drop every cached visitor token, e.g. after the key or Widget ID changes.
 */
function chattermate_forget_tokens() {
	delete_metadata( 'user', 0, CHATTERMATE_TOKEN_META, '', true );
}

/**
 * The current visitor's token, or '' when they are not signed in, the feature is
 * off, or ChatterMate could not issue one.
 *
 * @param array $options Settings from chattermate_get_options().
 * @return string
 */
function chattermate_visitor_token( $options ) {
	if ( ! chattermate_identity_ready( $options ) || ! is_user_logged_in() ) {
		return '';
	}

	$user = wp_get_current_user();
	if ( ! $user->exists() || ! is_email( $user->user_email ) ) {
		return '';
	}

	$cached = get_user_meta( $user->ID, CHATTERMATE_TOKEN_META, true );
	if ( chattermate_token_cache_matches( $cached, $options['widget_id'], $user->user_email ) ) {
		if ( '' !== $cached['token'] && $cached['expires'] > time() + CHATTERMATE_TOKEN_MARGIN ) {
			return $cached['token'];
		}
		if ( '' === $cached['token'] && $cached['retry_after'] > time() ) {
			return '';
		}
	}

	$issued = chattermate_request_token( $options, $user );
	update_user_meta(
		$user->ID,
		CHATTERMATE_TOKEN_META,
		array(
			'widget_id'   => $options['widget_id'],
			'email'       => $user->user_email,
			'token'       => $issued['token'],
			'expires'     => $issued['expires'],
			'retry_after' => '' === $issued['token'] ? time() + CHATTERMATE_TOKEN_RETRY : 0,
		)
	);

	return $issued['token'];
}

/**
 * Whether a cached token belongs to this widget and this email address.
 *
 * @param mixed  $cached    Stored user meta.
 * @param string $widget_id Widget ID.
 * @param string $email     The user's email now.
 * @return bool
 */
function chattermate_token_cache_matches( $cached, $widget_id, $email ) {
	return is_array( $cached )
		&& isset( $cached['widget_id'], $cached['email'], $cached['token'], $cached['expires'], $cached['retry_after'] )
		&& $cached['widget_id'] === $widget_id
		&& $cached['email'] === $email;
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
			'timeout'    => 3,
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
	if ( is_wp_error( $response ) || 201 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return $failed;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $body['data']['token'] ) || ! is_string( $body['data']['token'] ) ) {
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
 * JavaScript that hands the widget this visitor's identity.
 *
 * Signed in: the token, plus a marker that this browser was identified.
 * Not signed in: if this browser was identified earlier (a shared computer after
 * logout), forget the widget's stored token so the next person starts anonymous.
 *
 * @param array $options Settings.
 * @return string JavaScript, or '' when the feature is off.
 */
function chattermate_identity_js( $options ) {
	if ( ! chattermate_identity_ready( $options ) ) {
		return '';
	}

	$token = chattermate_visitor_token( $options );
	if ( '' !== $token ) {
		return 'window.chattermateToken=' . wp_json_encode( $token ) . ';try{localStorage.setItem("chattermate_wp_identified","1")}catch(e){}';
	}

	return 'try{if(localStorage.getItem("chattermate_wp_identified")){localStorage.removeItem("ctid");localStorage.removeItem("chattermate_wp_identified")}}catch(e){}';
}

/**
 * Whether this visitor can chat with a widget that only takes signed tokens.
 *
 * @param array $options Settings.
 * @return bool
 */
function chattermate_visitor_can_use_widget( $options ) {
	if ( ! get_option( CHATTERMATE_REQUIRES_TOKEN_OPTION ) ) {
		return true;
	}

	return '' !== chattermate_visitor_token( $options );
}
