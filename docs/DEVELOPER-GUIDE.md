# Developer guide

For whoever opens this repository next. It covers how the pieces fit together, the conventions to follow, and the handful of things that cost real time to work out the first time.

Everything here describes 1.0.0 as of 28 September 2026, after the core merge.

---

## 1. What this is

Five WordPress plugins in one repository, each with its own release:

| Slug | Role |
|---|---|
| `baukasten` | The core: a tabbed settings screen, a capability, and three built-in features in `features/` — Content Visibility (public/private switch on all content), Login Legal Pages (legal links on the login screen, `/login/` URL, rate limit) and the Consent Blocking Engine (blocks third parties until consent; site hardening; automatic updates). |
| `baukasten-multi-domain` | A domain per page. |
| `baukasten-business-cards` | A digital business card on its own short link, in one of three designs. Also requires Contact Form 7. |
| `baukasten-2fa` | A second factor answered from the admin bar of another signed-in session. Also requires the Two Factor plugin. |
| `baukasten-form-privacy` | Contact Form 7 and Flamingo without the leaks. Also requires Contact Form 7. |

The addons are separate plugins because they are wanted in different combinations and update on different schedules. They live in one repository because they share conventions, tooling and a settings screen.

**The core used to be four plugins.** Content Visibility, Login Legal Pages and the Consent Blocking Engine were addons until September 2026, when they were merged into the core — none of them could run without it, so they were one decision spread over four installs. Their code moved unchanged into `baukasten/features/<slug>/` and kept its namespace (`Baukasten\ContentVisibility` and so on), options, tables, REST route, tab ids and hooks. Each has a `bootstrap.php` where its main file used to be, with `install()` instead of an activation hook; `Baukasten\Features` loads them and `Baukasten\Installer` installs them. All three are always on. Their developer notes are in `features/<slug>/README.md`.

**There was an earlier architecture.** Before v1.0.0, Baukasten was a container plugin: "modules" were ZIP archives uploaded through the dashboard into `wp-content/uploads/baukasten-modules/`, managed by a scanner, a registry, an uploader and a fatal error guard the plugin implemented itself. All of that is gone — WordPress already does it for real plugins, and does it better. If you meet the word "module" in an old note anywhere, it means "plugin", and the mechanism it describes no longer exists.

---

## 2. The addon contract

Two things make a plugin a Baukasten addon.

**Declare the dependency**, so WordPress refuses to activate it without the core:

```
Requires Plugins: baukasten
```

**Register a tab** on `baukasten/register_addons`:

```php
add_action( 'baukasten/register_addons', static function (): void {
	Baukasten\Addons::register( array(
		'id'          => 'my-addon',                    // tab slug
		'title'       => __( 'My Addon', 'my-addon' ),  // tab label
		'plugin_file' => MY_ADDON_FILE,                 // version and description on the overview
		'capability'  => 'manage_options',              // optional, can only narrow access
		'position'    => 60,                            // optional, sort order
		'render'      => 'my_addon_render_tab',         // prints the tab
	) );
} );
```

Tab positions in use: 10 Content Visibility, 20 Login Legal Pages, 30 Consent Blocking Engine (all three built into the core), 40 Multi-Domain, 50 Business Cards, 60 Two-Factor Approval, 70 Form Privacy. A new addon starts at 80.

Every addon header also carries `Update URI: https://github.com/trstnbde/baukasten` while the plugins are not in the WordPress.org directory. Without it WordPress asks wordpress.org for updates to our slugs, and whoever registers one of them first would be offered to every site running ours.

Nothing is inherited and nothing is implemented. Guard the call with `class_exists( 'Baukasten\Addons' )` and the addon still runs without the core — it just has nowhere to put its settings, which is what the "core plugin missing" notices in each addon are for.

The action fires **on `init` at priority 20**, not on `plugins_loaded`. That is deliberate: a tab title is a translated string, and translating before `init` trips the "translation loading was triggered too early" notice WordPress 6.7 added.

### Saving a tab's form

Tabs render inside the core's page, so they post to `admin-post.php` and come back through the core's helpers:

```php
add_action( 'admin_post_my_addon_save', static function (): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'my-addon' ), 403 );
	}

	check_admin_referer( 'my_addon_save' );

	// … sanitise and save …

	Baukasten\Admin::redirect_to_tab( 'my-addon', 'success', __( 'Settings saved.', 'my-addon' ) );
} );
```

