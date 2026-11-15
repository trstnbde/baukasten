# Abnahme: Baukasten Addon: Wishlist

Geprüft am 15. November 2026 auf https://bundeshub.de/ (WordPress 7.1.3, PHP 8.4, Theme `baukasten-tt5-fork` 1.5). Wishlist 1.0.0, Core 1.0.2. Backup vor dem Deploy: `~/baukasten-backups/2026-11-15/` (`db.sql`, `plugins.tgz`). CI (`integration.yml`, 108 Tests, `WishlistTest` mit 15 Tests neu) auf `work/wishlist` grün; PHPCS ohne Befund.

## Vorgehen

Claude in Chrome war in dieser Sitzung nicht verbunden. Deshalb lief die Abnahme ohne Torstens Browserprofil:

- **Abgemeldet** mit `curl` und mit Playwright in einem headless Chrome mit frischem, temporärem Profil. Zwei getrennte Browserkontexte spielten zwei Gäste: Desktop 1280 × 900 und Mobil 390 × 844.
- **Backend** mit `wp eval-file` als Administrator: Die Meta-Boxen, Spalten und der Tab wurden gerendert und auf ihren Inhalt geprüft. Der Klickweg im Admin selbst (Speichern, Mediathek, Block-Editor) wurde nicht im Browser gesehen, siehe Offen.

Live angelegt:

- die Liste „Lenis 2. Geburtstag“ (ID 319; Leni, 2. Geburtstag, 11.12.2026);
- drei Testwünsche „[Test] …“ (IDs 320–322) mit Bildern aus der Mediathek;
- die Seite „Wunschliste Leni“ (`/wunschliste-leni/`, ID 323) mit dem Block.

Content Visibility legt neue Seiten privat an. Damit Gäste die Liste sehen, ist diese eine Seite auf **öffentlich** gestellt. Nach der Abnahme sind alle Testmeldungen zurückgenommen und die Testwünsche auf Entwurf gesetzt.

Screenshots (nicht im Repo, weil ein Testbild ein Porträt zeigt): `docs/acceptance/raw/wishlist/wl-desktop.png`, `wl-after-claim.png`, `wl-mobile.png`.

## Ergebnisse

| Kriterium | Ergebnis |
|---|---|
| Seite abgemeldet | ✅ HTTP 200. Kopf mit „Für Leni · 2. Geburtstag · 11. Dezember 2026“, Countdown „in 26 Tagen“, Einleitung; alles auf Deutsch |
| Assets | ✅ `wishlist.css` und `wishlist.js` (defer) nur auf dieser Seite, Version aus `filemtime` |
| Fremd-Requests | ✅ Der Browser fragt nur `bundeshub.de` an. Im HTML stehen als fremde Adressen nur die Shop-Links |
| Konsole | ✅ keine Fehler, in beiden Kontexten |
| Steuerleiste | ✅ erscheint erst mit JS; „3 von 3 Wünschen angezeigt“ |
| Sortierung | ✅ Standard nach Priorität (Holzeisenbahn, Bilderbuch, Matschhose); „Preis aufsteigend“ gibt Bilderbuch, Holzeisenbahn, Matschhose, ohne Preis zuletzt. Die Wahl steht in der URL (`?wl319_sort=price-asc`) |
| Filter | ✅ Priorität „Hoch“ zeigt nur Holzeisenbahn; „Preis bis 10 €“ nur Bilderbuch; „Noch gewünscht“ blendet Besorgtes aus |
| Kauf melden (Gast A) | ✅ Rückfrage im Karteninhalt (kein `confirm()`), danach Abzeichen „Bereits besorgt“, Bild grau, Toast „Danke! …“. Das Token liegt in `localStorage` |
| Menge > 1 | ✅ Bilderbuch zählt von „3 von 3“ auf „2 von 3 noch gewünscht“ |
| Anderer Besucher (Gast B, anderes Profil, mobil) | ✅ sieht „Bereits besorgt“ und „2 von 3“. Er hat keinen „Rückgängig“-Link und bei Besorgtem keinen Kauf-Button |
| Rückgängig nur im meldenden Browser | ✅ A nimmt beide Meldungen zurück, danach ist `localStorage` leer; B sieht nach dem Neuladen wieder alles offen |
| State-Route | ✅ `GET /wp-json/baukasten-wishlist/v1/lists/319` liefert nur Zahlen, keine Titel; `Cache-Control: no-store` |
| Mobil 390 px | ✅ kein horizontales Scrollen. Zuerst war die Sortier-Auswahl abgeschnitten und ein Hochformatbild sprengte den 4:3-Rahmen; beides ist behoben (Sortierung volle Breite, Bild absolut positioniert) und neu ausgerollt |
| Backend (als Administrator gerendert) | ✅ Box „Anlass“, „Wünsche auf dieser Liste“ mit „Wunsch hinzufügen“, Shortcode-Box `[baukasten_wishlist id="319"]`, Box „Details“ (Preis „34,90“, Liste vorausgewählt), Box „Gekauft“, Spalten „3 Wünsche / 0 von 5 gekauft“, Tab mit Listenübersicht und Einstellungen |
| Rate-Limit | ✅ mit `wp eval-file` und Dokumentations-IP 198.51.100.7, Limit 2: durch, durch, gesperrt. Danach Zähler gelöscht und Limit zurückgesetzt (20) |
| Leere Liste | ✅ nach dem Zurücksetzen der Testwünsche auf Entwurf: „Auf dieser Liste stehen noch keine Wünsche.“ |

## Nur per PHPUnit geprüft (CI grün)

- Preise in beiden Schreibweisen (`12,99`, `1.299,00`, `1,299.00`, `1.299`), Mengen- und Prioritätsgrenzen, `javascript:`-URLs, ungültige Daten.
- 409 ab erschöpfter Menge, Token nur einmal gültig, falsches Token 404, ungültiges 400, Token nie im Klartext gespeichert.
- Entwurf, Wunsch einer unveröffentlichten Liste, fremder Beitragstyp: 404. Geschlossene Liste: 403.
- Rate-Limit 429 je Adresse, andere Adresse eigenes Fenster.
- Shortcode escaped Titel. In einem passwortgeschützten Beitrag und bei unveröffentlichter Liste gibt er nichts aus.

## Beobachtungen

- Das Theme setzt weiche Trennstriche in Titel („Holz­ei­sen­bahn“). Das stört weder Sortierung noch Filter, wohl aber Textsuchen in Tests (diese arbeiten mit `data-id`).
- Die State-Route ist auch erreichbar, wenn die Seite privat ist; sie verrät nur Zahlen je Wunsch-ID.

## Offen

1. **Backend im Browser.** Speichern einer Liste und eines Wunsches über das Formular, die Mediathek für das Beitragsbild, den Farbwähler und den Block im Editor (Listenauswahl, Vorschau per `ServerSideRender`) hat noch niemand im Browser gesehen. Das holt Torsten beim Eintragen der echten Wünsche nach, oder ich wiederhole es mit Claude in Chrome.
2. **Echte Wünsche** für Leni eintragen; die drei Testwünsche (Entwurf) danach löschen.
3. Die Seite `/wunschliste-leni/` ist öffentlich und nicht im Menü. Ob sie zusätzlich ein Passwort bekommen soll, entscheidet Torsten.
4. Merge, Tags (`baukasten-1.0.2`, `baukasten-wishlist-1.0.0`, einzeln gepusht) und Releases nach Freigabe.
