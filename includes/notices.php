<?php
/**
 * The "Finish setting up ChatterMate" notice.
 *
 * Shown after activation, on the Dashboard and Plugins screens only, until a
 * Widget ID is saved or the notice is dismissed.
 *
 * @package ChatterMate
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Print the setup notice where it belongs.
 */
function chattermate_setup_notice() {
	if ( ! current_user_can( 'manage_options' ) || ! get_option( CHATTERMATE_SETUP_FLAG ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) ) {
		return;
	}

	$options = chattermate_get_options();
	if ( '' !== $options['widget_id'] ) {
		return;
	}

	$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=chattermate_dismiss_setup' ), 'chattermate_dismiss_setup' );
	?>
	<div class="notice notice-info chattermate-notice">
		<p>
			<strong><?php esc_html_e( 'Finish setting up ChatterMate.', 'chattermate-chat' ); ?></strong>
			<?php esc_html_e( 'Add your Widget ID to put AI support on your site.', 'chattermate-chat' ); ?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( chattermate_admin_page_url() ); ?>"><?php esc_html_e( 'Finish setup', 'chattermate-chat' ); ?></a>
			<a class="button-link" href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'Dismiss', 'chattermate-chat' ); ?></a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'chattermate_setup_notice' );

/**
 * Dismiss the setup notice for good.
 */
function chattermate_handle_dismiss_setup() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'chattermate-chat' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'chattermate_dismiss_setup' );

	delete_option( CHATTERMATE_SETUP_FLAG );

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
	exit;
}
add_action( 'admin_post_chattermate_dismiss_setup', 'chattermate_handle_dismiss_setup' );
