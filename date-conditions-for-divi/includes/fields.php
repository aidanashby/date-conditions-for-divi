<?php
/**
 * ACF field resolution: is this field name a date field for these post types? (SPEC 6.3)
 *
 * @package DCFD
 */

namespace DCFD;

/**
 * Whether ACF (or SCF) is available.
 */
function acf_active(): bool {
	return function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' );
}

/**
 * Normalise a Loop's post types. Empty or 'any' means every public post type,
 * matching how Divi queries a Loop with no post type selected.
 *
 * @param mixed $post_types String, array, or empty.
 * @return string[]
 */
function normalize_post_types( $post_types ): array {
	$post_types = array_filter( array_map( 'sanitize_key', (array) $post_types ) );

	if ( ! $post_types || in_array( 'any', $post_types, true ) ) {
		return array_values( get_post_types( [ 'public' => true ] ) );
	}

	return array_values( array_filter( $post_types, 'post_type_exists' ) );
}

/**
 * Look a field name up against the ACF field groups assigned to each post type.
 *
 * Status:
 * - found:     a Date or Date Time Picker on at least one post type, same type everywhere it exists.
 *              Post types without the field are fine: their posts are shown (SPEC 9).
 * - not_found: no post type has a field with this name.
 * - not_date:  it exists but isn't a date field anywhere.
 * - conflict:  the name maps to different field types on different post types.
 * - no_acf:    ACF isn't active.
 *
 * Cached per request (SPEC 6.3).
 *
 * @param string   $name       Flattened field name, e.g. key_facts_event_ends.
 * @param string[] $post_types Normalised post types.
 * @return array{status: string, type: string, post_types: string[]} post_types = where it was found.
 */
function lookup_field( string $name, array $post_types ): array {
	static $cache = [];

	$post_types = array_values( array_unique( $post_types ) );
	sort( $post_types );
	$key = $name . '|' . implode( ',', $post_types );

	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	if ( ! acf_active() ) {
		$cache[ $key ] = [ 'status' => 'no_acf', 'type' => '', 'post_types' => [] ];
		return $cache[ $key ];
	}

	$types = []; // post type => ACF field type.
	foreach ( $post_types as $post_type ) {
		$type = field_type_for_post_type( $name, $post_type );
		if ( null !== $type ) {
			$types[ $post_type ] = $type;
		}
	}

	$distinct = array_values( array_unique( $types ) );

	if ( ! $types ) {
		$status = 'not_found';
	} elseif ( count( $distinct ) > 1 ) {
		$status = 'conflict';
	} elseif ( in_array( $distinct[0], [ DATE_PICKER, DATE_TIME_PICKER ], true ) ) {
		$status = 'found';
	} else {
		$status = 'not_date';
	}

	$cache[ $key ] = [
		'status'     => $status,
		'type'       => 'found' === $status ? $distinct[0] : '',
		'post_types' => array_keys( $types ),
	];

	return $cache[ $key ];
}

/**
 * ACF field type for a flattened field name on one post type, or null if absent.
 * If several field groups define the name differently, the first found wins, the same
 * way ACF itself resolves a name on a post.
 *
 * @param string $name      Flattened field name.
 * @param string $post_type Post type.
 */
function field_type_for_post_type( string $name, string $post_type ): ?string {
	$groups = acf_get_field_groups( [ 'post_type' => $post_type ] );

	foreach ( (array) $groups as $group ) {
		$type = find_in_fields( $name, (array) acf_get_fields( $group ), '' );
		if ( null !== $type ) {
			return $type;
		}
	}

	return null;
}

/**
 * Walk fields, descending into Group fields to build flattened names (group_subfield,
 * group_subgroup_subfield). Repeaters, flexible content and clones are out of scope (SPEC 4).
 *
 * @param string $name   Target flattened name.
 * @param array  $fields ACF field arrays.
 * @param string $prefix Parent prefix, including the trailing underscore.
 */
function find_in_fields( string $name, array $fields, string $prefix ): ?string {
	foreach ( $fields as $field ) {
		if ( empty( $field['name'] ) ) {
			continue;
		}

		$full = $prefix . $field['name'];

		if ( $full === $name ) {
			return (string) $field['type'];
		}

		if ( 'group' === ( $field['type'] ?? '' ) && ! empty( $field['sub_fields'] ) && 0 === strpos( $name, $full . '_' ) ) {
			$type = find_in_fields( $name, $field['sub_fields'], $full . '_' );
			if ( null !== $type ) {
				return $type;
			}
		}
	}

	return null;
}

/**
 * Keep only rules whose field resolves to a date field; log the rest once per request
 * when WP_DEBUG is on (SPEC 5.2.7).
 *
 * @param array    $rules      Output of sanitize_rules().
 * @param string[] $post_types Normalised post types.
 * @return array Rules with a 'type' key added.
 */
function resolve_rules( array $rules, array $post_types ): array {
	static $logged = [];

	$resolved = [];
	foreach ( $rules as $rule ) {
		$result = lookup_field( $rule['field'], $post_types );

		if ( 'found' === $result['status'] ) {
			$resolved[] = $rule + [ 'type' => $result['type'] ];
			continue;
		}

		$log_key = $rule['field'] . '|' . implode( ',', $post_types );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! isset( $logged[ $log_key ] ) ) {
			$logged[ $log_key ] = true;
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only, per SPEC 5.2.7.
			error_log( sprintf( 'Date Conditions for Divi: rule on "%s" ignored (%s) for post types: %s', $rule['field'], $result['status'], implode( ', ', $post_types ) ) );
		}
	}

	return $resolved;
}
