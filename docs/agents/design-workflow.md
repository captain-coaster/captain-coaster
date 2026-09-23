# Design workflow (redesign #375)

Playbook for sessions picking up a redesign ticket. The decisions are settled: `DESIGN.md` + `.impeccable/` hold the target system, and the steps are 2 reskin → 3 shell → 4 navigation → 5 Home → 6 remaining pages. This file covers **how** to run each step. Bracketed tags like `[I:new-work]` point to the Sources section.

The whole site is getting a new look, so each step replaces the old styling instead of keeping it pixel-identical. Each step still ships as one reviewable PR.

## Authority order

1. `DESIGN.md` (target tokens + rules) and `.impeccable/captain-coaster-tokens.css` / `tailwind-theme.css` (canonical values). `design.json` is Impeccable's sidecar (component snippets, motion, shadows) [I:document §4b].
2. `PRODUCT.md`: local only and gitignored (`.gitignore:49`). Impeccable reads it on its own [I:SKILL Setup]. **Never quote it** in commits, PRs, issues, surface briefs, or anything under `.impeccable/` that gets committed. The repo is public.
3. The current `--cc-*` tokens and `.cc-*` CSS are the thing being replaced. Treat them as evidence of what exists, never as the target.

## Tool roles

**Impeccable (adopt: the only design tool).** Impeccable is a skill plus a local CLI (`~/.claude/skills/impeccable/scripts/impeccable`, skill v4.3.1). It reads PRODUCT.md, DESIGN.md and per-surface briefs [I:SKILL Setup]. It offers:
- planning: `shape`
- whole-surface design: new-work with `concept-seed` plus a browser decision page (`serve-question`) where the human locks one of several cards [I:new-work §3]
- refinement commands: `layout`, `typeset`, `adapt`, `polish`, `harden`, `clarify`
- evaluation: `critique`, `audit`, and `detect` (a local anti-pattern scanner)
- `extract`, which consolidates repeated patterns into a design system
- in-browser variants: `live`
- shipped subagents: `impeccable-finish-reviewer` (fresh-context review, no browser, reads the screenshots you pass it), `impeccable-documenter` (updates DESIGN.md + `design.json` from the build), `impeccable-asset-producer` (raster plates from an approved comp), and `impeccable-manual-edit-applier` (applies copy edits made in live mode) [agents:*].

Impeccable already covers side-by-side direction comparison and mock approval, so no second design tool is needed.

**OpenDesign (skip).** OpenDesign is a local-first desktop/daemon app (Electron, Node 24, pnpm) that generates standalone HTML prototypes, decks and media. It reads a `DESIGN.md` from a design-system package (`manifest.json` + `DESIGN.md` + `tokens.css`) and exposes an MCP server for Claude Code [OD:README, OD:design-systems]. Its "refresh an existing repo" plugin is still unchecked on its own roadmap, and its exports target React/Next/Vue [OD:README roadmap]. It adds nothing concrete here:
- Direction comparison is already covered by Impeccable's decision page.
- Its outputs are standalone artifacts, not Twig.
- Using it would mean a second design-system package, which gives two sources of truth.

**Symfony UX Twig Components + `html_cva` + `tailwind_merge` (adopt for every new component).** See the "Impeccable output to Twig Components" section. **Symfony UX Toolkit: use it for reference only.** It is experimental, copies recipes into `templates/` and `assets/` with no upgrade path, and its kits (shadcn, flowbite-4, bootstrap) bring their own class vocabulary [SF:toolkit]. Read a recipe for its a11y/behavior pattern, then rewrite it against our tokens.

**Playwright MCP** handles every visual check. Use a 390×844 viewport first, then desktop. Read the port from the dev-environment skill.

## One-time setup (first ticket that needs it)

