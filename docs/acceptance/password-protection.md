# Abnahme: Passwortschutz für Visitenkarten und Neuer-Tab-Seite, Admin-Bar-Link

Geprüft am 24. Oktober 2026 auf https://bundeshub.de/ (WordPress 7.1.3, PHP 8.4). Core, Business Cards und New Tab Page in Version 1.0.1. Backup vor dem Deploy: `~/baukasten-backups/2026-10-24-4/`. CI (`integration.yml`, 94 Tests, `PasswordProtectionTest` neu) auf `work/password-protection` grün.

## Vorgehen

Das Passwort von Torstens echter Karte (ID 55) wurde nicht verwendet. Für den Durchlauf mit Passwort habe ich eine **temporäre Karte** mit eigenem Passwort angelegt und danach gelöscht (ID 191, jetzt 404). Die Neuer-Tab-Seite hat kein Passwort bekommen (Freigabe fehlt, siehe Offen); ihre Sperrseite ist nur per PHPUnit geprüft. Kein Kontaktformular wurde abgesendet.

## Ergebnisse

| Kriterium | Ergebnis |
|---|---|
| Karte `/c/ninnxdhj/` (Passwort gesetzt), abgemeldet | ✅ HTTP 200 mit Sperrseite, Titel „Geschützte Karte“; im HTML kein `mailto:`, `tel:`, `action=vcf`, `action=pkpass`, kein Avatar, kein Name, kein `card.js`, nur Anfragen an `bundeshub.de` |
| Header der Sperrseite | ✅ `X-Robots-Tag: noindex, nofollow`, `Cache-Control: no-store, private` (zuerst fehlte `X-Robots-Tag`, weil `wp_headers` zu früh läuft; behoben, jetzt auf `template_redirect`) |
| `?action=vcf` und `?action=pkpass` einer gesperrten Karte | ✅ HTML der Sperrseite, keine Datei (`BEGIN:VCARD` nicht enthalten, kein `application/vnd.apple.pkpass`) |
| Formular postet an `/login/?action=postpass` | ✅ Antwort 302 zurück auf die Karte, `wp-postpass_…` gesetzt |
| Falsches Passwort | ✅ Karte bleibt gesperrt, Meldung „Das Passwort ist ungültig.“ |
| Richtiges Passwort | ✅ Karte mit allen Feldern, vCard (`text/vcard`) wird geliefert; ohne Cookie nicht |
| Laufzeit des Cookies | 10 Tage (`Max-Age=864000`), WordPress-Standard, unverändert; die Datenschutzerklärung nennt ihn |
| Eingeloggt (Administrator), Karte ohne Passwort | ✅ Felder sichtbar, kein Passwortfeld, keine Admin-Bar auf der Karte |
| Admin-Bar-Link „Neuer Tab“ | ✅ im Backend und im Frontend (`/blog/`), Ziel `/start/`, Globus-Symbol; mobile Ansicht nur per CSS vorgesehen, nicht auf einem Gerät gesehen |
| Tab der New Tab Page, Zeile „Wer sie öffnen kann“ | ✅ drei Zustände (privat / passwortgeschützt / öffentlich), im Moment „Privat“ |
| Rate-Limit `postpass` | ✅ mit `wp eval-file` und Dokumentations-IP 203.0.113.99: Versuche 1–10 durch, 11 und 12 mit HTTP 429 „Zu viele Passwortversuche“; Sperre 900 s; Schlüssel gehasht; Zähler danach gelöscht |
| Konsole, Fremd-Requests | ✅ keine Fehler; auf der Sperrseite nur eigene Hosts |

## Nur per PHPUnit geprüft (CI grün)

- Gesperrte Neuer-Tab-Seite: kein Favorit, keine Suche, keine Weiterleitung bei `?q=`, REST-Routen 401, mit Cookie 200, strengste Richtlinie ohne Inline-Style-Hash, angemeldete Nutzer (Abonnent, Redakteur, Administrator) kommen vorbei.
- CF7-Einsendung von einer gesperrten Karte wird abgewiesen (kein Versand, kein Flamingo-Eintrag), mit Cookie nicht.
- Admin-Bar-Knoten für Abonnent, Mitarbeiter, Redakteur, Administrator; kein Knoten ohne Seite oder abgemeldet.

## Beobachtungen

- Die Neuer-Tab-Seite steht auf **Privat** (geändert nach der letzten Sitzung). „Passwortgeschützt“ wirkt in WordPress nur bei Sichtbarkeit „Öffentlich“; Content Visibility „Privat“ geht vor und leitet auf den Login.
- Der Server überschreibt Sicherheits-Header der Antwort; die Richtlinie der Neuer-Tab-Seite steht deshalb zusätzlich im `<meta>`.

## Offen

1. Test der Neuer-Tab-Seite mit Passwort auf der Live-Site: nur nach Freigabe durch Torsten (Seite kurz auf „Öffentlich + Passwort“, danach „Privat“ wiederherstellen).
2. Test mit dem echten Kartenpasswort und der Mobilansicht der Admin-Bar auf einem Gerät.
3. Rechtstexte (Datenschutzerklärung Abschnitte 2, 5, 7, 14; Impressum): Der Cookie `wp-postpass_…` mit 10 Tagen Laufzeit und das Verhalten für Angemeldete stimmen mit den Angaben überein, soweit ich sie kenne; die Texte liegen nicht im Repo und wurden nicht geändert. Neu: Passwortversuche werden je IP-Adresse (gehasht, höchstens 15 Minuten bzw. die eingestellte Sperre) gezählt.
