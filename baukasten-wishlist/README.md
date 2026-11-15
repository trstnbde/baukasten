# Baukasten Addon: Wishlist

Wishlists for birthdays and other occasions: wishes with image, quantity, price, priority and shop link, shown in a page or post, where guests mark what they bought.

An addon for [Baukasten - Privacy Toolkit](../baukasten).

| | |
|---|---|
| Requires WordPress | 6.5 |
| Requires PHP | 8.1 |
| Requires plugin | `baukasten` |
| Tab | `wishlist`, position 90 |
| License | GPLv2 or later |

## Data

Two post types, neither public, both with classic meta boxes (`show_in_rest` off):

| Post type | What | Meta (`_baukasten_wishlist_*`) |
|---|---|---|
| `baukasten_wishlist` | one occasion; title, featured image = header | `person`, `occasion`, `date` (`Y-m-d`), `intro`, `accent`, `show_prices`, `close_after` |
| `baukasten_wish_item` | one wish; title, featured image = product, `menu_order` = "As arranged" | `list` (ID), `quantity` (1–99), `price` (`12.99`), `priority` (1 high, 2, 3 low), `url`, `note` |

`Fields` is the one schema: types, defaults, `sanitize()`. Prices typed as `12,99`, `1.299,00` or `1,299.00` are normalised; the last separator followed by one or two digits is the decimal one.

Both post types are taken out of Content Visibility: they have no address, the page that shows them carries the visibility.

## Reported purchases

`Claims`. One purchase is one meta row `_baukasten_wishlist_claim` on the wish, its value `hash_hmac( sha256, token, wp_salt( 'nonce' ) )`. One row per purchase rather than a counter: `add_post_meta()` is a single INSERT, so two reports at once cannot overwrite each other. After the insert the rows are counted again straight from the table; a report that pushed the count past the quantity removes its own row and answers 409.

No name, no account, no address is stored. The token (32 hex characters) goes back to the browser, which keeps it in `localStorage['baukastenWishlist']` under the wish ID. Only that token takes the report back.

`Claims::is_open()` closes a list after its date when `close_after` is set; filter `baukasten/wishlist/can_claim`.

## Routes

| | |
|---|---|
| `GET /wp-json/baukasten-wishlist/v1/lists/<id>` | `{ open, items: { <id>: { quantity, bought, remaining } } }` — counts only, no titles |
| `POST /wp-json/baukasten-wishlist/v1/items/<id>/claim` | 201 `{ token, id, quantity, bought, remaining }`; 409 fully bought; 403 closed; 404 unknown, draft, or list not published |
| `DELETE /wp-json/baukasten-wishlist/v1/items/<id>/claim?token=` | 200 with the new state; 404 unknown token |

Public, without a nonce — a cached page would carry an expired one, and guests are not logged in. The browser sends no cookies (`credentials: 'omit'`). POST and DELETE count against a fixed window per hashed address (`baukasten_wishlist_rate_limit`, default 20 per 10 minutes, 0 off) and answer 429 beyond it. The address hash comes from `Baukasten\LoginLegalPages\Rate_Limit::key()` when the core has it, so `baukasten/login/client_ip` applies here too. Every answer carries `Cache-Control: no-store`.

## Output

`Renderer::render()` is shared by the block `baukasten/wishlist` (dynamic, `render_callback`, editor script `assets/js/block-editor.js` against the `wp.*` globals without a build step, preview through `ServerSideRender`) and the shortcode `[baukasten_wishlist id="" sort="" filter="" controls=""]`.

- The server prints every wish in the chosen order with its state, so the list reads without JavaScript. Buttons and controls carry `hidden` until the script shows them.
- Inside a post that still asks for its password the shortcode prints nothing (trap 28); WordPress already withholds the content of such a post, this covers widgets and template parts.
- An unpublished list prints nothing for visitors and a note for editors.
- `assets/js/wishlist.js` sorts and filters on `data-*` attributes, keeps the choice in the address (`?wl<id>_sort=` …), refreshes the counts from the state route on load, and announces results in a live region.
- `assets/css/wishlist.css` inherits the theme's font and colours, mixes tints from `currentColor`, and takes the accent from `--baukasten-wl-accent`. No fonts, no third-party requests.
- Assets are registered on `init` and enqueued from inside the render, so they load only where a list is shown — before `wp_head` in a block theme, in the footer in a classic one.

## Hooks

| Hook | |
|---|---|
| `baukasten/wishlist/can_claim` | filter, `( bool $open, int $list_id )` |
| `baukasten/wishlist/item_data` | filter, `( array $data, WP_Post $item )` before a wish is printed |
| `baukasten/wishlist/sort_options` | filter, labels of the sort orders (keys can only be removed) |

## Admin

- **Wishlists** menu with the lists (columns: occasion, wishes and bought count, shortcode) and **Wishes** (columns: image, list, priority, price, bought; filter by list).
- A list's screen shows its wishes with an "Add wish" link that preselects the list, and the shortcode to copy.
- A wish's screen shows how many were reported bought and a "Reset to not bought" button (`admin-post.php?action=baukasten_wishlist_reset_item`, nonce per wish).
- **Settings → Baukasten → Wishlist**: overview of every list, "Reset purchases" per list, currency symbol (`baukasten_wishlist_currency`), rate limit.

## Uninstall

`uninstall.php` deletes both options and every list and wish (with their meta, so every report). Images stay in the media library.

## Tests

`tests/integration/WishlistTest.php`.
