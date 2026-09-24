<?php
/**
 * current_page Loop through Divi's real Loop code, on a simulated category archive.
 * Run: wp eval-file tests/current-page-check.php
 * Needs the fixtures (posts DCFD A-G are in Uncategorized).
 */

use ET\Builder\Packages\Module\Options\Loop\LoopUtils;

// Make this request look like the Uncategorized archive.
query_posts( [ 'cat' => get_cat_ID( 'Uncategorized' ), 'posts_per_page' => 20 ] ); // Hello world! (no date fields) is also in Uncategorized.
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];

$block = function ( $rules ) {
	$attrs = [
		'module'  => [ 'advanced' => [ 'loop' => [ 'desktop' => [ 'value' => [
			'enable' => 'on', 'queryType' => 'current_page', 'postPerPage' => '20', 'orderBy' => 'title', 'order' => 'ascending', 'loopId' => 'loop-dcfdcp' . wp_rand(),
		] ] ] ] ],
		'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => '<p>CP-ITEM:$variable({"type":"content","value":{"name":"loop_post_title","settings":{}}})$</p>' ] ] ],
	];
	if ( $rules ) {
		$attrs['dcfdDateRules'] = [ 'innerContent' => [ 'desktop' => [ 'value' => $rules ] ] ];
	}
	return serialize_block( [ 'blockName' => 'divi/text', 'attrs' => $attrs, 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => [] ] );
};

$titles = function ( $doc ) {
	$out = LoopUtils::parse_and_duplicate_loop_blocks( $doc );
	preg_match_all( '/"__loop_post_id":(\d+)/', $out, $m );
	return implode( ' ', array_map( function ( $id ) {
		return preg_replace( '/^DCFD ([A-G]) .*/', '$1', get_the_title( (int) $id ) );
	}, $m[1] ) );
};

$fail  = 0;
$check = function ( $actual, $expected, $label ) use ( &$fail ) {
	$ok = $actual === $expected;
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . "$label" . ( $ok ? '' : " expected [$expected] got [$actual]" ) . "\n";
	$fail += $ok ? 0 : 1;
};

$all = $titles( $block( null ) );
echo "archive without rules: [$all]\n";
$check( $titles( $block( [ 'rule1Field' => 'event_ends', 'rule1Operator' => 'before' ] ) ), 'B C D E F G Hello world!', 'current_page + event_ends before' );
$check( $titles( $block( [ 'rule1Field' => 'event_ends', 'rule1Operator' => 'after' ] ) ), 'A C D F G Hello world!', 'current_page + event_ends after' );

echo $fail ? "$fail FAILED\n" : "ALL PASSED\n";
