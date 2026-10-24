# Baukasten

[English](README.md) · **Deutsch**

Eine Familie von WordPress-Plugins rund um Datenschutz – ein Toolkit für die Grundlagen und Addons für Funktionen, die nur manche Websites brauchen – und ein Block-Theme dazu.

In diesem Repository liegen fünf Plugins und ein Theme, jedes mit eigenem Release. Sie werden gemeinsam entwickelt, weil sie Konventionen, Werkzeuge und eine Einstellungsseite teilen.

| Verzeichnis | Plugin | Tab | Was es tut |
|---|---|---|---|
| [`baukasten`](baukasten) | Baukasten - Privacy Toolkit | 10–30 | Der Kern mit drei eingebauten Funktionen: **Content Visibility** (ein Schalter öffentlich/privat für alle Inhalte), **Login Legal Pages** (rechtliche Links auf einem aufgeräumten `/login/`-Bildschirm mit Rate-Limit) und die **Consent Blocking Engine** (Drittanbieter bleiben bis zur Einwilligung blockiert, ein prüfbares Log, Härtung der Website und automatische Updates). Ein Bildschirm mit Tabs unter *Einstellungen → Baukasten*. |
| [`baukasten-multi-domain`](baukasten-multi-domain) | Baukasten Addon: Multi-Domain Landingpage | 40 | Gibt einer Seite ihre eigene Domain, sodass eine Installation unter jeder Domain eine andere Startseite ausliefert. |
| [`baukasten-business-cards`](baukasten-business-cards) | Baukasten Addon: Business Cards | 50 | Eine digitale Visitenkarte unter einem eigenen kurzen Link, für das Handy gebaut und vom Theme abgekoppelt, mit vCard-Download, QR-Code und einem Kontaktformular, das an die Inhaberin oder den Inhaber der Karte geht. |
| [`baukasten-2fa`](baukasten-2fa) | Baukasten Addon: Two-Factor Approval | 60 | Ein zweiter Faktor, bei dem eine andere, bereits angemeldete Sitzung die Anmeldung über ihre Adminleiste bestätigt. Braucht neben dem Kern das Plugin Two Factor. |
| [`baukasten-form-privacy`](baukasten-form-privacy) | Baukasten Addon: Form Privacy | 70 | Contact Form 7 und Flamingo ohne Datenlecks: Dateien nur dort, wo ein Formular ist, keine IP-Adresse, kein Adressbuch, eine Löschfrist, ein Exporter und Spamschutz ohne Drittanbieter. |
| [`baukasten-new-tab`](baukasten-new-tab) | Baukasten Addon: New Tab Page | 80 | Macht eine Seite zur Startseite des Browsers: Suche mit wählbarer Suchmaschine, Favoritenleiste, ein Gruß, der die Feiertage kennt, und ein Hintergrund aus der Mediathek oder, nach Einwilligung, von Unsplash. Wird in einem eigenen Tab gewählt. |

| Verzeichnis | Theme | Version | Was es tut |
|---|---|---|---|
| [`baukasten-tt5-fork`](baukasten-tt5-fork) | Baukasten Theme: TT5 Fork | 1.5 | Ein eigenständiger Fork von Twenty Twenty-Five 1.5 mit drei Designsystemen als Stile: **Organic** (Standard), **Modernist** und **Nocturne**, dazu **Noto Sans** und **Noto Serif** als Schrift-Presets. Alle Schriften sind enthalten, das Theme sendet also keine Anfragen an Dritte. Einzelheiten in [`docs/theme/TT5-FORK.md`](docs/theme/TT5-FORK.md) (Englisch). |

Alles ist auf Englisch geschrieben und bringt eine vollständige deutsche Übersetzung (`de_DE`) mit.

## Warum die Aufteilung so ist

Das Toolkit bestand aus vier Plugins, bis die drei, die ohne den Kern nicht laufen konnten, in ihn aufgenommen wurden: Wer das Toolkit will, will alles davon. Sie behalten ihre eigenen Verzeichnisse unter `baukasten/features/`, ihre Namespaces, Optionen und Hooks.

Die Addons bleiben getrennt, weil sie in unterschiedlichen Kombinationen gebraucht werden, von anderen Plugins abhängen (Contact Form 7, Two Factor) und in eigenem Rhythmus aktualisiert werden. Das kostet normalerweise verstreute Einstellungsseiten, deshalb sammelt der Kern sie: Ein Addon registriert einen Tab, der Kern zeigt ihn an.

Das Theme ist von den Plugins unabhängig: Es funktioniert ohne sie, und sie funktionieren mit jedem Theme. Zuerst war es ein Child-Theme von Twenty Twenty-Five. Als Fork braucht es kein Parent-Theme und trägt die drei Designsysteme nebeneinander, ausgewählt unter *Design → Editor → Stile*.

