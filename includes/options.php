<?php
/**
 * Stored settings: defaults, reading and sanitizing.
 *
 * @package ChatterMate
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default value of every setting.
 *
 * @return array
 */
function chattermate_default_options() {
	return array(
		'widget_id'         => '',
		'visibility_mode'   => 'all',
		'visibility_pages'  => array(),
		'visibility_types'  => array(),
		'hide_for_admins'   => false,
		'hide_woo_checkout' => false,
		'load_timing'       => 'immediate',
		'share_context'     => true,
		'identify_users'    => false,
		'script_url'        => '',
		'api_url'           => '',
	);
}

/**
 * Where the chat may appear: every page, only matching pages, or all but matching pages.
 *
 * @return array Mode key => label.
 */
function chattermate_visibility_modes() {
	return array(
		'all'     => __( 'On every page', 'chattermate-chat' ),
		'only'    => __( 'Only on the pages I choose', 'chattermate-chat' ),
		'except'  => __( 'On every page except the ones I choose', 'chattermate-chat' ),
	);
}

/**
 * Whole groups of pages a visibility rule can match, beyond single pages.
 *
 * WooCommerce groups are offered only while WooCommerce is active.
 *
 * @return array Type key => label.
 */
function chattermate_content_types() {
	$types = array(
		'front_page' => __( 'Homepage', 'chattermate-chat' ),
		'post'       => __( 'All blog posts', 'chattermate-chat' ),
	);

	if ( chattermate_woocommerce_active() ) {
		$types['product'] = __( 'All product pages', 'chattermate-chat' );
		$types['shop']    = __( 'Shop page', 'chattermate-chat' );
	}

	return $types;
}

/**
 * When the widget script loads on the front end.
 *
 * @return array Timing key => label.
 */
function chattermate_load_timings() {
	return array(
		'immediate'   => __( 'As soon as the page loads', 'chattermate-chat' ),
		'interaction' => __( 'When the visitor first scrolls, taps, moves the mouse or types', 'chattermate-chat' ),
	);
}

/**
 * Whether WooCommerce is active.
 *
 * @return bool
 */
function chattermate_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * The saved settings merged with defaults.
 *
 * @return array
 */
function chattermate_get_options() {
	$options = get_option( CHATTERMATE_OPTION, array() );
	if ( ! is_array( $options ) ) {
		$options = array();
	}

	// 1.0.0 stored the hosted script URL itself; empty now means "use the default".
	if ( isset( $options['script_url'] ) && CHATTERMATE_DEFAULT_SCRIPT_URL === $options['script_url'] ) {
		$options['script_url'] = '';
	}

	return wp_parse_args( $options, chattermate_default_options() );
}

/**
 * The widget loader URL: the self-hosted override, else the hosted default.
 *
 * @param array $options Settings from chattermate_get_options().
 * @return string
 */
function chattermate_script_url( $options ) {
	return '' !== $options['script_url'] ? $options['script_url'] : CHATTERMATE_DEFAULT_SCRIPT_URL;
}

/**
 * The ChatterMate API base URL, without a trailing slash.
 *
 * @param array $options Settings from chattermate_get_options().
 * @return string
 */
function chattermate_api_url( $options ) {
	$url = '' !== $options['api_url'] ? $options['api_url'] : CHATTERMATE_DEFAULT_API_URL;

	return untrailingslashit( $url );
}

/**
 * The ChatterMate app origin: the self-hosted loader's origin, else the hosted app.
 *
 * @param array $options Settings from chattermate_get_options().
 * @return string
 */
function chattermate_app_url( $options ) {
	if ( '' === $options['script_url'] ) {
		return CHATTERMATE_APP_URL;
	}

	$scheme = wp_parse_url( $options['script_url'], PHP_URL_SCHEME );
	$host   = wp_parse_url( $options['script_url'], PHP_URL_HOST );
	$port   = wp_parse_url( $options['script_url'], PHP_URL_PORT );
	if ( ! $scheme || ! $host ) {
		return CHATTERMATE_APP_URL;
	}

	return $scheme . '://' . $host . ( $port ? ':' . $port : '' );
}

/**
 * URL of the plugin's admin screen.
 *
 * @return string
 */
function chattermate_admin_page_url() {
	return admin_url( 'admin.php?page=' . CHATTERMATE_PAGE );
}

/**
 * Sanitize the submitted settings before they are stored.
 *
 * @param mixed $input Raw option input from the settings form.
 * @return array
 */
