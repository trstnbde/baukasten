# Baukasten Addon: Login Legal Pages

Privacy policy, terms and imprint under the login form; no WordPress header on the screen; a login address people can read.

An addon for [Baukasten - Privacy Toolkit](../baukasten).

| | |
|---|---|
| Requires WordPress | 6.5 |
| Requires PHP | 8.0 |
| Requires plugin | `baukasten` |
| License | GPLv2 or later |

## What it does

**Legal links under the form.** Privacy policy, terms of service and imprint, in that order, rendered through the `the_privacy_policy_link` filter so they land in the wrapper core already prints under the form rather than in a second block at the bottom of the page. A page that does not exist or is not published is left out — a dead link on the login screen is the first thing every user of the site sees.

**One card.** Core prints the form, its login navigation (`<p id="nav">`, the "Lost your password?" link) and the legal links as three separate siblings inside the `#login` column. They are styled as a single card: white form on top, a recessed `#f6f7f7` footer under it with a divider and 8px rounded corners. No DOM surgery and no JavaScript — three stacked blocks with the right borders are a card. Each block is pulled up one pixel so its own background covers the border above it, because CSS has no selector for "an element followed by another" short of `:has()`.

The column keeps core's 320px. All four links share one treatment — core's own colour and size for "Lost your password?" — and the legal row wraps when the labels do not fit, with the separator on the trailing item so a wrapped line ends with a pipe rather than starting with one.

**No WordPress header.** `login_header()` prints `<h1 role="presentation" class="wp-login-logo">` and `login_footer()` prints `<p id="backtoblog">`. Neither sits behind a filter, so both are hidden with CSS, scoped to `#login` so core's `<h1 class="screen-reader-text">` above it survives. Their text is emptied first through `login_headertext` and `login_site_html_link`, so no WordPress branding reaches the markup at all.

**`/login/` instead of `/wp-login.php`.** Rewrite rules always resolve to `index.php`, so they cannot point at a file. Instead the request is taken over: `$pagenow` is corrected on `plugins_loaded`, and `wp-login.php` is included on `wp_loaded` — the last hook before the query is parsed, and the same point in the bootstrap `wp-login.php` reaches on its own. Every login URL is rewritten through the `site_url` filter for the `login` and `login_post` schemes, which is where `wp_login_url()`, `wp_logout_url()`, `wp_lostpassword_url()`, `wp_registration_url()` and every form action come from.

`wp-login.php` keeps working and redirects. **This is not a security feature.** Nothing is blocked, and nobody can be locked out.

A pleasant side effect: WordPress's own `wp_redirect_admin_locations()` sends `/login/`, `/dashboard/` and `/admin/` to `wp_login_url()`, which this plugin has already rewritten — so those shortcuts keep working whatever slug you pick.

## Settings

**Settings → Baukasten → Login & Legal**: terms page, imprint page, login slug. The privacy policy stays where WordPress keeps it, on Settings → Privacy, and the tab links there.

The tab warns when a selected page is not reachable for logged-out visitors — it asks the Content Visibility addon, if that one is installed — and when a published page already uses the login slug.

## Filters

| Filter | Purpose |
|---|---|
| `baukasten/login_legal_pages/login_slug` | The path the login screen answers on |
| `baukasten/login_legal_pages/links` | The assembled list of legal links |
| `baukasten/login_legal_pages/terms_url` | The terms URL |
| `baukasten/login_legal_pages/imprint_url` | The imprint URL |
| `baukasten/login_legal_pages/page_is_public` | Whether a selected page is reachable anonymously |

## Development

```bash
composer install
composer lint
```

## License

GPLv2 or later. See [LICENSE](LICENSE).
