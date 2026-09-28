=== Baukasten Addon: Form Privacy ===
Contributors: trstnbde
Tags: contact form 7, flamingo, privacy, gdpr, spam
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: baukasten, contact-form-7
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Contact Form 7 and Flamingo without the leaks: no IP address, no address book, a retention period, and spam protection without a third party.

== Description ==

Contact Form 7 and Flamingo are a good pair, but out of the box they collect more than a contact form needs and keep it for ever. This addon keeps both, and closes the gaps with the filters they already offer — before the data is created, not after.

= Form assets only where a form is =

Contact Form 7 loads its script and stylesheet on every page, and once Cloudflare Turnstile or Google reCAPTCHA is set up, their scripts too — so every visitor's address goes to Cloudflare or Google on pages that have no form. Here Contact Form 7 only loads where a form is shown, and the captcha scripts are kept off every other page.

= No IP address =

Contact Form 7 never sees the sender's IP address, so it is in no mail, no stored message and no log.

= No address book =

Flamingo fills an address book on its own: from every form submission, from every user who registers or updates their profile, and from approved comments. That address book is switched off — no contact is collected any more, and the Address Book screen is gone.

= Less metadata, and a retention period =

With every message Contact Form 7 hands Flamingo nineteen pieces of metadata, IP address and browser among them. Only a short, configurable list is kept (date, time, page address, entry ID and title by default); the raw answers of Akismet, reCAPTCHA and Turnstile are dropped. Spam is not stored unless you want it, storage can be switched off entirely, and stored messages are deleted after a retention period — 90 days by default.

= A cleanup for what is already there =

One button, or `wp baukasten form-privacy clean`, deletes the old address book, reduces the metadata of stored messages, and applies the retention period right away. It reports what it changed, is safe to run twice, and never runs on its own.

= Access requests =

Stored messages appear in **Tools → Export Personal Data**, found by the sender's email address. Flamingo only registers an eraser, not an exporter.

= A paragraph for the privacy policy =

Settings → Privacy offers a suggested paragraph written from the current settings: what is stored, for how long, that no IP address is collected, that there is no address book and no external spam service.

= Spam protection that asks nobody =

A honeypot field that bots fill in and people never see, and a signed timestamp that catches forms sent back faster than a person could type. No request to anybody, no cookie, no JavaScript. It does not stop spam sent by hand.

== Installation ==

1. Install and activate **Baukasten - Privacy Toolkit** and **Contact Form 7**. WordPress will not let this addon activate without both.
2. Install and activate **Baukasten Addon: Form Privacy**. The protections are on straight away.
3. Review the settings under **Settings → Baukasten → Form Privacy**, and run the cleanup there once if Flamingo has been collecting before.
4. Set up a server cron job for `wp-cron.php`, so the retention period is applied on time even when the site has few visitors.

== Frequently Asked Questions ==

= Do I need Flamingo? =

No. Without Flamingo nothing is stored on the site anyway; the addon then only keeps Contact Form 7's files where they belong, keeps the IP address out, and protects against spam.

= Why not replace Flamingo? =

Because Contact Form 7 writes to Flamingo itself and keeps that code up to date, and every gap can be closed with filters both plugins already offer. A replacement would have to rebuild the list, the detail view, search, spam handling, export and eraser — more code, and nothing gained.

= Does the cleanup delete my messages? =

Only those older than the retention period. The rest keep their fields; only the metadata outside the list is removed.

= What about Cloudflare Turnstile? =

The honeypot replaces it. If spam still gets through, Contact Form 7's own Turnstile module is the next step — that brings in a third party, which the Consent Blocking Engine then holds back until the visitor consents.

= What does uninstalling remove? =

The addon's own settings and its cron event. Flamingo's messages stay.

== Changelog ==

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.0 =
First release.
