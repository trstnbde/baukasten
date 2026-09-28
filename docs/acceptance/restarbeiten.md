# Abnahme: Manuelle Restarbeiten (Developer)

Protokoll zu [Dokument 03a](../briefings/03a-restarbeiten-developer.md), Stand 28.09.2026. Die Rohdaten (Site-Health-Bericht, Header, Inventar) liegen nur lokal in `docs/acceptance/raw/`, weil dieses Repository öffentlich ist.

## Phase A

### A1 Ausgangswerte

| Messung | Ergebnis |
|---|---|
| TTFB Startseite, Median | 0,153 s |
| Header | HSTS `max-age=300`, `nosniff`, Referrer-Policy, Permissions-Policy und `frame-ancestors 'self'` waren schon gesetzt (3b A5 erledigt). |
| `/wp-json/wp/v2/users` | 200, Slug `torsten` öffentlich |
| `?author=1` | 301 auf `/p/author/torsten/` |
| `xmlrpc.php` | 403 |
| oEmbed Startseite | Titel „Startseite“, `author_name`, `author_url`, `provider_name` „Untitled blog“ |
| Fremde Hosts auf Karte 64, ausgeloggt | keine |

### A2 Bestand (nur gelesen)

| Punkt | Befund |
|---|---|
| Consent-Inventar | Einziger externer Host: `cloudflare-turnstile` → `challenges.cloudflare.com` |
| Kontakt → Integration | Turnstile eingerichtet. reCAPTCHA, Akismet, Brevo, Stripe und Constant Contact nicht eingerichtet. |
| Kontaktformulare | Nur #14 „Baukasten Addon: Business Cards“. Kein `[_remote_ip]`, `[_user_agent]` oder `[_user_*]` in Mail/Mail 2. Mail 2 ist inaktiv, `do_not_store` ist nicht gesetzt. |
| Flamingo | 0 Kontakte. 4 Nachrichten (1 Eingang, 3 Spam), die älteste vom 15.09.2026. |
| Business Cards | Formular #14 existiert. Beide Karten (#55, #64) nutzen es, beide haben eine E-Mail-Adresse. |
| Themes | Nur Twenty Twenty-Five, keine inaktiven Themes. |
| Einstellungen → Allgemein | Titel „Untitled blog“. Die Administrator-Adresse liegt nicht unter `bundeshub.de`. |
| Sichtbarkeit | Alle veröffentlichten Seiten sind privat, auch Datenschutzerklärung, Impressum und Nutzungsbedingungen. Die Links im Login und auf den Karten führen ausgeloggt deshalb auf den Login. |

### A3 Einstellungen

| Änderung | Alt | Neu |
|---|---|---|
| Consent: `block_speculative_loading` | `true` | `false` |
| CF7-Mailvorlagen: `[_remote_ip]`/`[_user_agent]` entfernen | nicht vorhanden | keine Änderung nötig |
| Auto-Update aller Plugins und Theme (Aufgabe 5) | 5 Plugins und Theme an, 5 Baukasten-Plugins aus | alle an |

## Phase B: Form Privacy

Siehe [form-privacy.md](form-privacy.md). Die Bereinigung lief nach Freigabe: 4 Nachrichten reduziert, der zweite Lauf ohne Änderung. Die Testeinsendung war erfolgreich, der Purge-Cron ist geplant.

## Phase C: Anpassungen aus Dokument 02

Siehe [empfehlungen.md](empfehlungen.md). Die Header- und Endpunktprüfungen aus A1 sind wiederholt:

- `/users`: 404
- `?author=1`: 404
- oEmbed: ohne Autor, private Einträge `oembed_invalid_url`
- XML-RPC: 403

„Vorlage erneut anwenden“ für Formular #14 und der Testversand an Karte 64 sind nach Freigabe erledigt.

## Phase D: Abschluss

| Punkt | Stand |
|---|---|
| Site-Health nachher | Plugin-Liste: 5 Baukasten-Plugins, alle mit Auto-Update. `WP_CACHE` war schon vorher `false` bzw. nicht gesetzt. `WP_DEBUG_DISPLAY` steht weiter auf `true` (3b A3 offen). |
| TTFB nachher | 0,162 s (vorher 0,153 s), im Rahmen der Messstreuung |
| Fremde Hosts | keine; auf Karten Cloudflare erst nach Einwilligung |
| Offene Webmaster-Punkte (3b) | Administrator-Adresse unter `bundeshub.de` mit DMARC, DKIM und SPF; Site-Titel; `user_nicename`; `WP_DEBUG_DISPLAY`, `DISALLOW_FILE_EDIT`, `DISABLE_WP_CRON` samt Server-Cronjob; Entscheidung zu `blog_public`; Rechtsseiten öffentlich schalten; Datenschutzerklärung mit dem Baustein aus Form Privacy. |
