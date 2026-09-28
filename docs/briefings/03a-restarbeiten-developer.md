# Manuelle Restarbeiten: Developer

Das ist Dokument 3a von 3, für Claude Code. Es umfasst alles außer Plugin-Code: Messungen, Einstellungen im WordPress-Backend, Abnahmeläufe und Übergaben an den Webmaster. Das Gegenstück für den Webmaster ist Dokument 3b. Beide sind in dieselben Phasen A bis D gegliedert, damit die Übergaben zusammenpassen.

| | |
|---|---|
| Zielsystem | https://bundeshub.de/ |
| Werkzeuge | Claude in Chrome, `curl`, lokale XAMPP-Umgebung |
| Protokoll | `docs/acceptance/restarbeiten.md` |

## Regeln

- Eingeloggte Arbeiten laufen im Chrome-Profil des Webmasters, dessen Sitzung besteht. Nicht abmelden, keine Zugangsdaten eingeben. Der Login verlangt Two Factor.
- Ausgeloggte Prüfungen laufen im Chrome-Profil „Test (ausgeloggt)“ oder per `curl`.
- Buttons mit Bestätigungsdialog (`window.confirm`) klickt der Webmaster. Ein Dialog blockiert Claude in Chrome, und die Bestätigung ist zugleich die Freigabe. Betroffen sind:
  - „DSGVO-Härtung & Standardwerte anwenden“
  - Bereinigung in Form Privacy
  - „Vorlage erneut anwenden“ in Business Cards
  - das Löschen von Themes, Kontakten und Nachrichten
- Den Hardening-Button weder klicken noch dem Webmaster empfehlen. Er setzt `blog_public` auf 0 zurück.
- Kein Test, der Mails an Dritte auslöst oder den Webmaster aussperrt (Rate-Limit, Dokument 2, Empfehlung 14), läuft auf Produktion ohne Freigabe.
- Jede Änderung im Backend wird mit altem und neuem Wert im Protokoll festgehalten.

---

## Phase A: Vor der Entwicklung

### A1. Ausgangswerte festhalten

- [ ] Unter Werkzeuge → Website-Zustand → Bericht „Website-Informationen in die Zwischenablage kopieren“ ausführen. Den Inhalt mit `get_page_text` auslesen und als `docs/acceptance/baseline-site-health.txt` ablegen.
- [ ] Antwortzeit ausgeloggt fünfmal messen und den Median notieren:

  ```bash
  for i in 1 2 3 4 5; do curl -o /dev/null -s -w '%{time_starttransfer}\n' https://bundeshub.de/; done
  ```

- [ ] Die Antwort-Header der Startseite festhalten:

  ```bash
  curl -sI https://bundeshub.de/
  ```

- [ ] Ausgeloggt festhalten: `https://bundeshub.de/wp-json/wp/v2/users`, `https://bundeshub.de/?author=1`, `https://bundeshub.de/xmlrpc.php` und die oEmbed-Antwort der Startseite. Stand 28.09.2026: Slug `torsten` und `author_url` öffentlich, `provider_name` „Untitled blog“.
- [ ] Im Profil „Test (ausgeloggt)“ die Startseite, eine Visitenkarte und die Seite mit Kontaktformular laden. Alle Requests mit `read_network_requests` erfassen und jeden Host notieren, der nicht `bundeshub.de` ist.

### A2. Bestand im Backend erfassen

Alles nur lesen, nichts ändern.

- [ ] Baukasten → Consent Blocking Engine: Inventarliste. Welche Handles haben einen externen Host? Das Ergebnis ist die Grundlage für die Entscheidung über Empfehlung 7 in Dokument 2.
- [ ] Kontakt → Integration: Sind Turnstile, reCAPTCHA, Akismet oder Brevo eingerichtet?
- [ ] Kontakt → Kontaktformulare: In jedem Formular die Mail-Vorlagen (Mail und Mail 2) auf `[_remote_ip]`, `[_user_agent]` und `[_user_*]` durchsehen. Unter „Zusätzliche Einstellungen“ `do_not_store` notieren.
- [ ] Flamingo: Anzahl der Kontakte im Adressbuch, Anzahl der Nachrichten und Datum der ältesten Nachricht.
- [ ] Baukasten → Business Cards: Existiert das Formular „Baukasten Addon: Business Cards“? Welche Karten verwenden es, und welche davon haben kein `email_work` und kein `email_priv`?
- [ ] Design → Themes: Liste der inaktiven Themes.
- [ ] Einstellungen → Allgemein: aktueller Titel und aktuelle Administrator-E-Mail-Adresse. Die Adresse nur im Protokoll festhalten, nicht weitergeben.
- [ ] Die Ergebnisse in einer Übergabe an den Webmaster zusammenfassen. Er braucht sie für die Entscheidungen in Dokument 3b, Phase A.

