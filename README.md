# Date Conditions for Divi

**Show posts in a Divi 5 Loop only when their dates say they should be shown, and show something friendly when there's nothing left.**

Lots of sites list things that come and go with time: events, job vacancies, courses, offers, deadlines. Divi 5's Loop Builder can list them, but it can't hide the ones that have ended or haven't started yet. So someone has to remember to unpublish old posts, and a page can end up showing last month's events.

This plugin adds two settings to Divi's Loop options:

- **Date rules:** show a post only if *now* is before, after or equal to a date stored on the post in an ACF date field. For example, show events whose booking has opened and which haven't finished yet.
- **Empty state:** pick a Divi Library item to show when no posts pass the rules. For example: "No upcoming events, check back soon."

It all happens automatically as time passes. Nothing needs saving or unpublishing.

## What you need

- WordPress with **Divi 5.8.1** or later
- **Advanced Custom Fields** (free or PRO) or **Secure Custom Fields**
- PHP 7.4 or later
- **Page caching turned off** for pages that use date rules. The results change on their own as time passes, so a cached page would go out of date.

## Install

1. Download the zip from the [latest release](../../releases/latest).
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, choose the zip and activate it.
3. Give your post type an ACF **Date Picker** or **Date Time Picker** field, such as `event_ends`.
4. In the Visual Builder, open an element with **Loop** turned on. You'll find the new settings under **Content → Loop**, below Divi's own Loop settings.

Full instructions are in the [plugin's readme](date-conditions-for-divi/readme.md).

## Example

An events page that shows only events you can currently book:

| Setting | Value |
|---|---|
| Date rule 1 | `booking_opens`, *Now is after the date* |
| Date rule 2 | `event_ends`, *Now is before the date* |
| When no posts match, show | a Library section saying "No upcoming events, check back soon" |

## Good to know

- The rules filter the database query itself, so pagination and page counts only count the posts that are shown.
- Times use your site's timezone (Settings → General). A date-only field counts as 23:59:59 on that day.
- A post with no date in the field is always shown for that rule.
- If a field name can't be found, or isn't a date field, the builder warns you and the rule is ignored.
- Up to two rules per Loop, and both must pass.
- Works on sections, rows, columns, groups and modules, for Loops that query **Post Types**, or **Current Page** in Theme Builder templates.
- It doesn't work with Repeater or options-page fields. Accordion items, slides and tabs can't have an empty state.

Changes in each version are in the [changelog](CHANGELOG.md).

## Licence

[MIT](LICENSE).

---

## For developers

### How it works

Divi 5 builds each Loop's `WP_Query` from the element's settings. The plugin:

1. **Adds settings in the Visual Builder** with Divi's JS hooks. It registers the attributes with `divi.moduleLibrary.moduleAttributes`, draws the fields inside Divi's own Loop group with `divi.module.options.loop.group.fields`, and adds a field-check message component with `divi.fieldLibrary.getFieldComponent`. The rules are saved on the element.
2. **Adds a `meta_query` to the Loop's query.**
   - Front end: `divi_loop_data_before_execution`.
   - Builder preview: `divi_module_options_loop_post_type_results_query_args`.
   - Each rule becomes *field missing* OR *field empty* OR *date comparison*, so missing or empty values let the post through.
   - Values are compared as strings, since ACF stores sortable `Ymd` and `Y-m-d H:i:s` formats. The same SQL works on SQLite and MySQL.
3. **Swaps in the Library item** when Divi flags a Loop as empty (`divi_loop_rendered_output` with `__loop_no_results`). It renders the item with `do_blocks()`, and also prints the item's Divi global variable and colour definitions, since Divi only defines the ones it finds in the page itself.
4. **Forces inline CSS** on pages that use date rules or an empty state, with `divi_frontend_assets_static_css_module_style_manager`. Otherwise Divi's cached static CSS would go stale as results change. Like Divi's own random-order Loops, it switches module CSS to Divi's separate `builder` / `module-design` inline resource, and leaves the shared stylesheet that carries the Theme Customizer CSS alone.

Known limits:
- The builder preview for *Current Page* Loops isn't filtered. The live page is.
- With the WP-PageNavi plugin, an empty Loop may still show page links.
- "Now is equal to" is only true for one exact second.

### Documents

| File | What it is |
|---|---|
| [SPEC.md](SPEC.md) | The v1 specification: scope, behaviour, date semantics, security, edge cases |
| [SPIKE-REPORT.md](SPIKE-REPORT.md) | Feasibility findings, with Divi source references, and where the spec turned out to be wrong |
| [PLAN.md](PLAN.md) | Build plan: file structure, how each rule maps to SQL, hook choices, decisions |
| [CHANGELOG.md](CHANGELOG.md) | Changes in each version |
| [tests/MANUAL-TESTS.md](tests/MANUAL-TESTS.md) | Full test matrix and results |

### Running the tests

You need a local WordPress site with Divi 5 and ACF, with the `date-conditions-for-divi/` folder linked or copied into `wp-content/plugins/`. Run WP-CLI from that site.

1. Create the test data. This makes an ACF field group "DCFD spike" on posts, seven posts `DCFD A` to `DCFD G`, three Library items and the page `/dcfd-loop-spike/`. It also sets the site timezone to Europe/London. Both scripts are safe to re-run, and should be re-run on each new day.
   ```
   wp eval-file tests/fixtures/setup-test-data.php
   wp eval-file tests/fixtures/build-test-page.php
   ```
2. Run the tests:
   ```
   php tests/rules-test.php                             # date logic, no WordPress needed
   bash tests/check-test-page.sh https://your-site.test # front end
   wp eval-file tests/rest-check.php --user=<admin>     # REST endpoints
   wp eval-file tests/current-page-check.php            # Current Page Loops
   ```

The expected results assume "now" is between 1 September and 1 December 2026. After that, update the fixture dates in `tests/fixtures/setup-test-data.php` and the expectations in `tests/check-test-page.sh`.

### Building a release

1. Bump the version in the plugin header and the `DCFD\VERSION` constant, and add a CHANGELOG.md entry.
2. Zip the `date-conditions-for-divi/` folder so the zip contains that folder. On Windows, use `tar -a -c -f date-conditions-for-divi-<version>.zip date-conditions-for-divi`, not PowerShell's `Compress-Archive`, which writes backslash paths that Linux hosts extract wrongly.
3. Create a GitHub release tagged `v<version>` with the zip attached.

### Conventions

- Prefix `dcfd_`, PHP namespace `DCFD`, JS hook namespace `dcfd`, Divi attributes `dcfd*` (e.g. `dcfdDateRules`, `dcfdEmptyState`).
- Plain JavaScript, no build step.
- Only public Divi hooks and methods, including public filters that are only documented in Divi's source. No private methods, and no parsing of rendered HTML.
- No translation-ready strings yet.
