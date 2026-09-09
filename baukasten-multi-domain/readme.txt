=== Baukasten Addon: Multi-Domain Landingpage ===
Contributors: trstnbde
Tags: domain, landing page, multisite, front page, routing
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Requires Plugins: baukasten
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Give a page its own domain. One WordPress install serves a different front page under each domain you point at it.

== Description ==

Point a second domain at your WordPress install, assign it to a page, and visitors arriving on that domain get that page as the front page — with every link, stylesheet and image on the domain they typed.

No second installation, no multisite, no theme changes.

= Where you assign it =

In the page list, where the pages already are:

* a **Front page for domain** column,
* a **Front page (domain.tld)** marker next to the title, beside "Front Page" and "Draft",
* the **quick edit**, prefilled from the row,
* and the page editor, classic or block, as a box in the sidebar.

Whatever you type is reduced to a host name: `https://www.Kunde.DE/pfad/` and `kunde.de:8080` both become `kunde.de`. The `www.` variant is covered for you.

= One domain, one page =

A domain belongs to exactly one page. Assign it to a second page and it is taken off the first, in the list and in the page's own settings — so the page list never shows two pages claiming the same address.

= Fast on the front end =

The front end never queries for the domain. Every assignment is mirrored into a single autoloaded option, so answering "which page belongs to this host" costs nothing: no `WP_Query`, no `meta_query`, no extra database round trip on any request. Measured on a stock install, a mapped domain serves its front page in fewer queries than the default one.

= What you still need =

DNS for the domain has to point at this server, and the web server has to accept it — a virtual host, or a catch-all. This plugin decides what WordPress shows once the request arrives; it cannot make a domain reach you.

== Installation ==

1. Install and activate **Baukasten - Privacy Toolkit**. WordPress will not let this addon activate without it.
2. Install and activate **Baukasten Addon: Multi-Domain Landingpage**.
3. Point the domain at this server.
4. Open **Pages**, and set the domain on the page that should answer for it.

== Frequently Asked Questions ==

= Do I need multisite? =

No. This is one ordinary install serving different front pages depending on the host the request came in on.

= What happens to the rest of the site on that domain? =

It stays reachable. Only the front page changes; every other URL works as it always did, on whichever domain the visitor is using.

= Can two pages share a domain? =

No, and the plugin enforces it rather than letting the two disagree. The most recent assignment wins and the previous page loses the domain.

= The map and my pages disagree. =

That can happen after a database import or if another plugin wrote the meta directly. **Settings → Baukasten → Multi-Domain** has a button that reads every page again and rebuilds the map from scratch.

= What if the assigned page is not published? =

Visitors will not see it. The overview marks those rows so you can spot it before somebody else does.

= Does it work with a subdirectory install or HTTPS? =

Yes. Only the host is swapped; the scheme and any path are kept.

== Screenshots ==

1. The page list with the domain column and the front page marker.
2. The Multi-Domain tab under Settings → Baukasten.

== Changelog ==

= 1.0.0 =
* First release.
* Domain column, post state, quick edit and editor box on pages.
* One domain belongs to exactly one page, enforced on every write.
* Front end routing from a single autoloaded option, with no query of its own.
* `home`, `siteurl` and every asset URL follow the domain the visitor is on.

== Upgrade Notice ==

= 1.0.0 =
First release.
