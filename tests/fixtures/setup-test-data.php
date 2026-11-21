<?php
// Test fixture: ACF field group "DCFD spike" on posts + seven test posts (A to G). Run with: wp eval-file <path>
// Also sets the site timezone to Europe/London. Idempotent: re-running refreshes field values (A and E's group field 90 days ago, B and E 90 days ahead, F today, G yesterday), so re-run it before testing on a new day. Local/dev sites only.
update_option( 'timezone_string', 'Europe/London' );

if ( ! acf_get_field_group( 'group_dcfd_spike' ) ) {
	acf_import_field_group( [
		'key'      => 'group_dcfd_spike',
		'title'    => 'DCFD spike',
		'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ] ] ],
		'fields'   => [
			[ 'key' => 'field_dcfd_date', 'name' => 'event_date', 'label' => 'Event date', 'type' => 'date_picker', 'return_format' => 'd/m/Y', 'display_format' => 'd/m/Y' ],
			[ 'key' => 'field_dcfd_ends', 'name' => 'event_ends', 'label' => 'Event ends', 'type' => 'date_time_picker', 'return_format' => 'd/m/Y g:i a', 'display_format' => 'd/m/Y g:i a' ],
			[ 'key' => 'field_dcfd_text', 'name' => 'venue', 'label' => 'Venue', 'type' => 'text' ],
			[
				'key' => 'field_dcfd_group', 'name' => 'key_facts', 'label' => 'Key facts', 'type' => 'group',
				'sub_fields' => [
					[ 'key' => 'field_dcfd_kf_ends', 'name' => 'event_ends', 'label' => 'KF ends', 'type' => 'date_time_picker' ],
					[
						'key' => 'field_dcfd_inner', 'name' => 'inner', 'label' => 'Inner', 'type' => 'group',
						'sub_fields' => [ [ 'key' => 'field_dcfd_closes', 'name' => 'closes', 'label' => 'Closes', 'type' => 'date_picker' ] ],
					],
				],
			],
		],
	] );
}

// Past and future are 90 days either side of the run, so the expectations never go out of date.
$past   = wp_date( 'Y-m-d 10:00:00', time() - 90 * DAY_IN_SECONDS );
$future = wp_date( 'Y-m-d 10:00:00', time() + 90 * DAY_IN_SECONDS );
$posts  = [
	'A past ends'        => [ 'event_ends' => $past ],
	'B future ends'      => [ 'event_ends' => $future ],
	'C missing'          => [],
	'D empty string'     => [ 'event_ends' => '' ],
	'E group past'       => [ 'event_ends' => $future, 'key_facts' => [ 'event_ends' => $past ] ],
	'F date today'       => [ 'event_date' => wp_date( 'Ymd' ) ],
	'G date yesterday'   => [ 'event_date' => wp_date( 'Ymd', time() - DAY_IN_SECONDS ) ],
];
foreach ( $posts as $title => $fields ) {
	$existing = get_page_by_title( "DCFD $title", OBJECT, 'post' );
	$id       = $existing ? $existing->ID : wp_insert_post( [ 'post_title' => "DCFD $title", 'post_status' => 'publish', 'post_type' => 'post' ] );
	// Values are (re)written every run so F/G stay "today"/"yesterday": re-run before testing on a new day.
	foreach ( $fields as $name => $value ) {
		update_field( $name, $value, $id ); // ACF API so reference keys are written like the UI does.
	}
	echo "$id $title\n";
}
foreach ( get_posts( [ 'post_type' => 'post', 'numberposts' => -1, 's' => 'DCFD' ] ) as $p ) {
	echo $p->ID, ' ', $p->post_title, ' ', wp_json_encode( array_filter( get_post_meta( $p->ID ), function ( $k ) { return '_' !== $k[0]; }, ARRAY_FILTER_USE_KEY ) ), "\n";
}
