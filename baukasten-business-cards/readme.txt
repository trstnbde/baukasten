=== Baukasten Addon: Business Cards ===
Contributors: trstnbde
Tags: business card, vcard, qr code, contact, wallet
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: baukasten, contact-form-7
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A digital business card on its own short link, built for a phone and detached from your theme. With vCard download and a QR code.

== Description ==

A business card is a thing you hand to someone. It is looked at on a phone, once, for about fifteen seconds, and it should not depend on how the rest of your site happens to look this year.

So a card here is not a page. It is served on its own short address, from its own template, with none of your theme attached — same card whatever theme the site wears, and nothing to load but the card itself.

= The address =

`example.com/b/a1b2c3d4`

Eight random characters, assigned once when the card is created and never changed again. The title you give a card is an internal label — "Sales, Berlin" — and never appears in the URL, so a card cannot be found by guessing someone's name, and a QR code you printed last year still works.

Cards are kept out of the sitemap and ask search engines not to index them, for the same reason.

The `/b/` part is yours to change, under Settings, Permalinks.

= Three designs =

One layout, three complete looks. The design is chosen per card.

* **Modernist** — Archivo, square corners, a hot orange-red, a hairline between sections.
* **Industry** — Barlow Condensed, a steel blue, and every section drawn as a plate with register marks at its corners.
* **Nocturne** — Inter, soft corners, a blurple accent, dark to begin with.

Each design carries a light and a dark palette. A card either follows the reader's own setting or is pinned to one of the two.

Every field is optional, and an empty field renders nothing at all: no empty heading, no stray separator, no gap where something used to be.

= What fits on a card =

Name, salutation, academic title, position and company. A biography. Call, email, WhatsApp and website as a row of four tiles — taken from the contact details below them, not typed a second time. Work and private email, phone and mobile, a website, a postal address and a what3words link. LinkedIn, Xing, GitHub, Mastodon, Facebook, Instagram, Threads, Discord and Signal, plus three links of your own. Three file downloads. An Apple Wallet pass and a Google Wallet token. A contact form. Imprint, privacy policy and terms in the footer.

= Save contact =

A card can offer itself as a vCard 3.0 file, which is what every phone means by "add to contacts". Name, organisation, title, every phone number and address you filled in, the postal address and the biography as a note.

= Wallet =

Upload the `.pkpass` and this site serves it itself, with the media type that makes a phone open Wallet rather than offer a download it cannot use. For Google, paste the signed token — or the whole save address, the token is taken out of it — and the card builds the button. Nothing reaches Apple or Google until somebody taps.

= Addresses and numbers =

Email addresses and telephone numbers are written into the page as character references rather than as themselves, so the plain string never appears in the source and the regular expressions address harvesters run find nothing. It is a speed bump rather than a lock — anything that renders the page reads them perfectly well — and it is done without JavaScript, so the links still work and copy and paste still gives the real value.

= The QR code =

A card can show itself as a QR code, drawn in the visitor's own browser. No image service, no API call, nothing that tells a third party that somebody looked at your card.

= Light and dark =

A card follows the visitor's system setting on its own. Switch on the toggle and they can override it; the choice is remembered on their device and goes no further.

= Contact form =

A card can embed a Contact Form 7 form, and there is a button under *Settings, Baukasten, Business Cards* that builds a suitable one: name, email, message, and a consent checkbox that links the card's own privacy policy and terms. Contact Form 7 is required, so it is there to build against.

The form has to carry an acceptance checkbox for the email consent — if it does not, or if the visitor does not tick it, the submission is refused on the server.

= Legal links =

If the **Baukasten Addon: Login Legal Pages** is installed, the imprint, privacy policy and terms come from there and are the same on every card. Without it, each card picks its own three pages from a dropdown of the site's pages.

== Installation ==

1. Install and activate **Baukasten - Privacy Toolkit** and **Contact Form 7**. WordPress will not let this addon activate without both of them.
2. Install and activate **Baukasten Addon: Business Cards**.
3. Go to **Business Cards** in the menu and add one.
4. Fill in what you want on it. Everything is optional.
5. Publish, then open the card from the *Address* column in the list.

To change the `/b/` part of the address, go to **Settings, Permalinks**.

== Frequently Asked Questions ==

= Why is the address eight random characters? =

Because a card is a link you hand out, not a page you publish. A readable slug would let anyone list your colleagues by guessing names, and would break the moment somebody changed a job title. The random one is assigned on the first save and never changes, so anything you printed it on keeps working.

= Can I choose my own address for a card? =

Not per card, no. The base is configurable, the card part is not — that is the point of it.

= Do cards show up in search results? =

Not by default. Cards are left out of the sitemap and sent with `noindex`. A filter, `baukasten/business_cards/discourage_indexing`, turns that off if you want them indexed.

= Why does this need Contact Form 7? =

Because the contact form on a card is a Contact Form 7 form. This addon builds one for you, keeps its consent wording in step with your legal pages, and refuses a submission that has not consented — all of it through Contact Form 7 rather than around it, so the form you get is the one you already know how to edit.

It is named in `Requires Plugins`, so WordPress will not activate this addon without it. If you switch Contact Form 7 off afterwards, cards keep working: the form field is hidden and the card serves without it, and a form you had chosen comes back when Contact Form 7 does.

= Can it make an Apple Wallet pass? =

No. Signing a `.pkpass` needs an Apple developer certificate, which a WordPress plugin has no way to hold for you. Build the pass elsewhere, upload the file here, and this site serves it with the media type that opens Wallet — which is the part a plain link gets wrong.

= Does it check the Google Wallet token? =

Only its shape: three base64url segments with a readable header. The signature was made with a key this site has never held, so nothing here can tell you the pass is genuine, still valid, or issued for your account. A check that cannot fail would be worse than none, because it reads like one that can.

= Where do the imprint and privacy links come from? =

From the Login Legal Pages addon if you have it — one answer for the whole site, which is what an imprint is. Without it, each card picks its three pages from a dropdown. Either way a page that is unpublished stops being linked instead of linking at a 404.

= Why does my card have no trailing slash? =

Because your site's permalink structure has none. Card addresses follow the site setting, like every other permalink.

= I use the Consent Blocking Engine and my form will not submit. =

If the form uses Cloudflare Turnstile, the Consent Blocking Engine holds that script back until the visitor consents — Turnstile is a third party, and blocking it is the engine doing its job. Either put it in a category your visitors accept, or use a form without it on cards.

= Does anything phone home? =

No. The QR code is drawn locally, there are no icon fonts and no brand logos, and a card with no contact form on it makes no third-party request at all.

== Third-party code ==

`assets/js/lib/qrcode.js` is the QR Code Generator for JavaScript by Kazuhiko Arase, version 1.4.4, used unmodified under the MIT licence. Its source is <https://github.com/kazuhikoarase/qrcode-generator>. It is not minified and it runs entirely in the browser.

`assets/fonts/` holds nine woff2 files under the SIL Open Font Licence 1.1, subset to Latin: Archivo, Barlow, Barlow Condensed and Inter. Each licence text sits beside them as `OFL-<family>.txt`. They are served from this site, never from a font service, and a card loads only the two faces its design uses.

== Screenshots ==

1. A card on a phone: photo, name, the buttons, and nothing of the theme.
2. The Business Cards tab under Settings, Baukasten.
3. The QR code and vCard download a card offers for handing itself on.

== Changelog ==

= 1.0.0 =

* First release.

== Upgrade Notice ==

= 1.0.0 =
First release.
