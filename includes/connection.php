<?php
/**
 * Checking a Widget ID against the ChatterMate API.
 *
 * The check runs only when an administrator saves the settings or clicks
 * "Check again" — never on page views. The result is cached in a transient.
 *
 * @package ChatterMate
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ask the ChatterMate API about a widget and map the answer to a status.
 *
 * Statuses: connected, needs_token, not_found, no_agent, unreachable.
 *
 * Uses the read-only GET /widgets/{id}/status. An API that predates it answers
 * that route with FastAPI's bare "Not Found"; only then is GET /widgets/{id}
 * asked instead (which, unlike /status, starts an anonymous visitor).
 *
 * @param string $widget_id Widget ID.
 * @param string $api_url   API base URL without a trailing slash.
 * @return array{status:string, agent_name:string}
 */
function chattermate_request_widget_status( $widget_id, $api_url ) {
	$base     = $api_url . '/widgets/' . rawurlencode( $widget_id );
	$response = chattermate_api_get( $base . '/status' );
	if ( 404 === $response['code'] && 'Not Found' === $response['detail'] ) {
		return chattermate_read_legacy_widget( chattermate_api_get( $base ), $widget_id );
	}

	return chattermate_read_widget_status( $response, $widget_id );
}

/**
 * GET a ChatterMate API URL and decode the JSON answer.
 *
 * wp_safe_remote_get() refuses private and loopback addresses, so an API URL
 * set under Advanced can't be used to probe the server's own network.
 *
 * @param string $url Absolute URL.
 * @return array{code:int, body:array, detail:string} code 0 when the request failed.
 */
function chattermate_api_get( $url ) {
	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'    => 8,
			'user-agent' => 'ChatterMate-WordPress/' . CHATTERMATE_VERSION,
			'headers'    => array( 'Accept' => 'application/json' ),
		)
	);
	if ( is_wp_error( $response ) ) {
		return array(
			'code'   => 0,
			'body'   => array(),
			'detail' => '',
		);
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $body ) ) {
		$body = array();
	}

	return array(
		'code'   => (int) wp_remote_retrieve_response_code( $response ),
		'body'   => $body,
		'detail' => isset( $body['detail'] ) && is_string( $body['detail'] ) ? $body['detail'] : '',
	);
}

/**
 * Map a GET /widgets/{id}/status answer. A 200 counts only when it is about this
 * widget: a site or app that answers any path with 200 is not ChatterMate.
 *
 * @param array  $response  From chattermate_api_get().
 * @param string $widget_id The widget asked about.
 * @return array{status:string, agent_name:string}
 */
function chattermate_read_widget_status( $response, $widget_id ) {
	if ( 200 === $response['code'] && isset( $response['body']['widget_id'] ) && $widget_id === $response['body']['widget_id'] ) {
		$name = isset( $response['body']['agent_name'] ) ? sanitize_text_field( (string) $response['body']['agent_name'] ) : '';

		return array(
			'status'     => empty( $response['body']['requires_token'] ) ? 'connected' : 'needs_token',
			'agent_name' => $name,
		);
	}

	return chattermate_read_missing( $response );
}

/**
 * Map a GET /widgets/{id} answer, from an API without /status.
 *
 * @param array  $response  From chattermate_api_get().
 * @param string $widget_id The widget asked about.
 * @return array{status:string, agent_name:string}
 */
function chattermate_read_legacy_widget( $response, $widget_id ) {
	if ( 200 === $response['code'] && isset( $response['body']['id'] ) && $widget_id === $response['body']['id'] ) {
		return array(
			'status'     => 'connected',
			'agent_name' => chattermate_agent_name( $response['body'] ),
		);
	}

	if ( 401 === $response['code'] ) {
		// "Unauthorized" is an email gate: the widget works and asks for an email.
		// "Unauthorized - Token required" only chats with visitors the site signs in.
		return array(
			'status'     => 'Unauthorized - Token required' === $response['detail'] ? 'needs_token' : 'connected',
			'agent_name' => '',
		);
	}

	return chattermate_read_missing( $response );
}

/**
 * Map a refusal shared by both routes: an unknown widget, a widget with no agent,
 * or anything else (an outage, a proxy page) as unreachable.
 *
 * @param array $response From chattermate_api_get().
 * @return array{status:string, agent_name:string}
 */
