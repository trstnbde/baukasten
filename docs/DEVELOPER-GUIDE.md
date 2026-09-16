# Developer guide

For whoever opens this repository next. It covers how the pieces fit together, the conventions to follow, and the handful of things that cost real time to work out the first time.

Everything here describes v1.0.0.

---

## 1. What this is

Seven WordPress plugins in one repository, published separately to the plugin directory:

| Slug | Role |
|---|---|
| `baukasten` | The core. A tabbed settings screen and a capability. |
| `baukasten-content-visibility` | Public/private switch on all content. |
| `baukasten-login-legal-pages` | Legal links on the login screen, `/login/` URL. |
| `baukasten-consent-blocking-engine` | Blocks third parties until consent; site hardening. |
| `baukasten-multi-domain` | A domain per page. |
| `baukasten-business-cards` | A digital business card on its own short link, in one of three designs. |
| `baukasten-2fa` | A second factor answered from the admin bar of another signed-in session. Also requires the Two Factor plugin. |

They are separate plugins because the features are wanted in different combinations and update on different schedules. They live in one repository because they share conventions, tooling and a release.

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

Tab positions in use: 10 Content Visibility, 20 Login Legal Pages, 30 Consent Blocking Engine, 40 Multi-Domain, 50 Business Cards.

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
| Text domain | equal to the slug — translate.wordpress.org requires it |
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

### The local test setup

XAMPP at `D:\xampp\htdocs`, WordPress 7.1, all seven plugins linked in as **directory junctions** so edits are live:

```powershell
New-Item -ItemType Junction -Path "D:\xampp\htdocs\wp-content\plugins\<slug>" -Target "D:\Code\Baukasten\<slug>"
```

Two things that bite:

- The XAMPP PHP CLI (`D:\xampp\php\php.exe`, 8.2) has **`ext-zip` and `ext-gd` disabled**, hence the `-d extension=…` above. A bare `php` on PATH is a different 8.3 install.
- WP-CLI is installed but not on the Bash PATH; drive it from PowerShell, and prepend `D:\xampp\mysql\bin` for `wp db`. Prefer `wp eval-file` over `wp eval` — PowerShell mangles inline PHP, and `!` and `%` inside a `wp db query` are eaten by cmd, which silently changes your SQL.

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

**`.distignore` matches path segments, not just paths.** `bin/build.php`'s `is_ignored()` tests every pattern against the full relative path *and* against each segment of it, so the `vendor` line meant for Composer also excludes `assets/js/vendor/anything`. The file vanishes from the release archive and from nowhere else — the plugin keeps working locally, and the ZIP ships a button that does nothing. Business Cards keeps its bundled QR encoder in `assets/js/lib/` for exactly this reason.

**A meta box that is not rendered still runs through the save loop.** A save posts nothing for a box that was hidden, and "posted nothing" is indistinguishable from "was cleared" — which for an unticked checkbox is true and for a whole hidden box is not. Business Cards hides its legal box when the Login Legal Pages addon supplies the links, and has to skip those fields in `Meta_Boxes::save()` or the next save of any unrelated field throws them away.

**A `.pkpass` needs `upload_mimes` and nothing else.** It is a ZIP, `finfo` reports `application/zip`, and that is in core's `$nonspecific_types` (`wp-includes/functions.php:3234`) — the branch it takes only requires the *declared* type's major part to be `application`. A `wp_check_filetype_and_ext` filter on top would only widen what gets past core's check.

**PHPCS's PrefixAllGlobals sniff treats view locals as globals.** Prefix them, or the build fails on a file that looks perfectly ordinary.

---

## 6. Where things are

```
baukasten*/                 the seven plugins
bin/build.php               packaging, one plugin or --all
bin/make-assets.php         directory icon and banner
docs/DEVELOPER-GUIDE.md     this file
docs/WORDPRESS-ORG-COMPLIANCE.md   guideline-by-guideline readiness, and what is left before submitting
docs/RELEASING.md           what ships together, and the one-shot slug step at first submission
.github/workflows/lint.yml  PHPCS per plugin, php -l on 8.0 to 8.4
```

Each plugin also carries its own `README.md` (how it works, and its known gaps) and `readme.txt` (what the directory shows).

---

## 7. Releasing

Nothing has been published yet; all seven are at 1.0.0.

The procedure lives in [`RELEASING.md`](RELEASING.md): which plugins ship together, and the one
irreversible step at first submission. **WordPress.org derives the directory slug from the
`Plugin Name` header, and it can only be corrected once, before a reviewer picks the submission
up.** Left alone every one of our seven would land under the wrong slug, and the slug is also the
folder name and the text domain.

`docs/WORDPRESS-ORG-COMPLIANCE.md` lists what is still outstanding.
