# Briefing: Addon „Baukasten Addon: Wishlist“ (`baukasten-wishlist` 1.0.0)

## Context
Torsten möchte eine Wunschliste für Lenis 2. Geburtstag (11.12.2026) auf bundeshub.de, wiederverwendbar für andere Personen und Anlässe. Produkte werden im Backend gepflegt (Bezeichnung, Bild, Menge, Preis, Priorität, Shop-URL), im Frontend per Block/Shortcode in Seite oder Beitrag angezeigt, dort sortier- und filterbar. Gäste melden per **einem anonymen Klick** „gekauft“; andere Besucher sehen den Stand. **Niemand sieht Namen** (es werden keine erhoben). Eine Meldung kann **im selben Browser** zurückgenommen werden; der Admin korrigiert im Backend.

Vorbild ist `baukasten-business-cards` (CPT, Meta-Boxen, Media-Picker, Settings-Tab, uninstall), REST-Muster aus `baukasten-new-tab/includes/class-rest-controller.php`, IP-Hash aus `Baukasten\LoginLegalPages\Rate_Limit::key()/client_ip()`.

## Eckdaten
| | |
|---|---|
| Plugin Name | Baukasten Addon: Wishlist |
| Slug/Ordner/Text Domain | `baukasten-wishlist` |
| Namespace | `Baukasten\Wishlist` |
| Tab | `wishlist`, Position 90, `manage_options` |
| Optionen | `baukasten_wishlist_currency` (Standard `€`), `baukasten_wishlist_rate_limit` (Standard 20 Meldungen / 10 min je IP) |
| Hooks | `baukasten/wishlist/sort_options`, `baukasten/wishlist/item_data`, `baukasten/wishlist/can_claim` |
| Branch | `work/wishlist` |
| Versionen | Addon 1.0.0, Core 1.0.2 (Catalog-Eintrag) |

## Datenmodell
- **CPT `baukasten_wishlist`** (eine Liste = ein Anlass), Classic Editor, `public => false`, `show_ui => true`, eigenes Admin-Menü „Wunschlisten“ (Dashicon `dashicons-heart`). Meta (`_baukasten_wishlist_*`): Person, Anlass, Datum, Einleitungstext, Headerbild, Akzentfarbe, Preise anzeigen (bool), Meldungen nach dem Datum schließen (bool).
- **CPT `baukasten_wish_item`**, Untermenü „Wünsche“. Titel = Bezeichnung; Meta: Liste (ID), Bild (Attachment-ID), Menge (int ≥ 1), Preis (Dezimal als String, `.` normiert), Priorität (1 hoch / 2 mittel / 3 niedrig), Shop-URL (`esc_url_raw`, nur http/https), Notiz (kurz), manuelle Reihenfolge (`menu_order`).
- **Meldungen**: je Klick eine eigene Meta-Zeile `_baukasten_wish_claim` = `wp_hash(token)` per `add_post_meta(..., false)` (atomar, kein Array-Überschreiben). Gekauft = Anzahl Zeilen; Rest = Menge − gekauft. Nach dem Insert wird erneut gezählt und bei Überschreitung die eigene Zeile wieder entfernt (Race-Schutz).
- Keine Namen, keine IP gespeichert. Rate-Limit-Transient nur mit IP-Hash.

