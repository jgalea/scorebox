<?php
/**
 * Meta box template for classic editor.
 *
 * @package ScoreBox
 * @var array $data Review data merged with defaults.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- File is included inside a class method; variables are function-scoped, not global.

$pros     = is_array( $data['pros'] ) ? $data['pros'] : array();
$cons     = is_array( $data['cons'] ) ? $data['cons'] : array();
$criteria = is_array( $data['criteria'] ) ? $data['criteria'] : array();
if ( empty( $pros ) ) {
	$pros = array( '' );
}
if ( empty( $cons ) ) {
	$cons = array( '' );
}
if ( empty( $criteria ) ) {
	$criteria = array( array( 'label' => '', 'rating' => 0 ) );
}
$use_criteria = ! empty( $data['use_criteria'] );
?>
<div class="scorebox-meta-box">
	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="scorebox_review_heading"><?php esc_html_e( 'Heading', 'scorebox' ); ?></label>
			</th>
			<td>
				<input type="text" id="scorebox_review_heading" name="scorebox_review_heading"
					value="<?php echo esc_attr( $data['heading'] ); ?>" class="large-text"
					placeholder="<?php esc_attr_e( 'e.g., Our Verdict', 'scorebox' ); ?>">
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="scorebox_review_style"><?php esc_html_e( 'Style', 'scorebox' ); ?></label>
			</th>
			<td>
				<?php
				$styles = scorebox_get_styles();
				$current_style = isset( $data['style'] ) ? $data['style'] : '';
				?>
				<select id="scorebox_review_style" name="scorebox_review_style">
					<option value="" <?php selected( $current_style, '' ); ?>><?php esc_html_e( 'Use global default', 'scorebox' ); ?></option>
					<?php foreach ( $styles as $style_key => $style_config ) : ?>
						<option value="<?php echo esc_attr( $style_key ); ?>" <?php selected( $current_style, $style_key ); ?>><?php echo esc_html( $style_config['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="scorebox_review_rating_type"><?php esc_html_e( 'Rating Type', 'scorebox' ); ?></label>
			</th>
			<td>
				<?php $rating_types = scorebox_get_rating_types(); ?>
				<?php if ( count( $rating_types ) > 1 ) : ?>
					<select id="scorebox_review_rating_type" name="scorebox_review_rating_type">
						<?php foreach ( $rating_types as $type_key => $type_config ) : ?>
							<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $data['rating_type'], $type_key ); ?>><?php echo esc_html( $type_config['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input type="hidden" name="scorebox_review_rating_type" value="star">
					<span><?php esc_html_e( 'Star (0-5)', 'scorebox' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>

		<?php do_action( 'scorebox_meta_box_fields', $data ); ?>

		<tr>
			<th scope="row">
				<label for="scorebox_review_rating"><?php esc_html_e( 'Rating', 'scorebox' ); ?></label>
			</th>
			<td>
				<?php
				$rating_types = scorebox_get_rating_types();
				$rating_type  = isset( $data['rating_type'] ) ? $data['rating_type'] : 'star';
				$type_config  = isset( $rating_types[ $rating_type ] ) ? $rating_types[ $rating_type ] : $rating_types['star'];
				$rating_max   = $type_config['max'];
				$rating_step  = $type_config['step'];
				$rating_desc  = $type_config['label'];
				?>
				<input type="number" id="scorebox_review_rating" name="scorebox_review_rating"
					value="<?php echo esc_attr( $data['rating'] ); ?>"
					min="0" max="<?php echo esc_attr( $rating_max ); ?>" step="<?php echo esc_attr( $rating_step ); ?>" style="width: 80px;">
				<span class="description" id="scorebox-rating-description"><?php echo esc_html( $rating_desc ); ?></span>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="scorebox_review_position"><?php esc_html_e( 'Position', 'scorebox' ); ?></label>
			</th>
			<td>
				<select id="scorebox_review_position" name="scorebox_review_position">
					<option value="" <?php selected( $data['position'], '' ); ?>><?php esc_html_e( 'Use global default', 'scorebox' ); ?></option>
					<option value="bottom" <?php selected( $data['position'], 'bottom' ); ?>><?php esc_html_e( 'After content', 'scorebox' ); ?></option>
					<option value="top" <?php selected( $data['position'], 'top' ); ?>><?php esc_html_e( 'Before content', 'scorebox' ); ?></option>
					<option value="both" <?php selected( $data['position'], 'both' ); ?>><?php esc_html_e( 'Before and after content', 'scorebox' ); ?></option>
					<option value="manual" <?php selected( $data['position'], 'manual' ); ?>><?php esc_html_e( 'Manual (block/shortcode only)', 'scorebox' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Where to display the review box. Leave on global default unless you want a per-post override.', 'scorebox' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="scorebox_review_summary"><?php esc_html_e( 'Summary', 'scorebox' ); ?></label>
			</th>
			<td>
				<textarea id="scorebox_review_summary" name="scorebox_review_summary" rows="3" class="large-text"
					placeholder="<?php esc_attr_e( 'Brief review summary...', 'scorebox' ); ?>"><?php echo esc_textarea( $data['summary'] ); ?></textarea>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Pros', 'scorebox' ); ?></th>
			<td>
				<div class="scorebox-repeater" data-field="pros">
					<?php foreach ( $pros as $pro ) : ?>
						<div class="scorebox-repeater__row">
							<input type="text" name="scorebox_review_pros[]" value="<?php echo esc_attr( $pro ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Add a pro...', 'scorebox' ); ?>">
							<button type="button" class="button scorebox-repeater__remove" aria-label="<?php esc_attr_e( 'Remove', 'scorebox' ); ?>">&times;</button>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button scorebox-repeater__add" data-target="pros"><?php esc_html_e( '+ Add Pro', 'scorebox' ); ?></button>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Cons', 'scorebox' ); ?></th>
			<td>
				<div class="scorebox-repeater" data-field="cons">
					<?php foreach ( $cons as $con ) : ?>
						<div class="scorebox-repeater__row">
							<input type="text" name="scorebox_review_cons[]" value="<?php echo esc_attr( $con ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Add a con...', 'scorebox' ); ?>">
							<button type="button" class="button scorebox-repeater__remove" aria-label="<?php esc_attr_e( 'Remove', 'scorebox' ); ?>">&times;</button>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button scorebox-repeater__add" data-target="cons"><?php esc_html_e( '+ Add Con', 'scorebox' ); ?></button>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="scorebox_review_cta_text"><?php esc_html_e( 'CTA Button Text', 'scorebox' ); ?></label>
			</th>
			<td>
				<input type="text" id="scorebox_review_cta_text" name="scorebox_review_cta_text"
					value="<?php echo esc_attr( $data['cta_text'] ); ?>" class="regular-text"
					placeholder="<?php esc_attr_e( 'e.g., Visit Website', 'scorebox' ); ?>">
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="scorebox_review_cta_url"><?php esc_html_e( 'CTA Button URL', 'scorebox' ); ?></label>
			</th>
			<td>
				<input type="url" id="scorebox_review_cta_url" name="scorebox_review_cta_url"
					value="<?php echo esc_url( $data['cta_url'] ); ?>" class="large-text"
					placeholder="https://">
			</td>
		</tr>
	</table>

	<hr>
	<h4><?php esc_html_e( 'Schema Settings', 'scorebox' ); ?></h4>
	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="scorebox_review_schema_type"><?php esc_html_e( 'Schema Type', 'scorebox' ); ?></label>
			</th>
			<td>
				<select id="scorebox_review_schema_type" name="scorebox_review_schema_type">
					<option value="Product" <?php selected( $data['schema_type'], 'Product' ); ?>><?php esc_html_e( 'Product', 'scorebox' ); ?></option>
					<option value="SoftwareApplication" <?php selected( $data['schema_type'], 'SoftwareApplication' ); ?>><?php esc_html_e( 'SoftwareApplication', 'scorebox' ); ?></option>
					<option value="Thing" <?php selected( $data['schema_type'], 'Thing' ); ?>><?php esc_html_e( 'Thing', 'scorebox' ); ?></option>
				</select>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="scorebox_review_product_name"><?php esc_html_e( 'Product / Item Name', 'scorebox' ); ?></label>
			</th>
			<td>
				<input type="text" id="scorebox_review_product_name" name="scorebox_review_product_name"
					value="<?php echo esc_attr( $data['product_name'] ); ?>" class="regular-text"
					placeholder="<?php esc_attr_e( 'Defaults to post title', 'scorebox' ); ?>">
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="scorebox_review_price"><?php esc_html_e( 'Price', 'scorebox' ); ?></label>
			</th>
			<td>
				<input type="text" id="scorebox_review_price" name="scorebox_review_price"
					value="<?php echo esc_attr( $data['price'] ); ?>" style="width: 120px;"
					placeholder="<?php esc_attr_e( 'e.g., 49.99', 'scorebox' ); ?>">
				<select id="scorebox_review_currency" name="scorebox_review_currency" style="width: 80px;">
					<option value="USD" <?php selected( $data['currency'], 'USD' ); ?>>USD</option>
					<option value="EUR" <?php selected( $data['currency'], 'EUR' ); ?>>EUR</option>
					<option value="GBP" <?php selected( $data['currency'], 'GBP' ); ?>>GBP</option>
				</select>
				<p class="description"><?php esc_html_e( 'Used in schema.org offers. Leave blank for free.', 'scorebox' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="scorebox_review_author_name"><?php esc_html_e( 'Author Name Override', 'scorebox' ); ?></label>
			</th>
			<td>
				<input type="text" id="scorebox_review_author_name" name="scorebox_review_author_name"
					value="<?php echo esc_attr( $data['author_name'] ); ?>" class="regular-text"
					placeholder="<?php esc_attr_e( 'Leave blank to use default from settings', 'scorebox' ); ?>">
			</td>
		</tr>
	</table>

</div>
