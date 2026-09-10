=== Baukasten Addon: Business Cards ===
Contributors: trstnbde
Tags: business card, vcard, qr code, contact, profile
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: baukasten
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

= Three layouts =

* **Classic** — a banner, a portrait overlapping it, and the contact details in full.
* **Modern** — no banner, a round portrait, the actions as a grid.
* **Bio** — a short header and then a column of full width buttons, for a card that is mostly links.

Every field is optional, and an empty field renders nothing at all: no empty heading, no stray separator, no gap where something used to be.

= What fits on a card =

Name, salutation, academic title, position and company. A biography. Call, email, WhatsApp and website as one row of primary buttons. Work and private email, phone and mobile, a website, a postal address and a what3words link. LinkedIn, Xing, GitHub, Mastodon, Facebook, Instagram, Threads, Discord and Signal, plus three links of your own. Three file downloads. Apple and Google Wallet badges. A contact form. Imprint, privacy policy and terms in the footer.

= Save contact =

A card can offer itself as a vCard 3.0 file, which is what every phone means by "add to contacts". Name, organisation, title, every phone number and address you filled in, the postal address and the biography as a note.

= The QR code =

A card can show itself as a QR code, drawn in the visitor's own browser. No image service, no API call, nothing that tells a third party that somebody looked at your card.

= Light and dark =

A card follows the visitor's system setting on its own. Switch on the toggle and they can override it; the choice is remembered on their device and goes no further.

= Contact form =

If Contact Form 7 is installed, a card can embed one of its forms. The form has to carry an acceptance checkbox for the email consent — if it does not, or if the visitor does not tick it, the submission is refused on the server. Without Contact Form 7 everything else on a card works exactly the same.

== Installation ==

1. Install and activate **Baukasten - Privacy Toolkit**. WordPress will not let this addon activate without it.
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

= Does this work without Contact Form 7? =

Yes. The contact form is the only part that needs it, and its field is simply hidden when it is not there. A form you had chosen is remembered if you deactivate Contact Form 7 and comes back with it.

= Can it make an Apple Wallet pass? =

No. Signing a `.pkpass` needs an Apple developer certificate, which a WordPress plugin has no way to hold for you. The two wallet fields take the address of a pass you host elsewhere, and the card shows a badge that links to it.

= Why does my card have no trailing slash? =

Because your site's permalink structure has none. Card addresses follow the site setting, like every other permalink.

= I use the Consent Blocking Engine and my form will not submit. =

If the form uses Cloudflare Turnstile, the Consent Blocking Engine holds that script back until the visitor consents — Turnstile is a third party, and blocking it is the engine doing its job. Either put it in a category your visitors accept, or use a form without it on cards.

= Does anything phone home? =

No. The QR code is drawn locally, there are no icon fonts and no brand logos, and a card with no contact form on it makes no third-party request at all.

== Third-party code ==

`assets/js/lib/qrcode.js` is the QR Code Generator for JavaScript by Kazuhiko Arase, version 1.4.4, used unmodified under the MIT licence. Its source is <https://github.com/kazuhikoarase/qrcode-generator>. It is the only third-party code in this plugin, it is not minified, and it runs entirely in the browser.

== Changelog ==

= 1.0.0 =

* First release.

== Upgrade Notice ==

= 1.0.0 =
First release.
