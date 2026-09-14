# WordPress.org readiness

Checked on 9 September 2026, again for Business Cards on 13 September 2026, and again for Two-Factor Approval on 14 September 2026, against the [detailed plugin guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/), the [header requirements](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/) and the [readme standard](https://wordpress.org/plugins/readme.txt), for all seven plugins in this repository.

| | Slug | Version | Requires WP | Requires PHP |
|---|---|---|---|---|
| Baukasten - Privacy Toolkit | `baukasten` | 1.0.0 | 6.5 | 8.0 |
| Baukasten Addon: Content Visibility | `baukasten-content-visibility` | 1.0.0 | 6.5 | 8.0 |
| Baukasten Addon: Login Legal Pages | `baukasten-login-legal-pages` | 1.0.0 | 6.5 | 8.0 |
| Baukasten Addon: Consent Blocking Engine | `baukasten-consent-blocking-engine` | 1.0.0 | 6.5 | 8.1 |
| Baukasten Addon: Multi-Domain Landingpage | `baukasten-multi-domain` | 1.0.0 | 6.5 | 8.0 |
| Baukasten Addon: Business Cards | `baukasten-business-cards` | 1.0.0 | 6.5 | 8.1 |
| Baukasten Addon: Two-Factor Approval | `baukasten-2fa` | 1.0.0 | 6.5 | 8.1 |

The slugs in that table are the slugs these plugins must end up with. WordPress.org does not read them from here — it derives the slug from the `Plugin Name` header, and none of the seven derives correctly. See [RELEASING.md](RELEASING.md); it is a one-shot correction at submission time.

Nothing below is outstanding except the items under **Before submitting**.

---

## The eighteen guidelines

| # | Guideline | Status |
|---|---|---|
| 1 | GPL compatible | ✅ GPLv2-or-later declared in every plugin header, `readme.txt` and `LICENSE`. Two bundled third-party sets, both GPL-compatible and both in Business Cards: `assets/js/lib/qrcode.js` is QR Code Generator for JavaScript 1.4.4 by Kazuhiko Arase, MIT, unmodified and unminified; `assets/fonts/` holds nine Latin-subset woff2 files of Archivo, Barlow, Barlow Condensed and Inter under the SIL Open Font Licence 1.1, each licence text shipped beside them. Both are credited in that plugin's `readme.txt`. Nothing else — no other libraries, no images from elsewhere. |
| 2 | Developer is responsible for the plugin | ✅ Single author, no third-party licensing to reconcile. |
| 3 | Stable version distributed from the directory | ⬜ Applies at release. `bin/build.php` produces the archive that goes into SVN trunk; it refuses to build when the plugin header version and the `readme.txt` stable tag disagree. |
| 4 | Human readable code | ✅ No minification, no obfuscation, no build step. The files shipped are the files written. |
| 5 | No trialware | ✅ No licence checks, no locked features, no expiry. |
| 6 | Software as a service | ✅ Not applicable. Nothing talks to a service. |
| 7 | No tracking without consent | ✅ None of the seven plugins makes an outgoing HTTP request of its own. The core's addon catalog is a hard-coded PHP array rather than a `plugins_api()` call, precisely so the overview can name an uninstalled addon without one. The one request any of their code triggers is the browser posting to this site's own REST endpoint, after a visitor clicks "load and always allow" on a blocked embed. The Consent Blocking Engine exists to *stop* third-party requests, and switches off the ones WordPress core makes to api.wordpress.org from the dashboard. |
| 8 | No executable code from third parties | ✅ Every script, stylesheet, font and image is served from the plugin folder, the bundled QR encoder and the four typefaces included — the encoder runs in the browser and makes no request of its own, and a card's fonts come from the site rather than a font service. `assets/img/avatar.svg` is local by design — it replaces a request to gravatar.com. |
| 9 | Nothing illegal, dishonest or offensive | ✅ |
| 10 | No "powered by" credits | ✅ None. |
| 11 | Do not hijack the admin | ✅ One submenu under Settings. No dashboard notices, no upsells, no advertising. **Fixed in 1.0.0:** Login Legal Pages used to inject a settings block into `options-privacy.php` through `admin_notices` and move it with JavaScript; it now has its own tab. The three "core plugin is missing" notices are dismissible, capability-gated, and only ever appear on a broken install. |
| 12 | No readme spam | ✅ Five tags each, no affiliate links, no competitor names, no keyword stuffing. Short descriptions are 113–134 characters, under the 150 limit. |
| 13 | Use the bundled libraries | ✅ Nothing WordPress ships is duplicated; no jQuery, no polyfills. WordPress bundles neither a QR encoder nor a typeface, which is why Business Cards carries both. `assets/js/consent-bootstrap.js` is dependency-free on purpose — it must run before anything it unblocks. |
| 14 | SVN is a release repository | ⬜ Applies at release. |
| 15 | Version numbers increment | ✅ All seven start at 1.0.0. The pre-release core was 1.1.0 and is reset to 1.0.0 for the first directory release, which is allowed because nothing has been published to the directory yet. |
| 16 | Complete, functional plugin at submission | ✅ All seven work standalone. The six addons declare `Requires Plugins: baukasten`; two of them name a foreign plugin as well — Business Cards requires `contact-form-7`, Two-Factor Approval requires `two-factor`. Both are in the directory, which is what that header can address. |
| 17 | Respect trademarks | ✅ "Baukasten" is the author's own name. No third-party project name is used as, or at the start of, a slug. The addon slugs begin with the author's own plugin name, which is the pattern the guideline asks for. |
| 18 | WordPress may change the guidelines | — |

## Plugin headers

Present and correct in all seven: `Plugin Name`, `Plugin URI`, `Description`, `Version`, `Requires at least`, `Tested up to`, `Requires PHP`, `Author`, `Author URI`, `License`, `License URI`, `Text Domain`, `Domain Path`. The six addons additionally carry `Requires Plugins` (WordPress 6.5+), so WordPress itself refuses to activate them without what they need: `baukasten` for all of them, plus `contact-form-7` for Business Cards and `two-factor` for Two-Factor Approval.

Each text domain equals the plugin's intended directory slug, which is what translate.wordpress.org requires — and the reason the slug correction at submission is not cosmetic.

Plugin names stay in English in every locale — they are proper nouns, and a German site shows **Baukasten - Privacy Toolkit** like everyone else. The `Description` headers are translated, so the Plugins screen still reads in the dashboard language.

## readme.txt

All seven validate against the standard: `=== Name ===` heading, `Contributors`, `Tags` (5), `Requires at least`, `Tested up to`, `Requires PHP`, `Stable tag`, `License`, `License URI`, `Requires Plugins` where applicable, a short description under 150 characters, then Description, Installation, FAQ, Changelog and Upgrade Notice. All seven now carry a Screenshots section, and the files those captions point at are the one thing still outstanding below. Written in English, as the plugin team has required since July 2025.

Run them through <https://wordpress.org/plugins/developers/readme-validator/> before submitting.

## Security review

| Area | Finding |
|---|---|
| Capability checks | Every write is gated: `manage_baukasten` for the core screen, the Consent tab and the Two-Factor tab, `manage_options` for Content Visibility, Multi-Domain and Business Cards, `manage_privacy_options` for Login & Legal — the same capability core requires for the privacy picker next door. |
| Nonces | Every `admin_post_*` handler calls `check_admin_referer()`; the Content Visibility Ajax endpoint calls `check_ajax_referer()`. |
| Escaping | `esc_html()`, `esc_attr()`, `esc_url()` and `wp_kses()` on output throughout; PHPCS with WordPress Coding Standards passes with zero findings on all seven. |
| Sanitising | `sanitize_key()`, `absint()`, `sanitize_text_field()`, `sanitize_title()` on input, with whitelists where a fixed set exists. |
| SQL | The one custom table is written through `$wpdb->insert()` and read through `$wpdb->prepare()`. The hardening routine's single site-wide `UPDATE` takes no user input at all. |
| Public endpoints | `POST /baukasten-consent/v1/consent` is deliberately reachable without a login — anonymous visitors have to be able to consent. It is guarded by the `wp_rest` nonce, a whitelist of registered categories, and a rate limit keyed by a hash of the client address. The address itself is never stored. |
| Data at rest | The consent log contains no IP address, no user id, no user agent — only a random id from the visitor's own cookie. |
| Uninstall | Each plugin ships an `uninstall.php` that removes its own options, meta and table, multisite included. The one exception is documented rather than hidden: the Consent Engine's hardening routine changes WordPress settings and posts, and uninstalling does not put those back. |

## Automated checks

```bash
# per plugin, from its directory
vendor/bin/phpcs                     # WordPress Coding Standards + PHPCompatibilityWP

# from the repository root
php -l <every file>                  # also runs in CI on PHP 8.0 to 8.4
php -d extension=zip bin/build.php --all
```

CI (`.github/workflows/lint.yml`) runs PHPCS per plugin and `php -l` across the whole repository on PHP 8.0, 8.1, 8.2, 8.3 and 8.4.

## Before submitting

1. **Screenshots.** `.wordpress-org/assets/` holds a generated icon and banner for each of the seven, but none of them has screenshots. They have to be taken from a real install and dropped in beside the icon; all seven `readme.txt` files list captions that need those files. The stale ones showing the old module screen were deleted rather than shipped.
2. **Confirm the slugs are free.** All seven returned a 404 from the plugin information API on 14 September 2026, meaning they are unused, but that can change at any time. Check again immediately before submitting.
3. **Correct the slug after uploading.** Not optional and not reversible — see [RELEASING.md](RELEASING.md). Submit `baukasten` first and let it be approved, because the other six name it in `Requires Plugins`.
4. **Decide a fallback slug for the core.** `baukasten` is a single common word and the plugins team may object to it. Better to have an answer ready than to improvise mid-review.
5. ~~The `Plugin URI` points at a private repository.~~ Resolved: the repository is public, so the `https://github.com/trstnbde/baukasten` in all seven headers resolves for everyone.

Items 1, 2 and 4 are release logistics. Item 3 is the one that cannot be undone.
