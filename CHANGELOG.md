# Changelog

All notable changes to Date Conditions for Divi. Versions follow [semantic versioning](https://semver.org/).

## 0.1.3 (2026-09-24)

### Added

- **Updates from GitHub.** The plugin now checks this repo's latest release about twice a day and offers it as a normal WordPress plugin update, using the bundled [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) 5.7 (MIT). Sites running 0.1.2 or earlier need to upload 0.1.3 by hand once; later versions arrive as updates.
- **Clean uninstall.** Deleting the plugin removes the update checker's stored data (one site option and one scheduled event). Settings saved on pages are left in the page content, where Divi ignores them.

## 0.1.2 (2026-09-24)

### Fixed

- Pages using the plugin lost Divi's **Theme Customizer** CSS (e.g. default button colours, heading sizes, layout widths), so elements such as a Theme Builder header button looked different. It happened once Divi had already cached the page's stylesheet, which is why it didn't show on a fresh site. The plugin was forcing Divi's shared page stylesheet inline, and Divi skips adding the Customizer CSS to that stylesheet when its cached file exists. The plugin now does what Divi does for random-order Loops: it moves only the builder module CSS to Divi's separate inline stylesheet and leaves the shared one alone.

### Upgrading

- After updating, clear Divi's static CSS once (the clear-cache button on the Divi → Theme Options page), or re-save each page that uses the plugin. This removes cached stylesheets written by earlier versions.

## 0.1.1 (2026-09-24)

Confirmed in real use on a MySQL staging site.

### Fixed

- Empty-state Library items styled with Divi **global variables** or **global colours** (e.g. padding set to a spacing variable) lost those styles when the page's own content used different variables. Divi only defines the variables it finds in the page and Theme Builder content, never inside the swapped-in Library item. The plugin now prints the item's own variable and colour definitions with it.

## 0.1.0 (2026-09-23)

First build. Tested on Divi 5.8.1, ACF 6.8.10, WordPress 7.1.2 (local WordPress Studio site, SQLite).

### Added

- **Date rules** on any Divi 5 Loop querying Post Types, or Current Page (Theme Builder templates).
  - Up to two rules, combined with AND. Each is an ACF field name plus "Now is after / before / equal to the date".
  - Supports ACF Date Picker and Date Time Picker fields, including fields inside ACF Group fields (and Groups inside Groups).
  - Date Picker dates count as 23:59:59 on that day. Date Time Picker values are compared to the second. "Now" is the site timezone.
  - A post with no value for a rule's field is always shown for that rule.
  - A rule whose field can't be found, isn't a date field, or has different types on different post types is ignored for the whole Loop (logged once per page load with `WP_DEBUG` on).
  - Rules change the query itself, so pagination and page counts only count posts that pass.
- **Empty state:** a Divi Library item that replaces the whole Loop element when no posts pass. It falls back to Divi's "No Results Found" if no item is chosen, or the item is missing, trashed or unpublished.
- **Visual Builder settings** inside Divi's own Loop group, shown only when Loop is on with a supported query type. Includes:
  - live preview refresh for Post Types Loops;
  - a field check under each field name (found / not found / not a date field / can't verify);
  - a Library item dropdown.

  The settings aren't responsive, hover or sticky, and aren't included in presets.
- **Forced inline builder CSS** on pages that use the plugin, so Divi's static CSS cache can't go stale as results change over time (the approach Divi uses for random-order Loops).
- REST endpoints `dcfd/v1/field` and `dcfd/v1/layouts` for the builder, restricted to users who can edit posts. They never return stored field values.
- Tests: plain-PHP date-semantics tests, front-end checks, REST checks, a `current_page` check, test data fixtures, and a manual test matrix (`tests/MANUAL-TESTS.md`).

### Known limitations

- The builder preview for Current Page Loops isn't filtered, because Divi offers no hook there. The live page is filtered.
- No empty state on child modules (accordion items, slides, tabs); date rules still work there.
- With the WP-PageNavi plugin, an empty filtered Loop may still show page links.
- "Now is equal to" is only true for one second.
- In the empty-state dropdown, "None" is listed last.
- Relies on some public Divi hooks that are in Divi's source but not its developer docs. PHP: `divi_loop_data_before_execution`, `divi_loop_rendered_output`, `divi_frontend_assets_static_css_module_style_manager`. JS: `divi.module.options.loop.group.fields`, `divi.fieldLibrary.getFieldComponent`, `divi.fieldLibrary.fieldComponentMap`. Re-check after Divi updates.