## Dateien (neu, Muster von Business Cards)
```
baukasten-wishlist/
  baukasten-wishlist.php        Header (Requires Plugins: baukasten, Update URI, PHP 8.1, GPLv2+, Torsten B.), Konstanten, boot() auf plugins_loaded, activate/deactivate
  includes/class-post-types.php  beide CPTs auf init 5, Block-Editor aus, Content-Visibility-Opt-out, Sitemap aus
  includes/class-fields.php      Schema + sanitize() + load() für Liste und Wunsch (wie BusinessCards\Fields)
  includes/class-meta-boxes.php  Meta-Boxen, Nonce einmal, save() mit Guards; Box „Meldungen“ mit Zähler + „Zurücksetzen“ (admin-post)
  includes/class-list-table.php  Spalten Liste/Bild/Prio/Preis/gekauft x/y, Filter nach Liste, sortierbar
  includes/class-claims.php      count(), remaining(), claim(item) → token, unclaim(item, token), reset(item), is_open(list)
  includes/class-rest-controller.php  baukasten-wishlist/v1: GET lists/<id>/state, POST items/<id>/claim, DELETE items/<id>/claim (token im Body)
  includes/class-renderer.php    render_list($list_id, $atts) → HTML-Karten mit data-* (prio, price, title, remaining, order)
  includes/class-block.php       dynamischer Block baukasten/wishlist (register_block_type mit block.json, render_callback → Renderer)
  includes/class-shortcode.php   [baukasten_wishlist id="…" sort="priority" filter="all"]
  includes/class-assets.php      Frontend-CSS/JS nur bei Bedarf (Merker im Callback, spät enqueuen – Falle 14)
  includes/class-settings.php, class-settings-tab.php   Tab: Währung, Rate-Limit, Übersicht der Listen mit Shortcode zum Kopieren, Link „Neue Liste“
  admin/views/{settings-tab.php, meta-box.php}
  blocks/wishlist/{block.json, editor.js}   ohne Build-Schritt (wp.element.createElement), Listen-Auswahl via ServerSideRender, Listen per wp_add_inline_script übergeben
  assets/css/{wishlist.css, admin.css}, assets/js/{wishlist.js, admin-media.js}
  templates/{list.php, item.php}
  languages/baukasten-wishlist{.pot,-de_DE.po,-de_DE.mo}
  uninstall.php  (Optionen + beide CPTs löschen, Multisite-Schleife)
  README.md, readme.txt, composer.json, phpcs.xml.dist, .distignore, .editorconfig, .gitignore, LICENSE
```

## Frontend
- Kopf: Headerbild, „Lenis 2. Geburtstag“, Datum + „noch N Tage“, Einleitungstext; Akzentfarbe als CSS-Variable.
- Karten-Raster (mobil einspaltig): Bild, Bezeichnung, Prioritäts-Badge, Preis (optional), „2 von 3 noch offen“, Button „Zum Shop“ (`rel="noopener nofollow sponsored"`, neuer Tab) und Button „Ich habe eins gekauft“. Bei Rest 0: Karte gedimmt, Badge „Bereits besorgt“.
- Steuerleiste: Sortierung (Priorität, Preis ↑/↓, Name, offene zuerst, manuelle Reihenfolge) und Filter (Alle / Noch offen / Besorgt, Priorität, Preisspanne). Clientseitig auf `data-*`, Zustand in der URL (`?sort=&status=`), ohne JS sinnvolle Standardreihenfolge.
- Melden: `fetch` POST → Token kommt zurück, `localStorage` (`baukastenWishlist.<itemId>` = Liste der Tokens) → Button „Rückgängig“ erscheint nur in diesem Browser. Bestätigungsdialog vor dem Melden (kein `confirm()`, eigenes Inline-Panel). `aria-live` für Statusmeldungen.
- Beim Laden `GET state`, damit ein Seitencache keinen alten Stand zeigt.
- Geschlossene Liste (Datum vorbei + Option an): Buttons aus, Hinweis.
- Passwortgeschützte Seite: Block/Shortcode rendert nur, wenn `post_password_required()` für den einbettenden Beitrag false ist (Falle 28). REST prüft: Liste und Wunsch veröffentlicht, Wunsch gehört zur Liste, Liste offen; Content-Visibility `Frontend_Guard::is_blocked()` falls vorhanden.
- Schriften/Skripte nur lokal, keine Fremd-Requests; Bilder über `wp_get_attachment_image()` (srcset, lazy).

## Sicherheit
- REST öffentlich ohne Nonce für Gäste (Seitencache), dafür: Rate-Limit je IP-Hash (Fixed Window wie `NewTab\Suggest::within_rate_limit()`), 429 bei Überschreitung; Token 32 Byte `random_bytes`, gespeichert nur als `wp_hash`; Undo nur mit passendem Token; `args`-Sanitizing; kein Kauf über Menge hinaus.
- Backend: Capability `edit_posts` für CPTs (Standard-Map), Tab und Reset-Handler `manage_options` + `check_admin_referer()` im Handler, Rückweg `Baukasten\Admin::redirect_to_tab()`.
- Ausgabe überall escaped; `wp_json_encode` in Attributen mit `esc_attr` (Falle 15).

