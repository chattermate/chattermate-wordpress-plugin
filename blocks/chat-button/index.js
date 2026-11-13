/**
 * Chat button block: the editor side.
 *
 * Plain JavaScript against WordPress's own packages, so there is no build step.
 * The button is rendered on the server (render.php); save() stores nothing.
 */
( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'chattermate/chat-button', {
		edit: function ( props ) {
			var attributes = props.attributes;
			// Inline like the button it renders, not a full-width bar.
			var blockProps = blockEditor.useBlockProps( {
				className: 'wp-element-button chattermate-chat-button',
				style: { display: 'inline-block' },
			} );

			return el(
				element.Fragment,
				null,
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __( 'Chat', 'chattermate-chat' ) },
						el( components.TextareaControl, {
							label: __( 'Message to start with', 'chattermate-chat' ),
							help: __( 'Optional. Typed into the chat box for the visitor, who decides whether to send it.', 'chattermate-chat' ),
							value: attributes.message,
							onChange: function ( value ) {
								props.setAttributes( { message: value } );
							},
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( blockEditor.RichText, {
						tagName: 'span',
						value: attributes.label,
						allowedFormats: [],
						withoutInteractiveFormatting: true,
						placeholder: __( 'Chat with us', 'chattermate-chat' ),
						'aria-label': __( 'Button text', 'chattermate-chat' ),
						onChange: function ( value ) {
							props.setAttributes( { label: value } );
						},
					} )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n ) );