- **Impeccable context.** Once per session, run `<skill>/scripts/impeccable context --target <file>` and follow what it prints. Don't re-run it [I:SKILL Setup].
- **Twig detection.** Twig is not in the detector's default file types. Add `"detector": {"extensions": [{"ext": ".html.twig", "engine": "html"}]}` to `.impeccable/config.json`. This is the one field you may hand-edit there [I:hooks]. Enable the hook with `/impeccable hooks on`. It writes to `.claude/settings.local.json`, which is machine-local.
- **Build path.** Set `buildPath: "code"` in `.impeccable/config.json`. Comp-led builds run a heavy phase machine (comp-spec, plates, a comp-diff hero gate at 72%) that suits persuasive art-directed pages, not this app. Its own docs call it a frontier-tier job [I:new-work §6]. Comps can still appear on the decision page as exploration.
- **Gitignore (done in the design-system PR).** Ignore `.impeccable/config.local.json`, `.impeccable/live/`, `.impeccable/review/`, `.impeccable/build/` and `.impeccable/mocks/`. Impeccable assumes `config.local.json` is gitignored [I:new-work §3, I:hooks]. Surface briefs and critique snapshots may be committed only after checking they carry no PRODUCT.md strategy.
- **Composer packages (not installed on main).** `symfony/ux-twig-component`, `twig/html-extra` (for `html_cva`, Twig ≥3.12 [Twig:html_cva]) and `tales-from-a-dev/twig-tailwind-extra` (requires Tailwind ≥4 and `twig/html-extra ^3.24` [TTE:composer]). `twig/extra-bundle` is already present.
- **tailwind-merge configuration.** By default it assumes color names don't clash with other class names [TMP:docs Configuration]. Our type tokens (`text-display`, `text-title`, `text-lead`, `text-caption`) would be read as colors, so `text-display text-ink` would lose one of the two classes. Register them under `tales_from_a_dev_twig_extra_tailwind.tailwind_merge.additional_configuration.classGroups` → `font-size: [{text: [display, title, lead, body, caption]}]`. Add radius names (`control`, `card`, `pill`) the same way if merges drop them [TTE:docs, TMP:docs].

## Step 2: Reskin (tokens + fonts, whole site)

**Impeccable:** this step is mostly mechanical, and no Impeccable command does token aliasing. Before starting, run `audit` on the Home and coaster templates. It produces a scored report and doesn't edit anything [I:audit]. After the swap, run `impeccable detect --json` on the changed CSS/templates [I:routing]. Contrast pairings are already measured in `contrast-report.json`. Only pairs the mapping creates that aren't in that report need checking.

