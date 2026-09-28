# Content Visibility

A public/private switch on every post, page and public custom post type. Private content is readable by logged-in users only, whatever their role.

Part of [Baukasten - Privacy Toolkit](../../README.md), in `features/content-visibility/`. Until the merge it was the separate plugin `baukasten-content-visibility`; namespace, options and hooks are unchanged.

## Why not core's "Private" status

Core's private status is gated behind `read_private_posts`, so only editors and administrators see it. A members area needs the opposite rule: everyone who is logged in may read it, nobody else may. That is a different question, so this is a separate flag rather than a reinterpretation of core's.

## What it adds

- A toggle column on every list table, saved over Ajax.
- Bulk actions for setting many entries at once.
- A box in the editor sidebar.
- A **Content Visibility** tab under *Settings → Baukasten*.

## How private is enforced

Hiding the link is not access control, so the flag is applied in every layer that can leak content:

| Layer | Hook |
|---|---|
| Single views | `template_redirect` at priority 0 — login redirect or 403 |
| Embed view (`/…/embed/`) | the same hook — a 404, with the query emptied so the embed template has nothing to show |
| oEmbed endpoint | `oembed_request_post_id` returns 0, so the endpoint answers `oembed_invalid_url` |
| Archives, blog index, search, secondary queries | `pre_get_posts`, `posts_where` |
| Content and excerpts | `the_content`, `the_content_feed`, `the_excerpt_rss` at `PHP_INT_MAX` |
| Sitemaps | `wp_sitemaps_posts_query_args` |
| REST API | `rest_pre_dispatch` for single items, `rest_{$post_type}_query` for collections |

## Page caches

The admin column and the bulk actions write post meta through `Visibility::set()`; `save_post` never runs, so nothing else would invalidate a cached copy. `set()` calls `clean_post_cache()`, and when an entry turns private it empties the whole page cache of WP Super Cache (`wp_cache_clear_cache()`), W3 Total Cache (`w3tc_flush_all()`), WP Rocket (`rocket_clean_domain()`) and LiteSpeed Cache (`litespeed_purge_all`). The whole cache, not the one URL: archives, feeds and the front page may list the entry too. Any other cache can hook `baukasten/content_visibility/changed`.

## Known gaps

- **Media files.** Uploads attached to a private entry stay reachable under their direct URL, because the web server delivers them without running PHP. Content Visibility hides the entry, not the files in `wp-content/uploads/`.

## Existing content

An entry with no stored flag counts as private, which would take a live site's archive offline on activation. So activation marks every entry that already exists as public; only content created afterwards defaults to private. A site coming from the separate plugin already ran that migration, so the merge does not run it again (see `install()` in `bootstrap.php`). The same migration can be re-run from the settings tab after adding a content type that already has entries.

## For developers

```php
use function Baukasten\ContentVisibility\is_private;
use function Baukasten\ContentVisibility\get_visibility;
use function Baukasten\ContentVisibility\viewer_may_see_private;

if ( is_private( $post ) ) {
	// …
}
```

| Filter | Purpose |
|---|---|
| `baukasten/content_visibility/post_types` | The post types the switch applies to |
| `baukasten/content_visibility/viewer_may_see_private` | Who counts as allowed; defaults to "logged in" |
| `baukasten/content_visibility/login_redirect` | Where a blocked visitor goes; return `''` for a 403 |
| `baukasten/content_visibility/changed` | Action, fired after a flag changed: `( $post_id, $value, $before )`. The place to purge a cache this plugin does not know |

The settings are applied *through* the first and third of those at the default priority, so a site filter still has the last word.
