# Consent Blocking Engine

Blocks third-party scripts, styles, embeds, resource hints, Gravatar and emoji until the visitor consents, and keeps an auditable log of what they decided.

Part of [Baukasten - Privacy Toolkit](../../README.md), in `features/consent-blocking-engine/`. Until the merge it was the separate plugin `baukasten-consent-blocking-engine`; namespace, options and hooks are unchanged.

## The idea

A consent banner that only *asks* a tracker not to run is a promise, not a control. This plugin makes the request impossible instead, and leaves the banner to somebody else.

## How it blocks

| Target | Hook | What happens |
|---|---|---|
| Scripts | `script_loader_tag` | `type="text/plain"` plus `data-baukasten-consent` |
| Inline scripts of a blocked handle | `wp_inline_script_attributes` | same, matched through the `{handle}-js-before\|after` id |
| Stylesheets | `style_loader_tag` | `href` and `rel` move into data attributes |
| ES modules | `wp_script_attributes` | same as scripts, restored as `type="module"` |
| Resource hints | `wp_resource_hints` | third-party `dns-prefetch` / `preconnect` removed |
| oEmbeds | `embed_oembed_html` | markup parked in a `<template>` behind a notice and two buttons |
| Gravatar | `pre_get_avatar_data`, `get_avatar` | local SVG, real URL in a data attribute |
| Emoji | `remove_action` | the `s.w.org` polyfill is not printed at all |

### Which category an asset falls into

1. An explicit assignment on the settings tab wins.
2. Otherwise: served from another host → the configured external category; served from this site → necessary.

Filterable with `baukasten/consent/asset_category`, and `baukasten/consent/first_party_hosts` covers a CDN serving your own files.

## Measures that are not consent gated

Some things leak in a way no visitor can meaningfully consent to, so `Privacy_Audit` switches them off instead of parking them behind a category:

| Measure | Hook |
|---|---|
| The commenter's IP address never reaches `wp_comments` | `pre_comment_user_ip` |
| Speculative loading (prefetch and prerender rules) is disabled — off by default: the rules only prefetch same-origin URLs | `wp_speculation_rules_configuration` |
| The dashboard stops calling `api.wordpress.org` — news widget and browser check | `wp_dashboard_setup`, `pre_site_transient_browser_*` |
| A dashboard warning when `siteurl` or `home` is not HTTPS | `admin_notices` |

Update checks for core, plugins and themes are deliberately **not** touched. Switching those off would stop security updates reaching the site, and no reading of data protection law makes that a good trade.

## Site hardening

The same class switches off what gives away who runs the site, and what gives an attacker a second door. Each is its own setting, on by default unless noted:

| Setting | Measure | Hook |
|---|---|---|
| `hide_rest_users` | `/wp/v2/users` and `/wp/v2/users/<id>` answer `rest_no_route` (404) when not logged in | `rest_pre_dispatch`, after authentication |
| `disable_author_archives` | author archives and `?author=N` answer 404, before `redirect_canonical()` can reveal the slug | `template_redirect` priority 1 |
| `strip_oembed_author` | no `author_name` or `author_url` in oEmbed responses | `oembed_response_data` |
| `disable_users_sitemap` | no users sitemap | `wp_sitemaps_add_provider` |
| `disable_xmlrpc` | XML-RPC off, its methods emptied, no `X-Pingback` header, no RSD link | `xmlrpc_enabled`, `xmlrpc_methods`, `wp_headers`, `rsd_link` |
| `disable_application_passwords` | application passwords off — **off by default**, integrations need them | `wp_is_application_passwords_available` |
| `auto_update_all` | every plugin and theme, including ones installed later, updates automatically | `auto_update_plugin`, `auto_update_theme` at `PHP_INT_MAX` |

`auto_update_all` registers before the `is_admin()` split, because automatic updates run from WP-Cron. The Plugins and Themes screens show "Auto-updates enabled" without a toggle while it is on.

The HTTPS warning is skipped when `wp_get_environment_type()` returns `local`. WordPress only knows that if `WP_ENVIRONMENT_TYPE` is set, so a development machine on `http://localhost` will see the warning until that constant exists.

## Two clicks for an embed

A blocked embed is not a dead end. Its placeholder carries two buttons:

- **Load content** inserts the `<template>` for that one embed and stores nothing at all.
- **Load and always allow "…"** writes the category through the REST endpoint, which is the same decision a banner would record, and unblocks everything in that category on the page.

## Privacy hardening

`Hardening::run_privacy_hardening_bulk_action()` puts the whole site into a defensible default state in one pass — from a button on the settings tab or from the command line:

```bash
wp baukasten harden
```

It closes comments and pings on every entry that is not in the trash with a single `UPDATE`, writes the privacy related core options (registration off, search engines discouraged, every discussion option off, avatars off), empties the credentials on **Settings → Connectors**, adds every installed plugin and theme to the `auto_update_plugins` / `auto_update_themes` site options (`Hardening::enable_auto_updates()`, public so it can run on its own), and switches on every measure above except speculative loading and application passwords.

**The hardening routine never runs on its own — not on activation, not on update — only from the button on the settings tab or the WP-CLI command.** `blog_public` is part of it, so a site that wants to be found must not run it again after unticking "Discourage search engines".

