=== Baukasten - Privacy Toolkit ===
Contributors: trstnbde
Tags: privacy, gdpr, consent, login, hardening
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Blocks third parties until consent, hardens the site, makes content public or private, and puts legal links on a tidy login screen.

== Description ==

Baukasten is a privacy toolkit in one plugin. Everything is configured on one screen under **Settings → Baukasten**, with a tab per part:

* **Consent Blocking Engine** — blocks non-essential scripts, styles, embeds, resource hints, Gravatar and emoji until the visitor has consented, keeps an auditable consent log, and hardens the site.
* **Content Visibility** — a public/private switch for every post, page and public custom post type. Private content is readable by logged-in users only, regardless of their role.
* **Login Legal Pages** — links to your privacy policy, terms and imprint on the login screen, a readable `/login/` address, and a rate limit on failed sign-ins.

All three are always on. More features come as separate addon plugins, which add their own tabs to the same screen: Multi-Domain Landingpage, Business Cards, Two-Factor Approval and Form Privacy.

= Consent: what it blocks =

Most consent plugins show a banner and then ask the tracker nicely not to run. This one does the opposite: it makes sure nothing reaches a third party in the first place, and leaves the banner to somebody else.

* **Scripts and stylesheets** from other hosts, or any handle you assign to a category. A blocked script gets `type="text/plain"`, so the browser neither fetches nor runs it. Inline configuration attached to a blocked handle is blocked with it.
* **ES modules**, including Interactivity API block scripts.
* **`dns-prefetch` and `preconnect` hints** to other hosts. A hint is a connection, and a connection is an IP address handed over.
* **Embedded content** — YouTube, Vimeo, anything oEmbed. The iframe is parked in a `<template>` behind a short notice and two buttons: load this one embed, or allow the whole category from now on.
* **Gravatar.** A local placeholder is served instead.
* **The emoji polyfill**, which pulls a bundle from `s.w.org`.

Every response is rendered in the same blocked state for every visitor, so a full page cache can store it whole. A small script in the browser then reads the consent cookie and puts back exactly what that visitor allowed. That script and its stylesheet are only loaded on pages that need them: pages that load something from another host or contain an embed.

= Consent: the log =

Every decision is appended to its own table; nothing is ever updated in place, so the history of a visitor's decisions can be reconstructed — which is what Article 7(1) GDPR asks a controller to be able to do. It holds no IP address, no user id and no user agent, only a random id from the visitor's own cookie, the categories, the policy version and the time. Rows older than the retention period (24 months by default, never less than 13) are deleted once a day. Export the log as CSV or JSON at any time.

The consent endpoint only accepts JSON sent from a page of this site, so another site cannot forge a decision.

= Site hardening =

Some leaks are not a decision a visitor could sensibly make, so they are simply switched off: the commenter's IP address never reaches the database, and the dashboard stops calling api.wordpress.org for its news widget and browser check. The REST user list, author archives, the author fields of oEmbed responses and the users sitemap no longer publish the name an administrator logs in with, and XML-RPC is switched off. Every plugin and theme — including ones installed later — is updated automatically.

**Apply privacy hardening and defaults** puts the whole site into a defensible default state in one pass: comments and pings closed on every entry, registration off, search engines discouraged, every discussion option off, avatars off, the credentials on Settings → Connectors emptied, automatic updates on for every installed plugin and theme, and every blocking and hardening measure switched on. `wp baukasten harden` does the same from the command line.

The hardening routine never runs on its own — not on activation, not on update — only from the button on the settings tab or the WP-CLI command. It reports what it changed and is safe to run again. It changes the site rather than the plugin, which is why the button asks first.

= Content Visibility =

WordPress already has a "Private" post status, but it is tied to the `read_private_posts` capability. This adds a second, independent flag: private means logged in, any role; logged out means not at all.

The switch appears as a column on every list table, as bulk actions, and as a box in the editor. The flag is enforced in every layer that can leak content: single views, the embed view and the oEmbed endpoint, archives, search, feeds, sitemaps and the REST API. Making content private also empties the page cache of WP Super Cache, W3 Total Cache, WP Rocket and LiteSpeed Cache.

An entry with no stored flag counts as private, so installing the plugin marks every entry that already exists as public. Only content created afterwards defaults to private.

= Login Legal Pages =

The login screen becomes one card with your privacy policy, terms and imprint under the form. The WordPress header and the "Go to <site>" link are removed. The login screen is served from `/login/` instead of `/wp-login.php`; `wp-login.php` keeps working and redirects. That address is a convenience, not a security feature.

