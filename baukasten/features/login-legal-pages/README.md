# Login Legal Pages

Privacy policy, terms and imprint under the login form; no WordPress header on the screen; a login address people can read.

Part of [Baukasten - Privacy Toolkit](../../README.md), in `features/login-legal-pages/`. Until the merge it was the separate plugin `baukasten-login-legal-pages`; namespace, options and hooks are unchanged.

## What it does

**Legal links under the form.** Privacy policy, terms of service and imprint, in that order, rendered through the `the_privacy_policy_link` filter so they land in the wrapper core already prints under the form rather than in a second block at the bottom of the page. A page that does not exist or is not published is left out — a dead link on the login screen is the first thing every user of the site sees.

**One card.** Core prints the form, its login navigation (`<p id="nav">`, the "Lost your password?" link) and the legal links as three separate siblings inside the `#login` column. They are styled as a single card: white form on top, a recessed `#f6f7f7` footer under it with a divider and 8px rounded corners. No DOM surgery and no JavaScript — three stacked blocks with the right borders are a card. Each block is pulled up one pixel so its own background covers the border above it, because CSS has no selector for "an element followed by another" short of `:has()`.

The column keeps core's 320px. All four links share one treatment — core's own colour and size for "Lost your password?" — and the legal row wraps when the labels do not fit, with the separator on the trailing item so a wrapped line ends with a pipe rather than starting with one.

**No WordPress header.** `login_header()` prints `<h1 role="presentation" class="wp-login-logo">` and `login_footer()` prints `<p id="backtoblog">`. Neither sits behind a filter, so both are hidden with CSS, scoped to `#login` so core's `<h1 class="screen-reader-text">` above it survives. Their text is emptied first through `login_headertext` and `login_site_html_link`, so no WordPress branding reaches the markup at all.

**`/login/` instead of `/wp-login.php`.** Rewrite rules always resolve to `index.php`, so they cannot point at a file. Instead the request is taken over: `$pagenow` is corrected on `plugins_loaded`, and `wp-login.php` is included on `wp_loaded` — the last hook before the query is parsed, and the same point in the bootstrap `wp-login.php` reaches on its own. Every login URL is rewritten through the `site_url` filter for the `login` and `login_post` schemes, which is where `wp_login_url()`, `wp_logout_url()`, `wp_lostpassword_url()`, `wp_registration_url()` and every form action come from.

`wp-login.php` keeps working and redirects. **The address is not a security feature** — nothing is blocked by it, and nobody can be locked out by it. The rate limit below is one.

A pleasant side effect: WordPress's own `wp_redirect_admin_locations()` sends `/login/`, `/dashboard/` and `/admin/` to `wp_login_url()`, which this plugin has already rewritten — so those shortcuts keep working whatever slug you pick.

## Rate limit

`Rate_Limit` counts failed sign-ins per IP address and refuses every attempt from a locked address, the correct password included. It registers at include time, next to `Login_URL`.

| Part | Hook | Behaviour |
|---|---|---|
| Count | `wp_login_failed`, `application_password_failed_authentication` | transient `baukasten_login_rl_<hash>`, window from the latest failure |
| Lock | `authenticate` at priority 100 | `WP_Error` with status 429 while locked. 100 because `wp_authenticate_username_password()` on 20 ignores an earlier error and returns the user when the password matches |
| Lock application passwords | `wp_authenticate_application_password_errors` | they are checked by calling the function directly, past `authenticate` |
| Reset | `wp_login` | the failure counter of the address goes; the strike count stays |
| Password resets | `lostpassword_post` | three per address and hour |

Five failures within fifteen minutes lock the address for fifteen minutes; every further lockout within 24 hours doubles that, up to a day (`baukasten_login_lock_<hash>`, `baukasten_login_strikes_<hash>`). Threshold, window and first lockout are settings, in `baukasten_login_rate_limit`.

- Per address only, never per user name, or anybody could lock the administrator out by typing their name.
- The message is the same whether the user name exists or not.
- The address is `REMOTE_ADDR`. Behind a reverse proxy you control, `baukasten/login/client_ip` may return the forwarded one — never trust `X-Forwarded-For` from anybody.
- The address is hashed with `wp_hash()` and only ever part of a transient name; expired transients are removed by core's daily cleanup.
- No `sleep()`: it would tie up a PHP-FPM worker.
- **Two Factor 0.16.0** fires `wp_login_failed` for a wrong second-factor code (`Two_Factor_Core::_login_form_validate_2fa()` and `_login_form_revalidate_2fa()`), so failed codes count towards the same limit — on top of Two Factor's own per-user delay.

`wp-login.php` visits with nothing submitted still run the authentication chain; an empty user name and password are passed through untouched.

## Settings

**Settings → Baukasten → Login & Legal**: terms page, imprint page, login slug, and the rate limit. The privacy policy stays where WordPress keeps it, on Settings → Privacy, and the tab links there.

The tab warns when a selected page is not reachable for logged-out visitors — it asks Content Visibility — and when a published page already uses the login slug.

## Filters

| Filter | Purpose |
|---|---|
| `baukasten/login_legal_pages/login_slug` | The path the login screen answers on |
| `baukasten/login_legal_pages/links` | The assembled list of legal links |
| `baukasten/login_legal_pages/terms_url` | The terms URL |
| `baukasten/login_legal_pages/imprint_url` | The imprint URL |
| `baukasten/login_legal_pages/page_is_public` | Whether a selected page is reachable anonymously |
| `baukasten/login/client_ip` | The address the rate limit counts against |
