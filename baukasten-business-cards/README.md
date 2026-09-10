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

Three layouts — Classic, Modern, Bio — share the same partials and differ in composition and CSS.

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

## The vCard

`template_redirect`, at `/{base}/{slug}?action=vcf`, gated on the card's own `enable_vcf` field. `action` is not a registered query var, so it is read from `$_GET` directly — `get_query_var( 'action' )` returns an empty string here however the URL was built. `?bkcard=vcf` is accepted as a less generic alias.

RFC 2426 is unforgiving in two places, both handled rather than hoped about. Text values escape `\`, `;`, `,` and newlines in that order, while the semicolons separating the components of `N` and `ADR` are structure and stay bare. Content lines fold at 75 **octets**, split per UTF-8 character with `preg_split( '//u' )` so a fold never lands inside a multi-byte sequence and no `ext-mbstring` dependency is introduced.

The salutation is deliberately kept out of `N` and `FN`: contact apps render it as part of the name, and "Frau Dr. Änne …" reads badly in a contact list. It stays on the HTML card.

## What this plugin cannot do

- **Sign an Apple Wallet pass.** That needs an Apple certificate. The wallet fields are links to a pass hosted elsewhere.
- **Split a postal address into a proper `ADR`.** The field is free text, and no heuristic for splitting it into seven components is right for Germany, Austria, Switzerland, the UK and the US at once. The whole address goes into the street component with escaped line breaks, plus a matching `LABEL`, which is where RFC 2426 puts an unparsed presentation address. Five separate address fields would fix this and would be a better data model.
- **Guarantee a free base.** `Settings::sanitize_base()` checks core query vars, rewrite bases, every post type and taxonomy slug, and top-level pages and posts. It cannot check a literal rule another plugin added through `add_rewrite_rule()` — those are regexes, not slugs. The field says so.

## Living with the other addons

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

## Third-party code

`assets/js/lib/qrcode.js` is [QR Code Generator for JavaScript](https://github.com/kazuhikoarase/qrcode-generator) 1.4.4 by Kazuhiko Arase, MIT, used unmodified and unminified. It is the only third-party file in this plugin and the only one in the whole repository; `docs/WORDPRESS-ORG-COMPLIANCE.md` records the exception.

## Development

```bash
composer install
composer lint
```

## License

GPLv2 or later. See [LICENSE](LICENSE).
