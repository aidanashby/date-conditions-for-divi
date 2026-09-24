<?php
/**
 * Apply date rules to Loop queries: front end and Visual Builder preview.
 *
 * @package DCFD
 */

namespace DCFD;

/**
 * Loop query types this plugin acts on (SPEC 4). The builder REST endpoint names the
 * post-types query 'post_type' (singular); block attributes use 'post_types'.
 */
const QUERY_TYPES = [ 'post_types', 'post_type', 'current_page' ];

/**
 * Add the rules' meta_query to WP_Query args.
 *
 * @param array $query_args WP_Query args.
 * @param mixed $raw_rules  Untrusted dcfdDateRules value.
 * @param mixed $post_types Post types the Loop queries.
 */
function apply_rules( array $query_args, $raw_rules, $post_types ): array {
	$rules = sanitize_rules( $raw_rules );
	if ( ! $rules ) {
		return $query_args;
	}

	$rules = resolve_rules( $rules, normalize_post_types( $post_types ) );
	if ( ! $rules ) {
		return $query_args;
	}

	$now     = current_datetime();
	$clauses = [];
	foreach ( $rules as $rule ) {
		$clauses[] = rule_clause( $rule['field'], $rule['operator'], $rule['type'], $now );
	}

	return merge_meta_query( $query_args, $clauses );
}

/**
 * Front end: runs before Divi's registry lookup, so the filtered args also decide which
 * cached query may be reused (SPIKE-REPORT Q2). For current_page loops Divi rebuilds the
 * args afterwards but carries meta_query over (LoopUtils::_merge_current_page_loop_sort_args).
 *
 * current_page loops resolve fields against every public post type, as the archive's post
 * type isn't known yet at this point. Posts of types without the field still show.
 *
 * @param array $loop_data Divi loop data: query_args, query_type, post_type.
 * @param array $attrs     Block attributes of the Loop container.
 */
function filter_front_end_loop( $loop_data, $attrs ) {
	if ( ! is_array( $loop_data ) || ! is_array( $attrs ) || empty( $attrs['dcfdDateRules'] ) ) {
		return $loop_data;
	}

	$query_type = $loop_data['query_type'] ?? '';
	if ( ! in_array( $query_type, QUERY_TYPES, true ) || ! isset( $loop_data['query_args'] ) || ! is_array( $loop_data['query_args'] ) ) {
		return $loop_data;
	}

	$post_types = 'current_page' === $query_type ? 'any' : ( $loop_data['post_type'] ?? 'any' );

	$loop_data['query_args'] = apply_rules(
		$loop_data['query_args'],
		$attrs['dcfdDateRules']['innerContent']['desktop']['value'] ?? null,
		$post_types
	);

	return $loop_data;
}
add_filter( 'divi_loop_data_before_execution', __NAMESPACE__ . '\filter_front_end_loop', 10, 2 );

/**
 * Visual Builder preview (REST). Rules arrive as JSON in the dcfd_rules param, added by
 * assets/builder.js. Untrusted: sanitised and resolved exactly like the front end (SPEC 8).
 * The current_page preview has no Divi filter and stays unfiltered (decision, PLAN.md).
 *
 * @param array $query_args WP_Query args.
 * @param array $params     All REST request params.
 */
function filter_builder_preview( $query_args, $params ) {
	if ( ! is_array( $query_args ) || ! is_array( $params ) || empty( $params['dcfd_rules'] ) || ! is_string( $params['dcfd_rules'] ) ) {
		return $query_args;
	}

	if ( 'post_type' !== ( $params['query_type'] ?? '' ) ) {
		return $query_args;
	}

	$raw = json_decode( wp_unslash( $params['dcfd_rules'] ), true );

	return apply_rules( $query_args, $raw, $query_args['post_type'] ?? 'any' );
}
add_filter( 'divi_module_options_loop_post_type_results_query_args', __NAMESPACE__ . '\filter_builder_preview', 10, 2 );
