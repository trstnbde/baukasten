# Briefings, September 2026

The four documents here were the brief for the round of changes on 28 September 2026:

| Document | For | Content |
|---|---|---|
| [01-briefing-baukasten-form-privacy.md](01-briefing-baukasten-form-privacy.md) | Developer | The Form Privacy addon, F1–F10 |
| [02-baukasten-empfehlungen.md](02-baukasten-empfehlungen.md) | Developer | Seventeen recommendations for the existing plugins |
| [03a-restarbeiten-developer.md](03a-restarbeiten-developer.md) | Developer | Measurements, backend settings and acceptance runs on the live site |
| [03b-restarbeiten-webmaster.md](03b-restarbeiten-webmaster.md) | Site owner | Hosting, DNS, mail and decisions |

They are kept as written. What was decided on top of them the same day, and where the implementation deviates as a result:

- **Consent Blocking Engine, Content Visibility and Login Legal Pages were merged into the core**, one plugin and one release. Multi-Domain, Two-Factor Approval, Business Cards and Form Privacy stay separate.
- **No separate "Site Hardening" addon (recommendation 7).** The plugin list above rules it out. The hardening measures stay in the Consent Blocking Engine, now part of the core. Because the consent engine can no longer be switched off on its own, recommendations 8 and 9 were implemented instead: its front end assets load only where needed, and the asset list is only recorded for administrators or during a scan. The rate limit (recommendation 14) lives in Login Legal Pages.
- **Automatic updates.** The hardening routine gained a step that turns on automatic updates for every installed plugin and theme, and a setting, on by default, that keeps every plugin and theme installed later on automatic updates too.
- **Turnstile on business cards (recommendation 6)** is not taken out. On a card, Cloudflare Turnstile is loaded only once the visitor ticks `[acceptance email-consent]`. The dequeueing on cards without a form is left to Form Privacy when it is active.
- **Card mail recipient (recommendation 5)** uses `_wpcf7_container_post`, not a new `_bkbc_card` field: since Business Cards renders its form with `in_the_loop` set, Contact Form 7 already sends the card's ID there. The same checks apply.
- **Form Privacy's tab is at position 70**, not 60: Two-Factor Approval had 60 already.
- **Versions stay at 1.0.0** for every plugin, instead of 1.1.0.
- **Two-Factor Approval (recommendation 16)** was already in the repository.
- **No local test server.** Development still happens locally; testing happens on the live site and in CI. Deployment is by SSH rather than by the site owner uploading ZIPs.
- **WordPress.org submission is on hold**, so recommendation 15 (`Update URI`) is in place for all five plugins.

Protocols of what was checked are in [`../acceptance/`](../acceptance).
