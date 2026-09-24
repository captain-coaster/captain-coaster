---
name: Captain Coaster
description: An enthusiast’s field guide — responsive light-theme foundations.
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
    fontWeight: 600
    lineHeight: 1.08
  title:
    fontFamily: "Barlow Condensed, Arial Narrow, Helvetica Neue, sans-serif"
    fontSize: "clamp(2rem, 1.6rem + 1.5vw, 2.5rem)"
    fontWeight: 600
    lineHeight: 1.08
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
---
# Design System: Captain Coaster

> **Status: partly shipped.** Colors, fonts and tokens are live since the reskin (#413); layout, navigation and components still use the legacy `.cc-*` files and migrate one surface at a time (plan: #375). Every new or reworked component follows this document. See "Redesign: target vs. current" in `AGENTS.md` and `docs/agents/design-workflow.md`.

## Overview

**Creative North Star: "An enthusiast’s field guide"**

Compact, useful and colorful enough to carry the excitement of a ride; serious enough to explain ranking integrity. Member photography and rider contributions lead. This records the built foundations proposal, not a production component library or finished app screens.

**Key Characteristics:**

- Condensed display typography with readable multilingual body text.
- Fresh blue actions, sunshine highlights and crisp ink on cool light surfaces.
- Mobile-first layouts and visible, native interaction states.

## Colors

Fresh blue directs action; sunshine brings warmth to selected highlights. Ink carries primary text; slate carries secondary text. Cool canvas and white surfaces keep rider content legible. Green, amber and coral are reserved for success, warning and error, paired with their pale backgrounds and explicit labels or icons.

**The Semantic Role Rule.** Consume semantic roles in components; keep primitive colors inside the system. Logo colors remain original; the stronger action blue is an interface extension.

**Rating stars** (look provisional, #423). Stars fill with sunshine and carry an amber edge: sunshine alone is 1.5:1 on white, the edge (7.1:1) keeps the shape legible. Empty stars of the rating input use the control boundary color (6.0:1).

**The Rating Scale.** Ordered rating data (the 0.5★–5★ distribution) runs from deep red through gold to a vivid green, interpolated with `color-mix(in oklch)` between `rating-scale-low` and `rating-scale-high`. Lightness rises toward "good", so the order stays readable with color-vision deficiencies; every step is ≥3:1 on white. Segments sit on a hairline surface gap with their labels outside the bar, never on it.

**The Roles-Not-Palettes Rule.** Data needs (ratings, statuses, counts) are semantic roles over the existing hues. When a role needs a missing lightness step, extend that hue's ramp (`green-500`, `coral-700`); never add a standalone hue or a parallel palette. Status and map-marker colors are not settled yet and keep their provisional aliases.

The frontmatter mirrors the canonical OKLCH values in `assets/styles/tokens.css` (the implementation since the reskin, #413; `.impeccable/captain-coaster-tokens.css` is the original proposal). `.impeccable/contrast-report.json` records WCAG sRGB luminance measurements: primary text 14.53:1, secondary text on white 6.01:1, default action 6.77:1, hover action 9.25:1, selected text 5.96:1 and ink on sunshine 9.79:1. Status pairings exceed 5.3:1. These are measured pairings, not a full accessibility certification. Keep normal text ≥4.5:1 and essential boundaries, focus indicators and large text ≥3:1.

## Typography

Barlow Condensed semibold gives short headings the wordmark’s condensed energy. Source Sans 3 carries body copy, controls and all four locales. Both load locally from `assets/fonts/` with `font-display: swap`; retain their font licenses. Use the supplied display/title clamps, body/lead/caption scale and system monospace for code. Keep prose near 70 characters per line and inputs at least 16px.

The guide’s own editorial headings use larger presentation sizes; the exported scale above is the reusable application foundation. Avoid fixed-height text containers and forced uppercase on long translated labels.

## Layout

Start with a single column, flexible widths and wrapping labels. Use the 4px spacing rhythm, fluid 20–48px gutters and the exported 75rem content maximum as the reusable container default. Add columns when content fits. The guide itself has an editorial side index on desktop and a native disclosure index on mobile; this does not establish application navigation.

Use at least 44px interaction targets, preferably 48px. Preserve logical DOM order as layouts change. Validate at narrow phone widths and 200% zoom; allow tables and code to scroll within their own containers.

## Elevation & Depth

Use tonal surfaces and restrained borders first. Raised elements use `--shadow-raised` (0 4px 16px oklch(0.28 0.04 245 / .09)); overlays use `--shadow-overlay` (0 12px 32px oklch(0.28 0.04 245 / .16)). Depth communicates layering, not decoration.

## Shapes

Small accents use the small radius; controls use the control radius and containers the card radius. Reserve pill shapes for compact tags. Borders are 1px: pale `border` for decorative separation, stronger `control-border` where a boundary identifies an input. Keep original logo artwork intact: `logo_light.svg` (white lettering) on dark surfaces, `logo_dark.svg` (lettering in the logo's own #28343A) on light ones. In compact UI the head mark (the star mask) may stand alone; it keeps its original colors and reads on light surfaces too. Proposed minimum logo widths: 180px horizontal, 238px stacked, with at least 12px clear space in compact UI and 24px in standalone placements.

## Components

Buttons are clear and compact: primary action blue with white text, deeper blue on hover; secondary white with ink text and a visible boundary. The built samples use 48px minimum height, 10px 18px padding and semibold body type. Controls retain visible keyboard focus: a 3px blue outline with at least a 3px offset. On dark surfaces use a sunshine focus indicator; code panes use an inset outline to avoid clipping. Disabled controls are visibly muted and remain semantically disabled.

Use native links, buttons, disclosure and radio controls. Selection needs a shape, check or label as well as color. Put validation beside its field and announce saved/copied outcomes through polite live regions. “Ridden, unrated” is a valid neutral state. Read-only star ratings use the same two-path star as the rating input (amber outline painted over the sunshine fill, half state clipped), always five stars, announced as “3.5/5”. The rating specimen is illustrative and saves no production data; richer ride tracking remains undecided in `PRODUCT.md`.

Feedback motion uses 120ms, entrances 180ms and `cubic-bezier(.16, 1, .3, 1)`. Honor reduced motion by removing transitions, animations and smooth scrolling. Use a 24px icon grid, 1.75px rounded strokes, and 20px icons in dense rows. Label unfamiliar actions and hide decorative icons from assistive technology. Prefer 16:9 discovery images, 4:3 gallery thumbnails and original-ratio photo viewers. Keep photography recognizable and alt text meaningful. Show actual member attribution when available; the supplied site photograph has no recorded photographer credit, so the guide says so explicitly.

For Symfony/Twig, Stimulus, Tailwind v4 and Vite, the tokens live in `assets/styles/tokens.css`: primitives and roles on `:root`, exposed as utilities through `@theme inline` (`bg-surface text-ink rounded-card`, `bg-action text-on-action hover:bg-action-hover`). Tailwind's default color palette is switched off, so only token colors (plus white) exist as utilities. Fonts are self-hosted woff2 subsets in `assets/fonts/`, bundled by Vite. Bind behavior to native controls.

## Do's and Don'ts

- Do preserve the Captain Coaster name and original logo exactly, including colors, proportions and complete artwork; the head mark alone is the only permitted reduction, for compact UI.
- Do test English, French, Spanish and German, longer labels, keyboard use and 200% zoom.
- Do use semantic CSS roles and real application data when implementing screens.
- Do credit existing photography and retain its provenance.
- Don’t recolor, redraw or crop the logo other than to the head mark or the approved dark-lettering variant (`logo_dark.svg`) for light backgrounds.
- Don’t use sunshine or pale decorative borders as unverified text or essential control boundaries.
- Don’t present illustrative names, ranks, scores or the sample 1–5 rating scale as shipped product facts.
- Don’t infer new ride-entry fields, milestone ordering, monetization, dark mode or app navigation from this proposal.
