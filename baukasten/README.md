# Baukasten - Privacy Toolkit

The shared base for a family of small, focused WordPress plugins, most of them about privacy. On its own it adds one screen under *Settings → Baukasten* and a capability; the addons fill it.

[![Lint](https://github.com/trstnbde/baukasten/actions/workflows/lint.yml/badge.svg)](https://github.com/trstnbde/baukasten/actions/workflows/lint.yml)

| | |
|---|---|
| Requires WordPress | 6.5 |
| Tested up to | 7.1 |
| Requires PHP | 8.0 |
| License | GPLv2 or later |

## Why

Privacy features arrive one at a time and are needed in different combinations, so each one is its own plugin: install what you need, update them independently, deactivate one without touching the rest. What that normally costs you is six scattered settings pages. Baukasten is the one place they all report to.

The core deliberately does almost nothing. WordPress already installs, updates, activates, pauses on fatal errors and uninstalls plugins; there is no reason to reimplement any of it.

## The addons

| Plugin | What it does |
|---|---|
| [`baukasten-content-visibility`](../baukasten-content-visibility) | A public/private switch on every post, page and public custom post type. Private content is readable by logged-in users only, regardless of role. |
| [`baukasten-login-legal-pages`](../baukasten-login-legal-pages) | Terms and imprint page pickers, all three legal links on the login screen, and a `/login/` URL. |
| [`baukasten-consent-blocking-engine`](../baukasten-consent-blocking-engine) | Blocks non-essential scripts, styles, embeds, resource hints, Gravatar and emoji until the visitor consents, with an auditable consent log. |

## What the core provides

- **A tabbed settings screen** under *Settings → Baukasten*. One tab per active addon, plus an overview listing every addon there is — active, installed but switched off, or not installed at all, each with the one link that moves it along. Deactivate an addon and its tab disappears with it.
- **A hard-coded addon catalog** in `includes/class-catalog.php`. It is what lets the overview name an addon the site has not got. Adding an addon to the family means adding a row there; nothing is fetched from the plugin directory at runtime.
- **The `manage_baukasten` capability**, granted to administrators on activation. Not tied to `manage_options`, so it can be delegated. Addons may additionally require a capability that fits their data.
- **`Baukasten\Admin` helpers** addons use for their own forms: `page_url()`, `add_notice()` and `redirect_to_tab()`.

## Installation

Install from the WordPress plugin directory, or clone into your plugins directory:

```bash
git clone https://github.com/trstnbde/baukasten.git wp-content/plugins/baukasten
```

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
