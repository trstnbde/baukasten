=== Baukasten - Privacy Toolkit ===
Contributors: trstnbde
Tags: privacy, gdpr, consent, cookies, imprint
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A settings hub for the Baukasten addons. Install the addons you need and configure them all in one place.

== Description ==

Baukasten is the shared base for a family of small, focused plugins, most of them about privacy. On its own it does nothing to your site: it adds one screen under **Settings → Baukasten** and a capability, and then waits for addons to fill it.

Every feature is a separate plugin. You install only what you need, each addon updates on its own schedule, and each one can be deactivated without touching the others — but you configure all of them on one screen instead of hunting through six different settings pages.

= The addons =

* **Baukasten Addon: Content Visibility** — a public/private switch for every post, page and public custom post type. Private content is readable by logged-in users only, regardless of their role.
* **Baukasten Addon: Login Legal Pages** — page pickers for your terms and imprint, links to all three legal pages on the login screen, and a tidy `/login/` URL.
* **Baukasten Addon: Consent Blocking Engine** — blocks non-essential scripts, styles, embeds, resource hints, Gravatar and emoji until the visitor has consented, and keeps an auditable consent log.
* **Baukasten Addon: Multi-Domain Landingpage** — gives a page its own domain, so one install serves a different front page under each domain you point at it.
* **Baukasten Addon: Business Cards** — a digital business card on its own short link, built for a phone and detached from your theme, with vCard download and a QR code.
* **Baukasten Addon: Two-Factor Approval** — a two-factor method that asks a second, already signed-in browser session to approve the login from the admin bar.

= What the core does =

* **One settings screen.** Each active addon gets its own tab under Settings → Baukasten. Deactivate the addon and the tab disappears with it.
* **An overview of every addon.** Name, description and version of each one, whether it is active, installed but switched off, or not installed at all — with a link straight to installing or activating it. The list is built into the plugin; nothing is fetched from anywhere.
* **A real capability.** Reaching the screen requires `manage_baukasten`, granted to administrators on activation. It is not tied to `manage_options`, so it can be delegated. Addons may require an additional capability of their own for their tab.
* **Nothing else.** No tracking, no external requests, no dashboard advertising, no upsells.

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

Nothing has to be inherited or implemented, and an addon that guards the call with `class_exists( 'Baukasten\Addons' )` keeps working when the core plugin is not installed.

== Installation ==

1. Install and activate **Baukasten - Privacy Toolkit** through **Plugins → Add Plugin**.
2. Install the addons you want the same way. WordPress will not let an addon activate without this plugin.
3. Configure everything under **Settings → Baukasten**.

== Frequently Asked Questions ==

= Do I need the addons? =

Yes. On its own this plugin only provides the settings screen the addons register their tabs on. Install whichever addons you need.

= I used an older version that uploaded modules as ZIP files. What happens to those? =

Versions before 1.0.0 stored modules in `wp-content/uploads/baukasten-modules/`. That system is gone: modules are now ordinary plugins, installed through WordPress. Updating removes the leftover options, the old capability and the module directory. Install the addon plugins to get the same features back.

= Can I let an editor manage these settings? =

Yes. Grant the `manage_baukasten` capability to any role. Individual addons may check an additional capability for their own tab, for example `manage_privacy_options`.

= Does it work on multisite? =

Settings are stored per site. Each site in a network configures its addons on its own.

= Does the plugin phone home? =

No. Neither the core nor any of the addons contacts an external server, loads assets from a CDN, or collects usage data.

== Screenshots ==

1. The Baukasten settings screen with one tab per active addon.
2. The overview tab, listing every addon with its version and whether it is installed.

== Changelog ==

= 1.0.0 =
* First release under the new architecture. Addons are ordinary WordPress plugins installed from the plugin directory instead of ZIP archives uploaded into the plugin.
* New settings screen with one tab per addon, and an overview of what is installed.
* New addon API: `baukasten/register_addons` and `Baukasten\Addons::register()`.
* The module uploader, scanner, registry and fatal error guard were removed; WordPress does all of that for real plugins.
* The capability is now `manage_baukasten`. The old `manage_baukasten_modules` capability, the module options and the module upload directory are removed on update.

== Upgrade Notice ==

= 1.0.0 =
Modules are now separate plugins. Updating removes the module upload directory and its options; install the Baukasten addon plugins from the plugin directory to get those features back.