`Baukasten\Admin` also gives you `page_url( $tab )` and `add_notice( $type, $message )`.

**Call `check_admin_referer()` in the handler itself**, not in a shared helper. PHPCS cannot see into a helper and will flag every `$_POST` read as unverified — and it is right to, because a reader cannot see it either.

---

## 3. Conventions

| | |
|---|---|
| Directory and slug | `baukasten-<feature>` |
| Main file | `<slug>/<slug>.php` — `bin/build.php` relies on this |
| Class files | `includes/class-thing-name.php` for `Thing_Name`, WordPress style, not PSR-4 |
| Views | `admin/views/*.php`, `require`d from a class, variables documented in the file docblock |
| Namespace | `Baukasten\FeatureName` |
| Text domain | equal to the slug — translate.wordpress.org requires it. The built-in features use `baukasten` |
| Hook prefix | `baukasten/` with slashes (PHPCS is configured to allow it) |
| Options | `baukasten_<feature>_<thing>` |
| View variables | `$baukasten_<abbrev>_*`, because PHPCS's PrefixAllGlobals sniff treats a view's locals as globals |
| Autoloading | plain `require_once` at the top of the main file; Composer maps `includes/` as a classmap, and no `vendor/` ships |
| Constants | `PLUGIN_DIR`, `PLUGIN_URL`, `PLUGIN_FILE`, `VERSION`, `TEXTDOMAIN` in the plugin's namespace |

Translations load on `init` priority 1, through `load_plugin_textdomain()`. Every plugin ships `languages/<slug>.pot` plus a German `.po`/`.mo`; regenerate with WP-CLI, never by hand.

Plugin **names stay in English in every locale** — they are proper nouns. Descriptions are translated. The core's German `.po` carries the name with an identical msgstr and a translator comment saying so, to stop it being helpfully translated later.

---

## 4. Tooling

```bash
# per plugin, from its directory
composer install
composer lint                                  # PHPCS: WordPress Coding Standards + PHPCompatibilityWP
composer lint:fix                              # PHPCBF

# from the repository root
php -d extension=zip bin/build.php --all       # build/<slug>-<version>.zip for each plugin
php -d extension=gd  bin/make-assets.php <slug> # .wordpress-org/assets icon and banner
```

`bin/build.php` refuses to build when a plugin's header version and its `readme.txt` stable tag disagree. It copies everything not listed in that plugin's `.distignore`.

Translations:

```bash
wp i18n make-pot <dir> <dir>/languages/<slug>.pot --slug=<slug> --domain=<slug>
wp i18n make-mo  <dir>/languages <dir>/languages
```

Only `baukasten/` has a `vendor/`; the other four are linted with `../baukasten/vendor/bin/phpcs`.

`bin/po-merge.py` builds a translated `.po` from a fresh `.pot` and existing translations — used when the three features' German moved into `baukasten-de_DE.po`, and for filling in new strings from a JSON list:

```bash
python bin/po-merge.py <slug>/languages/<slug>.pot out.po <slug>/languages/<slug>-de_DE.po --extra new.json --report missing.json
```

On the development machine the PHP CLI used for tooling (`D:\xampp\php\php.exe`, 8.2) has **`ext-zip` and `ext-gd` disabled**, hence the `-d extension=…` above; it is only a CLI there, not a test server.

### Testing

There is no local test site any more. Changes are tested on the live site, https://bundeshub.de/ (Hetzner, PHP 8.4), which runs all five plugins:

- **Automated:** `tests/` holds PHPUnit integration tests that run in CI against the WordPress test suite in `wp-env`. They cover what must never regress: private content through embed, oEmbed and REST; forged consent; the card mail recipient; activation leaving core options alone; the rate limit; Form Privacy's filters.
- **Deploy:** by SSH (`ssh -p 222 cwyhqy@www673.your-server.de`, key authentication, WP-CLI as `~/bin/wp`). Before every deployment, a database export and a tarball of `wp-content/plugins` go to `~/baukasten-backups/<date>/`, outside the web root. Then `wp plugin install <zip> --force` per plugin.
- **Logged in:** in the site owner's Chrome session, which already exists. Never sign out or in: the login asks for Two Factor.
- **Logged out:** `curl`, or a browser without the session.
- **Dangerous on a live site:** anything that sends mail to a third party, deletes data, or could lock the owner out (the rate limit — test it with `wp eval-file` and a documentation IP address, not through the login form). Ask first.
- **An old addon folder** (`baukasten-content-visibility` and the other two) must never be deleted through the Plugins screen: its `uninstall.php` deletes the data the core's feature now uses. Move or remove it on the file system.

