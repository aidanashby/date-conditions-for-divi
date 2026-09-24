#!/bin/bash
# Fetch the test page and compare each section's posts with the expected result.
# Expectations assume the fixture posts from tests/fixtures/setup-test-data.php and a "now"
# after 1 Sep 2026 and before 1 Dec 2026, with F = today and G = yesterday.
#
# Usage: tests/check-test-page.sh <site-url>     e.g. https://your-site.test
BASE="${1:?Usage: tests/check-test-page.sh <site-url>}"
HTML="$(curl -s "$BASE/dcfd-loop-spike/")"
fail=0

got() { # section marker → space-separated post letters (A-G) or page titles, in page order.
  printf '%s' "$HTML" | grep -o "$1-ITEM:[^<]*" | sed "s/^$1-ITEM://; s/^DCFD \([A-G]\) .*/\1/; s/^DCFD //" | tr '\n' ' ' | sed 's/ $//'
}
expect() { # marker, expected, description
  local actual; actual="$(got "$1")"
  if [ "$actual" = "$2" ]; then echo "PASS $1 $3"; else echo "FAIL $1 $3: expected [$2] got [$actual]"; fail=1; fi
}
has() { # text, description
  if printf '%s' "$HTML" | grep -q "$1"; then echo "PASS $2"; else echo "FAIL $2 (missing: $1)"; fail=1; fi
}

expect S1  "B C"             "event_ends before, page 1 of 3"
expect S2  ""                "section Loop empty"
expect S3  ""                "row Loop empty"
expect S4  ""                "module Loop empty"
expect S5  ""                "module Loop empty, no Library item"
expect S6  "A B C D F G"     "Group sub-field rule excludes E"
expect S7  "A B C D E F"     "Date Picker before: today (F) shown, yesterday (G) hidden"
expect S8  "B C D F G"       "two rules AND: excludes A and E"
expect S9  "A B C D E F G"   "non-date field rule ignored"
expect S10 "A C D F G"       "event_ends after"
expect S11 "B C D E F G Loop spike style control" "posts + pages, pages lack the field"
expect S12 "A C D F G"       "rule 2 alone, default operator after"
expect S13 ""                "second Loop using the same Library item is empty"
n=$(printf '%s' "$HTML" | grep -o 'EMPTY-STATE-MODULE' | wc -l)
[ "$n" -eq 2 ] && echo "PASS same Library item shown by two Loops" || { echo "FAIL same Library item: expected 2 got $n"; fail=1; }
expect S14O "B C"        "nested: outer Loop applies its own rule"
expect S14I "A C D F G A C D F G" "nested: inner Loop applies its own rule, once per outer item"
has "EMPTY-STATE-SECTION" "section Library item shown"
has "EMPTY-STATE-ROW"     "row Library item shown"
has "EMPTY-STATE-MODULE"  "module Library item shown"
has "No Results Found"    "native message kept when no Library item"
has "#ff00aa"             "Library item styles printed"
# 0.1.2 regression: module CSS goes on Divi's separate forced-inline resource, and the Customizer's
# unified stylesheet stays a normal <link> (forcing that one inline dropped the Customizer CSS).
has 'id="et-builder-module-design-' "module CSS forced inline on its own resource"
has "<link[^>]*et-core-unified-[0-9]*\.min\.css" "Customizer stylesheet still linked"

P2="$(curl -s "$BASE/dcfd-loop-spike/?loop-dcfdtext=2" | grep -o 'S1-ITEM:DCFD [A-G]' | sed 's/.* //' | tr '\n' ' ' | sed 's/ $//')"
[ "$P2" = "D E" ] && echo "PASS S1 page 2" || { echo "FAIL S1 page 2: expected [D E] got [$P2]"; fail=1; }
P3="$(curl -s "$BASE/dcfd-loop-spike/?loop-dcfdtext=3" | grep -o 'S1-ITEM:DCFD [A-G]' | sed 's/.* //' | tr '\n' ' ' | sed 's/ $//')"
[ "$P3" = "F G" ] && echo "PASS S1 page 3" || { echo "FAIL S1 page 3: expected [F G] got [$P3]"; fail=1; }

[ $fail = 0 ] && echo "ALL PASSED" || echo "SOME CHECKS FAILED"
exit $fail
