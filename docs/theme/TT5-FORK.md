# Baukasten Theme: TT5 Fork

A standalone fork of Twenty Twenty-Five (TT5) 1.5 that carries three design systems from Claude Design as style variations:

| Style | Claude Design project | Character |
| --- | --- | --- |
| **Organic** (default, `theme.json`) | `78565305-795b-4b4f-acdd-767e82fc3cbe` | Warm sand and terracotta, sage second accent, Caprasimo over Figtree, rounded frames, pill buttons, washed-out photographs |
| **Modernist** (`styles/02-modernist.json`) | `0204589f-ce0d-4aa0-81a8-1625c2b617ec` | Flat, Archivo throughout, near-mono red, no rounded corners, strong 2px rules, flush-left button labels, black-and-white photographs |
| **Nocturne** (`styles/03-nocturne.json`) | `78cfc91e-ec45-413b-95ea-0948642ebcec` | Compact dark interface, Inter at 500, 8px radii, outlined buttons, rules that fade out at both ends, photographs blended with `lighten` |

`styles/01-organic.json` repeats Organic under its own name, so that the style browser shows "Organic" next to the unnamed default and a site can return to it by name.

Two font presets, `styles/typography/noto-sans.json` and `noto-serif.json`, set body, headings, buttons, site title and pull quotes to Noto Sans or Noto Serif in any of the three.

Twenty Twenty-Five's own eight variations (`01-evening` … `08-midnight`), its color presets and its font presets are removed, and with them every font they used except Fira Code. Templates, template parts, patterns, section styles (`section-1` … `section-5`) and block styles are TT5's.

## Versions

| | |
| --- | --- |
| Forked from | Twenty Twenty-Five 1.5 |
| Theme version | 1.5 |
| Requires at least | WordPress 6.9 (`settings.border.radiusSizes`) |
| Tested up to | 7.1 |
| Text domain | `baukasten-tt5-fork`, German in `languages/de_DE.po` |

The theme was previously a child theme, "Baukasten Theme: TT5 Organic" (`baukasten-tt5-organic`). The fork needs no parent; TT5 may stay installed or go.

## What was changed against TT5 1.5

- Every `twentytwentyfive` identifier renamed: text domain `baukasten-tt5-fork`, function prefix `baukasten_tt5_fork_`, pattern slugs `baukasten-tt5-fork/…`, pattern categories `baukasten_tt5_fork_page` and `…_post-format`, the block bindings source `baukasten-tt5-fork/format`, the style handle `baukasten-tt5-fork-style`.
- `functions.php` loads `style.css` directly (no `style.min.css`), registers it as a second editor stylesheet after `assets/css/editor-style.css`, and loads the bundled translations with `load_theme_textdomain()`.
- `theme.json` is TT5's merged with the child's: the child's values win, its preset lists replace TT5's.
- The footer placeholder "Twenty Twenty-Five" reads "Baukasten TT5 Fork".
- `package.json`, `package-lock.json`, `contributing.txt` and `style.min.css` are gone.
- `style.css` carries an `Update URI`, like the plugins.

## Shared slugs

All three styles fill the same slugs, so patterns, section styles and content keep working when the style changes.

### Colors

TT5's eight roles keep TT5's meaning; ten more carry the steps a design system uses for hovers, tags and text on tints. Full 100–900 ramps are custom properties (`--wp--custom--color--{neutral|accent|accent-2}--{step}`), so the color picker stays short.

