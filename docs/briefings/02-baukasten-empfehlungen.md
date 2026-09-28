# Baukasten: Empfehlungen für Anpassungen

Das ist Dokument 2 von 3. Es wird nach dem Briefing „Form Privacy“ umgesetzt, weil die Empfehlungen 5 und 6 darauf aufbauen. Die manuellen Restarbeiten folgen in Dokument 3a (Developer) und 3b (Webmaster).

| | |
|---|---|
| Ausführung | Developer (Claude Code) |
| Freigaben und Deployment | Webmaster |
| Zielsystem | https://bundeshub.de/ |
| Codebasis | `github.com/trstnbde/baukasten`, Branch `main`, Commit `47577f7`, alle Plugins 1.0.0 |

Die Arbeitsweise und die Testregeln stehen in Dokument 1 im Abschnitt „Arbeitsweise und Tests“: lokal entwickeln, ZIP bauen, Webmaster deployt, Abnahme in Chrome auf https://bundeshub.de/, Protokoll unter `docs/acceptance/<slug>.md`.

Jedes geänderte Plugin bekommt eine neue Minor-Version (1.1.0) mit Changelog-Eintrag in `readme.txt`.

Die Prioritäten bedeuten:

- P1: Datenleck oder Fehlfunktion
- P2: Funktion, Architektur, Performance
- P3: Release und Pflege

Am 28.09.2026 extern bestätigt:

- `https://bundeshub.de/wp-json/wp/v2/users` liefert den Slug `torsten`.
- `/wp-json/oembed/1.0/embed` liefert `author_name` und `author_url`.
- `provider_name` lautet „Untitled blog“.

---

## P1: Content Visibility

### 1. Embed-Ansicht und oEmbed-Endpunkt geben private Beiträge preis

`Frontend_Guard::guard_singular()` bricht bei `is_embed()` ab. Die Embed-URL eines privaten Beitrags (`/p/ID/slug/embed/`) rendert deshalb das Core-Template `embed-content.php`. Dort erscheinen Titel und Beitragsbild ungefiltert, ein manuell gepflegter Auszug ebenfalls. `guard_content()` greift nur beim automatisch erzeugten Auszug.

Außerdem liefert `/wp-json/oembed/1.0/embed?url=…` Titel, Autorname, Autor-URL und Thumbnail. `register_rest_guards()` deckt über `rest_route_prefixes()` nur die Routen der Post-Typen ab, `oembed/1.0` gehört nicht dazu.

Umsetzung:

- In `guard_singular()` Embed-Anfragen privater Beiträge mit 404 beantworten.
- Über `oembed_request_post_id` für private Beiträge `0` zurückgeben.

