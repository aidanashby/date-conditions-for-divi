# Date Conditions for Divi

Show or hide posts in a Divi 5 Loop based on ACF date fields, compared with the current date and time. Show a Divi Library item instead when no posts are left.

Version 0.1.2. MIT licence. Source and releases: https://github.com/aidanashby/date-conditions-for-divi

## Requirements

- Divi 5.8.1 or later
- Advanced Custom Fields (free or PRO) or Secure Custom Fields, active
- PHP 7.4 or later
- **Page caching turned off for any page that uses date rules** (see "Caching" below)

## Setting it up

1. Upload and activate the plugin.
2. In ACF, give your post type a **Date Picker** or **Date Time Picker** field, e.g. `event_ends`.
3. In the Visual Builder, open an element that has **Loop** turned on (a section, row, column, group or module).
4. In **Content → Loop**, under Divi's own Loop settings, you'll find:
   - **Date rule 1: ACF field name** and **Date rule 1: show a post when**
   - **Date rule 2** (optional)
   - **When no posts match, show**

These settings only appear when Loop is on and the Loop's query type is **Post Types**, or **Current Page** (Divi only offers Current Page in Theme Builder templates, such as a blog or category archive).

## How the date rules work

A rule reads as: *show the post if **now** [is after / is before / is equal to] **the date in this field**.*

- **Now** is the current date and time in your site's timezone (Settings → General), to the second.
- **Date Time Picker** fields are compared to the second.
- **Date Picker** fields (date only) count as **23:59:59 on that day**. So:
  - "Now is before 31 December" stays true all of 31 December, until 23:59:58.
  - "Now is after 1 December" becomes true at midnight at the start of 2 December.
- **"Now is equal to"** is only true for a single second (the exact time in the field, or 23:59:59 for a date). You'll rarely need it.
- **If a post has no value for the field, the rule is skipped for that post, so it's shown.** This is handy when a Loop shows several post types and only some have the field.
- **Two rules** must both pass.
- If rule 1 is empty and rule 2 is filled in, rule 2 works on its own.

Example: to show events whose booking is open and which haven't ended, use:
- Rule 1: `booking_opens`, *Now is after the date*
- Rule 2: `event_ends`, *Now is before the date*

### Field names

Use the ACF **field name** (not the label). For a field inside an ACF **Group** field, join the names with underscores: a field `event_ends` inside a Group `key_facts` is `key_facts_event_ends`. Groups inside groups work the same way (`outer_inner_field`).

Repeater, flexible content and options-page fields aren't supported.

Under each field name the builder tells you what it found:

| Message | Meaning |
|---|---|
| Date field found (Date Picker / Date Time Picker) | Good to go |
| Field not found for *post type* | No field with that name on the post types this Loop shows. The rule is ignored. |
| Field is not a date field | It exists but isn't a Date or Date Time Picker. The rule is ignored. |
| Field is a different type on different post types | Rename one of them. The rule is ignored. |
| Can't verify for this query type, will be checked when the page loads | Current Page Loops can't be checked in the builder |

Warnings never stop you saving.

### In the Visual Builder

- **Post Types Loops:** the preview updates as you change a rule.
- **Current Page Loops:** the preview ignores the rules. The live page applies them.

## Empty state

Choose a **Divi Library** item under **When no posts match, show**. When the Loop has no posts to show, that Library item replaces the whole element on the live site, including Divi's "No Results Found" message.

- **Match the Library item to the element.** Use a section item to replace a section, a row item for a row, and a module item for a module. Otherwise the layout may nest oddly.
- **If you choose nothing,** or the item is later deleted, trashed or unpublished, Divi's normal "No Results Found" message shows instead.
- **In the Visual Builder,** an empty Loop shows Divi's own message, not the Library item.
- **Not available on child modules** (accordion items, slides, tabs). Divi doesn't give plugins a way to replace their empty output. Date rules still work on them.

## Caching

Because posts appear and disappear as time passes, with nothing being saved, **pages using this plugin must be excluded from page caching** (e.g. LiteSpeed Cache → Excludes → Do Not Cache URIs). The plugin doesn't do this for you.

Divi's own CSS cache is handled for you: on pages that use date rules or an empty state, the plugin tells Divi to print that page's builder CSS inline instead of from a cached file, so a newly shown Library item is always styled. Divi does the same for its random-order Loops.

When updating from 0.1.0 or 0.1.1, clear Divi's static CSS once afterwards (the clear-cache button on the Divi → Theme Options page).

## Things to know

- **Pagination:** the Pagination (Post Navigation) module and page counts only count posts that pass the rules.
- **WP-PageNavi:** if this plugin is active and a filtered Loop ends up empty, WP-PageNavi may still show page links.
- **Clocks going back:** ACF stores times without a timezone offset. During the hour the clocks go back (last Sunday in October in the UK), a time in that hour happens twice, so a rule on it can match twice.
- **Deactivating** leaves your settings saved on the page. While the plugin is off, Loops show all posts and Divi's normal empty message.
- **ACF deactivated:** date rules are ignored, so all posts show, until ACF is back.
- **Debug:** with `WP_DEBUG` on, a rule that's ignored because its field can't be found is logged once per page load to the PHP error log.

## Uninstalling

The plugin stores nothing of its own (no options, no database tables), so there's nothing to clean up. Settings saved on pages stay in the page content, where Divi ignores them.
