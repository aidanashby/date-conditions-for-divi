# Phase 0 spike report: Date Conditions for Divi

Date: 23rd September 2026. Divi 5.8.1, WordPress 7.1.2, PHP 8.3, ACF 6.8.10, WordPress Studio (SQLite).

**Verdict: feasible. The stop condition is not hit.** Q2 and Q6 both have supported answers, tested on the local site. Four findings change the spec, and they're marked **[SPEC CHANGE]** below:

- a different front-end hook
- a different static CSS fix
- no Post Filter module or infinite scroll to support
- a settings group header that can't be hidden

All source paths below are relative to `wp-content/themes/Divi/Divi/includes/builder-5/server/` unless stated otherwise.

## How the spike was run

- Throwaway mu-plugin (`wp-content/mu-plugins/dcfd-spike.php` + `.js`), now deleted. Copies are in the session scratchpad only.
- ACF field group "DCFD spike" on `post`:
  - `event_date` (Date Picker)
  - `event_ends` (Date Time Picker)
  - `venue` (Text)
  - Group `key_facts`, containing `event_ends` (Date Time Picker) and a nested Group `inner` containing `closes` (Date Picker)
- Seven test posts: `DCFD A past ends`, `DCFD B future ends`, `DCFD C missing`, `DCFD D empty string`, `DCFD E group past`, `DCFD F date today`, `DCFD G date yesterday`.
- Test page `/dcfd-loop-spike/` (ID 9787):
  - S1: module Loop with Post Navigation above and below
  - S2: section Loop that goes empty
  - S3: row Loop that goes empty
  - S4: module Loop that goes empty
  - S5: module Loop that goes empty, with no Library item
  - S6: rule on a Group sub-field
- Three Library items: section, row and module, each with a `#ff00aa` background so its styles can be detected.
- Spike rule: "Now is before `field`" → `meta > now`, using the OR group from spec 6.2 (NOT EXISTS / `= ''` / comparison, `type CHAR`).

The test posts, page, Library items and field group are still on the local site for Phase 1. I also set the site timezone to `Europe/London` (it was blank, UTC+0).

Storage formats confirmed on disk:
- Date Picker `20260923`
- Date Time Picker `2026-12-01 10:00:00`
- Group sub-field meta key `key_facts_event_ends`
- Empty Group parent stored as `key_facts = ''`

## Answers

### Q1. Settings group and attribute registration

**Yes.** Enqueued with `PackageBuildManager::register_package_build` on `divi_visual_builder_assets_before_enqueue_scripts`, in plain JS with no build step. Filtering on `metadata.attributes.module.settings.advanced.loop` present:
- the group and attributes registered on **78 element types**, including section, row, column, group and text;
- the Loop group appeared in the section's Content panel as "Date Rules And Empty State". Divi title-cases the label.

Attribute paths (identical in JS `attrs` and PHP `$block['attrs']`):
- Loop settings: `module.advanced.loop.desktop.value`
  - `enable` (`'on'`)
  - `queryType` (`post_types`, `current_page`, ...)
  - `subTypes` (`[{value:'post',label:'Posts'}]`)
  - `postPerPage`, `loopId` (`loop-xxxx`), `search`, `metaQuery`, `orderBy`, `order`
- Parsing: `Packages/Module/Options/Loop/LoopUtils.php:135-279`.
- Custom attributes: `dcfdDateRules.innerContent.desktop.value`.

The field-level `visible` callback receives `args.attrs` (plus `moduleId`, `attrName` etc.), so fields can be shown or hidden from the Loop settings. Tested: the fields were hidden on a section without Loop.

**[SPEC CHANGE] Risk:** the group *header* still showed on a section with Loop off, with no fields in it.
- Spec 5.1 says the group is "only visible when Loop is enabled".
- I found no documented group-level `visible` option.
- Options: accept an empty header, or test an undocumented group `visible` key in Phase 1.

**Addendum (after the spike): fields can likely go inside Divi's own Loop group.** The builder's Loop group component passes its field list through a JS filter before rendering: `applyFilters("divi.module.options.loop.group.fields", fields, { attrName, grouped, groupLabel, fieldLabel, visible, defaultGroupAttr, loopValues })`. This is in `visual-builder/build/module.js`, the `divi/loop` group component, and isn't in the developer docs.
- `loopValues` is the element's current Loop settings, so added fields can use the same `visible` logic as Divi's own Loop fields.
- The group renders with `supportsPresets: false`, which also keeps the fields out of presets (Q10).
- This would remove the empty-header problem entirely.
- Not yet tested. Decision: test at the start of build step 1 (PLAN.md), with a fallback if it fails.

The groups filter also reaches child modules (accordion-item, slide, etc.). See Q6 for why the empty state can't work there.

### Q2. Custom attributes reaching both query paths

