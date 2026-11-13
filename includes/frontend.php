<?php
/**
 * Loading the widget on the front end, subject to the visibility rules.
 *
 * @package ChatterMate
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the widget should load on the current front-end request.
 *
 * @param array $options Settings from chattermate_get_options().
 * @return bool
 */
function chattermate_should_load_widget( $options ) {
	$load = chattermate_passes_rules( $options );

	/**
	 * Filters whether the ChatterMate widget loads on the current request.
	 *
	 * @param bool  $load    Whether the widget loads.
	 * @param array $options The plugin settings.
	 */
	return (bool) apply_filters( 'chattermate_should_load_widget', $load, $options );
}

/**
 * Apply the saved rules, in order: Widget ID, administrators, checkout, page rules.
 *
 * @param array $options Settings from chattermate_get_options().
 * @return bool
 */
function chattermate_passes_rules( $options ) {
	if ( '' === $options['widget_id'] ) {
		return false;
	}

	if ( $options['hide_for_admins'] && current_user_can( 'manage_options' ) ) {
		return false;
	}

	if ( $options['hide_woo_checkout'] && chattermate_is_woo_cart_or_checkout() ) {
		return false;
	}

	if ( 'only' === $options['visibility_mode'] ) {
		return chattermate_request_matches_rules( $options );
	}

	if ( 'except' === $options['visibility_mode'] ) {
		return ! chattermate_request_matches_rules( $options );
	}

	return true;
}

/**
 * Whether this request is the WooCommerce cart or checkout.
 *
 * @return bool
 */
function chattermate_is_woo_cart_or_checkout() {
	if ( ! chattermate_woocommerce_active() ) {
		return false;
	}

	return ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() );
}

/**
 * Whether this request matches a chosen page or page group.
 *
 * @param array $options Settings from chattermate_get_options().
 * @return bool
 */
function chattermate_request_matches_rules( $options ) {
	$pages = array_map( 'absint', (array) $options['visibility_pages'] );
	if ( $pages && ( is_page( $pages ) || in_array( chattermate_archive_page_id(), $pages, true ) ) ) {
		return true;
	}

	foreach ( (array) $options['visibility_types'] as $type ) {
		if ( chattermate_request_is_type( $type ) ) {
			return true;
		}
	}

	return false;
}

/**
 * The page standing for this archive: the posts page on the blog index, the shop
 * page on the WooCommerce shop. is_page() is false on both, though both are pages
 * a site owner can pick.
 *
 * @return int Page ID, or 0.
 */
function chattermate_archive_page_id() {
	if ( is_home() && ! is_front_page() ) {
		return (int) get_option( 'page_for_posts' );
	}

	if ( function_exists( 'is_shop' ) && is_shop() && function_exists( 'wc_get_page_id' ) ) {
		return max( 0, (int) wc_get_page_id( 'shop' ) );
	}

	return 0;
}

/**
 * Whether this request belongs to a page group.
 *
 * @param string $type Group key from chattermate_content_types().
 * @return bool
 */
function chattermate_request_is_type( $type ) {
	switch ( $type ) {
		case 'front_page':
			return is_front_page();
		case 'post':
			return is_singular( 'post' );
		case 'product':
			return function_exists( 'is_product' ) && is_product();
		case 'shop':
			return function_exists( 'is_shop' ) && is_shop();
	}

	return false;
}

/**
 * The inline globals the widget reads: its ID and, for self-hosters, the API URL.
 *
 * @param array $options Settings from chattermate_get_options().
 * @return string JavaScript.
 */
function chattermate_widget_globals( $options ) {
	$js = 'window.chattermateId=' . wp_json_encode( $options['widget_id'] ) . ';';
	if ( '' !== $options['api_url'] ) {
		$js .= 'window.chattermateBaseUrl=' . wp_json_encode( chattermate_api_url( $options ) ) . ';';
	}

	return $js;
}

/**
 * Load the widget on the front end.
 *
 * Immediate: the hosted loader is enqueued in the footer, async.
 * On interaction: a small local script adds the hosted loader on the visitor's
 * first scroll, tap, mouse move or key press, which keeps it out of page-speed tests.
 */
function chattermate_enqueue_widget() {
	$options = chattermate_get_options();
	if ( ! chattermate_should_load_widget( $options ) ) {
		return;
	}

	$script_url = chattermate_script_url( $options );

	if ( 'interaction' === $options['load_timing'] ) {
		wp_enqueue_script(
			'chattermate-widget',
			CHATTERMATE_URL . 'public/js/delayed-loader.js',
			array(),
			CHATTERMATE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		$globals = chattermate_widget_globals( $options ) . 'window.chattermateWpLoader=' . wp_json_encode( array( 'src' => $script_url ) ) . ';';
	} else {
		// The hosted loader is versioned by ChatterMate, so no ?ver= is added.
		wp_enqueue_script(
			'chattermate-widget',
			$script_url,
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- remote script, versioned by the service.
			array(
				'in_footer' => true,
				'strategy'  => 'async',
			)
		);
		$globals = chattermate_widget_globals( $options );
	}

	wp_add_inline_script( 'chattermate-widget', $globals, 'before' );
}
add_action( 'wp_enqueue_scripts', 'chattermate_enqueue_widget' );
