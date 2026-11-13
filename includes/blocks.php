<?php
/**
 * Ways to open the chat from content: the Chat button block and the
 * [chattermate_button] shortcode. Links to #chattermate work too (public/js/chat.js).
 *
 * @package ChatterMate
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Chat button block and the shortcode.
 */
function chattermate_register_blocks() {
	// block.json's textdomain also sets up the editor script's translations.
	register_block_type( CHATTERMATE_DIR . 'blocks/chat-button' );

	add_shortcode( 'chattermate_button', 'chattermate_button_shortcode' );
}
add_action( 'init', 'chattermate_register_blocks' );

/**
 * [chattermate_button label="Chat with us" message="Where is my order?"]
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function chattermate_button_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'label'   => '',
			'message' => '',
		),
		$atts,
		'chattermate_button'
	);

	return chattermate_chat_button_html( $atts['label'], $atts['message'], 'class="wp-element-button chattermate-chat-button"' );
}

/**
 * A button that opens the chat. Empty where the chat itself does not load, so a
 * page never shows a button that does nothing.
 *
 * @param string $label              Button text; plain text, HTML is removed.
 * @param string $message            Optional message typed into the chat for the visitor.
 * @param string $wrapper_attributes Escaped HTML attributes for the button element.
 * @return string Escaped HTML.
 */
function chattermate_chat_button_html( $label, $message, $wrapper_attributes ) {
	if ( ! chattermate_widget_loads_here() ) {
		return '';
	}

	// Entities from the editor stay as they are; tags are removed.
	$label = trim( wp_kses( (string) $label, array() ) );
	if ( '' === $label ) {
		$label = esc_html__( 'Chat with us', 'chattermate-chat' );
	}

	return sprintf(
		'<button type="button" %1$s data-chattermate-open="1" data-chattermate-message="%2$s">%3$s</button>',
		$wrapper_attributes,
		esc_attr( trim( wp_strip_all_tags( (string) $message ) ) ),
		$label
	);
}
