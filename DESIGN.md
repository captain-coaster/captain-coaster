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
  warning: "oklch(0.4643523 0.0941316 74.17692)"
  warning-bg: "oklch(0.9661014 0.0400057 88.19611)"
  danger: "oklch(0.5194091 0.1411480 27.82671)"
  danger-bg: "oklch(0.9658552 0.0171604 35.34490)"
  focus: "oklch(0.4762678 0.1461232 259.22022)"
  logo-ink: "oklch(0.3165834 0.0193707 229.74411)"
  rating-fill: "oklch(0.8720509 0.1368842 84.26883)"
  rating-edge: "oklch(0.4643523 0.0941316 74.17692)"
  rating-empty: "oklch(0.4987397 0.0362213 248.55648)"
  rating-scale-low: "oklch(0.45 0.12 28)"
  rating-scale-high: "oklch(0.645 0.15 150)"
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
  tab-bar:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.pill}"
    height: "64px"
    padding: "4px"
  nav-tab:
    textColor: "{colors.muted-strong}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
  nav-tab-active:
    backgroundColor: "{colors.selected}"
    textColor: "{colors.action}"
  nav-badge:
    backgroundColor: "{colors.highlight}"
    textColor: "{colors.on-highlight}"
    rounded: "{rounded.pill}"
    height: "20px"
  header-link:
    textColor: "{colors.muted-strong}"
    height: "64px"
    padding: "0 12px"
  header-link-active:
    textColor: "{colors.action}"
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
  edition-pill:
    backgroundColor: "{colors.highlight}"
    textColor: "{colors.on-highlight}"
    typography: "{typography.caption}"
    rounded: "{rounded.pill}"
---
# Design System: Captain Coaster

