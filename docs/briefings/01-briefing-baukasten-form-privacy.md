# Briefing: Baukasten Addon „Form Privacy“

Das ist Dokument 1 von 3 und wird als erstes umgesetzt. Danach folgen „Baukasten: Empfehlungen für Anpassungen“ und die „Manuellen Restarbeiten“ (je ein Dokument für Developer und Webmaster).

| | |
|---|---|
| Arbeitstitel | Baukasten Addon: Form Privacy |
| Slug | `baukasten-form-privacy` |
| Ausführung | Developer (Claude Code) |
| Freigaben und Deployment | Webmaster |
| Zielsystem | https://bundeshub.de/: WordPress 7.1.2, PHP 8.4, Twenty Twenty-Five, Contact Form 7 6.1.7, Flamingo 2.6.4 |
| Geprüfte Quellen | Contact Form 7 Tag `v6.1.7`, Flamingo Tag `v2.6.4`, Baukasten `main` (Commit `47577f7`) |

Alle Hooks in diesem Dokument sind im Quellcode dieser Versionen belegt.

## Arbeitsweise und Tests

Die Regeln hier gelten auch für die Dokumente 2 und 3a.

1. **Entwicklung.** Umsetzung und erste Tests laufen lokal in der XAMPP-Umgebung aus `docs/DEVELOPER-GUIDE.md`, Abschnitt 4. Für jedes Plugin wird `composer lint` ausgeführt.
2. **Build.** `php -d extension=zip bin/build.php <slug>` erzeugt das Paket. Version im Plugin-Header und `Stable tag` in `readme.txt` müssen übereinstimmen.
3. **Deployment.** Der Webmaster installiert die ZIP-Dateien auf bundeshub.de. Der Developer schreibt Code nie direkt auf den Server.
4. **Abnahme.** Nach dem Deployment testet der Developer in Chrome mit Claude in Chrome auf https://bundeshub.de/. Dafür gibt es zwei Zugänge:
   - Eingeloggte Prüfungen laufen im Chrome-Profil des Webmasters, dessen Sitzung besteht. Der Developer meldet sich dort weder ab noch neu an, weil der Login Two Factor verlangt.
   - Ausgeloggte Prüfungen laufen im Chrome-Profil „Test (ausgeloggt)“ oder per `curl` von der Kommandozeile.
5. **Protokoll.** Jedes Abnahmekriterium wird mit Ergebnis, Datum und bei sichtbaren Ergebnissen mit Screenshot festgehalten. Das Protokoll liegt in `docs/acceptance/form-privacy.md`.
6. **Risiken.** Ein Test, der Daten löscht, Mails an Dritte auslöst oder den Webmaster aussperren kann, läuft auf Produktion nur nach dessen ausdrücklicher Freigabe.

## Entscheidung: Flamingo bleibt und wird eingehegt

Flamingo wird nicht ersetzt. Das Addon verhindert unnötige Datenerhebung an der Quelle, schaltet das Adressbuch ab, setzt eine Löschfrist durch und ergänzt, was für die DSGVO fehlt. Dafür gibt es drei Gründe:

1. **Die Integration pflegt CF7.** CF7 schreibt selbst in Flamingo, über `wpcf7_flamingo_submit()` in `modules/flamingo.php` auf `wpcf7_submit`, und der CF7-Autor hält diese Anbindung aktuell. Ein Ersatz müsste sie nachbauen und bei jedem CF7-Release nachziehen.
2. **Die Filter reichen aus.** Jede Datenschutzlücke lässt sich über vorhandene Filter schließen, bevor Daten entstehen. Nachträgliches Aufräumen braucht es nur für die Löschfrist und den Altbestand.
3. **Ein Ersatz bringt nichts.** Er müsste Admin-Liste, Detailansicht, Suche, Spam/Ham, CSV-Export und Eraser neu bauen, also mehr eigenen Code, ohne funktionalen Gewinn.

Die Entscheidung wird neu getroffen, wenn einer dieser Fälle eintritt:

- Flamingo wird nicht mehr gepflegt.
- `wpcf7_flamingo_*` oder `flamingo_add_*` entfallen.
- Eine Anforderung sprengt Flamingos Datenmodell, etwa verschlüsselte Ablage.