Abnahme: Der Webmaster markiert einen Testbeitrag als privat. Danach liefern diese Aufrufe ausgeloggt 404 bzw. `oembed_invalid_url`:

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://bundeshub.de/p/ID/SLUG/embed/
curl -s 'https://bundeshub.de/wp-json/oembed/1.0/embed?url=https://bundeshub.de/p/ID/SLUG/'
```

### 2. Cache-Purge beim Umschalten der Sichtbarkeit

Admin-Spalte (AJAX) und Bulk-Aktionen schreiben über `Visibility::set()` nur Post-Meta. `save_post` läuft dabei nicht, Seiten-Caches behalten deshalb die öffentliche Kopie. Auf bundeshub.de läuft kein Seiten-Cache mehr. Relevant wird der Punkt bei der Veröffentlichung auf wordpress.org.

Umsetzung in `Visibility::set()` nach dem Meta-Update:

- `clean_post_cache( $post_id )` aufrufen.
- Beim Wechsel auf privat den gesamten Seiten-Cache leeren, jeweils mit `function_exists()` abgesichert:
  - WP Super Cache: `wp_cache_clear_cache()`
  - W3 Total Cache: `w3tc_flush_all()`
  - WP Rocket: `rocket_clean_domain()`
  - LiteSpeed: `do_action( 'litespeed_purge_all' )`

  Die Funktionsnamen vor dem Einbau gegen die aktuellen Versionen der Cache-Plugins prüfen.
- `baukasten/content_visibility/changed` im README als Integrationspunkt dokumentieren.

Abnahme: nur lokal, mit WP Super Cache in der XAMPP-Umgebung.

### 3. Mediendateien privater Beiträge dokumentieren

Uploads bleiben unter ihrer direkten URL abrufbar, weil Apache sie ohne PHP ausliefert. Unter „Known gaps“ im README ergänzen.

---

## P1: Consent Blocking Engine

### 4. Einwilligung per CSRF fälschbar, Nonce ohne Schutzwirkung

`REST_Controller::set_consent()` hat bewusst keine Capability-Prüfung. Laut README schützt der `wp_rest`-Nonce. Das stimmt nicht:

- Core prüft den Nonce in `rest_cookie_check_errors()` nur, wenn einer mitgeschickt wird.
- Ein fremdes HTML-Formular kann per Top-Level-Navigation `categories[]=…` form-encoded an `/wp-json/baukasten-consent/v1/consent` senden. Das Cookie wird gesetzt, und `Consent_Log` schreibt eine Einwilligung, die der Besucher nie gegeben hat.
- Für ausgeloggte Besucher ist der Nonce für alle gleich, weil er an User-ID 0 hängt.
- Hinter einem Seiten-Cache veraltet er zudem nach 12 bis 24 Stunden.

Umsetzung:

- In `set_consent()` den Header `Origin` gegen die eigenen Hosts prüfen, also `home_url()` und bei aktivem Multi-Domain-Addon die gemappten Domains. Ersatzweise `Sec-Fetch-Site: same-origin` akzeptieren. Alles andere mit 403 ablehnen.
- Nur `application/json` annehmen, geprüft über `$request->get_content_type()`.
- Den Nonce aus `Frontend::enqueue()` und `consent-bootstrap.js` entfernen.
- Das README korrigieren: Der Schutz beruht auf Origin-Prüfung, Kategorie-Whitelist und Rate-Limit.

Abnahme:

1. In Chrome auf https://bundeshub.de/ ein Embed per „Laden und immer erlauben“ freigeben. Die Einwilligung wird gespeichert.
2. Eine lokale Testseite unter `http://localhost/csrf-test.html` in XAMPP ablegen. Sie enthält ein Formular mit `method="post"` an `https://bundeshub.de/wp-json/baukasten-consent/v1/consent`. In Chrome absenden. Erwartet: 403, kein Cookie `baukasten_consent`, keine neue Zeile im Log (Export auf dem Tab).

---

## P2: Business Cards

### 5. Kontaktformular „Baukasten Addon: Business Cards“ neu definieren

Ziel:

- `Forms::create()` legt das Formular mit dem vorgegebenen Inhalt an.
- Die Mail geht von der administrativen E-Mail-Adresse der Site an die E-Mail-Adresse der Visitenkarte, von der aus das Formular gesendet wurde.

**Formularinhalt.** Auf einer Site mit `de_DE` muss das angelegte Formular exakt diesem Text entsprechen:

```
<label>Name
    [text* your-name autocomplete:name]</label>

<label>E-Mail
    [email* your-email autocomplete:email]</label>

<label>Nachricht
    [textarea* your-message]</label>

[acceptance email-consent] Ich habe die <a href="/privacy">Datenschutzerklärung</a> und die <a href="/terms">Nutzungsbedingungen</a> gelesen und willige in die Verarbeitung meiner personenbezogenen Daten ein. [/acceptance]

[turnstile]

[submit "Nachricht senden"]
```

Umsetzung in `Forms::form_body()`:

- Die englischen Quelltexte bleiben übersetzbar. Die Übersetzung erfolgt über die `.po`-Datei, damit der Text bei der Anlage auf `de_DE` dem Vorgabetext entspricht:

  | Quelltext | Deutsch |
  |---|---|
  | `Name` | `Name` |
  | `Email` | `E-Mail` |
  | `Message` | `Nachricht` |
  | `Send message` | `Nachricht senden` |
  | `I have read the %1$sprivacy policy%2$s and the %3$sterms%4$s and consent to the processing of my personal data.` | `Ich habe die %1$sDatenschutzerklärung%2$s und die %3$sNutzungsbedingungen%4$s gelesen und willige in die Verarbeitung meiner personenbezogenen Daten ein.` |

  Der Einwilligungssatz ist neu, der alte Quelltext entfällt.
