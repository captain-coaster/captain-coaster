---
name: Captain Coaster
description: A precise guide dressed like a park — light foundations with a navy and sunshine livery.
colors:
  bg: "oklch(0.9722948 0.0074044 260.73153)"
  surface: "oklch(1.0000000 0.0000000 89.87556)"
  fg: "oklch(0.2801795 0.0373303 243.47664)"
  muted: "oklch(0.4987397 0.0362213 248.55648)"
  border: "oklch(0.8537608 0.0240674 256.11250)"
  control-border: "oklch(0.4987397 0.0362213 248.55648)"
  action: "oklch(0.4762678 0.1461232 259.22022)"
  action-hover: "oklch(0.4031302 0.1277954 259.73286)"
  on-action: "oklch(1.0000000 0.0000000 89.87556)"
  selected: "oklch(0.9560695 0.0165843 259.41835)"
  subtle: "oklch(0.9434260 0.0142838 254.60684)"
  muted-strong: "oklch(0.4370345 0.0400869 247.44779)"
  brand: "oklch(0.3033100 0.0810882 264.89308)"
  on-brand: "oklch(1.0000000 0.0000000 89.87556)"
  on-brand-muted: "oklch(0.8537608 0.0240674 256.11250)"
  highlight: "oklch(0.8720509 0.1368842 84.26883)"
  on-highlight: "oklch(0.2801795 0.0373303 243.47664)"
  success: "oklch(0.4522793 0.0740924 168.10507)"
  success-bg: "oklch(0.9595166 0.0149495 164.72910)"
  on-success: "oklch(1.0000000 0.0000000 89.87556)"
  warning: "oklch(0.4643523 0.0941316 74.17692)"
  warning-bg: "oklch(0.9661014 0.0400057 88.19611)"
  danger: "oklch(0.5194091 0.1411480 27.82671)"
  danger-bg: "oklch(0.9658552 0.0171604 35.34490)"
  focus: "oklch(0.4762678 0.1461232 259.22022)"
  logo-ink: "oklch(0.3165834 0.0193707 229.74411)"
  rating-fill: "oklch(0.8720509 0.1368842 84.26883)"
  rating-edge: "oklch(0.4643523 0.0941316 74.17692)"
  rating-empty: "oklch(0.4987397 0.0362213 248.55648)"
  rating-scale-low: "oklch(0.52 0.15 28)"
  rating-scale-mid: "oklch(0.80 0.15 85)"
  rating-scale-high: "oklch(0.56 0.13 155)"
typography:
  display:
    fontFamily: "Barlow Condensed, Arial Narrow, Helvetica Neue, sans-serif"
    fontSize: "clamp(2.75rem, 2rem + 3vw, 4.75rem)"
    fontWeight: 800
    fontStyle: italic
    lineHeight: 1.08
  title:
    fontFamily: "Barlow Condensed, Arial Narrow, Helvetica Neue, sans-serif"
    fontSize: "clamp(2rem, 1.6rem + 1.5vw, 2.5rem)"
    fontWeight: 800
    fontStyle: italic
    lineHeight: 1.08
  numeral:
    fontFamily: "Barlow Condensed, Arial Narrow, Helvetica Neue, sans-serif"
    fontWeight: 800
    fontStyle: italic
    fontVariantNumeric: tabular-nums
  body:
    fontFamily: "Source Sans 3, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  lead:
    fontFamily: "Source Sans 3, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 400
    lineHeight: 1.5
  caption:
    fontFamily: "Source Sans 3, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: ".875rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Source Sans 3, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: ".75rem"
    fontWeight: 600
    lineHeight: 1.25
rounded:
  sm: ".375rem"
  control: ".5rem"
  card: ".75rem"
  pill: "999px"
spacing:
  space-1: ".25rem"
  space-2: ".5rem"
  space-3: ".75rem"
  space-4: "1rem"
  space-5: "1.25rem"
  space-6: "1.5rem"
  space-8: "2rem"
  space-10: "2.5rem"
  space-12: "3rem"
  space-16: "4rem"
  space-24: "6rem"
components:
  button-primary:
    backgroundColor: "{colors.action}"
    textColor: "{colors.on-action}"
    rounded: "{rounded.control}"
    padding: "10px 18px"
  button-primary-hover:
    backgroundColor: "{colors.action-hover}"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.fg}"
    rounded: "{rounded.control}"
    padding: "10px 18px"
  button-text:
    textColor: "{colors.action}"
    height: "44px"
    padding: "0 8px"
  field:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.fg}"
    typography: "{typography.body}"
    rounded: "{rounded.control}"
    height: "48px"
    padding: "10px 14px"
  field-disabled:
    backgroundColor: "{colors.subtle}"
    textColor: "{colors.muted}"
  field-readonly:
    backgroundColor: "{colors.bg}"
  checkbox:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.sm}"
    size: "24px"
  checkbox-checked:
    backgroundColor: "{colors.action}"
    textColor: "{colors.on-action}"
  tag-chip:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.fg}"
    rounded: "{rounded.pill}"
    height: "44px"
    padding: "0 14px"
  tag-chip-selected:
    backgroundColor: "{colors.selected}"
    textColor: "{colors.action}"
  star-input-star:
    size: "48px"
  error-summary:
    backgroundColor: "{colors.danger-bg}"
    textColor: "{colors.fg}"
    rounded: "{rounded.card}"
    padding: "16px"
  tab-bar:
    backgroundColor: "{colors.brand}"
    rounded: "1rem"
    height: "64px"
    padding: "4px"
  nav-tab:
    textColor: "{colors.on-brand-muted}"
    typography: "{typography.label}"
    rounded: ".75rem"
  nav-tab-active:
    textColor: "{colors.highlight}"
  page-band:
    backgroundColor: "{colors.brand}"
    textColor: "{colors.on-brand}"
    height: "120px"
  nav-badge:
    backgroundColor: "{colors.highlight}"
    textColor: "{colors.on-highlight}"
    rounded: "{rounded.pill}"
    height: "20px"
  header-link:
    textColor: "{colors.on-brand-muted}"
    height: "64px"
    padding: "0 12px"
  header-link-active:
    textColor: "{colors.on-brand}"
  round-button:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.fg}"
    rounded: "{rounded.pill}"
    size: "44px"
  search-field:
    backgroundColor: "{colors.subtle}"
    textColor: "{colors.fg}"
    rounded: "{rounded.pill}"
    height: "44px"
  search-row:
    textColor: "{colors.fg}"
    rounded: "{rounded.control}"
    height: "56px"
    padding: "0 8px"
  search-row-selected:
    backgroundColor: "{colors.selected}"
  units-toggle:
    backgroundColor: "{colors.subtle}"
    rounded: "{rounded.pill}"
    padding: "4px"
  units-option-current:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.fg}"
    typography: "{typography.caption}"
    rounded: "{rounded.pill}"
    height: "44px"
  ranking-row:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.fg}"
    height: "72px"
    padding: "10px 12px"
  ranking-rank:
    textColor: "{colors.brand}"
    typography: "{typography.numeral}"
  ranking-first-numeral:
    textColor: "{colors.highlight}"
    typography: "{typography.numeral}"
    fontSize: "6.75rem"
  edition-pill:
    backgroundColor: "{colors.highlight}"
    textColor: "{colors.on-highlight}"
    typography: "{typography.numeral}"
