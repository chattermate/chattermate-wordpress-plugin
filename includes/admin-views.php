<?php
/**
 * Markup of the ChatterMate admin screen.
 *
 * @package ChatterMate
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How the connection status reads on screen.
 *
 * @param array      $options    Settings from chattermate_get_options().
 * @param array|null $connection Cached check from chattermate_get_connection().
 * @return array{tone:string, pill:string, title:string, detail:string}
 */
function chattermate_connection_view( $options, $connection ) {
	if ( '' === $options['widget_id'] ) {
		return array(
			'tone'   => 'neutral',
			'pill'   => __( 'Not connected', 'chattermate-chat' ),
			'title'  => __( 'Add your Widget ID', 'chattermate-chat' ),
			'detail' => __( 'The chat appears on your site once a Widget ID is saved.', 'chattermate-chat' ),
		);
	}

	if ( null === $connection ) {
		return array(
			'tone'   => 'neutral',
			'pill'   => __( 'Not checked', 'chattermate-chat' ),
			'title'  => __( 'Not checked yet', 'chattermate-chat' ),
			'detail' => __( 'Click Check again to confirm this Widget ID with ChatterMate.', 'chattermate-chat' ),
		);
	}

	/* translators: %s: time since the check, e.g. "5 mins". */
	$when = sprintf( __( 'Last checked %s ago.', 'chattermate-chat' ), human_time_diff( (int) $connection['checked_at'] ) );

	switch ( $connection['status'] ) {
		case 'connected':
			return array(
				'tone'   => 'ok',
				'pill'   => __( 'Connected', 'chattermate-chat' ),
				'title'  => '' !== $connection['agent_name']
					/* translators: %s: name of the ChatterMate AI agent. */
					? sprintf( __( 'Connected: %s', 'chattermate-chat' ), $connection['agent_name'] )
					: __( 'Connected', 'chattermate-chat' ),
				'detail' => $when . ' ' . __( 'Visitors see this agent on your site.', 'chattermate-chat' ),
			);
		case 'not_found':
			return array(
				'tone'   => 'error',
				'pill'   => __( 'Needs attention', 'chattermate-chat' ),
				'title'  => __( 'No widget has this ID', 'chattermate-chat' ),
				'detail' => __( "Copy it again from your agent's Widget tab in ChatterMate. Until then the chat can't answer visitors.", 'chattermate-chat' ),
			);
		case 'needs_token':
			if ( chattermate_identity_ready( $options ) ) {
				return array(
					'tone'   => 'ok',
					'pill'   => __( 'Connected', 'chattermate-chat' ),
					/* translators: %s: name of the ChatterMate AI agent. */
					'title'  => sprintf( __( 'Connected: %s, for signed-in visitors', 'chattermate-chat' ), '' !== $connection['agent_name'] ? $connection['agent_name'] : __( 'your agent', 'chattermate-chat' ) ),
					'detail' => $when . ' ' . __( 'The agent only chats with visitors who are signed in, so visitors who are not signed in do not see the chat.', 'chattermate-chat' ),
				);
			}
			return array(
				'tone'   => 'error',
				'pill'   => __( 'Needs attention', 'chattermate-chat' ),
				/* translators: %s: name of the ChatterMate AI agent. */
				'title'  => sprintf( __( '%s only chats with signed-in visitors', 'chattermate-chat' ), '' !== $connection['agent_name'] ? $connection['agent_name'] : __( 'This agent', 'chattermate-chat' ) ),
				'detail' => __( 'Turn on "Recognise signed-in customers" below and add an API key, or turn off "Require token" for the agent in ChatterMate, then click Check again.', 'chattermate-chat' ),
			);
		case 'needs_api_url':
			return array(
				'tone'   => 'error',
				'pill'   => __( 'Needs attention', 'chattermate-chat' ),
				'title'  => __( 'Set your API URL', 'chattermate-chat' ),
				'detail' => __( 'You use a self-hosted widget script. Add your API URL under Advanced so the plugin can check your widget there.', 'chattermate-chat' ),
			);
		case 'no_agent':
			return array(
				'tone'   => 'error',
				'pill'   => __( 'Needs attention', 'chattermate-chat' ),
				'title'  => __( 'This widget has no agent', 'chattermate-chat' ),
				'detail' => __( 'Choose an agent for this widget in ChatterMate, then click Check again.', 'chattermate-chat' ),
			);
	}

	return array(
		'tone'   => 'neutral',
		'pill'   => __( 'Not checked', 'chattermate-chat' ),
		'title'  => __( "Couldn't reach ChatterMate", 'chattermate-chat' ),
		'detail' => $when . ' ' . ( '' !== $options['api_url']
			? __( 'Your settings are saved and the chat still loads. Check the API URL under Advanced: it should end in /api/v1. If your API is on a private network address, WordPress blocks the request; allow it with the http_request_host_is_external filter.', 'chattermate-chat' )
			: __( 'Your settings are saved and the chat still loads. Try Check again in a minute.', 'chattermate-chat' ) ),
	);
}