| Slug | Role | Organic | Modernist | Nocturne |
| --- | --- | --- | --- | --- |
| `base` | ground | #f5ead8 | #f3f2f2 | #161826 |
| `contrast` | text | #201e1d | #201e1d | #e9e9ed |
| `accent-1` | the accent: links, buttons, focus | #c67139 | #ec3013 | #9184d9 |
| `accent-2` | fill with `contrast` text (section 2), text on `accent-3` (section 4) | #e1eecc (sage 200) | #ffe0d9 (accent 200) | #423a6a (accent 800) |
| `accent-3` | fill with `accent-2` text (section 4), pressed state | #8c491a (accent 700) | #ae1800 (accent 700) | #d2cefd (accent 300) |
| `accent-4` | muted text: dates, authors | #645c50 | #605d5d | #b2b6ca |
| `accent-5` | surface: section 1, code, quote | #ebddc5 | #eae9e9 | #232532 |
| `accent-6` | divider | `currentColor` 16 % | `currentColor` 40 % | `currentColor` 16 % |
| `second-accent` | second accent, base | #7a8a5e | #e15b47 | #a7a1db |
| `accent-hover` | link and button hover | #b2622d (600) | #dd2b0f (600) | #b5abfc (400) |
| `accent-tint` / `accent-on-tint` | tag fill / its text | #fff2eb / #643312 | #fff2ef / #7c1405 | #423a6a / #f5f4ff |
| `second-accent-tint` / `-on-tint` | the same for the second accent | #f0fae1 / #3d472b | #fff2ef / #71261b | #423e5d / #f5f4ff |
| `second-accent-strong` | second accent as text | #56633f | #9e3526 | #d2cefd |
| `neutral-tint` / `neutral-on-tint` | neutral tag | #f9f4ed / #474238 | #f8f4f4 / #444141 | #3f424d / #f3f5fe |
| `neutral-strongest` | neutral 900 | #2e2b25 | #2d2b2b | #292b31 |

On a dark ground the roles flip by intent, not by step: a "tint" is a deep step there and the text on it a light one, as Nocturne's guide asks. Modernist and Nocturne are mono schemes; their second accent is a machine-derived stand-in and reads like the first.

### Type

One scale for all three, fixed (`settings.typography.fluid: false`):

| Slug | Size | Used for |
| --- | --- | --- |
| `x-small` | 0.6875rem (11px) | captions, table heads |
| `small` | 0.8125rem (13px) | h6, meta |
| `medium` | 0.9375rem (15px) | body |
| `large` | 1.25rem (20px) | h4 |
| `x-large` | 1.5625rem (25px) | h3 |
| `xx-large` | 2rem (32px) | h2 |
| `display` | 2.625rem (42px) | h1 |

| | Organic | Modernist | Nocturne |
| --- | --- | --- | --- |
| Body | Figtree 400 | Archivo 400 | Inter 400 |
| Headings, buttons, site title, pull quote | Caprasimo 400 | Archivo 800 | Inter 500 |

All fonts are registered once in `theme.json` and bundled as WOFF2 (latin + latin-ext): Figtree, Caprasimo, Archivo, Inter, Noto Sans and Noto Serif (with italics), Fira Code. A variation only chooses among them, so the Noto presets and Fira Code exist in every style. The browser downloads only what a page uses.

### Spacing

Each design system's `--space-*` base times its density; `70` and `80` are derived (×12, ×16), the systems define no such steps.

| Slug | Factor | Organic (4.4px) | Modernist (4px) | Nocturne (2.8px) |
| --- | --- | --- | --- | --- |
| `20` | 2 | 8.8px | 8px | 5.6px |
| `30` | 3 | 13.2px | 12px | 8.4px |
| `40` | 4 | 17.6px | 16px | 11.2px |
| `50` | 6 | 26.4px | 24px | 16.8px |
| `60` | 8 | 35.2px | 32px | 22.4px |
| `70` | 12 | 52.8px | 48px | 33.6px |
| `80` | 16 | 70.4px | 64px | 44.8px |

`--space-1` is `--wp--custom--spacing--1`. `contentSize` 645px and `wideSize` 1340px stay TT5's.

### Radii and shadows

| Slug | Organic | Modernist | Nocturne |
| --- | --- | --- | --- |
| `sm` | 8px | 0 | 4px |
| `md` | 16px | 0 | 8px |
| `lg` | 28px | 0 | 14px |
| `frame` (groups and columns with a background) | 32.2px | 0 | 8px |
| `pill` (buttons, search, inputs) | 999px | 0 | 8px |

Shadow presets `sm`, `md`, `lg` come from each system's `--shadow-*`: soft ink-tinted shadows on the light grounds, a hairline edge plus ambient darkness on Nocturne. Core shadow presets are off; no block gets a shadow by default.

## CSS outside theme.json

A style variation stores theme.json merged with the variation, so anything theme.json sets for Organic would follow the site into the other two. Design-specific rules therefore live in `style.css` and read values the variations set:

| Rule | Reads | Organic | Modernist | Nocturne |
| --- | --- | --- | --- | --- |
| Photographs (image, featured image, media & text, cover, gallery) | `--wp--custom--image--filter`, `--wp--custom--image--blend` | `saturate(0.6) contrast(0.85) brightness(1.1) opacity(0.94)` | `grayscale(1) contrast(1.08)` | `mix-blend-mode: lighten` |
| Groups and columns with a background, not full width | `--wp--preset--border-radius--frame` | rounded | square | 8px |
| Button labels | `--wp--custom--button--align` | centred | flush left | centred |

The rest of `style.css` is the same for all three: underline 3px below the baseline, focus ring only on `:focus-visible` (2px accent, 2px offset), `::selection` as a 30 % accent tint, disabled controls at 45 % opacity, plus TT5's own rules.

Per-variation block CSS in the variation files: Modernist sets 2px separators and 2px table-head rules in the 40 % divider; Nocturne draws separators and table rows as gradients that fade out over 48px at each end, and its buttons are outlined (1px accent, hover 12 %, pressed 22 % accent tint).

Section style 3 lays `accent-1` as a field. On Nocturne light text on the blurple reaches 2.7:1, so `styles/03-nocturne.json` sets the text of section 3 to `base` (5.5:1) for groups, columns and column.

## Contrast

Limits: text 4.5:1, from 24px 3:1, focus rings and control edges 3:1. Every design system documents that its accent is tuned for 3:1 — large text and interface chrome, not body copy. The theme follows the design systems and uses the accent for links and buttons anyway; ✗ marks where that falls short.

| Pair | Organic | Modernist | Nocturne |
| --- | --- | --- | --- |
| `contrast` on `base` | 13.95 ✓ | 14.86 ✓ | 14.54 ✓ |
| `contrast` on `accent-5` | 12.40 ✓ | 13.70 ✓ | 12.55 ✓ |
| `accent-4` on `base` (dates) | 5.53 ✓ | 5.83 ✓ | 8.75 ✓ |
| Section 2: `contrast` on `accent-2` | 13.68 ✓ | 13.37 ✓ | 8.48 ✓ |
| Section 3: text on `accent-1` | 4.60 ✓ | 3.95 ✗ (large text ✓) | 5.45 ✓ (`base`) |
| Section 4: `accent-2` on `accent-3` | 5.61 ✓ | 5.77 ✓ | 6.84 ✓ |
| Link `accent-1` on `base` | 3.03 ✗ | 3.76 ✗ | 5.45 ✓ |
| Link hover `accent-hover` on `base` | 3.77 ✗ | 4.25 ✗ | 8.55 ✓ |
| Button text on button | 3.03 ✗ (`base` on `accent-1`) | 3.76 ✗ (`base` on `accent-1`) | 4.63 ✓ (accent on 12 % tint, hover) |
| Focus ring `accent-1` on `base` | 3.03 ✓ | 3.76 ✓ | 5.45 ✓ |
| Divider `accent-6` on `base` (field borders, outline buttons) | 1.37 ✗ | 2.41 ✗ | 1.54 ✗ |

To meet 4.5:1 for links on Organic and Modernist, switch the link color in theme.json or the variation from `accent-1` to `accent-3` (Organic 5.72, Modernist 6.41), as both guides suggest for paragraph-size accent text.

## Not mapped

- Icons (Lucide, Phosphor): WordPress ships no icon library. Use SVG in patterns.
- `.tag`, `.seg`, `.radio`, `.dialog`, the card kicker (10px): no core block. The nearest tag is `core/post-terms` with the "Pill shaped" style; its colors can use the `*-tint` and `*-on-tint` slugs.
- Modernist's 2px rule under the navigation bar and its visible modular grid: TT5's header pattern has no border, and grids are a matter of the pattern.
- Nocturne's saturated section ground (`--color-section`): deck and landing-page scale only, not mapped.
- Italics exist only for Noto Sans and Noto Serif; the other faces are synthesised by the browser, as the design systems define none.

## Licenses

Theme: GPLv2 or later, based on Twenty Twenty-Five (WordPress.org and contributors). Fonts: SIL Open Font License 1.1, license in each `assets/fonts/<font>/OFL.txt`. Images: TT5's CC0 images, listed in `readme.txt`.
