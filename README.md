# Baukasten

A family of small, focused WordPress plugins — one core that gives them a shared settings screen, and one plugin per feature. Most of them are about privacy.

Seven plugins live in this repository. Each is published separately to the WordPress plugin directory; they are developed together because they share conventions, tooling and a release cadence.

| Directory | Plugin | What it does |
|---|---|---|
| [`baukasten`](baukasten) | Baukasten - Privacy Toolkit | The core. One tabbed screen under *Settings → Baukasten* and the `manage_baukasten` capability. Does nothing on its own. |
| [`baukasten-content-visibility`](baukasten-content-visibility) | Baukasten Addon: Content Visibility | A public/private switch on every post, page and public custom post type. Private means logged in, any role. |
| [`baukasten-login-legal-pages`](baukasten-login-legal-pages) | Baukasten Addon: Login Legal Pages | Privacy policy, terms and imprint in the login card; no WordPress header on the screen; a `/login/` URL. |
| [`baukasten-consent-blocking-engine`](baukasten-consent-blocking-engine) | Baukasten Addon: Consent Blocking Engine | Blocks third-party scripts, styles, embeds, resource hints, Gravatar and emoji until the visitor consents, with an auditable log and a site-wide hardening routine. |
| [`baukasten-multi-domain`](baukasten-multi-domain) | Baukasten Addon: Multi-Domain Landingpage | Gives a page its own domain, so one install serves a different front page under each domain. |
| [`baukasten-business-cards`](baukasten-business-cards) | Baukasten Addon: Business Cards | A digital business card on its own short link, built for a phone and detached from the theme, with a vCard download and a QR code. |
| [`baukasten-2fa`](baukasten-2fa) | Baukasten Addon: Two-Factor Approval | A second factor that asks another already signed-in session to confirm the login from its admin bar. Needs the Two Factor plugin as well as the core. |

## Why it is split up

Features arrive one at a time and are wanted in different combinations. As separate plugins they can be installed individually, updated independently, and switched off one at a time. What that normally costs is a scattering of settings pages, so the core exists purely to collect them: an addon registers a tab and the core renders it.

An addon is an ordinary plugin. It declares `Requires Plugins: baukasten` and calls `Baukasten\Addons::register()` on the `baukasten/register_addons` action. Nothing is inherited, nothing is implemented, and an addon that guards the call with `class_exists()` keeps working without the core.

An addon may name other plugins in that header too, and hook into them independently: Business Cards requires Contact Form 7, Two-Factor Approval requires the Two Factor plugin. The Baukasten tab and the foreign integration stay separate.

New here? Start with [`docs/DEVELOPER-GUIDE.md`](docs/DEVELOPER-GUIDE.md). Shipping something? [`docs/RELEASING.md`](docs/RELEASING.md).

## Development

Each plugin has its own `composer.json` and `phpcs.xml.dist`:

```bash
cd baukasten            # or any of the others
composer install
composer lint
```

From the repository root:

```bash
php -d extension=zip bin/build.php --all         # build/<slug>-<version>.zip for each plugin
php -d extension=zip bin/build.php baukasten     # just one
php -d extension=gd  bin/make-assets.php baukasten   # directory icon and banner
```

CI runs PHPCS per plugin and `php -l` over everything on PHP 8.0 through 8.4.

## Documentation

- [`docs/DEVELOPER-GUIDE.md`](docs/DEVELOPER-GUIDE.md) — how the pieces fit together, the conventions, and the traps
- [`docs/WORDPRESS-ORG-COMPLIANCE.md`](docs/WORDPRESS-ORG-COMPLIANCE.md) — readiness for the plugin directory, guideline by guideline

## AI disclosure

**Partially AI-Modified.**

Parts of this repository were written or changed with AI assistance, on top of human-authored code and against human-written specifications. Every change was reviewed and tested before it was committed.

The label follows the European Commission's [EU icons for labelling AI-generated content](https://digital-strategy.ec.europa.eu/en/policies/eu-icons-labelling-ai-generated-content), which defines three: *Basic*, *Fully AI-Generated*, and *Partially AI-Modified* — the last for "pre-existing, human-made content [that] was partially modified with AI". The icons themselves are free to use without attribution.

## License

GPLv2 or later. See the `LICENSE` file in the repository root and in each plugin.
