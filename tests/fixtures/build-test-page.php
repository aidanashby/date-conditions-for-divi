<?php
// Test fixture: builds the "DCFD Loop spike" page and three Library items. Run with: wp eval-file <path>
// Each section's expected result is in tests/MANUAL-TESTS.md. Idempotent: re-running updates in place.
// $rule may be a field name (→ "Now is before" that field) or a full dcfdDateRules value array.
function b( $name, $attrs = [], $inner = [] ) {
	$attrs['builderVersion'] = '5.8.1';
	return [ 'blockName' => "divi/$name", 'attrs' => $attrs, 'innerBlocks' => $inner, 'innerHTML' => '', 'innerContent' => array_fill( 0, count( $inner ), null ) ];
}
function txt( $html, $extra = [] ) {
	return b( 'text', array_replace_recursive( [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => $html ] ] ] ], $extra ) );
}
function col( $inner, $extra = [] ) { return b( 'column', $extra, $inner ); }
function row( $inner, $extra = [] ) { return b( 'row', $extra, $inner ); }
function sec( $inner, $extra = [] ) { return b( 'section', $extra, $inner ); }
function loop( $id, $extra = [], $rule = 'event_ends', $empty = null ) {
	$a = [ 'module' => [ 'advanced' => [ 'loop' => [ 'desktop' => [ 'value' => array_merge( [
		'enable' => 'on', 'queryType' => 'post_types', 'subTypes' => [ [ 'value' => 'post', 'label' => 'Posts' ] ],
		'postPerPage' => '2', 'orderBy' => 'title', 'order' => 'ascending', 'loopId' => $id,
	], $extra ) ] ] ] ] ];
	if ( is_string( $rule ) ) { $rule = [ 'rule1Field' => $rule, 'rule1Operator' => 'before' ]; }
	if ( $rule ) { $a['dcfdDateRules'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $rule ] ] ]; }
	if ( $empty ) { $a['dcfdEmptyState'] = [ 'innerContent' => [ 'desktop' => [ 'value' => (string) $empty ] ] ]; }
	return $a;
}
$title_var = '$variable({"type":"content","value":{"name":"loop_post_title","settings":{}}})$';
$pink      = [ 'module' => [ 'decoration' => [ 'background' => [ 'desktop' => [ 'value' => [ 'color' => '#ff00aa' ] ] ] ] ] ];

function lib( $title, $type, $blocks ) {
	$existing = get_page_by_title( $title, OBJECT, 'et_pb_layout' );
	$id       = wp_insert_post( [ 'ID' => $existing ? $existing->ID : 0, 'post_title' => $title, 'post_type' => 'et_pb_layout', 'post_status' => 'publish', 'post_content' => wp_slash( serialize_blocks( [ b( 'placeholder', [], $blocks ) ] ) ) ] );
	wp_set_object_terms( $id, $type, 'layout_type' );
	return $id;
}
$lib_section = lib( 'DCFD empty section', 'section', [ sec( [ row( [ col( [ txt( '<p>EMPTY-STATE-SECTION</p>' ) ] ) ] ) ], $pink ) ] );
$lib_row     = lib( 'DCFD empty row', 'row', [ row( [ col( [ txt( '<p>EMPTY-STATE-ROW</p>' ) ] ) ], $pink ) ] );
$lib_module  = lib( 'DCFD empty module', 'module', [ txt( '<p>EMPTY-STATE-MODULE</p>', $pink ) ] );

