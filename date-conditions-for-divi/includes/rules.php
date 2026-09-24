<?php
/**
 * Rule sanitising and rule → meta_query conversion.
 *
 * Pure functions: no WordPress calls, so tests/rules-test.php can run them with plain PHP.
 * "Now" is always passed in; callers use current_datetime() (SPEC 5.2.1).
 *
 * @package DCFD
 */

namespace DCFD;

const OPERATORS        = [ 'after', 'before', 'equals' ];
const DEFAULT_OPERATOR = 'after'; // Must match the builder field's default (Divi doesn't save defaults).
const DATE_PICKER      = 'date_picker';
const DATE_TIME_PICKER = 'date_time_picker';

/**
 * Turn the raw dcfdDateRules value into a clean list of rules.
 *
 * Untrusted input (block attrs or REST params). Rules with an empty field name are dropped
 * (SPEC 5.1); invalid names or operators drop the rule rather than being "fixed".
 *
 * @param mixed $value { rule1Field, rule1Operator, rule2Field, rule2Operator }.
 * @return array<int, array{field: string, operator: string}>
 */
function sanitize_rules( $value ): array {
	if ( ! is_array( $value ) ) {
		return [];
	}

	$rules = [];
	foreach ( [ 1, 2 ] as $n ) {
		$field    = $value[ "rule{$n}Field" ] ?? '';
		$operator = $value[ "rule{$n}Operator" ] ?? '';
		$field    = is_string( $field ) ? trim( $field ) : '';
		$operator = is_string( $operator ) && '' !== $operator ? $operator : DEFAULT_OPERATOR;

		if ( '' === $field || ! is_valid_field_name( $field ) || ! in_array( $operator, OPERATORS, true ) ) {
			continue;
		}

		$rules[] = [
			'field'    => $field,
			'operator' => $operator,
		];
	}

	return $rules;
}

/**
 * SPEC 8: field names may contain only a-z, 0-9, _ and -.
 *
 * @param string $name Field name.
 */
function is_valid_field_name( string $name ): bool {
	return 1 === preg_match( '/^[a-z0-9_-]{1,64}$/', $name );
}

/**
 * One rule as a fail-open meta_query group: field missing OR empty OR the date comparison.
 *
 * "Now [operator] field" is rewritten as a comparison on the stored value. ACF stores
 * Date Picker as Ymd and Date Time Picker as Y-m-d H:i:s, both of which sort correctly as
 * strings, so CHAR comparison behaves the same on SQLite and MySQL (SPEC 6.2).
 *
 * @param string             $field    Meta key.
 * @param string             $operator after|before|equals.
 * @param string             $type     date_picker|date_time_picker.
 * @param \DateTimeInterface $now      Now, in the site timezone.
 */
function rule_clause( string $field, string $operator, string $type, \DateTimeInterface $now ): array {
	$comparison = DATE_PICKER === $type
		? date_comparison( $operator, $now )
		: datetime_comparison( $operator, $now );

	$group = [
		'relation' => 'OR',
		[
			'key'     => $field,
			'compare' => 'NOT EXISTS',
		],
		[
			'key'     => $field,
			'value'   => '',
			'compare' => '=',
		],
	];

	if ( null !== $comparison ) {
		$group[] = [
			'key'     => $field,
			'value'   => $comparison[1],
			'compare' => $comparison[0],
			'type'    => 'CHAR',
		];
	}

	return $group;
}

/**
 * Date Time Picker: compared to the second (SPEC 5.2.2, 5.2.4).
 *
 * @return array{0: string, 1: string} [ SQL compare, value ].
 */
function datetime_comparison( string $operator, \DateTimeInterface $now ): array {
	$value = $now->format( 'Y-m-d H:i:s' );
	$map   = [
		'after'  => '<', // Now > field.
		'before' => '>', // Now < field.
		'equals' => '=',
	];

	return [ $map[ $operator ], $value ];
}

/**
 * Date Picker: the date means 23:59:59 on that day, for every operator (SPEC 5.2.3).
 *
 * @return array{0: string, 1: string}|null [ SQL compare, value ], or null when no stored date can match.
 */
function date_comparison( string $operator, \DateTimeInterface $now ): ?array {
	$today       = $now->format( 'Ymd' );
	$last_second = '23:59:59' === $now->format( 'H:i:s' );

	switch ( $operator ) {
		case 'after':
			// Now > D 23:59:59 only once D is before today.
			return [ '<', $today ];
		case 'before':
			// Now < D 23:59:59: today still counts until its last second.
			return $last_second ? [ '>', $today ] : [ '>=', $today ];
		default: // equals: true only at 23:59:59 on D.
			return $last_second ? [ '=', $today ] : null;
	}
}

/**
 * Add rule clauses to query args without touching any existing meta_query (SPEC 6.2).
 *
 * The existing query (including Divi's own clauses and any named ordering clauses) is nested
 * whole inside an outer AND, so its own relation and keys are preserved.
 *
 * @param array $query_args WP_Query args.
 * @param array $clauses    Output of rule_clause().
 */
function merge_meta_query( array $query_args, array $clauses ): array {
	if ( ! $clauses ) {
		return $query_args;
	}

	$merged = [ 'relation' => 'AND' ];
	if ( ! empty( $query_args['meta_query'] ) && is_array( $query_args['meta_query'] ) ) {
		$merged[] = $query_args['meta_query'];
	}

	$query_args['meta_query'] = array_merge( $merged, $clauses ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	return $query_args;
}
