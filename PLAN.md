# Build plan: Date Conditions for Divi v1

This plan assumes the four SPEC.md changes in SPIKE-REPORT.md are approved (they were, 23 September 2026). Decisions are listed at the end.

## Build status (24 September 2026): v0.1.2

| Step | State |
|---|---|
| 1. Skeleton, Loop-group placement, save round-trip | ✅ Fields render inside the Loop group; rules save and reload, and survive a save with the plugin deactivated |
| 2. rules.php + tests | ✅ 2,186 checks |
| 3. query.php + fields.php | ✅ front end, builder preview, Group and nested-Group fields |
| 4. builder.js + rest.php | ✅ messages, live preview refresh, Library select, hidden when Loop is off, absent from presets |
| 5. empty-state.php | ✅ section/row/module, fallbacks, shared item, forced inline CSS, featured-image backgrounds |
| 6. Theme Builder + `current_page` | ✅ TB archive template with a `current_page` Loop and empty state. Current Page is only offered in TB templates, not on ordinary pages |
| 7. readme.md | ✅ `date-conditions-for-divi/readme.md` |
| 8. MySQL run | ✅ Real use on a MySQL staging site (a vacancies page: date rules and empty state). Scripted fixture run S1–S14 not repeated on MySQL: real use judged enough (24 Sep) |
| 10. Customizer CSS kept (0.1.2) | ✅ Found on the staging site: forcing the shared unified stylesheet inline dropped the Theme Customizer CSS once a cached file existed. Now uses Divi's separate `builder` / `module-design` resource, as Divi does for random-order Loops |
| 9. Global variables in Library items (0.1.1) | ✅ Found on the staging site: Divi didn't define a variable used only by the Library item. The plugin now prints the item's variable and colour definitions (`global_data_styles()` in empty-state.php, using Divi's public `DetectFeature` and `Style` methods) |

Changes from the plan below, made during the build:
- The Library item select is a `divi/select` fed by `GET dcfd/v1/layouts`. "None" sorts last because JS orders numeric keys first. Cosmetic.
- The field-check message is a small custom field component registered with the `divi.fieldLibrary.getFieldComponent` JS filter. That filter is public but undocumented, so it joins the source-only hooks list.
- Server-side attribute registration isn't needed: attributes save and reload without it.
- The builder script loads before React, so it looks React up at render time.

## File structure

```
date-conditions-for-divi/                  (repo root)
├── SPEC.md, SPIKE-REPORT.md, PLAN.md
├── tests/
│   ├── rules-test.php                     plain-PHP assert script, no framework
│   ├── MANUAL-TESTS.md                    manual matrix with results
│   └── fixtures/                          test data + test page scripts (exist now)
└── date-conditions-for-divi/              (plugin; linked into the local test site)
    ├── date-conditions-for-divi.php       header, constants, ACF/Divi guards, requires, hook wiring
    ├── includes/
    │   ├── rules.php                      pure: sanitise rules, Now formatting, rule → meta_query
    │   ├── fields.php                     ACF field resolution + per-request cache + debug log
    │   ├── query.php                      front-end + REST query hooks
    │   ├── empty-state.php                rendered_output replacement + forced-inline styles
    │   └── rest.php                       validation + Library-items endpoints
    ├── assets/builder.js                  plain JS, no build step (spike confirmed)
    └── readme.md
```

The spec's five PHP responsibilities map to five files. The spec's "static CSS handling" is one filter, so it lives in `empty-state.php`, not a file of its own.

Skipped:
- `uninstall.php`: the plugin stores nothing (no options, no signatures). Add it if anything stored is introduced later.
- Server-side attribute registration (`divi_module_library_register_module_attrs`): only added if the save round-trip test (step 1) shows the builder stripping unregistered attributes.

## Data shape

- `dcfdDateRules.innerContent.desktop.value = { rule1Field, rule1Operator, rule2Field, rule2Operator }`. Four fields, one attribute, split with `subName`.
- `dcfdEmptyState.innerContent.desktop.value = "<layout ID>"`.
- Operators are stored as `after | before | equals`.
- All fields: `features: { responsive:false, hover:false, sticky:false, dynamicContent:false, preset:'content' }` (spec 5.1, Q10).

## Rule → SQL (includes/rules.php)

"Now [op] field value" becomes a condition on the stored meta value. Every rule is wrapped as `OR( NOT EXISTS, = '', <comparison> )`, compared as `CHAR`. Now is `current_datetime()` (spec 5.2.1).

| Field type | Operator | Comparison on meta value | Why |
|---|---|---|---|
| Date Time (`Y-m-d H:i:s`) | after | `meta < now` | now > field |
| | before | `meta > now` | now < field |
| | equals | `meta = now` | to the second |
| Date (`Ymd`, means 23:59:59) | after | `meta < today` | now > D 23:59:59 only once D is before today |
| | before | `meta >= today` if time < 23:59:59, else `meta > today` | covers the boundary second |
| | equals | `meta = today` if time is 23:59:59, else a clause that can't match | true only at 23:59:59 |

For the "can't match" case: drop the comparison, so the OR group reduces to missing/empty only (still fail-open).

Rules combine with AND (spec 5.2.6). The fragment is merged as `['relation'=>'AND', <existing>, <rule1>, <rule2>]`, leaving Divi's own meta query and named clauses untouched (spec 6.2).

Pure functions, with Now passed in as a `DateTimeImmutable`, so tests can pin the clock.

## Hooks (includes/query.php)

- **Front end:** `divi_loop_data_before_execution` ([SPEC CHANGE] from `_after_`), only for `query_type` `post_types` and `current_page`. It reads `$attrs['dcfdDateRules']`, resolves fields against `$loop_data['post_type']` (`['any']` → all public post types) and appends the meta query to `$loop_data['query_args']`.
- **Builder:** the JS `resultsQueryParams` filter sets `dcfd_rules` (JSON). PHP `divi_module_options_loop_post_type_results_query_args` re-validates it (spec 8), since REST `$params` is never trusted.
- **`current_page` builder preview:** see open decision 1.
- **Nested loops:** each block's own attrs drive its own query, so nothing extra is needed. Verify manually.

## Field resolution (includes/fields.php)

- `acf_get_field_groups( ['post_type' => $pt] )` → `acf_get_fields()`, recursing into `group` sub-fields to build `parent_child` names.
- Accept `date_picker` / `date_time_picker` only.
- The same name with different types across post types means unresolvable (spec 6.3).
- A static per-request cache.
- Unresolvable rules are dropped for the whole Loop, logged once per request when `WP_DEBUG` is on (spec 5.2.7).
- ACF inactive (`! function_exists( 'acf_get_field_groups' )`): all rules are ignored, and the builder shows a notice.

## Empty state (includes/empty-state.php)

- `divi_loop_rendered_output`: bail unless `$attrs['__loop_no_results']` is set and there's a `dcfdEmptyState` ID.
- `absint`, then `get_post`: `et_pb_layout` + `publish`, or return Divi's output unchanged (spec 5.3, 8).
- Replace the output with `do_blocks( $layout->post_content )` (Q7).
- **Forced inline styles:** `divi_frontend_assets_static_css_module_style_manager` swaps in Divi's forced-inline `builder` / `module-design` resource (0.1.2; 0.1.0–0.1.1 set `forced_inline` on the shared unified resource, which dropped the Customizer CSS) on pages whose content, including Theme Builder layouts via `ET_Post_Stack`, contains `dcfdDateRules` (Q8). Covers both spec 6.5 risks.
- Child modules: fields hidden in the builder (Q6).

## Builder (assets/builder.js + includes/rest.php)

- Enqueued via `PackageBuildManager::register_package_build` on `divi_visual_builder_assets_before_enqueue_scripts`.
- Group and attributes are registered only where `metadata.attributes.module.settings.advanced.loop` exists and the module isn't a child module.
- Field `visible`: Loop `enable === 'on'` and `queryType` in `post_types` / `current_page`.
- Operator: `divi/select`. Library item: `divi/select` with options from `GET dcfd/v1/layouts`, returning ID, title and layout type.
- Validation messages (spec 5.4) come from `GET dcfd/v1/check-field?name=&post_types=`. Requires `wp_rest` nonce + `edit_posts`, and returns `{status, type, post_types}` only (spec 8). For `current_page` loops it returns the "Can't verify" message without querying.
- Where the validation message appears (field description, or a read-only notice field) is decided in step 4 against what the Divi component props allow.

## Build order

Each step ends with `php -l` on changed files and its tests passing before the next starts.

1. **Skeleton, Loop-group placement and save round-trip.** Plugin header, guards, and a builder.js that registers the attributes (`divi.moduleLibrary.moduleAttributes`) and draws the fields inside the Loop group (`divi.module.options.loop.group.fields`), visible only when `loopValues.enable === 'on'` and the query type is `post_types`/`current_page`. In the builder, confirm:
   - the fields appear in the Loop group, and not on elements with Loop off;
   - a rule saves and survives a reload;
   - editing a rule refreshes the preview;
   - the fields are absent from the preset modal.

   Then deactivate the plugin, save, reactivate, and record whether the attributes survived. If the Loop-group filter doesn't work, switch to the fallback in decision 4. This step settles attribute placement, server-side registration and the builder half of Q9 before anything else depends on them.
2. **rules.php + tests/rules-test.php.** Every operator × field type at boundary seconds:
   - 23:59:58, 23:59:59 and 00:00:00 on D and D+1
   - missing and empty values
   - one rule, two rules, and rule 1 empty with rule 2 set
   - DST days (last Sunday of March/October, Europe/London)
   - a `+01:00` UTC-offset timezone

   Tests run the generated clause against in-memory rows, so no database is needed.
3. **query.php + fields.php.** Front end and REST. Re-run the spike page (S1 pagination/Post Navigation, S6 group, nested group `key_facts_inner_closes`) plus a multi-post-type Loop where only one type has the field (spec 9).
4. **builder.js settings + rest.php.** Operator/Library selects, validation messages, live preview refresh after editing a rule, preset modal exclusion, and hidden on child modules.
5. **empty-state.php.** Section, row and module containers; missing/trashed/draft Library item fallback; the same item used by two Loops; static CSS off→on sequence (the Q8 script); featured-image background Loop (spec 6.5 risk 2).
6. **Theme Builder + `current_page`.** Archive template with a `current_page` Loop, and forced-inline detection from a TB layout.
7. **readme.md**: setup, plain-language date behaviour (including the one-second "equals" and the DST note), page-cache requirement, container-level Library advice, child-module and WP-PageNavi limits.
8. **MySQL run** (spec 10) on a MySQL staging site: install a test plugin zip, set up the test data, and run tests/MANUAL-TESTS.md sections 2, 3 and 5.

## Requirement → test map

| Spec | Met by | Tested by |
|---|---|---|
| 5.1 group, fields, per-instance | builder.js | step 1 save test, step 4 |
| 5.2.1 to 5.2.6 date semantics | rules.php | rules-test.php |
| 5.2.7 unresolvable field | fields.php | step 3 manual + debug log |
| 5.3 empty state | empty-state.php | step 5 matrix |
| 5.4 preview + messages | builder.js, REST filter, rest.php | step 4 |
| 6.2 merge, fail-open, CHAR | rules.php | rules-test.php + step 3 on SQLite and MySQL |
| 6.3 Group fields, type conflicts, ACF off | fields.php | step 3 |
| 6.5 static CSS | forced-inline filter | step 5 (Q8 script + featured image) |
| 8 security | sanitising in rules.php, rest.php checks | rules-test.php (bad names/operators), step 4 (no nonce → 401/403) |
| 9 edge cases | as above | tests/MANUAL-TESTS.md, one row each |
| Deactivation | nothing stored | step 1 |

## Decisions (23rd September 2026)

1. **`current_page` builder preview:** left unfiltered, with the "Can't verify" message. Decided.
2. **Forced inline styles** via `divi_frontend_assets_static_css_module_style_manager` (Q8): approved.
3. **MySQL run (step 8):** on a MySQL staging site, since the local site uses SQLite.
4. **Empty group header** (Q1): put the fields *inside Divi's own Loop group* using the builder filter `divi.module.options.loop.group.fields` (see SPIKE-REPORT.md, Q1 addendum). This is tested first in build step 1. If it fails, fall back to the separate group plus a "Turn on Loop to use date rules" note field shown only when Loop is off.