$blocks = [ b( 'placeholder', [], [
	// S1: pagination ABOVE a module-level loop, rule on event_ends.
	sec( [ row( [ col( [
		b( 'post-nav', [ 'module' => [ 'advanced' => [ 'targetLoop' => [ 'desktop' => [ 'value' => 'loop-dcfdtext' ] ] ] ] ] ),
		txt( "<p>S1-ITEM:$title_var</p>", loop( 'loop-dcfdtext', [ 'search' => 'DCFD' ] ) ),
		b( 'post-nav', [ 'module' => [ 'advanced' => [ 'targetLoop' => [ 'desktop' => [ 'value' => 'loop-dcfdtext' ] ] ] ] ] ),
	] ) ] ) ] ),
	// S2: section-level loop that ends up empty, with section Library item.
	sec( [ row( [ col( [ txt( "<p>S2-ITEM:$title_var</p>" ) ] ) ] ) ], loop( 'loop-dcfdsec', [ 'search' => 'past ends' ], 'event_ends', $lib_section ) ),
	// S3: row-level loop, empty, row Library item.
	sec( [ row( [ col( [ txt( "<p>S3-ITEM:$title_var</p>" ) ] ) ], loop( 'loop-dcfdrow', [ 'search' => 'past ends' ], 'event_ends', $lib_row ) ) ] ),
	// S4: module-level loop, empty, module Library item.
	sec( [ row( [ col( [ txt( "<p>S4-ITEM:$title_var</p>", loop( 'loop-dcfdmod', [ 'search' => 'past ends' ], 'event_ends', $lib_module ) ) ] ) ] ) ] ),
	// S5: module-level loop, empty, no Library item -> native No Results.
	sec( [ row( [ col( [ txt( "<p>S5-ITEM:$title_var</p>", loop( 'loop-dcfdnat', [ 'search' => 'past ends' ] ) ) ] ) ] ) ] ),
	// S6: group-subfield rule.
	sec( [ row( [ col( [ txt( "<p>S6-ITEM:$title_var</p>", loop( 'loop-dcfdgrp', [ 'search' => 'DCFD', 'postPerPage' => '20' ], 'key_facts_event_ends' ) ) ] ) ] ) ] ),
	// S7: Date Picker field, "Now is before" (today still counts).
	sec( [ row( [ col( [ txt( "<p>S7-ITEM:$title_var</p>", loop( 'loop-dcfddp', [ 'search' => 'DCFD', 'postPerPage' => '20' ], 'event_date' ) ) ] ) ] ) ] ),
	// S8: two rules combined with AND.
	sec( [ row( [ col( [ txt( "<p>S8-ITEM:$title_var</p>", loop( 'loop-dcfdtwo', [ 'search' => 'DCFD', 'postPerPage' => '20' ], [ 'rule1Field' => 'event_ends', 'rule1Operator' => 'before', 'rule2Field' => 'key_facts_event_ends', 'rule2Operator' => 'before' ] ) ) ] ) ] ) ] ),
	// S9: rule on a non-date field (venue) is ignored for the whole Loop.
	sec( [ row( [ col( [ txt( "<p>S9-ITEM:$title_var</p>", loop( 'loop-dcfdtxt', [ 'search' => 'DCFD', 'postPerPage' => '20' ], 'venue' ) ) ] ) ] ) ] ),
	// S10: "Now is after" event_ends.
	sec( [ row( [ col( [ txt( "<p>S10-ITEM:$title_var</p>", loop( 'loop-dcfdaft', [ 'search' => 'DCFD', 'postPerPage' => '20' ], [ 'rule1Field' => 'event_ends', 'rule1Operator' => 'after' ] ) ) ] ) ] ) ] ),
	// S11: posts + pages; pages have no event_ends so they all show.
	sec( [ row( [ col( [ txt( "<p>S11-ITEM:$title_var</p>", loop( 'loop-dcfdmix', [ 'search' => 'DCFD', 'postPerPage' => '20', 'subTypes' => [ [ 'value' => 'post', 'label' => 'Posts' ], [ 'value' => 'page', 'label' => 'Pages' ] ] ] ) ) ] ) ] ) ] ),
	// S12: rule 1 empty, rule 2 set, operator omitted (defaults to "after").
	sec( [ row( [ col( [ txt( "<p>S12-ITEM:$title_var</p>", loop( 'loop-dcfdr2', [ 'search' => 'DCFD', 'postPerPage' => '20' ], [ 'rule1Field' => '', 'rule2Field' => 'event_ends' ] ) ) ] ) ] ) ] ),
	// S13: a second empty Loop using the same module Library item as S4.
	sec( [ row( [ col( [ txt( "<p>S13-ITEM:$title_var</p>", loop( 'loop-dcfdmod2', [ 'search' => 'past ends' ], 'event_ends', $lib_module ) ) ] ) ] ) ] ),
	// S14: nested Loops. Outer row Loop "before event_ends" (B C), inner module Loop "after event_ends".
	sec( [ row( [ col( [
		txt( "<p>S14O-ITEM:$title_var</p>" ),
		txt( "<p>S14I-ITEM:$title_var</p>", loop( 'loop-dcfdinner', [ 'search' => 'DCFD', 'postPerPage' => '20' ], [ 'rule1Field' => 'event_ends', 'rule1Operator' => 'after' ] ) ),
	] ) ], loop( 'loop-dcfdouter', [ 'search' => 'DCFD' ], 'event_ends' ) ) ] ),
] ) ];

$existing = get_page_by_path( 'dcfd-loop-spike' );
$page_id  = wp_insert_post( [
	'ID' => $existing ? $existing->ID : 0, 'post_title' => 'DCFD Loop spike', 'post_name' => 'dcfd-loop-spike',
	'post_type' => 'page', 'post_status' => 'publish', 'post_content' => wp_slash( serialize_blocks( $blocks ) ),
] );
update_post_meta( $page_id, '_et_pb_use_builder', 'on' );
update_post_meta( $page_id, '_et_pb_use_divi_5', 'on' );
echo "page=$page_id libs=$lib_section,$lib_row,$lib_module\n", get_permalink( $page_id ), "\n";
