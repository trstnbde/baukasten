# Baukasten Addon: Multi-Domain Landingpage

Give a page its own domain. One WordPress install serves a different front page under each domain you point at it.

An addon for [Baukasten - Privacy Toolkit](../baukasten).

| | |
|---|---|
| Requires WordPress | 6.5 |
| Requires PHP | 8.0 |
| Requires plugin | `baukasten` |
| License | GPLv2 or later |

## Where the field lives

In the page list, not on a settings screen of its own. Assigning a domain is a one-field decision about a page, and a field on another screen is a field that gets forgotten.

- `manage_page_posts_columns` — a **Front page for domain** column
- `display_post_states` — **Front page (domain.tld)** next to the title
- `quick_edit_custom_box` — prefilled from the row by `assets/js/admin-quick-edit.js`, which wraps `inlineEditPost.edit` the way core's own scripts do
- `add_meta_boxes_page` for the classic editor, and `register_post_meta( …, show_in_rest )` for the block editor and anything else using the REST API

## How the data is kept

Post meta `_baukasten_domain` is the source of truth — it is what an editor edits. The front end must not read it: answering "which page belongs to this host" with a `meta_query` would put a database round trip in front of every request.

So the same information is mirrored into `baukasten_domain_map`, one autoloaded option shaped `[ 'domain.tld' => id, 'www.domain.tld' => id ]`. `Domain_Map` hooks `added_post_meta`, `updated_post_meta` and `deleted_post_meta`, so **every** write path keeps it in step — quick edit, meta box, REST — and nothing writes the map directly.

Uniqueness is enforced in the same place: taking a domain gives it up elsewhere, meta included, so the map and the pages can never disagree about who owns an address. Trashing a page drops its routes; restoring it puts them back. `Domain_Map::rebuild()` reads every page again when an import or another plugin has made the two drift apart.

## Routing

`Domain_Router` is five filters and a lookup in an array that is already in memory:

| Filter | What it does |
|---|---|
| `pre_option_show_on_front` | `'page'` |
| `pre_option_page_on_front` | the mapped page ID |
| `option_home`, `option_siteurl` | host swapped to the one the visitor is on |
| `content_url`, `plugins_url`, `includes_url` | the same, for asset URLs |
| `script_loader_src`, `style_loader_src` | a last pass, for plugins that cached their URL in a constant |
| `redirect_canonical` | cancelled on the front page only |

The last three rows are not decoration. `WP_CONTENT_URL` and friends are defined in `wp-settings.php` from `siteurl` **before any plugin loads**, so no filter can reach them; a plugin that stores `plugin_dir_url( __FILE__ )` in a constant at include time is past even those. Without these, a page on the customer's domain would pull its stylesheets, scripts and fonts from the installation's primary domain — a needless cross-origin request, and a privacy leak, because the primary domain would then see every visitor of every customer domain.

The host swap only ever touches this site's own hosts, so a URL pointing at a CDN or a third party comes back untouched, and running it twice changes nothing.

Routing is skipped in the admin, during Ajax, in the REST API, under WP-CLI and in cron — the editor has to keep talking to the host it was loaded from.

## What this plugin cannot do

Make a domain reach you. DNS has to point at the server and the web server has to accept the host; this decides what WordPress shows once the request has arrived.

## Filters

| Filter | Purpose |
|---|---|
| `baukasten/multi_domain/page_id` | the page a host is routed to; return `0` to route nothing |

## Development

```bash
composer install
composer lint
```

Domain routing is testable without touching your hosts file:

```bash
curl -s -H "Host: kunde.test" http://localhost/
```

## License

GPLv2 or later. See [LICENSE](LICENSE).