Protocols of what was checked go to `docs/acceptance/<topic>.md`. Raw data from the site — Site Health exports, headers, inventories — stays in `docs/acceptance/raw/`, which is gitignored: this repository is public.

---

## 5. Traps

Every one of these cost time. They are listed so they cost it once.

**`script_loader_tag` is not handed one tag.** `WP_Scripts::do_item()` glues the translations, the `before` inline script, the `<script src>` and the `after` inline script into one string and filters that. Rewriting only the first `<script` in it disarms the tracker's *configuration* and leaves the tracker running — exactly backwards. See `Blocking_Scripts::disarm_script_tag()`.

**`oembed_result` runs before the cache, `embed_oembed_html` after it.** WordPress stores the return value of the first in post meta. Filtering there bakes your markup into that cache with the wording and settings of the day it was fetched, and the second filter then wraps it again. Wrap at output time only.

**Including `wp-login.php` from inside a method makes its variables local.** It is written to run at the top of a request, where they are globals, and `login_header()` reads `$error`, `$interim_login` and `$action` through `global`. Declare them before the include or the login screen loses its error box and its body class. See `Login_URL::serve()`.

**A rewrite rule cannot point at a file.** WordPress rewrites always resolve to `index.php`, so `/login/` is served by taking the request over on `wp_loaded` — the last hook before the query is parsed, and the same point in the bootstrap `wp-login.php` reaches on its own.

**`WP_CONTENT_URL` is defined before plugins load.** It comes from `siteurl` in `wp-settings.php`, so no filter reaches it, and a plugin that stores `plugin_dir_url( __FILE__ )` in a constant at include time is past even `plugins_url`. If you need asset URLs to follow something, filter `content_url`, `plugins_url`, `includes_url` *and* `script_loader_src` / `style_loader_src`. See `Domain_Router`.

**`#nav` and `.privacy-policy-page-link` are siblings on the login screen**, not a shared container, and core offers no filter to remove the logo `<h1>` or the "Go to site" link. The login card is three stacked blocks with matching borders and a one pixel overlap. There is no selector for "an element followed by another" short of `:has()`.

**A post slug is looked up lowercased.** `WP_Query::parse_query()` runs the requested name through `sanitize_title_for_query()`, which calls `strtolower()` unconditionally. A generated slug with capitals in it is stored as typed and searched for in lower case, so the post 404s while the admin list shows a permalink pointing straight at it. Base36, not base62. See `Slug::assign()`.

**`wp_unique_post_slug()` runs before `wp_insert_post_data`, not after.** Which is what makes generating a slug in that filter work at all: nothing downstream can append a `-2`, and because the filter always returns a non-empty `post_name` the title-derived fallback later in `wp_insert_post()` never fires either.

**Only the post type's own `rewrite` argument gives you working permalinks.** `get_permalink()` reads `WP_Rewrite::get_extra_permastruct()`, and only `WP_Post_Type::add_rewrite_rules()` ever fills that in. `'rewrite' => false` plus a hand-written `add_rewrite_rule()` serves the pretty URL and then has `redirect_canonical()` bounce it back to the ugly one, because that is what `get_permalink()` still returns.

**Removing `wp_enqueue_global_styles` leaves the Customizer CSS behind.** On a block theme that function unhooks `wp_custom_css_cb` itself, as a side effect. Take the function out to detach a theme and nothing unhooks it any more, so the Customizer CSS prints onto a page that has nothing else of the theme left on it. See `Renderer::strip_head()`.

**Translating before `init` trips a 6.7 notice.** Anything that produces a translated string — including a tab title — has to run on `init` or later.

**An old addon and its merged feature cannot both load.** WordPress includes active plugins in the sorted order of their paths, and `-` sorts before `/`, so `baukasten-consent-blocking-engine/…` is included before `baukasten/baukasten.php`. If the old plugin is still active when the new core arrives, every class of the feature already exists. `Baukasten\Features::load()` skips a feature whose old `PLUGIN_FILE` constant is defined, deactivates the old plugin on the next admin request, and only then records the structure version.

