# Baukasten

**English** · [Deutsch](README.de.md)

A family of WordPress plugins about privacy — one toolkit that does the groundwork, and addons for the features only some sites need — and a block theme to go with them.

Five plugins and one theme live in this repository, each with its own release. They are developed together because they share conventions, tooling and a settings screen.

| Directory | Plugin | Tab | What it does |
|---|---|---|---|
| [`baukasten`](baukasten) | Baukasten - Privacy Toolkit | 10–30 | The core, with three built-in features: **Content Visibility** (a public/private switch on all content), **Login Legal Pages** (legal links on a tidy, rate-limited `/login/` screen) and the **Consent Blocking Engine** (third parties blocked until consent, an auditable log, site hardening and automatic updates). One tabbed screen under *Settings → Baukasten*. |
| [`baukasten-multi-domain`](baukasten-multi-domain) | Baukasten Addon: Multi-Domain Landingpage | 40 | Gives a page its own domain, so one install serves a different front page under each domain. |
| [`baukasten-business-cards`](baukasten-business-cards) | Baukasten Addon: Business Cards | 50 | A digital business card on its own short link, built for a phone and detached from the theme, with a vCard download, a QR code and a contact form that mails the card's owner. |
| [`baukasten-2fa`](baukasten-2fa) | Baukasten Addon: Two-Factor Approval | 60 | A second factor that asks another already signed-in session to confirm the login from its admin bar. Needs the Two Factor plugin as well as the core. |
| [`baukasten-form-privacy`](baukasten-form-privacy) | Baukasten Addon: Form Privacy | 70 | Contact Form 7 and Flamingo without the leaks: assets only where a form is, no IP address, no address book, a retention period, an exporter, and spam protection without a third party. |
| [`baukasten-new-tab`](baukasten-new-tab) | Baukasten Addon: New Tab Page | 80 | Turns one page into a browser start page: search with a choice of engines, a favourites bar, a greeting that knows the holidays, and a background from the media library or, after consent, Unsplash. Chosen on its own tab in *Settings → Baukasten*. |

| Directory | Theme | Version | What it does |
|---|---|---|---|
| [`baukasten-tt5-fork`](baukasten-tt5-fork) | Baukasten Theme: TT5 Fork | 1.5 | A standalone fork of Twenty Twenty-Five 1.5 with three design systems as styles: **Organic** (the default), **Modernist** and **Nocturne**, plus **Noto Sans** and **Noto Serif** as font presets. All fonts are bundled, so the theme sends no requests to third parties. Details in [`docs/theme/TT5-FORK.md`](docs/theme/TT5-FORK.md). |

Everything is written in English and ships with a complete German translation (`de_DE`).

## Why it is split the way it is

The toolkit was four plugins until the three that could not run without the core were merged into it: whoever wants the toolkit wants all of it. They keep their own directories under `baukasten/features/`, their namespaces, options and hooks.

The addons stay separate because they are wanted in different combinations, depend on other plugins (Contact Form 7, Two Factor), and update on their own schedule. What that normally costs is a scattering of settings pages, so the core collects them: an addon registers a tab and the core renders it.

The theme is independent of the plugins: it works without them, and they work with any theme. It was a child theme of Twenty Twenty-Five first; as a fork it needs no parent and carries the three design systems side by side, chosen under *Appearance → Editor → Styles*.

The plugins and the theme are not in the WordPress.org directories yet; that is on hold. Until then each carries `Update URI: https://github.com/trstnbde/baukasten`, so WordPress never offers somebody else's plugin or theme of the same slug as an update. Install them from the [releases](https://github.com/trstnbde/baukasten/releases).

An addon is an ordinary plugin. It declares `Requires Plugins: baukasten` and calls `Baukasten\Addons::register()` on the `baukasten/register_addons` action. Nothing is inherited, nothing is implemented, and an addon that guards the call with `class_exists()` keeps working without the core.

An addon may name other plugins in that header too, and hook into them independently: Business Cards requires Contact Form 7, Two-Factor Approval requires the Two Factor plugin. The Baukasten tab and the foreign integration stay separate.

New here? Start with [`docs/DEVELOPER-GUIDE.md`](docs/DEVELOPER-GUIDE.md). Shipping something? [`docs/RELEASING.md`](docs/RELEASING.md).

## Releases

One tag, one ZIP, one GitHub release per plugin or theme:

| Tag | ZIP |
|---|---|
| `baukasten-1.0.1` | `baukasten-1.0.1.zip` |
| `baukasten-multi-domain-1.0.0` | `baukasten-multi-domain-1.0.0.zip` |
| `baukasten-business-cards-1.0.1` | `baukasten-business-cards-1.0.1.zip` |
| `baukasten-2fa-1.0.0` | `baukasten-2fa-1.0.0.zip` |
| `baukasten-form-privacy-1.0.0` | `baukasten-form-privacy-1.0.0.zip` |
| `baukasten-new-tab-1.0.1` | `baukasten-new-tab-1.0.1.zip` |
| `baukasten-tt5-fork-1.5` | `baukasten-tt5-fork-1.5.zip` |

Plugins: *Plugins → Add New → Upload Plugin*. Theme: *Appearance → Themes → Add New → Upload Theme*.

## Development

Each plugin has its own `composer.json` and `phpcs.xml.dist`:

```bash
cd baukasten            # or any of the other plugins
composer install
composer lint
```

From the repository root:

```bash
php -d extension=zip bin/build.php --all                 # build/<slug>-<version>.zip for each plugin and the theme
php -d extension=zip bin/build.php baukasten-tt5-fork    # just one
php -d extension=gd  bin/make-assets.php baukasten       # directory icon and banner
```

CI runs PHPCS per plugin, `php -l` over everything on PHP 8.0 through 8.4, and integration tests against the WordPress test suite in `wp-env` (`tests/`).

## Documentation

- [`docs/DEVELOPER-GUIDE.md`](docs/DEVELOPER-GUIDE.md) — how the pieces fit together, the conventions, and the traps
- [`docs/RELEASING.md`](docs/RELEASING.md) — tags, releases, and the one-shot slug step for a later WordPress.org submission
- [`docs/theme/TT5-FORK.md`](docs/theme/TT5-FORK.md) — the theme: token mapping for Organic, Modernist and Nocturne, contrast, limits
- [`docs/WORDPRESS-ORG-COMPLIANCE.md`](docs/WORDPRESS-ORG-COMPLIANCE.md) — readiness for the plugin directory, guideline by guideline (on hold)
- [`docs/briefings/`](docs/briefings) — the briefings behind Form Privacy and the 2026-09 round of changes
- [`docs/acceptance/`](docs/acceptance) — acceptance protocols from the live site

## AI disclosure

**Partially AI-Modified.**

Parts of this repository were written or changed with AI assistance, on top of human-authored code and against human-written specifications. Every change was reviewed and tested before it was committed.

The label follows the European Commission's [EU icons for labelling AI-generated content](https://digital-strategy.ec.europa.eu/en/policies/eu-icons-labelling-ai-generated-content), which defines three: *Basic*, *Fully AI-Generated*, and *Partially AI-Modified* — the last for "pre-existing, human-made content [that] was partially modified with AI". The icons themselves are free to use without attribution.

## License

GPLv2 or later. See the `LICENSE` file in the repository root and in each plugin. The theme is based on Twenty Twenty-Five (GPLv2 or later, WordPress.org and contributors); its fonts are under the SIL Open Font License 1.1.
