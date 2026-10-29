# What this fork changes

This is a fork of MantisBT 2.28.4 (tag `release-2.28.4`), kept on the branch `chamsys-2.28`. It is not meant to
be merged upstream: it is an open fork that adds things MantisBT does not have and fixes a few of its defects.

The rules it is built with:

- Everything that changes behaviour is **off by default**, behind a `$g_...` option. A fork install that
  sets nothing behaves like 2.28.4, apart from the bug fixes and the markup additions below.
- Changes to markup are **additive**: attributes, classes and wrappers are added, nothing is renamed or moved,
  so plugins and themes written for 2.28 keep working.
- Everything a user reads goes through `lang_get()`, new events are documented in the Developers Guide and new
  options in the Administration Guide (`docbook/`).
- PHP 7.4 syntax, MantisBT coding guidelines.
- Each change is its own small commit: `git log --oneline release-2.28.4..` is the full list (about 115).

## At a glance

| What | Option | Default |
|---|---|---|
| Search in a search engine (Meilisearch) instead of SQL `LIKE` | `$g_search_backend` | `database` (as stock) |
| Suggestions as you type in the navbar search box | `$g_search_live_enabled` | OFF |
| "Have you seen these issues?" while reporting | `$g_report_similar_issues` | ON, but needs the line above |
| Look up users as you type in the user filters | `$g_user_typeahead_min_users` | 0 (never) |
| Hide other users from the user lists | `$g_show_user_list_threshold` | ANYBODY (as stock) |
| Likes on issues | `$g_enable_likes` | OFF |
| Layout for phones | `$g_mobile_layout` | OFF |
| Several favicons (SVG, PNG sizes, Apple icon) | `$g_favicon_image` as a list | one icon, as stock |
| An image for the brand in the navbar | `$g_navbar_logo_image` | empty (the site name) |
| Themes from plugins, light and dark | `$g_theme`, `$g_color_scheme` | `default`, `auto` |
| Largest page of the issue list | `$g_filter_per_page_max` | 0 (no limit) |
| utf8mb4 connections | `$g_db_charset` | stock behaviour |
| Switch off the admin directory warning | `$g_admin_dir_check` | ON |

## Search