**Front end: yes.** `divi_loop_data_before_execution` and `divi_loop_data_after_execution` both receive the full block attributes, including `dcfdDateRules` and `dcfdEmptyState`. This held for text-module, section and row Loops (`LoopUtils.php:2748`, `:2803`).

**Builder REST: yes, for `post_types`.**
- `divi.module.layout.childModule.loop.resultsQueryParams` received the saved `dcfdDateRules` and `module.advanced.loop` attributes.
- The param set there (`dcfd_rules`) arrived in `$params` of `divi_module_options_loop_post_type_results_query_args` (`QueryResultsController.php:705`).
- The builder preview for S1 then showed only B and C, matching the front end.
- The REST `query_type` value is **`post_type`** (singular), not `post_types` (`QueryResultsController.php:59-73`).

**[SPEC CHANGE] Use `divi_loop_data_before_execution`, not `_after_execution`, on the front end.**
- The registry lookup `LoopQueryRegistry::get_query_if_matches()` runs *between* the two filters (`LoopUtils.php:2771-2777`), keyed on a signature of the query args.
- If a query with the same unfiltered args is already stored, `execute_query()` reuses it and ignores anything added in `_after_execution` (`LoopUtils.php:904-908`). A predictive query built by a Post Navigation module in an earlier document (such as a Theme Builder header) is one example.
- Adding the meta query in `_before_execution` changes the signature, so a stale match can't happen.
- It also works for `current_page`: after the current-page rebuild, `_merge_current_page_loop_sort_args()` copies the prior `meta_query` back (`LoopUtils.php:3452-3466`).
- Runtime evidence is for `_after_execution`. The `_before_execution` behaviour is from source, and Phase 1 re-tests it.

**Gap: builder preview for `current_page` has no Divi filter.**
- `_get_current_page_results()` builds and runs the query with no `apply_filters` (`QueryResultsController.php:724-765`).
- Supported options (decision needed, see PLAN.md):
  - (a) Leave `current_page` previews unfiltered. The builder already shows "Can't verify for this query type".
  - (b) A core `pre_get_posts` hook scoped to the `/divi/v1/loop/query-results` REST route when `query_type=current_page` and `dcfd_rules` is present.

### Q3. Filtering, including page 2

**Yes.** S1 (2 per page, title ascending, 7 posts, A excluded):

| Page | Posts |
|---|---|
| 1 | B, C |
| 2 (`?loop-dcfdtext=2`) | D, E |
| 3 | F, G |

Also confirmed:
- Missing field (C) and empty-string field (D) are shown, so fail-open works in SQL on SQLite.
- Group sub-field rule (S6, `key_facts_event_ends`): E is excluded, and everything else is shown, including posts with no `key_facts`.

Pagination is offset-based (`LoopUtils.php:181-197`), and the offset is applied to the filtered query, so page 2 is correct.

### Q4. Pagination module page counts

**Yes.** The "Pagination module" in 5.8.1 is **Post Navigation** (`divi/post-nav`) with `module.advanced.targetLoop`. Placed both above and below the Loop, it showed Next → page 2 on page 1, Previous and Next on page 2, and no Next on page 3 (3 pages).

Why position doesn't matter:
- Loop duplication runs for the whole document at parse time, before any block renders (`FrontEnd/BlockParser/BlockParser.php:950-956`).
- By the time Post Navigation renders, the filtered query is already in `LoopQueryRegistry`, and `loop_pagination_total_pages` is set on the first duplicated block (`LoopUtils.php:2889-2893`).
- `PostNavigationModule::get_loop_pagination_data()` reads those (`PostNavigation/PostNavigationModule.php:865-905`).

Edge case: when the filtered Loop is **empty**, nothing is stored in the registry (`LoopUtils.php:2809-2818`). `get_loop_pagination()` then calls `LoopQueryRegistry::get_query()`, which builds an **unfiltered** predictive query (`PostNavigationModule.php:716`; `LoopQueryRegistry.php:88-106`; `LoopUtils.php:100-124`, which runs no filters).
- Page count is still 1, because no block carries `loop_pagination_id`.
- But if the **WP-PageNavi** plugin is active, it's handed the unfiltered query (`PostNavigationModule.php:758-824`) and would show page links for an empty Loop.
- Low risk. WP-PageNavi isn't on either production stack per `platform-docs.md`. Documented in the readme.

### Q5. Infinite scroll, AJAX pagination, Post Filter

**[SPEC CHANGE] None of these exist in Divi 5.8.1.**
- The module list (`visual-builder/packages/module-library/src/components/`) has no post-filter, infinite scroll or load-more module.
- `grep -ri "infinite|loadMore|post-filter"` over the server source finds only recursion guards and unrelated modules.
- Loop pagination is a full page load with `?{loopId}=N`, which goes through the front-end path tested in Q3.

