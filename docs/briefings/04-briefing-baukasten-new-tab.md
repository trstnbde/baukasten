# Teil B: Vorhaben „Baukasten Addon: New Tab Page“

## Ziel

Die Browser-Startseite `bhub start` (bisher eigenständiges PHP unter https://bhub.cc/start/, Version 2.0.0 als Rebuild vorbereitet) wird ein Baukasten-Addon auf https://bundeshub.de/, wird dort veröffentlicht und mit Claude in Chrome abgenommen. Funktionen und Leistungsziele von 2.0.0 bleiben. Die eigene Admin-Seite mit Passwort entfällt zugunsten von WordPress (Login mit Two Factor): Welche Seite die Startseite ist, wählt man unter **Einstellungen → Lesen**, so wie Homepage und Beitragsseite; alles Weitere steht im Baukasten-Tab.

**Name.** In der deutschen WordPress-Oberfläche heißt `page_on_front` „Homepage“, und auf bundeshub.de trägt die Homepage (Seite 59) den Titel „Startseite“. Ein Feld „Startseite“ direkt daneben wäre missverständlich. Darum heißt das Addon nach dem Firefox-Vorbild **New Tab Page**, deutsch **Neuer-Tab-Seite**.

| | |
|---|---|
| Plugin Name | `Baukasten Addon: New Tab Page` |
| Slug, Ordner, Text Domain | `baukasten-new-tab` |
| Namespace | `Baukasten\NewTab` |
| Tab | id `new-tab`, Position **80** |
| Optionen | `baukasten_new_tab_page` (Seiten-ID, registriert in der Gruppe `reading`), `baukasten_new_tab_settings` (autoload), `baukasten_new_tab_unsplash_key` (autoload `no`) |
| Hooks | `baukasten/new_tab/*` |
| Version | Addon `1.0.0`; Core `1.0.1` wegen des neuen Katalog-Eintrags |
| Zielgruppe | Geeks im weiten Sinn: Gaming, Sci-Fi und Fantasy, Wissenschaft, Maker, Retro-Computing, Netzkultur. Entwickler sind ein Teil davon, nicht die Mehrheit. |

## Referenzen

| Quelle | Wozu |
|---|---|
| https://bhub.cc/start/ | Ist-Version 1 (Tailwind-CDN, Alpine, Google Fonts, Unsplash-Key im Browser) – nur zum Vergleichen |
| `bhub-start-2.0.0.zip` aus der Analyse vom 24.10.2026 | Funktionale Referenz für Favoritenleiste, Suchmaschinenwahl, Vorschläge, Unsplash-Pool, Barrierefreiheit. Liegt das ZIP nicht vor, bei Torsten nachfragen. Nicht ins Repo kopieren. |
| `baukasten-business-cards/includes/class-renderer.php` | Muster für eine vom Theme abgekoppelte Ausgabe |
| `baukasten-business-cards/includes/class-legal-links.php` | Muster für Rechtslinks aus Login Legal Pages |
| `baukasten-multi-domain/includes/class-page-list.php` | Muster für `display_post_states` |
| `baukasten/features/content-visibility/includes/class-frontend-guard.php` | Wächter auf `template_redirect` Priorität 0; die Übernahme muss danach laufen |
| `baukasten/features/consent-blocking-engine/README.md` | Consent-Cookie, Kategorien, REST-Route |
| `baukasten/features/login-legal-pages/README.md` | `Legal_Pages`-Getter und Seitentitel als Linktext |

### Port-Landkarte 2.0.0 → Addon

| 2.0.0 | Addon |
|---|---|
| `index.php` (Router, Seite) | `includes/class-reading-setting.php` (Feld unter Lesen), `includes/class-renderer.php`, `templates/new-tab.php` |
| `?action=suggest` | REST `GET /wp-json/baukasten-new-tab/v1/suggest?q=` (`class-rest-controller.php`, `class-suggest.php`) |
| `?action=bg` | REST `GET /wp-json/baukasten-new-tab/v1/backgrounds` (`class-backgrounds.php`, `class-unsplash.php`) |
| `?action=icon` | entfällt: Favicons werden beim Speichern geholt und als Datei in `uploads/baukasten-new-tab/icons/` abgelegt (`class-favicons.php`, WP-Cron zum Nachladen) |
| `?action=go` | Suche ohne JS: GET auf die Seite mit `q` und `e`. Suchmaschinen leiten weiter. **Adressen nicht automatisch weiterleiten** (offener Redirect, siehe Sicherheit), sondern eine Zwischenseite mit Link zeigen |
| `admin.php`, `config.php`, `data/settings.php` | Feld unter Lesen + Baukasten-Tab `new-tab` (`class-settings-tab.php`, `admin/views/settings-tab.php`), Capability `manage_options` |
| `src/greetings.php` | `data/greetings-de_DE.php`, `data/greetings-en_US.php`, `data/holidays-islamic.php`, `data/holidays-jewish.php`, `class-greetings.php`, `class-holidays.php` |
| `assets/app.css`, `assets/app.js` | übernehmen und anpassen (Fira Sans, Feiertags-Gruß, Consent-Schalter, REST-URLs aus der Config) |
| Footer-Links aus Einstellungen | aus Login Legal Pages (`\Baukasten\LoginLegalPages\Legal_Pages`), siehe F5 |

## Geklärte Fragen (24.10.2026)

### Können die Feiertage aus öffentlichen Kalendern kommen?

Ja, aber zur Laufzeit lohnt sich nur für die islamischen Feste überhaupt eine Quelle, und auch dort reicht eine Tabelle, die beim Entwickeln erzeugt wird.

| Feiertage | Ergebnis |
|---|---|
| Ostern, Kölner Karneval, Europatag, Tag der Deutschen Einheit, Weihnachten, Silvester | Lokal berechnet bzw. fest. Feiertags-APIs wie feiertage-api.de oder Nager.Date helfen nicht: Weiberfastnacht und Europatag sind keine gesetzlichen Feiertage und stehen dort nicht. |
| Rosch ha-Schana, Jom Kippur | Der jüdische Kalender ist rein arithmetisch und lässt sich exakt berechnen (`jewishtojd()` aus `ext-calendar`). Öffentliche Quelle: die Hebcal-REST-API (`https://www.hebcal.com/hebcal?v=1&cfg=json&maj=on&year=YYYY`, ohne Key, Inhalte unter CC BY 4.0). Sie bestätigt z. B. Rosch ha-Schana 2.10.2027 und Jom Kippur 11.10.2027. Dient als Gegenprobe und als Quelle für die Fallback-Tabelle, nicht zur Laufzeit. |
| Eid al-Fitr, Eid al-Adha | Mondkalender, nicht sicher berechenbar. Die AlAdhan-API (Islamic Network, frei, ohne Key) rechnet mit `calendarMethod=DIYANET` nach dem Kalender der Diyanet, dem die DITIB-Gemeinden in Deutschland folgen. Gegenprobe bestanden: `hToG/01-10-1448` ergibt 09.03.2027 (Eid al-Fitr), `hToG/10-12-1448` ergibt 16.05.2027 (Eid al-Adha); beides deckt sich mit CNN Türk. Eine offizielle offene API der Diyanet selbst habe ich nicht gefunden. AlAdhan nennt weder Lizenz noch Rate-Limit. |

**Entscheidung:** Keine Laufzeit-Abfragen für Feiertage. `bin/fetch-holidays.php` (neues Werkzeug im Repo-Root) holt beim Entwickeln die Daten für 2026–2040 von AlAdhan (DIYANET) und Hebcal und schreibt `data/holidays-islamic.php` und `data/holidays-jewish.php` mit Quelle und Abrufdatum im Kopf. Die Dateien werden committet. Laufzeit: jüdische Daten per `jewishtojd()`, wenn `function_exists`, sonst aus der Tabelle; islamische Daten nur aus der Tabelle. Ein Integrationstest prüft, dass beide Tabellen mindestens zwei Jahre in die Zukunft reichen, und gleicht die jüdische Tabelle gegen `jewishtojd()` ab. Hebcal wird in `README.md` und `readme.txt` als Quelle genannt (CC BY 4.0 verlangt Namensnennung). Begründung: Der Core-Katalog verzichtet ausdrücklich auf ausgehende Requests; ein Feiertagskalender, der sich auf Jahre im Voraus festlegen lässt, ist kein Grund, damit anzufangen.

### Kann Unsplash über einen PHP-Proxy ausgeliefert werden?

Technisch ja, nach den Unsplash-API-Richtlinien nein. Die Hotlinking-Richtlinie verlangt für **jede** Nutzung über die API die zurückgegebenen `photo.urls`, damit jede Ansicht dem Fotografen gutgeschrieben wird. Wer eigene Bild-Infrastruktur nutzen will, soll das API-Team nach einem „photo views beacon“ fragen. Ein Proxy ohne diese Zusage riskiert den Entzug des API-Zugangs.

Sauber und ohne Drittanbieter geht es auf einem anderen Weg: **Die Unsplash-Lizenz** erlaubt, Fotos herunterzuladen, zu kopieren und zu nutzen, auch kommerziell, ohne Namensnennung (erwünscht ist sie trotzdem); verboten ist nur, damit einen vergleichbaren Dienst nachzubauen. Lädt Torsten Fotos von unsplash.com herunter und in die Mediathek hoch, liefert WordPress sie selbst aus. Das ist keine API-Nutzung, die Hotlinking-Pflicht greift nicht, und es fließt keine IP-Adresse an Dritte.

**Entscheidung:** Kein Proxy. Drei Hintergrundquellen, siehe F4: Mediathek (Standard), Unsplash-API mit Hotlinking hinter Einwilligung, aus. Eine Funktion, die API-Fotos automatisch in die Mediathek kopiert, wird nicht gebaut, solange Unsplash das nicht schriftlich zulässt.

## Funktionen

### F1 Auswahl unter Einstellungen → Lesen und Ausgabe

**Auswahl wie Homepage und Beitragsseite:**

- `register_setting( 'reading', 'baukasten_new_tab_page', [ 'type' => 'integer', 'sanitize_callback' => …, 'default' => 0 ] )` und `add_settings_field( …, 'reading', 'default' )` mit `wp_dropdown_pages()` (`show_option_none` „— Auswählen —“, nur veröffentlichte Seiten). Beschriftung „Neuer-Tab-Seite“, darunter ein Satz: „Diese Seite zeigt Suche, Favoriten und Gruß statt ihres Inhalts. Einstellungen unter Baukasten → New Tab Page.“ mit Link auf den Tab.
- Das Feld landet am Ende der Tabelle auf `options-reading.php`, weil core `do_settings_fields( 'reading', 'default' )` dort ausgibt. Nicht per JavaScript neben „Homepage“ verschieben; core-Markup bleibt unangetastet.
- Sanitize: muss eine veröffentlichte Seite sein; gleich `page_for_posts` wird abgelehnt (Fehler über `add_settings_error`, Wert bleibt alt), weil die Beitragsseite sonst ihre Beiträge verliert. Gleich `page_on_front` ist erlaubt, dann ist die Neuer-Tab-Seite die Homepage; der Hinweis darunter sagt das.
- `display_post_states`: Kennzeichnung „Neuer-Tab-Seite“ in der Seitenliste, wie „Homepage“ und „Beitragsseite“.
- Im Editor dieser Seite ein Hinweis wie bei der Beitragsseite: „Du bearbeitest die Neuer-Tab-Seite. Ihr Inhalt wird nicht angezeigt.“ (Block-Editor: `wp.data.dispatch( 'core/notices' ).createWarningNotice` aus einem kleinen Skript, nur auf dieser Seite geladen; klassischer Editor: `edit_form_after_title`).
- Wird die Seite gelöscht, in den Papierkorb gelegt oder auf Entwurf gesetzt, setzt das Addon die Option auf 0 (`wp_trash_post`, `before_delete_post`, `transition_post_status`), so wie core `page_on_front` zurücksetzt.
- Im Tab: ist keine Seite gewählt, ein Knopf „Seite anlegen und auswählen“ (Titel „Neuer Tab“, Slug `start`, veröffentlicht). Vorher prüfen, ob der Slug frei ist.

**Ausgabe:**

- Übernahme auf `template_include` (Priorität 99) für `is_page( $id )`, Admin-Bar und Robots auf `wp`, wie Business Cards. Der Wächter von Content Visibility läuft auf `template_redirect` Priorität 0 und damit vorher: Ist die Seite privat geschaltet, sehen Ausgeloggte den Login, ohne dass das Addon etwas tut. Die eigene Einstellung „Sichtbarkeit“ aus dem ersten Entwurf entfällt; der Tab verweist auf den Schalter der Seite und warnt, dass Favoriten private Ziele verraten können.
- Das Template ist ein **eigenes, minimales HTML ohne `wp_head()`/`wp_footer()`**: keine Theme-Styles, kein Block-CSS, keine Admin-Bar. Statify zählt die Seite nicht, weil eine neue Registerkarte kein Besuch ist: Filter `statify__skip_tracking` gibt für diese Seite `true` zurück (Statify zählt serverseitig auf `template_redirect`, den Filter darum spätestens auf `wp` registrieren; das JS-Tracking entfällt ohnehin, weil `wp_footer()` fehlt). Begründung im Klassen-Docblock.
- Tab-Titel = Seitentitel. Asset-URLs über `plugins_url()`, damit Multi-Domain sie auf den aufgerufenen Host umschreibt.
- Header auf `template_redirect` (nach Priorität 0): `Content-Security-Policy` ohne `unsafe-inline` (`default-src 'self'; img-src 'self' data:` plus `https://images.unsplash.com` nur im Modus `unsplash`; `connect-src 'self'; form-action 'self' https:; frame-ancestors 'none'; base-uri 'none'; object-src 'none'`), `Referrer-Policy: no-referrer`, `X-Robots-Tag: noindex`, `Cache-Control: no-cache, private`. Konfiguration für das JS als `<script type="application/json">`.
- **Multi-Domain** funktioniert ohne Zusatzcode: Weist man der Neuer-Tab-Seite eine Domain zu, wird sie dort Homepage, und `is_page()` greift weiter. REST-Aufrufe aus dem JS gehen relativ an den aktuellen Host (`/wp-json/…`), damit die Consent-Route die Origin akzeptiert. Ob bhub.cc oder eine Subdomain so angebunden wird, entscheidet Torsten.

### F2 Favoritenleiste im Firefox-Stil

Wie 2.0.0: Glasleiste oben, Favicon 16 px + Titel, Überlauf ins »-Menü per `ResizeObserver`, Pfeiltasten, Menü mit Escape. Neu:

- Favicons holt der Server beim Speichern und per Cron alle 7 Tage (`<link rel="icon">` der Zielseite, sonst `/favicon.ico`), mit `wp_remote_get()`, Timeout 3 s, max. 100 KB, Format per Magic Bytes geprüft. Erlaubt: PNG, ICO, GIF, JPEG, WebP. **Kein SVG aus fremder Quelle** in `uploads/` (direkt aufgerufen wäre es ausführbar). Ohne Treffer: Monogramm-SVG als `data:`-URI, Farbe aus dem Hostnamen.
- Nur URLs aus den Einstellungen werden geholt, kein offener Proxy.
- Editor im Tab: Zeilen mit Titel, Adresse, optionaler Symbol-Adresse, Hoch/Runter/Entfernen (Vanilla-JS). Nur `http`/`https`.

### F3 Suche

- Auswahlmenü links im Suchfeld (Listbox, Tastatur), Wahl in `localStorage`, `@`-Kürzel für eine einzelne Suche. Maschinen und Vorlagen (`{q}` = Suchbegriff), im Tab einzeln abschaltbar und editierbar:

| Maschine | Kürzel | Vorlage | Stand der Prüfung (24.10.2026) |
|---|---|---|---|
| Startpage (Standard) | `@sp` | `https://www.startpage.com/sp/search?query={q}&cat=web&language=deutsch&lui=deutsch` | `lui` wirkt, Gegenprobe mit `english` |
| Brave | `@b` | `https://search.brave.com/search?q={q}&source=web&lang=de&country=de` | `lang` wirkt, Gegenprobe mit `en` |
| Google KI-Modus | `@g` | `https://www.google.com/search?q={q}&udm=50&hl=de&gl=de` | Captcha im Test, `udm=50` nicht offiziell dokumentiert – erneut prüfen |
| Perplexity | `@p` | `https://www.perplexity.ai/search?q={q}` | funktioniert; Sprache folgt Browser und Frage |
| Yandex | `@y` | `https://yandex.com/search/?text={q}&lang=de` | Captcha im Test, `lang=de` unbestätigt – erneut prüfen |

- Adresserkennung wie 2.0.0 (`heise.de`, `localhost:8080`, IPs; Dateiendungen wie `app.js` sind Suchen). `Alt`/`Strg`+`Enter` öffnet in neuem Tab, `/` fokussiert das Feld.
- Vorschläge über REST, Anbieter wählbar (Google `client=firefox&hl=de`, DuckDuckGo `kl=de-de&type=list`, Brave `/api/suggest`, Aus). Antwortformat aller drei ist `["q",[…]]` (DuckDuckGo und Brave am 24.10.2026 geprüft). Transient-Cache 10 min, `Cache-Control: private, max-age=300`, Rate-Limit 60 Anfragen/Minute je `wp_hash( REMOTE_ADDR )` (Adresse nie speichern). Im Browser `AbortController` und Debounce 110 ms.

### F4 Hintergrund

Drei Quellen, im Tab wählbar:

| Modus | Herkunft | Einwilligung | Standard |
|---|---|---|---|
| `media` | Bilder aus der Mediathek, im Tab per Medien-Dialog ausgewählt (Mehrfachauswahl, Reihenfolge egal). Gedacht für Fotos, die Torsten unter der Unsplash-Lizenz heruntergeladen hat. | nicht nötig, alles von der eigenen Domain | ja |
| `unsplash` | Unsplash-API mit Hotlinking | nötig | nein |
| `off` | Verlauf in den Farben der Seite | – | nein |

**`media`:** Ausgabe über die Bildgrößen von WordPress: `wp_get_attachment_image_srcset()` mit `sizes="100vw"` auf einem `<img>` mit `object-fit: cover` (statt `background-image`, damit der Browser die Größe wählt). Für Hochformat-Bildschirme `sizes` so setzen, dass die Höhe abgedeckt ist (Seitenverhältnis des Bildes einrechnen). Die Dominanzfarbe beim Speichern einmal aus dem kleinsten Bild berechnen (GD/Imagick über `wp_get_image_editor`) und in Attachment-Meta ablegen. Quellenangabe aus der Bildunterschrift des Anhangs; ist sie leer, keine Angabe. Der Tab erklärt in einem Satz, dass die Unsplash-Lizenz keine Nennung verlangt, sie aber erwünscht ist, und dass die Bildunterschrift dafür das Feld ist.

**`unsplash`:**
- Access Key nur serverseitig (eigene Option, nie im HTML, nie in REST-Antworten). Pool: ein Aufruf `photos/random?count=30&content_filter=high`, Transient 3 Stunden, Sperre gegen parallele Abrufe, bei Fehler alter Pool weiter.
- Bild im Browser in Bildschirmgröße: `urls.raw` + `w`, `h` (Viewport × DPR, DPR max. 1,5, Raster 256 px, max. 2560), `fit=crop&crop=entropy&q=50&auto=format`. Messung 24.10.2026: Testfoto 344 KB statt 2.186 KB.
- Quellenangabe Pflicht (API-Richtlinien): „Foto: Name auf Unsplash“, Links mit `utm_source=<app_name>&utm_medium=referral`.
- **Kein Proxy, kein Kopieren in die Mediathek** (siehe Geklärte Fragen).
- **Einwilligung** über die Consent Blocking Engine: Ohne Einwilligung zeigt die Seite den Verlauf und im Footer den Schalter „Fotos von Unsplash laden“. Cookie `baukasten_consent` lesen wie `consent-bootstrap.js`; Kategorie = CBE-Einstellung `external_default` (Standard `functional`). Beim Klick erst `GET /wp-json/baukasten-consent/v1/consent-state`, dann `POST …/consent` mit **den bisherigen Kategorien plus der neuen** (`Content-Type: application/json`, gleiche Origin), sonst überschreibt der Klick frühere Entscheidungen. `POST` ersetzt die gespeicherte Auswahl (`Consent_State::write()`), darum das Zusammenführen. Weil die Neuer-Tab-Seite nicht gecacht wird, darf der Server die Entscheidung beim Rendern auch selbst lesen (`\Baukasten\ConsentBlockingEngine\Consent_State::allowed()`) und den Modus direkt ausgeben; das spart einen Bildwechsel nach dem Laden. Ohne die Core-Klasse: eigener Cookie `baukasten_new_tab_unsplash=1`, 12 Monate.

**Für alle Modi:** Dominanzfarbe sofort, Bild nach `decode()` einblenden, Wechselintervall im Tab (Standard 10 min, 0 = nur beim Laden), Pause bei verborgenem Tab, `prefers-reduced-motion` ohne Überblendung.

### F5 Footer

Links: Foto-Quelle bzw. Consent-Schalter. Rechts: Datenschutzerklärung, Haftungsausschluss, Impressum aus Login Legal Pages – die drei Getter `get_privacy_url()`, `get_terms_url()`, `get_imprint_url()` und als Linktext `get_label( $page_id, $fallback )` mit den IDs aus `get_option( 'wp_page_for_privacy_policy' )`, `get_page_id( Legal_Pages::OPTION_TERMS )` und `get_page_id( Legal_Pages::OPTION_IMPRINT )`; nicht `get_links()` (dessen Filter gilt dem Login-Bildschirm; Begründung wie in `Business Cards\Legal_Links`). Linktext = Seitentitel, so heißt der Terms-Link auf bundeshub.de „Haftungsausschluss“. Nicht veröffentlichte oder für Ausgeloggte private Seiten werden ausgelassen; der Tab warnt, wenn das passiert. Kein eigenes Linkfeld im Tab.

### F6 Grüße

**Zufall.** Bei jedem Aufruf wählt der Server einen zufälligen Gruß aus dem Pool. Kein Wochenmodus. Damit derselbe Gruß nicht in kurzer Folge wiederkommt, merkt sich das JS die letzten drei in `localStorage` (in `try/catch`) und tauscht bei einem Treffer gegen einen anderen aus dem Pool; der Pool steht dafür in der JSON-Config (ca. 3 KB).

**Feiertage haben Vorrang.** In der ISO-Kalenderwoche (Zeitzone `wp_timezone()`, Jahr und Woche mit `o`/`W`), die das Ankerdatum enthält, steht statt des Zufalls der feste Feiertagsgruß. Fallen zwei Anker in dieselbe Woche, gewinnt der Anker, der dem heutigen Tag am nächsten liegt; bei Gleichstand die Reihenfolge der Tabelle. Belegter Fall: 2027 fallen Rosch ha-Schana (2.10., Hebcal) und Tag der Deutschen Einheit (3.10.) in dieselbe Woche.

| Feiertag | Anker | Quelle zur Laufzeit |
|---|---|---|
| Kölner Karneval | Weiberfastnacht = Ostersonntag − 52 Tage | berechnet |
| Ostern | Ostersonntag | berechnet (Gauß/Meeus selbst implementiert, nicht `easter_date()`) |
| Eid al-Fitr | 1. Schawwal | `data/holidays-islamic.php` (AlAdhan, DIYANET) |
| Europatag | 9. Mai | fest |
| Eid al-Adha | 10. Dhu l-Hiddscha | `data/holidays-islamic.php` |
| Rosch ha-Schana | 1. Tischri, erster voller Tag | `jewishtojd( 1, 1, $y )`, sonst `data/holidays-jewish.php` (Hebcal) |
| Jom Kippur | 10. Tischri | `jewishtojd( 1, 10, $y )`, sonst Tabelle |
| Tag der Deutschen Einheit | 3. Oktober | fest |
| Weihnachten | 24. Dezember | fest (24. und 31.12. liegen immer in verschiedenen Wochen) |
| Silvester | 31. Dezember | fest; die Woche kann ISO-Woche 1 des Folgejahres sein |

Zum Testen: `?bk_date=YYYY-MM-DD` überschreibt das Datum, **nur** für Nutzer mit `manage_options`; dazu Filter `baukasten/new_tab/now`.

Platzhalter: `{hebrew_year}` (Jahr des Ankerdatums + 3761), `{unity_years}` (Jahr − 1990), `{next_year}` (Jahr des 31.12. + 1).

Speicherort und Sprache: Witze werden lokalisiert, nicht übersetzt. Darum keine `.po`-Strings, sondern `data/greetings-<locale>.php` mit den Schlüsseln `random` und `holidays`, Auswahl über `determine_locale()`, Fallback `en_US`. Die englische Datei schreibst du als sinngemäße Fassung. Filter: `baukasten/new_tab/greetings`, `baukasten/new_tab/holiday`. Der Inhalt unten ist für `de_DE` verbindlich; Änderungen nur nach Rückfrage.

**Zufallspool de_DE (52):**

```
Hallo Welt. Systemstatus: betriebsbereit.
Es gibt keinen Ort wie 127.0.0.1.
Schon versucht, es aus- und wieder einzuschalten?
Die Antwort ist 42. Die Frage bleibt offen.
Achtung, Heisenbugs: Sie verschwinden, sobald du hinsiehst.
Neuer Tag, neuer Speicherpunkt.
Quicksave nicht vergessen.
Kritischer Treffer auf die To-do-Liste!
Würfelglück heute: natürliche 20.
Erfolg freigeschaltet: Browser geöffnet.
Mana voll, Kaffee auch.
Der Endgegner heißt heute Posteingang.
Nebenquest verfügbar: Kühlschrank aufräumen.
Lade Level 2. Bitte nicht ausschalten.
Konami-Code eingegeben? Dann los.
Insert Coin to continue.
Teleporter kalibriert. Ziel: Feierabend.
Warp-Antrieb bereit, Captain.
Houston, wir haben kein Problem.
Bitte beamen. Aber erst nach dem Kaffee.
Handtuch eingepackt? Dann kann ja nichts schiefgehen.
Zweites Frühstück ist auch eine Mahlzeit.
Zeitreise geglückt: Du bist in der Gegenwart gelandet.
Neue Nachricht aus dem All: Auch heute nichts von Aliens.
Das Universum expandiert. Deine To-do-Liste hoffentlich nicht.
Schrödingers Posteingang: gelesen und ungelesen zugleich.
Die Entropie steigt. Aufräumen lohnt sich trotzdem.
Licht schafft 299.792.458 m/s. Dein WLAN etwas weniger.
Pi ist irrational. Dein Plan für heute nicht.
Kalibriert, kompiliert, koffeiniert.
Lötkolben heiß, Ideen heißer.
Erst messen, dann löten.
Der 3D-Drucker läuft. Die Geduld hoffentlich auch.
Raspberry Pi oder Himbeerkuchen? Warum nicht beides.
Brettspielabend? Die Würfel liegen bereit.
Retro-Modus: 8 Bit, volle Laune.
Diskette eingelegt. Laufwerk A: bereit.
Pixel für Pixel zum Ziel.
Es ist nicht DNS. Es kann nicht DNS sein. Es war DNS.
Kein Bug. Ein undokumentiertes Feature.
Um Rekursion zu verstehen, musst du zuerst Rekursion verstehen.
Es gibt 10 Arten von Menschen: die, die Binär verstehen, und die anderen.
Strg+Z fürs echte Leben wäre ein gutes Feature.
Kein Backup, kein Mitleid.
Tabs oder Leerzeichen? Heute keine Grundsatzdebatten.
HTTP 418: Ich bin eine Teekanne.
404: Prokrastination nicht gefunden.
sudo mach mir einen Kaffee.
Gummiente bereit. Erklär ihr dein Problem.
ping 127.0.0.1 – Pong. Du lebst.
Hack the planet. Oder erst mal die Mails.
Nächster Halt: Inbox Zero.
```

Kein Gruß nennt einen Wochentag, weil der Pool an jedem Tag gilt. Keine wörtlichen Zitate aus Filmen, Serien oder Büchern, nur Anspielungen.

**Feiertagsgrüße de_DE:**

| Schlüssel | Gruß |
|---|---|
| `carnival_cologne` | Kölle Alaaf! Kostüm-Update erfolgreich eingespielt. |
| `easter` | Frohe Ostern! Alle Easter Eggs gefunden? |
| `eid_al_fitr` | Eid Mubarak! Ein gesegnetes Fest zum Ende des Ramadan. |
| `europe_day` | Schönen Europatag! 27 Staaten, 24 Amtssprachen, ein Roaming-Tarif. |
| `eid_al_adha` | Eid al-Adha Mubarak! Ein gesegnetes Opferfest. |
| `rosh_hashanah` | Schana Tova u-Metuka! Ein gutes und süßes Jahr {hebrew_year}. |
| `yom_kippur` | Gmar Chatima Tova. Ein leichtes Fasten allen, die heute fasten. |
| `german_unity` | Schönen Tag der Deutschen Einheit! Seit {unity_years} Jahren ein Branch. |
| `christmas` | Frohe Weihnachten! Plätzchenvorrat: kritisch hoch. |
| `new_years_eve` | Guten Rutsch! Countdown läuft: Jahr = {next_year}. |

Jom Kippur ist ein Fast- und Bußtag: dort kein Witz, auch nicht in der englischen Fassung. Die Grüße zu Eid, Rosch ha-Schana und Jom Kippur bleiben respektvoll; Geek-Anspielungen nur bei den übrigen.

### F7 Schrift

Fira Sans für alles, auch die Grüße; die Monospace-Schrift aus 2.0.0 entfällt. Den Prompt `›` und den grünen Blockcursor behalten.

- Schnitte 400 (Fließtext, Leiste, Footer), 500 (Suchfeld, Menüs), 600 (Gruß), jeweils `latin` und `latin-ext` als WOFF2 unter `assets/fonts/`, getrennt per `unicode-range`. Lizenz als `assets/fonts/OFL-Fira-Sans.txt` (Namensschema wie in Business Cards). Quelle: das Fira-Repository von Mozilla bzw. die OFL-Dateien von Google Fonts, beim Entwickeln heruntergeladen, nie zur Laufzeit.
- `font-display: swap`, nur 600-latin per `<link rel="preload">`. Fallback: `"Fira Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`.
- Die Tab-Ansicht im Admin nutzt die WordPress-Admin-Schrift.

### F8 Tab „New Tab Page“ in Einstellungen → Baukasten

Abschnitte: Seite (gewählte Seite mit Link auf Lesen, Knopf „Seite anlegen und auswählen“, Vorschau-Link, Hinweis auf Content Visibility), Suche (Standard, aktiv, Vorlagen, Vorschlagsanbieter), Hintergrund (Modus; bei `media` Medien-Dialog; bei `unsplash` Key – Feld leer = behalten, Suchbegriffe, Ausrichtung, App-Name, „Speichern und Verbindung testen“; Intervall; „Cache leeren“), Favoriten (Editor), Hinweis auf die Rechtslinks aus Login & Legal. Speichern über `admin-post.php` mit Nonce im Handler. Validierung: Vorlagen nur `https://` mit `{q}`, URLs nur `http(s)`, Key-Format `[A-Za-z0-9_-]{20,100}`.

## Sicherheit

- **Offener Redirect vermeiden.** 2.0.0 leitete `?action=go&q=<adresse>` ungeprüft weiter. Im Addon leitet die No-JS-Suche nur auf Suchmaschinen-Vorlagen weiter; eine erkannte Adresse bekommt eine Zwischenseite mit dem Link. Integrationstest dafür.
- REST-Routen öffentlich, aber mit Rate-Limit, Längenbegrenzung (`q` max. 120 Zeichen) und `sanitize_text_field`.
- Key und Pool nie in Ausgaben; ein Test prüft das HTML der Seite und alle REST-Antworten auf den Key.
- Ausgaben escapen (`esc_html`, `esc_url`, `esc_attr`, `wp_json_encode` mit `JSON_HEX_TAG`), PHPCS sauber.

## Leistungsziele (gegen 2.0.0 gemessen)

| Messwert | Ziel |
|---|---|
| HTML der Seite, gzip | ≤ 5 KB |
| CSS + JS, gzip | ≤ 10 KB, versioniert, `immutable` |
| Schriften beim ersten Aufruf | ≤ 75 KB (drei Latin-WOFF2; `latin-ext` nur bei Bedarf) |
| Hintergrund bei 1366 × 800 | ≤ 350 KB |
| Requests beim ersten Aufruf | ≤ 7, im Modus `media` alle von bundeshub.de |
| Fremd-Requests ohne Einwilligung | 0 |
| TTFB (Median aus 5) | ≤ 250 ms |

## Repo anpassen

- [ ] `baukasten-new-tab/` mit allem, was die anderen Addons haben: `.distignore`, `.editorconfig`, `.gitignore`, `LICENSE`, `README.md`, `readme.txt` (Stable tag = Header-Version, Hebcal als Quelle nennen), `composer.json`, `phpcs.xml.dist` (Text Domain `baukasten-new-tab`), `uninstall.php` (Optionen inkl. `baukasten_new_tab_page`, Transients, Cron, `uploads/baukasten-new-tab/`; Mediathek-Bilder bleiben), `languages/` (`.pot`, `-de_DE.po`, `.mo` via WP-CLI), `.wordpress-org/assets/` (`bin/make-assets.php`).
- [ ] `bin/fetch-holidays.php`: erzeugt `baukasten-new-tab/data/holidays-*.php` aus AlAdhan (`calendarMethod=DIYANET`) und Hebcal, 2026–2040, mit Quelle und Abrufdatum. In `docs/DEVELOPER-GUIDE.md` Abschnitt 4 dokumentieren (einmal im Jahr laufen lassen).
- [ ] `baukasten/includes/class-catalog.php`: Eintrag `new-tab` (Slug `baukasten-new-tab`, Position 80, englische Kurzbeschreibung), deutsche Übersetzung in `baukasten-de_DE.po`, Core auf 1.0.1 (Header, `readme.txt`, Changelog, Upgrade Notice).
- [ ] `README.md` und `README.de.md`: Zeile in der Plugin-Tabelle und der Release-Tabelle.
- [ ] `docs/DEVELOPER-GUIDE.md`: Tabelle in Abschnitt 1, Tab-Positionen in Abschnitt 2 („A new addon starts at 90“), neue Fallen in Abschnitt 5.
- [ ] `docs/RELEASING.md` und `docs/WORDPRESS-ORG-COMPLIANCE.md`: Tag-Tabelle und Slug-Tabelle („Baukasten Addon: New Tab Page“ → abgeleitet `baukasten-addon-new-tab-page`, muss `baukasten-new-tab` sein).
- [ ] `.github/workflows/lint.yml`: Matrix um `baukasten-new-tab`. `.github/workflows/release.yml`: `case`-Liste um `baukasten-new-tab`.
- [ ] `.wp-env.json`: `"./baukasten-new-tab"` in `plugins`.
- [ ] `tests/integration/NewTabTest.php`: Option unter Lesen (Sanitize, Ablehnung bei `page_for_posts`, Zurücksetzen beim Löschen), Post-State, Übernahme nur auf der gewählten Seite und auch als Homepage, Content Visibility greift vorher, Feiertagswochen für alle zehn Anker 2026–2028 inkl. Kollision 2027, Ostern gegen bekannte Daten, Tabellen-Reichweite, jüdische Tabelle gegen `jewishtojd()`, Zufallspool (52, keine Duplikate), Key nicht in Ausgaben, kein offener Redirect, Rate-Limit der Vorschlags-Route (Fremd-Requests per `pre_http_request` abfangen).
- [ ] `docs/acceptance/new-tab.md`: Abnahmeprotokoll.

## Ablauf

1. Branch `work/new-tab`. Repo-Stand mit `git status` prüfen; lokale, ungepushte Änderungen von Torsten nicht anfassen.
2. Gerüst, Feld unter Lesen, Renderer, Tab, Optionen. `composer lint` sauber.
3. Favoriten, Suche, Vorschläge, Hintergrund (Mediathek, Unsplash mit Consent), Footer, Grüße, Feiertage (`bin/fetch-holidays.php` ausführen), Schrift.
4. Übersetzungen, Integrationstests, Repo-Anpassungen aus der Liste oben.
5. Branch pushen, CI grün.
6. Build: `php -d extension=zip bin/build.php baukasten-new-tab` und `… baukasten`.
7. Per SSH prüfen: `php -m | grep -i calendar` (entscheidet, ob die jüdische Tabelle zur Laufzeit gebraucht wird) und ob der Slug `start` frei ist.
8. Deploy auf bundeshub.de nach Teil A (Backup zuerst), Core 1.0.1 und Addon 1.0.0. Seite anlegen, unter Lesen auswählen. Die Homepage (`page_on_front`, Seite 59) bleibt unverändert, außer Torsten sagt etwas anderes.
9. Abnahme mit Claude in Chrome, Ergebnisse in `docs/acceptance/new-tab.md`:
   - Lesen: Feld vorhanden, Auswahl speichert, Ablehnung bei Beitragsseite, Post-State in der Seitenliste, Editor-Hinweis
   - ausgeloggt: Seite lädt, im Modus `media` keine Fremd-Requests; im Modus `unsplash` keine Fremd-Requests vor dem Klick, danach Foto und Kategorie zusätzlich zu bestehenden; Footer-Links erreichbar; privat geschaltete Seite führt auf den Login
   - Suche: alle fünf Maschinen, `@`-Kürzel, Vorschläge, Adresserkennung, No-JS-Fallback per `curl`
   - Favoriten: Überlauf bei 1366 px und 390 px, Tastatur, Favicons und Monogramme
   - Grüße: `?bk_date=` für jeden der zehn Anker plus 2027-10-02/03, Zufall über mehrere Aufrufe
   - Tab: Speichern, Fehlertexte, Medien-Dialog, Unsplash-Verbindungstest, Cache leeren
   - Messwerte der Leistungsziele (Network-Panel, `curl -w`), Konsole ohne Fehler, Dark Mode, `prefers-reduced-motion`
10. Zusammenfassung an Torsten: was live ist, Messwerte gegen Ziele, offene Punkte. Merge, Tags und Releases erst nach seiner Freigabe.

## Bewusst nicht im Umfang

Unsplash-Proxy und automatisches Kopieren von API-Fotos in die Mediathek (Richtlinien, siehe oben), Feiertagsabfragen zur Laufzeit, Favoritenordner, Synchronisation mit Browser-Lesezeichen, WordPress.org-Einreichung.

## Quellen der Klärung

- Unsplash, Guideline Hotlinking Images: https://help.unsplash.com/api-guidelines/more-on-each-guideline/guideline-hotlinking-images
- Unsplash API Guidelines: https://help.unsplash.com/api-guidelines/unsplash-api-guidelines
- Unsplash License: https://unsplash.com/license
- Hebcal REST API: https://www.hebcal.com/home/195/jewish-calendar-rest-api
- AlAdhan, Kalendermethoden: https://api.aladhan.com/v1/islamicCalendar/methods
- CNN Türk, Bayram-Termine 2027: https://www.cnnturk.com/turkiye/2027-resmi-tatiller-takvimi-dini-bayramlar-resmi-tatil-gunler-ne-zaman-2027-ramazan-ve-kurban-bayrami-tarihleri-3447415
