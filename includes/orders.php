<?php
/**
 * Order status for the chat: ChatterMate's agent asks about one order, by its
 * number and the billing email the customer typed in the chat.
 *
 * POST /wp-json/chattermate/v1/orders/lookup {order_number, email}, signed by
 * ChatterMate with the site link's secret (site-link.php). The order comes back
 * only when the number and the billing email both match; anything else is the
 * same 404, so the route can't be used to find out which orders exist. Lookups
 * that match nothing are limited per order number and per store. The answer
 * holds what a status card needs, never the street address or phone.
 *
 * @package ChatterMate
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Lookups that matched no order, per hour: for one order number, and for the whole store.
define( 'CHATTERMATE_ORDER_MISSES_PER_NUMBER', 10 );
define( 'CHATTERMATE_ORDER_MISSES_PER_STORE', 200 );
define( 'CHATTERMATE_ORDER_MAX_ITEMS', 20 );

/**
 * Register the route, on stores only.
 */
function chattermate_register_order_route() {
	if ( ! chattermate_woocommerce_active() ) {
		return;
	}
	register_rest_route(
		'chattermate/v1',
		'/orders/lookup',
		array(
			'methods'             => 'POST',
			'callback'            => 'chattermate_lookup_order',
			'permission_callback' => function ( $request ) {
				return $request->is_json_content_type() && chattermate_request_is_signed( $request, 'order_lookup' );
			},
		)
	);
}
add_action( 'rest_api_init', 'chattermate_register_order_route' );

/**
 * The lookup.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function chattermate_lookup_order( $request ) {
	// Only the signed JSON body counts: never query or form parameters, which the
	// signature doesn't cover.
	$params = $request->get_json_params();
	$number = is_array( $params ) && isset( $params['order_number'] ) ? (string) $params['order_number'] : '';
	$email  = is_array( $params ) && isset( $params['email'] ) ? strtolower( trim( (string) $params['email'] ) ) : '';
	if ( ! preg_match( '/^[A-Za-z0-9-]{1,32}$/', $number ) || ! is_email( $email ) ) {
		return new WP_Error( 'chattermate_bad_request', 'An order number and an email are needed.', array( 'status' => 400 ) );
	}

	$keys = array(
		'chattermate_order_misses_' . md5( strtolower( $number ) ) => CHATTERMATE_ORDER_MISSES_PER_NUMBER,
		'chattermate_order_misses'                                  => CHATTERMATE_ORDER_MISSES_PER_STORE,
	);
	foreach ( $keys as $key => $limit ) {
		if ( (int) get_transient( $key ) >= $limit ) {
			return new WP_Error( 'chattermate_too_many', 'Too many lookups that matched nothing.', array( 'status' => 429 ) );
		}
	}

	$order = chattermate_find_order( $number );
	if ( ! $order || ! hash_equals( strtolower( trim( (string) $order->get_billing_email() ) ), $email ) ) {
		// Only misses count: a customer asking about their own order is never locked out
		foreach ( array_keys( $keys ) as $key ) {
			set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
		}
		return new WP_Error( 'chattermate_not_found', 'No such order.', array( 'status' => 404 ) );
	}

	return rest_ensure_response( array( 'order' => chattermate_order_summary( $order ) ) );
}

/**
 * The order shown to customers with this number. Order numbers are order IDs
 * unless a plugin changes them: the chattermate_order_id filter maps a number to
 * an ID for such stores.
 *
 * @param string $number Order number as the customer gave it.
 * @return WC_Order|null
 */
function chattermate_find_order( $number ) {
	$order_id = (int) apply_filters( 'chattermate_order_id', ctype_digit( $number ) ? (int) $number : 0, $number );
	$order    = $order_id > 0 ? wc_get_order( $order_id ) : null;
	if ( ! $order instanceof WC_Order || 'checkout-draft' === $order->get_status() ) {
		return null;
	}

	// The number the customer sees must be the one they gave.
	return (string) $order->get_order_number() === $number ? $order : null;
}

/**
 * What the status card shows.
 *
 * @param WC_Order $order Order.
 * @return array
 */
function chattermate_order_summary( $order ) {
	$items = array();
	foreach ( array_slice( $order->get_items(), 0, CHATTERMATE_ORDER_MAX_ITEMS ) as $item ) {
		$product = $item->get_product();
		$image   = $product ? wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) : '';
		$items[] = array(
			'name'     => $item->get_name(),
			'quantity' => $item->get_quantity(),
			'total'    => wc_format_decimal( (float) $item->get_total() + (float) $item->get_total_tax(), wc_get_price_decimals() ),
			'image'    => $image ? $image : '',
		);
	}

	$created  = $order->get_date_created();
	$shipping = $order->has_shipping_address()
		? array( $order->get_shipping_city(), $order->get_shipping_postcode(), $order->get_shipping_country() )
		: array( $order->get_billing_city(), $order->get_billing_postcode(), $order->get_billing_country() );

	return array(
		'id'       => $order->get_id(),
		'number'   => (string) $order->get_order_number(),
		'status'   => $order->get_status(),
		'created'  => $created ? $created->date( DATE_ATOM ) : null,
		'currency' => $order->get_currency(),
		'total'    => wc_format_decimal( $order->get_total(), wc_get_price_decimals() ),
		'items'    => $items,
		'shipping' => array(
			'city'     => $shipping[0],
			'postcode' => $shipping[1],
			'country'  => $shipping[2],
		),
		'tracking' => chattermate_order_tracking( $order ),
	);
}

/**
 * Tracking numbers, from the WooCommerce Shipment Tracking extension when it's
 * used; other shipping plugins can add theirs with the chattermate_order_tracking
 * filter ({provider, number, url} each).
 *
 * @param WC_Order $order Order.
 * @return array
 */
function chattermate_order_tracking( $order ) {
	$tracking = array();
	$items    = $order->get_meta( '_wc_shipment_tracking_items' );
	foreach ( is_array( $items ) ? $items : array() as $entry ) {
		if ( ! is_array( $entry ) || empty( $entry['tracking_number'] ) ) {
			continue;
		}
		$tracking[] = array(
			'provider' => ! empty( $entry['custom_tracking_provider'] ) ? $entry['custom_tracking_provider'] : ( isset( $entry['tracking_provider'] ) ? $entry['tracking_provider'] : '' ),
			'number'   => (string) $entry['tracking_number'],
			'url'      => ! empty( $entry['custom_tracking_link'] ) ? esc_url_raw( $entry['custom_tracking_link'] ) : '',
		);
	}

	return apply_filters( 'chattermate_order_tracking', $tracking, $order );
}