## Befund

| Befund | Stelle | Folge |
|---|---|---|
| Das Adressbuch sammelt automatisch: aus CF7-Einsendungen mit Status `mail_sent`, aus WordPress-Nutzern (`profile_update`, `user_register`, bei Aktivierung alle) und aus freigegebenen Kommentaren (bei Aktivierung die letzten 20). | CF7 `modules/flamingo.php`, Flamingo `includes/user.php`, `includes/comment.php` | Kontaktdatensätze ohne Zweck und ohne Löschfrist |
| Einsendungen werden nie automatisch gelöscht. Nur Spam wandert nach `FLAMINGO_MOVE_TRASH_DAYS` (30 Tage) in den Papierkorb. | `flamingo_schedule_move_trash()` in `includes/functions.php` | unbefristete Speicherung |
| Zu jeder Einsendung werden Metadaten gespeichert: `remote_ip`, `user_agent`, `url`, `date`, `time`, `post_*`, `site_*`, `user_login`, `user_email`, `user_display_name`. | `wpcf7_flamingo_submit()` | IP und User-Agent dauerhaft in `_meta` |
| Feldwerte liegen dreifach vor: in `_field_{name}`, in `_fields` und als Volltext in `post_content`. | `Flamingo_Inbound_Message::save()` | Löschen nur vollständig über `wp_delete_post( $id, true )` |
| Flamingo registriert Eraser, aber keinen Exporter. | `admin/includes/privacy.php` | Das Core-Werkzeug kann keine Auskunft nach Art. 15 DSGVO zu diesen Daten geben. |
| CF7 lädt sein JavaScript und CSS auf jeder Seite. Bei eingerichtetem Dienst laden Turnstile (`cloudflare-turnstile`) und reCAPTCHA (`google-recaptcha`) ebenfalls seitenweit. `wpcf7_load_js` hat auf diese beiden keinen Einfluss. | `includes/controller.php`, `modules/turnstile/turnstile.php`, `modules/recaptcha/recaptcha.php` | unnötige Requests, bei Captcha-Diensten an Dritte |
| Das Formular hat keinen Spam-Schutz ohne Drittanbieter. | – | Spam-Schutz nur über Akismet, reCAPTCHA oder Turnstile möglich, alle mit Datenübermittlung |

## Funktionen

### F1: CF7-Assets nur auf Seiten mit Formular

- `wpcf7_load_js` und `wpcf7_load_css` auf `false` setzen.
- Auf `wpcf7_shortcode_callback` (feuert in `wpcf7_contact_form_tag_func()`) `wpcf7_enqueue_scripts()` und `wpcf7_enqueue_styles()` aufrufen. Twenty Twenty-Five ist ein Block-Theme und rendert das Template vor `wp_head`, die Assets landen also regulär im Head bzw. Footer. Der CF7-Block `contact-form-7/contact-form-selector` speichert einen Shortcode und läuft über denselben Pfad.
- Für klassische Themes zusätzlich auf `wp_enqueue_scripts` bei `is_singular()` vorab prüfen: `has_shortcode( $post->post_content, 'contact-form-7' )` oder `has_block( 'contact-form-7/contact-form-selector' )`.
- Auf allen Seiten, auf denen F1 keine CF7-Assets lädt, auf `wp_enqueue_scripts` mit Priorität 20 die Handles `cloudflare-turnstile` und `google-recaptcha` dequeuen.
- Schnittstelle für Business Cards: eine öffentliche statische Methode `\Baukasten\FormPrivacy\Assets::manages_cf7_assets(): bool`. Sie gibt `true` zurück, wenn F1 aktiv ist. Business Cards überlässt F1 dann die Asset-Steuerung (Dokument 2, Empfehlung 6).

### F2: IP-Adresse nicht erheben

- `wpcf7_remote_ip_addr` gibt `''` zurück.
- Nebenwirkungen, die im Tab dokumentiert werden:
  - IP-Regeln der Disallowed List greifen nicht mehr.
  - `[_remote_ip]` bleibt in Mails leer.
  - Der `posted_data_hash` verliert die IP als Bestandteil.

### F3: Flamingo-Adressbuch deaktivieren

