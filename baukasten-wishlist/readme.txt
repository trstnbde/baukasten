=== Baukasten Addon: Wishlist ===
Contributors: trstnbde
Tags: wishlist, gift list, birthday, registry, gifts
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: baukasten
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Wishlists for birthdays and other occasions: wishes with image, quantity, price, priority and shop link, shown in a page or post, where guests mark what they bought.

== Description ==

A wishlist is one occasion for one person — a second birthday, Christmas, a wedding. You add the wishes in the admin, put the list on any page or post with the **Wishlist** block or a shortcode, and send the link around. Guests see what is still wanted, sort and filter the list, and press **I bought this** once they have. Everybody who opens the list afterwards sees the wish as taken care of, so nothing is bought twice.

* **Lists** with a title, the person, the occasion, a date with a countdown, an introduction, a header image and an accent colour. As many as you like, each on its own page or several on one.
* **Wishes** with a name, a product image, a quantity, an approximate price, a priority (high, medium, low), a shop address and a short note.
* **Bought wishes leave the list.** A list starts with the wishes still wanted; guests can switch to all wishes or to the ones already bought.
* **Sorting** by priority, by what is still wanted, by price either way, by name, or in your own order; **filters** by status, priority and price. The choice is kept in the address, so a filtered list can be shared.
* **Anonymous reports.** One click, no name, no account. A wish wanted three times counts down one at a time.
* **Undo from the same browser.** Right after a report the message offers Undo; later, the list points a returning guest to the wishes they reported. Only the reporting browser holds the token that takes a report back. You can reset any wish, or a whole list, in the admin.
* **Close after the day**, if you want: the list stays visible, but takes no more reports.
* Works with any theme: the list inherits fonts and colours and adds only its accent.

= Privacy =

Reporting a purchase asks for no name and stores none — not even you can see who bought what. The site stores only a hash of the browser's token; the visitor's address is used, hashed, for a rate limit and is never stored. The browser keeps the tokens of its own reports in `localStorage`, nothing else. The page loads nothing from other hosts; a shop link opens the shop only when it is clicked.

== Installation ==

1. Install and activate Baukasten - Privacy Toolkit, then this plugin.
2. Open **Wishlists → Add list**, fill in the occasion, and save.
3. Add wishes from the list's screen ("Add wish") or under **Wishlists → Wishes**. The product image is the featured image.
4. Add the **Wishlist** block to a page, choose the list, and publish the page.

== Frequently Asked Questions ==

= Can the list be private? =

The list has no address of its own; the page it is on decides. Give that page a password, or make it private with Content Visibility, and the list is just as private.

= What stops somebody from ticking off everything? =

Nothing, ultimately — the list is meant for people you sent the link to. A rate limit (20 reports per address in ten minutes by default, under **Settings → Baukasten → Wishlist**) stops a script, and every report can be reset in the admin.

= What is the shortcode? =

`[baukasten_wishlist id="123"]`, with the list's ID. Options: `sort="priority|open|price-asc|price-desc|title|order"`, `filter="open|all|done"` (default `open`), `controls="no"`.

= Can guests see the bought wishes? =

Yes, with "Show: All wishes" or "Already bought". The list starts with the wishes still wanted; to start with all of them, choose "All wishes" under "Shows at first" in the block's settings, or write `filter="all"` in the shortcode.

== Changelog ==

= 1.0.1 =
* A list starts with the wishes still wanted, so a reported wish leaves it. Blocks and shortcodes that chose "All wishes" explicitly keep it.
* After a report that hides the wish, the message offers Undo for ten seconds, and pauses while it has the pointer or the keyboard focus.
* A returning guest whose reported wishes are filtered out gets a note above the list that shows them.
* Showing all wishes, the bought ones come after the ones still wanted in every order.
* The token that takes a report back travels in the `X-Baukasten-Wishlist-Token` header instead of the address. `?token=` still works for pages cached with 1.0.0.
* The focus never lands inside a hidden wish.

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.1 =
Bought wishes leave the list for guests, with Undo in the message right after a report.

= 1.0.0 =
First release.
