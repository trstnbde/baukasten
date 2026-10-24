# Teil B: Vorhaben „Passwortschutz für Neuer-Tab-Seite und Visitenkarten, Admin-Bar-Link“

Auftrag von Torsten vom 24. Oktober 2026. Betroffen: Core `baukasten`, `baukasten-business-cards`, `baukasten-new-tab`. Branch `work/password-protection`.

## Ziel

1. Die WordPress-Sichtbarkeit „Passwortgeschützt“ wirkt auf der Neuer-Tab-Seite (Seiten-Liste: „Neuer Tab — Neuer-Tab-Seite“) und auf jeder Visitenkarte (`baukasten_card`). Heute ignorieren beide Addons das Passwort, weil sie am Theme vorbei rendern.
2. New Tab Page setzt einen Link zur Neuer-Tab-Seite in die Admin-Bar, für jeden angemeldeten Nutzer, gleich welche Rolle.
3. Kein Datenleck in Nebenwegen: Downloads, REST-Routen, Formulare und Weiterleitungen respektieren die Sperre genauso wie die Seite selbst.

**Priorität hoch.** Die Rechtstexte auf bundeshub.de (Datenschutzerklärung Abschnitte 2, 5, 7, 14; Impressum) beschreiben den Passwortschutz bereits als wirksam. Bis zum Deploy stimmt das nicht.

## Befund (live geprüft am 24.10.2026)

| Stelle | Befund |
|---|---|
| Karte ID 55, `/c/ninnxdhj/` | Passwort gesetzt, Liste zeigt „Passwortgeschützt“. Abgemeldeter Abruf: HTTP 200, alle Felder, `tel:`-, `mailto:`- und vCard-Link im HTML. |
| `Business Cards\Renderer` | `detach()` und `use_card_template()` prüfen nur `is_card_route()`; `templates/card-view.php` gibt die Felder direkt aus. |
| `Business Cards\Vcard::maybe_serve()`, `Pass::maybe_serve()` | liefern `?action=vcf` und `?action=pkpass` ohne `post_password_required()`. |
| `Business Cards\Forms` | Mail an die Kartenadresse über `_wpcf7_container_post`. Bei gesperrter Karte bleibt das Formular per CF7-REST (`/contact-form-7/v1/contact-forms/<id>/feedback`) erreichbar. |
| `New Tab\Renderer` | `take_over()` leitet `?q=` weiter und baut die View mit den Favoriten (private Adressen) ohne Passwortprüfung; `use_template()` rendert sie. |
| `New Tab\Rest_Controller::may_read()` | prüft nur `Content Visibility\Frontend_Guard::is_blocked()`, nicht das Passwort (`/suggest`, `/backgrounds`). |
| Core, Login Legal Pages | Das Formular von `get_the_password_form()` postet an `site_url( 'wp-login.php?action=postpass', 'login_post' )`, das `Login_URL` auf `/login/?action=postpass` umbiegt. Ob `Login_URL::serve()` `postpass` sauber durchreicht, ist ungetestet. |
| Core, Rate-Limit | Seitenpasswörter sind nicht begrenzt. Das Kartenpasswort lässt sich unbegrenzt raten. |

Ein ungetesteter Entwurf für Business Cards liegt unter `Z:\usr\home\cwyhqy\baukasten-business-cards-1.0.1.patch` (Fallback aufs Theme für Karte, vCard und Pass). Er ist Ausgangspunkt, nicht Ziel: Theme-Fallback, Formular und Rate-Limit fehlen.

## Verhalten, das gelten soll

| Zustand der Seite/Karte | Abgemeldet | Angemeldet (jede Rolle) |
|---|---|---|
| Öffentlich, kein Passwort | wie heute | wie heute |
| Öffentlich, Passwort, noch nicht eingegeben | Sperrseite, sonst nichts | Inhalt ohne Passwortabfrage |
| Öffentlich, Passwort, korrekt eingegeben (`wp-postpass_<COOKIEHASH>`) | Inhalt | Inhalt |
| Content Visibility „Privat“ (nur Neuer-Tab-Seite; Karten sind ausgenommen) | Login wie heute, Passwort spielt keine Rolle | Inhalt |

