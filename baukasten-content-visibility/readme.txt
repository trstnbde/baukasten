=== Baukasten Addon: Content Visibility ===
Contributors: trstnbde
Tags: privacy, private, members, visibility, restrict
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Requires Plugins: baukasten
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A public/private switch on every post and page. Private content is readable by logged-in users only, whatever their role.

== Description ==

WordPress already has a "Private" post status, but it is tied to the `read_private_posts` capability, so only editors and administrators can see it. That is the wrong tool for a site that wants a members area: content that every logged-in user may read, and nobody else.

This addon adds a second, independent flag. Every post, page and public custom post type is either **public** or **private**. Private means: logged in, any role. Logged out means: not at all.

= Where the switch appears =

* A column with a toggle on every list table, saved without a page reload.
* Bulk actions for setting many entries public or private at once.
* A box in the sidebar of the editor.

= How private is enforced =

Hiding a link is not access control, so the flag is applied in every layer that can leak content:

* Single views are stopped before the template renders — either by sending the visitor to the login form and back, or with a 403.
* Archives, the blog index, search and any secondary query drop private entries entirely.
* Feeds and the excerpt feed are filtered.
* Private entries are left out of the XML sitemaps.
* The REST API refuses both single items and collections.

= Existing content is never taken offline =

An entry with no stored flag counts as private. That would take a live site's whole archive offline the moment you activate the plugin, so activation marks every entry that already exists as public. Only content created afterwards defaults to private. The migration can be run again from the settings tab after you add a content type that already has entries.

= Settings =

Everything is configured on the **Content Visibility** tab under **Settings → Baukasten**: which content types the switch applies to, and whether a logged-out visitor is redirected to the login form or gets a 403.

= For developers =

`Baukasten\ContentVisibility\is_private( $post )`, `get_visibility( $post )` and `viewer_may_see_private()` are available to themes and other plugins. The filters `baukasten/content_visibility/post_types`, `…/viewer_may_see_private` and `…/login_redirect` override the stored settings.

== Installation ==

1. Install and activate **Baukasten - Privacy Toolkit**. WordPress will not let this addon activate without it.
2. Install and activate **Baukasten Addon: Content Visibility**.
3. Configure it on the **Content Visibility** tab under **Settings → Baukasten**.

== Frequently Asked Questions ==

= Does this replace the "Private" post status? =

No, it is separate. Core's private status stays what it was and needs `read_private_posts`. This flag only asks whether somebody is logged in.

= What happens to my content when I activate the plugin? =

Nothing becomes invisible. Everything that exists at that moment is explicitly marked public.

= And when I deactivate it? =

Everything becomes visible again. The flags stay stored, so reactivating restores the previous state. Only deleting the plugin removes them.

= Can subscribers see private content? =

Yes. Any logged-in user can, regardless of role. That is the point of this plugin. Use `baukasten/content_visibility/viewer_may_see_private` if you need a stricter rule.

= Is private content really hidden from the REST API? =

Yes. Single items are refused and collections are filtered, for logged-out requests.

== Screenshots ==

1. The visibility column on the posts list, with the toggle.
2. The settings tab under Settings → Baukasten.

== Changelog ==

= 1.0.0 =
* First release as a standalone plugin. Earlier versions were a module inside the Baukasten plugin.
* New settings tab: pick the content types the switch applies to, and choose between a login redirect and a 403 for logged-out visitors.
* The migration that marks existing content public can now be run again from the settings tab.

== Upgrade Notice ==

= 1.0.0 =
Content Visibility is now a plugin of its own. Install it through the plugin directory; the module version inside Baukasten is no longer used.
