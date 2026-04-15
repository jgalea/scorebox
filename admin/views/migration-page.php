<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- File is included inside a class method; variables are function-scoped, not global.

$sources = scorebox_get_migration_sources();
?>
<div class="wrap scorebox-admin">
	<h1 class="scorebox-admin__title">
		<span class="dashicons dashicons-star-filled"></span>
		<?php esc_html_e( 'ScoreBox Migration', 'scorebox' ); ?>
	</h1>

	<div id="scorebox-migration-status" style="display:none;" class="notice">
		<p id="scorebox-migration-message"></p>
	</div>

	<?php foreach ( $sources as $source_key => $source_config ) : ?>
		<?php
		$posts = call_user_func( $source_config['detect'] );
		$total = count( $posts );
		?>

		<h2>
			<?php
			/* translators: %s: migration source label (e.g. WP Review Pro) */
			echo esc_html( sprintf( __( 'Migrate from %s', 'scorebox' ), $source_config['label'] ) );
			?>
		</h2>

		<?php if ( 0 === $total ) : ?>
			<div class="notice notice-info">
				<p>
					<?php
					/* translators: %s: migration source label */
					echo esc_html( sprintf( __( 'No posts with %s data found.', 'scorebox' ), $source_config['label'] ) );
					?>
				</p>
			</div>
		<?php else : ?>
			<div class="notice notice-warning">
				<p>
					<?php
					/* translators: 1: number of posts, 2: migration source label */
					echo esc_html( sprintf( __( 'Found %1$d posts with %2$s data.', 'scorebox' ), $total, $source_config['label'] ) );
					?>
				</p>
			</div>

			<p>
				<button type="button" class="button button-primary scorebox-migrate-all-btn" data-source="<?php echo esc_attr( $source_key ); ?>">
					<?php
					/* translators: %d: number of posts */
					echo esc_html( sprintf( __( 'Migrate All (%d posts)', 'scorebox' ), $total ) );
					?>
				</button>
			</p>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 50px;"><?php esc_html_e( 'ID', 'scorebox' ); ?></th>
						<th><?php esc_html_e( 'Post Title', 'scorebox' ); ?></th>
						<?php foreach ( $source_config['columns'] as $col ) : ?>
							<th style="width: 80px;"><?php echo esc_html( ucfirst( $col ) ); ?></th>
						<?php endforeach; ?>
						<th style="width: 100px;"><?php esc_html_e( 'Status', 'scorebox' ); ?></th>
						<th style="width: 120px;"><?php esc_html_e( 'Action', 'scorebox' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $posts as $post ) : ?>
						<?php
						$existing = get_post_meta( $post->ID, '_scorebox_review', true );
						$already  = ! empty( $existing );
						$row_data = is_callable( $source_config['row_data'] ) ? call_user_func( $source_config['row_data'], $post ) : array();
						?>
						<tr id="scorebox-migrate-row-<?php echo esc_attr( $post->ID ); ?>">
							<td><?php echo esc_html( $post->ID ); ?></td>
							<td><a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( get_the_title( $post->ID ) ); ?></a></td>
							<?php foreach ( $source_config['columns'] as $col ) : ?>
								<td><?php echo isset( $row_data[ $col ] ) ? wp_kses_post( $row_data[ $col ] ) : '-'; ?></td>
							<?php endforeach; ?>
							<td class="scorebox-migrate-status">
								<?php if ( $already ) : ?>
									<span style="color: #0073aa;"><?php esc_html_e( 'Already migrated', 'scorebox' ); ?></span>
								<?php else : ?>
									<span style="color: #999;"><?php esc_html_e( 'Pending', 'scorebox' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! $already ) : ?>
									<button type="button" class="button button-small scorebox-migrate-single"
										data-post-id="<?php echo esc_attr( $post->ID ); ?>"
										data-source="<?php echo esc_attr( $source_key ); ?>">
										<?php esc_html_e( 'Migrate', 'scorebox' ); ?>
									</button>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<hr>
	<?php endforeach; ?>
</div>