Nothing to support in v1. Spec sections 7.5 and 10 ("Infinite scroll and Post Filter") can be dropped. Re-check on each Divi update.

### Q6. `divi_loop_rendered_output` with `__loop_no_results`

**Yes, for parent-level containers.** On an empty Loop:
- `LoopUtils.php:2821-2823` sets `$block['attrs']['__loop_no_results'] = true`.
- The wrapper registered for every module (`ModuleRegistration.php:272`; `LoopUtils.php:3358-3399`) renders the container with the native "No Results Found" content, then passes the whole container HTML through `divi_loop_rendered_output`.

The filter fired for the S2 section, S3 row and S4 text module, and returning a replacement string replaced the whole container. S5 with no Library item kept Divi's "No Results Found".

Limits:
- **Child modules** (accordion-item, slide, tab, etc.) return `''` *before* the filter runs (`LoopUtils.php:3361-3364`), so an empty state can't be attached to a child-module Loop. The readme will say so, and ideally the settings are hidden for child modules.
- The filter fires for *every* module render, so the callback must bail cheaply.

### Q7. Rendering a Library item with its styles

**`do_blocks( $layout->post_content )` inside the hook works.** It gives clean markup with no extra wrappers, and the Library item's module styles go into Divi's normal style collection. They land in the page CSS: `.et_pb_section_6.et_pb_section{background-color:#ff00aa}`, `.et_pb_row_7{...}`, `.et_pb_text_14{...}`.

The module order classes carry on from the page counter, so there are no class collisions.

Compared:

| Method | Markup | Styles on a cold cache |
|---|---|---|
| `do_blocks()` | clean | ✓ |
| `et_builder_render_layout()` (`includes/builder/core.php:292`) | adds `et-l et-l--post` + `et_builder_inner_content` wrappers inside the container | ✓ |
| `BlockParserStore::render_inner_content()` (the Blog module pattern) | same wrappers | ✓ |
| `render_inner_content()` + inline `Style::render()` | same wrappers | ✓, but duplicated CSS |

Recommendation: `do_blocks()`, a WordPress core function. It skips the `et_builder_render_layout` shortcode/wpautop chain, so a legacy Divi 4 shortcode Library item wouldn't render. Divi 5 items are blocks.

**Caveat:** styles are generated **only when the page's static CSS isn't already cached**. `Module.php:721` skips style generation when `StaticCSS::$styles_manager->enqueued` is true. See Q8.

### Q8. Static CSS risks

**Risk 1 is real, reproduced.**
1. Rules off, so the Loops had results.
2. Cold cache render: the page CSS file was written.
3. Rules on: the empty states rendered, but with no `#ff00aa` in the HTML or the cached CSS, on repeat loads too.

**Risk 2 (featured-image CSS variables) was not reproduced**, because I didn't build a dynamic background in the spike. From source it's the same mechanism: Divi forces inline styles for random-order Loops specifically "to prevent stale CSS variable caching" (`FrontEnd/Assets/StaticCSS.php:251-252`, `:319`). Phase 1 manual matrix covers it.

**[SPEC CHANGE] The spec 6.5 fix (signature + `ET_Core_PageResource::remove_static_resources()` on render) doesn't work for visitors.** `remove_static_resources()` returns early unless the current user passes `et_core_security_check_passed( 'edit_posts' )` or it's WP-Cron (`themes/Divi/Divi/core/components/PageResource.php:1226`). An anonymous visitor's render can't clear anything. Scheduling a cron clear would leave the first visitor unstyled.

**Fix, tested:** mirror Divi's own random-order handling and force inline styles on pages that use date rules.
- Hook: `divi_frontend_assets_static_css_module_style_manager`, a filter documented in source (`StaticCSS.php:377-386`, `:407-408`).
- Set `forced_inline = true`, `write_file_location = 'footer'` and `set_output_location( 'footer' )` on the manager (and deferred manager), plus `add_hooks = true`. These are the same public properties Divi sets itself at `StaticCSS.php:391-403`.
- Result: `#ff00aa` printed inline on every request (3 of 3), and no static file link.
- It fixes risk 1 and, by Divi's own reasoning, risk 2.
- Cost: those pages' builder CSS is generated inline on every request. Acceptable, since the spec already requires page caching to be off for them.
- **Correction (0.1.2):** setting these properties on the manager Divi hands the filter was wrong when that manager is the shared `core` / `unified` resource. It also carries the Theme Customizer CSS, which `et_divi_add_customizer_css()` skips when the unified file already exists, so the Customizer CSS vanished. The spike missed it because the test page had no cached file yet. Divi's own forced path (`StaticCSS::setup_styles_manager`) uses a separate `builder` / `module-design` resource, and the plugin now swaps that in instead.
- Caveat: it relies on public properties of `ET_Core_PageResource` inside that filter. That is not a private method, but it isn't documented in the developer docs either. **Flagged for your approval.**

