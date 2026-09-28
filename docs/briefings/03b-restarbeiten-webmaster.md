# Manuelle Restarbeiten: Webmaster

Das ist Dokument 3b von 3, für dich als Webmaster. Es enthält alles, was Zugang zum Hoster, eine Entscheidung oder eine Bestätigung im Backend verlangt. Der Developer (Claude Code) arbeitet parallel nach Dokument 3a. Beide Dokumente sind in dieselben Phasen A bis D gegliedert.

| | |
|---|---|
| Installation | https://bundeshub.de/, `/usr/www/users/cwyhqy/apps/wordpress` |
| Hoster | Hetzner Webhosting (Nameserver `your-server.de`), Verwaltung über konsoleH |
| DNS-Stand 28.09.2026 | SPF `v=spf1 +a +mx ?all`, kein DMARC-Eintrag, DKIM nicht geprüft |

---

## Phase A: Vor der Entwicklung

### A1. Vorbereitung

- [ ] Backup von Dateien und Datenbank ziehen. Einmal testweise prüfen, ob es sich zurückspielen lässt, zum Beispiel lokal in XAMPP.
- [ ] In Chrome ein zweites Profil „Test (ausgeloggt)“ anlegen, dort die Erweiterung Claude in Chrome installieren und bundeshub.de freigeben. In diesem Profil nie bei WordPress anmelden.
- [ ] Im eigenen Chrome-Profil bei https://bundeshub.de/login/ angemeldet bleiben, während der Developer arbeitet.
- [ ] Eine Testkarte in Business Cards anlegen, mit einer eigenen Adresse in `email_work`.
- [ ] Einen Testbeitrag anlegen, mit Beitragsbild und manuellem Auszug, und über Content Visibility auf privat stellen. Den Link dem Developer geben (Dokument 2, Empfehlung 1).
- [ ] Den Quellcode von „Baukasten Addon: Two-Factor Approval“ ins Repository legen oder dem Developer übergeben (Dokument 2, Empfehlung 16).

### A2. Reste von WP Super Cache entfernen

Zugang per SFTP bzw. SSH oder über den Dateimanager in konsoleH.

- [ ] Löschen, falls vorhanden:
  - `wp-content/advanced-cache.php`
  - `wp-content/wp-cache-config.php`
  - `wp-content/cache/`
- [ ] In `wp-config.php` die Zeile `define( 'WP_CACHE', true );` entfernen.
- [ ] In `.htaccess` einen eventuell vorhandenen Block `# BEGIN WPSuperCache … # END WPSuperCache` entfernen.

### A3. Die `wp-config.php` ergänzen

Die Zeilen oberhalb von `/* That's all, stop editing! */` einfügen bzw. ändern:

```php
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISALLOW_FILE_EDIT', true );
define( 'DISABLE_WP_CRON', true );
```

- [ ] `DISABLE_WP_CRON` erst setzen, wenn A4 eingerichtet ist.
- [ ] In konsoleH prüfen, ob in der PHP-Konfiguration `display_errors` aus ist.

### A4. Server-Cronjob einrichten

Ohne Seiten-Cache und bei wenig Traffic läuft WP-Cron unregelmäßig. Davon hängen Updates, Flamingos Spam-Aufräumung und die Löschfrist von Form Privacy ab.

- [ ] In der Cronjob-Verwaltung von konsoleH einen Job alle 15 Minuten anlegen:

  ```
  */15 * * * * wget -q -O /dev/null "https://bundeshub.de/wp-cron.php?doing_wp_cron"
  ```

  Wenn der Tarif keine Cronjobs enthält, übernimmt ein externer Cron-Dienst denselben Aufruf.
- [ ] Danach A3 abschließen (`DISABLE_WP_CRON`).

### A5. Die `.htaccess` ergänzen

Der Block kommt oberhalb von `# BEGIN WordPress`, weil WordPress seinen eigenen Block überschreibt:

```apache
<IfModule mod_headers.c>
Header always set Strict-Transport-Security "max-age=300"
Header always set X-Content-Type-Options "nosniff"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=()"
Header always set Content-Security-Policy "frame-ancestors 'self'"
</IfModule>

<Files "xmlrpc.php">
Require all denied
</Files>
```

