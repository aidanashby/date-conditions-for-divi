<?php
/**
 * Plain-PHP checks for includes/rules.php. No WordPress, no framework.
 *
 * Run: php tests/rules-test.php   (exit code 0 = all passed)
 *
 * Each generated meta_query group is evaluated against stored values the way SQL would
 * (string comparison), and the result is checked against SPEC 5.2 stated directly:
 * "show the post if Now [operator] field", Date Picker = 23:59:59, empty/missing = shown.
 */

require __DIR__ . '/../date-conditions-for-divi/includes/rules.php';

use const DCFD\DATE_PICKER;
use const DCFD\DATE_TIME_PICKER;

$failures = 0;
$checks   = 0;

function check( bool $ok, string $label ): void {
	global $failures, $checks;
	++$checks;
	if ( ! $ok ) {
		++$failures;
		echo "FAIL: $label\n";
	}
}

/** Evaluate one rule group against a stored value (null = meta row doesn't exist), like SQL would. */
function sql_matches( array $group, ?string $stored ): bool {
	foreach ( $group as $key => $clause ) {
		if ( 'relation' === $key ) {
			continue;
		}
		if ( 'NOT EXISTS' === $clause['compare'] ) {
			$hit = null === $stored;
		} elseif ( null === $stored ) {
			$hit = false; // Other comparisons need a row.
		} else {
			$cmp = strcmp( $stored, $clause['value'] );
			switch ( $clause['compare'] ) {
				case '=':
					$hit = 0 === $cmp;
					break;
				case '<':
					$hit = $cmp < 0;
					break;
				case '>':
					$hit = $cmp > 0;
					break;
				case '>=':
					$hit = $cmp >= 0;
					break;
				default:
					throw new Exception( 'Unexpected compare ' . $clause['compare'] );
			}
		}
		if ( $hit ) {
			return true; // Group relation is OR.
		}
	}
	return false;
}

/** SPEC 5.2 stated directly. */
function expected( string $operator, string $type, ?string $stored, DateTimeImmutable $now ): bool {
	if ( null === $stored || '' === $stored ) {
		return true; // 5.2.5: missing or empty field → rule ignored → shown.
	}
	$field = DATE_PICKER === $type
		? DateTimeImmutable::createFromFormat( '!Ymd H:i:s', $stored . ' 23:59:59', $now->getTimezone() )
		: DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $stored, $now->getTimezone() );
	// Wall-clock comparison: ACF stores local wall time with no offset.
	$a = $now->format( 'Y-m-d H:i:s' );
	$b = $field->format( 'Y-m-d H:i:s' );
	switch ( $operator ) {
		case 'after':
			return $a > $b;
		case 'before':
			return $a < $b;
		default:
			return $a === $b;
	}
}

$zones = [ 'Europe/London', '+01:00', 'UTC' ];

// Moments to test "now" at: day boundaries, the Date Picker boundary second, and UK DST days.
$nows = [
	'2026-12-01 00:00:00', '2026-12-01 12:00:00', '2026-12-01 23:59:58', '2026-12-01 23:59:59',
	'2026-12-02 00:00:00', '2026-11-30 23:59:59',
	'2026-03-29 00:59:59', '2026-03-29 02:00:00', // Spring forward (01:00 → 02:00 BST).
	'2026-10-25 00:59:59', '2026-10-25 02:00:00', // Fall back.
	'2026-12-31 23:59:59', '2027-01-01 00:00:00',
];

// Stored values, including empty and missing.
$stored_dates     = [ null, '', '20261130', '20261201', '20261202', '20260329', '20261025', '20261231', '20270101' ];
$stored_datetimes = [
	null, '', '2026-12-01 11:59:59', '2026-12-01 12:00:00', '2026-12-01 12:00:01',
	'2026-12-01 23:59:59', '2026-12-02 00:00:00', '2026-03-29 02:00:00', '2026-10-25 02:00:00',
	'2026-12-31 23:59:59', '2027-01-01 00:00:00',
];

foreach ( $zones as $zone ) {
	$tz = new DateTimeZone( $zone );
	foreach ( $nows as $now_string ) {
		$now = new DateTimeImmutable( $now_string, $tz );
		foreach ( DCFD\OPERATORS as $op ) {
			foreach ( [ DATE_PICKER => $stored_dates, DATE_TIME_PICKER => $stored_datetimes ] as $type => $values ) {
				$group = DCFD\rule_clause( 'event_ends', $op, $type, $now );
				foreach ( $values as $stored ) {
					$label = sprintf( '%s now=%s %s %s stored=%s', $zone, $now_string, $op, $type, var_export( $stored, true ) );
					check( sql_matches( $group, $stored ) === expected( $op, $type, $stored, $now ), $label );
				}
			}
		}
	}
}

