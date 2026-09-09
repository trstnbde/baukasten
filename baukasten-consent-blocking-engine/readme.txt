=== Baukasten Addon: Consent Blocking Engine ===
Contributors: trstnbde
Tags: consent, gdpr, privacy, cookies, tracking
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: baukasten
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Blocks third-party scripts, styles, embeds, resource hints, Gravatar and emoji until the visitor consents, and keeps an auditable log.

== Description ==

Most consent plugins show a banner and then ask the tracker nicely not to run. This one does the opposite: it makes sure nothing reaches a third party in the first place, and leaves the banner to somebody else.

= What it blocks =

* **Scripts and stylesheets** from other hosts, or any handle you assign to a category. A blocked script gets `type="text/plain"`, so the browser neither fetches nor runs it. Inline configuration attached to a blocked handle with `wp_add_inline_script()` is blocked with it — otherwise the tracker's setup would still execute.
* **ES modules**, including Interactivity API block scripts, which never pass through the classic script filter and need their own path.
* **`dns-prefetch` and `preconnect` hints** to other hosts. A hint is a connection, and a connection is an IP address handed over.
* **Embedded content** — YouTube, Vimeo, anything oEmbed. The iframe is parked in a `<template>`, which the browser parses but never loads, behind a short notice and two buttons: load this one embed, or allow the whole category from now on.
* **Gravatar.** Every avatar is a request to a third party carrying the visitor's IP and a hash of a commenter's email address. A local placeholder is served instead.
* **The emoji polyfill**, which pulls a bundle from `s.w.org`.

= Why it works with a page cache =

Every response is rendered in the same blocked state, for every visitor, whether or not they have consented. That HTML is identical for everybody, so a full page cache can store it whole without ever serving one visitor's consent to another.

A small script then runs in the browser, reads the consent cookie, and puts back exactly what that visitor allowed. It also listens for a `consentUpdated` event, so a banner can change the decision without a page reload.

= Things nobody can consent to =

Some leaks are not a decision a visitor could sensibly make, so they are simply switched off: the commenter's IP address never reaches the database, speculative prefetching is disabled, and the dashboard stops calling api.wordpress.org for its news widget and browser check. A warning appears if the site is not served over HTTPS.

Update checks are deliberately left alone — switching those off would stop security updates, and no reading of data protection law makes that a good trade.

= One button for the whole site =

**Apply privacy hardening and defaults** puts the site into a defensible default state in one pass: comments and pings closed on every entry, registration off, search engines discouraged, every discussion option off, avatars off, the credentials on Settings → Connectors emptied, and every blocking measure switched on. `wp baukasten harden` does the same from the command line.

It reports what it changed and is safe to run again. It is also blunt, and it changes the site rather than the plugin — deactivating the plugin undoes none of it — which is why the button asks first.

= The consent log =

Every decision is appended to its own table; nothing is ever updated in place, so the history of a visitor's decisions can be reconstructed — which is what Article 7(1) GDPR asks a controller to be able to do.

It holds no personal data. No IP address, no user id, no user agent. Only a random id generated in the visitor's own cookie, the categories, the policy version they were agreed against, and the time. Export it as CSV or JSON at any time.

= This plugin has no banner =

It blocks, it records, and it exposes a REST endpoint. Showing the dialog is a separate job with separate design constraints, and it belongs in a theme or another addon:

`
POST /wp-json/baukasten-consent/v1/consent
X-WP-Nonce: <wp_rest nonce>

{ "categories": [ "functional", "statistics" ] }
`

`GET /wp-json/baukasten-consent/v1/consent-state` returns the categories and the current decision.

== Installation ==

1. Install and activate **Baukasten - Privacy Toolkit**. WordPress will not let this addon activate without it.
2. Install and activate **Baukasten Addon: Consent Blocking Engine**. It starts blocking immediately, with third-party assets in the "Functional" category.
3. Open a few front end pages so the plugin can record which scripts and styles your site actually loads.
4. Go to **Settings → Baukasten → Consent** and put each of them in the right category.
5. Add a banner, from your theme or another plugin, that writes to the REST endpoint above.

== Frequently Asked Questions ==

= Will this break my site? =

Assets served from your own domain are never blocked; only third-party ones are, and only until consent. If a third-party script is genuinely necessary, put its handle in the "Necessary" category on the settings tab.

= How does it know which handle is which? =

It does not guess and it does not phone a scanning service. Every front end page records the handles it printed, so the settings table fills itself as you browse your own site. Anything you have not categorised falls back to the rule "from this site: necessary, from anywhere else: needs consent".

= Does it work with caching? =

Yes, that is the point. The server always renders the blocked state, so the cached HTML is safe for every visitor, and the browser applies the individual decision afterwards.

= Where is the banner? =

There isn't one. This plugin is the enforcement layer. A banner writes to its REST endpoint. Blocked embeds carry their own two buttons, so visitors are never left staring at a placeholder they cannot dismiss.

= Does the hardening button undo itself when I deactivate the plugin? =

No. It changes WordPress settings and your posts, not plugin settings. Deactivating or deleting the plugin leaves all of it in place.

= Is the consent cookie readable by JavaScript? =

Yes, deliberately — the bootstrap script has to read it to know what to unblock. It contains a category list, a random id and a timestamp, and nothing else.

= What happens to the log if I delete the plugin? =

The table is dropped. Export it first; the settings tab says so as well.

== Screenshots ==

1. The Consent tab under Settings → Baukasten, with the asset table.
2. A blocked embed placeholder on the front end.

== Changelog ==

= 1.0.0 =
* First release.
* Blocking for classic scripts and styles, inline scripts attached to a blocked handle, ES modules, resource hints, oEmbeds, Gravatar and the emoji polyfill.
* Cache-safe: the server always renders the blocked state, a bootstrap script applies the individual decision in the browser.
* Append-only consent log with CSV and JSON export, containing no personal data.
* REST endpoints `GET /consent-state` and `POST /consent` under `baukasten-consent/v1`.
* Settings tab with a self-filling table of the site's own script and style handles.
* Measures outside consent: no commenter IP in the database, no speculative prefetching, no dashboard calls to api.wordpress.org, and a warning when the site is not served over HTTPS.
* Two-click loading for blocked embeds, either once or by allowing the category.
* "Apply privacy hardening and defaults", also available as `wp baukasten harden`.

== Upgrade Notice ==

= 1.0.0 =
First release.