Angemeldete Nutzer umgehen das Passwort **nur** für die gewählte Neuer-Tab-Seite und für `baukasten_card`, über den Filter `post_password_required` mit genau dieser Bedingung. Für alle anderen Beiträge und Seiten bleibt das Kernverhalten unverändert.

## Funktionen

### P1 Business Cards: Sperrseite und Nebenwege

- `Renderer::is_locked()`: `is_card_route() && post_password_required( get_queried_object() )`.
- Gesperrt: eigene Sperrseite `templates/card-locked.php`, vom Theme abgekoppelt wie die Karte (gleiche `strip_head()`-/`strip_queue()`-Logik, Kartenschrift, `card.css`, Hell-Dunkel nach Systemeinstellung). Inhalt: Hinweis „Diese Karte ist passwortgeschützt“, Formular aus `get_the_password_form()`, Fehlermeldung bei falschem Passwort (Cookie vorhanden, aber falsch), Footer mit den Rechtstexten aus Login Legal Pages. **Keine** Felder, kein Foto, kein Name, kein QR-Code, keine Skripte der Karte; Dokumenttitel generisch.
- Header der Sperrseite: `X-Robots-Tag: noindex, nofollow`, `Cache-Control: no-store, private`.
- `Vcard::maybe_serve()` und `Pass::maybe_serve()`: bei Sperre nichts ausliefern; die Anfrage fällt auf die Sperrseite durch.
- Formular: Einsendungen mit `_wpcf7_container_post` einer gesperrten Karte ohne gültigen Passwort-Cookie werden abgewiesen (keine Mail, kein Flamingo-Eintrag, CF7-Status `mail_failed` oder eigener Status mit neutraler Meldung).

### P2 New Tab Page: Sperrseite und Nebenwege

- `Renderer::is_locked()` analog, auf Basis von `Settings::page_id()`.
- Gesperrt: `take_over()` leitet keine Suche weiter und baut keine View mit Favoriten, Grüßen, Hintergründen; stattdessen `templates/locked.php` im Stil der Seite (Noto Sans, Farbverlauf ohne Foto, kein Unsplash, keine Skripte außer dem Nötigsten). CSP wie bisher über `content_security_policy()`; jede Inline-Style-Änderung braucht einen neuen Hash (Falle im Developer Guide).
- `Rest_Controller::may_read()`: zusätzlich `post_password_required( $page_id )` prüfen, dann 401 mit neutraler Meldung. Der Passwort-Cookie wird bei REST-Aufrufen mitgeschickt; das muss ein Test belegen.
- Settings-Tab, Zeile „Who can open it“: drei Zustände anzeigen (private / password protected / public) mit Hinweis, dass öffentliche Favoriten Adressen verraten und ein Passwort sie schützt.
- `Page_Setting`-Hinweise im Editor entsprechend ergänzen.

### P3 New Tab Page: Admin-Bar-Link

- `add_action( 'admin_bar_menu', …, 35 )`, Knoten-ID `baukasten-new-tab`, Titel „New Tab“ (de: „Neuer Tab“) mit Dashicon, Ziel `get_permalink( Settings::page_id() )` (Multi-Domain filtert die Adresse, falls die Seite einer Domain zugeordnet ist).
- Sichtbar für jeden angemeldeten Nutzer, im Frontend und in `wp-admin`, ohne Capability-Prüfung über `read` hinaus. Nicht, wenn keine Seite gewählt oder die Seite nicht veröffentlicht ist. Auf der Neuer-Tab-Seite selbst gibt es keine Admin-Bar (bleibt so).
- Auch in der mobilen Admin-Bar (< 783 px) sichtbar; das braucht eine kleine CSS-Regel, eingereiht nur, wenn die Admin-Bar angezeigt wird, als Datei im Plugin.
- Die Nutzer-Einstellung „Werkzeugleiste anzeigen“ wird respektiert, nicht überschrieben.

### P4 Core: Seitenpasswörter über die Login-Adresse und Rate-Limit

