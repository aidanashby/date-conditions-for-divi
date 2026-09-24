<?php
/**
 * REST endpoints used by the Visual Builder (SPEC 5.4, 8).
 *
 * Both need a logged-in editor. WordPress cookie auth only sets the user when a valid
 * wp_rest nonce is sent (X-WP-Nonce), so the capability check also enforces the nonce.
 * Neither endpoint returns stored field values.
 *
 * @package DCFD
 */

namespace DCFD;

/**
 * Register routes.
 */
function register_rest_routes(): void {
	$permission = static function (): bool {
		return current_user_can( 'edit_posts' );
	};

	register_rest_route(
		'dcfd/v1',
		'/field',
		[
			'methods'             => 'GET',
			'permission_callback' => $permission,
			'callback'            => __NAMESPACE__ . '\rest_check_field',
			'args'                => [
				'name'       => [
					'type'     => 'string',
					'required' => true,
				],
				'post_types' => [
					'type'    => 'string',
					'default' => '',
				],
			],
		]
	);

	register_rest_route(
		'dcfd/v1',
		'/layouts',
		[
			'methods'             => 'GET',
			'permission_callback' => $permission,
			'callback'            => __NAMESPACE__ . '\rest_layouts',
		]
	);
}
add_action( 'rest_api_init', __NAMESPACE__ . '\register_rest_routes' );

/**
 * Is this field name a date field for these post types?
 *
 * @param \WP_REST_Request $request Request.
 */
function rest_check_field( \WP_REST_Request $request ): \WP_REST_Response {
	$name = trim( (string) $request->get_param( 'name' ) );

	if ( ! is_valid_field_name( $name ) ) {
		return new \WP_REST_Response( [ 'status' => 'invalid_name', 'type' => '', 'post_types' => [] ] );
	}

	$post_types = normalize_post_types( array_filter( explode( ',', (string) $request->get_param( 'post_types' ) ) ) );
	$result     = lookup_field( $name, $post_types );

	return new \WP_REST_Response( $result + [ 'queried' => $post_types ] );
}

/**
 * Published Divi Library items, for the empty-state select (SPEC 5.1).
 */
function rest_layouts(): \WP_REST_Response {
	$posts = get_posts(
		[
			'post_type'      => 'et_pb_layout',
			'post_status'    => 'publish',
			'posts_per_page' => 500, // ponytail: flat cap, add search if a site ever has more Library items.
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		]
	);

	$items = [];
	foreach ( $posts as $post ) {
		$terms   = get_the_terms( $post, 'layout_type' );
		$items[] = [
			'id'    => $post->ID,
			'title' => html_entity_decode( get_the_title( $post ), ENT_QUOTES ),
			'type'  => ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->slug : '',
		];
	}

	return new \WP_REST_Response( $items );
}