### A3. Einstellungen ändern

- [ ] Baukasten → Consent Blocking Engine: den Schalter „Speculative Loading (Prefetch- und Prerender-Regeln)“ ausschalten und speichern. Der neue Default aus Empfehlung 10 ändert gespeicherte Werte nicht, deshalb ist dieser Schritt nötig.
- [ ] Kontakt → Kontaktformulare: `[_remote_ip]` und `[_user_agent]` aus allen Mail-Vorlagen entfernen und speichern. Danach zeigt der Konfigurationsvalidator keine neuen Fehler.

---

## Phase B: Nach dem Deployment von Form Privacy

Vorausgesetzt ist, dass der Webmaster Form Privacy installiert und aktiviert hat (Dokument 3b, B1).

- [ ] Abnahmekriterien 1 bis 12 aus Dokument 1 ausführen und protokollieren. Für Kriterium 2 die Testadresse des Webmasters verwenden.
- [ ] Die Einstellungen auf dem Tab entsprechend den Entscheidungen des Webmasters setzen: Speicherung ja/nein, Löschfrist, Meta-Whitelist.
- [ ] Dem Webmaster melden, wie viele Kontakte und Nachrichten die Bereinigung (F6) betreffen wird. Er löst sie aus (Dokument 3b, B2).
- [ ] Nach der Bereinigung prüfen:
  - Der Tab zeigt 0 Kontakte.
  - Keine Nachricht ist älter als die Frist.
  - Ein zweiter Lauf, vom Webmaster ausgelöst, meldet überall „keine Änderung“.
- [ ] Kontrollieren, dass `baukasten/form_privacy/purge` geplant ist. Lokal mit `wp cron event list`, auf Produktion über die Anzeige des nächsten Laufs auf dem Tab. Core hat dafür keine eigene Ansicht.

---

## Phase C: Nach dem Deployment der Anpassungen aus Dokument 2

- [ ] Die Abnahme jeder umgesetzten Empfehlung aus Dokument 2 ausführen und protokollieren.
- [ ] Business Cards:
  - Ist das Formular vorhanden, bittet der Developer den Webmaster, „Vorlage erneut anwenden“ zu klicken. Fehlt es, legt der Developer es über den Button „Kontaktformular anlegen“ an. Dieser Button hat keinen Bestätigungsdialog.
  - Danach den Formularinhalt mit dem Vorgabetext aus Empfehlung 5 vergleichen.
- [ ] Das Formular den Karten zuweisen, die der Webmaster in Phase A freigegeben hat. Für Karten ohne E-Mail-Adresse den Webmaster erinnern: Einsendungen gehen dort an die Admin-Adresse.
- [ ] Den Testversand aus Empfehlung 5 an die Testkarte des Webmasters durchführen, erst nach dessen Freigabe.
- [ ] Die Header- und Endpunkt-Prüfungen aus A1 wiederholen und mit den Ausgangswerten vergleichen.

---

## Phase D: Abschluss

- [ ] Den Website-Zustand erneut exportieren und mit `baseline-site-health.txt` vergleichen. Erwartete Unterschiede:
  - Abschnitte `wp-dropins`, `WP Super Cache` und `WordPress Importer` fehlen.
  - `WP_CACHE` ist `undefined` oder `false`.
  - `WP_DEBUG_DISPLAY` ist `false`.
  - Neue Plugin-Versionen und Form Privacy sind aufgeführt.
  - `blog_public` steht auf dem Wert, den der Webmaster entschieden hat.
- [ ] Die Antwortzeit wie in A1 messen und neben den Ausgangswert stellen.
- [ ] Die Netzwerkanalyse aus A1 wiederholen. Erwartet: kein Request an einen fremden Host, und auf Seiten ohne Formular keine CF7-Dateien.
- [ ] Unter Werkzeuge → Website-Zustand → Status offene Empfehlungen notieren und jede einordnen: erledigt, bewusst verworfen (zum Beispiel Seiten-Cache, persistenter Objekt-Cache) oder offen.
- [ ] Abschlussbericht an den Webmaster. Inhalt:
  - erledigte Punkte
  - offene Punkte mit Zuständigkeit
  - Messwerte vorher und nachher
  - Link auf die Protokolle unter `docs/acceptance/`
