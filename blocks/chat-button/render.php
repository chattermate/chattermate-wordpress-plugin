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

echo wp_kses_post(
	chattermate_chat_button_html(
		isset( $attributes['label'] ) ? (string) $attributes['label'] : '',
		isset( $attributes['message'] ) ? (string) $attributes['message'] : '',
		get_block_wrapper_attributes( array( 'class' => 'wp-element-button chattermate-chat-button' ) )
	)
);