/**
 * Whether the live preview can run for this connection.
 *
 * @param array      $options    Settings from chattermate_get_options().
 * @param array|null $connection Cached check.
 * @return bool
 */
function chattermate_can_preview( $options, $connection ) {
	if ( '' === $options['widget_id'] ) {
		return false;
	}

	if ( null === $connection ) {
		return true;
	}
	if ( 'needs_token' === $connection['status'] ) {
		// The preview chats as the signed-in administrator when that can work.
		return chattermate_identity_ready( $options );
	}

	return ! in_array( $connection['status'], array( 'not_found', 'no_agent' ), true );
}

/**
 * The logo mark, decorative.
 *
 * @param int $size Width and height in px.
 */
function chattermate_render_logo( $size ) {
	printf(
		'<svg class="chattermate-logo" width="%1$d" height="%1$d" viewBox="0 0 64 64" aria-hidden="true" focusable="false"><rect width="64" height="64" rx="16" fill="#12150E"/><circle cx="23" cy="32" r="8" fill="#C9F24E"/><circle cx="41" cy="32" r="8" fill="#FF9D87"/></svg>',
		absint( $size )
	);
}

/**
 * Render the whole screen.
 */
function chattermate_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$options    = chattermate_get_options();
	$connection = chattermate_get_connection( $options );
	$view       = chattermate_connection_view( $options, $connection );
	?>
	<div class="wrap chattermate-admin">
		<?php chattermate_render_header( $options, $view ); ?>
		<hr class="wp-header-end">
		<?php settings_errors(); ?>
		<?php chattermate_render_connect_notice(); ?>

		<?php
		if ( '' === $options['widget_id'] ) {
			chattermate_render_first_run( $options );
		}
		?>

		<div class="chattermate-layout">
			<div class="chattermate-main">
				<form method="post" action="options.php" class="chattermate-form">
					<?php settings_fields( 'chattermate_settings' ); ?>
					<?php chattermate_render_connect_card( $options, $view ); ?>
					<?php chattermate_render_visibility_card( $options ); ?>
					<?php chattermate_render_personal_card( $options ); ?>
					<?php chattermate_render_advanced_card( $options ); ?>
					<div class="chattermate-actions">
						<button type="submit" class="chattermate-button chattermate-button--primary"><?php esc_html_e( 'Save changes', 'chattermate-chat' ); ?></button>
						<span class="chattermate-muted"><?php esc_html_e( 'Changes go live on your site straight away.', 'chattermate-chat' ); ?></span>
					</div>
				</form>
				<form id="chattermate-check-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="chattermate_check_connection">
					<?php wp_nonce_field( 'chattermate_check_connection' ); ?>
				</form>
			</div>
			<aside class="chattermate-side">
				<?php chattermate_render_preview_card( $options, $connection ); ?>
				<?php chattermate_render_quick_links( $options ); ?>
			</aside>
		</div>
	</div>
	<?php
}