Plugins und Theme sind noch nicht in den Verzeichnissen von WordPress.org; das ist zurückgestellt. Bis dahin trägt jedes `Update URI: https://github.com/trstnbde/baukasten`, damit WordPress nie das Plugin oder Theme eines anderen mit demselben Slug als Update anbietet. Installiert werden sie aus den [Releases](https://github.com/trstnbde/baukasten/releases).

Ein Addon ist ein gewöhnliches Plugin. Es deklariert `Requires Plugins: baukasten` und ruft `Baukasten\Addons::register()` in der Action `baukasten/register_addons` auf. Nichts wird geerbt, nichts implementiert, und ein Addon, das den Aufruf mit `class_exists()` absichert, funktioniert auch ohne den Kern weiter.

Ein Addon kann in diesem Header auch andere Plugins nennen und sich unabhängig davon in sie einhängen: Business Cards braucht Contact Form 7, Two-Factor Approval das Plugin Two Factor. Der Baukasten-Tab und die fremde Integration bleiben getrennt.

Neu hier? Anfangen mit [`docs/DEVELOPER-GUIDE.md`](docs/DEVELOPER-GUIDE.md). Etwas ausliefern? [`docs/RELEASING.md`](docs/RELEASING.md). Die Dokumentation unter `docs/` ist auf Englisch.

## Releases

Ein Tag, ein ZIP, ein GitHub-Release je Plugin oder Theme:

| Tag | ZIP |
|---|---|
| `baukasten-1.0.1` | `baukasten-1.0.1.zip` |
| `baukasten-multi-domain-1.0.0` | `baukasten-multi-domain-1.0.0.zip` |
| `baukasten-business-cards-1.0.1` | `baukasten-business-cards-1.0.1.zip` |
| `baukasten-2fa-1.0.0` | `baukasten-2fa-1.0.0.zip` |
| `baukasten-form-privacy-1.0.0` | `baukasten-form-privacy-1.0.0.zip` |
| `baukasten-new-tab-1.0.1` | `baukasten-new-tab-1.0.1.zip` |
| `baukasten-tt5-fork-1.5` | `baukasten-tt5-fork-1.5.zip` |

Plugins: *Plugins → Neues Plugin hinzufügen → Plugin hochladen*. Theme: *Design → Themes → Theme hinzufügen → Theme hochladen*.

## Entwicklung

Jedes Plugin hat sein eigenes `composer.json` und `phpcs.xml.dist`:

```bash
cd baukasten            # oder eines der anderen Plugins
composer install
composer lint
```

Aus dem Wurzelverzeichnis des Repositorys:

```bash
php -d extension=zip bin/build.php --all                 # build/<slug>-<version>.zip für jedes Plugin und das Theme
php -d extension=zip bin/build.php baukasten-tt5-fork    # nur eines
php -d extension=gd  bin/make-assets.php baukasten       # Icon und Banner für das Verzeichnis
```

Die CI führt PHPCS je Plugin aus, `php -l` über alles mit PHP 8.0 bis 8.4 und Integrationstests gegen die WordPress-Testsuite in `wp-env` (`tests/`).

## Dokumentation

- [`docs/DEVELOPER-GUIDE.md`](docs/DEVELOPER-GUIDE.md) – wie die Teile zusammenpassen, die Konventionen und die Fallen
- [`docs/RELEASING.md`](docs/RELEASING.md) – Tags, Releases und der einmalige Slug-Schritt für eine spätere Einreichung bei WordPress.org
- [`docs/theme/TT5-FORK.md`](docs/theme/TT5-FORK.md) – das Theme: Token-Mapping für Organic, Modernist und Nocturne, Kontrast, Grenzen
- [`docs/WORDPRESS-ORG-COMPLIANCE.md`](docs/WORDPRESS-ORG-COMPLIANCE.md) – Bereitschaft für das Plugin-Verzeichnis, Richtlinie für Richtlinie (zurückgestellt)
- [`docs/briefings/`](docs/briefings) – die Briefings hinter Form Privacy und der Änderungsrunde 2026-09
- [`docs/acceptance/`](docs/acceptance) – Abnahmeprotokolle von der Live-Website

## KI-Offenlegung

**Teilweise KI-verändert (Partially AI-Modified).**

Teile dieses Repositorys wurden mit KI-Unterstützung geschrieben oder geändert, auf Grundlage von Code, den Menschen geschrieben haben, und nach Vorgaben, die Menschen formuliert haben. Jede Änderung wurde vor dem Commit geprüft und getestet.

Die Kennzeichnung folgt den [EU-Symbolen zur Kennzeichnung KI-generierter Inhalte](https://digital-strategy.ec.europa.eu/en/policies/eu-icons-labelling-ai-generated-content) der Europäischen Kommission. Sie unterscheiden drei Stufen: *Basic*, *Fully AI-Generated* und *Partially AI-Modified*. Die letzte gilt für bereits bestehende, von Menschen erstellte Inhalte, die teilweise mit KI verändert wurden. Die Symbole selbst dürfen ohne Namensnennung verwendet werden.

## Lizenz

GPLv2 oder später. Siehe die Datei `LICENSE` im Wurzelverzeichnis und in jedem Plugin. Das Theme basiert auf Twenty Twenty-Five (GPLv2 oder später, WordPress.org und Mitwirkende); seine Schriften stehen unter der SIL Open Font License 1.1.
