# Baukasten Addon: Consent Blocking Engine

Blocks third-party scripts, styles, embeds, resource hints, Gravatar and emoji until the visitor consents, and keeps an auditable log of what they decided.

An addon for [Baukasten - Privacy Toolkit](../baukasten).

| | |
|---|---|
| Requires WordPress | 6.5 |
| Requires PHP | 8.1 |
| Requires plugin | `baukasten` |
| License | GPLv2 or later |

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
| Speculative loading (prefetch and prerender rules) is disabled | `wp_speculation_rules_configuration` |
| The dashboard stops calling `api.wordpress.org` — news widget and browser check | `wp_dashboard_setup`, `pre_site_transient_browser_*` |
| A dashboard warning when `siteurl` or `home` is not HTTPS | `admin_notices` |

Update checks for core, plugins and themes are deliberately **not** touched. Switching those off would stop security updates reaching the site, and no reading of data protection law makes that a good trade.

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

It closes comments and pings on every entry that is not in the trash with a single `UPDATE`, writes the privacy related core options (registration off, search engines discouraged, every discussion option off, avatars off), empties the credentials on **Settings → Connectors**, and switches on every measure above.

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

`POST` has no capability check on purpose — the people whose consent matters are not logged in. It is guarded by the `wp_rest` nonce, a whitelist of registered categories (never free text), and a rate limit keyed by a hash of the client address, which is neither stored nor logged.

## The log

`{$wpdb->prefix}baukasten_consent_log`, insert only. A decision is never updated: every change appends a row, so the history is reconstructable, which is what Article 7(1) GDPR asks for.

| Column | Contents |
|---|---|
| `anonymous_id` | random id from the visitor's own cookie |
| `categories` | JSON array |
| `policy_version` | the wording those categories were agreed against |
| `created_at` | UTC timestamp |

No IP address, no user id, no user agent. Export as CSV or JSON from the settings tab.

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
| `baukasten/consent/embed_notice` | filter | the text shown in place of an embed |
| `baukasten/consent/avatar_fallback` | filter | the placeholder avatar |
| `baukasten/consent/recorded` | action | after a decision was written |

## Development

```bash
composer install
composer lint
```

## License

GPLv2 or later. See [LICENSE](LICENSE).
