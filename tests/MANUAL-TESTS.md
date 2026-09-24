# Test matrix: Date Conditions for Divi 0.1.2

Results as of 23 September 2026 on a local WordPress Studio site (SQLite, Divi 5.8.1, ACF 6.8.10, Europe/London). Everything has passed except three optional checks. On 24 September 2026, 0.1.1 was also confirmed in real use on a MySQL staging site.

**Status key:** ✅ passed · ⏳ still to run (who runs it is in the row)

## Automated tests (re-run after any change)

| Test | Command | Result |
|---|---|---|
| Date semantics, every operator × field type at the boundary seconds, DST days, UTC-offset zone, sanitising, meta_query merging | `php tests/rules-test.php` | ✅ 2,186 checks |
| Front end, all test-page sections + pagination | `bash tests/check-test-page.sh <site-url>` | ✅ 23 checks |
| REST endpoints, field resolution, permissions | `wp eval-file tests/rest-check.php --user=<admin>` | ✅ 14 checks |
| `current_page` Loop through Divi's Loop code on a category archive | `wp eval-file tests/current-page-check.php` | ✅ 2 checks |

The test data comes from `tests/fixtures/` (see the root README). Fixture posts: **A** ends 1 Sep 2026 (past), **B** ends 1 Dec 2026 (future), **C** no field, **D** empty field, **E** ends in future but `key_facts_event_ends` past, **F** Date Picker today, **G** Date Picker yesterday.

## Front end (test page `/dcfd-loop-spike/`)

| # | Case | Expected | Result |
|---|---|---|---|
| S1 | Module Loop, "before event_ends", 2 per page, Pagination above and below | Page 1 B C, page 2 D E, page 3 F G; Next/Prev correct | ✅ |
| S2 | Section Loop, empty, section Library item | Library section replaces the section, pink background styled | ✅ |
| S3 | Row Loop, empty, row Library item | Library row replaces the row | ✅ |
| S4 | Module Loop, empty, module Library item | Library module replaces the module | ✅ |
| S5 | Module Loop, empty, no Library item | Divi's "No Results Found" | ✅ |
| S6 | Group sub-field `key_facts_event_ends` | All but E | ✅ |
| S7 | Date Picker `event_date`, before | Today (F) shown, yesterday (G) hidden | ✅ |
| S8 | Two rules (AND) | Excludes A and E | ✅ |
| S9 | Rule on a text field (`venue`) | Rule ignored, all shown | ✅ |
| S10 | "after event_ends" | A C D F G | ✅ |
| S11 | Posts + Pages, pages lack the field | Pages shown, A hidden | ✅ |
| S12 | Rule 1 empty, rule 2 set, no operator saved | Rule 2 alone, default "after" | ✅ |
| — | Empty field ('') vs never saved | Both shown (D and C) | ✅ |
| — | Library item unpublished (draft) | Falls back to "No Results Found" | ✅ |
| — | Library item trashed | Falls back to "No Results Found" | ✅ |
| — | ACF deactivated | Rules ignored, all posts shown, no errors | ✅ |
| — | Builder CSS forced inline (on Divi's separate `module-design` resource) | Library item styled on every load | ✅ |
| — | Customizer CSS kept when the page's unified CSS file was cached before the plugin applied | Customizer stylesheet still linked, header button styled | ✅ local repro and MySQL staging site, 24 Sep (0.1.2) |
| — | Library item styled with a Divi global variable (e.g. padding) on a page whose own content uses a different global variable | Variable defined, padding applied | ✅ local repro and MySQL staging site, 24 Sep |
| — | Plugin deactivated (spike) | Page renders, all posts, native empty output | ✅ |
| — | `current_page` Loop on a category archive (Divi code, CLI) | Rules applied | ✅ |
| S13 | Same Library item used by two Loops on one page | Both show it | ✅ |
| S14 | Nested Loops (row Loop "before", module Loop "after" inside) | Outer B C; inner A C D F G per outer item | ✅ |
| — | Loop with featured-image background (spec 6.5 risk 2), result set changes | Right images after the change | ✅ 23 Sep |
| — | Theme Builder archive template with a `current_page` Loop + empty state | Rules applied, Library item styled | ✅ 23 Sep |
| — | Site timezone set as a UTC offset | Same as named zone | ✅ (unit tests); live ⏳ optional |
| — | `WP_DEBUG` on: ignored rule logged once per page load | One log line per field | ⏳ optional: needs WP_DEBUG on (wp-config change, ask first) |

## Visual Builder

| Case | Expected | Result |
|---|---|---|
| Settings appear inside **Content → Loop**, after Divi's Loop fields | Rule 1, rule 2, empty state | ✅ |
| No separate settings group on elements without Loop | None shown | ✅ (group no longer exists) |
| Field check message: found | "Date field found (Date Time Picker)" | ✅ |
| Field check message: not found | "Field not found for post" | ✅ |
| Field check message: not a date field | "Field is not a date field" | ✅ |
| Editing a rule refreshes the Loop preview | Preview changes | ✅ (event_ends → venue: B C → A B) |
| Empty-state select lists Library items with type | Listed | ✅ ("None" sorts last, cosmetic) |
| Rule saved and survives reload | In post content | ✅ |
| Settings hidden when Loop is off | Hidden | ✅ 23 Sep |
| Current Page Loop shows "Can't verify…" | Message shown | Not checked. On ordinary pages Divi only offers Post Types, Terms, Users and Menus (23 Sep); Current Page appears in Theme Builder templates, where the rules were confirmed working (row above). ⏳ optional: glance at the message next time a TB template is open |
| Settings absent from the preset modal | Absent (Loop group has presets off) | ✅ 23 Sep |
| **Save with the plugin deactivated, then reactivate** | Rules still on the page | ✅ 23 Sep |

## MySQL (staging site)

| Case | Expected | Result |
|---|---|---|
| Real use: a vacancies page Loop with date rules and a row Library item empty state | Rules applied, item styled | ✅ 24 Sep (0.1.1) |
| All of "Front end" S1–S14 on MySQL | Same results as SQLite | Not run: real use above judged enough (24 Sep). Steps below if ever needed |

### Steps for a MySQL run

1. Upload `date-conditions-for-divi-<version>.zip` via Plugins → Add New → Upload, and activate. ACF must be active.
2. Set up test data.
   - **With SSH/WP-CLI:** upload `tests/fixtures/setup-test-data.php` and `build-test-page.php`, then run `wp eval-file setup-test-data.php` and `wp eval-file build-test-page.php`. Note: `setup-test-data.php` sets the site timezone to Europe/London.
   - **Without SSH:** create the same ACF fields, posts, Library items and page by hand, following the two fixture scripts.
3. Run `bash tests/check-test-page.sh https://<staging-site-url>`.
4. Afterwards, delete the test page, the 7 "DCFD" posts, the 3 "DCFD empty" Library items, and the "DCFD spike" ACF field group.
