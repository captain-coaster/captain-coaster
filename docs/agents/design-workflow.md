# Design workflow

How a design decision is made, written down and built. What the product looks like: `DESIGN.md`.

## Top-down

The design system is the generic frame every page reuses. A design change enters at the highest layer it touches and flows down, in one PR:

1. **`DESIGN.md`**: the decision.
2. **`assets/styles/tokens.css`**: its values, transcribed.
3. **Components**: the frame, built once.
4. **Recipes and pages**: compositions of the frame.

A page that looks wrong is evidence about a layer above it. Find the rule or the component responsible and change it there, so every page that uses it moves together. A change only one page receives is an exception (Layers).

## Authority

- **`DESIGN.md` decides.** Its frontmatter values are normative; its prose says how to apply them. When the code, the rendered pages, a mockup or an issue disagrees with it, either `DESIGN.md` is changed on purpose, with the human, or the other side has a bug. It describes what is live, and every PR keeps it true.
- **`tokens.css` transcribes.** A value exists there because `DESIGN.md` states it. A new value is written in `DESIGN.md` first.
- **The rendered components are the observable version**: the reference page, `/en/design` on the local server, built from the real tokens and components. A rule added to `DESIGN.md` gets its rubric there in the same PR. `docs/design/livery.html` (local) is the board the livery was approved on: it shows the intended character and stays the benchmark for it, but its values and names are out of date, so take those from `DESIGN.md`.
- **A mockup is exploration.** Once approved, what it decides is written into `DESIGN.md`, and the build follows `DESIGN.md`.
- `PRODUCT.md` is local only, gitignored. Its content stays out of commits, PRs, issues and `DESIGN.md`.
- `.cc-*` CSS is what is being replaced: evidence of what exists, never the target.

## Layers

| Layer | What it is | Lives in | Specified in `DESIGN.md` as |
| --- | --- | --- | --- |
| Foundation | Color roles, type steps, spacing, radii, motifs | `tokens.css` | Frontmatter, Colors, Typography, Layout, Shapes |
| Motif | A livery object: band and stripes, sign band, ribbon, meter, hero numeral, the angle | One component or `@utility` each | The livery: its rule of use and where it may appear |
| Component | Generic: no page's content or context in its name, props or sizes | `templates/components/` root (`Button`, `Meter`, `SignBand`, `Thumb`) | Components: anatomy, variants, sizes, states |
| Recipe | A composition of components for one feature area, used the same way throughout it | `templates/components/{Area}/` (`Ranking:Row`) | Its area: which components, in which order. Sizes come from the components |
| Page | A composition of components and recipes | `templates/{Controller}/` | Its budget, width and order of blocks |
| Exception | A one-off that departs from a rule, for a stated reason | Beside the page that needs it | Exceptions: what, where, why, what would remove it |

Sizes, colors and type are set in foundations and components only. A recipe or a page that needs a value of its own is asking for a variant or an exception.

The Exceptions list is meant to shrink. Create the section with its first entry.

## Before adding anything

Take the first that fits:

1. **A component already does it**: use it as it is.
2. **A variant of a component would do it**, and another page could use that variant: add the variant (`html_cva`), specified in `DESIGN.md` first.
3. **The need is generic**: a new component, specified in `DESIGN.md` first.
4. **The need belongs to one feature area**: a recipe composed of components.
5. **A rule has to bend**: an exception. The human decides; it is recorded with its reason.

The second page that needs a recipe's pattern promotes it to a component, in that PR.

## Scales

Every size comes from a scale in `tokens.css`: a color role, a type step (`text-label`, `caption`, `body`, `lead`, `title`, `display`), a radius, Tailwind's 4px spacing. A size the scale lacks is a new step, decided in `DESIGN.md`; a Tailwind default step (`text-2xl`) or a bracket value in its place is debt.

## Changing the system

1. **Name the layer.** State the problem as a rule or a component, with every page it shows on: grep the component's uses, screenshot each at 390px. Done when no page-specific wording is left in the problem statement.
2. **Ground it** when the answer is open: usage data, a benchmark, current practice from primary sources. An unbacked answer is a hypothesis and says so.
3. **Decide with the human**: `mattpocock-skills:grilling` for the rule, `frontend-design:frontend-design` for a mockup when it is visual, showing the component in each context that uses it.
4. **Write `DESIGN.md`**, then `tokens.css`.
5. **Build the component.** Every page using it inherits the change; a page that needed an override before loses it.
6. **Check** every page from step 1 (Checks).

## Migrating a page

One page per PR.