Diese Funktion ist fest eingebaut und hat keinen Schalter. Sobald das Addon aktiv ist und `Flamingo_Contact` existiert, gilt:

| Teil | Hook | Verhalten |
|---|---|---|
| Keine neuen Kontakte | `flamingo_add_contact` | `email` auf `''` setzen. `Flamingo_Contact::add()` bricht dann ab. Das gilt für CF7, Nutzer, Kommentare und die Sammelroutinen bei der Aktivierung von Flamingo. `wpcf7_flamingo_submit()` behandelt den leeren Rückgabewert bereits (Kontakt-ID 0). |
| Adressbuch aus dem Menü | `flamingo_map_meta_cap` | `flamingo_edit_contacts` und `flamingo_edit_contact` auf `do_not_allow` mappen. WordPress entfernt dann den Untermenüpunkt „Adressbuch“, und der Hauptmenüpunkt „Flamingo“ führt auf „Eingegangene Nachrichten“, weil der erste erlaubte Untermenüpunkt zum Ziel wird (`wp-admin/includes/menu.php`). |
| Löschrechte bleiben | – | `flamingo_delete_contact(s)` nicht mappen. Sonst verweigert Flamingos Eraser (`flamingo_privacy_contact_eraser()`) das Löschen von Restbeständen. |
| Altbestand | F6 | Kontakte und Kontakt-Tags werden über die Bereinigung gelöscht, nicht automatisch bei der Aktivierung. |

Ein direkter Aufruf von `admin.php?page=flamingo` endet mit der Core-Meldung zu fehlenden Rechten. Das ist gewollt.

### F4: Datenminimierung bei Einsendungen

| Teil | Hook | Verhalten |
|---|---|---|
| Metadaten auf Whitelist | `wpcf7_flamingo_inbound_message_parameters`, Priorität 20, also nach dem Turnstile-Modul (Priorität 10) | `meta` auf die Whitelist reduzieren. `akismet`, `recaptcha` und die Turnstile-Einträge in `meta` leeren. |
| Spam nicht speichern | `wpcf7_flamingo_submit_if` | `spam` aus dem Array entfernen |
| Speicherung ganz aus | `wpcf7_flamingo_submit_if` | leeres Array zurückgeben |

Standard-Whitelist: `date`, `time`, `url`, `post_id`, `post_title`.

Die CF7-eigenen Mittel ergänzen das und werden im Tab erklärt: `do_not_store: true` pro Formular und die Form-Tag-Option `do-not-store` pro Feld.

### F5: Löschfrist

- Täglicher Cron-Hook `baukasten/form_privacy/purge`. Er wird bei der Aktivierung geplant und bei der Deaktivierung entfernt.
- Die Abfrage läuft auf `flamingo_inbound` mit `post_status` explizit gleich `publish`, `flamingo-spam` und `trash`. `any` reicht nicht, weil der Spam-Status `exclude_from_search` hat.
- `date_query` auf `post_date_gmt`, älter als die Frist.
- Stapel zu je 100, `wp_delete_post( $id, true )`, Zeitbudget pro Lauf etwa 20 Sekunden. Der Rest wird im nächsten Lauf erledigt.
- Frist in Tagen, Standard 90, ganze Zahl ≥ 1.
- Voraussetzung ist ein Server-Cronjob, weil WP-Cron bei wenig Traffic unregelmäßig läuft. Das ist eine Aufgabe für den Webmaster.

### F6: Bereinigung des Altbestands

Ein Button auf dem Tab und `wp baukasten form-privacy clean` rufen dieselbe Routine auf. Sie arbeitet nach dem Muster von `Hardening` in der Consent Blocking Engine: vergleichen, schreiben, pro Schritt melden, ob sich etwas geändert hat, und bei wiederholtem Lauf ohne Änderung bleiben.

Schritte:

1. Alle `flamingo_contact`-Beiträge endgültig löschen, dazu alle Terme der Taxonomie `flamingo_contact_tag`.
2. Bei bestehenden Einsendungen `_meta` auf die Whitelist reduzieren und `_akismet` und `_recaptcha` leeren.
3. Die Löschfrist sofort anwenden.

