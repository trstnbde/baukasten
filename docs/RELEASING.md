# Releasing

Seven plugins, four releases. This is the whole procedure; `DEVELOPER-GUIDE.md`
covers day-to-day work and `WORDPRESS-ORG-COMPLIANCE.md` covers what the
directory asks for.

## What ships together

The core and the three addons that are useless without it move as one release,
because they are one decision: you either want the privacy toolkit or you do
not. The other three are independent products that happen to live in this repo,
and each one gets its own tag so its version number means something on its own.

| Tag | Plugins |
| --- | --- |
| `baukasten-<version>` | `baukasten`, `baukasten-consent-blocking-engine`, `baukasten-content-visibility`, `baukasten-login-legal-pages` |
| `baukasten-multi-domain-<version>` | `baukasten-multi-domain` |
| `baukasten-business-cards-<version>` | `baukasten-business-cards` |
| `baukasten-2fa-<version>` | `baukasten-2fa` |

Tags are always `<slug>-<version>`. The old repo-wide `v1.0.0` predates this and
is gone.

A bundled release still ships four separate ZIPs and four separate directory
submissions. Nothing about the grouping reaches WordPress.org; it is a GitHub
convenience, so that "the toolkit, 1.1.0" is one thing to link to.

## Steps

1. **Bump the version in two places** — the `Version:` header in
   `<slug>/<slug>.php` and `Stable tag:` in `<slug>/readme.txt`. `bin/build.php`
   refuses to build when they disagree, which is the only thing standing between
   a typo and a release nobody can update from.
2. **Write the changelog.** A `= <version> =` block under `== Changelog ==`, and
   a matching one under `== Upgrade Notice ==` saying why someone should bother.
3. **Translations.** Regenerate, merge, compile:

   ```bash
   wp i18n make-pot <slug> <slug>/languages/<slug>.pot --slug=<slug> --domain=<slug>
   # fill in the new msgstr in <slug>/languages/<slug>-de_DE.po
   wp i18n make-mo <slug>/languages <slug>/languages
   ```

   On this machine WP-CLI has to be driven through the XAMPP PHP; see
   `DEVELOPER-GUIDE.md`.
4. **Build.**

   ```bash
   php -d extension=zip bin/build.php --all
   ```

   `--all` only picks up directories whose name starts with `baukasten`, so a
   third-party plugin checked out beside them (`two-factor/`) is never packaged.
5. **Directory assets.** Icon and banner are generated:

   ```bash
   php -d extension=gd bin/make-assets.php <slug>
   ```

   Screenshots are not, and never will be — they have to come from a real
   install. They go in `<slug>/.wordpress-org/assets/` as `screenshot-1.png` and
   so on, numbered to match the captions in `readme.txt`. That directory is
   excluded by `.distignore`: these files belong in the SVN `assets/` directory,
   never inside the plugin ZIP.
6. **Tag and release.** `git tag -a <slug>-<version>`, push, then a GitHub
   release with the ZIP or ZIPs attached.
7. **SVN**, for a plugin already in the directory: commit the ZIP contents to
   `trunk`, copy to `tags/<version>`, and put the `.wordpress-org/assets` files
   in `assets/`.

## First submission: the slug is decided once

Read this before uploading anything to WordPress.org.

**The directory derives the slug from the `Plugin Name` header, not from our
directory name, and after approval it can never be changed.** That slug becomes
the plugin's URL, the folder name on every site that installs it, the SVN
repository, and the text domain. Left alone, "Baukasten - Privacy Toolkit" would
become `baukasten-privacy-toolkit`, and every `__( '…', 'baukasten' )` call in
the codebase would be addressing a domain that no longer exists.

There is exactly one chance to correct it: **immediately after uploading, before
a reviewer picks the submission up, the submission page offers a link to change
the permalink.** Use it. Once the review has begun the link is gone, and once the
plugin is approved nothing can be done at all.

| Plugin Name | What the directory would derive | What it must be |
| --- | --- | --- |
| Baukasten - Privacy Toolkit | `baukasten-privacy-toolkit` | `baukasten` |
| Baukasten Addon: Content Visibility | `baukasten-addon-content-visibility` | `baukasten-content-visibility` |
| Baukasten Addon: Login Legal Pages | `baukasten-addon-login-legal-pages` | `baukasten-login-legal-pages` |
| Baukasten Addon: Consent Blocking Engine | `baukasten-addon-consent-blocking-engine` | `baukasten-consent-blocking-engine` |
| Baukasten Addon: Multi-Domain Landingpage | `baukasten-addon-multi-domain-landingpage` | `baukasten-multi-domain` |
| Baukasten Addon: Business Cards | `baukasten-addon-business-cards` | `baukasten-business-cards` |
| Baukasten Addon: Two-Factor Approval | `baukasten-addon-two-factor-approval` | `baukasten-2fa` |

**Submit the core first and wait for it to be approved.** Every addon declares
`Requires Plugins: baukasten`, and that header is matched against the directory
slug. If the core ends up under a different slug, six plugins have to be edited
before they can be submitted at all.

`baukasten` is a single common word, and the plugins team may object to a slug
that generic. Decide the fallback before you need it rather than under review.

Other things a reviewer will look at first, in the order they are listed on the
submission page: unescaped output, unvalidated input, form handling without a
nonce, and the guidelines. PHPCS with WPCS covers the first three mechanically —
run it on all seven before submitting.

## Checklist

- [ ] Header version and `Stable tag` agree
- [ ] Changelog and Upgrade Notice written
- [ ] `.pot` regenerated, `de_DE.po` filled in, `.mo` compiled
- [ ] `php -d extension=zip bin/build.php --all` clean, no `two-factor` ZIP
- [ ] Screenshots present and numbered to match the readme captions
- [ ] `vendor/bin/phpcs` clean on every plugin that changed
- [ ] Tag pushed, GitHub release created, ZIPs attached
- [ ] First submission only: slug corrected before the review started
