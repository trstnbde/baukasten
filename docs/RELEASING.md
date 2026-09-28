# Releasing

Five plugins, five releases. This is the whole procedure; `DEVELOPER-GUIDE.md`
covers day-to-day work and `WORDPRESS-ORG-COMPLIANCE.md` covers what the
directory asks for.

**Publishing to WordPress.org is on hold** (decided 28 September 2026). Releases
are GitHub releases with ZIPs attached, installed by upload. Until that changes,
every plugin header carries

```
Update URI: https://github.com/trstnbde/baukasten
```

so WordPress does not ask wordpress.org for updates to our slugs — otherwise a
stranger who registered one of them first would be offered to every site running
ours. Remove the header from each plugin before it is submitted to the directory.

## What ships

One tag, one plugin, one ZIP:

| Tag | Plugin |
| --- | --- |
| `baukasten-<version>` | `baukasten` — the core, with Consent Blocking Engine, Content Visibility and Login Legal Pages built in |
| `baukasten-multi-domain-<version>` | `baukasten-multi-domain` |
| `baukasten-business-cards-<version>` | `baukasten-business-cards` |
| `baukasten-2fa-<version>` | `baukasten-2fa` |
| `baukasten-form-privacy-<version>` | `baukasten-form-privacy` |

Tags are always `<slug>-<version>`. The core used to ship together with three
separate addons under one tag; since those were merged into it, it is a single
ZIP like the others.

## Steps

1. **Bump the version in two places** — the `Version:` header in
   `<slug>/<slug>.php` and `Stable tag:` in `<slug>/readme.txt`. `bin/build.php`
   refuses to build when they disagree. In the core, `const VERSION` follows the
   header; the features use it too.
2. **Write the changelog.** A `= <version> =` block under `== Changelog ==`, and
   a matching one under `== Upgrade Notice ==` saying why someone should bother.
3. **Translations.** Regenerate, merge, compile:

   ```bash
   wp i18n make-pot <slug> <slug>/languages/<slug>.pot --slug=<slug> --domain=<slug>
   # fill in the new msgstr in <slug>/languages/<slug>-de_DE.po (bin/po-merge.py helps)
   wp i18n make-mo <slug>/languages <slug>/languages
   ```

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

   Screenshots are not — they have to come from a real install and go in
   `<slug>/.wordpress-org/assets/` as `screenshot-1.png` and so on. That
   directory is excluded by `.distignore`.
6. **Tag and release.** `git tag -a <slug>-<version>`, push the tag.
   `.github/workflows/release.yml` builds the ZIP and attaches it to the GitHub
   release for that tag. **Push tags one at a time:** GitHub creates no events,
   and so starts no workflow, when more than three tags arrive in one push.
7. **Deploy** to the live site as described in `DEVELOPER-GUIDE.md`, backup
   first.

## Later: the first WordPress.org submission

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
| Baukasten Addon: Multi-Domain Landingpage | `baukasten-addon-multi-domain-landingpage` | `baukasten-multi-domain` |
| Baukasten Addon: Business Cards | `baukasten-addon-business-cards` | `baukasten-business-cards` |
| Baukasten Addon: Two-Factor Approval | `baukasten-addon-two-factor-approval` | `baukasten-2fa` |
| Baukasten Addon: Form Privacy | `baukasten-addon-form-privacy` | `baukasten-form-privacy` |

**Submit the core first and wait for it to be approved.** Every addon declares
`Requires Plugins: baukasten`, and that header is matched against the directory
slug. If the core ends up under a different slug, four plugins have to be edited
before they can be submitted at all.

`baukasten` is a single common word, and the plugins team may object to a slug
that generic. Decide the fallback before you need it rather than under review.

Other things a reviewer will look at first, in the order they are listed on the
submission page: unescaped output, unvalidated input, form handling without a
nonce, and the guidelines. PHPCS with WPCS covers the first three mechanically —
run it on all five before submitting.

## Checklist

- [ ] Header version and `Stable tag` agree
- [ ] Changelog and Upgrade Notice written
- [ ] `.pot` regenerated, `de_DE.po` filled in, `.mo` compiled
- [ ] `php -d extension=zip bin/build.php --all` clean, no `two-factor` ZIP
- [ ] `vendor/bin/phpcs` clean on every plugin that changed, integration tests green
- [ ] Tag pushed, GitHub release created, ZIP attached
- [ ] Deployed with a backup taken first
- [ ] WordPress.org only: `Update URI` removed, screenshots present, slug corrected before the review started