- [ ] HSTS schrittweise einführen. Das Multi-Domain-Addon bedient mehrere Domains, und jede davon braucht ein gültiges Zertifikat. Erst `max-age=300` setzen, dann alle Domains in Chrome öffnen, danach auf `31536000` erhöhen. `includeSubDomains` und `preload` nur, wenn alle Subdomains dauerhaft HTTPS liefern.
- [ ] Den Developer bitten, die Header-Prüfung aus Dokument 3a, A1 zu wiederholen.

### A6. E-Mail-Versand vorbereiten

Das Kontaktformular der Visitenkarten sendet künftig von der Administrator-E-Mail-Adresse (Dokument 2, Empfehlung 5). Die Adresse muss deshalb zur Domain passen, sonst landen Mails im Spam oder werden abgewiesen.

- [ ] In konsoleH ein Postfach unter `bundeshub.de` anlegen oder ein vorhandenes verwenden.
- [ ] Unter Einstellungen → Allgemein diese Adresse als „Administrator-E-Mail-Adresse“ eintragen. WordPress übernimmt sie erst, wenn du den Bestätigungslink in der Mail an die neue Adresse anklickst.
- [ ] DMARC ergänzen: einen TXT-Eintrag für `_dmarc.bundeshub.de` mit `v=DMARC1; p=none; rua=mailto:<Postfach unter bundeshub.de>`. Nach einigen Wochen mit sauberen Berichten auf `p=quarantine` erhöhen.
- [ ] DKIM in konsoleH aktivieren, falls noch nicht aktiv.
- [ ] SPF von `?all` auf `~all` verschärfen. Vorher bei Hetzner klären, ob der Mailversand aus PHP über Server läuft, die `+a` und `+mx` abdecken. Sonst würden eigene Mails abgewertet.

### A7. Einstellungen und Konto

- [ ] Unter Einstellungen → Allgemein einen Titel vergeben. Aktuell steht dort „Untitled blog“, und der Titel erscheint im Mail-Absender, im Betreff und in der oEmbed-Antwort.
- [ ] Den `user_nicename` ändern, damit der öffentliche Slug nicht dem Login-Namen entspricht. Das geht nicht über die Profilseite.
  - Per WP-CLI:

    ```bash
    wp user update 1 --user_nicename=redaktion
    ```

  - Oder in phpMyAdmin:

    ```sql
    UPDATE <tabellenpräfix>users SET user_nicename = 'redaktion' WHERE ID = 1;
    ```

    Das Präfix steht in `wp-config.php` in `$table_prefix`.
- [ ] Unter Profil einen „Öffentlichen Namen“ wählen, der nicht der Login-Name ist.
- [ ] Prüfen, dass die Backup-Codes von Two Factor außerhalb der Site liegen.
- [ ] Optional in konsoleH `opcache.interned_strings_buffer` auf 16 erhöhen, falls der Tarif eigene PHP-Werte erlaubt. Der Puffer ist zu 99,34 % belegt.

### A8. Entscheidungen

Grundlage ist die Bestandsaufnahme des Developers aus Dokument 3a, A2. Die Ergebnisse gibst du an den Developer zurück.

- [ ] **Suchmaschinen.** Sollen Startseite, Landingpages und Visitenkarten gefunden werden? Falls ja, unter Einstellungen → Lesen den Haken bei „Suchmaschinen davon abhalten …“ entfernen. Den Button „DSGVO-Härtung & Standardwerte anwenden“ danach nicht mehr klicken: Er setzt den Haken wieder, weil `blog_public` Teil der Routine bleibt.
- [ ] **Einsendungen speichern.** Ja oder nein. Falls ja: Löschfrist in Tagen, Standard 90.
- [ ] **Consent Blocking Engine.** Behalten oder nach der Aufteilung deaktivieren (Dokument 2, Empfehlung 7). Maßgeblich ist, ob die Inventarliste externe Hosts zeigt.
- [ ] **Turnstile.** Keine Schlüssel unter Kontakt → Integration eintragen. Der Tag `[turnstile]` im Formular bleibt dann wirkungslos.
- [ ] **WP-CLI-Befehl `wp baukasten harden`.** Behalten oder entfernen (Dokument 2, Empfehlung 11).
- [ ] **Karten mit Formular.** Welche Karten bekommen das Kontaktformular? Karten ohne E-Mail-Adresse leiten Einsendungen an die Admin-Adresse.
- [ ] **Einwilligungstext.** Der vorgegebene Satz nennt keinen Zweck („… in die Verarbeitung meiner personenbezogenen Daten ein“). Optional um „zur Beantwortung meiner Nachricht“ ergänzen. Die Änderung betrifft die deutsche Übersetzung in Business Cards.
- [ ] **Inaktive Themes.** Löschen unter Design → Themes (Bestätigungsdialog). Das Theme-Verzeichnis belegt 20 MB, aktiv ist nur Twenty Twenty-Five.