1. **Place the page**, from `DESIGN.md` alone: its clean ↔ fun budget, its width (full, text or narrow), a side column or not, band or not (`headerBand`, `shortTitle`), edge to edge or not (`pageSurface`).
2. **Features**, when what the page must do is open: `mattpocock-skills:grilling`. Done when the human has confirmed a short list: jobs, data, primary and secondary actions, empty, signed-out and error states, what gets dropped.
3. **Map every block** to the component or recipe that covers it (Before adding anything). Done when each block has a name or a recorded gap. A generic gap is settled in the system first (Changing the system), ahead of the page.
4. **Mockup**, when the composition is open: `frontend-design:frontend-design`, a 390px-first HTML prototype published as an Artifact, assembled from the components as `DESIGN.md` specifies them. Iterate on the mockup, not on Twig, until the human approves it.
5. **Build** from `DESIGN.md` and the approved mockup (Building components).
6. **Check** (Checks).
7. **Review** with Impeccable when the page is new ground: `harden` (German length, empty states, errors), `polish`, `detect` on the changed files, then a fresh `impeccable-finish-reviewer`.
8. **Update `DESIGN.md`**: the page's order of blocks and its recipes.

**A page migrates whole.** At the end of its PR every component on the page is in the new system, shared ones included (its filters, its form controls, its pager): no `.cc-*` class, no `pageCanvas`, no legacy helper, no class string copied from another template, px spacing replaced by Tailwind's scale. The `.cc-*` rules nothing else uses are deleted.

## Checks

- Screenshots at 360, 390 and 1440px in en, fr, es and de; 200% zoom; a keyboard pass.
- Chrome DevTools "Emulate vision deficiencies" (deuteranopia, protanopia, achromatopsia): no state told apart by color alone.
- One loud moment per screen and the page's budget, read on the 390px screenshot.
- `npx @google/design.md lint DESIGN.md`, on demand, after editing the frontmatter. Read `broken-ref` and `contrast-ratio`. It reports the `clamp()` font sizes and `fontStyle` as invalid (the format has neither) and every color no frontmatter component cites as orphaned: expected.

## Not there yet

What the sections above assume and the repo doesn't have. Delete a line when it is done.

- **`DESIGN.md` is still written page by page** from Ranking on, with per-page sizes; the same pattern is specified several times (section title, top-100 meter, photo tile, icon disc, list row height). It has no Exceptions section.
- **About half the components sit under a page's name** (`Home:`, `Profile:`, `Ranking:`, `Notification:`) without having been sorted into component, recipe or exception.
- **No status component**: the coaster status families (DESIGN.md, Colors) are drawn by hand on the reference page; legacy pages use `data-status` colors that differ for Under construction, Relocated and Retracked.
- **The reference page shows the foundations only** (`/en/design`, `templates/Design/index.html.twig`, dev only): colors, type, rhythm, shapes, the livery motifs. Components are added to it as they are sorted.
- **No component uses the `heading` step, `stack`, `section` or the `row` heights yet**: section titles, lists and page spacing are still on their older sizes.
- **Motion is not on the system yet**: legacy CSS carries about thirty different transitions (`transition: all`, 0.15 to 0.3s) and the rating widget its own keyframes (sparkle, pulses of 0.6 to 1s). They move to the three gestures of DESIGN.md, Motion, as their pages migrate.
- **The dark theme is switchable on the reference page only.** Photo scrims are drawn with `ink`, which turns light in the dark theme: they need a role that stays dark before the theme reaches real pages (`Profile:Favourite`, `HeroPhoto`, `Ranking:Podium`).
- **The type scale isn't enforced**: Tailwind's default text sizes still exist and are in use.
- **Nothing checks `tokens.css` against the frontmatter.**

## Building components

`templates/ranking/` with `templates/components/Ranking/`, `FilterPanel` and `Page/Header` is the worked example of a migrated page.

- **Location and naming.** An anonymous component is a template in `templates/components/`; the path gives the name (`Ranking/Row.html.twig` → `<twig:Ranking:Row>`). Props through `{% props %}`, documented in the file's opening comment. A PHP class only when the component needs logic.
- **Variants** through `html_cva`, rendered as `class="{{ cva.apply({...}, attributes.render('class'))|tailwind_merge }}"` so a class passed by the caller overrides the default.
- **A spacing token's name becomes utilities on every sizing prefix** (`h-`, `inline-`, `block-`, `size-`): check the name against Tailwind's own classes first. `--spacing-block` silently replaced `inline-block`.
- **New token names** (a type step, a radius, a spacing) are registered for `tailwind_merge` in `config/packages/tales_from_a_dev_twig_extra_tailwind.yaml`, or merges drop them.
- **Behavior.** Native elements first (`dialog`, `popover`, `details`), then Stimulus. Live Components (not installed) only for state that needs a server round trip.
- **Copy.** Every string through `|trans`, in all four locales, in `DESIGN.md`'s voice.
- **Images.** Signed crop URLs (`docs/agents/architecture.md`, Images); `fetchpriority="high"` on the hero, lazy below the fold, a fixed `aspect-ratio`.
- **Motion.** 120ms feedback, 180ms entrances, `motion-reduce:` on every transition.

