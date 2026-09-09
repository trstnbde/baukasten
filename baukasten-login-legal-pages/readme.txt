=== Baukasten Addon: Login Legal Pages ===
Contributors: trstnbde
Tags: login, privacy, imprint, terms, gdpr
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Requires Plugins: baukasten
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy policy, terms and imprint under the login form, no WordPress header on the screen, and a login address people can read.

== Description ==

In several jurisdictions an imprint and a privacy policy have to be reachable from every page of a site. The login screen is a page of your site, and WordPress links neither. It links itself instead: a WordPress logo at the top, and a "Go to <site>" link at the bottom.

This addon fixes both ends.

= Legal links under the form =

The login screen becomes one card. The form sits on top; under it, in the same card, a recessed footer holds "Lost your password?" on one line and your privacy policy, terms of service and imprint on the next, separated by pipes. Only pages that exist and are published are linked — a dead link on the login screen is the first thing every user of the site sees, so a missing page is simply left out.

The privacy policy comes from WordPress itself, on Settings → Privacy. The other two are picked on the addon's own tab.

= A login screen without WordPress branding =

The header with the WordPress logo and the "Go to <site>" link below the form are both removed. Apart from that and the link panel, nothing is restyled: it stays the login screen WordPress ships, so it keeps working with admin colour schemes, translations and anything else that hooks into it.

The screen reader heading core prints above the form is deliberately kept.

= A readable login address =

The login screen is served from `/login/` instead of `/wp-login.php`, and every login link, logout link and form action WordPress generates points there. The slug is configurable.

`wp-login.php` keeps working and simply redirects. This is a tidier address, **not** a security feature and not a "hide my login" plugin: nothing is blocked, and nobody can be locked out of their own site by it.

== Installation ==

1. Install and activate **Baukasten - Privacy Toolkit**. WordPress will not let this addon activate without it.
2. Install and activate **Baukasten Addon: Login Legal Pages**.
3. Open **Settings → Baukasten → Login & Legal** and pick your terms and imprint pages.
4. Set the privacy policy under **Settings → Privacy**, where WordPress keeps it.

== Frequently Asked Questions ==

= Is `/login/` a security feature? =

No. Moving the login screen to a different address does not stop anyone determined; `wp-login.php` still answers, it just redirects. Use strong passwords and two-factor authentication for security.

= Can this lock me out? =

No. `wp-login.php` keeps working. If the address ever gets in your way, delete the `baukasten_login_slug` option or deactivate the plugin.

= Why does the login address need pretty permalinks? =

Without them a request for `/login/` never reaches WordPress at all. When permalinks are off, the addon leaves `wp-login.php` alone and says so on its settings tab.

= What if a page already uses the same slug? =

The login screen wins, and that page becomes unreachable. The settings tab checks for this and warns you.

= Where did the glass styling go? =

Version 1.0.0 removed it. The login screen is now WordPress's own, unchanged apart from the removed header and back link. The filters `baukasten/login_legal_pages/logo_url` and `baukasten/login_legal_pages/gradient` are gone with it.

= Removing the header takes away the only link back to the site. Is that on purpose? =

Yes. Both the logo link and the "Go to <site>" link are removed by design. The legal links under the form remain, and they lead to the site.

== Screenshots ==

1. The login screen: one card, no WordPress header, no back link, four links in the card footer.
2. The Login & Legal tab under Settings → Baukasten.

== Changelog ==

= 1.0.0 =
* First release as a standalone plugin. Earlier versions were a module inside the Baukasten plugin.
* The form and the four links below it are one card, with a recessed footer, a divider and rounded corners. All four links are styled alike, and the legal row wraps when the labels do not fit.
* The login screen is served from `/login/`, configurable, with every generated login URL following it. `wp-login.php` redirects there.
* Removed the glassmorphism styling. The login screen is WordPress's own again.
* Removed the header with the WordPress logo and the "Go to site" link.
* Settings moved from an injected block on Settings → Privacy to their own tab under Settings → Baukasten.
* Removed the filters `baukasten/login_legal_pages/logo_url` and `baukasten/login_legal_pages/gradient`; added `baukasten/login_legal_pages/login_slug`.

== Upgrade Notice ==

= 1.0.0 =
Login Legal Pages is now a plugin of its own, the login screen is no longer restyled, and it moves to /login/. Check Settings → Baukasten → Login & Legal after updating.