Vor dem Lauf fragt eine Bestätigung nach, denn gelöschte Daten lassen sich nicht wiederherstellen. Die Routine läuft nie automatisch, weder bei der Aktivierung noch bei einem Update.

### F7: Exporter für Auskunftsersuchen

Über `wp_personal_data_exporters` einen Exporter registrieren. Er findet Einsendungen über `_from_email` und exportiert Betreff, Felder, Datum und die verbleibenden Metadaten. Er läuft paginiert, 50 Einträge pro Seite.

### F8: Baustein für die Datenschutzerklärung

`wp_add_privacy_policy_content()` bekommt einen Text, der aus den aktuellen Einstellungen erzeugt wird. Er nennt:

- welche Daten gespeichert werden
- dass keine IP-Adresse erhoben wird
- die Löschfrist in Tagen
- dass kein Adressbuch geführt wird
- dass kein externer Spam-Dienst eingesetzt wird

### F9: Honeypot und Zeitsperre

Spam-Schutz ohne externe Requests, ohne Cookie und ohne JavaScript.

Ausgabe über `wpcf7_form_elements`:

- Ein Textfeld `_bk_hp` als Honeypot, kein `type="hidden"`, weil Bots versteckte Felder oft auslassen.
- Das Feld wird per CSS aus dem sichtbaren Bereich geschoben, nicht mit `display: none`.
- Attribute: `tabindex="-1"`, `autocomplete="off"`, `aria-hidden="true"` am Wrapper.
- Ein Label „Dieses Feld leer lassen“ für den Fall, dass ein Screenreader das Feld doch erreicht.

Zeitstempel über `wpcf7_form_hidden_fields`:

- Ein verstecktes Feld `_bk_ts` mit dem Wert `{time}.{wp_hash( time . '|' . form_id )}`.
- Keine Höchstdauer. So funktioniert das Formular auch hinter einem Seiten-Cache, und lange geöffnete Tabs werden nicht abgewiesen.

Prüfung über `wpcf7_spam` (`$spam`, `$submission`), Priorität 8. CF7 selbst prüft auf Priorität 9 (Turnstile, reCAPTCHA) und 10 (Akismet, Disallowed List).

- Ist `$spam` bereits `true`, wird der Wert unverändert zurückgegeben.
- Ist `_bk_hp` nicht leer, gilt die Einsendung als Spam. Log über `$submission->add_spam_log( array( 'agent' => 'baukasten-form-privacy', 'reason' => 'honeypot' ) )`.
- Fehlt `_bk_ts`, ist die Signatur ungültig oder liegen weniger als `min_fill_seconds` zwischen Ausgabe und Absenden, gilt die Einsendung ebenfalls als Spam, mit dem jeweiligen `reason`.

Felder mit führendem Unterstrich übernimmt CF7 nicht in `posted_data` (`WPCF7_Submission::setup_posted_data()`). `_bk_hp` und `_bk_ts` erscheinen deshalb weder in Mails noch in Flamingo.

Grenze: Gegen gezielte, menschlich gesteuerte Einsendungen hilft das nicht. Erst wenn danach noch messbar Spam durchkommt, wird das CF7-eigene Turnstile-Modul erwogen. Diese Entscheidung trifft der Webmaster.

### F10: Einstellungs-Tab

Der Tab hat Position 60 und zeigt:

- Schalter für F1, F2, F9 und die Schalter aus F4
- die Löschfrist und die Mindestausfüllzeit
- die Meta-Whitelist als Checkboxen der Keys, die CF7 kennt
- die Anzahl gespeicherter Einsendungen, die älteste Einsendung, verbliebene Adressbuch-Kontakte und den nächsten Purge-Lauf
- einen festen Hinweis, dass das Adressbuch deaktiviert ist
- den Bereinigungs-Button aus F6

Wenn Flamingo fehlt, zeigt der Tab einen Hinweis und nur F1, F2 und F9.

## Einstellungen

Option `baukasten_form_privacy_settings`:

| Key | Typ | Standard |
|---|---|---|
| `assets_on_demand` | bool | `true` |
| `strip_ip` | bool | `true` |
| `store_submissions` | bool | `true` |
| `store_spam` | bool | `false` |
| `meta_whitelist` | string[] | `['date','time','url','post_id','post_title']` |
| `retention_days` | int | `90` |
| `honeypot` | bool | `true` |
| `min_fill_seconds` | int | `3` |

