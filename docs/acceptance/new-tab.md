# Abnahme: Baukasten Addon: New Tab Page 1.0.0 (Core 1.0.1)

Geprüft am 24. Oktober 2026 auf https://bundeshub.de/ (WordPress 7.1.3, PHP 8.4), eingeloggt in Chrome (Claude in Chrome, neuer Tab), ausgeloggt per `curl`. Backup vor dem Deploy: `~/baukasten-backups/2026-10-24/` (`db.sql`, `plugins.tgz`). CI (`integration.yml`, 72 Tests) auf `work/new-tab` grün.

## Stand der Seite auf der Live-Site

- Seite „Neuer Tab“ (ID 180, `/start/`) über den Knopf im Tab angelegt und ausgewählt. Die Homepage (Seite 59) ist unverändert.
- Content Visibility hat die neue Seite zunächst **privat** angelegt (Standard der Site). Für die Prüfung ausgeloggt habe ich sie auf **öffentlich** gestellt; sie ist es noch.
- Test-Favoriten (12, darunter eine nicht erreichbare Adresse für das Monogramm) und drei erzeugte Testbilder in der Mediathek (IDs 181–183, Bildunterschrift „Testbild n (erzeugt, kein Foto)“) sind gesetzt. Beides darf Torsten ersetzen.
- Auf dem Server fehlte `~/bin/wp`; angelegt aus `wp-cli.phar` (`~/bin/wp-cli.phar`).

## Ergebnisse

| Kriterium | Ergebnis |
|---|---|
| Lesen: Feld vorhanden, am Ende der Tabelle | ✅ „Neuer-Tab-Seite“, Hinweis mit Link auf den Tab |
| Lesen: Auswahl speichert | ✅ (Seite 180) |
| Lesen: Beitragsseite wird abgelehnt | ✅ Fehlermeldung, bisherige Auswahl bleibt (Option 180) |
| Seitenliste: Post-State | ✅ „Neuer Tab — Neuer-Tab-Seite“ |
| Editor-Hinweis | ✅ „Du bearbeitest die Neuer-Tab-Seite. Ihr Inhalt wird nicht angezeigt.“ |
| Ausgeloggt: Seite lädt, privat → Login | ✅ privat: 302 auf `/login/?redirect_to=…`, REST 401; öffentlich: 200 |
| Ausgeloggt, Modus `media`: nur eigene Domain | ✅ Hosts aller Requests: `bundeshub.de` |
| Footer-Links | ✅ Datenschutzerklärung, Haftungsausschluss, Impressum |
| Suche ohne JS, alle fünf Maschinen | ✅ 303 auf Startpage, Brave, Google KI-Modus, Perplexity, Yandex; unbekannte Maschine → Standard |
| Offener Redirect | ✅ `?q=evil.example.com` → 200, Zwischenseite mit Link, keine Weiterleitung |
| `@`-Kürzel, Vorschläge | ✅ `@b linu` schaltet auf Brave um, `/wp-json/baukasten-new-tab/v1/suggest` antwortet 200 mit `Cache-Control: private, max-age=300` |
| Favoriten, Überlauf | ✅ bei erzwungener Leistenbreite 390 px: 3 sichtbar, 9 im »-Menü, Pfeiltasten und Escape (Fokus zurück auf ») |
| Favicons, Monogramm | ✅ 11 Symbole aus `uploads/baukasten-new-tab/icons/`, die unerreichbare Adresse zeigt das Monogramm |
| Grüße, zehn Anker 2027 plus 2027-10-02/03 | ✅ alle zehn Feiertagsgrüße, 2.10. Rosch ha-Schana, 3.10. Deutsche Einheit, 27.9. Rosch ha-Schana, 4.10. wieder Zufall, 2.1.2028 Silvester („Jahr = 2028“) |
| Tab: Speichern, Fehlertexte | ✅ ungültige Vorlage, doppeltes Kürzel, zu kurzer Key, `javascript:`-Favorit: je eine Meldung, alles andere gespeichert |
| Tab: Medien-Dialog | ✅ Mehrfachauswahl, drei Bilder übernommen |
| Tab: Unsplash-Verbindungstest, Einwilligung | ⏳ offen: kein Access Key hinterlegt |
| Tab: Cache leeren | nicht ausgelöst (nichts zu leeren) |
| Konsole | Nur `InvalidStateError: … ViewTransition` aus dem Browser (auch auf wp-admin-Seiten); kein Fehler aus dem Addon |
| Dark Mode, `prefers-reduced-motion` | nicht im Browser geprüft (nicht emulierbar); per CSS vorgesehen |
| Mobilansicht 390 × 844 | `resize_window` änderte den Viewport nicht; Überlauf mit erzwungener Breite geprüft, Layout nicht auf einem Handy |