/**
 * Screen header: logo, title, status pill and dashboard link.
 *
 * @param array $options Settings.
 * @param array $view    From chattermate_connection_view().
 */
function chattermate_render_header( $options, $view ) {
	?>
	<div class="chattermate-header">
		<?php chattermate_render_logo( 40 ); ?>
		<div class="chattermate-header__text">
			<h1><?php esc_html_e( 'ChatterMate', 'chattermate-chat' ); ?></h1>
			<p><?php esc_html_e( 'AI customer support on every page of your site', 'chattermate-chat' ); ?></p>
		</div>
		<span class="chattermate-pill chattermate-pill--<?php echo esc_attr( $view['tone'] ); ?>"><?php echo esc_html( $view['pill'] ); ?></span>
		<a class="chattermate-button chattermate-button--primary chattermate-header__cta" href="<?php echo esc_url( chattermate_app_url( $options ) . '/' ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Open ChatterMate dashboard', 'chattermate-chat' ); ?>
			<span aria-hidden="true">&#8599;</span>
			<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'chattermate-chat' ); ?></span>
		</a>
	</div>
	<?php
}

/**
 * First-run panel, shown until a Widget ID is saved.
 *
 * @param array $options Settings.
 */
function chattermate_render_first_run( $options ) {
	$app = chattermate_app_url( $options );
	?>
	<section class="chattermate-hero" aria-labelledby="chattermate-hero-title">
		<div class="chattermate-hero__copy">
			<span class="chattermate-badge"><?php esc_html_e( 'Setup takes about 2 minutes', 'chattermate-chat' ); ?></span>
			<h2 id="chattermate-hero-title"><?php esc_html_e( 'Support rebuilt around AI, on every page of your site', 'chattermate-chat' ); ?></h2>
			<p><?php esc_html_e( 'ChatterMate answers from your own content, hands over to your team when it should, and turns hard questions into tickets.', 'chattermate-chat' ); ?></p>
			<div class="chattermate-hero__actions">
				<?php if ( chattermate_connect_available( $options ) ) : ?>
					<a class="chattermate-button chattermate-button--lime" href="<?php echo esc_url( chattermate_connect_start_url() ); ?>"><?php esc_html_e( 'Connect with ChatterMate', 'chattermate-chat' ); ?></a>
				<?php endif; ?>
				<a class="chattermate-button chattermate-button--outline-light" href="<?php echo esc_url( $app . '/signup' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Create free account', 'chattermate-chat' ); ?> <span aria-hidden="true">&#8599;</span></a>
			</div>
		</div>
		<ol class="chattermate-steps">
			<li><span class="chattermate-steps__n">1</span><span><strong><?php esc_html_e( 'Create your account.', 'chattermate-chat' ); ?></strong><br><?php esc_html_e( 'It is free to start.', 'chattermate-chat' ); ?></span></li>
			<li><span class="chattermate-steps__n">2</span><span><strong><?php esc_html_e( 'Train it on your site.', 'chattermate-chat' ); ?></strong><br><?php esc_html_e( 'Add your website, docs and FAQs.', 'chattermate-chat' ); ?></span></li>
			<li><span class="chattermate-steps__n">3</span><span><strong><?php esc_html_e( 'Connect this site.', 'chattermate-chat' ); ?></strong><br><?php esc_html_e( 'Click Connect with ChatterMate and pick your agent, or paste its Widget ID below.', 'chattermate-chat' ); ?></span></li>
		</ol>
	</section>
	<?php
}

/**
 * Connect card: Widget ID, status and links.
 *
 * @param array $options Settings.
 * @param array $view    From chattermate_connection_view().
 */