function chattermate_read_missing( $response ) {
	$status = 'unreachable';
	if ( 404 === $response['code'] && 'Agent not found' === $response['detail'] ) {
		$status = 'no_agent';
	} elseif ( 404 === $response['code'] && 'Widget not found' === $response['detail'] ) {
		$status = 'not_found';
	}

	return array(
		'status'     => $status,
		'agent_name' => '',
	);
}

/**
 * The agent's visitor-facing name from a GET /widgets/{id} answer.
 *
 * @param array $body Decoded response body.
 * @return string
 */
function chattermate_agent_name( $body ) {
	if ( empty( $body['agent'] ) || ! is_array( $body['agent'] ) ) {
		return '';
	}

	$agent = $body['agent'];
	$name  = ! empty( $agent['display_name'] ) ? $agent['display_name'] : ( isset( $agent['name'] ) ? $agent['name'] : '' );

	return sanitize_text_field( (string) $name );
}

/**
 * Check the saved Widget ID now and cache the result.
 *
 * @return array|null The stored result, or null when no Widget ID is saved.
 */
function chattermate_refresh_connection() {
	$options = chattermate_get_options();
	if ( '' === $options['widget_id'] ) {
		delete_transient( CHATTERMATE_CONNECTION_TRANSIENT );
		delete_option( CHATTERMATE_REQUIRES_TOKEN_OPTION );
		return null;
	}

	$api_url = chattermate_api_url( $options );
	$result  = chattermate_api_known( $options )
		? chattermate_request_widget_status( $options['widget_id'], $api_url )
		: array(
			'status'     => 'needs_api_url',
			'agent_name' => '',
		);

	$result['widget_id']  = $options['widget_id'];
	$result['api_url']    = $api_url;
	$result['checked_at'] = time();

	set_transient( CHATTERMATE_CONNECTION_TRANSIENT, $result, MONTH_IN_SECONDS );

	// Read on every page view, so kept as a small autoloaded flag rather than
	// reading the transient there.
	// An unreachable or unknown API says nothing about the widget: keep what was known.
	if ( ! in_array( $result['status'], array( 'unreachable', 'needs_api_url' ), true ) ) {
		update_option( CHATTERMATE_REQUIRES_TOKEN_OPTION, 'needs_token' === $result['status'] ? 1 : 0, true );
	}

	return $result;
}

/**
 * The cached check for the saved Widget ID, if there is one.
 *
 * @param array $options Settings from chattermate_get_options().
 * @return array|null Null when the saved ID has not been checked.
 */
function chattermate_get_connection( $options ) {
	$cached = get_transient( CHATTERMATE_CONNECTION_TRANSIENT );
	if ( ! is_array( $cached ) || empty( $cached['widget_id'] ) ) {
		return null;
	}

	if ( $cached['widget_id'] !== $options['widget_id'] || $cached['api_url'] !== chattermate_api_url( $options ) ) {
		return null;
	}

	return $cached;
}

/**
 * After the settings are saved: re-check the widget and settle the setup notice.
 */
function chattermate_on_options_saved() {
	// A new widget, API or key gets a fresh try at signed-in tokens.
	delete_transient( CHATTERMATE_KEY_REJECTED_TRANSIENT );

	$options = chattermate_get_options();
	if ( '' !== $options['widget_id'] ) {
		delete_option( CHATTERMATE_SETUP_FLAG );
	}

	chattermate_refresh_connection();
}
add_action( 'add_option_' . CHATTERMATE_OPTION, 'chattermate_on_options_saved' );
add_action( 'update_option_' . CHATTERMATE_OPTION, 'chattermate_on_options_saved' );

/**
 * Saving unchanged settings fires no update hook, so re-check here instead.
 *
 * @param mixed $value     New value.
 * @param mixed $old_value Stored value.
 * @return mixed The new value, unchanged.
 */
function chattermate_on_unchanged_save( $value, $old_value ) {
	if ( $value === $old_value ) {
		// Saving again is how an admin retries: a rejected key gets a fresh try too.
		delete_transient( CHATTERMATE_KEY_REJECTED_TRANSIENT );
		chattermate_refresh_connection();
	}

	return $value;
}
add_filter( 'pre_update_option_' . CHATTERMATE_OPTION, 'chattermate_on_unchanged_save', 10, 2 );
