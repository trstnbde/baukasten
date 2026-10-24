=== Baukasten Addon: New Tab Page ===
Contributors: trstnbde
Tags: start page, new tab, search, favorites, privacy
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: baukasten
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turns one page into a browser start page: search with a choice of engines, a favourites bar, a greeting and a background, detached from the theme and without third-party requests.

== Description ==

A browser start page is opened dozens of times a day and has one job. This addon makes one page of your site that page: you pick it on the addon's tab under **Settings → Baukasten**, and it shows search, favourites and a greeting instead of its content.

The page is served from its own template. No theme, no block CSS, no admin bar, no tracking script, a Content Security Policy without `unsafe-inline`, and a font that lives on your server.

* **Search** with a menu of engines — Startpage (default), Brave, Google AI mode, Perplexity, Yandex — each editable and switchable. `@sp`, `@b`, `@g`, `@p` and `@y` followed by a space search one engine once. Addresses such as `heise.de` or `localhost:8080` open directly; `Alt` or `Ctrl` with `Enter` opens a new tab; `/` focuses the field.
* **Suggestions** from Startpage, asked for by your server, so Startpage sees what was typed but not who typed it.
* **Favourites bar** in the style of the Firefox bookmarks toolbar. What does not fit moves into a » menu. Icons are fetched by the server and served from your site.
* **Background** from your media library (default), from Unsplash, or a plain gradient.
* **A greeting** at random from 52 nerdy business greetings or your own list, or on one of ten holidays — Carnival in Cologne, Easter, Eid al-Fitr, Europe Day, Eid al-Adha, Rosh Hashanah, Yom Kippur, German Unity Day, Christmas, New Year's Eve — the holiday's own in the week it falls in. Both can be switched off.
* **Footer links** to the privacy policy and, with Login & Legal, terms and imprint, as the site has them.

= Backgrounds =

**Media library.** Photos you download from unsplash.com and upload yourself. The Unsplash License allows that, also commercially, and asks for no credit; credit is welcome, and the image caption is where it goes. Nothing is loaded from another host.

**Unsplash.** One API call fills a pool of 30 photos for three hours. The photos are loaded from images.unsplash.com directly, as the Unsplash API guidelines require, so each view is credited to the photographer. **That sends every visitor's IP address and browser details to Unsplash without asking first, so name it in your privacy policy.** The settings tab carries a notice to that effect. The access key stays on the server and is never printed.

No proxy and no copying of API photos into the library: Unsplash does not allow either.

= Holidays =

The dates are not fetched while your site runs. Easter and Carnival are calculated, five dates are fixed, the Jewish ones are calculated with PHP's calendar extension or read from a table, and the Islamic feasts are read from a table made when the plugin was written. Sources: Hebcal (CC BY 4.0) for the Jewish calendar, and the AlAdhan API for the Islamic feasts, following the calendar of the Diyanet, which the DITIB congregations in Germany follow. Where AlAdhan no longer vouches for that calendar (from February 2028), Umm al-Qura is used.

= Privacy =

With photos from the media library or none, the page makes no request to a third party. Suggestions and icons are fetched by your server; the visitor's address and cookies never reach those providers. With photos from Unsplash, the visitor's browser loads them from Unsplash. The browser stores the last engine and the last three greetings in `localStorage`, nothing else.

== Installation ==

1. Install and activate Baukasten - Privacy Toolkit, then this plugin.
2. Open **Settings → Baukasten → New Tab Page** and press "Create page and select it", or choose any page there.
3. Add favourites and choose a background there.
4. Set the page's address as the new tab page of your browser.

== Frequently Asked Questions ==

= Who can open the page? =

Whoever the page's visibility allows. Switch it private with Content Visibility and logged-out visitors are sent to the login screen. Favourites can give away addresses that are meant to stay private, so think before leaving it public.

= Can the page also be the homepage? =

Yes. Choose the same page under Homepage and New tab page.

= Does it work with Multi-Domain? =

Yes. Give the page a domain and it is that domain's start page.

= What happens to my data when I delete the plugin? =

Settings, stored icons, the photo pool and caches are deleted. The page and the images in your media library stay.

== Screenshots ==

1. The page with favourites, a greeting and the engine menu.
2. The tab under Settings → Baukasten.

== Changelog ==

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.0 =
First release.
