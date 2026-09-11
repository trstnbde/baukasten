# Baukasten Addon: Business Cards

A digital business card on its own short link, built for a phone and detached from the theme.

An addon for [Baukasten - Privacy Toolkit](../baukasten).

| | |
|---|---|
| Requires WordPress | 6.5 |
| Requires PHP | 8.1 |
| Requires plugin | `baukasten` |
| License | GPLv2 or later |

## What a card is

A `baukasten_card` post with about fifty optional meta fields, no content, no editor, and a template of its own. It is served at `/{base}/{slug}`, where the base defaults to `b` and the slug is eight random characters.

## The three designs

Modernist, Industry and Nocturne are **one layout in three skins**, not three layouts. They differ in typeface, palette, spacing, radii, how a photograph is treated, whether a section is drawn as a framed plate, and whether the design starts light or dark. None of that is structure, so there is one `templates/layouts/card.php` and a table in `Skins`, rather than three near-identical template files that would have drifted apart.

A skin is `assets/css/skins/<id>.css`: `@font-face` blocks, its two palettes, its tokens, and the handful of rules that are its own. `assets/css/card.css` is the skeleton and is byte-identical for every card on a site, so a visitor who opens two cards of different designs downloads it once. Two stylesheets, and a skin file is the unit a site forks.

**The `src` in a skin's `@font-face` is relative to the stylesheet**, so it is `url("../../fonts/…")` — two levels up, not one.

## Light and dark

Two independent axes — which design, and which of its two grounds — plus a third input, what the design's own starting point is. CSS cannot branch on a custom property's value, so the starting point is an attribute the server writes.

Each skin declares a light ground and a dark ground as **named pairs** (`--bkbc-light-*`, `--bkbc-dark-*`) and never assigns them. `card.css` owns the single assignment layer, five rules whose order is the whole design:

1. light, for everyone
2. the design's own starting point, when that is dark
3. the card, when it is pinned to light or dark
4. the reader's system setting, when the card follows them and they have not touched the switch
5. the switch

Only four tokens ever flip — background, surface, text, divider — which is what keeps it to five short rules. Rules 3 and 5 exist separately on purpose: 5 is what the blocking head snippet writes, and **3 is what makes a pinned card still work with JavaScript off**. Without rule 3 a card pinned to dark comes out light on a light phone, and the only symptom is that it looks wrong.

A design that starts dark does **not** follow the system back out into light. Nocturne being dark is a property of the design, like a poster's ink; the switch is there for anyone who disagrees, and it wins in both directions.

All 54 combinations — three designs × three card settings × three switch states × two system settings — are asserted by reading the computed background, not by eye.

## The address

The slug is assigned once, in `wp_insert_post_data`, and never rewritten. Two things make that work:

`wp_unique_post_slug()` runs **before** `wp_insert_post_data` is applied, not after, so the value the filter returns is what reaches the database — nothing downstream can append a `-2`. And because a non-empty `post_name` always comes back, the title-derived fallback later in `wp_insert_post()` never fires, which is what keeps an internal label like "Sales, Berlin" out of the URL.

The alphabet is lowercase base36, **not** base62, and that is not a shortcut. `WP_Query::parse_query()` runs the requested name through `sanitize_title_for_query()`, which lowercases unconditionally, so a stored `Ab3Kf9Zq` would be looked up as `ab3kf9zq`: the card would 404 while the admin list showed a permalink pointing straight at it. 36⁸ is about 2.8 × 10¹², drawn with `random_int()` per character so there is no modulo bias.

## Routing

The route is the post type's own permastruct — `'rewrite' => array( 'slug' => $base, 'with_front' => false )` — rather than an `add_rewrite_rule()` of ours. `get_permalink()` reads `WP_Rewrite::get_extra_permastruct()`, and only `WP_Post_Type::add_rewrite_rules()` ever fills that in; a hand-written rule would serve the pretty URL and then have `redirect_canonical()` bounce it back to `?baukasten_card=…`, because that is what `get_permalink()` would still be returning.

Rules are flushed on activation, on deactivation, and once on the first request after the base or the plugin version changes — a stamp in one option, compared on `init`, so a base changed by WP-CLI or a migration is picked up too.

Card addresses carry a trailing slash only if the site's permalink structure does. **Build every card URL with `get_permalink()`**, never by concatenation.

With plain permalinks there is no permastruct at all and cards are reachable at `?baukasten_card=…`; the base field on the Permalinks screen is disabled and says so.

## Detaching the theme

`template_include` returns `templates/card-view.php`, a whole HTML document. `wp_head()` and `wp_footer()` still run, because a contact form and its captcha register themselves there.

Removing the theme takes two passes, because neither is sufficient alone:

- **By callback.** Everything that prints straight into the head — feed links, oEmbed and REST discovery, emoji, `wp_enqueue_global_styles`, the block template skip link. The one that bites: on a block theme `wp_enqueue_global_styles()` unhooks `wp_custom_css_cb` itself; remove that function and nothing unhooks it any more, so the Customizer CSS lands on a card that has nothing else of the theme left on it.
- **By queue.** A late pass over `wp_styles()->queue` and `wp_scripts()->queue` drops a fixed list of core block handles — `global-styles` and its relatives are registered with `src === false` and deliver their payload inline, so no URL test can find them — plus anything whose `src` sits under a theme root. **Everything else is kept on purpose**: a card can carry a form and a captcha from plugins whose handles cannot be known in advance, and losing those silently would be worse than a stray stylesheet.

Known limit: `wp_enqueue_block_support_styles()` attaches an anonymous closure to `wp_head` that neither `remove_action` nor a dequeue can reach. It only fires from block render callbacks, and the card template never calls `the_content()` or `do_blocks()` — and must not start.

