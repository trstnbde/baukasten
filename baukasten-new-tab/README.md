# Baukasten Addon: New Tab Page

Turns one page into a browser start page: search, a favourites bar, a greeting and a background, detached from the theme and without third-party requests.

An addon for [Baukasten - Privacy Toolkit](../baukasten). It replaces the standalone PHP script `bhub start`.

| | |
|---|---|
| Requires WordPress | 6.5 |
| Requires PHP | 8.1 |
| Requires plugin | `baukasten` |
| Tab | `new-tab`, position 80 |
| License | GPLv2 or later |

## How the page is chosen

Like the homepage: **Settings → Reading → New tab page**, a `wp_dropdown_pages()` field registered in the `reading` group (`baukasten_new_tab_page`). Core prints it at the end of that table and it stays there; moving it next to "Homepage" would mean JavaScript reaching into core's markup.

- Only a published page. The posts page is refused (its posts would disappear); the homepage is allowed, then the new tab page is the homepage.
- The page carries the post state "New tab page" in the page list and a notice in both editors.
- Trashing, deleting or unpublishing it resets the option, as core does for `page_on_front`.
- The tab can create a page ("New Tab", slug `start`) if that slug is free.

## Output

`Renderer` swaps the template on `template_include` (priority 99) for `is_page( $id )` and sends its headers on `template_redirect` priority 1. Content Visibility guards private content at priority 0, so a private page sends logged-out visitors to the login screen before this addon does anything.

The template has no `wp_head()` and no `wp_footer()`. Consequently no theme, block CSS, emoji, admin bar or Statify script. Statify's server-side counter is told to skip the page (`statify__skip_tracking`) — a new tab is not a visit. Asset URLs go through `plugins_url()` so Multi-Domain can rewrite them.

Headers: a Content Security Policy without `unsafe-inline` (the two inline styles are allowed by hash; `images.unsplash.com` only in the Unsplash mode), `Referrer-Policy: no-referrer`, `X-Robots-Tag: noindex, nofollow`, `Cache-Control: no-cache, private`. The script reads its configuration from `<script type="application/json" id="bk-config">`.

## Search

`Search` decides whether an entry is an address or a search, in PHP and, with the same rules, in `assets/src/new-tab.js`. Without JavaScript the form is a `GET` on the page with `q` and `e`:

- a search is forwarded (303) to the engine's host, which comes from the settings;
- an address is **not** forwarded — `templates/address.php` shows a link instead. A page that forwards `?q=<host>` would be an open redirect on this site's domain.

Suggestions: `GET /wp-json/baukasten-new-tab/v1/suggest?q=`, asking Google (`client=firefox`), DuckDuckGo or Brave from the server. Ten minutes in a transient, `Cache-Control: private, max-age=300`, 60 requests a minute per `wp_hash( REMOTE_ADDR )` (the address itself is never stored), `q` up to 120 characters. The browser aborts stale requests and waits 110 ms.

## Backgrounds

| Mode | Source | Consent |
|---|---|---|
| `media` (default) | images chosen in the media dialog, served as `<img srcset sizes>` | no |
| `unsplash` | API pool of 30 in the option `baukasten_new_tab_unsplash_pool`, three hours; hotlinked photos at screen size | yes |
| `off` | gradient | – |

The dominant colour is computed once from the smallest size of each image (GD, then Imagick) and stored in `_baukasten_new_tab_color`.

Unsplash needs consent in the category the Consent Blocking Engine uses for external resources (`external_default`, usually `functional`). The page reads the decision on the server (it is never cached) and renders the right mode. The button posts the **previous categories plus the new one** to `/baukasten-consent/v1/consent`, because that route replaces the stored selection. Without the engine, a cookie of the addon's own is used.

The access key lives in `baukasten_new_tab_unsplash_key` (not autoloaded) and is read only by the request that calls the API. It is in no HTML and no REST answer; a test checks both.

## Favourites

Titles, addresses and optional icon addresses, `http`/`https` only. On saving and weekly (WP-Cron `baukasten/new_tab/refresh_icons`) the server fetches each icon — `<link rel="icon">`, then `/favicon.ico` — with a 3 s timeout, at most 100 KB, accepted by magic bytes (PNG, ICO, GIF, JPEG, WebP; **no SVG**, which would be executable when opened directly from `uploads/`), and stores it under `uploads/baukasten-new-tab/icons/`. Without one the bar draws a monogram.

## Greetings and holidays

`data/greetings-<locale>.php` with the keys `random` and `holidays`, chosen by `determine_locale()` with `en_US` as the fallback. Jokes are localised, not translated, so they are not in the `.po` file.

A holiday owns the ISO week its anchor falls into (`o`/`W`, site time zone). Two anchors in one week: the one closer to today wins, then table order — 2 October 2027 (Rosh Hashanah) and 3 October (German Unity Day) are the case that exists.

| Holiday | Date | Source |
|---|---|---|
| Carnival in Cologne | Easter − 52 days | calculated |
| Easter | Easter Sunday | calculated (Meeus), not `easter_date()` |
| Eid al-Fitr, Eid al-Adha | 1 Shawwal, 10 Dhu al-Hijjah | `data/holidays-islamic.php` |
| Europe Day | 9 May | fixed |
| Rosh Hashanah, Yom Kippur | 1 and 10 Tishri | `jewishtojd()`, else `data/holidays-jewish.php` |
| German Unity Day | 3 October | fixed |
| Christmas, New Year's Eve | 24 and 31 December | fixed |

`bin/fetch-holidays.php` writes both tables: AlAdhan with `calendarMethod=DIYANET` — the calendar of the Diyanet, which the DITIB congregations in Germany follow — and, after the date up to which AlAdhan vouches for it (January 2028), Umm al-Qura, each such line marked; Hebcal, whose content is CC BY 4.0, for the Jewish dates. Nothing is fetched while the site runs.

`?bk_date=YYYY-MM-DD` shows another day, for users with `manage_options` only. Filter: `baukasten/new_tab/now`.

## Fonts

Fira Sans 400, 500 and 600, `latin` and `latin-ext` as WOFF2 in `assets/fonts/`, SIL Open Font License 1.1 (`OFL-Fira-Sans.txt`). Only 600-latin is preloaded.

## Sources of the script and stylesheet

`assets/src/new-tab.js` and `assets/src/new-tab.css` are minified into `assets/js/` and `assets/css/` (esbuild; commands in the developer guide). The sources are not shipped.

## Hooks

| Hook | Type | Purpose |
|---|---|---|
| `baukasten/new_tab/template` | filter | the template file |
| `baukasten/new_tab/greetings` | filter | the greeting pool and holiday greetings |
| `baukasten/new_tab/holiday` | filter | the holiday of the week |
| `baukasten/new_tab/now` | filter | the date the page is rendered for |
| `baukasten/new_tab/legal_links` | filter | the footer links |
| `baukasten/new_tab/compute_jewish_dates` | filter | `false` forces the table |

## Not included

An Unsplash proxy and copying API photos into the library (against the API guidelines), holiday lookups at run time, favourite folders, synchronisation with browser bookmarks.