- `PLACEHOLDER_PRIVACY` wird `/privacy`, `PLACEHOLDER_TERMS` wird `/terms`.
- `localise_consent_links()` ersetzt diese Anker wie bisher pro Karte über `Legal_Links::url()` und entlinkt sie, wenn keine Seite hinterlegt ist.
- Neu: Außerhalb von Karten-Routen ersetzt `localise_consent_links()` die Anker nur im Plugin-Formular (`WPCF7_ContactForm::get_current()->id() === Forms::plugin_form_id()`). Die URLs stammen dann aus Login Legal Pages, falls aktiv, sonst aus `get_privacy_policy_url()`. Ohne Ziel wird der Anker entlinkt. So entsteht kein Link auf eine nicht existierende Seite `/privacy`.
- Die Zeile `[turnstile]` steht zwischen Einwilligung und Button. Ohne eingerichtetes CF7-Turnstile registriert CF7 den Tag mit `__return_empty_string`, er gibt also nichts aus und lädt nichts (`modules/turnstile/turnstile.php`). Mit eingerichtetem Turnstile setzt CF7 das Widget ohnehin in jedes Formular ein. Der Tag legt dann nur die Position fest.
- Den Text der Fehlermeldung in `require_consent()` an den neuen Einwilligungssatz angleichen (personenbezogene Daten statt E-Mail-Adresse).

**Empfänger.** Die Kartenvorlage `templates/card-view.php` läuft ohne The Loop. `in_the_loop()` ist deshalb `false`, und CF7 setzt `_wpcf7_container_post` auf 0. Überschreiben lässt sich das Feld nicht, weil CF7 die Rückgabe von `wpcf7_form_hidden_fields` mit `+=` zusammenführt und bestehende Keys dabei Vorrang haben. Daraus folgt:

- Über `wpcf7_form_hidden_fields` auf Karten-Routen ein eigenes Feld `_bkbc_card` mit der Karten-ID ausgeben.
- Über `wpcf7_special_mail_tags` zwei Mail-Tags registrieren:
  - `[_bkbc_card_email]`: das erste nicht leere Feld aus `email_work`, `email_priv`, in derselben Reihenfolge wie `templates/partials/quick-actions.php`.
  - `[_bkbc_card_title]`: der Titel der Karte.
- Die Karten-ID ist vom Client geliefert. Sie gilt nur, wenn alle Bedingungen erfüllt sind:
  - Post-Typ `baukasten_card`, Status `publish`
  - `cf7_form_id` der Karte entspricht der ID des abgesendeten Formulars
  - bei aktivem Content-Visibility-Addon: die Karte ist nicht privat, oder der Absender ist eingeloggt
- Ist eine Bedingung nicht erfüllt oder hat die Karte keine E-Mail-Adresse, liefert `[_bkbc_card_email]` `get_bloginfo( 'admin_email' )`. So bleibt keine Einsendung unzugestellt, und eine manipulierte ID erreicht nur den Admin.
- Der Mail-Tag endet auf `_email`. Der CF7-Konfigurationsvalidator setzt dafür `example@example.com` ein und meldet im Empfängerfeld keinen Fehler (`includes/config-validator/mail.php`).

**Mail-Konfiguration** in `Forms::mail()`:

| Eigenschaft | Wert |
|---|---|
| `sender` | `[_site_title] <[_site_admin_email]>` |
| `recipient` | `[_bkbc_card_email]` |
| `subject` | unverändert: „Business card enquiry via [_site_title]“ bzw. die deutsche Übersetzung |
| `body` | wie bisher, zusätzlich vor der Signatur die Zeile `Karte: [_bkbc_card_title]` (übersetzbar) |
| `additional_headers` | `Reply-To: [your-email]` |
| `mail_2` | inaktiv |

