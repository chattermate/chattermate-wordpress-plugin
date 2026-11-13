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

	if ( 'only' === $options['visibility_mode'] && ! chattermate_request_matches_rules( $options ) ) {
		return false;
	}

	if ( 'except' === $options['visibility_mode'] && chattermate_request_matches_rules( $options ) ) {
		return false;
	}

	// Last, as it may ask ChatterMate for a token: a widget that only takes
	// signed-in visitors stays off for everyone else.
	return chattermate_visitor_can_use_widget( $options );
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

	return $js . chattermate_signed_out_js( $options );
}

/**
 * Settings for public/js/chat.js.
 *
 * @param array $options Settings from chattermate_get_options().
 * @return string JavaScript.
 */
function chattermate_helper_config( $options ) {
	$config = array(
		'src'  => chattermate_script_url( $options ),
		'lazy' => 'interaction' === $options['load_timing'],
	);

	// A signed-in visitor's token is fetched first, so chat.js loads the widget.
	$identity = chattermate_identity_config( $options );
	if ( $identity ) {
		$config['identity'] = $identity;
	}

	if ( $options['share_context'] ) {
		$config['context'] = (object) chattermate_page_context();
		if ( chattermate_woocommerce_active() ) {
			$config['cartUrl'] = esc_url_raw( rest_url( 'wc/store/v1/cart' ) );
		}
	}

	return 'window.chattermateWp=' . wp_json_encode( $config ) . ';';
}

/**
 * Whether the widget loads on this request, decided once per request.
 *
 * Shared by the enqueue below and the Chat button, so a button never shows on a
 * page where the chat itself is hidden.
 *
 * @return bool
 */
function chattermate_widget_loads_here() {
	static $loads = null;
	if ( null === $loads ) {
		$loads = chattermate_is_page_view() && chattermate_should_load_widget( chattermate_get_options() );
	}

	return $loads;
}

/**
 * Whether this request is a visitor viewing a page: not the admin, a feed, the
 * REST API (content.rendered), AJAX or cron (newsletters built from posts).
 *
 * @return bool
 */
function chattermate_is_page_view() {
	return ! is_admin()
		&& ! is_feed()
		&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		&& ! wp_is_json_request()
		&& ! wp_doing_ajax()
		&& ! wp_doing_cron();
}

/**
 * Load the widget on the front end.
 *
 * Immediate: the hosted loader is enqueued in the footer, async.
 * On interaction: public/js/chat.js adds the hosted loader on the visitor's first
 * scroll, tap, mouse move or key press, which keeps it out of page-speed tests.
 * Signed-in customers: chat.js fetches the visitor's token, then adds the loader.
 * Either way chat.js also opens the chat from Chat buttons and #chattermate links.
 */
function chattermate_enqueue_widget() {
	if ( ! chattermate_widget_loads_here() ) {
		return;
	}

	$options = chattermate_get_options();
	$globals = chattermate_widget_globals( $options );

	wp_enqueue_script(
		'chattermate-chat',
		CHATTERMATE_URL . 'public/js/chat.js',
		array(),
		CHATTERMATE_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	// chat.js loads the widget itself on first interaction, and for a signed-in
	// visitor once their token is in. Otherwise the hosted script loads straight away.
	$helper_loads = 'interaction' === $options['load_timing'] || chattermate_identity_for_visitor( $options );
	if ( $helper_loads ) {
		wp_add_inline_script( 'chattermate-chat', $globals . chattermate_helper_config( $options ), 'before' );
		return;
	}

	wp_add_inline_script( 'chattermate-chat', chattermate_helper_config( $options ), 'before' );

	// The hosted loader is versioned by ChatterMate, so no ?ver= is added.
	wp_enqueue_script(
		'chattermate-widget',
		chattermate_script_url( $options ),
		array(),
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- remote script, versioned by the service.
		array(
			'in_footer' => true,
			'strategy'  => 'async',
		)
	);
	wp_add_inline_script( 'chattermate-widget', $globals, 'before' );
}
add_action( 'wp_enqueue_scripts', 'chattermate_enqueue_widget' );