## Technische Vorgaben

- Header:
  - `Requires Plugins: baukasten, contact-form-7`
  - `Requires PHP: 8.1`
  - `Requires at least: 6.5`
  - `Update URI: https://github.com/trstnbde/baukasten` (Begründung in Dokument 2, Empfehlung 15)
- Flamingo ist optional. Jede Flamingo-Funktion wird mit `class_exists( 'Flamingo_Inbound_Message' )` bzw. `class_exists( 'Flamingo_Contact' )` abgesichert.
- Namespace `Baukasten\FormPrivacy`. Konventionen, Textdomain, deutsche Übersetzung und Build richten sich nach `docs/DEVELOPER-GUIDE.md`.
- Filter werden unbedingt registriert, wie in `Business_Cards\Forms::register()`. Nur Funktionsaufrufe in fremde Plugins werden abgesichert.
- Formulare speichern über `admin-post.php`, mit `check_admin_referer()` im Handler selbst.
- `uninstall.php` löscht nur die eigene Option und den Cron-Hook, keine Flamingo-Daten.
- Tabellen in README und Root-README um das neue Plugin ergänzen und die Tab-Position 60 im Developer Guide eintragen.

## Nicht im Umfang

- eigene Datenhaltung, eigene Admin-Liste, eigener Export
- Anbindung externer Spam-Dienste (Akismet, reCAPTCHA, Turnstile). F9 ersetzt sie.
- Verschlüsselung gespeicherter Einsendungen
- Änderungen an Business Cards. Die stehen in Dokument 2.

## Abnahmekriterien

Kriterien, deren Ergebnis im Browser sichtbar ist, prüft der Developer in Chrome auf https://bundeshub.de/. Die übrigen prüft er lokal oder per `curl`.

1. Eine Seite ohne Formular lädt keine Datei aus `wp-content/plugins/contact-form-7/`. Geprüft wird im Netzwerk-Tab mit `read_network_requests`.
2. Eine Seite mit Formular lädt die CF7-Dateien und sendet erfolgreich. Test im Profil „Test (ausgeloggt)“ mit einer Adresse des Webmasters.
3. Nach der Testeinsendung aus Kriterium 2 enthält die Einsendung in Flamingo nur die Whitelist-Metadaten, ohne `remote_ip` und `user_agent`.
4. Im Admin-Menü fehlt „Flamingo → Adressbuch“, und „Flamingo“ öffnet die eingegangenen Nachrichten. `admin.php?page=flamingo` zeigt die Meldung zu fehlenden Rechten.
5. Weder die Testeinsendung noch ein Profil-Update des Admin-Kontos erzeugen einen `flamingo_contact`. Prüfung lokal oder per SQL-Abfrage durch den Webmaster.
6. Eine Einsendung, deren `post_date` lokal auf Frist plus einen Tag zurückgesetzt wurde, ist nach `wp cron event run baukasten/form_privacy/purge` samt aller Meta-Einträge gelöscht.
7. Die Bereinigung meldet beim zweiten Lauf in jedem Schritt „keine Änderung“. Auf Produktion läuft sie nur nach Freigabe durch den Webmaster.
8. Der Core-Export unter Werkzeuge → Personenbezogene Daten exportieren enthält die Testeinsendung der angefragten Adresse.
9. Lokal werden drei Einsendungen als Spam abgewiesen: eine mit gefülltem `_bk_hp`, eine mit manipuliertem `_bk_ts`, eine innerhalb von `min_fill_seconds`. Der `spam_log` nennt jeweils den Grund. Eine reguläre Einsendung geht durch. `_bk_hp` und `_bk_ts` erscheinen weder in der Mail noch in Flamingo.
10. Lokal mit eingerichteten Turnstile-Testschlüsseln lädt eine Seite ohne Formular kein Skript von `challenges.cloudflare.com`.
11. Ohne Flamingo bleibt die Site fehlerfrei, und der Tab zeigt den Hinweis.
12. `composer lint` läuft ohne Fehler, `php -l` läuft auf PHP 8.1 bis 8.4 ohne Fehler.
