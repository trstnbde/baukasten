# Abnahme: Empfehlungen und Aufgaben vom 28.09.2026

Protokoll zu [Dokument 02](../briefings/02-baukasten-empfehlungen.md) und zu den Aufgaben, die am selben Tag dazukamen. Abweichungen und ihre Gründe stehen in [`../briefings/README.md`](../briefings/README.md). Geprüft auf https://bundeshub.de/ nach dem Deployment (ausgeloggt per `curl` und im In-App-Browser, eingeloggt im Chrome des Webmasters, serverseitig per WP-CLI) und in der CI.

## Aufgaben

| Aufgabe | Ergebnis | Prüfung |
|---|---|---|
| Kern, Consent, Content Visibility und Login Legal Pages als ein Plugin | ✅ | Die drei alten Addons wurden per WP-CLI deaktiviert und ihre Ordner ins Backup verschoben, nicht gelöscht. Danach Kern 1.0.0 installiert: `baukasten_structure = 2`, alle drei Bereiche geladen. Die Übersicht zeigt sie als „Enthalten“, alle Tabs laufen fehlerfrei. Die Daten sind unverändert: 7 Visibility-Metas, Consent-Log, Rechtsseiten, Login-Slug. |
| Auto-Update für alle Plugins und Themes, auch künftige | ✅ | Den Schritt `Hardening::enable_auto_updates()` habe ich einzeln ausgeführt. Die Gesamtroutine lief nicht, weil sie `blog_public` zurücksetzt. Erster Lauf: 5 eingeschaltet, zweiter Lauf: „Bereits für alle Plugins und Themes aktiv“. Plugin- und Theme-Liste zeigen jetzt überall `on`. `auto_update_plugin` und `auto_update_theme` liefern auch für unbekannte Elemente `true`. |
| Turnstile auf Karten erst nach `[acceptance email-consent]` | ✅ | Karte 64, ausgeloggt: Nach dem Laden und nach dem Tippen ins Namensfeld gibt es kein Skript von `challenges.cloudflare.com`. Nach dem Haken wird `api.js?render=explicit` geladen und das Widget gerendert. Der Hinweis unter dem Formular ist angepasst. |

## Empfehlungen