## Wallet

**Apple.** The card names an attachment; the plugin serves it. `upload_mimes` lets a `.pkpass` through — and nothing else is needed, because core sniffs one as `application/zip`, which is in its own `$nonspecific_types`, and that branch only requires the declared type's major part to be `application`. The route reads the attachment id from the card being viewed and from nowhere else, so a caller can name a card but never a file; it then checks the stored media type and that the file really sits inside the uploads tree.

It is served rather than linked because the media type is the whole thing: iOS opens Wallet from `application/vnd.apple.pkpass` and from nothing else, and a web server that has never heard of the extension sends `application/octet-stream`. The file is still reachable at its uploads URL by anyone who guesses it — the plugin cannot fix that without leaving the media library — but the card never prints that address.

**Google.** There is no pass file to serve: a Google Wallet pass exists only as a signed JWT behind `pay.google.com/gp/v/save/…`. So the card stores the token and builds the address itself. The sanitiser accepts a whole save URL and takes the token out of it, and checks the shape — three base64url segments with a readable header. **The signature is not checked**, because verifying it needs the issuer's public key, and a check that cannot fail is worse than no check.

The button is the plugin's own, not Google's asset: a brand mark is a trademark with its own rules, which is the same position `Icons` takes on network logos.

## The vCard

`template_redirect`, at `/{base}/{slug}?action=vcf`, gated on the card's own `enable_vcf` field. `action` is not a registered query var, so it is read from `$_GET` directly — `get_query_var( 'action' )` returns an empty string here however the URL was built. `?bkcard=vcf` is accepted as a less generic alias.

RFC 2426 is unforgiving in two places, both handled rather than hoped about. Text values escape `\`, `;`, `,` and newlines in that order, while the semicolons separating the components of `N` and `ADR` are structure and stay bare. Content lines fold at 75 **octets**, split per UTF-8 character with `preg_split( '//u' )` so a fold never lands inside a multi-byte sequence and no `ext-mbstring` dependency is introduced.

The salutation is deliberately kept out of `N` and `FN`: contact apps render it as part of the name, and "Frau Dr. Änne …" reads badly in a contact list. It stays on the HTML card.

## What this plugin cannot do

- **Sign an Apple Wallet pass.** That needs an Apple certificate, which a plugin has no way to hold. Build the pass elsewhere and upload it.
- **Split a postal address into a proper `ADR`.** The field is free text, and no heuristic for splitting it into seven components is right for Germany, Austria, Switzerland, the UK and the US at once. The whole address goes into the street component with escaped line breaks, plus a matching `LABEL`, which is where RFC 2426 puts an unparsed presentation address. Five separate address fields would fix this and would be a better data model.
- **Sign a Google Wallet token, or tell you whether one is any good.** See above.
- **Guarantee a free base.** `Settings::sanitize_base()` checks core query vars, rewrite bases, every post type and taxonomy slug, and top-level pages and posts. It cannot check a literal rule another plugin added through `add_rewrite_rule()` — those are regexes, not slugs. The field says so.

## Living with the other addons

**Login Legal Pages**, when it is installed, is the only source of the imprint, privacy policy and terms — the per-card fields are not offered at all, because a field that saves a value nothing reads is a trap. `Legal_Links` reads its three individual getters rather than its `get_links()`: that one applies `baukasten/login_legal_pages/links`, whose own docblock says it filters the links *in the login footer*, and somebody who adds one there has not asked for it on every business card.

`Meta_Boxes::save()` skips the three page fields while that box is hidden. Without it, a save of any unrelated field would read "posted nothing" as "was cleared" and throw the site's legal pages away.

**Content Visibility** defaults every new post of a supported type to private, which for a business card is exactly backwards: the whole point is a link you hand to someone who is not logged in. Cards are removed from its list through its documented `baukasten/content_visibility/post_types` filter. Put them back with `baukasten/business_cards/respect_content_visibility`.

**Consent Blocking Engine** treats any host but the site's own as third party, Cloudflare included. Measured on a test install: a Turnstile script on a card route is rewritten to `type="text/plain"` with `data-baukasten-consent="functional"`, so a Turnstile-protected form cannot be submitted until the visitor consents — with no error to explain why. That is the engine doing its job, not a bug, and this plugin does not override it. A card with no contact form drops Contact Form 7's and Turnstile's assets entirely, so it makes no third-party request at all.

## Filters

| Filter | Purpose |
|---|---|
| `baukasten/business_cards/base` | The URL segment cards are served from. |
| `baukasten/business_cards/card` | A card's fields, after loading and before rendering. |
| `baukasten/business_cards/template` | The template a card is rendered with. |
| `baukasten/business_cards/discourage_indexing` | `false` to let cards into the sitemap and search results. |
| `baukasten/business_cards/respect_content_visibility` | `true` to let the Content Visibility addon manage cards. |
| `baukasten/business_cards/skins` | The designs a card can be rendered in. |
| `baukasten/business_cards/legal_links` | The three links at the foot of a card. |

## Third-party code

`assets/js/lib/qrcode.js` is [QR Code Generator for JavaScript](https://github.com/kazuhikoarase/qrcode-generator) 1.4.4 by Kazuhiko Arase, MIT, unmodified and unminified.

`assets/fonts/` holds nine woff2 files under the SIL Open Font Licence 1.1, subset to Latin — Archivo, Barlow, Barlow Condensed and Inter — with each licence text beside them. They are served from the site and never from a font service, and a card loads only the two faces its design paints with.

`docs/WORDPRESS-ORG-COMPLIANCE.md` records both.

## Development

```bash
composer install
composer lint
```

## License

GPLv2 or later. See [LICENSE](LICENSE).