function chattermate_render_connect_card( $options, $view ) {
	$app = chattermate_app_url( $options );
	?>
	<section class="chattermate-card" aria-labelledby="chattermate-connect-title">
		<h2 id="chattermate-connect-title"><?php esc_html_e( 'Connect', 'chattermate-chat' ); ?></h2>
		<div class="chattermate-field">
			<label for="chattermate-widget-id"><?php esc_html_e( 'Widget ID', 'chattermate-chat' ); ?></label>
			<div class="chattermate-inline">
				<input type="text" id="chattermate-widget-id" class="chattermate-input chattermate-input--mono" name="<?php echo esc_attr( CHATTERMATE_OPTION ); ?>[widget_id]" value="<?php echo esc_attr( $options['widget_id'] ); ?>" autocomplete="off" spellcheck="false" placeholder="<?php esc_attr_e( 'e.g. d63483f1-3954-4a94-a3f7-bb5039dda633', 'chattermate-chat' ); ?>" aria-describedby="chattermate-status"<?php echo 'error' === $view['tone'] ? ' aria-invalid="true"' : ''; ?>>
				<?php if ( '' !== $options['widget_id'] ) : ?>
					<button type="submit" form="chattermate-check-form" class="chattermate-button chattermate-button--outline"><?php esc_html_e( 'Check again', 'chattermate-chat' ); ?></button>
				<?php endif; ?>
			</div>
		</div>
		<div id="chattermate-status" class="chattermate-status chattermate-status--<?php echo esc_attr( $view['tone'] ); ?>" role="status">
			<strong><?php echo esc_html( $view['title'] ); ?></strong>
			<span><?php echo esc_html( $view['detail'] ); ?></span>
		</div>
		<p class="chattermate-links">
			<?php if ( chattermate_connect_available( $options ) ) : ?>
				<a class="chattermate-button chattermate-button--primary" href="<?php echo esc_url( chattermate_connect_start_url() ); ?>"><?php echo '' === $options['widget_id'] ? esc_html__( 'Connect with ChatterMate', 'chattermate-chat' ) : esc_html__( 'Connect a different agent', 'chattermate-chat' ); ?></a>
			<?php endif; ?>
			<a class="chattermate-button chattermate-button--outline" href="<?php echo esc_url( $app . '/ai-agents' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get your Widget ID', 'chattermate-chat' ); ?> <span aria-hidden="true">&#8599;</span></a>
			<a class="chattermate-button chattermate-button--outline" href="<?php echo esc_url( $app . '/signup' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Create free account', 'chattermate-chat' ); ?> <span aria-hidden="true">&#8599;</span></a>
		</p>
	</section>
	<?php
}

/**
 * "Where the chat appears" card: page rules, admin and checkout options, load timing.
 *
 * @param array $options Settings.
 */
