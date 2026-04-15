<?php
/**
 * REST API endpoint for review data.
 *
 * Provides /wp-json/scorebox/v1/review/<post_id> for headless/decoupled use.
 *
 * @package ScoreBox
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register REST API routes.
 */
function scorebox_register_rest_routes() {
	register_rest_route(
		'scorebox/v1',
		'/review/(?P<id>\d+)',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'scorebox_rest_get_review',
				'permission_callback' => function ( $request ) {
					$post = get_post( $request->get_param( 'id' ) );
					if ( ! $post ) {
						return true; // Let the callback return 404.
					}
					if ( 'publish' === $post->post_status ) {
						return true;
					}
					return current_user_can( 'read_post', $post->ID );
				},
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
						'sanitize_callback' => 'absint',
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => 'scorebox_rest_update_review',
				'permission_callback' => function ( $request ) {
					return current_user_can( 'edit_post', $request->get_param( 'id' ) );
				},
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
						'sanitize_callback' => 'absint',
					),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'scorebox_register_rest_routes' );

/**
 * GET handler — return review data for a post.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function scorebox_rest_get_review( $request ) {
	$post_id = $request->get_param( 'id' );
	$post    = get_post( $post_id );

	if ( ! $post ) {
		return new WP_Error(
			'scorebox_not_found',
			__( 'Post not found.', 'scorebox' ),
			array( 'status' => 404 )
		);
	}

	$review = scorebox_get_review( $post_id );

	if ( ! $review ) {
		return new WP_REST_Response(
			array(
				'post_id' => $post_id,
				'review'  => null,
			),
			200
		);
	}

	return new WP_REST_Response(
		array(
			'post_id' => $post_id,
			'review'  => $review,
			'schema'  => scorebox_build_schema( $post_id, $review ),
		),
		200
	);
}

/**
 * POST/PUT handler — update review data for a post.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function scorebox_rest_update_review( $request ) {
	$post_id = $request->get_param( 'id' );
	$post    = get_post( $post_id );

	if ( ! $post ) {
		return new WP_Error(
			'scorebox_not_found',
			__( 'Post not found.', 'scorebox' ),
			array( 'status' => 404 )
		);
	}

	$body = $request->get_json_params();
	if ( empty( $body ) ) {
		return new WP_Error(
			'scorebox_invalid_data',
			__( 'Invalid review data.', 'scorebox' ),
			array( 'status' => 400 )
		);
	}

	scorebox_save_review( $post_id, $body );
	$review = scorebox_get_review( $post_id );

	return new WP_REST_Response(
		array(
			'post_id' => $post_id,
			'review'  => $review,
			'updated' => true,
		),
		200
	);
}
