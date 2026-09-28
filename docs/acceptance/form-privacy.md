# Abnahme: Baukasten Addon: Form Privacy

Protokoll zu den Abnahmekriterien aus [Briefing 01](../briefings/01-briefing-baukasten-form-privacy.md). Geprüft am 28.09.2026 auf https://bundeshub.de/ (WordPress 7.1.2, PHP 8.4, CF7 6.1.7, Flamingo 2.6.4, Turnstile unter Kontakt → Integration eingerichtet) und in der CI (`tests/`, WordPress-Testsuite in wp-env).

Deployment per SSH mit WP-CLI. Vorher wurden Datenbank und Plugins gesichert (`~/baukasten-backups/2026-09-28/` auf dem Server, außerhalb des Webroots).

| # | Kriterium | Ergebnis | Wie geprüft |
|---|---|---|---|
| 1 | Seite ohne Formular lädt nichts aus `contact-form-7/` | ✅ | `curl` einer 404-Seite (alle anderen Seiten sind privat): keine CF7-, Turnstile-, reCAPTCHA- oder Consent-Dateien |
| 2 | Seite mit Formular lädt CF7 und sendet | ✅ | Nach Freigabe: Testeinsendung über Karte 64 im Chrome des Webmasters (Absender `test@example.org`). Turnstile wurde erst nach dem Haken geladen und ohne Interaktion bestanden, Ergebnis `mail_sent`. Zustellung und Spam-Einstufung prüft der Webmaster im Postfach der Karte. |
| 3 | Gespeicherte Einsendung nur mit Whitelist-Metadaten | ✅ | Nachricht #115: `_meta` enthält nur `url`, `date`, `time`, `post_id` und `post_title`, kein `remote_ip`, `user_agent` oder `turnstile`. `_akismet` und `_recaptcha` sind leer, die Felder enthalten weder `_bk_hp` noch `_bk_ts`. |
| 4 | „Adressbuch“ fehlt, „Flamingo“ öffnet die Nachrichten | ✅ | Chrome, eingeloggt: Das Flamingo-Menü enthält nur „Nachrichten-Eingang“, der Hauptpunkt verweist auf `flamingo_inbound`. `flamingo_edit_contacts` ist für den Admin `false`, `flamingo_delete_contacts` ist `true`. |
| 5 | Kein `flamingo_contact` durch Einsendung oder Profil-Update | ✅ | Nach der Testeinsendung (`mail_sent`, früher der Auslöser für einen Kontakt) weiterhin 0 Kontakte. `Flamingo_Contact::add()` gibt `NULL` zurück. CI: Kontakt- und Benutzeranlage erzeugen keinen Kontakt. |
| 6 | Überfällige Einsendung wird vom Purge gelöscht | ✅ CI | `FormPrivacyTest::test_retention_deletes_old_messages`, Spam-Status eingeschlossen, samt Meta. Auf Produktion ist der Cron `baukasten/form_privacy/purge` geplant, keine Nachricht ist älter als 90 Tage. |
| 7 | Zweiter Bereinigungslauf meldet überall „keine Änderung“ | ✅ | Nach Freigabe per `wp baukasten form-privacy clean`: Lauf 1 meldet „4 Nachrichten reduziert“, Adressbuch und Löschfrist ohne Änderung. Lauf 2 meldet in allen drei Schritten „keine Änderung“. |
| 8 | Core-Export enthält die Einsendungen der Adresse | ✅ | Exporter `baukasten-form-privacy` ist registriert. Für den Absender einer vorhandenen Nachricht liefert er 1 Eintrag mit 26 Feldern, Gruppe „Kontaktformular-Nachrichten“. |
| 9 | Honeypot, manipulierter und zu früher Zeitstempel sind Spam, `_bk_*` nicht in Mail/Flamingo | ✅ | Auf Produktion per `curl` an das Formular von Karte 55: gefüllter Honeypot, gefälschter, fehlender und zu schneller Zeitstempel ergeben jeweils `spam`, ohne Mail und ohne Speicherung. Die Gründe sind serverseitig über `Honeypot::judge()` bestätigt (`honeypot`, `timestamp missing`, `timestamp invalid`, `too fast`, eine reguläre Einsendung ergibt `''`). Der Filter liegt auf Priorität 8. |
| 10 | Mit Turnstile-Schlüsseln lädt eine Seite ohne Formular nichts von Cloudflare | ✅ | Siehe 1. Turnstile ist auf Produktion eingerichtet. |
| 11 | Ohne Flamingo fehlerfrei, Tab zeigt Hinweis | ✅ Code | Alle Flamingo-Aufrufe sind mit `class_exists` abgesichert, und der Tab hat einen eigenen Zweig. Auf Produktion nicht geprüft, weil Flamingo dort aktiv bleibt. |
| 12 | `composer lint`, `php -l` 8.1–8.4 | ✅ | PHPCS ohne Befund, `php -l` in der CI auf 8.0–8.4 |

Weitere Beobachtungen:

- Einsendung ohne Einwilligung: CF7 meldet `validation_failed`, bevor der Spam-Check läuft. Damit wird Cloudflare nie gefragt.
- IP-Adresse: `wpcf7_remote_ip_addr` liefert `''`.
- Löschfrist: Der erste Lauf war für 28.09.2026 14:46 geplant. Die Seite hat noch keinen Server-Cronjob (Dokument 3b, A4).