- **Search backends** (`$g_search_backend`): `database` is the stock SQL search. `meilisearch` sends the text
  search of the issue filter to a [Meilisearch](https://www.meilisearch.com/) index, which is typo tolerant and
  fast on large trackers. The index holds the summary, description, steps to reproduce, additional information,
  notes, category and tags. The filter still decides what a user may see: the engine only finds candidate issues
  (at most `$g_search_candidate_limit`), and the rest of the filter and the access rules narrow them down.
  Private note text is kept apart in the index and only searched for users who may read private notes.
  Settings: `$g_search_meilisearch_url`, `_key`, `_index`, `_timeout`. The backend is an interface, another
  engine can be added.
- **Keeping the index up to date:** issues that are created, changed, deleted or have a note change are queued in a
  new table, and `php scripts/search_index.php` drains the queue (run it from cron or as a service).
  `--setup` creates the index or updates its settings, `--reindex` rebuilds it from all the issues, `--status`
  shows the backend and the queue.
- **Quick search** (`$g_search_live_enabled`): suggestions under the search box of the navbar while typing, with
  keyboard support (it is a WAI-ARIA 1.2 combobox), a link to all results, and a per-user rate limit
  (`$g_search_live_rate_limit`, per minute). It only works with an indexed backend, so that it stays fast. It uses
  the same issue filter as every other list, so it cannot show an issue the user may not see.
- **Similar issues** (`$g_report_similar_issues`): the report page suggests existing issues as the summary is typed,
  to avoid duplicates. It is available when quick search is.
- The **jump to issue** box searches text when what is typed is not an issue number, and accepts `#123`.

## Users

- **User lookup as you type** (`$g_user_typeahead_min_users`): on a tracker with many users, the Reporter,
  Monitored By and Note By filters no longer render every user in a `<select>`,
  which can be megabytes of HTML. Above the threshold they become a search box. What can be found is a subset of
  what the dropdown would have listed, never more: it follows the project, the access thresholds, the "show real
  names" setting and `$g_limit_reporters`; it never returns email addresses; it has a minimum query length, a
  result cap and a rate limit (`$g_user_typeahead_rate_limit`). Without JavaScript the list holds the users that
  are selected.
- **Merge two accounts:** `user_merge()` and a page (a button on the details page of a user) give everything the
  first account did and owns to the second and delete the first: issues, notes, files, history entries,
  revisions, tags, categories, news, sponsorships, filters and profiles, and the monitoring, likes, project access
  and configuration where the second account has none of its own (its own are kept). It runs in a transaction,
  asks for the password again, needs the access of deleting a user for both accounts, cannot merge the own account
  or the last administrator, and is logged (`LOG_USER_MANAGEMENT`). Plugins give the rows they keep for users on
  `EVENT_MANAGE_USER_MERGE`. The old and new values of a handler or reporter change in an issue's history are not
  rewritten.
- **`$g_show_user_list_threshold`**: stock MantisBT lists every user who could be reporter or handler to everyone,
  which on a tracker open to customers lets them see each other. Raise this to a minimum access level to stop it.

## Likes

Users can say they like, or are affected by, an issue (`$g_enable_likes`). A button on the issue page, a count that
everybody who sees the issue sees, a sortable Likes column for the issue list, and a "most liked" block on the
summary page. Who liked is shown from `$g_show_likers_threshold`. Liking never changes when an issue was last
updated and never sends mail. Access is `$g_like_bug_threshold`, with `$g_like_own_issue` and
`$g_like_readonly_issues`. Also in the REST API (`/issues/{id}/likes`) and as the events `EVENT_BUG_LIKE` and
`EVENT_BUG_UNLIKE`.

## Phones

`$g_mobile_layout` adds a stylesheet and a script that apply below 768 pixels and change nothing on larger screens:

- stacked rows on the issue and update pages and one card per issue in the list, with the color of the status;
- a box of My View without issues is only its header (`data-empty` on the box);
- one field per line in the filter (a tap on a value opens it for editing), a pager that shows the current page and
  the links to move;
- inputs of 16px and targets of 44px (no zoom on focus on iOS), safe area insets;
- a bar at the bottom of the screen with the first four items of the main menu (or the ones in
  `$g_mobile_bar_items`) and a Menu button for the sidebar. `EVENT_LAYOUT_MOBILE_BAR` lets a plugin add entries to
  it, and a theme that draws its own sidebar marks it `data-sidebar` and its button `data-sidebar-toggle`;
- on the issue page, the panels (details, notes, history) shown one at a time, chosen with tabs that follow the
  WAI-ARIA tabs pattern (arrow keys, Home and End, right to left aware). Links to something in another panel
  (`#c123`, `#history`, `#attachments`) open that panel first;
- the form to add a note and the buttons of an issue (edit, assign, change status, monitor, like, ...) open as
  sheets from the bottom of the screen, from a button on the page. The sheet is the element of the page, not a
  copy, and it is a modal dialog while it is open: the focus goes in and stays in, Escape or a tap on the scrim
  closes it, the focus goes back to the button. After a note is sent the issue opens on its notes;
- the filter, which is several screens long on a phone, starts collapsed when there are issues to show (not saved,
  so what the user chose on a larger screen is kept).

It needs no theme. A theme restyles it through the same markup (`data-sheet` marks a sheet). Plugins that add something to the issue page
should wrap it in an element with `data-panel="details"`, `"notes"` or `"history"`.

## Themes

- **Themes from plugins:** `EVENT_THEMES` lets a plugin offer themes (stylesheets, scripts, color schemes);
  `$g_theme` chooses one and users can choose their own on the preferences page. A theme can ask for
  `viewport-fit=cover`.
- **Light and dark:** `$g_color_scheme` (`light`, `dark`, `auto`) for themes that style more than one, per-user too.
- **Icons and brand:** `$g_favicon_image` can be a list of icons with their `rel`, `type` and `sizes` (a string works
  as before and prints the same link), and `$g_navbar_logo_image` shows an image in the navbar in place of the
  site name (which becomes its text alternative). The footer can be replaced with `EVENT_LAYOUT_FOOTER`.
- **Hooks for styling:** `EVENT_LAYOUT_HTML_ATTRIBUTES` and `EVENT_LAYOUT_BODY_ATTRIBUTES`; the status colors as
  CSS variables `--status-<id>` and `data-status` on issue rows and status cells; the user's font as
  `--font-family`.

## Markup for stylesheets, scripts and accessibility

- **Landmarks and labels:** a skip link, `role="navigation"` regions with names, translated screen reader texts, an
  `<h1>` for assistive technology on every page (`layout_page_title()`), labels for the login fields, the note
  text area and the controls of the filter, `aria-current` in the menu and the pager, which is now a `<nav>`, and
  `role="main"` on the content of the page (a theme that draws a `<main>` of its own should not print a second
  one). `$g_pager_natural_order` prints the pager in reading order (first, previous, the pages, next, last) instead
  of last first floated to the right.
- **Filter controls have names:** each control of a filter field gets the name of its field (`aria-label`) by script,
  in the issue list and on the static filter page. The static filter page still has unnamed controls without
  JavaScript.
- **Data attributes** that stay stable: `data-section` on the rows of the issue page, `data-panel`, `data-label`,
  `data-note-id`, `data-user-id`, `data-bug-id`, `data-status`, `data-menu-id` on the sidebar, `data-pager`, `data-empty`,
  `data-field` and `data-label` in the filter. The `#attachments` anchor is on the attachments row.
- **`mantis:content-loaded`:** an event fired on the document when a field of the filter is loaded dynamically, so
  scripts do not need a `MutationObserver`.

## For plugin authors

Things a plugin could only do by rewriting the page output before:

- **Replace parts of the layout:** `EVENT_LAYOUT_NAVBAR`, `_SIDEBAR`, `_BREADCRUMBS` and `_FOOTER` receive the same
  data the default code uses and replace it when the callback prints and returns true; `EVENT_MENU_USER` for the
  user menu; `layout_get_sidebar_items()`, `layout_get_user_menu_items()` and `layout_get_projects_menu()` return
  the main menu, the user menu and the project switcher as data. Menu events accept array items.
- **Record errors:** `EVENT_ERROR` offers an application error to the plugins before it is logged; a callback that
  records it (with more context, or in a log service) returns true and the fork does not write its own line.
- **Write pages without boilerplate:** `MantisPlugin::pages()` declares the access and CSRF rules of a plugin's
  pages (closed by default), `plugin_page()` takes query parameters, `layout_widget_begin/end()` draws the standard
  widget, `layout_url_is_current_page()` lights the active sidebar item and tab of plugin pages,
  `html_print_json_data()` passes data to scripts without inline script, `EVENT_BUG_PAGE` lets a plugin replace
  the report or update page.
- **Assets:** `require_js()` works for plugin files, `plugin_require_css()` / `plugin_require_js()`, and a way to
  load a stylesheet last. `plugin_file.php` is served without the database, plugins or a session cookie, with
  immutable cache headers and the modification time in the URL.
- **Events for what happens in the tracker:** `EVENT_NOTIFY_RECIPIENTS` tells who is notified of a change to an
  issue, a note, a mention or a reminder (user ids, after preferences and access were applied: for in-app
  notifications or webhooks; it is signalled where the emails are sent, so not when email notifications are off);
  `EVENT_SUMMARY_PANELS` adds panels to the summary page; `EVENT_MANAGE_USER_MERGE` moves a plugin's rows when
  users are merged.
- **Display names:** `EVENT_DISPLAY_USER_NAME` changes how a user is shown, and user names carry `data-user-id`.
- **Rate limiting:** `token_rate_limit_allow()` limits requests per user, for endpoints that plugins add.
- **Internal REST group** `/api/rest/internal/` for endpoints used by the pages themselves (issue search, user
  search).

## Signing in with an external identity provider

Hooks for a plugin that signs users in with OpenID Connect or similar, without patching the login pages:
`EVENT_AUTH_LOGIN_FORM_BEFORE` / `_AFTER`, `EVENT_AUTH_USER_FLAGS` triggered before anyone is logged in,
`AuthFlags::setProfileManagedExternally()` (the account and user management pages then respect fields that are
owned by the provider, and `UserUpdateCommand` ignores changes to them), `EVENT_AUTH_LOGIN` and
`EVENT_AUTH_LOGOUT`, `EVENT_AUTH_AUTO_CREATE_USER` (a plugin that checks credentials against its own directory
creates the account of an unknown user, or refuses the login), `user_set_access_level()` (changes the access level
of an account, protected ones too when asked), `user_create()` with an option to skip the signup email, `db_begin()` / `db_commit()` /
`db_rollback()`, and `form_security_check()`, a token check that returns instead of halting.

## Bug fixes

- `Content-Disposition` for inline downloads now has the `inline` disposition type.
- Adding or removing a monitor no longer changes the issue's `last_updated`, which is the edit conflict check.
- PHP 8.4 turning `E_USER_ERROR` into a deprecation no longer wipes the error parameters; application errors are
  written to the server log; `curl_close()` is no longer called (deprecated in PHP 8.5).
- The bug list cookie is capped, `user_delete()` removes the rest of a user's rows.
- Database error details are hidden from users unless detailed errors are on.
- Buttons added with `EVENT_MENU_ISSUE` can open in a new tab; QuickTime previews are declared `video/mp4`;
  pinch zoom is no longer disabled in the viewport meta.
- REST API tokens are also accepted as `Authorization: Bearer <token>`.
- `$g_db_charset` lets MySQL connections use `utf8mb4`.
- `$g_filter_per_page_max` caps the page size of the issue list (only the page; print, export and the APIs are
  not capped) and the range shown in the list heading is right.
- `$g_admin_dir_check` switches off the admin directory warning separately from the other admin checks.
- The update issue page has a Cancel button next to Update Information, which goes back to the issue.

## Upgrading from 2.28.4

- The schema has new steps (214 to 219): the search queue table, the likes table and `like_count` on issues. Run
  `admin/install.php` as for any upgrade. Downgrading is not supported.
- Nothing changes in behaviour until an option above is set, apart from the bug fixes, the accessibility
  and `data-*` markup, and "similar issues" on the report page once quick search is on.

## Development

`chamsys/stack/` is a Docker stack (nginx, PHP 8.5, MariaDB, Meilisearch and the search indexer) to run and try the
fork: `chamsys/stack/stack.sh up`, then `install`. See the header of `stack.sh`. There is no PHPUnit suite in the
tree; the changes were tested against this stack, with curl, command line scripts and a real browser for the
JavaScript.

## Not here

Things the fork does not do, on purpose or not yet: no Bootstrap migration (the Ace theme stays), no
notification bell or in-app notifications (only the event they would need), no bundled plugins, and the pager is
in its original DOM order unless `$g_pager_natural_order` is on (it changes markup that existing plugins select on).
