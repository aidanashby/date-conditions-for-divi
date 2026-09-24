# Date Conditions for Divi: v1 specification

Version: 1.0 (23rd September 2026)
Status: ready for planning. Build starts only after the Phase 0 spike (section 7) is reported and approved.

## 1. Purpose

A WordPress plugin for Divi 5 that adds two settings to any Loop-enabled element in the Visual Builder:

1. **Date rules.** Up to two rules that exclude posts from the Loop's query based on ACF date fields stored on each post. Example: show only events where the current date and time is after `booking_opens` and before `event_ends`.
2. **Empty state.** A Divi Library item that replaces the whole Loop container when the Loop returns no posts.

Because the rules change the query itself, pagination, post counts and the empty state all reflect only the posts that pass.

## 2. Environment and baseline

| Item | Value |
|---|---|
| Divi | 5.8.1 (tested baseline; declare this as the minimum) |
| Local dev site | WordPress Studio (SQLite database) |
| Divi source on local site | `wp-content\themes\Divi\Divi\includes\builder-5\` |
| Divi developer docs | Divi 5 developer documentation (Elegant Themes) |
| Production databases | MySQL/MariaDB. All queries must behave identically on SQLite and MySQL. |
| ACF | Required (ACF free or SCF). Not currently installed on the local site. |
| PHP | 7.4 minimum (matches Divi) |
| Distribution | GitHub releases. No WordPress.org packaging, no translation-ready strings. |

## 3. Names and conventions

| Item | Value |
|---|---|
| Plugin name | Date Conditions for Divi |
| Slug and folder | `date-conditions-for-divi` |
| PHP prefix | `dcfd_` (functions, options, meta keys, hooks) |
| PHP namespace | `DCFD` |
| JS hook namespace | `dcfd` |
| Divi attribute names | Prefixed `dcfd` (e.g. `dcfdDateRules`, `dcfdEmptyState`) |

Terminology used below:

- **Loop container**: the section, row, column, group or module that has Loop enabled.
- **Rule**: one date field plus one operator.
- **Now**: the current date and time in the site timezone, to the second.

## 4. Scope

### In scope for v1

- Up to two date rules per Loop container, combined with AND.
- Operators: is after, is before, is equal to.
- ACF Date Picker and Date Time Picker fields, including sub-fields of ACF Group fields.
- Loop query types `post_types` and `current_page`.
- Empty-state Divi Library item replacing the whole Loop container.
- Visual Builder settings, live preview of filtered results, and field validation warnings.

### Out of scope for v1

- A display condition in Divi's Conditions tab (all logic lives in Loop settings).
- Relative dates ("today + 7 days").
- Non-ACF post meta, ACF Time Picker, repeater and flexible content sub-fields, options page fields.
- Term, user, menu, repeater and WooCommerce-specific loop query types.
- Page caching. Pages using this feature are excluded from page caching by the site owner; the plugin does not handle it.
- Showing the Library item inside the Visual Builder (the builder shows Divi's native "No Results Found" message for an empty Loop).

## 5. Behaviour

### 5.1 Settings location

A new settings group, labelled **Date Rules and Empty State**, in the Content panel of any element where Loop is available. The group is only visible when Loop is enabled on that element and the Loop's query type is `post_types` or `current_page`.

Fields:

| Field | Type | Notes |
|---|---|---|
| Rule 1: field name | Text | ACF field name, e.g. `event_ends` or `key_facts_event_ends` for a Group sub-field |
| Rule 1: operator | Select | "Now is after", "Now is before", "Now is equal to" |
| Rule 2: field name | Text | Optional |
| Rule 2: operator | Select | Optional |
| Empty state: Library item | Select | Lists published Divi Library items by title and type. Default: none |

Settings are per instance: not responsive, not hover or sticky, not part of presets.

A rule with an empty field name is ignored. A rule is read as "show the post if Now [operator] [field value]".

### 5.2 Date semantics

These are fixed decisions. Implement them exactly.

1. **Now** is `current_datetime()` in the site timezone (Settings > General). Never server time, `time()` or `date()`.
2. **Date Time Picker** values are compared to the second.
3. **Date Picker** values (date only) mean 23:59:59 on that date, for every operator. So:
   - "Now is before 31st December" is true up to and including 23:59:58 on 31st December.
   - "Now is after 1st December" is true from 00:00:00 on 2nd December.
   - "Now is equal to 1st December" is true only at 23:59:59 on 1st December.
4. **Equals** is an exact match to the second, as specified by the site owner.
5. **Missing or empty field**: the rule is ignored for that post, so the post is shown. This applies per rule: a post with rule 1's field empty and rule 2's field filled is tested against rule 2 only.
6. **Two rules** combine with AND.
7. **Field not resolvable** (not found, or not a Date Picker or Date Time Picker field for the queried post types): the rule is ignored for the whole Loop. The builder shows a warning (5.4). With `WP_DEBUG` on, log it once per request.

### 5.3 Empty state

- Applies when the Loop's filtered query returns no posts.
- The selected Library item replaces the entire Loop container output on the front end, including Divi's native "No Results Found" message.
- If no item is selected, or the item is missing, trashed or unpublished at render time, Divi's native output is left untouched.
- Any Library item type may be selected. The readme advises matching the item to the container level (a section item to replace a section, and so on).
- The Library item renders with its full Divi styling.

### 5.4 Visual Builder

- Changing any rule refreshes the Loop preview, using the same query logic as the front end.
- After a field name is entered, the builder checks it against the Loop's post type(s) and shows one of: "Date field found", "Field not found for [post type]", "Field is not a date field", or "Can't verify for this query type, will be checked when the page loads" (for `current_page` loops). Warnings never block saving.

## 6. Technical approach

These findings come from reading the Divi 5.8.1 source and the developer docs. The Phase 0 spike must confirm each one before any feature code is written.

### 6.1 Extension points

| Purpose | Hook | Side | Source |
|---|---|---|---|
| Register the settings group | `divi.moduleLibrary.moduleSettings.groups` | JS | `Developer/extending-loop.md` |
| Register the attributes | `divi.moduleLibrary.moduleAttributes` | JS | `Developer/extending-loop.md` |
| Register attributes server-side | `divi_module_library_register_module_attrs` | PHP | `Developer/php-filters.md` (undocumented parameters) |
| Pass rules into the builder preview request | `divi.module.layout.childModule.loop.resultsQueryParams` | JS | `Developer/extending-loop.md` |
| Modify the builder preview query | `divi_module_options_loop_post_type_results_query_args` | PHP | `Developer/extending-loop.md`; fires in `QueryResultsController.php` (REST, builder only) |
| Modify the front-end query | `divi_loop_data_after_execution` (receives block attributes) | PHP | Source only: `Packages/Module/Options/Loop/LoopUtils.php` |
| Replace output when the Loop is empty | `divi_loop_rendered_output`, with `$attrs['__loop_no_results']` | PHP | Source only: `LoopUtils::wrap_render_callback_for_loop_no_results()` |
| Enqueue builder script | `PackageBuildManager::register_package_build` on `divi_visual_builder_assets_before_enqueue_scripts` | PHP | `Developer/modifying-module-output.md` |

Key source finding: the documented PHP query filter only runs on the builder's REST preview. Front-end rendering calls `LoopUtils::execute_query()` directly and never reaches it. Both paths need handling, sharing one PHP function that turns rules into query arguments.

Divi's native meta query cannot replace this plugin: its values are static sanitised text (`LoopUtils::build_meta_query()`), with no concept of "now" and no way to let posts with an empty field through.

### 6.2 Query construction

- One shared function converts rules plus Now into a `meta_query` fragment. The builder REST path and the front-end path both call it.
- Merge with any existing native meta query using an outer `'relation' => 'AND'`. Never overwrite Divi's own meta query or named clauses used for ordering.
- Each rule becomes an OR group: field does not exist, OR field equals `''`, OR the date comparison. This delivers the fail-open rule in SQL.
- Compare as strings (`type => 'CHAR'`), formatting Now to match the field's storage format. ACF stores Date Picker values as `Ymd` and Date Time Picker values as `Y-m-d H:i:s`; both sort correctly as strings. Avoid `CAST` to `DATE`/`DATETIME`, which behaves differently on SQLite.
- Derive each clause from section 5.2, including the 23:59:59 rule for Date Picker fields. Cover the boundary seconds with tests.
- Apply only to `post_types` and `current_page` query types. Leave all others untouched.

### 6.3 Field resolution

- Resolve a field name against the ACF field groups assigned to each queried post type, recursing into Group fields to build flattened names (`group_subfield`, `group_subgroup_subfield`).
- Accept only `date_picker` and `date_time_picker` types. Record the type, as it decides the comparison format.
- If the same name resolves to different types across the queried post types, treat the rule as unresolvable (5.2 point 7).
- Cache resolutions per request.
- If ACF is inactive, the settings group shows a notice and all rules are ignored.

### 6.4 Empty-state rendering

- Hook `divi_loop_rendered_output`. When `__loop_no_results` is set on the Loop container and a valid Library item is selected, return the Library item's rendered output in place of the container's output.
- The spike must establish the correct way to render a Divi 5 Library item inside a page render so that its styles are generated and printed (Divi 5 generates module styles during block rendering).
- Only published `et_pb_layout` posts are valid. Re-verify post type and status at render time.

### 6.5 Static CSS risk

Divi caches page CSS as static files and, per `LoopHooks.php`, invalidates that cache when posts are saved, including featured-image-to-loop-item CSS mappings. This plugin changes a Loop's results as time passes, with no save to trigger invalidation. Two risks to test in the spike:

1. The empty state appears on a page whose CSS was cached while the Loop had results, so the Library item's styles are missing.
2. Loop items using dynamic featured-image backgrounds show the wrong images after the result set changes.

If either occurs, the plugin must fix it. Suggested approach: store a signature of each filtered Loop's result IDs and empty flag per post; when a render produces a different signature, clear that post's static resources through `ET_Core_PageResource`. The spike confirms the right API and timing.

### 6.6 Code structure

- Plain JavaScript using `window.vendor.wp.hooks`, with no build step unless the spike shows one is needed.
- PHP organised by responsibility: attribute registration, rule resolution and query building, empty-state rendering, static CSS handling, validation endpoint.
- Git repository from the first commit.

## 7. Phase 0: feasibility spike

Answer each question with evidence (file and line, or a test on the local site), then stop and report. Do not write feature code until the report is approved.

1. Do the settings group and attributes register on native Loop-enabled elements (section, row, column, group, and at least one module)? What is the attribute path for the Loop's query type, post type(s) and `loopId`?
2. Do custom attributes reach `divi_loop_data_after_execution` in `$block['attrs']` on the front end, and reach `$params` in the REST filter from the builder?
3. Does a `meta_query` added in each path filter results correctly, including on the second page of a paginated Loop?
4. Does the Pagination module read the filtered query (via `LoopQueryRegistry`), so page counts are correct?
5. Do infinite scroll, AJAX pagination and the native Post Filter module fetch further results through a path that applies the rules? If a path bypasses both hooks, identify it and propose a fix.
6. Does `divi_loop_rendered_output` fire for the Loop container with `__loop_no_results` set, and can its return value replace the whole container?
7. What is the correct way to render a Library item with its styles inside that hook?
8. Do the static CSS risks in 6.5 occur? If so, what is the right invalidation API and timing?
9. What happens to saved layouts when the plugin is deactivated (see `Developer/handling-unknown-attributes.md`)? Acceptable result: the Loop shows all posts and Divi's native empty output.
10. Can attributes be excluded from presets?

**Stop condition:** if question 2 or 6 has no supported answer, report the options and wait for a decision. Do not build on private methods or output parsing.

## 8. Security

- Field names: allow only `a-z`, `0-9`, `_` and `-`.
- Operators: whitelist of three values.
- Library item ID: `absint`, then verify post type and published status.
- Re-validate everything arriving through REST `$params`; never trust builder input.
- The validation endpoint requires a nonce and an editing capability, and returns only found/not-found, field type and post types. It never returns stored values.
- All output escaped; Library item output passes through Divi's own rendering.

## 9. Edge cases to handle

- Loop querying several post types, where a field exists on some but not others: posts of types without the field are shown (missing field rule).
- Rule 1 empty, rule 2 set: rule 2 applies alone.
- Posts where ACF has saved an empty string versus posts that never had the field saved.
- Group sub-field names, including nested groups.
- Theme Builder templates and archive pages using `current_page` loops.
- Nested loops: each Loop container applies only its own rules.
- Two Loops on one page with different rules, and the same Library item used by two Loops.
- DST changeover (the last Sunday in March and October in the UK).
- Site timezone set as a UTC offset rather than a named zone.
- Library item deleted after selection.
- ACF deactivated after rules were configured.

## 10. Testing

Automated where practical (PHP unit tests for rule-to-query conversion and date semantics), plus a manual test matrix on the local site covering:

- Every operator against Date Picker and Date Time Picker fields, including the exact boundary seconds.
- Missing field, empty field, one rule, two rules.
- Paginated Loop (posts per page below the total), pagination module counts.
- Infinite scroll and Post Filter, if the spike finds them in scope.
- Empty state: section, row and module containers; missing Library item fallback.
- Static CSS: result set changing over time with the static CSS cache on.
- Builder: live preview, each validation message, `current_page` message.
- Deactivation with saved layouts.
- One run against MySQL before first production install.

## 11. Deliverables

- The plugin in `date-conditions-for-divi/`, with a `readme.md` covering setup, how each setting behaves (sections 5.2 and 5.3 in plain language), the page caching requirement, and the Library item level advice.
- `uninstall.php` removing any stored signatures or options.
- Test files and the manual test matrix with results.
- Spike report (section 7), kept in the repository.
