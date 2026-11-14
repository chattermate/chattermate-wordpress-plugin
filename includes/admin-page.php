<?php
/**
 * The ChatterMate admin screen: menu, setting registration, assets and actions.
 *
 * @package ChatterMate
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CHATTERMATE_DIR . 'includes/admin-views.php';
require_once CHATTERMATE_DIR . 'includes/connect.php';

/**
 * Add the top-level ChatterMate menu.
 *
 * The icon is "none" here and drawn by admin/css/menu.css: WordPress repaints
 * SVG menu icons in one colour, which would turn the logo into a white square.
 */
function chattermate_add_admin_menu() {
	add_menu_page(
		__( 'ChatterMate', 'chattermate-chat' ),
		__( 'ChatterMate', 'chattermate-chat' ),
		'manage_options',
		CHATTERMATE_PAGE,
		'chattermate_render_admin_page',
		'none',
		58
	);
}
add_action( 'admin_menu', 'chattermate_add_admin_menu' );

/**
 * The menu icon's stylesheet, on every admin screen (the menu is on every screen).
 */
function chattermate_menu_icon_style() {
	if ( current_user_can( 'manage_options' ) ) {
		wp_enqueue_style( 'chattermate-menu', CHATTERMATE_URL . 'admin/css/menu.css', array(), CHATTERMATE_VERSION );
	}
}
add_action( 'admin_enqueue_scripts', 'chattermate_menu_icon_style' );

/**
 * 1.0.0 kept its settings under Settings > ChatterMate Chat: send old bookmarks
 * and links to the new screen.
 */
function chattermate_redirect_old_settings_page() {
	global $pagenow;

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect, no action taken.
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( 'options-general.php' === $pagenow && 'chattermate-chat' === $page ) {
		wp_safe_redirect( chattermate_admin_page_url() );
		exit;
	}
}
// WordPress refuses an unknown settings page before admin_init runs, so catch it there.
add_action( 'admin_page_access_denied', 'chattermate_redirect_old_settings_page' );

/**
 * Register the setting with its sanitize callback.
 */
function chattermate_register_settings() {
	register_setting(
		'chattermate_settings',
		CHATTERMATE_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'chattermate_sanitize_options',
			'default'           => chattermate_default_options(),
			'show_in_rest'      => false,
		)
	);
}
add_action( 'admin_init', 'chattermate_register_settings' );

/**
 * Sites upgraded from 1.0.0 lack the autoloaded flag the front end reads; a
 * missing option costs a database query on every page view.
 */
function chattermate_ensure_flags() {
	if ( null === get_option( CHATTERMATE_REQUIRES_TOKEN_OPTION, null ) ) {
		add_option( CHATTERMATE_REQUIRES_TOKEN_OPTION, 0, '', true );
	}
}
add_action( 'admin_init', 'chattermate_ensure_flags' );

/**
 * Load the screen's CSS and JS on the ChatterMate screen only.
 *
 * @param string $hook_suffix Current admin page hook.
 */
function chattermate_admin_assets( $hook_suffix ) {
	if ( 'toplevel_page_' . CHATTERMATE_PAGE !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style( 'chattermate-admin', CHATTERMATE_URL . 'admin/css/admin.css', array(), CHATTERMATE_VERSION );
	wp_enqueue_script(
		'chattermate-admin',
		CHATTERMATE_URL . 'admin/js/admin.js',
		array(),
		CHATTERMATE_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
add_action( 'admin_enqueue_scripts', 'chattermate_admin_assets' );

/**
 * "Check again": re-check the saved Widget ID, then return to the screen.
 */
function chattermate_handle_check_connection() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'chattermate-chat' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'chattermate_check_connection' );

	chattermate_refresh_connection();

	wp_safe_redirect( chattermate_admin_page_url() );
	exit;
}
add_action( 'admin_post_chattermate_check_connection', 'chattermate_handle_check_connection' );

/**
 * Suggested text for the site's privacy policy (Settings > Privacy).
 */
function chattermate_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}

	$content = '<p>' . esc_html__( 'This site uses ChatterMate to answer questions in a chat window. When you open a page with the chat, your browser loads the chat from ChatterMate. What you type in the chat, and any name or email you give, is sent to ChatterMate to answer you and to let our team follow up. ChatterMate also receives standard request details such as your IP address and browser type, and keeps a token in your browser so the conversation continues across pages.', 'chattermate-chat' ) . '</p>'
		. '<p>' . esc_html__( 'To answer better, the chat may also tell ChatterMate which page you are on, the product you are looking at and, when you open the chat, what is in your shopping cart. If you are logged in to this site, your name and email address may be sent so our team knows who they are talking to.', 'chattermate-chat' ) . '</p>'
		. '<p>' . esc_html__( 'If you ask the chat about an order, the order number and billing email you type are used to look it up on this site. The chat is told the order\'s status, date, items, total, delivery town and postcode and any tracking numbers, only when both match.', 'chattermate-chat' ) . '</p>'
		. '<p>' . sprintf(
			/* translators: %s: ChatterMate privacy policy URL. */
			esc_html__( 'ChatterMate privacy policy: %s', 'chattermate-chat' ),
			'https://chattermate.chat/privacy-policy/'
		) . '</p>';

	wp_add_privacy_policy_content( __( 'ChatterMate Chat', 'chattermate-chat' ), wp_kses_post( $content ) );
}
add_action( 'admin_init', 'chattermate_privacy_policy_content' );