## Leistungsziele

| Messwert | Ziel | Gemessen |
|---|---|---|
| HTML, gzip | ≤ 5 KB | 4,7 KB (mit 12 Favoriten) |
| CSS + JS, gzip | ≤ 10 KB | 8,1 KB (2,8 + 5,3), `immutable`, versioniert nach Inhalt |
| Schriften beim ersten Aufruf | ≤ 75 KB | 72,8 KB (drei Latin-WOFF2) |
| Hintergrund bei 1920 × 889 | ≤ 350 KB | 52,8 KB (`testbild-2-2048x1152.jpg`; für 1366 × 800 nicht gemessen) |
| Requests beim ersten Aufruf | ≤ 7 | 7 ohne Favoriten (HTML, CSS, JS, 3 Schriften, Bild); jedes Favicon kommt hinzu (11 mit diesen Favoriten, 97 KB) |
| Fremd-Requests ohne Einwilligung | 0 | 0 |
| TTFB (Median aus 5) | ≤ 250 ms | **450 ms – verfehlt.** Die Login-Seite liegt gleich (410–490 ms), `wp-json/` bei 210 ms, statische Dateien bei 150–180 ms: die Zeit geht in das Laden von WordPress samt Theme und Plugins, nicht in die Seite selbst. |

## Beobachtungen

- Der Server überschreibt mit `Header always set` das `Content-Security-Policy` der Seite (nur `frame-ancestors 'self'` kommt an) und das `Referrer-Policy`. Die Richtlinie steht deshalb zusätzlich als `<meta http-equiv>` im Dokument (ohne `frame-ancestors`); `<meta name="referrer">` deckt die Referrer-Policy ab, nur die Weiterleitung der Suche trägt `strict-origin-when-cross-origin`.
- Der Seitentitel kam zuerst mit weichem Trennstrich („Neu­er Tab“) von wp-Typography; behoben, die Seite nimmt jetzt den Rohtitel.
- Die Zeit bis zur ersten Antwort ließe sich nur mit einem Cache oder einem früheren Abbruch des WordPress-Starts senken; beides ist nicht Teil dieses Addons.

## Offen

1. Unsplash: Access Key im Tab eintragen, dann „Speichern und Verbindung testen“, Einwilligung (keine Fremd-Requests vor dem Klick, danach Foto und Kategorie zusätzlich zu bestehenden), Quellenangabe.
2. Mobil, Dark Mode und reduzierte Bewegung auf einem echten Gerät ansehen.
3. Entscheidung, ob die Seite öffentlich oder privat bleibt und ob eine Domain per Multi-Domain zugewiesen wird.

## Nachtrag 24. Oktober 2026: Änderungen nach der Abnahme

Auf Wunsch von Torsten geändert und erneut deployt (Backup `~/baukasten-backups/2026-10-24-2/`, CI grün):

- Schrift Noto Sans (400, 600; Latin 27 KB statt 73 KB), im Suchfeld normales Gewicht.
- Einstellung für die Grüße (an/aus, Feiertage an/aus, eigene Liste); 52 neue nerdige Business-Grüße.
- Seitenwahl nur noch im Tab, nicht mehr unter Einstellungen → Lesen.
- Vorschläge immer von Startpage (`osuggestions`), Einstellung entfernt.
- Rechtslinks nicht mehr im Tab erwähnt; Footer unverändert die Standardlinks der Site.
- Seiten-Icon: Site-Icon bzw. `/favicon.ico` (WordPress-Standard).
- Unsplash ohne Einwilligung, dafür Datenschutzhinweis im Tab.

Geprüft: Unsplash-Verbindungstest („Mit Unsplash verbunden. 8 von 50 Anfragen in dieser Stunde übrig.“), Foto, Fotograf und Unsplash-Link mit `utm_source=bundeshub-de` auf der Seite, Vorschläge von Startpage, kein Einwilligungsknopf mehr, Tab mit Seitenwahl und Grußeinstellungen. Der Unsplash-Test ist damit erledigt; die Demo-Anwendung erlaubt 50 Anfragen pro Stunde.