`mail_host()` entfällt.

Ist die Admin-Adresse nicht auf `bundeshub.de`, meldet der CF7-Validator „Absender-E-Mail-Adresse gehört nicht zur Domain der Website“. Die Mail wird dann voraussichtlich als Spam eingestuft oder abgewiesen. Das ist eine Aufgabe für den Webmaster (Dokument 3b). Das README von Business Cards soll diese Voraussetzung nennen.

**Bestehendes Formular.** Auf bundeshub.de kann das Formular aus Version 1.0.0 schon existieren, und `create()` lehnt dann ab. Der Tab bekommt deshalb einen zweiten Button „Vorlage erneut anwenden“. Er arbeitet so:

- Er ist nur sichtbar, wenn `plugin_form_id()` größer 0 ist.
- Er überschreibt `form`, `mail`, `mail_2` und `additional_settings` des Plugin-Formulars mit der Vorlage.
- Vor dem Überschreiben fragt eine Bestätigung nach.
- Bei einem Update geschieht das nie automatisch, weil der Webmaster das Formular bearbeitet haben kann.

Abnahme in Chrome auf https://bundeshub.de/:

1. Unter Kontakt → Kontaktformulare den Inhalt des Formulars „Baukasten Addon: Business Cards“ mit dem Vorgabetext vergleichen. Der Konfigurationsvalidator meldet keinen Fehler, ausgenommen die Absender-Domain, solange Dokument 3b nicht erledigt ist.
2. Eine Testkarte des Webmasters mit dessen Adresse in `email_work` im Profil „Test (ausgeloggt)“ aufrufen. Die Links im Einwilligungssatz zeigen auf die hinterlegten Rechtstexte, nicht auf `/privacy` oder `/terms`.
3. Das Formular absenden, nach Freigabe durch den Webmaster. Die Mail kommt an der Kartenadresse an, Absender ist die Admin-Adresse, Reply-To die eingegebene Adresse, der Body nennt den Kartentitel.
4. Lokal eine Einsendung mit manipuliertem `_bkbc_card` (fremde, private oder formularlose Karte) absenden. Die Mail geht an die Admin-Adresse.

### 6. Turnstile-Sonderbehandlung zurückbauen, CF7-Assets an Form Privacy übergeben

Stand in 1.0.0:

- `Forms::CF7_HANDLES` enthält `cloudflare-turnstile`.
- `match_assets_to_card()` dequeued die CF7-Handles auf Karten ohne Formular.
- `README.md` (Abschnitt Consent Blocking Engine) und `readme.txt` (FAQ) beschreiben das Zusammenspiel von Turnstile mit der Consent Blocking Engine.

Umsetzung:

- In `match_assets_to_card()` den Dequeue-Zweig überspringen, wenn `\Baukasten\FormPrivacy\Assets::manages_cf7_assets()` existiert und `true` liefert. Das Laden der Assets auf Karten mit Formular bleibt, weil F1 dort über `wpcf7_shortcode_callback` ohnehin lädt und ein doppeltes Enqueue folgenlos ist.
- Ohne Form Privacy bleibt der bisherige Dequeue einschließlich `cloudflare-turnstile` als Fallback. Sonst käme bei eingerichtetem Turnstile das Cloudflare-Skript auf jede Karte.
- Die Turnstile-Passagen in `README.md` und `readme.txt` ersetzen. Neuer Inhalt:
  - Spam-Schutz kommt aus Form Privacy (F9), ohne Drittanbieter.
  - Der Tag `[turnstile]` in der Vorlage bleibt ohne Turnstile-Schlüssel wirkungslos.
  - Wer Turnstile einrichtet, bindet einen Dritten ein, den die Consent Blocking Engine bis zur Einwilligung blockiert.

Abnahme in Chrome: Eine Karte ohne Formular und eine Karte mit Formular aufrufen. In beiden Fällen gibt es keinen Request an `challenges.cloudflare.com`. CF7-Dateien laden nur bei der Karte mit Formular.