function chattermate_sanitize_options( $input ) {
	$clean = chattermate_default_options();
	if ( ! is_array( $input ) ) {
		return $clean;
	}

	$clean['widget_id'] = chattermate_sanitize_widget_id( isset( $input['widget_id'] ) ? $input['widget_id'] : '' );

	$mode                     = isset( $input['visibility_mode'] ) ? sanitize_key( $input['visibility_mode'] ) : 'all';
	$clean['visibility_mode'] = array_key_exists( $mode, chattermate_visibility_modes() ) ? $mode : 'all';

	$clean['visibility_pages'] = chattermate_sanitize_page_ids( isset( $input['visibility_pages'] ) ? $input['visibility_pages'] : array() );

	// Keep a WooCommerce group that is saved while WooCommerce is inactive, so it
	// still applies when WooCommerce comes back.
	$known_types = array( 'front_page', 'post', 'product', 'shop' );
	$types       = isset( $input['visibility_types'] ) ? (array) $input['visibility_types'] : array();
	$types       = array_map( 'sanitize_key', $types );

	$clean['visibility_types'] = array_values( array_intersect( $known_types, $types ) );

	$clean['hide_for_admins']   = ! empty( $input['hide_for_admins'] );
	$clean['hide_woo_checkout'] = ! empty( $input['hide_woo_checkout'] );

	$timing               = isset( $input['load_timing'] ) ? sanitize_key( $input['load_timing'] ) : 'immediate';
	$clean['load_timing'] = array_key_exists( $timing, chattermate_load_timings() ) ? $timing : 'immediate';

	$clean['share_context']  = ! empty( $input['share_context'] );
	$clean['identify_users'] = ! empty( $input['identify_users'] );
	chattermate_save_api_key( $input );

	if ( chattermate_can_edit_urls() ) {
		$clean['script_url'] = chattermate_sanitize_url_override( isset( $input['script_url'] ) ? $input['script_url'] : '', CHATTERMATE_DEFAULT_SCRIPT_URL );
		$clean['api_url']    = chattermate_sanitize_url_override( isset( $input['api_url'] ) ? $input['api_url'] : '', CHATTERMATE_DEFAULT_API_URL );
	} else {
		$stored              = chattermate_get_options();
		$clean['script_url'] = $stored['script_url'];
		$clean['api_url']    = $stored['api_url'];
	}

	if ( 'only' === $clean['visibility_mode'] && empty( $clean['visibility_pages'] ) && empty( $clean['visibility_types'] ) ) {
		add_settings_error(
			CHATTERMATE_OPTION,
			'chattermate_only_without_pages',
			__( 'The chat is set to show only on chosen pages, but none are chosen, so it shows nowhere. Choose pages or groups, or pick "On every page".', 'chattermate-chat' ),
			'warning'
		);
	}

	return $clean;
}

/**
 * Whether the current user may change the script and API URLs.
 *
 * They decide which script runs on every page, so they need the same right as
 * adding scripts to content: on a network, only super admins have it.
 *
 * @return bool
 */
function chattermate_can_edit_urls() {
	return current_user_can( 'unfiltered_html' );
}

/**
 * Keep only the characters a ChatterMate Widget ID can contain.
 *
 * @param mixed $raw Submitted value.
 * @return string
 */
function chattermate_sanitize_widget_id( $raw ) {
	$raw   = trim( sanitize_text_field( (string) $raw ) );
	$clean = substr( preg_replace( '/[^A-Za-z0-9-]/', '', $raw ), 0, 64 );

	if ( $clean !== $raw && '' !== $raw ) {
		add_settings_error(
			CHATTERMATE_OPTION,
			'chattermate_widget_id_cleaned',
			__( 'The Widget ID had characters a Widget ID never contains, so they were removed. Check it against your ChatterMate dashboard.', 'chattermate-chat' ),
			'warning'
		);
	}

	return $clean;
}

/**
 * Keep only IDs of published pages.
 *
 * @param mixed $raw Submitted page IDs.
 * @return int[]
 */
function chattermate_sanitize_page_ids( $raw ) {
	$ids = array_unique( array_filter( array_map( 'absint', (array) $raw ) ) );

	$pages = array();
	foreach ( $ids as $id ) {
		if ( 'page' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
			$pages[] = $id;
		}
	}

	return $pages;
}

/**
 * Sanitize a self-hosted URL override. Empty or equal to the default means "use the default".
 *
 * @param mixed  $raw     Submitted value.
 * @param string $default The hosted default URL.
 * @return string
 */
function chattermate_sanitize_url_override( $raw, $default ) {
	$url = esc_url_raw( trim( (string) $raw ), array( 'http', 'https' ) );
	if ( '' === $url || untrailingslashit( $url ) === untrailingslashit( $default ) ) {
		return '';
	}

	return $url;
}
