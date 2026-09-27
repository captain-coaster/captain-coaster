---
version: 1
slug: "templates-base-html-twig"
primary_target: "templates/base.html.twig"
related_targets: []
---

# Surface brief: app navigation (#415)

Scope: global navigation on every page: mobile header, bottom tab bar, full-screen search, desktop top bar, footer, logged in and out, 4 locales. Mode: Operate. Mobile first (390px), desktop functional. Build ticket: #416.

Job: Ranking and Home dominate, then Map and Search. Every destination one tap away; any coaster, park or rider findable by typing; the rider's Profile hub a permanent destination. Rating stays contextual (coaster page, search rows), never global nav.

Constraints: light theme first, dark theme coming: semantic role tokens only (`surface`, `canvas`, `ink`, `line`, `action`, `selected`, `highlight`, `chrome`), never ramp colors. Logo may be reduced to the head mark (star mask) in compact UI; on light surfaces the full logo uses `logo_dark.svg` (no dark bands). Tab labels always visible and must survive German.

## Direction contract

Revised 2026-09-24 after a design grill and prototype (decision log on #415); supersedes the "Raised centre Search" contract.

THESIS: Navigation lives in the thumb as one floating pill; the top of the screen belongs to the page. Refuses the brand band, the hamburger and the raised "create"-looking button.

OWN-WORLD: DESIGN.md unchanged, light theme, no dark bands. Floating translucent pill (`surface` at 75% + backdrop blur, `shadow-overlay`, 1px white edge) with 24px Heroicons and always-present labels; active = `action` on a `selected` pill; unread badge in `highlight` on Profile. Full logo (`logo_dark.svg`) only on Home and in the footer; desktop header carries it too.

STORY: Five equal destinations (Home · Ranking · Search · Map · Profile), Search a plain tab in the middle. Pages own their top: a large title on the canvas that collapses into a translucent 56px bar with the title and the page's actions. Detail pages get a round floating back button: previous page when coming from the site, otherwise the parent (coaster → park, park → map, Top → author, review → coaster). Signed out, Profile is an account page: sign-in card, then language and units.

FIRST VIEWPORT: (390) no header; page title on the canvas, round action buttons top right (filters on Ranking/Advanced search). Bottom pill 64px + 12px + safe area, labels hide on scroll down (icons only), never hides. Search opens a full-screen `<dialog>` with the field focused, recent searches and shortcut rows (Advanced search, Ranking, Map); results as full-width 56px rows. Map is full screen under the pill with floating filters. (1440, from lg) classic 64px translucent white top bar: full logo · Home / Ranking / Map (active = 3px action underline) · wide filled search field with `/` and Cmd/Ctrl+K · avatar (→ Profile page, unread badge) or Sign in. Footer (on the canvas, content-width rule): Community / About link groups; from lg also language select and units toggle.

FORM: Floating pill tab bar, middle Search. Provenance: a human design grill against 2026 conventions plus a clickable prototype (2026-09-24, decision log on #415) replaced a concept-seed roll; the earlier roll (seed 3eae32e1, decision comps in `.impeccable/mocks/decision/`) is superseded. Signature interaction: the large title handing over to the compact bar as it scrolls away, while the pill tightens to icons. Motion: 180ms ease-out, none under reduced motion.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict and DESIGN.md.

## Open for later

- Home → "Explore" once the Home redesign gives it a discovery purpose.
- Reviews, Tops, Riders shown in context (community stream on Home, "In N Tops" on coaster pages).
- Full-bleed photo hero with the floating buttons on coaster/park pages (coaster page redesign).
- Profile hub content (Profile surface).
- Search result rows still use emoji type markers; replace with drawn icons.