---

## P2: Consent Blocking Engine und Härtung

### 7. Consent Blocking Engine aufteilen

Das Addon verbindet zwei Aufgaben:

- Einwilligungssteuerung: `Blocking_*` (ohne Emoji), `Frontend`, `Consent_State`, `Consent_Log`, `REST_Controller`, `Inventory`
- Härtung ohne Einwilligung: `Privacy_Audit`, `Hardening`, `Blocking_Emoji`, `wp baukasten harden`

Empfehlung: Die Härtung in ein eigenes Addon „Site Hardening“ (`baukasten-site-hardening`, Tab-Position 35) auslagern. Die Consent Blocking Engine behält nur die Einwilligungslogik. Die Empfehlungen 11, 12 und 14 gehören dann in das neue Addon.

Migration:

- Die Optionen der Härtungsmaßnahmen aus `baukasten_consent_settings` einmalig in die neue Option übernehmen.
- Bis beide Addons aktualisiert sind, dürfen Hooks nicht doppelt registriert werden.

Effekt auf bundeshub.de: Solange nichts von Dritten geladen wird, kann der Webmaster die Consent Blocking Engine deaktivieren. Damit entfallen Frontend-JavaScript, CSS, Cookie, REST-Route und Log-Tabelle, und die Härtung bleibt trotzdem aktiv.

### 8. Frontend-Assets nur bei Bedarf laden

Falls Empfehlung 7 zurückgestellt wird: `Frontend::enqueue()` lädt auf jeder Seite `consent-bootstrap.js` (6,8 KB, im Head, blockierend) und `blocked.css`. Beides nur laden, wenn eine der beiden Bedingungen gilt:

- `Inventory::all()` enthält einen Eintrag mit externem Host.
- Die Anfrage enthält ein Embed, bei Einzelansichten geprüft mit `has_block( 'core/embed' )` oder per oEmbed-URL im Inhalt.

Für Sonderfälle eine Option „immer laden“ vorsehen.

### 9. Inventory nur im Scan-Modus

`Inventory::collect()` läuft bei jedem Frontend-Aufruf und liest `baukasten_consent_handles`. Die Option ist nicht autoloaded, das kostet also eine zusätzliche Datenbankabfrage pro Seitenaufruf.

Erfassung beschränken auf:

- `current_user_can( 'manage_options' )`
- ein zeitlich begrenztes Scan-Flag als Transient, das auf dem Tab gestartet wird

### 10. Speculative Loading nicht mehr blockieren

`block_speculative_loading` ist standardmäßig aktiv und steht in `Hardening::MEASURES`. Speculation Rules laden nur Same-Origin-URLs vor, in der Core-Voreinstellung erst beim Klickbeginn. Es fließen keine Daten an Dritte.

Umsetzung: Default auf `false`, Eintrag aus `MEASURES` entfernen. Die Option selbst bleibt.

Ein neuer Default ändert gespeicherte Einstellungen nicht. Auf bundeshub.de muss der Schalter deshalb manuell umgelegt werden (Dokument 3a).

### 11. Hardening nur über den Button

Vorgabe:

- `blog_public` bleibt in `Hardening::OPTIONS`.
- Die Routine läuft nie bei Installation, Aktivierung oder Update, sondern nur über den Button „DSGVO-Härtung & Standardwerte anwenden“ (englisch „Apply privacy hardening and defaults“) auf dem Tab.

Befund in 1.0.0: Die Vorgabe ist erfüllt. `activate()` legt nur die Default-Einstellungen an und installiert die Log-Tabelle. `run_privacy_hardening_bulk_action()` wird ausschließlich aus `Settings_Tab` und `CLI` aufgerufen. Der Bestätigungsdialog nennt die Suchmaschinen ausdrücklich.

Umsetzung:

- Im README und in `readme.txt` den Satz ergänzen: „The hardening routine never runs on its own — not on activation, not on update — only from the button on the settings tab or the WP-CLI command.“
- Einen Integrationstest aufnehmen (Empfehlung 17): Nach Aktivierung und Update sind `blog_public`, `users_can_register` und `default_comment_status` unverändert.
- Der WP-CLI-Befehl `wp baukasten harden` bleibt als gleichwertiger manueller Auslöser. Soll ausschließlich der Button auslösen, wird `CLI::register()` für diesen Befehl entfernt.
- Diese Vorgabe gilt nach Empfehlung 7 auch für das Addon „Site Hardening“.

### 12. Härtungsmaßnahmen gegen User-Enumeration und XML-RPC

Keine Klasse deckt bisher ab, dass der Slug des Admin-Kontos öffentlich ist. Die Maßnahmen gehören als einzelne Optionen in `Privacy_Audit` bzw. nach Empfehlung 7 in „Site Hardening“. Sie sind standardmäßig an und Teil der Routine:

| Maßnahme | Hook |
|---|---|
| `/wp/v2/users` und `/wp/v2/users/(?P<id>…)` für ausgeloggte Anfragen entfernen | `rest_endpoints` |
| Autorenarchive und `?author=N` mit 404 beantworten | `template_redirect` mit `is_author()` |
| `author_name` und `author_url` aus oEmbed-Antworten entfernen | `oembed_response_data` |
| Users-Sitemap abschalten | `wp_sitemaps_add_provider` für `users` |
| XML-RPC-Methoden leeren, `X-Pingback`-Header und RSD-Link entfernen | `xmlrpc_methods`, `wp_headers`, `remove_action( 'wp_head', 'rsd_link' )` |
| Anwendungspasswörter abschalten (optional, standardmäßig aus) | `wp_is_application_passwords_available` |

Abnahme per `curl`, ausgeloggt:

- `https://bundeshub.de/wp-json/wp/v2/users` liefert 404 (`rest_no_route`).
- `https://bundeshub.de/?author=1` liefert 404.
- Die oEmbed-Antwort der Startseite enthält keine Autorfelder.

### 13. Löschfrist für das Consent-Log

`Consent_Log` schreibt nur neue Zeilen und löscht nie. `anonymous_id` ist ein pseudonymes Personendatum.

Umsetzung:

- Täglicher Cron-Job, der Zeilen löscht, die älter als eine einstellbare Frist sind.
- Untergrenze der Frist ist die Cookie-Laufzeit von 12 Monaten plus eine Nachweisfrist. Standard 24 Monate.

Außerdem die README-Aussage zum Rate-Limit präzisieren. `wp_hash( REMOTE_ADDR )` steht fünf Minuten lang als Transient-Name in `wp_options`. „Weder gespeichert noch geloggt“ trifft das nicht genau.

---

## P2: Login Legal Pages

### 14. Rate-Limit für Anmeldungen

`Login_URL::claim_request()` leitet nur einfache GET-Anfragen von `wp-login.php` auf `/login/` um. POST-Anfragen an `wp-login.php` verarbeitet WordPress weiterhin. Ein Rate-Limit nur auf `/login/` wäre deshalb umgehbar. Es sitzt an der Authentifizierung und greift so für `/login/`, `wp-login.php`, XML-RPC und Anwendungspasswörter gleichermaßen.

| Teil | Hook | Verhalten |
|---|---|---|
| Fehlversuche zählen | `wp_login_failed` | Zähler im Transient `baukasten_login_rl_{wp_hash( IP )}` erhöhen, Fenster 15 Minuten |
| Sperren | `authenticate`, Priorität 100 | Bei überschrittener Schwelle `WP_Error` zurückgeben, auch bei korrektem Passwort. Priorität 100 ist nötig, weil `wp_authenticate_username_password()` auf Priorität 20 einen vorher gesetzten `WP_Error` ignoriert und bei gültigen Zugangsdaten den User liefert. |
| Zurücksetzen | `wp_login` | Zähler der IP nach erfolgreicher Anmeldung löschen |
| Passwort-Reset drosseln | `lostpassword_post` | Eigener Zähler, 3 Anfragen pro Stunde und IP |

Die Sperre wird gestaffelt:

- 5 Fehlversuche in 15 Minuten lösen eine Sperre von 15 Minuten aus.
- Jede weitere Sperre innerhalb von 24 Stunden verdoppelt die Dauer, höchstens bis 24 Stunden.
- Schwelle, Fenster und Grunddauer sind auf dem Tab einstellbar. Die Funktion ist standardmäßig an.

Vorgaben:

- Nur pro IP sperren, nie pro Benutzername. Eine Kontosperre würde es jedem erlauben, den Admin auszusperren.
- Die Fehlermeldung ist generisch und für existierende und nicht existierende Benutzernamen gleich.
- Für XML-RPC und REST bekommt der `WP_Error` `array( 'status' => 429 )`.
- Die IP stammt ausschließlich aus `REMOTE_ADDR`. `X-Forwarded-For` wird nur über den Filter `baukasten/login/client_ip` ausgewertet und nur für einen bekannten Reverse-Proxy.
- Kein `sleep()`, weil das PHP-FPM-Worker blockiert.
- Die IP wird nur gehasht gespeichert. Abgelaufene Transients räumt Core täglich ab.
- Two Factor 0.16.0: Prüfen, ob fehlgeschlagene Codes des zweiten Faktors `wp_login_failed` auslösen, und das Ergebnis im README festhalten.
- Den README-Absatz „This is not a security feature“ präzisieren: Die URL ist keine Sicherheitsfunktion, das Rate-Limit schon.
- Nach Empfehlung 7 gehört die Funktion ins Addon „Site Hardening“. Der Name „Login Legal Pages“ passt dann nicht mehr.

Abnahme:

- Vollständig lokal: sechs POST-Anfragen mit falschem Passwort, abwechselnd an `/login/` und `wp-login.php`. Die sechste zeigt die Sperrmeldung, eine Anmeldung mit korrektem Passwort wird danach ebenfalls abgewiesen, und nach Ablauf funktioniert sie wieder.
- Auf Produktion nur ein Fehlversuch im Profil „Test (ausgeloggt)“, danach kontrollieren, dass der Transient existiert. Developer und Webmaster teilen sich dieselbe IP. Ein vollständiger Sperrtest würde den Webmaster aussperren.

---

## P3: Release und Pflege

### 15. `Update URI` setzen, solange die Plugins nicht auf wordpress.org liegen

Ohne diesen Header fragt WordPress bei wordpress.org nach Updates für die Slugs `baukasten`, `baukasten-content-visibility` usw. Registriert jemand anderes einen davon zuerst, bietet bundeshub.de dessen Code als Update an.

Umsetzung: In jedem Plugin-Header `Update URI: https://github.com/trstnbde/baukasten` setzen, auch in Form Privacy und Two-Factor Approval. Den Eintrag bei Veröffentlichung auf wordpress.org entfernen.

### 16. Two-Factor Approval ins Repository aufnehmen

„Baukasten Addon: Two-Factor Approval“ läuft produktiv, fehlt aber im Repository, in der README und im Developer Guide. Den Quellcode liefert der Webmaster. Danach nach denselben Konventionen aufnehmen: Linting, CI, Übersetzung, README.

### 17. Integrationstests

Die CI führt nur PHPCS und `php -l` aus. Integrationstests mit wp-env oder der WordPress-Testsuite sollen diese Fälle abdecken:

- Eine anonyme Anfrage an Embed, oEmbed und REST eines privaten Beitrags liefert 404 (Empfehlung 1).
- Ein Consent-POST mit fremdem `Origin` oder form-encoded liefert 403 (Empfehlung 4).
- `[_bkbc_card_email]` löst gültige, fremde, private und formularlose Karten korrekt auf (Empfehlung 5).
- Aktivierung und Update ändern keine Core-Optionen (Empfehlung 11).
- Das Rate-Limit greift über `/login/` und `wp-login.php` hinweg und sperrt auch bei korrektem Passwort (Empfehlung 14).
- Form Privacy: F3 (keine Kontakte), F4 (Meta-Whitelist), F5 (Löschfrist), F9 (Honeypot).