// Spot checks straight from SPEC 5.2.3, so the semantics are readable here too.
$tz  = new DateTimeZone( 'Europe/London' );
$at  = function ( $s ) use ( $tz ) {
	return new DateTimeImmutable( $s, $tz );
};
$dec31 = '20261231';
check( sql_matches( DCFD\rule_clause( 'f', 'before', DATE_PICKER, $at( '2026-12-31 23:59:58' ) ), $dec31 ), 'before 31 Dec true at 23:59:58' );
check( ! sql_matches( DCFD\rule_clause( 'f', 'before', DATE_PICKER, $at( '2026-12-31 23:59:59' ) ), $dec31 ), 'before 31 Dec false at 23:59:59' );
check( ! sql_matches( DCFD\rule_clause( 'f', 'after', DATE_PICKER, $at( '2026-12-01 23:59:59' ) ), '20261201' ), 'after 1 Dec false at 23:59:59 on 1 Dec' );
check( sql_matches( DCFD\rule_clause( 'f', 'after', DATE_PICKER, $at( '2026-12-02 00:00:00' ) ), '20261201' ), 'after 1 Dec true from 00:00:00 on 2 Dec' );
check( sql_matches( DCFD\rule_clause( 'f', 'equals', DATE_PICKER, $at( '2026-12-01 23:59:59' ) ), '20261201' ), 'equals 1 Dec true at 23:59:59' );
check( ! sql_matches( DCFD\rule_clause( 'f', 'equals', DATE_PICKER, $at( '2026-12-01 23:59:58' ) ), '20261201' ), 'equals 1 Dec false at 23:59:58' );
check( 2 === count( DCFD\rule_clause( 'f', 'equals', DATE_PICKER, $at( '2026-12-01 12:00:00' ) ) ) - 1, 'equals with no possible match keeps only the fail-open clauses' );

// sanitize_rules (SPEC 5.1, 8).
check( [] === DCFD\sanitize_rules( null ), 'non-array input → no rules' );
check( [] === DCFD\sanitize_rules( [ 'rule1Field' => '', 'rule2Field' => '' ] ), 'empty field names ignored' );
check(
	[ [ 'field' => 'event_ends', 'operator' => 'before' ] ] === DCFD\sanitize_rules( [ 'rule2Field' => 'event_ends', 'rule2Operator' => 'before' ] ),
	'rule 1 empty, rule 2 applies alone'
);
check(
	[ [ 'field' => 'booking_opens', 'operator' => 'after' ] ] === DCFD\sanitize_rules( [ 'rule1Field' => 'booking_opens' ] ),
	'missing operator defaults to after'
);
check( [] === DCFD\sanitize_rules( [ 'rule1Field' => 'event_ends', 'rule1Operator' => 'LIKE' ] ), 'unknown operator drops the rule' );
foreach ( [ 'Event_Ends', 'event ends', "x'--", 'a.b', 'é', str_repeat( 'a', 65 ), 'x;DROP' ] as $bad ) {
	check( [] === DCFD\sanitize_rules( [ 'rule1Field' => $bad ] ), "invalid field name rejected: $bad" );
}
check( 2 === count( DCFD\sanitize_rules( [ 'rule1Field' => 'a', 'rule2Field' => 'key_facts_event-ends', 'rule2Operator' => 'equals' ] ) ), 'two valid rules kept' );
check( [] === DCFD\sanitize_rules( [ 'rule1Field' => [ 'x' ] ] ), 'non-string field rejected' );

// merge_meta_query (SPEC 6.2).
$divi_own = [ 'relation' => 'OR', 'price_clause' => [ 'key' => 'price', 'value' => '5', 'compare' => '>' ], [ 'key' => 'x' ] ];
$clause   = DCFD\rule_clause( 'f', 'after', DATE_TIME_PICKER, $at( '2026-12-01 12:00:00' ) );
$merged   = DCFD\merge_meta_query( [ 'post_type' => [ 'post' ], 'meta_query' => $divi_own ], [ $clause, $clause ] );
check( 'AND' === $merged['meta_query']['relation'], 'outer relation is AND' );
check( $divi_own === $merged['meta_query'][0], "Divi's own meta_query nested untouched, named clause kept" );
check( 4 === count( $merged['meta_query'] ), 'existing + two rules + relation' );
check( [ 'post_type' => [ 'post' ] ] === DCFD\merge_meta_query( [ 'post_type' => [ 'post' ] ], [] ), 'no rules → args unchanged' );
check( [ 'relation' => 'AND', $clause ] === DCFD\merge_meta_query( [], [ $clause ] )['meta_query'], 'no existing meta_query' );

echo $failures ? "\n$failures of $checks checks FAILED\n" : "All $checks checks passed\n";
exit( $failures ? 1 : 0 );