The rate limit is. Five failed sign-ins within fifteen minutes lock the IP address out for fifteen minutes, even with the correct password, and every further lockout within a day doubles the wait, up to 24 hours. It counts on `/login/`, `wp-login.php`, XML-RPC and application passwords alike, and wrong two-factor codes from the Two Factor plugin count too. Addresses are stored hashed and only for as long as the lock lasts, and never per user name, so nobody can lock another user out. Password reset requests are limited to three per address and hour.

= Writing an addon =

An addon is an ordinary plugin. It declares `Requires Plugins: baukasten` in its header and registers a tab:

`
add_action( 'baukasten/register_addons', function () {
    Baukasten\Addons::register( array(
        'id'          => 'my-addon',
        'title'       => __( 'My Addon', 'my-addon' ),
        'plugin_file' => MY_ADDON_FILE,
        'render'      => 'my_addon_render_tab',
    ) );
} );
`

== Installation ==

1. Install and activate **Baukasten - Privacy Toolkit**.
2. Open **Settings → Baukasten**. Consent blocking starts immediately, with third-party assets in the "Functional" category.
3. Browse a few front end pages while logged in, so the Consent tab can list which scripts and styles your site actually loads, and put each in the right category.
4. Pick your terms and imprint pages on the **Login & Legal** tab. The privacy policy is set under **Settings → Privacy**, where WordPress keeps it.

== Frequently Asked Questions ==

= I used the separate addons Consent Blocking Engine, Content Visibility or Login Legal Pages. What happens to them? =

They are part of this plugin now. Updating it deactivates the old plugins; their settings, the visibility flags and the consent log are used exactly as they are. Then remove the old plugin folders by FTP or SSH. **Do not delete them on the Plugins screen**: their own uninstall routine would delete the data this plugin now uses.

= Will the consent blocking break my site? =

Assets served from your own domain are never blocked; only third-party ones are, and only until consent. If a third-party script is genuinely necessary, put its handle in the "Necessary" category.

= Where is the banner? =

There isn't one. This plugin is the enforcement layer; a banner from your theme or another plugin writes the decision to `POST /wp-json/baukasten-consent/v1/consent` as JSON. Blocked embeds carry their own two buttons.

= Does the hardening button undo itself when I deactivate the plugin? =

No. It changes WordPress settings and your posts, not plugin settings.

= Why are all plugins suddenly updating automatically? =

Because "Update every plugin and theme automatically" is on by default: outdated plugins are the most common way into a WordPress site. Switch it off on the Consent tab if you manage updates yourself.

= Can this lock me out? =

The login address cannot: `wp-login.php` keeps working. The rate limit locks out an IP address, never an account, and only after repeated failures. If it ever locks you out, wait, or delete the transients starting with `baukasten_login_lock_`.

= Can I let an editor manage these settings? =

Yes. Grant the `manage_baukasten` capability to any role. Content Visibility additionally checks `manage_options`, Login & Legal `manage_privacy_options`.

= Does the plugin phone home? =

No. It contacts no external server, loads nothing from a CDN, and collects no usage data.

= What happens to my data if I delete the plugin? =

Its options, the visibility flags and the consent log table are removed. Export the log first; the Consent tab says so as well.

== Screenshots ==

1. The Baukasten settings screen with one tab per part.
2. The overview tab, listing the built-in parts and every addon.

== Changelog ==

= 1.0.0 =
* Consent Blocking Engine, Content Visibility and Login Legal Pages are part of this plugin instead of separate addons. Their settings and data carry over unchanged.
* Consent: the endpoint only accepts JSON from this site's own pages; the nonce is gone.
* Consent: front end script and styles only load where something is blocked or embedded.
* Consent: the asset list is only recorded for administrators, or for everyone during a 30 minute scan.
* Consent: log rows are deleted after a retention period, 24 months by default.
* Hardening: automatic updates for every plugin and theme, now and later; also a step of the hardening routine.
* Hardening: REST user list, author archives, oEmbed author fields and the users sitemap no longer reveal the administrator's login name; XML-RPC is off. Application passwords can be switched off.
* Hardening: speculative loading is no longer blocked by default.
* Content Visibility: the embed view and the oEmbed endpoint no longer reveal private content.
* Content Visibility: making content private empties common page caches.
* Login: rate limit for failed sign-ins and password reset requests.
* Addons are ordinary WordPress plugins with `Requires Plugins: baukasten`, registered through `baukasten/register_addons`.

== Upgrade Notice ==

= 1.0.0 =
Consent Blocking Engine, Content Visibility and Login Legal Pages are now built in. The old plugins are deactivated automatically; remove their folders by FTP, not from the Plugins screen, or their uninstall routine deletes your data.