| # | Ergebnis | Prüfung |
|---|---|---|
| 1 Embed/oEmbed privater Beiträge | ✅ | `/terms/embed/` → 404. oEmbed für `/` und `/blog/` → `oembed_invalid_url`. Vorher lieferte oEmbed der Startseite Titel, Autorname und Autor-URL. Nebenbefund behoben: Die private **Beitragsseite** `/blog/` wurde ausgeloggt ausgeliefert (200), weil der Guard nur Einzelansichten prüfte. Jetzt leitet sie auf den Login um. CI: `ContentVisibilityTest`. |
| 2 Cache-Purge | ✅ Code | `clean_post_cache()` sowie der Purge von WP Super Cache, W3TC, WP Rocket und LiteSpeed beim Wechsel auf privat. Auf bundeshub.de läuft kein Seiten-Cache; lokal gibt es keine Testumgebung mehr. |
| 3 Mediendateien | ✅ | Unter „Known gaps“ im README von Content Visibility dokumentiert. |
| 4 Einwilligung per CSRF | ✅ | Formular-POST von `https://attacker.example` → 403 ohne Cookie. JSON von fremdem Origin → 403. Ohne Origin (curl) → 403. JSON von der eigenen Seite im Browser → 200, das Cookie wird gesetzt, ein Log-Eintrag entsteht (Testeintrag vom 28.09.). Der Nonce ist entfernt. CI: `ConsentOriginTest`. |
| 5 Kontaktformular Business Cards | ✅ | Vorlage, Mail-Tags `[_bkbc_card_email]`/`[_bkbc_card_title]`, Absender, Reply-To und der Button „Vorlage erneut anwenden“ sind umgesetzt. CI: `CardRecipientTest`. Nach Freigabe wurde die Vorlage per WP-CLI auf Formular #14 angewendet: Das Formular entspricht zeichengenau dem Vorgabetext. Mail: Absender `[_site_title] <[_site_admin_email]>`, Empfänger `[_bkbc_card_email]`, `Reply-To: [your-email]`, „Karte: [_bkbc_card_title]“ im Body, Mail 2 inaktiv. Die Links im Einwilligungssatz zeigen auf `/privacy/` und `/terms/`. Testversand über Karte 64: `mail_sent`, der Empfänger wird auf die Adresse der Karte aufgelöst, nicht auf die Admin-Adresse. Die Admin-Adresse liegt nicht auf `bundeshub.de`; die Zustellbarkeit hängt an 3b A6. |
| 6 Turnstile/Form Privacy | ✅ | Auf Karten ohne Formular übernimmt Form Privacy das Dequeue. Karten mit Formular melden sich über `baukasten/form_privacy/page_has_form`. Turnstile wird auf Karten nicht ausgebaut, sondern erst nach Einwilligung geladen (siehe Aufgaben). |
| 7 Site Hardening als eigenes Addon | — entfällt | Durch die Zusammenlegung überholt, siehe Briefings-README. |
| 8 Frontend-Assets nur bei Bedarf | ✅ | Auf Karte 64 laden `consent-bootstrap.js` und `blocked.css` nicht mehr: Es gibt keinen externen Host in der Queue und kein Embed. |
| 9 Inventory nur im Scan-Modus | ✅ Code | Erfasst nur für `manage_options` oder bei laufendem Scan. „Asset-Liste leeren und scannen“ startet 30 Minuten. Die Option `baukasten_consent_scan_until` ist angelegt. |
| 10 Speculative Loading | ✅ | Default `false`, aus `MEASURES` entfernt. Auf Produktion umgestellt: `block_speculative_loading` von `true` auf `false`. |
| 11 Härtung nur per Button | ✅ | Der Satz steht in README und readme.txt. CI: Aktivierung, Upgrade und Strukturmigration ändern `blog_public`, `users_can_register` und `default_comment_status` nicht. **Nebenbefund behoben:** Der Bestätigungsdialog des Buttons war seit jeher wirkungslos. `onsubmit` endete auf Produktion nach `return window.confirm( `, weil `wp_json_encode()` unescaped in einem doppelt quotierten Attribut stand. Der Button hätte ohne Rückfrage gehärtet. |
| 12 User-Enumeration, XML-RPC | ✅ | `/wp-json/wp/v2/users` und `/users/1` → 404 `rest_no_route` (vorher 200 mit Slug `torsten`). `?author=1` → 404 (vorher 301 auf `/p/author/torsten/`). `/p/author/torsten/` → 404. oEmbed ohne Autorfelder. Users-Sitemap → 404. Kein `X-Pingback`. XML-RPC → 403 (per `.htaccess`, zusätzlich im Plugin abgeschaltet). |
| 13 Löschfrist Consent-Log | ✅ | Täglicher Cron `baukasten/consent/purge_log`, 24 Monate, Minimum 13. Die README-Aussage zum Rate-Limit ist präzisiert. |
| 14 Rate-Limit | ✅ | Serverseitig mit der Dokumentations-IP 203.0.113.77: Versuche 1–5 ergeben `invalid_username`, Versuch 6 ergibt `baukasten_login_locked`, Sperre 900 s. Eine andere IP bleibt frei. Die Transients sind danach entfernt. CI: Sperre auch bei korrektem Passwort, Verdopplung der Sperre, gleiche Meldung für unbekannte Nutzer. Two Factor 0.16 meldet falsche Codes über `wp_login_failed`. |
| 15 Update URI | ✅ | In allen fünf Plugins gesetzt. |
| 16 2FA im Repository | ✅ | War seit Commit `639c0d0` enthalten. |
| 17 Integrationstests | ✅ | `tests/`, Workflow `integration.yml`, 35 Tests grün. |

## Messwerte

| | Vorher | Nachher |
|---|---|---|
| TTFB Startseite (Median aus 5, ausgeloggt) | 0,153 s | 0,162 s |
| Fremde Hosts auf Karte 64 (ausgeloggt) | keine | keine; Cloudflare erst nach Einwilligung |
| Aktive Baukasten-Plugins | 7 | 5 |
