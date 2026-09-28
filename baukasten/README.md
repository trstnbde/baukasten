# Baukasten - Privacy Toolkit

A privacy toolkit for WordPress in one plugin: consent-based blocking of third parties with an auditable log, site hardening, a public/private switch on all content, and legal links on a tidy, rate-limited login screen. Everything is configured under *Settings → Baukasten*, and further addons add their own tabs to the same screen.

[![Lint](https://github.com/trstnbde/baukasten/actions/workflows/lint.yml/badge.svg)](https://github.com/trstnbde/baukasten/actions/workflows/lint.yml)

| | |
|---|---|
| Requires WordPress | 6.5 |
| Tested up to | 7.1 |
| Requires PHP | 8.1 |
| License | GPLv2 or later |

## What is built in

| Feature | Tab | What it does |
|---|---|---|
| [Content Visibility](features/content-visibility/README.md) | 10 | A public/private switch on every post, page and public custom post type. Private content is readable by logged-in users only, regardless of role. |
| [Login Legal Pages](features/login-legal-pages/README.md) | 20 | Terms and imprint page pickers, all three legal links on the login screen, a `/login/` URL, and a rate limit on failed sign-ins. |
| [Consent Blocking Engine](features/consent-blocking-engine/README.md) | 30 | Blocks non-essential scripts, styles, embeds, resource hints, Gravatar and emoji until the visitor consents, keeps an auditable consent log, and hardens the site — including automatic updates for every plugin and theme. |

All three are always on. They were separate plugins until they were merged into this one: whoever wants the toolkit wants all of it, and three plugins that could not run without a fourth were four things to install and update for one decision. Each still lives in its own directory under `features/` with its own namespace, options and hooks, so nothing about a site's data changed.

`includes/class-features.php` loads them, and handles a site that still has one of the old plugins: WordPress loads `baukasten-consent-blocking-engine/…` before `baukasten/baukasten.php`, so if an old plugin is still active, its feature is skipped for that request instead of declaring every class twice. The next admin request deactivates the old plugin silently and asks for its folder to be removed by hand — **never through the Plugins screen**, because the old plugin's own `uninstall.php` would delete the data the feature now uses. The option `baukasten_structure` records that the features were installed; it replaces a version bump, which the merge did not get.

## The addons

Separate plugins that declare `Requires Plugins: baukasten` and add a tab:

| Plugin | Tab | What it does |
|---|---|---|
| [`baukasten-multi-domain`](../baukasten-multi-domain) | 40 | A domain per page. |
| [`baukasten-business-cards`](../baukasten-business-cards) | 50 | A digital business card on its own short link. |
| [`baukasten-2fa`](../baukasten-2fa) | 60 | A second factor approved from another signed-in session. |
| [`baukasten-form-privacy`](../baukasten-form-privacy) | 70 | Contact Form 7 and Flamingo without the leaks. |

## What the core provides

- **A tabbed settings screen** under *Settings → Baukasten*. One tab per feature and per active addon, plus an overview listing the built-in features and every addon there is — active, installed but switched off, or not installed at all, each with the one link that moves it along. Deactivate an addon and its tab disappears with it.
- **A hard-coded catalog** in `includes/class-catalog.php`. It is what lets the overview name an addon the site has not got. Adding an addon to the family means adding a row there; nothing is fetched from the plugin directory at runtime.
- **The `manage_baukasten` capability**, granted to administrators on activation. Not tied to `manage_options`, so it can be delegated. Addons may additionally require a capability that fits their data.
- **`Baukasten\Admin` helpers** addons use for their own forms: `page_url()`, `add_notice()` and `redirect_to_tab()`.

## Installation

Download `baukasten-<version>.zip` from the [releases](https://github.com/trstnbde/baukasten/releases) and upload it under *Plugins → Add Plugin → Upload Plugin*. The plugin is not in the WordPress.org directory yet; its `Update URI` header tells WordPress not to look for it there.

The plugin ships its own autoloader and runs without `composer install`. Composer is only needed for the linting tools.

## Writing an addon

An addon is an ordinary WordPress plugin. Two things make it a Baukasten addon.

**1. Declare the dependency** in the plugin header, so WordPress refuses to activate it without the core:

```
Requires Plugins: baukasten
```

**2. Register a tab** on `baukasten/register_addons`:

```php
add_action( 'baukasten/register_addons', static function (): void {
	Baukasten\Addons::register( array(
		'id'          => 'my-addon',                       // tab slug
		'title'       => __( 'My Addon', 'my-addon' ),     // tab label
		'plugin_file' => MY_ADDON_FILE,                    // for version and description
		'capability'  => 'manage_options',                 // optional, narrows access
		'position'    => 20,                               // optional, sort order
		'render'      => 'my_addon_render_tab',            // prints the tab
	) );
} );
```

Nothing is inherited and nothing is implemented. Guard the call with `class_exists( 'Baukasten\Addons' )` and the addon still runs when the core plugin is missing — it just has nowhere to put its settings.

### Saving a tab's form

Tabs render inside the core's page, so they post to `admin-post.php` and come back with a notice:

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

## Hooks

| Hook | Type | Fires |
|---|---|---|
| `baukasten/register_addons` | action | once per request, on `init` priority 20, when the core collects the addons that want a tab |

The features have many more; see their READMEs.

## Development

```bash
composer install
composer lint       # PHPCS with WordPress Coding Standards
composer lint:fix   # PHPCBF
```

From the repository root, `php -d extension=zip bin/build.php --all` packages every plugin into `build/`.

Class files follow the WordPress file naming convention (`includes/class-addons.php` for `Baukasten\Addons`) and are resolved by the bundled autoloader in `includes/class-autoloader.php`. Composer maps them with a classmap rather than PSR-4, because PSR-4 would require different file names.

## Contributing

Issues and pull requests are welcome. Please run `composer lint` before opening a pull request and keep code and comments in English.

## License

GPLv2 or later. See [LICENSE](LICENSE).
