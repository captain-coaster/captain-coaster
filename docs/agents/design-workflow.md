# Design workflow (redesign #375)

Playbook for sessions picking up a redesign ticket. The decisions are settled and `DESIGN.md` holds them. Done: reskin (#413), page shell, navigation, and the livery on the navigation and the Ranking (#469–#471). Next: Home (#417), then the remaining pages one at a time.

**The Ranking page is the reference**: a migrated page looks and is built like it (`templates/ranking/`, `templates/components/Ranking/`, `FilterPanel`, `Page/Header`).

Bracketed tags like `[I:critique]` point to the Sources section.

## Authority

1. `DESIGN.md` (rules, the livery, the clean ↔ fun budget, the One-Container Rule, Tailwind practice) and `assets/styles/tokens.css` (canonical values).
2. The reference board `docs/design/livery.html`: local only, gitignored, like everything under `.impeccable/` except `config.json`.
3. `PRODUCT.md`: local only and gitignored. **Never quote it** in commits, PRs, issues or DESIGN.md. The repo is public.
4. The `--cc-*` tokens and `.cc-*` CSS are what is being replaced: evidence of what exists, never the target.

**DESIGN.md is the source of truth, kept true in every PR.** Anything decided with the human, in a ticket or in conversation, is written into DESIGN.md (and `tokens.css` when it is a value) in the same PR as the code. It describes what is live: no "target" or "until #N ships" paragraph left behind once the code lands. When a page shows a rule working badly, fix the rule there, never with a one-off value in the page.

**The rendered board is what was approved.** Before building from the board, open it in Playwright and screenshot the relevant frames; put them next to the build before showing anything. Its source, DESIGN.md and an issue can each say something the human never saw (the star field was in all three and never rendered). When they disagree, follow the render, say so, and correct DESIGN.md.

## Migrating a page

One page per PR, design and build together.

1. **Place the page**, from DESIGN.md alone:
   - its clean ↔ fun budget: which livery motifs it may use, band or not (`headerBand`, `shortTitle`);
   - its width: full, text or narrow, a side column or not, canvas or white (`pageSurface`). A page never sets its own maximum width or side padding;
   - the shared components it uses as they are (`Page:Header`, `FilterPanel`, `Button`, `RoundButton`, the form theme).
2. **Features**, when what the page must do is open: `mattpocock-skills:grilling`. Jobs, data, primary vs. secondary, empty / signed-out / error states, what gets dropped. Output: a short feature list confirmed by the human.
3. **Mockup**, when the board doesn't already show the page: `frontend-design:frontend-design`, a 390px-first HTML prototype published as an Artifact. Skip the palette and typeface part of that skill: colors, type and radii are DESIGN.md's tokens. The human approves the mockup; iterate on it, not on Twig.
4. **Build** as Twig Components (below), from the rendered board or the approved mockup.
5. **Check**: screenshots at 360, 390 and 1440px in en/fr/es/de next to the board's frames, 200% zoom, a keyboard pass, and Chrome DevTools "Emulate vision deficiencies" (deuteranopia, protanopia, achromatopsia): no state told apart by color alone.
6. **Review** with Impeccable when the page is new ground: `harden` (German length, empty states, errors) [I:harden], `polish` [I:polish], `detect` on the changed files, then a fresh `impeccable-finish-reviewer`. `critique` is optional and needs two isolated subagents [I:critique].
7. **Update DESIGN.md** in the same PR: the page's section, any new shared pattern, any rule that changed.

**A page migrates whole.** At the end of its PR every component on the page is in the new system, shared ones included (its filters, its form controls, its pager): no `.cc-*` class, no `var(--cc-*)`, no legacy helper, no class string copied from another template. Delete the `.cc-*` rules nothing else uses.

## Building components

- **Location and naming.** Anonymous component = template only, in `templates/components/`; the path gives the name (`Ranking/Row.html.twig` → `<twig:Ranking:Row>`). Props through `{% props %}`, documented in the file's opening comment.
- **Variants** with `html_cva`; render `class="{{ cva.apply({...}, attributes.render('class'))|tailwind_merge }}"` so a class passed by the caller overrides the default [SF:twig-component, Twig:html_cva]. Custom token names are registered for `tailwind_merge` in `config/packages/tales_from_a_dev_twig_extra_tailwind.yaml`: add a new type or radius token there, or merges drop it.
- **Styling** follows DESIGN.md's "Tailwind practice" list: utilities in the markup, a token for a value used twice, `@utility` for a livery motif, container queries where a side column takes room, subgrid for columns shared by rows, platform state variants (`has-checked:`, `aria-[current=page]:`, `in-data-brand:`).
- **Behavior.** Native elements first (`dialog`, `popover`, `details`), then Stimulus. Live Components only for server round-trip state.
- **Copy.** Every string through `|trans`, in all four locales. Sober wording (DESIGN.md, Voice).
- **Images.** Lambda-signed crop URLs (AGENTS.md, Images); `fetchpriority="high"` on the hero, lazy below the fold, a fixed `aspect-ratio`.
- **Motion.** 120ms feedback, 180ms entrances, `motion-reduce:` on every transition.

## Platform baseline

The target is Baseline Widely Available (AGENTS.md, Frontend). Check a feature on https://webstatus.dev before relying on it.

- **Use freely** (widely available): container queries, `:has()`, subgrid, `<dialog>` with `showModal()`, `svh`/`dvh`, `clamp()`, `color-mix()` and OKLCH, cascade layers.
- **Progressive enhancement only**, the page must work without: `@starting-style` with `transition-behavior: allow-discrete`, `text-wrap: balance`, the Popover API, invoker commands (`commandfor`), CSS anchor positioning, cross-document View Transitions.
- A feature that isn't widely available and doesn't degrade cleanly is the human's decision, recorded in AGENTS.md.

## Impeccable

A skill plus a local CLI (`~/.claude/skills/impeccable`). It reads PRODUCT.md and DESIGN.md on its own [I:SKILL]. Used here for review and polish, not for direction: page design comes from the board or the mockup step.

- Run `impeccable context --target <template>` once per session before its commands.
- **Never regenerate DESIGN.md.** `document` asks whether to refresh, overwrite or merge: always merge [I:document]. Its redesign path replaces DESIGN.md with a new world: always frame work as an extension inside the existing one.
- **PRODUCT.md leaks.** Impeccable loads it and passes it to its subagents. Keep briefs, prompts and PR text free of its strategy.
- `detect` flags design-system drift, so legacy templates are noisy: scope it to the files changed in the PR.
- The finish reviewer and critique assessments run as fresh subagents, never forked.
- Live mode is unverified on Twig (no hot reload, text matching fails on `|trans`). If used, run `live-server stop` before committing and never keep its copy edits.

## Pitfalls

- **Verification budget.** One build, one batched screenshot round, one confirm round. Don't loop screenshots.
- **Shared database.** A branch with a migration needs its own database (dev-environment skill) before any page renders.
- **Fixed-height bands.** A band holds one title line and one context line. A long title needs a `shortTitle`; anything else moves into the page.
- **Links on a brand surface** are white or sunshine and take a sunshine focus ring (`focus-visible:outline-highlight`): action blue fails on navy.

## Sources

- Impeccable (upstream https://github.com/pbakaus/impeccable, docs https://impeccable.style/docs/): [I:SKILL] `SKILL.md`, [I:critique] `reference/critique.md`, [I:polish] `reference/polish.md`, [I:harden] `reference/harden.md`, [I:document] `reference/document.md`.
- [SF:twig-component] https://symfony.com/bundles/ux-twig-component/current/index.html
- [Twig:html_cva] https://twig.symfony.com/doc/3.x/functions/html_cva.html
- Tailwind: https://tailwindcss.com/docs/theme, https://tailwindcss.com/docs/responsive-design#container-queries
- Baseline status: https://webstatus.dev