---

## Phase B: Form Privacy ausrollen

### B1. Deployment

- [ ] Vom Developer die Datei `baukasten-form-privacy-1.0.0.zip` übernehmen.
- [ ] Unter Plugins → Installieren → Plugin hochladen installieren und aktivieren.
- [ ] Dem Developer Bescheid geben, damit er die Abnahme aus Dokument 3a, Phase B startet.

### B2. Bereinigung auslösen

- [ ] Die Zahlen des Developers prüfen: Wie viele Kontakte und Nachrichten sind betroffen?
- [ ] Unter Baukasten → Form Privacy die Bereinigung starten und den Dialog bestätigen. Danach auf Bitte des Developers ein zweites Mal, als Wiederholungstest.

---

## Phase C: Anpassungen aus Dokument 2 ausrollen

### C1. Deployment

- [ ] Die ZIP-Dateien der geänderten Plugins übernehmen, jeweils Version 1.1.0.
- [ ] Unter Plugins → Installieren → Plugin hochladen installieren und „Aktuelle Version durch hochgeladene Version ersetzen“ wählen. Die Reihenfolge ist egal, weil Business Cards das Form-Privacy-Addon optional abfragt.
- [ ] Wird die Härtung aufgeteilt (Empfehlung 7): „Site Hardening“ installieren und aktivieren, bevor die Consent Blocking Engine aktualisiert wird. Danach auf dem Tab kontrollieren, dass die Maßnahmen übernommen wurden.
- [ ] Dem Developer Bescheid geben.

### C2. Bestätigungen im Backend

- [ ] Unter Baukasten → Business Cards „Vorlage erneut anwenden“ klicken und bestätigen, sofern das Formular schon existiert.
- [ ] Den Testversand an die eigene Testkarte freigeben und den Eingang prüfen:
  - Absender ist die Admin-Adresse.
  - Antworten gehen an die im Formular eingegebene Adresse.
  - Die Mail liegt nicht im Spam.
- [ ] Den Sperrtest für das Anmelde-Limit auf Produktion nicht freigeben. Er würde dich selbst aussperren, weil Developer und Webmaster dieselbe IP verwenden. Er läuft lokal.

---

## Phase D: Abschluss

- [ ] Die Datenschutzerklärung aktualisieren. Aufzunehmen sind:
  - WP Super Cache entfällt.
  - Kontaktformular: gespeicherte Daten, Löschfrist, keine IP-Speicherung, kein Adressbuch, kein externer Spam-Dienst. Grundlage ist der Textbaustein aus Form Privacy (F8) unter Einstellungen → Datenschutz.
  - Formulare auf Visitenkarten werden an die Adresse des jeweiligen Karteninhabers weitergeleitet. Ist der Karteninhaber nicht der Betreiber der Site, ist er Empfänger der Daten und muss genannt werden.
  - Das Consent-Log mit seiner Löschfrist, falls die Consent Blocking Engine bleibt.
- [ ] Die Nutzungsbedingungen prüfen, auf die der Einwilligungssatz verlinkt. Sie müssen über Login Legal Pages bzw. pro Karte hinterlegt sein.
- [ ] Den Abschlussbericht des Developers durchsehen und die offenen Punkte übernehmen.
- [ ] Ein frisches Backup ziehen.