- Sicherstellen, dass `/login/?action=postpass` (und das direkte `wp-login.php?action=postpass`) den Cookie setzt und zur Karte bzw. Seite zurückleitet; falls `Login_URL::serve()` etwas verschluckt, dort beheben.
- Rate-Limit für `postpass` in `Rate_Limit`: auf `login_form_postpass` pro IP zählen (gehasht, Transient, wie die Anmeldung); Vorschlag 10 Versuche in 15 Minuten, dann 15 Minuten Sperre mit HTTP 429 und neutraler Meldung. Werte in den Tab „Login & Rechtliches“ aufnehmen, eigene Option `baukasten_login_postpass_limit`.
- Kein Test über das Live-Formular (Teil A), sondern `wp eval-file` mit Dokumentations-IP und PHPUnit.

### P5 Übersetzungen, Doku, Versionen

- Neue Strings in `.pot`, `de_DE.po/.mo` aller drei Plugins (WP-CLI, nicht von Hand).
- `README.md` je Plugin: Abschnitt zum Passwortschutz und seinen Grenzen. Developer Guide: neue Fallen, falls welche auftreten.
- Versionen: Core, Business Cards und New Tab Page auf 1.0.1 (Header, `VERSION`, `readme.txt` mit Changelog).

## Tests (PHPUnit in `tests/integration/`)

- Gesperrte Karte: Antwort ohne Feldinhalte, ohne `tel:`/`mailto:`; `?action=vcf` und `?action=pkpass` liefern keine Datei; mit gültigem Cookie alles wie bisher; angemeldeter Abonnent sieht die Karte ohne Passwort.
- CF7-Einsendung an eine gesperrte Karte ohne Cookie: keine Mail (Mailer abfangen), kein Flamingo-Eintrag.
- Neuer-Tab-Seite gesperrt: kein Favorit im HTML, `?q=test` leitet nicht weiter, `/suggest` und `/backgrounds` 401; mit Cookie 200.
- Admin-Bar: Knoten für Abonnent, Redakteur, Administrator vorhanden; ohne gewählte Seite nicht.
- `postpass`-Rate-Limit greift nach dem Schwellenwert, gehashte IP, Ablauf.
- Bestehende Tests bleiben grün (`ContentVisibilityTest`, `NewTabTest`, `CardRecipientTest`, `RateLimitTest`).

## Abnahme auf bundeshub.de (Protokoll `docs/acceptance/password-protection.md`)

1. Backup und Deploy nach Teil A, Rechte prüfen.
2. Abgemeldet per `curl`: `/c/ninnxdhj/`, `?action=vcf`, `?action=pkpass` → Sperrseite bzw. keine Datei; HTML ohne Kontaktdaten. Screenshot der Sperrseite (Profil „Test (ausgeloggt)“, 390 × 844 und Desktop).
3. Ausgeloggt im Testprofil das Kartenpasswort eingeben (Torsten fragen, nicht raten) → Karte erscheint; Cookie `wp-postpass_…` mit Laufzeit protokollieren (WordPress-Standard 10 Tage, nicht ändern: die Datenschutzerklärung nennt ihn).
4. Eingeloggt: Karte ohne Passwort, Admin-Bar-Link sichtbar in Frontend und Backend, Klick öffnet die Neuer-Tab-Seite.
5. Neuer-Tab-Seite testweise auf „Öffentlich + Passwort“ nur nach Freigabe durch Torsten, danach Ursprungszustand („Privat“) wiederherstellen, außer Torsten entscheidet anders.
6. `read_network_requests` auf Sperrseiten: nur eigene Hosts. Konsole ohne Fehler.
7. Kein Kontaktformular auf Produktion absenden (Mail an Dritte).

## Rechtstexte

Nicht im Repo. Nach dem Deploy Torsten Bescheid geben; die Texte nennen den Cookie `wp-postpass_…` und den Passwortschutz bereits. Weicht etwas ab (Laufzeit, Verhalten für Angemeldete), Torsten informieren statt die Texte selbst zu ändern.

## Bewusst nicht im Umfang

- Eigene Passwortverwaltung neben dem Kernmechanismus.
- Passwortschutz für weitere Beitragstypen.
- Änderungen an Content Visibility außer dem, was P2 für die Anzeige im Tab braucht.

Nach Release wandert dieser Teil B unverändert nach `docs/briefings/05-briefing-passwortschutz.md`, und hier bleibt nur ein Verweis.
