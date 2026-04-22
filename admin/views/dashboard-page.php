<?php
/**
 * Dashboard page template.
 *
 * @package ScoreBox
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- File is included inside a class method; variables are function-scoped, not global.

$options    = get_option( 'scorebox_settings', array() );
$post_types = apply_filters( 'scorebox_post_types', isset( $options['post_types'] ) ? $options['post_types'] : array( 'post', 'page' ) );
// Count reviewed posts.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$total_reviews = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != %s",
		'_scorebox_review',
		''
	)
);

// Get recent reviews.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$recent_ids = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT pm.post_id
		FROM {$wpdb->postmeta} pm
		INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		WHERE pm.meta_key = %s
		AND pm.meta_value != %s
		AND p.post_status IN (%s, %s, %s, %s)
		ORDER BY p.post_modified DESC
		LIMIT %d",
		'_scorebox_review',
		'',
		'publish',
		'draft',
		'pending',
		'private',
		10
	)
);

// Calculate average rating.
$avg_rating = 0;
$rating_count = 0;
if ( $total_reviews > 0 ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$all_meta = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != %s",
			'_scorebox_review',
			''
		)
	);
	$sum = 0;
	foreach ( $all_meta as $raw ) {
		$data = json_decode( $raw, true );
		if ( is_array( $data ) && ! empty( $data['rating'] ) ) {
			$type = ! empty( $data['rating_type'] ) ? $data['rating_type'] : 'star';
			$sum += scorebox_normalize_to_star_scale( $data['rating'], $type );
			$rating_count++;
		}
	}
	if ( $rating_count > 0 ) {
		$avg_rating = round( $sum / $rating_count, 1 );
	}
}

// Count migratable posts across all registered migration sources (WP Review Pro + Pro sources).
$wp_review_count = scorebox_count_all_migratable();

// Latest review — use the most recently modified reviewed post.
$latest_review_label = '—';
$latest_review_title = '';
if ( ! empty( $recent_ids ) ) {
	$latest_post = get_post( $recent_ids[0] );
	if ( $latest_post ) {
		$latest_gmt = strtotime( $latest_post->post_modified_gmt );
		if ( $latest_gmt ) {
			/* translators: %s: human-readable time difference, e.g. "2 days". */
			$latest_review_label = sprintf( __( '%s ago', 'scorebox' ), human_time_diff( $latest_gmt ) );
			$latest_review_title = $latest_post->post_title;
		}
	}
}
?>
<div class="wrap scorebox-admin">
	<h1 class="scorebox-admin__title">
		<span class="dashicons dashicons-star-filled"></span>
		<?php esc_html_e( 'ScoreBox', 'scorebox' ); ?>
		<?php do_action( 'scorebox_admin_dashboard_title_badge' ); ?>
	</h1>

	<div class="scorebox-dashboard">
		<!-- Stats cards -->
		<div class="scorebox-stats">
			<div class="scorebox-stat-card">
				<div class="scorebox-stat-card__icon dashicons dashicons-edit"></div>
				<div class="scorebox-stat-card__content">
					<span class="scorebox-stat-card__value"><?php echo esc_html( $total_reviews ); ?></span>
					<span class="scorebox-stat-card__label"><?php esc_html_e( 'Total Reviews', 'scorebox' ); ?></span>
				</div>
			</div>

			<div class="scorebox-stat-card">
				<div class="scorebox-stat-card__icon dashicons dashicons-star-filled"></div>
				<div class="scorebox-stat-card__content">
					<span class="scorebox-stat-card__value"><?php echo esc_html( $avg_rating ); ?><small>/5</small></span>
					<span class="scorebox-stat-card__label"><?php esc_html_e( 'Average Rating', 'scorebox' ); ?></span>
				</div>
			</div>

			<div class="scorebox-stat-card">
				<div class="scorebox-stat-card__icon dashicons dashicons-migrate"></div>
				<div class="scorebox-stat-card__content">
					<span class="scorebox-stat-card__value"><?php echo esc_html( $wp_review_count ); ?></span>
					<span class="scorebox-stat-card__label">
						<?php if ( $wp_review_count > 0 ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=scorebox-migration' ) ); ?>">
								<?php esc_html_e( 'Ready to Migrate', 'scorebox' ); ?>
							</a>
						<?php else : ?>
							<?php esc_html_e( 'Ready to Migrate', 'scorebox' ); ?>
						<?php endif; ?>
					</span>
				</div>
			</div>

			<div class="scorebox-stat-card"<?php echo $latest_review_title ? ' title="' . esc_attr( $latest_review_title ) . '"' : ''; ?>>
				<div class="scorebox-stat-card__icon dashicons dashicons-clock"></div>
				<div class="scorebox-stat-card__content">
					<span class="scorebox-stat-card__value"><?php echo esc_html( $latest_review_label ); ?></span>
					<span class="scorebox-stat-card__label"><?php esc_html_e( 'Latest Review', 'scorebox' ); ?></span>
				</div>
			</div>
		</div>
		<?php do_action( 'scorebox_admin_dashboard_after_stats' ); ?>

		<div class="scorebox-dashboard__grid">
			<!-- Recent reviews -->
			<div class="scorebox-panel">
				<h2 class="scorebox-panel__title">
					<?php esc_html_e( 'Recent Reviews', 'scorebox' ); ?>
					<?php if ( $total_reviews > 10 ) : ?>
						<span class="scorebox-panel__title-meta">
							<?php
							/* translators: %d: total number of reviews */
							echo esc_html( sprintf( __( 'showing 10 of %d', 'scorebox' ), $total_reviews ) );
							?>
						</span>
					<?php endif; ?>
				</h2>
				<?php if ( empty( $recent_ids ) ) : ?>
					<p class="scorebox-panel__empty"><?php esc_html_e( 'No reviews yet. Add a Review Box block to any post to get started.', 'scorebox' ); ?></p>
				<?php else : ?>
					<table class="scorebox-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Post', 'scorebox' ); ?></th>
								<th><?php esc_html_e( 'Rating', 'scorebox' ); ?></th>
								<th><?php esc_html_e( 'Type', 'scorebox' ); ?></th>
								<th><?php esc_html_e( 'Status', 'scorebox' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $recent_ids as $pid ) : ?>
								<?php
								$post   = get_post( $pid );
								$review = scorebox_get_review( $pid );
								if ( ! $post || ! $review ) {
									continue;
								}
								$r_type  = ! empty( $review['rating_type'] ) ? $review['rating_type'] : 'star';
								$display = $review['rating'];
								switch ( $r_type ) {
									case 'percentage':
										$display = round( $review['rating'] ) . '%';
										break;
									case 'point':
										$display = $review['rating'] . '/10';
										break;
									default:
										$display = $review['rating'] . '/5';
										break;
								}
								?>
								<tr>
									<td>
										<a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>">
											<?php echo esc_html( get_the_title( $pid ) ); ?>
										</a>
									</td>
									<td><strong><?php echo esc_html( $display ); ?></strong></td>
									<td><?php echo esc_html( ucfirst( $r_type ) ); ?></td>
									<td>
										<span class="scorebox-status scorebox-status--<?php echo esc_attr( $post->post_status ); ?>">
											<?php echo esc_html( get_post_status_object( $post->post_status )->label ); ?>
										</span>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="scorebox-panel__footer">
						<a href="<?php echo esc_url( admin_url( 'edit.php?orderby=scorebox_rating&order=DESC' ) ); ?>">
							<?php esc_html_e( 'View all reviewed posts', 'scorebox' ); ?> &rarr;
						</a>
					</p>
				<?php endif; ?>
			</div>

			<!-- Quick links panel -->
			<div class="scorebox-panel">
				<h2 class="scorebox-panel__title"><?php esc_html_e( 'Quick Links', 'scorebox' ); ?></h2>
				<div class="scorebox-quick-links">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=scorebox-settings' ) ); ?>" class="scorebox-quick-link">
						<span class="dashicons dashicons-admin-generic"></span>
						<?php esc_html_e( 'Settings', 'scorebox' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=scorebox-migration' ) ); ?>" class="scorebox-quick-link">
						<span class="dashicons dashicons-migrate"></span>
						<?php esc_html_e( 'Migration Tools', 'scorebox' ); ?>
						<?php if ( $wp_review_count > 0 ) : ?>
							<span class="scorebox-badge scorebox-badge--count"><?php echo esc_html( $wp_review_count ); ?></span>
						<?php endif; ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>" class="scorebox-quick-link">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'New Review Post', 'scorebox' ); ?>
					</a>
				</div>

				<h3 class="scorebox-panel__subtitle"><?php esc_html_e( 'Usage', 'scorebox' ); ?></h3>
				<div class="scorebox-usage-hints">
					<div class="scorebox-hint">
						<strong><?php esc_html_e( 'Block Editor', 'scorebox' ); ?></strong>
						<p><?php esc_html_e( 'Add the "Review Box" block to any post or page.', 'scorebox' ); ?></p>
					</div>
					<div class="scorebox-hint">
						<strong><?php esc_html_e( 'Shortcode', 'scorebox' ); ?></strong>
						<p><code>[scorebox]</code> <?php esc_html_e( 'or', 'scorebox' ); ?> <code>[scorebox id="123"]</code></p>
					</div>
					<div class="scorebox-hint">
						<strong><?php esc_html_e( 'REST API', 'scorebox' ); ?></strong>
						<p><code>/wp-json/scorebox/v1/review/{id}</code></p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
