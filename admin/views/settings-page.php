<?php
/**
 * Settings page template with tabs.
 *
 * @package ScoreBox
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- File is included inside a class method; variables are function-scoped, not global.

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab switching only.
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
?>
<div class="wrap scorebox-admin">
	<h1 class="scorebox-admin__title">
		<span class="dashicons dashicons-star-filled"></span>
		<?php esc_html_e( 'ScoreBox Settings', 'scorebox' ); ?>
	</h1>

	<nav class="scorebox-tabs">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=scorebox-settings&tab=general' ) ); ?>"
			class="scorebox-tab <?php echo 'general' === $active_tab ? 'scorebox-tab--active' : ''; ?>">
			<span class="dashicons dashicons-admin-generic"></span>
			<?php esc_html_e( 'General', 'scorebox' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=scorebox-settings&tab=appearance' ) ); ?>"
			class="scorebox-tab <?php echo 'appearance' === $active_tab ? 'scorebox-tab--active' : ''; ?>">
			<span class="dashicons dashicons-art"></span>
			<?php esc_html_e( 'Appearance', 'scorebox' ); ?>
		</a>
		<?php do_action( 'scorebox_settings_tabs', $active_tab ); ?>
	</nav>

	<div class="scorebox-settings-content">
		<?php if ( 'general' === $active_tab ) : ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'scorebox_settings_group' );
				?>
				<div class="scorebox-panel">
					<h2 class="scorebox-panel__title"><?php esc_html_e( 'Review Defaults', 'scorebox' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php do_settings_fields( 'scorebox-settings', 'scorebox_general' ); ?>
					<?php do_action( 'scorebox_settings_after_general' ); ?>
					</table>
				</div>

				<div class="scorebox-panel">
					<h2 class="scorebox-panel__title"><?php esc_html_e( 'Post Types', 'scorebox' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Reviews On', 'scorebox' ); ?></th>
							<td>
								<?php
								$options       = get_option( 'scorebox_settings', array() );
								$enabled_types = isset( $options['post_types'] ) ? $options['post_types'] : array( 'post', 'page' );
								$available     = get_post_types( array( 'public' => true ), 'objects' );
								foreach ( $available as $pt ) :
									if ( 'attachment' === $pt->name ) {
										continue;
									}
								?>
									<label style="display: block; margin-bottom: 6px;">
										<input type="checkbox" name="scorebox_settings[post_types][]"
											value="<?php echo esc_attr( $pt->name ); ?>"
											<?php checked( in_array( $pt->name, $enabled_types, true ) ); ?>>
										<?php echo esc_html( $pt->labels->name ); ?>
									</label>
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'Select which post types can have review boxes.', 'scorebox' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button(); ?>
			</form>

		<?php elseif ( 'appearance' === $active_tab ) : ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'scorebox_settings_group' ); ?>

				<div class="scorebox-panel">
					<h2 class="scorebox-panel__title"><?php esc_html_e( 'Style & Colors', 'scorebox' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php do_settings_fields( 'scorebox-settings-appearance', 'scorebox_appearance' ); ?>
					</table>
				</div>

				<div class="scorebox-panel">
					<h2 class="scorebox-panel__title"><?php esc_html_e( 'Preview', 'scorebox' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Preview how the review box will look with current settings. Save to apply changes.', 'scorebox' ); ?></p>
					<div id="scorebox-settings-preview" style="max-width: 600px; margin-top: 16px;">
						<?php
						echo scorebox_render_box( scorebox_get_preview_review(), 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</div>
				</div>

				<?php submit_button(); ?>
			</form>

		<?php endif; ?>
		<?php do_action( 'scorebox_settings_tab_content', $active_tab ); ?>
	</div>
</div>