Every step compares before it writes and reports whether it changed anything, so it is safe to run repeatedly and the second run tells you there was nothing left to do. Credentials supplied through a PHP constant or an environment variable are out of reach of a database routine and are named in the report rather than silently missed.

It is blunt on purpose, and it changes the site rather than the plugin: deactivating the plugin does not undo any of it. That is why the button asks first.

## Why it survives a full page cache

The server renders the blocked state **always**, identically for every visitor, and never reads the consent cookie while rendering. That makes the HTML cacheable as a whole with no risk of serving one visitor's consent to another.

`assets/js/consent-bootstrap.js` then runs in the browser, reads the cookie, and restores what that visitor allowed — replacing blocked `<script>` elements with live copies, putting `href` back on stylesheets, and moving embed templates into the document. It re-runs on a `consentUpdated` event, so a banner can change the decision without a reload.

## The REST API

```
GET  /wp-json/baukasten-consent/v1/consent-state
POST /wp-json/baukasten-consent/v1/consent      { "categories": ["functional"] }
```

`POST` has no capability check on purpose — the people whose consent matters are not logged in. A `wp_rest` nonce would protect nothing here: core only checks one when it is sent, it is the same for every logged-out visitor, and behind a page cache it goes stale. What guards the route instead (`REST_Controller::is_same_origin()`):

- **Origin.** The `Origin` header must be one of the site's own hosts — `home_url()`, `site_url()`, and whatever `baukasten/consent/allowed_hosts` adds (the Multi-Domain addon adds its domains). Without `Origin`, `Sec-Fetch-Site: same-origin` is accepted. Anything else is a 403, and so is a request with neither header.
- **JSON only.** `Content-Type: application/json`. An HTML form on another site cannot send that, and a script cannot send it cross-origin without a preflight this route never answers.
- **A whitelist** of registered categories, never free text.
- **A rate limit** of 30 writes per five minutes, keyed by `wp_hash( REMOTE_ADDR )`. The hash, not the address, is part of a transient's name in `wp_options` for five minutes; the address itself is not stored.

## The log

`{$wpdb->prefix}baukasten_consent_log`, insert only. A decision is never updated: every change appends a row, so the history is reconstructable, which is what Article 7(1) GDPR asks for.

| Column | Contents |
|---|---|
| `anonymous_id` | random id from the visitor's own cookie |
| `categories` | JSON array |
| `policy_version` | the wording those categories were agreed against |
| `created_at` | UTC timestamp |

No IP address, no user id, no user agent. Export as CSV or JSON from the settings tab.

`anonymous_id` is still a pseudonymous identifier, so rows do not live forever: the daily cron event `baukasten/consent/purge_log` deletes rows older than `log_retention_months` (default 24, minimum 13 — the cookie lives twelve months, and a decision has to stay provable while it is in force), in batches of 1,000.

## When the front end assets load

`consent-bootstrap.js` and `blocked.css` are not loaded on every page. `Frontend::maybe_enqueue()` runs at the end of `wp_enqueue_scripts` and loads them when a queued script or stylesheet, dependencies included, comes from another host, or when the entry being shown has an embed block or a bare URL on a line of its own. Anything blocked later — an embed in rendered content, a script printed from a shortcode — calls `Frontend::need()` from the blocking filter and the assets print in the footer instead. `always_load_frontend` loads them everywhere, for a banner that expects the script.

## The asset list

`Inventory::collect()` records handles on `wp_footer` only for a user with `manage_options`, or for everyone while a scan runs. "Clear the asset list and scan" on the tab starts a 30 minute scan; its end time lives in the autoloaded option `baukasten_consent_scan_until`, so checking it costs no query.

## Known gaps

- **`<link rel="modulepreload">`.** `WP_Script_Modules::print_script_module_preloads()` prints these with `printf()` and no filter, so a blocked module's static dependencies may still be fetched. Every script module WordPress ships is first-party, so this costs a request rather than leaking to a third party — but it is a gap.
- **Assets printed without going through `wp_enqueue_script()`/`wp_enqueue_style()`** are invisible to every hook used here. A theme hard-coding a `<script src="https://…">` into `header.php` cannot be blocked by any plugin.
- **`wp_oembed_get()` called directly by a theme** bypasses `embed_oembed_html` and is not wrapped. Wrapping on `oembed_result` instead would cover it, but that filter runs once at fetch time and WordPress stores its return value in post meta — the placeholder would be baked into that cache with the category and wording of the day it was fetched, and wrapped a second time on output.

## Filters and actions

| Hook | Type | Purpose |
|---|---|---|
| `baukasten/consent/categories` | filter | the category definitions |
| `baukasten/consent/asset_category` | filter | the category one handle falls into |
| `baukasten/consent/first_party_hosts` | filter | hosts that count as your own |
| `baukasten/consent/allowed_hosts` | filter | hosts a consent decision may be sent from |
| `baukasten/consent/embed_notice` | filter | the text shown in place of an embed |
| `baukasten/consent/avatar_fallback` | filter | the placeholder avatar |
| `baukasten/consent/recorded` | action | after a decision was written |
