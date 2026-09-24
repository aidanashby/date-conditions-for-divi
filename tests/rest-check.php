<?php
/**
 * Checks the builder REST endpoints and field resolution, as an admin.
 * Run: wp eval-file tests/rest-check.php --user=<admin>
 * Needs the fixtures from tests/fixtures/setup-test-data.php.
 */

$fail  = 0;
$check = function ( $ok, $label ) use ( &$fail ) {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n";
	$fail += $ok ? 0 : 1;
};
$field = function ( $name, $types = 'post' ) {
	$request = new WP_REST_Request( 'GET', '/dcfd/v1/field' );
	$request->set_query_params( [ 'name' => $name, 'post_types' => $types ] );
	return rest_do_request( $request )->get_data();
};

$r = $field( 'event_ends' );
$check( 'found' === $r['status'] && 'date_time_picker' === $r['type'], 'event_ends → found, Date Time Picker' );
$r = $field( 'event_date' );
$check( 'found' === $r['status'] && 'date_picker' === $r['type'], 'event_date → found, Date Picker' );
$check( 'found' === $field( 'key_facts_event_ends' )['status'], 'Group sub-field key_facts_event_ends → found' );
$check( 'found' === $field( 'key_facts_inner_closes' )['status'], 'nested Group sub-field key_facts_inner_closes → found' );
$check( 'not_date' === $field( 'venue' )['status'], 'venue (text) → not_date' );
$check( 'not_date' === $field( 'key_facts' )['status'], 'key_facts (the Group itself) → not_date' );
$r = $field( 'nope', 'page' );
$check( 'not_found' === $r['status'] && [ 'page' ] === $r['queried'], 'unknown field on page → not_found for page' );
$check( 'found' === $field( 'event_ends', 'post,page' )['status'], 'post + page: found on post only → found' );
$check( 'found' === $field( 'event_ends', '' )['status'], 'no post types (any) → found' );
$check( 'invalid_name' === $field( 'Bad Name' )['status'], 'invalid name rejected' );
$check( ! array_key_exists( 'value', $field( 'event_ends' ) ), 'no stored values returned' );

$layouts = rest_do_request( new WP_REST_Request( 'GET', '/dcfd/v1/layouts' ) )->get_data();
$titles  = wp_list_pluck( $layouts, 'type', 'title' );
$check( 'section' === ( $titles['DCFD empty section'] ?? '' ), 'layouts lists Library items with type' );
$check( ! array_diff_key( $layouts[0], array_flip( [ 'id', 'title', 'type' ] ) ), 'layouts returns only id, title, type' );

wp_set_current_user( 0 );
$check( 401 === rest_do_request( new WP_REST_Request( 'GET', '/dcfd/v1/layouts' ) )->get_status(), 'logged out → 401' );

echo $fail ? "$fail FAILED\n" : "ALL PASSED\n";