**Detection must cover Theme Builder layouts.** The spike checked only the main post's `post_content`. Divi's own check uses `ET_Post_Stack::get()` (`LoopUtils.php:2472-2505`), and Phase 1 must include TB body, header and footer layouts.

### Q9. Deactivation

**Front end: acceptable.** With the spike code removed:
- The page returned HTTP 200 with no warnings.
- `dcfdDateRules` and `dcfdEmptyState` were ignored.
- Every Loop showed all posts (S2 to S5 showed post A).
- An empty Loop falls back to native output, since nothing hooks `divi_loop_rendered_output`.

`Developer/handling-unknown-attributes.md` covers Divi 4 → 5 *conversion* only and doesn't apply to Divi 5 block attributes.

**Not tested:**
- Whether the builder keeps or strips unregistered `dcfd*` attributes when a page is **saved** with the plugin inactive.
- Whether it keeps them on a normal save with the plugin active, and whether server-side `divi_module_library_register_module_attrs` registration is then needed.

This is Phase 1's first test (a save round-trip). Front-end rendering doesn't need server-side registration: the attributes reached `$block['attrs']` without it.

### Q10. Excluding attributes from presets

**Yes, by documentation:** `features.preset: 'content'` (or `'meta'`) means "Not included in presets" (`Developer/implementing-option-group-presets.md:181-182`; `Developer/preset-attribute.md:41`). The spike used `preset: 'content'`. I didn't check the preset modal at runtime. It's in the Phase 1 builder checks.

## Where SPEC.md and the source disagree

| Spec | Source / evidence | Change |
|---|---|---|
| 6.1: modify front-end query in `divi_loop_data_after_execution` | Registry reuse between the filters can bypass it (`LoopUtils.php:2771-2808`) | Use `divi_loop_data_before_execution` |
| 6.1: REST filter covers the builder preview | Covers `post_type` only; `current_page` has no filter (`QueryResultsController.php:724-765`) | Decide: unfiltered preview or scoped `pre_get_posts` |
| 6.5: clear static CSS via signature + `remove_static_resources()` | Needs `edit_posts` or cron (`PageResource.php:1226`) | Force inline styles via the style-manager filter |
| 6.5: no signatures stored → 11: `uninstall.php` removes signatures | Force-inline stores nothing | No `uninstall.php` needed unless something is stored |
| 7.5, 10: infinite scroll, AJAX pagination, Post Filter | Don't exist in 5.8.1 | Drop from v1 |
| 5.1: own settings group, only visible when Loop enabled | Field-level `visible` works; a separate group's header stays visible | Put the fields inside Divi's Loop group via `divi.module.options.loop.group.fields` (to test in step 1) |
| 6.1: register attrs server-side | Not needed for the front end | Only if the Phase 1 save test shows stripping |
| Query type naming | JS/front end `post_types`; REST `post_type` | Handle both |
| 5.3: empty state on "any" container | Child modules bypass the filter (`LoopUtils.php:3361-3364`) | Exclude child modules; readme note |

## Risks the spec misses

1. **"Now is equal to" is true for one second.** A Date Time rule is true at exactly one second, and a Date Picker rule only at 23:59:59. The spec says this is deliberate, but site owners will almost never see it match. The readme should say so plainly.
2. **DST fall-back hour.** ACF stores local wall-clock time with no offset, so the repeated 01:00 to 02:00 hour on the last Sunday in October is ambiguous. `current_datetime()` returns wall-clock time too, so comparisons follow the clock on the wall: a post ending "01:30" matches twice. Low impact; document it. UTC-offset sites have no DST.
3. **Loop with no post type selected** (`subTypes` empty → `post_type = ['any']`, `LoopUtils.php:416-419`). Field resolution needs a rule for "any": check all public post types that have the field.
4. **WP-PageNavi with an empty filtered Loop** shows unfiltered page links (Q4).
5. **Performance.** Each rule adds a `LEFT JOIN` for NOT EXISTS plus joins for the other clauses. Fine at food bank / charity scale.
6. **MySQL string comparison.** `CAST(meta_value AS CHAR)` compares using the column collation. The values are digits and fixed-format strings, so any collation sorts them correctly, but spec 10's "one MySQL run" stays mandatory.
7. **Builder preview may share results between Loops that differ only by `search`.** S2 to S5 showed the same preview as S1 in the builder, and only 2 REST requests were logged for 6 Loops. Custom params are part of the request, so date rules aren't affected. Noted only because it looks odd while testing.
8. **Browser tooling.** Builder screenshots timed out repeatedly. Live refresh of the preview *after editing* a rule wasn't exercised; only the initial render with saved rules was. That's in the Phase 1 manual matrix.