## Repo anpassen (Liste aus Commit `c7fc47a`)
`.github/workflows/lint.yml` (Matrix), `release.yml` (case-Zeile), `.wp-env.json` (plugins), `tests/bootstrap.php` (require), `tests/integration/WishlistTest.php`, `baukasten/includes/class-catalog.php` + Core-Übersetzung + Core 1.0.2 (Header, `VERSION`, Stable tag, Changelog, Upgrade Notice), `baukasten/readme.txt`, `baukasten/README.md`, `README.md`, `README.de.md`, `docs/DEVELOPER-GUIDE.md` (§1, Tab-Position 90, ggf. neue Falle), `docs/RELEASING.md` (Tag- und Slug-Tabelle), `docs/WORDPRESS-ORG-COMPLIANCE.md` (Tabelle, Zählung), Briefing als `docs/briefings/06-briefing-wishlist.md` + Index, `CLAUDE.md` Teil B (und veralteten Pfad `D:\Code` → `D:\Claude` korrigieren).

## Tests (PHPUnit, CI wp-env)
Sanitizing (Preis „12,99“ → `12.99`, negative Menge → 1, `javascript:`-URL verworfen); claim bis Menge, danach 409; unclaim nur mit richtigem Token; reset; Entwurf/fremde Liste → 404; geschlossene Liste → 403; Rate-Limit → 429; Shortcode-Ausgabe enthält Karten und escaped Titel; passwortgeschützte Seite rendert nichts; uninstall räumt auf.

## Ablauf
1. `git switch -c work/wishlist`; Gerüst, `composer lint` (`../baukasten/vendor/bin/phpcs`).
2. Funktionen, Assets, Block, Tab.
3. i18n: `wp i18n make-pot`, deutsche `.po` vollständig, `make-mo`; Core-Catalog-String.
4. Tests + Repo-Änderungen, Commits je Thema, Push `work/wishlist` → CI (lint + integration) grün.
5. `php -d extension=zip bin/build.php baukasten-wishlist` und `baukasten`; `make-assets.php`.
6. Deploy per SSH: Backup nach `~/baukasten-backups/<Datum>/` (`wp db export`, `plugins.tgz`), `scp -P 222` beider ZIPs, `wp plugin install … --force --activate`.
7. Live-Einrichtung: Liste „Lenis 2. Geburtstag“ (Person Leni, Anlass 2. Geburtstag, 11.12.2026), 3 Test-Wünsche mit Bild aus der Mediathek, Seite „Wunschliste Leni“ mit Block.
8. Abnahme mit Claude in Chrome (neuer Tab, eingeloggt Backend + Seite; ausgeloggt Profil „Test (ausgeloggt)“/`curl`): Anlegen/Bearbeiten, Darstellung Desktop + 390×844, Sortieren/Filtern, Melden → zweiter Browser sieht Stand, Rückgängig nur im meldenden Browser, Menge erschöpft → „Bereits besorgt“, Reset im Backend, Netzwerk ohne Fremd-Requests, Konsole fehlerfrei. Rate-Limit nur per `wp eval-file` mit Doku-IP, nicht durch Massenklicks. Protokoll `docs/acceptance/wishlist.md` mit Screenshots.
9. Testmeldungen danach zurücksetzen; Testwünsche bleiben als Entwurf, damit Torsten echte Produkte einträgt (oder er sagt, dass sie weg sollen). Bericht an Torsten; Merge, Tags, Releases nur nach Freigabe.

## Bewusst nicht im Umfang
Käufernamen, E-Mail-Benachrichtigungen, Reservierung mit Ablauf, Preisabruf aus Shops, Affiliate-Links, Import.

## Entscheidungen bei der Umsetzung (15. November 2026)

- Gast meldet mit **einem anonymen Klick**; jeder Klick zählt ein Stück. Käufernamen werden nicht erhoben, niemand sieht sie.
- Rückgängig nur im meldenden Browser (Token in `localStorage`), Admin setzt im Backend zurück.
- Produkt- und Kopfbild sind das **Beitragsbild**, statt eines eigenen Media-Pickers.
- Bewusst ohne Nonce für Gäste (Seitencache); stattdessen Rate-Limit je gehashter Adresse.
- Abnahme ohne Claude in Chrome (nicht verbunden): Playwright mit frischem Profil, Backend per `wp eval-file`. Protokoll: [`../acceptance/wishlist.md`](../acceptance/wishlist.md).