## Tailwind practice

- Utilities in the markup, composed in Twig Components. No `@apply`, no page stylesheet.
- A value used twice is a token in `@theme` (`h-band-page`, `pb-tab-bar`, `max-w-text`), not a repeated arbitrary value. A one-off `calc()` over tokens is fine in brackets.
- A livery motif that isn't a single declaration is an `@utility` in `tokens.css` (`livery-type` for Barlow 800 italic, `livery-stripes`, `text-stroke-ink`), so it takes variants like any utility. The 800 italic is always `livery-type`, never spelled out.
- Layout responds to its container wherever a side column can take room (`@container`, `@xl/results:`, `@3xl/ledger:`). A box as wide as its content (a plate set on a photo) can't be a container and keeps viewport variants.
- Columns shared by several rows are one grid with `grid-cols-subgrid`.
- State comes from the platform: `has-checked:`, `peer-checked:`, `aria-[current=page]:`, `open:` and `starting:` on `<dialog>`. A component placed on a brand surface adapts through `in-data-brand:`, not a prop. JavaScript only where there is behavior (fetching, focus).
- A class string is never copied between templates: the second use is a component.

## Assets

- `assets/styles/app.css` is the entry: it imports `tokens.css`, then the legacy component files under `layer(components)`.
- `styles/coaster.css` and `styles/top-list.css` are separate Vite entries (`vite.config.js`). Each entry is its own Tailwind build, so a file there that uses `theme()` imports `tokens.css` itself.
- Icons are locked SVGs in `assets/icons/`, committed with `php bin/console ux:icons:lock`. CI fails on a template icon that isn't locked. An icon referenced only from PHP (`NotificationType`) is invisible to the lock command: add it with `ux:icons:import`.
- Markup built in JavaScript takes its icons from the server-rendered `<template id="js-icons">` (`js/icons.js`).
- `npm run check:css-contract` fails on a retired class name. When retiring another class family, add it to `deprecatedClassFamilies` in `scripts/check-css-contract.mjs`.

## Browser support

The target is [Baseline Widely Available](https://web.dev/baseline). Vite builds to it by default; Tailwind v4's own floor sits under it (Safari 16.4, Chrome 111, Firefox 128). Nothing enforces it in CI: check a feature on https://webstatus.dev before relying on it.

- **Use freely**: container queries, `:has()`, subgrid, `<dialog>` with `showModal()`, `svh`/`dvh`, `clamp()`, `color-mix()` and OKLCH, cascade layers.
- **Progressive enhancement only**, the page works without: `@starting-style` with `transition-behavior: allow-discrete`, `text-wrap: balance`, the Popover API, invoker commands (`commandfor`), CSS anchor positioning, cross-document View Transitions.
- A feature that is neither widely available nor cleanly degrading is the human's decision, recorded here.

## Impeccable

A skill plus a local CLI. It reads PRODUCT.md and DESIGN.md on its own. Used here for review and polish; direction comes from `DESIGN.md` and the mockup step.

- Run `impeccable context --target <template>` once per session before its commands.
- **DESIGN.md is edited, never regenerated.** When `document` asks whether to refresh, overwrite or merge: merge. Frame any design work as an extension of the existing system.
- **PRODUCT.md leaks.** Impeccable loads it and passes it to its subagents: keep briefs, prompts and PR text free of its content.
- `detect` flags design-system drift, so legacy templates are noisy: scope it to the files changed in the PR.
- The finish reviewer and `critique` assessments run as fresh subagents, never forked.
- After live mode, run `live-server stop` before committing and discard its copy edits: text matching fails on `|trans`.

## Pitfalls

- **Verification budget.** One build, one batched screenshot round, one confirm round.
- **Shared database.** A branch with a migration needs its own database (`isolated-database` skill) before any page renders.
- **Fixed-height bands.** A band holds one title line and one context line. A long title needs a `shortTitle`; anything else moves into the page.
- **Links on a brand surface** are white or sunshine with a sunshine focus ring (`focus-visible:outline-highlight`): action blue fails on navy.

## Sources

- The `DESIGN.md` format (alpha): https://github.com/google-labs-code/design.md
- Components, recipes and one-offs: https://bradfrost.com/blog/post/design-system-components-recipes-and-snowflakes/
- Design Tokens format, 2025.10: https://www.designtokens.org/tr/2025.10/
- Tailwind: https://tailwindcss.com/docs/theme, https://tailwindcss.com/docs/responsive-design#container-queries
- Twig Components: https://symfony.com/bundles/ux-twig-component/current/index.html
- `html_cva`: https://twig.symfony.com/doc/3.x/functions/html_cva.html
- Impeccable: https://impeccable.style/docs/