function chattermate_render_visibility_card( $options ) {
	$name = CHATTERMATE_OPTION;
	?>
	<section class="chattermate-card" aria-labelledby="chattermate-visibility-title">
		<h2 id="chattermate-visibility-title"><?php esc_html_e( 'Where the chat appears', 'chattermate-chat' ); ?></h2>

		<fieldset class="chattermate-fieldset">
			<legend><?php esc_html_e( 'Show the chat', 'chattermate-chat' ); ?></legend>
			<?php foreach ( chattermate_visibility_modes() as $mode => $label ) : ?>
				<label class="chattermate-choice">
					<input type="radio" name="<?php echo esc_attr( $name ); ?>[visibility_mode]" value="<?php echo esc_attr( $mode ); ?>" data-chattermate-mode <?php checked( $options['visibility_mode'], $mode ); ?>>
					<span><?php echo esc_html( $label ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<?php chattermate_render_page_rules( $options ); ?>

		<hr class="chattermate-divider">

		<label class="chattermate-check">
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[hide_for_admins]" value="1" <?php checked( $options['hide_for_admins'] ); ?>>
			<span><strong><?php esc_html_e( 'Hide from logged-in administrators', 'chattermate-chat' ); ?></strong><br>
			<span class="chattermate-muted"><?php esc_html_e( 'The live preview on this screen still works.', 'chattermate-chat' ); ?></span></span>
		</label>

		<?php if ( chattermate_woocommerce_active() ) : ?>
			<label class="chattermate-check">
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[hide_woo_checkout]" value="1" <?php checked( $options['hide_woo_checkout'] ); ?>>
				<span><strong><?php esc_html_e( 'Hide on the WooCommerce cart and checkout', 'chattermate-chat' ); ?></strong> <span class="chattermate-tag"><?php esc_html_e( 'WooCommerce', 'chattermate-chat' ); ?></span><br>
				<span class="chattermate-muted"><?php esc_html_e( 'Keeps buyers focused on paying.', 'chattermate-chat' ); ?></span></span>
			</label>
		<?php elseif ( $options['hide_woo_checkout'] ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[hide_woo_checkout]" value="1">
		<?php endif; ?>

		<hr class="chattermate-divider">

		<fieldset class="chattermate-fieldset">
			<legend><?php esc_html_e( 'Load the chat', 'chattermate-chat' ); ?></legend>
			<?php foreach ( chattermate_load_timings() as $timing => $label ) : ?>
				<label class="chattermate-check">
					<input type="radio" name="<?php echo esc_attr( $name ); ?>[load_timing]" value="<?php echo esc_attr( $timing ); ?>" <?php checked( $options['load_timing'], $timing ); ?>>
					<span><?php echo esc_html( $label ); ?>
					<?php if ( 'interaction' === $timing ) : ?>
						<span class="chattermate-tag chattermate-tag--lime"><?php esc_html_e( 'Best for PageSpeed', 'chattermate-chat' ); ?></span>
					<?php endif; ?>
					</span>
				</label>
			<?php endforeach; ?>
		</fieldset>
	</section>
	<?php
}

/**
 * The page and page-group picker used by "only on" and "except" rules.
 *
 * @param array $options Settings.
 */
function chattermate_render_page_rules( $options ) {
	$name  = CHATTERMATE_OPTION;
	$pages = chattermate_pickable_pages( $options['visibility_pages'] );
	?>
	<div class="chattermate-rules" data-chattermate-rules<?php echo 'all' === $options['visibility_mode'] ? ' hidden' : ''; ?>>
		<fieldset class="chattermate-fieldset">
			<legend><?php esc_html_e( 'Groups of pages', 'chattermate-chat' ); ?></legend>
			<div class="chattermate-chips">
				<?php foreach ( chattermate_content_types() as $type => $label ) : ?>
					<label class="chattermate-chip">
						<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[visibility_types][]" value="<?php echo esc_attr( $type ); ?>" <?php checked( in_array( $type, $options['visibility_types'], true ) ); ?>>
						<span><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
				<?php
				// A saved WooCommerce group stays saved while WooCommerce is off.
				foreach ( array_diff( $options['visibility_types'], array_keys( chattermate_content_types() ) ) as $kept_type ) :
					?>
					<input type="hidden" name="<?php echo esc_attr( $name ); ?>[visibility_types][]" value="<?php echo esc_attr( $kept_type ); ?>">
				<?php endforeach; ?>
			</div>
		</fieldset>

		<fieldset class="chattermate-fieldset">
			<legend><?php esc_html_e( 'Pages', 'chattermate-chat' ); ?></legend>
			<?php if ( empty( $pages ) ) : ?>
				<p class="chattermate-muted"><?php esc_html_e( 'You have no published pages yet.', 'chattermate-chat' ); ?></p>
			<?php else : ?>
				<label class="screen-reader-text" for="chattermate-page-filter"><?php esc_html_e( 'Find a page', 'chattermate-chat' ); ?></label>
				<input type="search" id="chattermate-page-filter" class="chattermate-input" placeholder="<?php esc_attr_e( 'Find a page…', 'chattermate-chat' ); ?>" data-chattermate-filter>
				<ul class="chattermate-page-list" data-chattermate-pages>
					<?php foreach ( $pages as $page ) : ?>
						<li>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[visibility_pages][]" value="<?php echo esc_attr( $page->ID ); ?>" <?php checked( in_array( (int) $page->ID, array_map( 'intval', $options['visibility_pages'] ), true ) ); ?>>
								<span><?php echo esc_html( '' !== $page->post_title ? $page->post_title : __( '(no title)', 'chattermate-chat' ) ); ?></span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="chattermate-muted" data-chattermate-empty hidden><?php esc_html_e( 'No pages match.', 'chattermate-chat' ); ?></p>
			<?php endif; ?>
		</fieldset>
	</div>
	<?php
}

/**
 * "Personal answers" card: page details for the AI and signed-in customers.
 *
 * @param array $options Settings.
 */
function chattermate_render_personal_card( $options ) {
	$name     = CHATTERMATE_OPTION;
	$has_key  = '' !== chattermate_api_key();
	$apps_url = chattermate_app_url( $options ) . '/settings/widget-apps';
	?>
	<section class="chattermate-card" aria-labelledby="chattermate-personal-title">
		<h2 id="chattermate-personal-title"><?php esc_html_e( 'Personal answers', 'chattermate-chat' ); ?></h2>

		<label class="chattermate-check">
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[share_context]" value="1" <?php checked( $options['share_context'] ); ?>>
			<span><strong><?php esc_html_e( 'Tell the AI what the visitor is looking at', 'chattermate-chat' ); ?></strong><br>
			<span class="chattermate-muted">
				<?php
				if ( chattermate_woocommerce_active() ) {
					esc_html_e( 'The page they are on and, on product pages, the product, price and stock. When they open the chat, what is in their cart.', 'chattermate-chat' );
				} else {
					esc_html_e( 'The kind of page they are on and its title.', 'chattermate-chat' );
				}
				?>
			</span></span>
		</label>

		<hr class="chattermate-divider">

		<label class="chattermate-check">
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[identify_users]" value="1" data-chattermate-identity <?php checked( $options['identify_users'] ); ?>>
			<span><strong><?php esc_html_e( 'Recognise signed-in customers', 'chattermate-chat' ); ?></strong><br>
			<span class="chattermate-muted"><?php esc_html_e( 'Visitors who are logged in to this site chat under their name and email, so your team knows who they are and they keep their conversations on any device. Their name and email are sent to ChatterMate, which trusts that the email is theirs: turn this on only if your site confirms email addresses.', 'chattermate-chat' ); ?></span></span>
		</label>

		<div class="chattermate-field chattermate-indent" data-chattermate-identity-fields<?php echo $options['identify_users'] ? '' : ' hidden'; ?>>
			<label for="chattermate-api-key"><?php esc_html_e( 'Widget app API key', 'chattermate-chat' ); ?></label>
			<input type="password" id="chattermate-api-key" class="chattermate-input chattermate-input--mono" name="<?php echo esc_attr( $name ); ?>[api_key]" value="" autocomplete="new-password" spellcheck="false" placeholder="<?php echo $has_key ? esc_attr__( 'Saved. Paste a new key to replace it.', 'chattermate-chat' ) : esc_attr__( 'Paste the API key', 'chattermate-chat' ); ?>">
			<p class="chattermate-muted">
				<?php esc_html_e( 'Create one in ChatterMate under Settings, then Widget Apps. It stays on your server; visitors only ever get their own short-lived token.', 'chattermate-chat' ); ?>
				<a href="<?php echo esc_url( $apps_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Widget Apps', 'chattermate-chat' ); ?> <span aria-hidden="true">&#8599;</span></a>
			</p>
			<?php if ( $has_key ) : ?>
				<label class="chattermate-check">
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[api_key_remove]" value="1">
					<span><?php esc_html_e( 'Remove the saved key', 'chattermate-chat' ); ?></span>
				</label>
			<?php elseif ( $options['identify_users'] ) : ?>
				<p class="chattermate-muted"><strong><?php esc_html_e( 'Add a key to turn this on.', 'chattermate-chat' ); ?></strong></p>
			<?php endif; ?>
			<?php if ( $has_key && get_transient( CHATTERMATE_KEY_REJECTED_TRANSIENT ) ) : ?>
				<div class="chattermate-status chattermate-status--error" role="status">
					<strong><?php esc_html_e( 'ChatterMate rejected this API key', 'chattermate-chat' ); ?></strong>
					<span><?php esc_html_e( 'Signed-in visitors chat anonymously for now. Check that the key is from the same ChatterMate organization as the widget, or create a new one and paste it here.', 'chattermate-chat' ); ?></span>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

/**
 * Published pages for the picker, by title: the first 500, plus every page
 * already chosen so a saved choice never drops off the list.
 *
 * @param int[] $chosen Saved page IDs.
 * @return WP_Post[]
 */
function chattermate_pickable_pages( $chosen ) {
	$query = array(
		'post_type'              => 'page',
		'post_status'            => 'publish',
		'orderby'                => 'title',
		'order'                  => 'ASC',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	);

	$pages = get_posts( array_merge( $query, array( 'numberposts' => 500 ) ) );
	$ids   = wp_list_pluck( $pages, 'ID' );

	$missing = array_diff( array_map( 'absint', (array) $chosen ), $ids );
	if ( $missing ) {
		$pages = array_merge( $pages, get_posts( array_merge( $query, array( 'post__in' => $missing, 'numberposts' => count( $missing ) ) ) ) );
	}

	return $pages;
}

/**
 * Advanced card: script and API URLs for self-hosted ChatterMate.
 *
 * @param array $options Settings.
 */
function chattermate_render_advanced_card( $options ) {
	$name     = CHATTERMATE_OPTION;
	$open     = '' !== $options['script_url'] || '' !== $options['api_url'];
	$editable = chattermate_can_edit_urls();
	$disabled = $editable ? '' : ' disabled';
	?>
	<details class="chattermate-card chattermate-advanced"<?php echo $open ? ' open' : ''; ?>>
		<summary><span class="chattermate-advanced__title"><?php esc_html_e( 'Advanced', 'chattermate-chat' ); ?></span> <span class="chattermate-muted"><?php esc_html_e( 'For self-hosted ChatterMate', 'chattermate-chat' ); ?></span></summary>
		<div class="chattermate-field">
			<label for="chattermate-script-url"><?php esc_html_e( 'Widget script URL', 'chattermate-chat' ); ?></label>
			<input type="url" id="chattermate-script-url" class="chattermate-input" name="<?php echo esc_attr( $name ); ?>[script_url]" value="<?php echo esc_attr( $options['script_url'] ); ?>" placeholder="<?php echo esc_attr( CHATTERMATE_DEFAULT_SCRIPT_URL ); ?>"<?php echo esc_attr( $disabled ); ?>>
			<p class="chattermate-muted"><?php esc_html_e( 'Leave empty to use ChatterMate cloud. Self-hosted: your app URL followed by /webclient/chattermate.min.js.', 'chattermate-chat' ); ?></p>
		</div>
		<div class="chattermate-field">
			<label for="chattermate-api-url"><?php esc_html_e( 'API URL', 'chattermate-chat' ); ?></label>
			<input type="url" id="chattermate-api-url" class="chattermate-input" name="<?php echo esc_attr( $name ); ?>[api_url]" value="<?php echo esc_attr( $options['api_url'] ); ?>" placeholder="<?php echo esc_attr( CHATTERMATE_DEFAULT_API_URL ); ?>"<?php echo esc_attr( $disabled ); ?>>
			<p class="chattermate-muted"><?php esc_html_e( 'Leave empty to use ChatterMate cloud. Self-hosted: your API URL ending in /api/v1.', 'chattermate-chat' ); ?></p>
		</div>
		<?php if ( ! $editable ) : ?>
			<p class="chattermate-muted"><?php esc_html_e( 'Only a network administrator can change these URLs, because they decide which script runs on your site.', 'chattermate-chat' ); ?></p>
		<?php endif; ?>
	</details>
	<?php
}

/**
 * Live preview card. The widget loads only when the button is clicked.
 *
 * @param array      $options    Settings.
 * @param array|null $connection Cached check.
 */
function chattermate_render_preview_card( $options, $connection ) {
	?>
	<section class="chattermate-card chattermate-card--ink" aria-labelledby="chattermate-preview-title">
		<span class="chattermate-badge"><?php esc_html_e( 'Live preview', 'chattermate-chat' ); ?></span>
		<h2 id="chattermate-preview-title"><?php esc_html_e( 'Try your widget here', 'chattermate-chat' ); ?></h2>
		<?php if ( chattermate_can_preview( $options, $connection ) ) : ?>
			<p data-chattermate-preview-text><?php esc_html_e( 'Loads the real chat on this page so you can ask it what your customers ask.', 'chattermate-chat' ); ?></p>
			<button type="button" class="chattermate-button chattermate-button--lime"
				data-chattermate-preview
				data-widget-id="<?php echo esc_attr( $options['widget_id'] ); ?>"
				data-script-url="<?php echo esc_url( chattermate_script_url( $options ) ); ?>"
				data-api-url="<?php echo esc_url( '' !== $options['api_url'] ? chattermate_api_url( $options ) : '' ); ?>"
				data-identity="<?php echo esc_attr( (string) wp_json_encode( chattermate_identity_config( $options ) ) ); ?>"
				data-loaded-text="<?php esc_attr_e( 'Your chat is at the bottom right of this page. Ask it what your customers ask.', 'chattermate-chat' ); ?>"
				data-open-text="<?php esc_attr_e( 'Open the chat', 'chattermate-chat' ); ?>"><?php esc_html_e( 'Show live preview', 'chattermate-chat' ); ?></button>
		<?php elseif ( '' === $options['widget_id'] ) : ?>
			<p><?php esc_html_e( 'Save a Widget ID to try the chat here.', 'chattermate-chat' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Fix the connection above to try the chat here.', 'chattermate-chat' ); ?></p>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * Quick links into ChatterMate, the docs and support.
 *
 * @param array $options Settings.
 */
function chattermate_render_quick_links( $options ) {
	$app   = chattermate_app_url( $options );
	$links = array(
		array( $app . '/', __( 'Dashboard', 'chattermate-chat' ), __( 'Conversations and tickets', 'chattermate-chat' ) ),
		array( $app . '/knowledge', __( 'Train your AI', 'chattermate-chat' ), __( 'Add pages, docs and FAQs', 'chattermate-chat' ) ),
		array( $app . '/ai-agents', __( 'Customise the widget', 'chattermate-chat' ), __( 'Colours, launcher and greeting', 'chattermate-chat' ) ),
		array( CHATTERMATE_DOCS_URL, __( 'Documentation', 'chattermate-chat' ), __( 'Widget setup guide', 'chattermate-chat' ) ),
		array( CHATTERMATE_SUPPORT_URL, __( 'Get support', 'chattermate-chat' ), __( 'Help center and contact', 'chattermate-chat' ) ),
	);
	?>
	<section class="chattermate-card chattermate-card--flush" aria-labelledby="chattermate-links-title">
		<h2 id="chattermate-links-title" class="chattermate-eyebrow"><?php esc_html_e( 'Quick links', 'chattermate-chat' ); ?></h2>
		<ul class="chattermate-quick-links">
			<?php foreach ( $links as $link ) : ?>
				<li>
					<a href="<?php echo esc_url( $link[0] ); ?>" target="_blank" rel="noopener noreferrer">
						<span><strong><?php echo esc_html( $link[1] ); ?></strong><span class="chattermate-muted"><?php echo esc_html( $link[2] ); ?></span></span>
						<span aria-hidden="true">&#8599;</span>
						<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'chattermate-chat' ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php
}
