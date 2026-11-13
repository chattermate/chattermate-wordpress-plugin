<?php
/**
 * Page details for the AI: what kind of page the visitor is on and, in a
 * WooCommerce store, the product they are looking at.
 *
 * Only public page facts go into the page's HTML, so cached pages stay correct
 * for everyone. The visitor's own cart is read in their browser from the
 * WooCommerce Store API when they open the chat (public/js/chat.js).
 *
 * The widget takes flat values with lowercase keys (ChatterMate.setContext()).
 *
 * @package ChatterMate
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Longest text value sent; the widget caps the whole context at 4 KB.
define( 'CHATTERMATE_CONTEXT_TEXT_MAX', 200 );

/**
 * Facts about the current page.
 *
 * @return array<string, string|int|float|bool>
 */
function chattermate_page_context() {
	$context = array(
		'page_type'  => chattermate_page_type(),
		'page_title' => chattermate_context_text( chattermate_page_title() ),
	);

	if ( chattermate_woocommerce_active() ) {
		$context['store_currency'] = get_woocommerce_currency();
		$context                   = array_merge( $context, chattermate_product_context() );
	}

	/**
	 * Filters the page details the ChatterMate AI receives.
	 *
	 * Keys must be lowercase letters, digits and underscores; values must be text,
	 * numbers or true/false. Never add anything private: it is printed in the page.
	 *
	 * @param array $context Page details.
	 */
	return (array) apply_filters( 'chattermate_page_context', array_filter( $context, 'chattermate_context_value_set' ) );
}

/**
 * The kind of page, in a word.
 *
 * @return string
 */
function chattermate_page_type() {
	$woo = chattermate_woocommerce_active();

	if ( $woo && is_product() ) {
		return 'product';
	}
	if ( $woo && is_cart() ) {
		return 'cart';
	}
	if ( $woo && is_checkout() ) {
		return 'checkout';
	}
	if ( $woo && is_account_page() ) {
		return 'account';
	}
	if ( $woo && is_shop() ) {
		return 'shop';
	}
	if ( $woo && is_product_taxonomy() ) {
		return 'product_category';
	}
	if ( is_front_page() ) {
		return 'home';
	}
	if ( is_singular( 'post' ) ) {
		return 'blog_post';
	}
	if ( is_page() ) {
		return 'page';
	}
	if ( is_search() ) {
		return 'search';
	}

	return 'archive';
}

/**
 * The page's own title, without the site name.
 *
 * @return string
 */
function chattermate_page_title() {
	if ( is_singular() ) {
		return single_post_title( '', false );
	}
	if ( is_search() ) {
		return get_search_query( false );
	}
	if ( is_archive() ) {
		return wp_strip_all_tags( get_the_archive_title() );
	}

	return get_bloginfo( 'name' );
}

/**
 * The product on a WooCommerce product page.
 *
 * @return array
 */
function chattermate_product_context() {
	if ( ! is_product() ) {
		return array();
	}

	$product = wc_get_product( get_queried_object_id() );
	if ( ! $product ) {
		return array();
	}

	return array(
		'product_name'     => chattermate_context_text( $product->get_name() ),
		'product_price'    => chattermate_context_text( html_entity_decode( wp_strip_all_tags( wc_price( (float) $product->get_price() ) ), ENT_QUOTES, 'UTF-8' ) ),
		'product_sku'      => chattermate_context_text( (string) $product->get_sku() ),
		'product_in_stock' => (bool) $product->is_in_stock(),
		'product_on_sale'  => (bool) $product->is_on_sale(),
		'product_url'      => esc_url_raw( (string) get_permalink( $product->get_id() ) ),
	);
}

/**
 * Plain, single-line text cut to a safe length.
 *
 * @param string $text Text.
 * @return string
 */
function chattermate_context_text( $text ) {
	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );

	return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, CHATTERMATE_CONTEXT_TEXT_MAX ) : substr( $text, 0, CHATTERMATE_CONTEXT_TEXT_MAX );
}

/**
 * Whether a context value is worth sending: no empty strings.
 *
 * @param mixed $value Value.
 * @return bool
 */
function chattermate_context_value_set( $value ) {
	return '' !== $value && null !== $value;
}