> **Status: partly shipped.** Colors, fonts and tokens are live since the reskin (#413); the page shell and app navigation are shipped as Twig Components (`templates/components/Nav/`, `Page/`). The livery layer (brand role, 800 italic, motifs, clean ↔ fun budget) is defined here and in `tokens.css` (#469) and applied surface by surface, starting with the navigation (#470) and the ranking (#471). Page content components still use the legacy `.cc-*` files and migrate one surface at a time (plan: #375). Every new or reworked component follows this document. See "Redesign: target vs. current" in `AGENTS.md` and `docs/agents/design-workflow.md`.

## Overview

**Creative North Star: "A precise guide, dressed like a park"**

Captain Coaster has two faces and the design holds both. The **guide** is serious: the ranking has to be trusted, a coaster page read in seconds, a form filled without friction. The **park** is a celebration: it is why riders come back. They are not mixed at random: the guide carries the content (white, ink, Source Sans, edge-to-edge lists, exact data), the park carries the dressing (the navy band with the logo's stars, the sunshine livery stripes, big condensed numerals). This dressing is **the livery** (#469). Reference board with mockups: [`docs/design/livery.html`](docs/design/livery.html) (illustrative data).

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
| Home, Ranking | 3/3 | Brand band with star field and stripes, ribbon, sunshine sign band, the big #1 |
| Profile, milestones | 2/3 | Brand band with avatar, sunshine sign band, top-100 meter |
| Coaster, park | 2/3 | The photo is the header (stripes at its foot), hero numerals; the rest is guide |
| Search, lists, map | 1/3 | Compact brand bar, hero numerals |
| Forms, settings, legal, explainers | 0/3 | Pure guide: white, ink, action blue. Only the tab bar keeps the brand |

### The livery: brand motifs

Each motif comes from a real object of the park world; that is what keeps the site from looking like any other app. Each one is a single component or utility, never redrawn per page.

- **Livery stripes** (a coaster train's paint): two sunshine stripes rising left to right at the foot of a brand band or hero photo. Drawn as an SVG (`--livery-stripes`), never as a rotated gradient (it aliases into steps).
- **Star field** (the Captain's mask): the logo's stars at 15% on the brand surface (`--star-field`). Never on white.
- **Hero numerals** (the stats board at a ride's entrance): Barlow Condensed 800 italic, tabular.
- **Sunshine sign band** (ride signage): sunshine, ink rules (2–2.5px), at most three figures, edge to edge.
- **Ribbon**: the one label placed on a photo, slightly tilted, sunshine with an ink edge.
- **The angle**: one slant, −10° (`-skew-x-10`), repeated: the New pill, the active-tab underline, the meter. The livery's speed turned into shape.
- **No divider motif.** Sections are separated by space and hairlines; the livery stripes already carry the signature. (The queue chain stays on the maintenance page only.)
- **One sunshine line at a time.** The stripes belong to bands and hero photos only. Bars carry no sunshine rule: the desktop header meets the band with a 1px white hairline at 14%, and the compact bar ends the same way.

Home's band carries the logo and one short tagline, written to sound native in each locale rather than translated word for word, on one line at 360px (wording and translations are chosen with the Home redesign, #417). Brand band heights are fixed so the band never eats the screen: Home `h-band-home` (136px), pages `h-band-page` (120px), profile `h-band-profile` (148px), then the 56px compact bar once the band scrolls away.

**Key Characteristics:**

- Condensed display typography, 800 italic for titles and numbers, readable multilingual body text.
- Navy brand surfaces with sunshine livery framing clean white content.
- Mobile-first layouts and visible, native interaction states.
- One floating pill for navigation on phones and tablets, a classic top bar on desktop.

## Colors

Fresh blue directs action; sunshine brings warmth to selected highlights. Ink carries primary text; slate carries secondary text. Cool canvas and white surfaces keep rider content legible. Green, amber and coral are reserved for success, warning and error, paired with their pale backgrounds and explicit labels or icons.

**The Semantic Role Rule.** Consume semantic roles in components; keep primitive colors inside the system. Logo colors remain original; the stronger action blue is an interface extension.

**Brand (navy)** is the livery's surface: the band at the top of Home, Ranking, profile and park pages, the tab bar, the desktop header, the active filter. It is the logo blue pulled darker, not a new hue. On it: white 13.6:1, sunshine 9.2:1, `on-brand-muted` 8.7:1; action blue fails (2.0:1), so links on brand are white or sunshine and the focus ring is sunshine.

**Proportions.** Roughly: white 58%, canvas 12%, ink 10%, brand 12%, sunshine 6%, action blue 2%. Lots of white, brand at the top and bottom, sunshine in small, strong doses.

Two support roles carry the navigation's quiet states. **Subtle** (pale mist) fills hover rows, the search field, preference tracks and placeholders. **Muted-strong** (deep slate) is secondary text that needs more weight: idle tabs and header links, the page context line, the current language value. Sunshine is a surface with ink on it or around it: the unread badge, the New pill, the active tab, the sunshine sign band, the ribbon, the big #1, the top-100 meter. Never text on white. Browser-drawn surfaces take the palette: text selection uses the selected fill with ink text, the caret and native accents use action blue.

**Rating stars** (look provisional, #423). Stars fill with sunshine and carry an amber edge: sunshine alone is 1.5:1 on white, the edge (7.1:1) keeps the shape legible. Empty stars of the rating input use the control boundary color (6.0:1).

**The Rating Scale.** Ordered rating data (the 0.5★–5★ distribution) runs from deep red through gold to a vivid green, interpolated with `color-mix(in oklch)` between `rating-scale-low` and `rating-scale-high`. Lightness rises toward "good", so the order stays readable with color-vision deficiencies; every step is ≥3:1 on white. Segments sit on a hairline surface gap with their labels outside the bar, never on it.

**The Roles-Not-Palettes Rule.** Data needs (ratings, statuses, counts) are semantic roles over the existing hues. When a role needs a missing lightness step, extend that hue's ramp (`green-500`, `coral-700`); never add a standalone hue or a parallel palette. Status and map-marker colors are not settled yet and keep their provisional aliases.

The frontmatter mirrors the canonical OKLCH values in `assets/styles/tokens.css` (the implementation since the reskin, #413; `.impeccable/captain-coaster-tokens.css` is the original proposal). `.impeccable/contrast-report.json` records WCAG sRGB luminance measurements: primary text 14.53:1, secondary text on white 6.01:1, default action 6.77:1, hover action 9.25:1, selected text 5.96:1 and ink on sunshine 9.79:1. Status pairings exceed 5.3:1. These are measured pairings, not a full accessibility certification. Keep normal text ≥4.5:1 and essential boundaries, focus indicators and large text ≥3:1.

## Typography

Barlow Condensed gives short headings the wordmark’s condensed energy: **800 italic** for page titles, section titles and every hero numeral (ranks, ratings, counters, stats); 600 upright stays where legacy pages still use it. Italic exists only in Barlow and only for short text, never a sentence longer than a line. Faces: 600, 800 and 800 italic, latin subsets. Source Sans 3 carries body copy, controls and all four locales. Both load locally from `assets/fonts/` with `font-display: swap` and metric-matched fallbacks; retain their font licenses. Use the supplied display/title clamps, body/lead/caption/label scale and system monospace for code. Keep prose near 70 characters per line and inputs at least 16px.

The page title (h1) is the title step in the display face. Its context line (park, author, country) is lead-size semibold muted-strong on its own line beneath; a further muted line (former names) is caption. Group headings in navigation (Recent, Community, About, Language) are caption semibold muted in the body face, never the display face.

**The Label Step Rule.** The label step (12px, 1.25) exists for compact navigation labels only, the tab bar: it is the largest size that keeps the longest translation on one line at 360px. Tab labels are never truncated; anything else uses caption or larger.

The guide’s own editorial headings use larger presentation sizes; the exported scale above is the reusable application foundation. Avoid fixed-height text containers and forced uppercase on long translated labels. Sentence case everywhere; no all-caps labels. UI labels (tabs, buttons) stay Source Sans 600.

## Layout

Start with a single column, flexible widths and wrapping labels. Use the 4px spacing rhythm, fluid 16–48px gutters and the 75rem content maximum as the reusable container default. Add columns when content fits. Sign-in forms narrow the body to a 28rem column and align the page title with it.

**The Thumb Rule.** Below `lg` (64rem), navigation is the floating bottom pill: five equal destinations, Home · Ranking · Search · Map · Profile, with Search a plain tab in the middle. The pill floats 12px above the bottom edge plus the safe area, at most 28rem wide, and never hides; the page reserves room for it at its foot. From `lg`, a classic 64px top bar replaces it. Destinations without a tab (reviews, Tops, riders, contact, blog, privacy) live in the footer and on the Profile page, never in a hamburger.

**The Page-Owns-Its-Top Rule.** Pages open with a large title on the canvas, no header band, and no row spent on buttons alone: the back button (detail pages only, below `lg`) sits left of the title and the page's actions right of it, on the title's first line. Below `lg`, once that row scrolls away, a translucent 56px bar fades in at the top with the same back button, the title and the actions; it takes no room before that. On Home the title is kept for assistive technology only and the full logo stands on the canvas instead.

Sticky elements stack against these heights: side panels stick below the 56px compact bar (below `lg`) or the 64px desktop header plus its 1px line. Use at least 44px interaction targets, preferably 48px. Preserve logical DOM order as layouts change. Validate at narrow phone widths (360px) and 200% zoom; allow tables and code to scroll within their own containers.

## Elevation & Depth

Use tonal surfaces and restrained borders first. Raised elements use `--shadow-raised` (0 4px 16px oklch(0.28 0.04 245 / .09)); overlays use `--shadow-overlay` (0 12px 32px oklch(0.28 0.04 245 / .16)). Depth communicates layering, not decoration.

**Livery bars (decided, applied in #470).** The tab bar and the desktop header become solid brand navy; a touch of translucency may come later, but they stay navy. The tab bar carries a 1px white ring at 12% so it stays distinct over the brand footer; the desktop header meets the band with the 1px hairline. Until #470 ships, the glass rule below is what is live.

**The Floating Glass Rule.** Translucency is reserved for navigation that floats over scrolling content: white at 75–85% with a strong backdrop blur. The tab bar (75%, 1px white 60% edge, overlay shadow), the round page buttons (75%, raised shadow), the compact page bar (80%, a 1px line shadow at its foot) and the desktop header (85%, 1px line border). Content cards, panels and dialogs stay opaque.

## Shapes

Small accents use the small radius; controls use the control radius and containers the card radius. Pill shapes belong to compact tags and to navigation-scale controls: the tab bar and its active tab, the search field, the language chip, the units toggle and badges. Floating page buttons are 44px circles. Borders are 1px: pale `border` for decorative separation, stronger `control-border` where a boundary identifies an input. Ink strokes of 2–2.5px exist only around sunshine (sign band, ribbon, big #1, meter), like a painted sign. Small radii: 4px on photos and thumbnails, the control radius on controls, pills for navigation and chips; large rounded content cards give way to edge-to-edge sections. One slant: −10°.

Keep original logo artwork intact. It has one source, `<twig:Logo>` (inline SVG): the lettering is logo ink (#28343A) on light surfaces and white on dark ones (`class="text-white"`), the mark never changes. The full logo appears at 28px high (193px wide): in the desktop header, on Home below `lg`, and in the footer; other pages carry no logo on phones. In compact UI the head mark (the star mask) may stand alone; it keeps its original colors and reads on light surfaces too. The favicon and app icons are the head mark: bare on a transparent ground in the browser tab (it reads on light and dark tabs), centered on ink-900 where the platform needs an opaque tile (iOS home screen, Android maskable), since one tile has to suit light and dark home screens. Proposed minimum logo widths: 180px horizontal, 238px stacked, with at least 12px clear space in compact UI and 24px in standalone placements.

## Dark theme

Planned, not active. The dark theme swaps roles, it doesn't invert colors: the brand navy (lifted one step), sunshine, ink on sunshine (`on-highlight`), the stars, the stripes and the photos keep their values; the canvas, surfaces, text, lines, action blue and state colors change. The band stays distinct from the deeper canvas through its saturation, star field and stripes. Values live in `tokens.css` under `:root[data-theme="dark"]` with their measured contrasts.

- Text on sunshine always uses `on-highlight`, never `fg`: `fg` turns light in the dark theme.
- On the dark theme, action blue becomes a light blue with ink text on filled buttons (`on-action`).
- Success and danger are both light in the dark theme: the shape rule (Accessibility) matters even more there.
- Activation waits until the main pages no longer use legacy `.cc-*` colors (many are hardcoded): system preference by default, a switch on Profile.

## Accessibility

- **Color never carries meaning alone.** Every pair of states also differs by shape or text: filled vs outlined, + vs −, arrow up vs down, an icon. Movement marks show the sign (+2, −1) next to the arrow; pros and cons tags carry + and − icons, pros filled, cons outlined (#472).
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

> The navigation, page header and ranking below shipped before the livery; #470 and #471 re-dress them (brand band and bar, sunshine active states, 800 italic numerals, the big #1). Until then this section describes what is live.

Buttons are clear and compact: primary action blue with white text, deeper blue on hover; secondary white with ink text and a visible boundary. The built samples use 48px minimum height, 10px 18px padding and semibold body type. Controls retain visible keyboard focus: a 3px focus-blue outline with a 3px offset on links, buttons, selects and summaries, keyboard only. Pill tabs and full-width settings rows draw the same outline inset (−3px) so it isn't clipped. On dark surfaces use a sunshine focus indicator; code panes use an inset outline to avoid clipping. Disabled controls are visibly muted and remain semantically disabled.

Use native links, buttons, disclosure and radio controls. Selection needs a shape, check or label as well as color. Put validation beside its field and announce saved/copied outcomes through polite live regions. “Ridden, unrated” is a valid neutral state. Read-only star ratings use the same two-path star as the rating input (amber outline painted over the sunshine fill, half state clipped), always five stars, announced as “3.5/5”. The rating specimen is illustrative and saves no production data; richer ride tracking remains undecided in `PRODUCT.md`.

### Navigation

- **Tab bar (below `lg`):** the floating glass pill, 64px high with 4px inset, five equal columns. Each tab is a 24px outline icon over its label; idle tabs are muted-strong, hover ink. The active tab is action blue on a selected pill. Profile shows the signed-in rider's avatar instead of the icon, with the unread count as a sunshine badge (ink text, 2px white ring, "99+" cap). Labels are always shown at rest; while scrolling down they drop to assistive technology only and the bar tightens to 48px, returning on scroll up or near the top.
- **Desktop header (from `lg`):** a 64px light glass bar with a 1px line at its foot: full logo · Home / Ranking / Map as semibold muted-strong links (active = action blue with a 3px action underline, slightly rounded at its top) · a wide filled search field · the avatar leading to Profile (with the same badge), or a primary Sign in button when signed out.
- **Page header:** see Layout. Round buttons are 44px glass circles with a 20px ink icon, solid white on hover; the back button is an arrow. Back goes to the previous page when arriving from the site, otherwise to the page's parent. The filters action (a funnel) opens the filter bottom sheet below `md`: a native modal `<dialog>` rising from the bottom edge (card radius on top, ink backdrop at 40%, at most 85% of the viewport), a fixed title row, the filters scrolling on their own and a full-width primary "Show results" button that closes it. From `md` the same panel is the sticky side column.

### Search

- **Field:** a 44px filled pill (subtle fill, no visible border) with a 20px muted magnifier; on focus it turns white with a 2px action outline. The desktop field shows a `/` key hint and answers `/` and Cmd/Ctrl+K. A clear button appears once there is text.
- **Dialog (below `lg`):** the Search tab opens a full-screen white dialog that fades in (180ms), field focused, with a Cancel text button. Its empty state lists recent searches (this device only, clearable) and an Advanced search row (Ranking and Map are already tabs): 56px rows, 24px muted icon, trailing chevron. The desktop field opens the same content as a floating panel on focus.
- **Result rows:** 56px, control radius, a 24px marker gutter so names align with the recent and shortcut rows. Name semibold ink, detail muted; matched text is bolder, never a highlighted chip. Hover is the subtle fill; the keyboard-selected row takes the selected fill, with no side stripe.

### Footer

> Target (decided, applied in #470): the footer turns brand navy with the star field and no livery stripes (one sunshine line at a time). Links in `on-brand`, group headings and the © line in `on-brand-muted`, the logo lettering white; the language chip and units toggle sit on the brand surface. It closes the page the way the band opens it. The paragraph below is what is live.

The footer sits on the canvas like the page it closes, inside the content width with a 1px line rule above it: the full logo, then two link groups, Community and About, with caption headings and 44px ink links that turn action blue and underline on hover. External links carry a small out-arrow. A muted caption © line ends the page. From `lg` the footer also holds the language chip and the units toggle; below `lg` those live on the Profile page.

### Preferences

- **Language:** a native select laid invisibly over its visible value, so the platform picker opens on tap. On Profile it is a 56px settings row (label, current value, up-down chevron) in a white card with subtle dividers; in the footer a 44px subtle pill chip with a language icon, selected fill on hover.
- **Units:** a two-option toggle `km/h · m | mph · ft` on a subtle pill track; each option is a 44px caption-semibold pill, the current one white with ink text and the raised shadow.

### Maintenance page

The 503 page nginx serves for every URL during deploys. One file, `assets/maintenance/maintenance.html`, copied into `public/` by `deploy.sh`: the woff2 fonts are inlined as base64 and the logo paths are copied from `<twig:Logo>`. Nothing else can load while it is up (only `/favicon.ico` passes), so it is one self-contained file with no links or buttons, and it mirrors the token values as hex literals in its own `:root` (commented back to `tokens.css`): update both together.

- **The closure sign:** logo (28px, 32px from 40rem) over a white card-radius panel, at most 30rem, in a 3px ink frame with the raised shadow, the one surface where borders are heavier than 1px. Display-step title ("Please remain seated"), lead-size muted-strong announcement.
- **Live status strip:** the sign's foot, sunshine with ink text behind a 3px ink rule, a Lucide loader-circle spinner in ink (a check once the site answers) and a tabular countdown to the next check (every 10s stretching to 60s, only while the tab is visible, plus on tab focus and on reconnect). It turns success green with white text when the site answers, then the page returns to the requested URL.
- **Posts and chain:** two ink posts carry the sign; a dashed sunshine-and-ink queue chain hangs between rings at mid-height. Static: the page reloads too soon after reopening for a motion to pay off.
- **Languages:** en, fr, es, de, from the URL's locale prefix, then the browser, then English; ride-operator voice.

### Ranking

The monthly ranking reads as a music chart: a three-coaster cover, then the chart ledger. Anything added to #1–#3 goes in both `Ranking:Podium` and `Ranking:Row`.

- **Chart ledger:** one white card with hairline `line` dividers between rows. Below `sm` it runs edge to edge like a native list (no side border or radius) and each row takes the page gutter as its padding; from `sm` it is a card-radius card, rows padded 12px. Rows are at least 72px: a fixed rank column (Barlow Condensed title step, tabular, 56px wide, 64px once a page holds 4-digit ranks) with the movement mark under the number, a 64×56 (8:7) thumbnail at the small radius (subtle fill and a muted coaster icon when there is no photo), then the name over park and manufacturer caption lines in muted. Hover is the subtle fill; focus is drawn inset.
- **Names:** semibold, never clamped in ledger rows; the ridden mark is glued to the last word so it never wraps alone. Park and manufacturer lines truncate with an ellipsis. Narrow podium cards alone cut the name at two lines.
- **Movement marks:** up and down counts are a 14px arrow and a caption-semibold number, success green up, danger coral down; unchanged shows nothing. Best rank ever is the up count followed by a crown; a new entry is sparkles alone. Crown and sparkles are drawn like the rating stars (sunshine fill, amber edge, 16px). The meaning is in `sr-only` text and a `title`, never color alone. In a filtered list the big number is the position in that list and a muted-strong globe with the world rank replaces the movement.
- **Ridden marks:** a 16px success-green circle-check after the name; a ridden top-100 coaster that is gone (a "legend") gets a ghost instead, same green.
- **Cover (podium):** on phones #1 spans both columns with a 16:9 photo loaded with high priority, #2/#3 share the next row as 4:3 cards; once the podium is 36rem wide #1 takes two of three columns and both rows, its photo filling the height left by its text. Position numerals step above the ledger's: #2/#3 halfway between title and display, #1 at 1.5× display. It lays out on its own width (container query), not the viewport.
- **Wide ledger:** from a 56rem results width (container query on the results, not the viewport, since the filter column takes room) the cover steps aside and #1–#3 are plain ledger rows. The manufacturer leaves the name stack for its own column; Manufacturer, Country and "Duels won" (the score, one-decimal percent, right-aligned, semibold tabular) follow, name / manufacturer / country sharing the free width 3 : 2 : 1.5 and the score a fixed 96px. Caption-semibold muted headings sit on the same tracks in a sticky strip under the compact bar or the desktop header.
- **No medals:** no medal colors, no larger photos for the top 3 in the ledger (maintainer decision). The cover is the only place the top 3 are set apart.
- **Month line:** the page context line is the month (lead, semibold, muted-strong), followed by the New pill (sunshine, ink caption text) during the ranking's first week. New entries are reached through the "new this month" filter, not a link here (maintainer decision). A caption muted line beneath holds the countdown to the next ranking and the explainer link.
- **Top-100 meter:** a white card: body-semibold title and muted caption left, display `79 / 100` right, an 8px pill bar in action blue on subtle with surface notches at 25/50/75, and a ghost line counting legends.
- **Pager:** "Load N more" is a full-width secondary button that is a real `?page=N` link, appended in place when enhanced; below it a caption total and a "Jump to rank" subtle pill chip over an invisible native select.

**Read layout (learn more).** Explanatory pages narrow to 42rem: section titles sit on the canvas (lead, semibold, body face), every section's content on a white card-radius card with a 1px line border and 16px padding, sections 40px apart.

- **Totals:** a two-by-two grid inside one card with hairline dividers, display-title numbers over caption labels and a muted-strong caption delta.
- **Principles:** a divided card list; each item a 40px subtle disc holding a 20px muted-strong Lucide icon, a bold one-line title, then the explanation in muted-strong. A personal figure sits in a selected-fill control-radius note.
- **Duel figure:** two coasters face to face (4:3 photos, display rank beside the name, a muted "vs" between), a 12px split pill bar with the winner's share in action blue and the other side in `control-line`, a 2px gap between; percentages sit outside the bar, the winner's semibold in action blue.
- **Trend chart:** a server-rendered inline SVG line (2px action stroke, hairline gridlines at max, half and zero, non-scaling strokes), Y ticks and years in muted caption; a crosshair, a dot and a white overlay-shadow tooltip follow the pointer and the arrow keys. The metric switch is the units-toggle pattern: one row of caption-semibold pills on a subtle track, the checked one white with the raised shadow, scrolling sideways if a locale does not fit.

### Iconography

- **One set: [Lucide](https://lucide.dev)** (`lucide:` in `ux_icon`), on its 24px grid with 1.75px rounded strokes, set once in `config/packages/ux_icons.yaml`. Icons are 24px in navigation and search rows and 20px in dense rows, drawn in the text color.
- **Filled state:** a selected or rated state fills the same outline icon (`fill: 'currentColor'`, or `fill-current` / `fill: currentColor` in CSS), e.g. a voted thumb or a liked heart. There is no separate filled set.
- **One icon per meaning:** coaster `roller-coaster`, park `ferris-wheel`, review `message-square-text`, photo `image` (upload: `camera`), rating `star`, Top list `clipboard-list`, loading `loader-circle` spinning, ridden `circle-check`, gone (a ridden legend) `ghost`, new `sparkles`, best rank ever `crown`, world rank `globe`, duel `swords`. Reuse these before picking another.
- **Exceptions:** brand logos (`fe:google` on sign-in) and the drawn rating stars (half state, see Components). `npm run check:icon-sets` fails CI on any other locked set.
- **Markup built in JS** takes its icons from the server-rendered `<template id="js-icons">` (`js/icons.js`), never emoji or glyphs.

Feedback motion uses 120ms, entrances 180ms and `cubic-bezier(.16, 1, .3, 1)`; the compact bar's fade-in and the search dialog use 180ms. Honor reduced motion by removing transitions, animations and smooth scrolling. Label unfamiliar actions and hide decorative icons from assistive technology. Prefer 16:9 discovery images, 4:3 gallery thumbnails and original-ratio photo viewers. Keep photography recognizable and alt text meaningful. Show actual member attribution when available; the supplied site photograph has no recorded photographer credit, so the guide says so explicitly.

For Symfony/Twig, Stimulus, Tailwind v4 and Vite, the tokens live in `assets/styles/tokens.css`: primitives and roles on `:root`, exposed as utilities through `@theme inline` (`bg-surface text-ink rounded-card`, `bg-action text-on-action hover:bg-action-hover`, `text-label`). Tailwind's default color palette is switched off, so only token colors (plus white) exist as utilities. Fonts are self-hosted woff2 subsets in `assets/fonts/`, bundled by Vite. Bind behavior to native controls.

## Do's and Don'ts

- Do preserve the Captain Coaster name and original logo exactly, including colors, proportions and complete artwork; the head mark alone is the only permitted reduction, for compact UI.
- Do test English, French, Spanish and German, longer labels, keyboard use and 200% zoom.
- Do use semantic CSS roles and real application data when implementing screens.
- Do credit existing photography and retain its provenance.
- Do put a new top-level destination in the footer and on Profile, not in a sixth tab or a menu; the tab bar holds exactly five.
- Do put a page's context (park, author, country) on its own line under the title.
- Do take livery motifs from their single component or token; never redraw stripes or stars inline.
- Don’t recolor, redraw or crop the logo other than to the head mark or switching the lettering between logo ink and white.
- Don’t use sunshine or pale decorative borders as unverified text or essential control boundaries.
- Don’t present illustrative names, ranks, scores or the sample 1–5 rating scale as shipped product facts.
- Don’t put reading content on the brand surface: it frames the page (band, bars), the light canvas carries the content.
- Don’t spend more of the clean ↔ fun budget than the page type allows, and don’t stack two loud moments on one screen.
- Don’t let color alone tell two states apart.
- Don’t hide the tab bar on scroll or raise its Search tab into a create-style button.
- Don’t infer new ride-entry fields, milestone ordering or monetization from this document.