---
# Design System: Captain Coaster

> **Status: partly shipped.** Colors, fonts and tokens are live since the reskin (#413); the page shell and app navigation are shipped as Twig Components (`templates/components/Nav/`, `Page/`). The livery layer (brand role, 800 italic, motifs, clean ↔ fun budget) is defined here and in `tokens.css` (#469) and applied surface by surface: the navigation (#470), the ranking (#471) and Home (#417) carry it. Page content components still use the legacy `.cc-*` files and migrate one surface at a time (plan: #375). Every new or reworked component follows this document. See "Redesign: target vs. current" in `AGENTS.md` and `docs/agents/design-workflow.md`.

## Overview

**Creative North Star: "A precise guide, dressed like a park"**

Captain Coaster has two faces and the design holds both. The **guide** is serious: the ranking has to be trusted, a coaster page read in seconds, a form filled without friction. The **park** is a celebration: it is why riders come back. They are not mixed at random: the guide carries the content (white, ink, Source Sans, edge-to-edge lists, exact data), the park carries the dressing (the navy band, the sunshine livery stripes, big condensed numerals). This dressing is **the livery** (#469). Reference board with mockups: `docs/design/livery.html` (local only, not committed; illustrative data).

Navigation lives in the thumb: below the desktop breakpoint one floating pill carries the five destinations, and the top of the screen belongs to the page, its title and its own actions. Page content sits on the light canvas; the navy brand surface frames it (band, tab bar, desktop header), never the reading area.

### Audience

Enthusiasts who know models, manufacturers and stats by heart, and count their credits. Each group gets a design answer:

- **Credit hunters** (how many, where next): counters as hero numerals, the ridden mark, the top-100 meter.
- **Ranking fans** (who moves, and why): big ranks, movement marks, the monthly publication treated as an event, the method explained.
- **Reviewers**: stars, the rating distribution, readable reviews with pros and cons.
- **Trip planners**: park pages, the map, "not ridden yet" filters.
- **Visitors from search**: photo, rating and rank readable in three seconds, no account needed.

### Principles

1. **A guide dressed like a park.** Content stays clean and readable; the livery carries the fun. When a decoration hurts reading, reading wins.
2. **Numbers are the heroes.** Ranks, ratings, credits, heights and speeds are set in Barlow Condensed 800 italic, large and tabular. Enthusiasts read the numbers before the words.
3. **Edge to edge, not boxes.** Photos and lists run from edge to edge, separated by hairlines. A card only for what floats above the page: dialogs, sheets, desktop side panels.
4. **One loud moment per screen.** One element is allowed to shout (the #1, the ribbon, the sunshine band, your counter); everything else stays quiet.
5. **Sunshine is a surface, never text on white.** It fills, with ink on it or around it. Sunshine text exists only on the brand surface.
6. **The fun is in the visuals, not the words.** Copy stays plain and precise, without puns (see Voice).
7. **Thumb first.** Design at 390px, then widen. Navigation at the bottom, actions within reach, nothing hidden in a menu.

### The clean ↔ fun budget

Each page type has a budget that says which livery motifs it may use. A page migration starts by placing the page on this scale.

| Page type | Budget | Allowed |
| --- | --- | --- |
| Home, Ranking | 3/3 | Brand band with stripes, ribbon, sunshine sign band, the big #1 |
| Profile, milestones | 2/3 | Brand band with avatar, sunshine sign band, top-100 meter |
| Coaster, park | 2/3 | The photo is the header (stripes at its foot), hero numerals; the rest is guide |
| Search, lists, map | 1/3 | Compact brand bar, hero numerals |
| Forms, settings, legal, explainers | 0/3 | Pure guide: white, ink, action blue. Only the tab bar keeps the brand |

### The livery: brand motifs

Each motif comes from a real object of the park world; that is what keeps the site from looking like any other app. Each one is a single component or utility, never redrawn per page.

- **Livery stripes** (a coaster train's paint): two sunshine stripes rising left to right at the foot of a brand band or hero photo. Drawn as an SVG (the `livery-stripes` utility), never as a rotated gradient (it aliases into steps).
- **Hero numerals** (the stats board at a ride's entrance): Barlow Condensed 800 italic, tabular.
- **Sunshine sign band** (ride signage): sunshine, ink rules (2–2.5px), at most three figures, edge to edge. The profile is the one place with a fourth: the current year, once it has a dated ride.
- **Ribbon**: the one label placed on a photo, slightly tilted, sunshine with an ink edge.
- **The angle**: one slant, −10° (`-skew-x-10`), repeated: the New pill, the meter. Marks only 3–4px high (the active-tab and active-link underlines) double it to −20° so the slant still reads. The livery's speed turned into shape.
- **No divider motif.** Sections are separated by space and hairlines; the livery stripes already carry the signature. (The queue chain stays on the maintenance page only.)
- **One sunshine line at a time.** The stripes belong to bands and hero photos only. Bars carry no sunshine rule: the desktop header meets the band with a 1px white hairline at 14%, and the compact bar ends the same way.

Home's band carries the logo and one short tagline, written to sound native in each locale rather than translated word for word, on one line at 360px (chosen with the Home redesign, #417). Brand band heights are fixed so the band never eats the screen: Home `h-band-home` (136px), pages `h-band-page` (120px), profile `h-band-profile` (148px), then the 56px compact bar once the band scrolls away.

**Key Characteristics:**

- Condensed display typography, 800 italic for titles and numbers, readable multilingual body text.
- Navy brand surfaces with sunshine livery framing clean white content.
- Mobile-first layouts and visible, native interaction states.
- One floating pill for navigation on phones and tablets, a classic top bar on desktop.

## Colors

Fresh blue directs action; sunshine brings warmth to selected highlights. Ink carries primary text; slate carries secondary text. Cool canvas and white surfaces keep rider content legible. Green, amber and coral are reserved for success, warning and error, paired with their pale backgrounds and explicit labels or icons.

**The Semantic Role Rule.** Consume semantic roles in components; keep primitive colors inside the system. Logo colors remain original; the stronger action blue is an interface extension.

**Brand (navy)** is the livery's surface: the band at the top of Home, Ranking and profile pages, the tab bar, the desktop header, the active filter. It is the logo blue pulled darker, not a new hue. On it: white 13.6:1, sunshine 9.2:1, `on-brand-muted` 8.7:1; action blue fails (2.0:1), so links on brand are white or sunshine and the focus ring is sunshine.

**Proportions.** Roughly: white 58%, canvas 12%, ink 10%, brand 12%, sunshine 6%, action blue 2%. Lots of white, brand at the top and bottom, sunshine in small, strong doses.

Two support roles carry the navigation's quiet states. **Subtle** (pale mist) fills hover rows, the search field, preference tracks and placeholders. **Muted-strong** (deep slate) is secondary text that needs more weight: idle tabs and header links, the page context line, the current language value. Sunshine is a surface with ink on it or around it: the unread badge, the New pill, the active tab, the sunshine sign band, the ribbon, the big #1, the top-100 meter. Never text on white. Browser-drawn surfaces take the palette: text selection uses the selected fill with ink text, the caret and native accents use action blue.

**Rating stars** (look provisional, #423). Stars fill with sunshine and carry an amber edge: sunshine alone is 1.5:1 on white, the edge (7.1:1) keeps the shape legible. Empty stars of the rating input use the control boundary color (6.0:1).

**The Rating Scale.** Ordered rating data (the 0.5★–5★ distribution) runs coral → gold → green through three anchors, `rating-scale-low`, `-mid` and `-high`, interpolated with `color-mix(in oklch)` into ten steps (`--rating-step-1…10`, utilities `bg-rating-1…10`). The gold is lighter than both ends, so order is carried by position and labels, never by color alone. Steps 1–3 and 9–10 reach 3:1 on white (5.9 to 3.5:1); steps 4–8 do not (2.0 to 2.9:1), which is acceptable for large bar segments with their value labelled outside, not for text or thin marks. Segments sit on a hairline surface gap with their labels outside the bar, never on it.

**The Roles-Not-Palettes Rule.** Data needs (ratings, statuses, counts) are semantic roles over the existing hues. When a role needs a missing lightness step, extend that hue's ramp (`coral-500`, `gold-400`, `green-500`); never add a standalone hue or a parallel palette. Status and map-marker colors are not settled yet and keep their provisional aliases.

The frontmatter mirrors the canonical OKLCH values in `assets/styles/tokens.css`, the one token source since the reskin (#413). Measured WCAG sRGB contrasts: primary text 14.53:1, secondary text on white 6.01:1, default action 6.77:1, hover action 9.25:1, selected text 5.96:1 and ink on sunshine 9.79:1. Status pairings exceed 5.3:1. These are measured pairings, not a full accessibility certification. Keep normal text ≥4.5:1 and essential boundaries, focus indicators and large text ≥3:1.

## Typography

Barlow Condensed gives short headings the wordmark’s condensed energy: **800 italic** for page titles, section titles and every hero numeral (ranks, ratings, counters, stats); 600 upright stays where legacy pages still use it. Italic exists only in Barlow and only for short text, never a sentence longer than a line. Faces: 600 and 800 italic, latin subsets. Source Sans 3 carries body copy, controls and all four locales. Both load locally from `assets/fonts/` with `font-display: swap` and metric-matched fallbacks; retain their font licenses. Use the supplied display/title clamps, body/lead/caption/label scale and system monospace for code. Keep prose near 70 characters per line and inputs at least 16px.

The page title (h1) is the title step in the display face. Its context line (park, author, country) is lead-size semibold muted-strong on its own line beneath; a further muted line (former names) is caption. Group headings in navigation (Recent, Community, About, Language) are caption semibold muted in the body face, never the display face.

**The Label Step Rule.** The label step (12px, 1.25) exists for compact navigation labels only, the tab bar: it is the largest size that keeps the longest translation on one line at 360px. Tab labels are never truncated or wrapped: below 360px (and at 200% zoom) the bar shows the icons alone, the labels staying for assistive technology. Anything else uses caption or larger.

The guide’s own editorial headings use larger presentation sizes; the exported scale above is the reusable application foundation. Avoid fixed-height text containers and forced uppercase on long translated labels. Sentence case everywhere; no all-caps labels. UI labels (tabs, buttons) stay Source Sans 600.

## Layout

Start with a single column, flexible widths and wrapping labels. Use the 4px spacing rhythm. Add columns when content fits.

**The One-Container Rule.** Every page lays out in the same page container: the 75rem content maximum (`max-w-content`) with the fluid 16–48px gutter (`px-gutter`), centered. The desktop header, the band, the page body and the footer all use it, so the logo, the page title, the first column and the footer start on the same left edge. Backgrounds run edge to edge (the brand surfaces, a white page); content never does, except a list or a photo that bleeds to the screen edges below `sm`. Inside the container a page uses one of three widths, and nothing else:

| Width | Token | For |
| --- | --- | --- |
| Full | `max-w-content` (75rem) | Lists, ledgers, grids, pages with a side column |
| Text | `max-w-text` (40rem) | Forms, explainers, flashes: a readable line length, left-aligned in the container |
| Narrow | `max-w-narrow` (28rem) | Sign-in and register, centered, the page title aligned with it |

- **Side column:** a page's filters (`<twig:FilterPanel>`) are a 16rem column inside the container, left of the content, from `md`; 32px from it (40px from `lg`). It is sticky under the bar above it and scrolls on its own when taller than the screen. It has no background, border or card of its own. Its scrollbar is thin and drawn only while the column is hovered or holds focus (the `scrollbar-quiet` utility).
- **Page background:** the canvas by default; a page made of edge-to-edge lists or photos sets `pageSurface` and sits on white (the Ranking). Below `md` such a page starts flush under its band, so a cover photo meets the stripes.
- **The one exception** is the Map: it fills the screen, outside the container, with its filters as a white panel against the left edge.
- A page never sets its own maximum width or side padding. A new need is a new row in this table, decided here first.

**The Thumb Rule.** Below `lg` (64rem), navigation is the floating bottom bar: five equal destinations, Home · Ranking · Search · Map · Profile, with Search a plain tab in the middle. The bar floats 12px above the bottom edge plus the safe area, at most 28rem wide, and never hides; the page reserves room for it at its foot. From `lg`, a classic 64px top bar replaces it. Destinations without a tab (reviews, Tops, riders, contact, blog, privacy) live in the footer and on the Profile page, never in a hamburger.

**The Page-Owns-Its-Top Rule.** The top of the screen is the page's title, with no row spent on buttons alone: the back button (detail pages only, below `lg`) sits left of the title and the page's actions right of it, on the title's line. Below `lg`, once that row scrolls away, the 56px compact brand bar fades in at the top with the same back button, the title and the actions; it takes no room before that.

- **Band pages** (a page sets `headerBand`; today the Ranking and Home) open with the brand band, plain navy, edge to edge above any side column: title on one line (800 italic; a page whose title is long sets a `shortTitle` shown below `lg`, and anything longer is cut with an ellipsis rather than wrapped), one context line under it, livery stripes at its foot. Its height is fixed, 120px below `lg` and 156px from `lg`, so the context line never wraps: what doesn't fit moves into the page.
- **Home** opens with its own band (`<twig:Home:Band>`); the title is kept for assistive technology only. A visitor gets 136px below `lg`, 128px from `lg`: the full logo below `lg` (the desktop header already carries it), then the tagline in 800 italic on one line. A member gets a shorter band, 80px with the logo alone below `lg`, and from `lg` a 44px strip that only carries the stripes. The tagline is a promise to the visitor ("Find your next favorite coaster."); its es and de versions are still to be read by native speakers (#417).
- **Profile** opens with its own band (`<twig:Profile:Band>`), 148px below `lg` and 172px from `lg`: the avatar, the name as the h1 on two lines at most (then an ellipsis: the band never grows and the actions never leave the screen), the home park and "Member since" under it, the page's round buttons at its right.
- **Other pages** keep the title on the canvas until they migrate.

Sticky elements stack against these heights: side panels stick below the 56px compact bar (below `lg`) or the 64px desktop header plus its 1px line. Use at least 44px interaction targets, preferably 48px. Preserve logical DOM order as layouts change. Validate at narrow phone widths (360px) and 200% zoom; allow tables and code to scroll within their own containers.

## Elevation & Depth

Use tonal surfaces and restrained borders first. Raised elements use `--shadow-raised` (0 4px 16px oklch(0.28 0.04 245 / .09)); overlays use `--shadow-overlay` (0 12px 32px oklch(0.28 0.04 245 / .16)). Depth communicates layering, not decoration.

**Livery bars.** The tab bar, the desktop header and the compact page bar are solid brand navy; a touch of translucency may come later, but they stay navy. The tab bar carries a 1px white ring at 12% and the overlay shadow, so it stays distinct over the brand footer; the desktop header and the compact bar end with a 1px white hairline at 14%.

**The Floating Glass Rule.** Translucency is left to the round page buttons on a light surface (canvas, map): white at 75% with a strong backdrop blur and the raised shadow. On a brand surface they are white at 14%, 24% on hover, with a white icon and no shadow. Content cards, panels and dialogs stay opaque.

## Shapes

Small accents use the small radius; controls use the control radius and containers the card radius. Pill shapes belong to compact tags and to navigation-scale controls: the tab bar and its active tab, the search field, the language chip, the units toggle and badges. Floating page buttons are 44px circles. Borders are 1px: pale `border` for decorative separation, stronger `control-border` where a boundary identifies an input. Ink strokes of 2–2.5px exist only around sunshine (sign band, ribbon, big #1, meter), like a painted sign. Small radii: 4px on photos and thumbnails, the control radius on controls, pills for navigation and chips; large rounded content cards give way to edge-to-edge sections. One slant: −10°.

Keep original logo artwork intact. It has one source, `<twig:Logo>` (inline SVG): the lettering is logo ink (#28343A) on light surfaces and white on dark ones (`class="text-white"`), the mark never changes. The full logo appears at 28px high (193px wide): in the desktop header, on Home below `lg`, and in the footer; other pages carry no logo on phones. In compact UI the head mark (the star mask) may stand alone; it keeps its original colors and reads on light surfaces too. The favicon and app icons are the head mark: bare on a transparent ground in the browser tab (it reads on light and dark tabs), centered on ink-900 where the platform needs an opaque tile (iOS home screen, Android maskable), since one tile has to suit light and dark home screens. Proposed minimum logo widths: 180px horizontal, 238px stacked, with at least 12px clear space in compact UI and 24px in standalone placements.

## Dark theme

Planned, not active. The dark theme swaps roles, it doesn't invert colors: the brand navy (lifted one step), sunshine, ink on sunshine (`on-highlight`), the stripes and the photos keep their values; the canvas, surfaces, text, lines, action blue and state colors change. The band stays distinct from the deeper canvas through its saturation and stripes. Values live in `tokens.css` under `:root[data-theme="dark"]` with their measured contrasts.

- Text on sunshine always uses `on-highlight`, never `fg`: `fg` turns light in the dark theme.
- On the dark theme, action blue becomes a light blue with ink text on filled buttons (`on-action`); a success fill takes ink text too (`on-success`).
- Success and danger are both light in the dark theme: the shape rule (Accessibility) matters even more there.
- Activation waits until the main pages no longer use legacy `.cc-*` colors (many are hardcoded): system preference by default, a switch on Profile.

## Accessibility

- **Color never carries meaning alone.** Every pair of states also differs by shape or text: filled vs outlined, + vs −, arrow up vs down, an icon. Movement marks pair the color with an up or down arrow; pros and cons tags are filled for a pro, outlined for a con (#472).
- **Different lightness for neighbours.** When two state colors sit side by side (success and danger), one is dark and filled, the other light or outlined. The rating scale already rises in lightness.
- **Measured contrast.** Text ≥ 4.5:1, UI boundaries, focus and large text ≥ 3:1. Sunshine is never text on white.
- **Checked per PR** with Chrome DevTools, Rendering, "Emulate vision deficiencies" (deuteranopia, protanopia, achromatopsia), as well as 360px, 200% zoom and keyboard.
- Italic only on short display text; body copy stays upright.

## Voice

Like an enthusiast who knows the subject, talking to another one: precise, direct, warm without overdoing it. Trade terms (credits, airtime, launch, RMC) are welcome: they are the audience's language. No puns and no exclamation marks; the personality lives in the visuals. One deliberate exception: the ride-operator voice of the maintenance page.

| Context | Do | Don't |
| --- | --- | --- |
| Button | Rate this coaster | Let's ride! |
| Empty state | No ratings yet. Rate the coasters you've ridden and your stats show up here. | Your track is empty! Time to hop on board! |
| Error | Your rating wasn't saved. Check your connection and try again. | Oops, something went off the rails! |
| Announcement | The September ranking is out. Steel Vengeance stays #1. | Hold on tight, the new ranking just dropped! |

## Components

A brand surface marks itself with `data-brand` (band, bars, tab bar, footer). Shared components placed on one adapt through `in-data-brand:` utilities (logo lettering, round buttons, footer links) instead of taking a prop.

Buttons are one Twig Component, `<twig:Button>` (a link styled as a button when it has `href`), clear and compact in four variants: primary action blue with white text, deeper blue on hover; secondary white with ink text, a control-line boundary and the subtle fill on hover; text, an action-blue label (44px high, underlined on hover) for a secondary action beside a submit; danger, white with a danger border and text and the danger-bg fill on hover, for a destructive action (delete account). Primary and secondary are at least 48px high with 10px 18px padding and semibold body type; disabled buttons fade to 60% opacity. `block` makes a form's submit full width below `md` and sized to its label from `md`. Controls retain visible keyboard focus: a 3px focus-blue outline with a 3px offset on links, buttons, selects and summaries, keyboard only. Pill tabs and full-width settings rows draw the same outline inset (−3px) so it isn't clipped. On dark surfaces, the brand surface included, use a sunshine focus indicator (`focus-visible:outline-highlight`); code panes use an inset outline to avoid clipping. Disabled controls are visibly muted and remain semantically disabled.

Use native links, buttons, disclosure and radio controls. Selection needs a shape, check or label as well as color. Put validation under its field (see Forms) and announce saved/copied outcomes through polite live regions. “Ridden, unrated” is a valid neutral state. Read-only star ratings use the same two-path star as the rating input (amber outline painted over the sunshine fill, half state clipped), always five stars, announced as “3.5/5”. The rating input is shipped in the review form (see Forms); richer ride tracking remains undecided in `PRODUCT.md`.

### Forms

Every Symfony form renders through one global theme (`templates/form/fields.html.twig`): templates call `form_row` / `form_errors` and place their buttons as `<twig:Button>`; labels, help, autocomplete and constraints live in the FormType, never in the template.

- **Field row:** label (body semibold ink) above, help (caption muted) between label and field so it is read before typing, error under the field (a 16px circle-alert icon and caption-semibold danger text). Only optional fields are marked, "(optional)" after the label in muted normal weight; no asterisks.
- **Controls:** white surface, 1px control-line border, control radius, at least 48px high, 16px text, 10px 14px padding, muted placeholder. Hover turns the border ink; focus draws the standard 3px focus outline at 3px offset. Disabled takes the subtle fill, a pale line border and muted text; read-only the canvas fill with a pale line border. Textareas start at 9rem and resize vertically only. Selects stay native, with a 20px muted-strong chevrons-up-down icon at the right.
- **Invalid:** danger border plus a 1px inset danger ring. It shows for server errors (`aria-invalid`) and for native constraints only once the user has interacted (`:user-invalid`), never on load.
- **Checkbox and radio:** a 24px shape with a 1.5px control-line edge (checkbox small radius, radio round), filled action blue when checked with a white check or dot. The label wraps the input and the whole row is at least 44px. A single checkbox's help and errors align with its label text, not the box.
- **Groups:** radio groups, the star rating and tag chips are fieldsets named by a legend (set like a label) and described by their help and errors.
- **Error summary:** after a failed submit, a card-radius danger-bg card with a danger border opens the form: a bold danger "N fields need attention" line behind a 20px circle-alert icon, any form-level message, then links to each invalid field (a group links to its first option). It takes focus on load and draws no focus ring, since it isn't interactive. The sign-in error is the same danger card, announced as an alert.
- **Star rating input:** ten native radios, 0.5 to 5, two half-star labels per 48px star so each half is a 24px target, each named "3.5 out of 5". Same two-path star and rating tokens as the read-only stars (empty edge `rating-empty`; filled sunshine with the amber edge). The fill is CSS-only, arrow keys move natively, and the focus outline wraps the whole row.
- **Tag chips:** pill checkboxes, at least 44px, 1px line border (control-line on hover). Checked is the selected fill with action border and text, semibold, behind a leading check icon. Once `max` (3) are chosen the others disable: dashed border, muted text. The server enforces the same limit (a Count constraint) and the help states it ("Up to 3"). Most used tags come first; below `md` only the first 8 (plus any checked one) show, followed by a text "Show all (N)" that reveals the rest and moves focus to the first revealed chip.
- **File:** a control-bordered white box showing a secondary-button shape, the chosen file's name and a 4:3 preview of an image. The native input is transparent and covers the whole box, so a click or a dropped file anywhere reaches it; its own button (labelled in the browser's language) and the thumbnail iOS draws in it stay out of sight.
- **Filter chip** (`ChipType`): a yes/no filter applied as soon as it changes. A pill at least 44px high, white with a control-line border and semibold ink text (ink border on hover). On, it is the brand surface: navy fill and border, white text, behind an 8px sunshine mark at the livery angle, so the state has a shape as well as a color. Chips wrap in a row with 8px gaps. It is a native checkbox under the pill; the focus outline wraps the pill.
- **Switch** (`SwitchType`): for settings applied as soon as they change; a filter uses a chip, a form with a submit button a checkbox. A 40x24 pill track (subtle fill, 1.5px control-line edge, control-line knob) that turns action blue with a white knob when on, `role="switch"`. The label sits left, the switch right, and the whole row is the target (56px, 44px compact).
- **Compact size:** a form sets `control_size: compact` for 44px controls and switch rows (the filter panel); `mark_optional: false` drops the "(optional)" mark where every field is optional.
- **Search field in a form** (`SearchType`): the control with a leading 20px muted magnifier.
- **Select outside a form:** `<twig:Select>` draws the same control (a sort order, a dialog's reason).
- **Filter panel** (`FilterType`, rendered by `<twig:FilterPanel>`): the coaster name, then fieldsets under caption-semibold muted legends: Show (filter chips), Coaster and Location (selects with a visible label and an "Any" empty choice: their lists are too long for chips), then a Clear filters text button. The chips are the panel's only livery; the rest is the pure-guide form system. Filters apply on change. From `md` it is the page's side column (Layout); below `md` it is the filter bottom sheet (Navigation, Page header), opened by the funnel button, which carries a 14px sunshine dot with an ink edge while a filter is on.
- **Sections of a long form** (profile settings) are fieldsets under a lead-semibold legend, 40px apart.
- **Turnstile** (interaction-only) has no row and takes no space until a challenge appears.

**The One-Column Rule.** Form rows sit 24px apart in one column of at most 40rem. On desktop the extra width goes to context, never to stretched inputs: the review form's coaster card (photo, name, park) stays sticky beside the fields from `lg`. The submit is full width on phones and sized to its label, left-aligned, from `md`, with a secondary action as a text button beside it. Exception: inside the narrow (28rem) sign-in and register card the primary action stays full width at every size, matching the full-width Google button.

**The Known-Identity Rule.** Show what the server already knows instead of disabled inputs: the signed-in contact form shows the sender (avatar, name, "replies go to your account email") in place of the name and email fields.

### Navigation

- **Tab bar (below `lg`):** the floating brand bar, 64px high with a 16px radius and 4px inset, five equal columns. Each tab is a 24px outline icon over its label; idle tabs are `on-brand-muted`, hover white. The active tab is sunshine, icon and label, over a 24×3px slanted sunshine underline. Profile shows the signed-in rider's avatar instead of the icon, with the unread count as a sunshine badge (ink text, 2px brand ring, "99+" cap). Labels are always shown at rest; while scrolling down they drop to assistive technology only and the bar tightens to 48px, returning on scroll up or near the top.
- **Desktop header (from `lg`):** a 64px brand bar: full logo with white lettering · Home / Ranking / Map as semibold `on-brand-muted` links (hover white; active = white over a 4px slanted sunshine underline) · the search field · the avatar in a 2px white ring, leading to Profile (with the same badge, white 14% disc on hover and when current), or, signed out, Sign in as a quiet pill like the search field: white at 12% (20% on hover), a 20px user icon and a white semibold label. Action blue fails on brand.
- **Page header:** see Layout. On the band the title is the title step in 800 italic, the context line body semibold `on-brand-muted` (lead from `lg`), and the New pill a sunshine parallelogram at the livery angle with 800 italic ink caption text. The compact bar sets the title at 24px in the same face. Round buttons (`<twig:RoundButton>`) are 44px circles with a 20px icon (see Elevation); the back button is an arrow. Back goes to the previous page when arriving from the site, otherwise to the page's parent. The filters action (a funnel) opens the filter bottom sheet below `md`: a native modal `<dialog>` rising from the bottom edge (card radius on top, ink backdrop at 40%, at most 85% of the viewport), a fixed title row, the filters scrolling on their own and a full-width primary "Show results" button that closes it. From `md` the same panel is the sticky side column (Layout).

### Search

- **Field:** a 44px filled pill (subtle fill, no visible border) with a 20px muted magnifier; on focus it turns white with a 2px action outline. In the desktop header it sits on brand: white at 12% with `on-brand-muted` placeholder and magnifier, white text once filled; focused, the same white field with ink text and a 2px sunshine outline 2px off, on the navy. The desktop field shows a `/` key hint and answers `/` and Cmd/Ctrl+K. A clear button appears once there is text.
- **Dialog (below `lg`):** the Search tab opens a full-screen white dialog that fades in (180ms), field focused, with a Cancel text button. Its empty state lists recent searches (this device only, clearable) and an Advanced search row (Ranking and Map are already tabs): 56px rows, 24px muted icon, trailing chevron. The desktop field opens the same content as a floating panel on focus.
- **Result rows:** 56px, control radius, a 24px marker gutter so names align with the recent and shortcut rows. Name semibold ink, detail muted; matched text is bolder, never a highlighted chip. Hover is the subtle fill; the keyboard-selected row takes the selected fill, with no side stripe.

### Footer

The footer closes the page the way the band opens it: plain brand navy, edge to edge, and no livery stripes (one sunshine line at a time). Inside the content width: the full logo with white lettering, then two link groups, Community and About, with caption `on-brand-muted` headings and 44px white links that underline on hover. External links carry a small out-arrow. A caption `on-brand-muted` © line ends the page. From `lg` the footer also holds the language chip and the units toggle; below `lg` those live on the Profile page, and the footer's navy runs on under the floating tab bar. On the Profile page the same link groups sit on the canvas in ink.

### Preferences

- **Language:** a native select laid invisibly over its visible value, so the platform picker opens on tap. On the signed-out Profile page it is a 56px settings row (label, current value, up-down chevron) between hairlines, edge to edge (a signed-in member sets both in Settings); in the footer a 44px pill chip with a language icon, white at 12% with white text, 20% on hover.
- **Units:** a two-option toggle `km/h · m | mph · ft` on a subtle pill track (white at 12% in the footer, idle option `on-brand-muted`); each option is a 44px caption-semibold pill, the current one white with ink text and the raised shadow.

### Maintenance page

The 503 page nginx serves for every URL during deploys. One file, `assets/maintenance/maintenance.html`, copied into `public/` by `deploy.sh`: the woff2 fonts are inlined as base64 and the logo paths are copied from `<twig:Logo>`. Nothing else can load while it is up (only `/favicon.ico` passes), so it is one self-contained file with no links or buttons, and it mirrors the token values as hex literals in its own `:root` (commented back to `tokens.css`): update both together.

- **The closure sign:** logo (28px, 32px from 40rem) over a white card-radius panel, at most 30rem, in a 3px ink frame with the raised shadow, the one surface where borders are heavier than 1px. Display-step title ("Please remain seated"), lead-size muted-strong announcement.
- **Live status strip:** the sign's foot, sunshine with ink text behind a 3px ink rule, a Lucide loader-circle spinner in ink (a check once the site answers) and a tabular countdown to the next check (every 10s stretching to 60s, only while the tab is visible, plus on tab focus and on reconnect). It turns success green with white text when the site answers, then the page returns to the requested URL.
- **Posts and chain:** two ink posts carry the sign; a dashed sunshine-and-ink queue chain hangs between rings at mid-height. Static: the page reloads too soon after reopening for a motion to pay off.
- **Languages:** en, fr, es, de, from the URL's locale prefix, then the browser, then English; ride-operator voice.

### Ranking

The monthly ranking reads as a music chart: a cover, then the chart ledger. The page sits on white (`pageSurface`), edge to edge, with no card around the cover or the ledger and no divider between them. Anything added to #2/#3 goes in both `Ranking:Podium` and `Ranking:Row`.

- **Cover:** below a 36rem results width (container query on the results, not the viewport, since the filter column takes room) the cover is #1 alone: a 16:10 photo across the screen, loaded with high priority. From 36rem it is a 340px grid, #1 over two of three columns and both rows, #2 and #3 stacked beside it, small radius; a filtered list of one or two coasters keeps the same height with the cover alone or the two side by side. Each tile carries its text on an ink scrim at its foot: the name in 800 italic (26px, the title step on the wide #1, 24px on #2/#3) with the ridden mark, then a caption line with the park (and manufacturer on #1) and the movement, all white. The #1 numeral is the page's loud moment: sunshine, stroked 2.5px in ink (the `text-stroke-ink` utility), 108px (150px wide); #2/#3 take a 64px white numeral.
- **Chart ledger:** one CSS grid that declares the columns once; the headings and every row sit on it through subgrid, so nothing is sized twice. Rows are separated by hairlines, at least 72px: a rank column as wide as the widest rank shown, at least 48px, rows appended by "Load more" included (title step, 800 italic, `brand`, tabular) with the movement mark under the number, a 64×56 (8:7) thumbnail at the small radius (subtle fill and a muted coaster icon when there is no photo), then the name over park and manufacturer caption lines in muted. Below 36rem it opens with #2 and #3; below `sm` it runs to the screen edges and each row takes the page gutter as its padding. Hover is the subtle fill; focus is drawn inset.
- **Names:** semibold, never clamped in ledger rows; the ridden mark is glued to the last word so it never wraps alone. Park and manufacturer lines truncate with an ellipsis. Cover names cut at two lines.
- **Movement marks:** a 14px arrow and a caption-semibold number, unsigned (the arrow gives the direction), success green up, danger coral down; unchanged shows nothing. Best rank ever is the up count followed by a crown; a new entry is sparkles alone. Crown and sparkles are drawn like the rating stars (sunshine fill, amber edge, 16px). The meaning is in `sr-only` text and a `title`, never color alone. In a filtered list the big number is the position in that list and a muted-strong globe with the world rank replaces the movement. On a cover photo these marks are white.
- **Ridden marks:** a 16px success-green circle-check after the name; a ridden top-100 coaster that is gone (a "legend") gets a ghost instead, same green.
- **Wide ledger:** from a 48rem ledger width (the desktop page beside the filter column) the grid gains three columns and the manufacturer leaves the name stack for its own; Manufacturer, Country and "Duels won" (the score, one-decimal percent, right-aligned, 20px 800 italic tabular) follow, name / manufacturer / country sharing the free width 3 : 2 : 1.5 and the score a fixed 96px. Caption-semibold muted headings sit on the same tracks in a sticky white strip with a 2px ink rule at its foot, under the compact bar or the desktop header.
- **No medals:** no medal colors, no larger photos for the top 3 in the ledger (maintainer decision). The cover is the only place the top 3 are set apart.
- **Month line:** the band's one context line is the month, then either the New pill (the ranking's first week) or the countdown to the next ranking, never both. The countdown is on the band from `lg` only, in normal weight after a dot. New entries are reached through the "new this month" filter, not a link here (maintainer decision).
- **Explainer link:** always at the top of the page, never under the list. From `lg` it is on the band, level with the title at its right (white semibold, an arrow, underlined on hover). Below `lg` it is the row that opens the page, right under the band (at least 56px, a hairline at its foot, trailing chevron): the semibold label over the countdown in muted caption (no countdown while the New pill is shown).
- **Top-100 meter:** the signed-in rider's block, above the cover at every size (below `lg`, under the explainer row). No card: body-semibold title and muted caption left, `79 / 100` right in 800 italic (title step, the total in muted lead), then a 12px bar at the livery angle: sunshine fill ending on an ink edge, canvas track, 2px ink outline, ink notches at 25/50/75.
- **Pager:** "Load N more" is a full-width secondary `<twig:Button>` that is a real `?page=N` link, appended in place when enhanced; below it a caption total and a "Jump to rank" subtle pill chip over an invisible native select.

**Read layout (learn more).** Explanatory pages use the text width (40rem): section titles sit on the canvas (lead, semibold, body face), every section's content on a white card-radius card with a 1px line border and 16px padding, sections 40px apart.

- **Totals:** a two-by-two grid inside one card with hairline dividers, display-title numbers over caption labels and a muted-strong caption delta.
- **Principles:** a divided card list; each item a 40px subtle disc holding a 20px muted-strong Lucide icon, a bold one-line title, then the explanation in muted-strong. A personal figure sits in a selected-fill control-radius note.
- **Duel figure:** two coasters face to face (4:3 photos, display rank beside the name, a muted "vs" between), a 12px split pill bar with the winner's share in action blue and the other side in `control-line`, a 2px gap between; percentages sit outside the bar, the winner's semibold in action blue.
- **Trend chart:** a server-rendered inline SVG line (2px action stroke, hairline gridlines at max, half and zero, non-scaling strokes), Y ticks and years in muted caption; a crosshair, a dot and a white overlay-shadow tooltip follow the pointer and the arrow keys. The metric switch is the units-toggle pattern: one row of caption-semibold pills on a subtle track, the checked one white with the raised shadow, scrolling sideways if a locale does not fit.

### Home

Two pages on one URL, made of stable slots (`templates/Home/`). Both sit on white (`pageSurface`), edge to edge below `lg`. The loud moment is the sunshine sign band; the ribbon is the photo's one label.

- **Visitor, what the site knows:** band with the tagline → Hero photo → sign band (ratings, ratings today, riders) → reviews → top 3 → parks near you. From `lg` the photo takes two thirds in 16:9 with the figures as a plate on its lower edge, and the top 3 are three photo tiles beside it. No sign-up block.
- **Member, what the site knows about them:** message of the moment (in a park, below `lg`) → short band → Hero photo → sign band (coasters ridden, ridden this year, reviews) → top-100 meter → next action → parks near you → reviews. From `lg` the dashboard is one side panel beside the photo, at the same height.
- **Hero photo** (`<twig:HeroPhoto>`): one featured coaster, coaster news (announced, under construction, recently opened) or a member's best photo, never one of the ranking's top 3 (the visitor's page shows them beside it). 16:10 across a phone (16:9 for a member); from `sm` to `lg` edge to edge at a fixed 384px, flush under the band, so a tablet doesn't get a full-screen photo; from `lg` a column with the small radius. Always labelled by the ribbon (`<twig:Ribbon>`: sunshine, 2px ink edge, tilted −3°, 800 italic), the name in 800 italic and the park on an ink scrim at its foot, the photographer credited behind a 14px camera. No rank or rating on it.
- **Sign band** (`<twig:SignBand>`): up to three equal cells, a title-step 800 italic numeral over a caption-semibold label, 2px ink rules between the cells and above and below. Edge to edge below `lg`. Its `plate` variant is the same band and, from `lg`, a plate with a 2px ink border all round, the overlay shadow and wider cells, set 28px below the photo's lower right edge; the photo's caption stays above it. In the member's side panel it is the same sunshine band, flush to the panel's edges. In a band narrower than 360px (container query) the numerals drop to 24px so three six-digit figures fit.
- **Member figures:** a figure shows from its first unit, never a zero cell. An account without a coaster gets the starter block instead (800 italic title, one sentence, a primary button to search). The top-100 meter shows from the first top-100 coaster, as on the profile.
- **Member side panel** (from `lg`): a card (card radius, line border, raised shadow) in four parts: its title ("Your dashboard", 24px 800 italic), the sunshine sign band (the page's loud moment at every size), the top-100 meter, the next action on a canvas foot. It doesn't repeat the member's avatar, name or a Profile link: the header's avatar already does.
- **Next action:** one suggestion and one button: a 64×56 thumbnail (or the action's icon on the subtle fill), a semibold line over a muted caption, then a secondary button, full width below `lg`.
- **Message of the moment:** one line at the very top of a member's Home, on the brand surface, at least 56px: a 20px sunshine icon, the message in semibold over a caption detail in `on-brand-muted`, a trailing chevron, and a white hairline at 14% between it and the band. Kept for what is immediate, and for phones and tablets only: "Are you at …?" within 2km of a park. The ranking's publication is not announced here (a notification already does).
- **Review item** (`<twig:ReviewItem>`): no card, items separated by hairlines (side by side from `lg`). A 56×49 thumbnail, the coaster in semibold over its park in muted caption, the rating as one 800 italic numeral and one star (`<twig:RatingNumeral>`), four lines of text that open in place on a tap and close on the next one, with a muted 20px chevron under it for the keyboard and assistive technology (shown only when the text is cut, turned over when open; no "Read more" label), pros and cons as pills (`<twig:ProConTag>`: pros filled success green, cons outlined in danger), then a 24px avatar, the author in caption semibold and the date. The list lays out on its own width (container query): two stacked, two side by side from 42rem, three from 56rem; side by side they share their four rows through subgrid, so texts, tags and authors line up.
- **Top 3** (visitor): the Ranking's ledger rows below `lg`; from `lg` three photo tiles in the cover's language (a 64px white numeral, the name in 800 italic and the park on a left-to-right ink scrim). No sunshine numeral: the loud moment is elsewhere.
- **Parks near you:** the position is asked from a secondary button, never on load; once the browser already allows it the parks load on their own. Denied or unavailable: one sentence and a button to the map, no button asking again. While the parks load, subtle placeholder tiles hold the row's height. The section carries its state (`data-state`), and what shows follows from it. Park tiles (`<twig:ParkTile>`) are the one bordered tile: small radius, line border, a 16:9 photo (the main image of the park's best-ranked coaster, or the park icon on the subtle fill), the park in semibold, the distance in muted caption; a member also sees "Ridden 9 / 14" in 800 italic over a meter, and a success check after the name of a finished park. A row that scrolls sideways with snap below `lg`, four across from `lg`.
- **Section titles** (`<twig:SectionHeader>`): 24px 800 italic (30px from `lg`), one muted caption under it, one caption-semibold action-blue text link at its right with a 44px target.
- **Meter** (`<twig:Meter>`): the livery's progress bar, shared with the Ranking's top-100 meter.
- **Thumbnail** (`<twig:Thumb>`): the 8:7 coaster thumbnail of list rows, shared by the Ranking's ledger, the review item and the next action.
- The message of the moment opens in 180ms (its row grows from nothing) instead of pushing the page down at once.

### Profile

One template for a member's own profile and everyone else's (`templates/Profile/show.html.twig`); budget 2/3. `/profile` is the member's public profile plus their own layer. Sits on white (`pageSurface`), edge to edge below `lg`. The loud moment is the sunshine sign band; the top-100 meter under it is the page's signature.

- **Avatar ring:** neutral, 2px white with a navy gap, in the band and in the desktop header. Colored rings are kept for members' distinctions (supporters, milestones); don't spend one on decoration. The signed-out page shows an empty avatar instead, a dashed sunshine circle around a user icon.
- **Order:** band → sign band (coasters, parks, countries, the current year from its first dated ride) → top-100 meter → favourites → access rows → records → ratings → rides per year → contributions. From `lg`: the meter and favourites beside the access rows, the records as columns under their photos, then ratings beside rides per year and contributions.
- **Every figure is a door:** the sign band's cells link (coasters to the ratings, parks and countries to the member's map) and a record links to its coaster.
- **Blocks show from their first unit, never empty:** records from 10 coasters, ratings from 20, the meter from 1 top-100 coaster, rides per year from one dated ride, a contribution from 1. The thresholds live in `ProfileStatsBuilder`. An empty account shows its owner the starter block and a visitor one muted sentence.
- **Top-100 meter:** a 24px 800 italic title over a muted caption, the count as a 44px numeral beside ` / 100`, then `<twig:Meter>` with notches at 25/50/75.
- **Favourites** (`<twig:Profile:Favourite>`): the first three of the main Top as photo tiles in the Ranking cover's language (white numeral, name in 800 italic and park on an ink scrim), 4:5 on a phone, 4:3 from `sm`. Without a Top, only the owner sees an invitation to create one.
- **Access rows** (`<twig:Profile:Row>`): 56px rows between hairlines, a 24px icon, a semibold label, a muted count, a chevron. Notifications (owner only) carry the unread count on a sunshine pill with an ink edge.
- **Records** (`<twig:Profile:Record>`): the value as a title-step 800 italic numeral in brand navy with a muted unit, then what it measures, the coaster and its park. Rows below `lg`; from `lg` columns under a 4:3 photo.
- **Ratings** (`<twig:Profile:Ratings>`): the distribution as ten bars on the rating scale over a 2px ink baseline, the average as `<twig:RatingNumeral>`, then the three pros and cons the member picks most, each with its count.
- **Rides per year** (`<twig:Profile:Years>`): navy bars with their count above; no bar for a year at zero at the end; one muted line says how many rides have no date.
- **Contributions:** up to four figures (reviews, votes on reviews, photos, likes) as 800 italic numerals over muted captions, two per row between hairlines.
- **Signed out** (`templates/Profile/guest.html.twig`): the same page, empty: the band with the empty avatar, the sign band with dashes in place of numbers, then Sign in and Create an account, three reasons, language and units, and two links.
- **Settings** (budget 0/3): one form in three fieldsets (Profile, Preferences, Notifications) and one Save; then Account (the email as known identity, Sign out), and deleting the account in its own danger card (danger border on danger-bg, a circle-alert heading, what happens, the danger button), apart from Sign out.

### Iconography

- **One set: [Lucide](https://lucide.dev)** (`lucide:` in `ux_icon`), on its 24px grid with 1.75px rounded strokes, set once in `config/packages/ux_icons.yaml`. Icons are 24px in navigation and search rows and 20px in dense rows, drawn in the text color.
- **Filled state:** a selected or rated state fills the same outline icon (`fill: 'currentColor'`, or `fill-current` / `fill: currentColor` in CSS), e.g. a voted thumb or a liked heart. There is no separate filled set.
- **One icon per meaning:** coaster `roller-coaster`, park `ferris-wheel`, review `message-square-text`, photo `image` (upload: `camera`), rating `star`, Top list `clipboard-list`, loading `loader-circle` spinning, ridden `circle-check`, gone (a ridden legend) `ghost`, new `sparkles`, best rank ever `crown`, world rank `globe`, duel `swords`. Reuse these before picking another.
- **Exceptions:** brand logos (`fe:google` on sign-in) and the drawn rating stars (half state, see Components). `npm run check:icon-sets` fails CI on any other locked set.
- **Markup built in JS** takes its icons from the server-rendered `<template id="js-icons">` (`js/icons.js`), never emoji or glyphs.

Feedback motion uses 120ms, entrances 180ms and `cubic-bezier(.16, 1, .3, 1)`; the compact bar's fade-in and the search dialog use 180ms. Honor reduced motion by removing transitions, animations and smooth scrolling. Label unfamiliar actions and hide decorative icons from assistive technology. Prefer 16:9 discovery images, 4:3 gallery thumbnails and original-ratio photo viewers. Keep photography recognizable and alt text meaningful. Show actual member attribution when available; the supplied site photograph has no recorded photographer credit, so the guide says so explicitly.

For Symfony/Twig, Stimulus, Tailwind v4 and Vite, the tokens live in `assets/styles/tokens.css`: primitives and roles on `:root`, exposed as utilities through `@theme inline` (`bg-surface text-ink rounded-card`, `bg-action text-on-action hover:bg-action-hover`, `text-label`). Tailwind's default color palette is switched off, so only token colors (plus white) exist as utilities. Fonts are self-hosted woff2 subsets in `assets/fonts/`, bundled by Vite. Bind behavior to native controls.

**Tailwind practice (the Ranking page is the reference).**

- Utilities in the markup, composed in Twig Components; variants through `html_cva`, class overrides through `tailwind_merge`. No `@apply`, no page stylesheet.
- A value used twice is a token in `@theme` (`h-band-page`, `pb-tab-bar`, `max-w-text`), not a repeated arbitrary value. A one-off `calc()` over tokens is fine in brackets.
- A livery motif that isn't a single declaration is an `@utility` in `tokens.css` (`livery-type` for Barlow 800 italic, `livery-stripes`, `text-stroke-ink`), so it takes variants like any other. The 800 italic is never spelled out as `font-display font-extrabold italic`.
- A box as wide as its content (a plate set on a photo) can't be a container: it keeps viewport variants.
- Layout responds to its container, not the viewport, wherever a side column can take room (`@container`, `@xl/results:`, `@3xl/ledger:`). Columns shared by several rows are one grid with `grid-cols-subgrid`, never widths repeated by hand.
- State comes from the platform: `has-checked:`, `peer-checked:`, `aria-[current=page]:`, `in-data-brand:`, `open:`/`starting:` on `<dialog>`. JavaScript only where there is behavior (fetching, focus).
- Shared buttons, round buttons, avatars and the logo are their components; a class string is never copied between templates.

## Do's and Don'ts

- Do preserve the Captain Coaster name and original logo exactly, including colors, proportions and complete artwork; the head mark alone is the only permitted reduction, for compact UI.
- Do test English, French, Spanish and German, longer labels, keyboard use and 200% zoom.
- Do use semantic CSS roles and real application data when implementing screens.
- Do credit existing photography and retain its provenance.
- Do put a new top-level destination in the footer and on Profile, not in a sixth tab or a menu; the tab bar holds exactly five.
- Do put a page's context (park, author, country) on its own line under the title.
- Do take livery motifs from their single component or token; never redraw stripes inline.
- Do keep the brand surface plain navy: no pattern, texture or gradient on it.
- Don’t recolor, redraw or crop the logo other than to the head mark or switching the lettering between logo ink and white.
- Don’t use sunshine or pale decorative borders as unverified text or essential control boundaries.
- Don’t present illustrative names, ranks, scores or the sample 1–5 rating scale as shipped product facts.
- Don’t put reading content on the brand surface: it frames the page (band, bars), the light canvas carries the content.
- Don’t spend more of the clean ↔ fun budget than the page type allows, and don’t stack two loud moments on one screen.
- Don’t let color alone tell two states apart.
- Don’t hide the tab bar on scroll or raise its Search tab into a create-style button.
- Don’t infer new ride-entry fields, milestone ordering or monetization from this document.