**A version number that does not change cannot trigger an upgrade.** The merge shipped as 1.0.0, like everything before it, so `Installer::maybe_upgrade()` never noticed it. `baukasten_structure` is a second version, for the shape of the plugin, checked on every `wp_loaded` — not `plugins_loaded`: Content Visibility's migration asks which post types exist, and before `init` there are none, so it would migrate nothing and cache an empty list of managed types.

**Contact Form 7 registers its handles on `wp_enqueue_scripts`.** A block theme renders the whole template — shortcodes included — before `wp_head`, so `wpcf7_shortcode_callback` fires before the handles exist and `wpcf7_enqueue_scripts()` from there is silently ignored. Take note in the callback and enqueue on `wp_enqueue_scripts` (Form Privacy's `Assets`).

**`wp_json_encode()` inside a double-quoted attribute ends the attribute.** `onsubmit="return window.confirm( <?php echo wp_json_encode( … ); ?> );"` prints `"…"` inside `"…"`, the attribute stops at the first quote, the handler is a syntax error, and the form submits without asking. The hardening button shipped like that. Wrap it in `esc_attr()`.

**`rest_endpoints` runs before authentication.** To hide a route from logged-out visitors, check `is_user_logged_in()` on `rest_pre_dispatch`, which runs after `rest_cookie_check_errors()` has reset a cookie without a nonce to user 0.

**`.distignore` matches path segments, not just paths.** `bin/build.php`'s `is_ignored()` tests every pattern against the full relative path *and* against each segment of it, so the `vendor` line meant for Composer also excludes `assets/js/vendor/anything`. The file vanishes from the release archive and from nowhere else — the plugin keeps working locally, and the ZIP ships a button that does nothing. Business Cards keeps its bundled QR encoder in `assets/js/lib/` for exactly this reason.

**A meta box that is not rendered still runs through the save loop.** A save posts nothing for a box that was hidden, and "posted nothing" is indistinguishable from "was cleared" — which for an unticked checkbox is true and for a whole hidden box is not. Business Cards hides its legal box when the Login Legal Pages addon supplies the links, and has to skip those fields in `Meta_Boxes::save()` or the next save of any unrelated field throws them away.

**A `.pkpass` needs `upload_mimes` and nothing else.** It is a ZIP, `finfo` reports `application/zip`, and that is in core's `$nonspecific_types` (`wp-includes/functions.php:3234`) — the branch it takes only requires the *declared* type's major part to be `application`. A `wp_check_filetype_and_ext` filter on top would only widen what gets past core's check.

**PHPCS's PrefixAllGlobals sniff treats view locals as globals.** Prefix them, or the build fails on a file that looks perfectly ordinary.

---

## 6. Where things are

```
baukasten*/                 the five plugins
baukasten/features/         Content Visibility, Login Legal Pages, Consent Blocking Engine
bin/build.php               packaging, one plugin or --all
bin/make-assets.php         directory icon and banner
bin/po-merge.py             carries translations into a fresh .pot
tests/                      PHPUnit integration tests, run in wp-env
docs/DEVELOPER-GUIDE.md     this file
docs/RELEASING.md           tags and releases, and the one-shot slug step at a later first submission
docs/WORDPRESS-ORG-COMPLIANCE.md   guideline-by-guideline readiness (submission on hold)
docs/briefings/             the September 2026 briefings and what came of them
docs/acceptance/            protocols from the live site
.github/workflows/          lint.yml (PHPCS, php -l), integration.yml (tests), release.yml (ZIPs per tag)
```

Each plugin also carries its own `README.md` (how it works, and its known gaps) and `readme.txt` (what the directory shows).

---

## 7. Releasing

All five are at 1.0.0 and released on GitHub; publishing to WordPress.org is on hold.

The procedure lives in [`RELEASING.md`](RELEASING.md). For the day the directory submission is
taken up again it also holds the one irreversible step: **WordPress.org derives the directory slug
from the `Plugin Name` header, and it can only be corrected once, before a reviewer picks the
submission up.** Left alone every one of our five would land under the wrong slug, and the slug is
also the folder name and the text domain. The `Update URI` headers have to go before submitting.

`docs/WORDPRESS-ORG-COMPLIANCE.md` lists what is still outstanding for that.
