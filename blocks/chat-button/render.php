<?php
/**
 * Chat button block: the front-end markup.
 *
 * @package ChatterMate
 *
 * @var array $attributes Block attributes.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Escaped inside chattermate_chat_button_html().
echo chattermate_chat_button_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	isset( $attributes['label'] ) ? (string) $attributes['label'] : '',
	isset( $attributes['message'] ) ? (string) $attributes['message'] : '',
	get_block_wrapper_attributes( array( 'class' => 'wp-element-button chattermate-chat-button' ) )
);