**Recipe:**
1. Move the target tokens into `assets/styles/tokens.css`, which is the one token source (AGENTS.md). Put semantic colors in `@theme inline` so utilities exist (`bg-surface`, `text-ink`, `rounded-card`) [TW:theme]. Add the type scale to `@theme` as `--text-display` / `--text-title` / `--text-lead` / `--text-caption`, each with a `--text-*--line-height`, so `text-display` is a real utility (`tailwind-theme.css` doesn't expose these yet).
2. Alias every `--cc-*` color to a target semantic token. The PR needs a mapping table with columns: old token → new token → rationale.
3. Delete the `fonts.googleapis.com` Roboto link at `templates/base.html.twig:19`. Swap `font-family` in `base.css`.
4. Screenshots: before and after, at 390px and desktop, in en/fr/es/de. Cover Home, the coaster page, the ranking, a form, and the menu open.

**Collisions to fix in this PR:**
- **Class names.** The new token utilities collide with legacy hand-written classes: `text-muted` (`typography.css:175`), and `bg-success`/`bg-warning`/`bg-danger`/`border-danger*` (`colors.css:86-178`), used across about 31 templates. `app.css:41` declares the `utilities` layer last, so the Tailwind utility wins and the legacy rule is silently replaced. That's the same trap `tokens.css`'s header describes. Retire the legacy classes (decided): delete the legacy rules and let the target utility take over. Where a legacy class also set something else (e.g. white text on a colored background), fix the template. Add the retired names to `check-css-contract.mjs`.
- **Tailwind defaults.** `captain-coaster-tokens.css` sets `--radius-sm`, `--ease-out` and `--font-mono` in plain `:root`. Those names are also Tailwind default theme variables, and an unlayered `:root` beats `@layer theme`, so `rounded-sm`, `ease-out` and `font-mono` would change everywhere. Declare them in `@theme` on purpose, or rename them.

**2026 baseline:**
- **Fonts: WOFF2 only, subset, self-hosted** [web.dev:fonts]. `assets/fonts/` holds only TTF, and `.impeccable/captain-coaster-tokens.css` points at `.ttf`. Convert and subset them with fontTools `pyftsubset` [fonttools]. en/fr/es/de are all Latin script, so one "latin" subset covers them. Use the same `unicode-range` Google Fonts serves for Source Sans 3: `U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD` [GF:css2, MDN:unicode-range]. Keep Source Sans 3 as one variable file (weights 200–900); variable fonts suit multi-weight use [web.dev:fonts].
- `font-display: swap`, as DESIGN.md specifies (Baseline widely available [WS:font-display]).
- **Fallback metric overrides.** Add `size-adjust` / `ascent-override` / `descent-override` on a local fallback `@font-face` to cut layout shift on swap [web.dev:fonts, MDN:size-adjust]. Baseline: *limited* (no Safari) [WS:font-metric-overrides]. Harmless where unsupported, so ship it as progressive enhancement.
- **Preload only the body face's latin subset**, with `crossorigin` [web.dev:fonts] (preload is widely available [WS:preload]). Vite rewrites and hashes CSS `url()` fonts, and inlines files under `assetsInlineLimit` [Vite:assets]. Done in #413: the woff2 files live in `assets/fonts/` and go through Vite (content-hashed). Reprise lists them in `public/build/manifest.json`, which is Symfony's `json_manifest_path`, so `asset('build/assets/fonts/<file>.woff2')` returns the hashed URL for the preload. The preload is production-only: the Vite dev server's manifest doesn't list fonts. Don't put CSS-referenced files in `public/`: reprise disables `copyPublicDir`, so Vite rewrites their URLs to `/build/…` without copying them, and they 404 in production.
- **Colors stay OKLCH** as authored (Oklab/OkLCh is widely available [WS:oklab]). `color-mix()` is fine for hover and tint steps (widely available [WS:color-mix]).
- **Tailwind v4 `@theme`.** Tokens that should produce utilities go in `@theme`. Values that only reference other variables use `@theme inline`. Everything else stays in `:root`. Use `--color-*: initial` if the default palette should be dropped. Put shared theme files in an `@import`, so each separate Vite CSS entry (`score-card.css`, `top-list.css`, …) gets the same tokens [TW:theme; AGENTS.md Frontend].

**Human gate:** review the mapping table and screenshots. **Done:** CI green, contract check extended, no `--cc-*` value left without a mapping.

## Step 3: Page shell (canvas, container, gutters, base type)

**Impeccable (world is settled; refinement inside DESIGN.md):**
1. `layout templates/base.html.twig`: reading order, grouping, rhythm [I:layout].
2. `typeset`: role scale and measure [I:typeset].
3. `adapt ... mobile`: 390px first [I:adapt].

Before each command, state that DESIGN.md is the authority and that `.cc-*` is legacy being replaced. Otherwise "preserve the established world" pulls it back toward the old look [I:layout, I:typeset]. Finish with `detect`.

**Recipe:**
- Canvas: `bg-canvas`.
- Container: `max-w-(--content-max)`, or a `--container-content: 75rem` theme key.
- Gutters: `px-(--gutter)` (a 20–48px clamp).
- Base type: currently `html{font-size:16px}` and body 13px (`base.css:50-57`). Body goes to 1rem/1.5 per DESIGN.md.
- Spacing: Tailwind's default `--spacing: .25rem` already matches DESIGN.md's 4px `space-N`, so `p-4` equals `space-4` [TW:theme]. Don't add spacing tokens.
- Tension to resolve: Impeccable's Operate guidance prefers fixed rem type for app UI [I:operate], while DESIGN.md sets fluid display/title clamps. DESIGN.md wins [I:SKILL "brief wins"]. Keep clamps for page titles only.

**2026 baseline:**
- **Container queries** for components (`@container` plus Tailwind `@sm:` variants), with viewport breakpoints only for the shell [TW:responsive, MDN:container-queries]. Widely available [WS:container-queries].
- `min-h-dvh` / `svh` instead of `100vh` on mobile [MDN:length]. Widely available [WS:viewport-units].
- `clamp()` for gutters and display type (widely available [WS:clamp]).
- `:has()` for parent state, e.g. `body:has(dialog[open])` scroll lock (widely available [WS:has]).
- `text-wrap: balance` on headings (newly available [WS:text-wrap-balance]).
- Cascade layers are already in use (`app.css:41`, widely available [WS:cascade-layers]).
- **Motion:** use `motion-safe:` / `motion-reduce:`, or `@media (prefers-reduced-motion)` [MDN:prefers-reduced-motion] (widely available). DESIGN.md timing: 120ms feedback, 180ms entrance.

**Done:** every page renders on the new shell at 390px and 1440px in all four locales, with no horizontal scroll and at 200% zoom (DESIGN.md Layout).

## Step 4: Navigation

DESIGN.md explicitly does **not** define app navigation ("Don't infer … app navigation"). This step designs it.

**Design ticket (no production code):**
1. `impeccable context --target templates/base.html.twig`.
2. `/impeccable shape navigation`: discovery interview (2–3 questions per round) and a confirmed brief. It writes no code [I:shape].
3. Continue into new-work under **"Create a whole surface inside an established world"**: run `impeccable concept-seed --scope surface --mode operate`. It deals three structures. Serve them with `impeccable serve-question --start --payload <file>` (check `--schema` for the payload shape), then `--wait --key` [I:new-work §3]. With `buildPath: code`, cards carry wireframe schematics. With image generation available, cards can carry comps under `.impeccable/mocks/decision/`, anchored on a screenshot of a real page [I:new-work §3, I:visualize]. All frames are **portrait 390**.
4. **Human gate:** the human locks one card on the decision page. That's the approval [I:new-work §3]. The agent records the direction contract (THESIS / OWN-WORLD / STORY / FIRST VIEWPORT / FORM / FINISH) with `impeccable surface-brief write` [I:new-work §5]. Attach a 390px capture of the locked card to the issue.
- **Never** run `concept-seed --scope direction` here. It re-opens the visual world and ends by replacing DESIGN.md [I:new-work §3, I:SKILL].

**Implementation ticket:**
1. Build code-led against the contract [I:new-work §6]: header, menu/sidebar, footer, as Twig Components.
2. Load `craft-floor.md` before editing [I:SKILL Setup].
3. Run one batched screenshot round (390 + 1440, plus the four locales), fix everything, run one confirm round. Two rounds is the ceiling [I:new-work §7].
4. Capture `.impeccable/review/desktop.png` and `mobile.png`. Run `detect`.
5. Spawn `impeccable-finish-reviewer` fresh with the packet new-work §7 lists. Act on its one-word disposition: `recapture` / `rebuild` / `fix` / `ship`.
6. Spawn `impeccable-documenter` to record the nav components in DESIGN.md + `design.json` (merge; see Pitfalls).

**2026 baseline:**
- **Mobile menu:** use a modal `<dialog>` opened with `showModal()`. It gives focus trapping, inertness of the rest of the page, Esc to close and a `::backdrop` for free [MDN:dialog]. Widely available [WS:dialog].
- **Account and other light menus:** use the Popover API (`popover`, `popovertarget`), which provides light-dismiss and the top layer [MDN:popover]. Newly available since 2025-01 [WS:popover].
- **Invoker commands** (`commandfor` / `command="show-modal"`) open dialogs without JS [MDN:invoker-commands]. Only newly available since 2025-12 [WS:invoker-commands], so keep a small Stimulus fallback or get the human's sign-off to require it.
- **CSS anchor positioning:** Baseline *limited* [WS:anchor-positioning]. Enhancement only; popovers must position correctly without it.
- **Enter/exit animation:** `@starting-style` + `transition-behavior: allow-discrete` for dialog/popover (newly available [WS:starting-style, WS:transition-behavior]), wrapped in `motion-safe`.
- **Cross-document View Transitions** (`@view-transition { navigation: auto; }`) [MDN:view-transition]: Chrome/Edge/Safari (incl. iOS), not Firefox, so Baseline *limited* [WS:cross-doc-vt]. Fine as progressive enhancement. Disable under reduced motion.
- **Mobile nav pattern:** for an Operate surface, keep the nav familiar and give it one scheme [I:operate]. The pattern itself is decided in the design ticket, not assumed. Touch targets ≥44px, preferably 48 (DESIGN.md).

## Step 5: Home (reference page, first shared components)

**Design ticket:** same flow as Step 4: `shape home` → `concept-seed --scope surface --mode <persuade|operate>` → human locks a card → direction contract. Pick the mode from what a visitor to *this* surface is trying to do [I:SKILL Modes], and say which one in the ticket. Craft-floor bans apply here: no eyebrow/kicker labels, no identical icon cards, no hero-metric template [I:craft-floor].

**Implementation ticket:**
1. Build code-led. Screenshot rounds, then finish reviewer, same as Step 4.
2. `/impeccable extract templates/Home` to pull out anything repeated 3+ times with the same intent [I:extract]. extract stops and asks where the design system lives. Answer "anonymous Twig Components in `templates/components/`, Tailwind utilities, `html_cva` variants". It also mentions TypeScript/Storybook, which don't apply here; document variants in the component's `{% props %}` block instead.
3. `impeccable-documenter` updates the DESIGN.md Components section and `design.json`.

**2026 baseline:**
- Everything from Step 3.
- **Images:** keep the Lambda-signed crop URLs (AGENTS.md Images). Add `loading="lazy"` below the fold and `fetchpriority="high"` on the hero image. Use 16:9 discovery and 4:3 thumbnails with `aspect-ratio` (DESIGN.md).
- `details name=` exclusive accordions if needed (newly available [WS:details-name]).

**Done:** Home rebuilt entirely from `templates/components/` with no `.cc-*` or `--cc-*` on the page, the reviewer returned `ship`, and DESIGN.md is updated.

## Step 6: Remaining pages (design + build in one PR)

Per page:
1. `impeccable context --target <template>`.
2. Optionally run `critique <template>`. It needs two isolated subagents and saves a snapshot to `.impeccable/critique/`, which `polish` picks up afterwards [I:critique, I:polish].
3. Rebuild the page from existing components. Only run `concept-seed --scope surface` when the page's composition is genuinely open; a local extension skips it [I:new-work §3].
4. Run `harden` for German length, empty states and errors [I:harden], then `polish` [I:polish], then `audit`.
5. Run `detect`, then a 390/1440 × 4-locale screenshot round.

New patterns → `extract` → documenter.

## Impeccable output to Twig Components

- **Location and naming.** Anonymous component = template only, in `templates/components/`. The path gives the name (`Button/Primary.html.twig` → `<twig:Button:Primary>`). Use `index.html.twig` for a directory's root component. Declare props with `{% props %}` (required unless given a default), and pass everything else through as `attributes` [SF:twig-component]. Only add a PHP class when the component needs logic or services.
- **Variants.** Use `html_cva(base:, variants:, compound_variants:, default_variant:)`. Render `class="{{ cva.apply({variant, size}, attributes.render('class'))|tailwind_merge }}"` so a class passed by the caller overrides the default without conflict [SF:twig-component CVA, Twig:html_cva]. When several merge steps feed `html_attr`, use `tailwind_classes` [TTE:docs].
- **Translating what Impeccable produces:**
  - Impeccable's CSS snippets (`design.json` components, live-mode "carbonize" output, which normally moves rules into a stylesheet [I:live Accept]) become Tailwind utilities in the markup, using target-token utilities (`bg-action`, `rounded-control`, `shadow-raised`).
  - Hardcoded px becomes the Tailwind scale (DESIGN.md's `10px 18px` button padding ≈ `py-2.5 px-4.5`).
  - A hand-written class is allowed only for pseudo-element icons or complex selectors, with a comment saying why (AGENTS.md Frontend).
- **Copy.** All strings go through `|trans` in `translations/*+intl-icu.*.yml`. Never keep literal copy produced by Impeccable or by the live manual-edit applier.
- **Behavior.** Use native elements first (`dialog`, `popover`, `details`), then Stimulus. Live Components only when there's server round-trip state.

## Pitfalls

- **DESIGN.md overwrite.** `document` must not silently overwrite an existing DESIGN.md. It stops and asks: refresh, overwrite or merge [I:document]. **Always pick merge.** Regenerating DESIGN.md also regenerates `design.json` [I:document §4b]. The documenter subagent treats an existing DESIGN.md as "update, not replace" [agents:documenter]. After any write, `git diff DESIGN.md .impeccable/` and restore the `> **Status: target, not yet shipped.**` blockquote if it was dropped (it isn't a canonical section [I:document]). Only the ticket that finishes the migration updates that line. Impeccable never writes `captain-coaster-tokens.css` or `tailwind-theme.css`; once folded into `tokens.css` those are frozen references.
- **Redesign routing.** Impeccable's redesign path picks a replacement world and replaces DESIGN.md [I:SKILL "Redesign replaces"]. Always frame work as "whole surface / extension inside the established DESIGN.md world" so it stays at `--scope surface`.
- **Leaking PRODUCT.md.** Impeccable loads PRODUCT.md into context and asset-producer agents receive it [agents:asset-producer]. Keep direction contracts, surface briefs, comp prompts and PR text free of its strategy before committing.
- **Detector blind to Twig** until `detector.extensions` is set [I:hooks]. Also, `detect` flags design-system drift, so legacy `.cc-*` templates will be noisy. Scope `detect` to the files changed in the PR.
- **Live mode on Twig (unverified on this stack; test before relying on it).**
  - live injects a script into the served HTML file (use `templates/base.html.twig`, anchor `</body>`) and expects HMR [I:live-setup, I:live].
  - Vite/reprise does not hot-reload Twig, so expect manual reloads.
  - `live-wrap` finds elements by text, which fails on `|trans` strings and falls back to "agent-driven" [I:live Handle fallback].
  - Always run `live-server stop` so the injected script is removed before committing [I:live Cleanup].
  - Don't use live's manual copy edits; edit the translation files.
- **Verification budget.** One build, one batched inspection, one confirm round. Don't loop screenshots [I:SKILL principles]. Don't re-run paid image generation just to "verify".
- **Subagent rules.** The finish reviewer and critique assessments must run as fresh subagents, never forked; a degraded inline run must say so [I:new-work §7, I:critique].

## Sources

Impeccable (local install `~/.claude/skills/impeccable`, v4.3.1; upstream https://github.com/pbakaus/impeccable, docs https://impeccable.style/docs/):
- [I:SKILL] `SKILL.md`
- [I:new-work] `reference/new-work.md`
- [I:shape] `reference/shape.md`
- [I:visualize] `reference/visualize.md`
- [I:document] `reference/document.md`
- [I:extract] `reference/extract.md`
- [I:live] `reference/live.md`
- [I:live-setup] `reference/live-setup.md`
- [I:hooks] `reference/hooks.md`
- [I:operate] `reference/operate.md`
- [I:craft-floor] `reference/craft-floor.md`
- [I:critique] `reference/critique.md`
- [I:audit] `reference/audit.md`
- [I:polish] `reference/polish.md`
- [I:layout] `reference/layout.md`
- [I:typeset] `reference/typeset.md`
- [I:adapt] `reference/adapt.md`
- [I:harden] `reference/harden.md`
- [I:routing] `reference/routing.md`
- [agents:*] `~/.claude/agents/impeccable-{finish-reviewer,documenter,asset-producer,manual-edit-applier}.md`

OpenDesign:
- [OD:README] https://github.com/nexu-io/open-design/blob/main/README.md (Install into your coding agent, Roadmap, Design systems)
- [OD:design-systems] https://github.com/nexu-io/open-design/blob/main/design-systems/README.md
- `plugins/_official/scenarios/od-code-migration/SKILL.md`

Symfony / Twig:
- [SF:twig-component] https://symfony.com/bundles/ux-twig-component/current/index.html
- [SF:toolkit] https://symfony.com/bundles/ux-toolkit/current/index.html
- [Twig:html_cva] https://twig.symfony.com/doc/3.x/functions/html_cva.html
- [TTE:docs] https://github.com/tales-from-a-dev/twig-tailwind-extra/blob/main/docs/index.md
- [TTE:composer] its `composer.json`
- [TMP:docs] https://github.com/tales-from-a-dev/tailwind-merge-php/blob/main/docs/index.md

Tailwind / Vite:
- [TW:theme] https://tailwindcss.com/docs/theme
- [TW:responsive] https://tailwindcss.com/docs/responsive-design#container-queries
- [Vite:assets] https://vite.dev/guide/assets

Fonts:
- [web.dev:fonts] https://web.dev/articles/font-best-practices
- [GF:css2] https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@200..900 (latin block)
- [fonttools] https://fonttools.readthedocs.io/en/latest/subset/

MDN:
- [MDN:unicode-range] https://developer.mozilla.org/en-US/docs/Web/CSS/@font-face/unicode-range
- [MDN:size-adjust] https://developer.mozilla.org/en-US/docs/Web/CSS/@font-face/size-adjust
- [MDN:dialog] https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/dialog
- [MDN:popover] https://developer.mozilla.org/en-US/docs/Web/API/Popover_API
- [MDN:invoker-commands] https://developer.mozilla.org/en-US/docs/Web/API/Invoker_Commands_API
- [MDN:view-transition] https://developer.mozilla.org/en-US/docs/Web/CSS/@view-transition
- [MDN:container-queries] https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_containment/Container_queries
- [MDN:length] https://developer.mozilla.org/en-US/docs/Web/CSS/length#relative_length_units_based_on_viewport
- [MDN:prefers-reduced-motion] https://developer.mozilla.org/en-US/docs/Web/CSS/@media/prefers-reduced-motion

Baseline:
- [WS:<id>] Baseline status from https://webstatus.dev/features/<id>, checked 2026-09-23. Ids used: `font-display`, `font-metric-overrides`, `link-rel-preload` (cited as `preload`), `oklab`, `color-mix`, `container-queries`, `viewport-unit-variants` (cited as `viewport-units`), `min-max-clamp` (cited as `clamp`), `has`, `text-wrap-balance`, `cascade-layers`, `dialog`, `popover`, `invoker-commands`, `anchor-positioning`, `starting-style`, `transition-behavior`, `cross-document-view-transitions` (cited as `cross-doc-vt`), `details-name`.

Repo:
- `DESIGN.md`, `AGENTS.md`, `.impeccable/*`
- `assets/styles/{app,tokens,base,colors,typography}.css`
- `templates/base.html.twig`, `composer.json`, `.gitignore`
- `vendor/symfony/reprise/src/Twig/AssetExtension.php`
