<?php
/**
 * Plugin Name:       ChatterMate Chat
 * Plugin URI:        https://chattermate.chat
 * Description:        Add the ChatterMate AI customer-support chat widget to your site. Enter your Widget ID and the chat launcher appears on every page — no theme edits.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            ChatterMate
 * Author URI:        https://chattermate.chat
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       chattermate-chat
 *
 * @package ChatterMate
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CHATTERMATE_VERSION', '1.0.0' );
define( 'CHATTERMATE_OPTION', 'chattermate_options' );
define( 'CHATTERMATE_DEFAULT_SCRIPT_URL', 'https://app.chattermate.chat/webclient/chattermate.min.js' );

/**
 * Return the saved plugin options merged with defaults.
 *
 * @return array{widget_id:string, script_url:string}
 */
function chattermate_get_options() {
	$defaults = array(
		'widget_id'  => '',
		'script_url' => CHATTERMATE_DEFAULT_SCRIPT_URL,
	);

	$options = get_option( CHATTERMATE_OPTION, array() );
	if ( ! is_array( $options ) ) {
		$options = array();
	}

	$options = wp_parse_args( $options, $defaults );

	// An empty stored script URL falls back to the hosted default.
	if ( '' === trim( (string) $options['script_url'] ) ) {
		$options['script_url'] = CHATTERMATE_DEFAULT_SCRIPT_URL;
	}

	return $options;
}

/**
 * Register the setting and its sanitize callback.
 */
function chattermate_register_settings() {
	register_setting(
		'chattermate_settings_group',
		CHATTERMATE_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'chattermate_sanitize_options',
			'default'           => array(),
		)
	);

	add_settings_section(
		'chattermate_main_section',
		__( 'Widget settings', 'chattermate-chat' ),
		'chattermate_settings_section_intro',
		'chattermate-chat'
	);

	add_settings_field(
		'chattermate_widget_id',
		__( 'Widget ID', 'chattermate-chat' ),
		'chattermate_field_widget_id',
		'chattermate-chat',
		'chattermate_main_section'
	);

	add_settings_field(
		'chattermate_script_url',
		__( 'Script URL', 'chattermate-chat' ),
		'chattermate_field_script_url',
		'chattermate-chat',
		'chattermate_main_section'
	);
}
add_action( 'admin_init', 'chattermate_register_settings' );

/**
 * Sanitize the submitted options before they are stored.
 *
 * @param mixed $input Raw option input from the settings form.
 * @return array{widget_id:string, script_url:string}
 */
function chattermate_sanitize_options( $input ) {
	$clean = array(
		'widget_id'  => '',
		'script_url' => CHATTERMATE_DEFAULT_SCRIPT_URL,
	);

	if ( ! is_array( $input ) ) {
		return $clean;
	}

	if ( isset( $input['widget_id'] ) ) {
		$clean['widget_id'] = sanitize_text_field( $input['widget_id'] );
	}

	if ( isset( $input['script_url'] ) && '' !== trim( (string) $input['script_url'] ) ) {
		$clean['script_url'] = esc_url_raw( trim( (string) $input['script_url'] ) );
	}

	if ( '' === $clean['script_url'] ) {
		$clean['script_url'] = CHATTERMATE_DEFAULT_SCRIPT_URL;
	}

	return $clean;
}

/**
 * Add the plugin settings page under the Settings menu.
 */
function chattermate_add_settings_page() {
	add_options_page(
		__( 'ChatterMate Chat', 'chattermate-chat' ),
		__( 'ChatterMate Chat', 'chattermate-chat' ),
		'manage_options',
		'chattermate-chat',
		'chattermate_render_settings_page'
	);
}
add_action( 'admin_menu', 'chattermate_add_settings_page' );

/**
 * Add a "Settings" link on the Plugins list row.
 *
 * @param array $links Existing action links.
 * @return array
 */
function chattermate_plugin_action_links( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=chattermate-chat' ) ),
		esc_html__( 'Settings', 'chattermate-chat' )
	);
	array_unshift( $links, $settings_link );

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'chattermate_plugin_action_links' );

/**
 * Section intro text.
 */
function chattermate_settings_section_intro() {
	echo '<p>' . wp_kses(
		sprintf(
			/* translators: %s: docs URL */
			__( 'Enter the Widget ID from your ChatterMate dashboard. Not sure where to find it? See the <a href="%s" target="_blank" rel="noopener noreferrer">widget documentation</a>.', 'chattermate-chat' ),
			'https://docs.chattermate.chat/features/widget'
		),
		array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
	) . '</p>';
}

/**
 * Render the Widget ID field.
 */
function chattermate_field_widget_id() {
	$options = chattermate_get_options();
	printf(
		'<input type="text" id="chattermate_widget_id" name="%1$s[widget_id]" value="%2$s" class="regular-text" autocomplete="off" placeholder="%3$s" />',
		esc_attr( CHATTERMATE_OPTION ),
		esc_attr( $options['widget_id'] ),
		esc_attr__( 'e.g. d63483f1-3954-4a94-a3f7-bb5039dda633', 'chattermate-chat' )
	);
	echo '<p class="description">' . esc_html__( 'Required. The widget appears only after this is set.', 'chattermate-chat' ) . '</p>';
}

/**
 * Render the Script URL field.
 */
function chattermate_field_script_url() {
	$options = chattermate_get_options();
	printf(
		'<input type="url" id="chattermate_script_url" name="%1$s[script_url]" value="%2$s" class="regular-text" placeholder="%3$s" />',
		esc_attr( CHATTERMATE_OPTION ),
		esc_attr( $options['script_url'] ),
		esc_attr( CHATTERMATE_DEFAULT_SCRIPT_URL )
	);
	echo '<p class="description">' . esc_html__( 'Advanced. Leave as the default unless you self-host ChatterMate.', 'chattermate-chat' ) . '</p>';
}

/**
 * Render the settings page.
 */
function chattermate_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'chattermate_settings_group' );
			do_settings_sections( 'chattermate-chat' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

/**
 * Inject the ChatterMate embed into the site footer.
 *
 * Emits the canonical two-tag snippet: the inline global that carries the Widget ID,
 * followed by the hosted loader script.
 */
function chattermate_print_widget() {
	if ( is_admin() ) {
		return;
	}

	$options   = chattermate_get_options();
	$widget_id = trim( (string) $options['widget_id'] );
	if ( '' === $widget_id ) {
		return;
	}

	printf(
		'<script>window.chattermateId=%s;</script>' . "\n" . '<script src="%s" async></script>' . "\n",
		wp_json_encode( $widget_id ),
		esc_url( $options['script_url'] )
	);
}
add_action( 'wp_footer', 'chattermate_print_widget' );
