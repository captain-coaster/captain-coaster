# Design workflow

How to build or migrate a page or component. What it should look like: `DESIGN.md`.

**The Ranking page is the reference**: a migrated page looks and is built like it (`templates/ranking/`, `templates/components/Ranking/`, `FilterPanel`, `Page/Header`).

## Authority

1. `DESIGN.md` (rules) and `assets/styles/tokens.css` (values).
2. The reference board `docs/design/livery.html`: local only, gitignored.
3. `PRODUCT.md`: local only, gitignored. Its content stays out of commits, PRs, issues and DESIGN.md.
4. `.cc-*` CSS is what is being replaced: evidence of what exists, never the target.

**DESIGN.md describes what is live, and every PR keeps it true.** A decision made with the human, in a ticket or in conversation, is written into DESIGN.md (and `tokens.css` when it is a value) in the same PR as the code. When a page shows a rule working badly, fix the rule there, not with a one-off value in the page.

**The rendered board is what was approved.** Before building from the board, open it in Playwright and screenshot the relevant frames. When the render, the board's source, DESIGN.md or an issue disagree, follow the render, say so, and correct DESIGN.md.

## Migrating a page

One page per PR, design and build together.

1. **Place the page**, from DESIGN.md alone:
   - its clean ↔ fun budget: which livery motifs it may use, band or not (`headerBand`, `shortTitle`);
   - its width (full, text or narrow), a side column or not, edge to edge or not (`pageSurface`);
   - the shared components it uses as they are (`Page:Header`, `FilterPanel`, `Button`, `RoundButton`, the form theme).
2. **Features**, when what the page must do is open: `mattpocock-skills:grilling`. Done when the human has confirmed a short feature list: jobs, data, primary and secondary actions, empty, signed-out and error states, what gets dropped.
3. **Mockup**, when the board doesn't show the page: `frontend-design:frontend-design`, a 390px-first HTML prototype published as an Artifact, using DESIGN.md's colors, type and radii. Iterate on the mockup, not on Twig, until the human approves it.
4. **Build** as Twig Components (below), from the rendered board or the approved mockup.
5. **Check**: screenshots at 360, 390 and 1440px in en, fr, es and de next to the board's frames; 200% zoom; a keyboard pass; Chrome DevTools "Emulate vision deficiencies" (deuteranopia, protanopia, achromatopsia) showing no state told apart by color alone.
6. **Review** with Impeccable when the page is new ground: `harden` (German length, empty states, errors), `polish`, `detect` on the changed files, then a fresh `impeccable-finish-reviewer`.
7. **Update DESIGN.md** in the same PR: the page's section, any new shared pattern, any rule that changed.

**A page migrates whole.** At the end of its PR every component on the page is in the new system, shared ones included (its filters, its form controls, its pager): no `.cc-*` class, no `pageCanvas`, no legacy helper, no class string copied from another template, px spacing replaced by Tailwind's scale. The `.cc-*` rules nothing else uses are deleted.

## Building components

- **Location and naming.** An anonymous component is a template in `templates/components/`; the path gives the name (`Ranking/Row.html.twig` → `<twig:Ranking:Row>`). Props through `{% props %}`, documented in the file's opening comment. A PHP class only when the component needs logic.
- **Variants** through `html_cva`, rendered as `class="{{ cva.apply({...}, attributes.render('class'))|tailwind_merge }}"` so a class passed by the caller overrides the default.
- **New token names** (a type step, a radius, a spacing) are registered for `tailwind_merge` in `config/packages/tales_from_a_dev_twig_extra_tailwind.yaml`, or merges drop them.
- **Behavior.** Native elements first (`dialog`, `popover`, `details`), then Stimulus. Live Components (not installed) only for state that needs a server round trip.
- **Copy.** Every string through `|trans`, in all four locales, in DESIGN.md's voice.
- **Images.** Signed crop URLs (`docs/agents/architecture.md`, Images); `fetchpriority="high"` on the hero, lazy below the fold, a fixed `aspect-ratio`.
- **Motion.** 120ms feedback, 180ms entrances, `motion-reduce:` on every transition.

## Tailwind practice

- Utilities in the markup, composed in Twig Components. No `@apply`, no page stylesheet.
- A value used twice is a token in `@theme` (`h-band-page`, `pb-tab-bar`, `max-w-text`), not a repeated arbitrary value. A one-off `calc()` over tokens is fine in brackets.
- A livery motif that isn't a single declaration is an `@utility` in `tokens.css` (`livery-type` for Barlow 800 italic, `livery-stripes`, `text-stroke-ink`), so it takes variants like any utility. The 800 italic is always `livery-type`, never spelled out.
- Layout responds to its container wherever a side column can take room (`@container`, `@xl/results:`, `@3xl/ledger:`). A box as wide as its content (a plate set on a photo) can't be a container and keeps viewport variants.
- Columns shared by several rows are one grid with `grid-cols-subgrid`.
- State comes from the platform: `has-checked:`, `peer-checked:`, `aria-[current=page]:`, `open:` and `starting:` on `<dialog>`. A component placed on a brand surface adapts through `in-data-brand:`, not a prop. JavaScript only where there is behavior (fetching, focus).
- Shared buttons, round buttons, avatars and the logo are their components: a class string is never copied between templates.

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

A skill plus a local CLI. It reads PRODUCT.md and DESIGN.md on its own. Used here for review and polish; page direction comes from the board or the mockup step.

- Run `impeccable context --target <template>` once per session before its commands.
- **DESIGN.md is edited, never regenerated.** When `document` asks whether to refresh, overwrite or merge: merge. Frame any design work as an extension of the existing system.
- **PRODUCT.md leaks.** Impeccable loads it and passes it to its subagents: keep briefs, prompts and PR text free of its content.
- `detect` flags design-system drift, so legacy templates are noisy: scope it to the files changed in the PR.
- The finish reviewer and `critique` assessments run as fresh subagents, never forked.
- After live mode, run `live-server stop` before committing and discard its copy edits: text matching fails on `|trans`.

## Pitfalls

- **Verification budget.** One build, one batched screenshot round, one confirm round.
- **Shared database.** A branch with a migration needs its own database (`dev-environment` skill) before any page renders.
- **Fixed-height bands.** A band holds one title line and one context line. A long title needs a `shortTitle`; anything else moves into the page.
- **Links on a brand surface** are white or sunshine with a sunshine focus ring (`focus-visible:outline-highlight`): action blue fails on navy.

## Sources

- Impeccable: https://impeccable.style/docs/
- Twig Components: https://symfony.com/bundles/ux-twig-component/current/index.html
- `html_cva`: https://twig.symfony.com/doc/3.x/functions/html_cva.html
- Tailwind: https://tailwindcss.com/docs/theme, https://tailwindcss.com/docs/responsive-design#container-queries
