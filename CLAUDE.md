# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## About the project

Captain Coaster is a participative guide for roller coaster enthusiasts — users rate, review, and build top lists for coasters they have ridden. The global ranking is computed from user ratings using an ELO-like algorithm.

## Tech stack

- **Backend**: Symfony 7.x, PHP 8.5, Doctrine ORM
- **Database**: MariaDB 11.8
- **Cache/Queue**: Redis
- **Frontend**: Tailwind CSS v4, Vite, Stimulus (Hotwire), Symfony UX Live Components
- **API**: API Platform v4
- **Admin**: EasyAdmin v4
- **Storage**: AWS S3 via Flysystem
- **AI**: AWS Bedrock (`BedrockService`) — used for coaster summaries
- **Auth**: Google OAuth (KnpOAuth2ClientBundle) + Cloudflare Turnstile

## Development commands

```bash
# Start dev server (PHP)
symfony server:start

# Start Vite asset pipeline (required alongside Symfony server)
npm run dev

# Build assets for production
npm run build

# Run all tests
vendor/bin/phpunit

# Run a single test file or test method
vendor/bin/phpunit tests/Service/RankingServiceTest.php
vendor/bin/phpunit --filter testMethodName

# Static analysis (level 7)
vendor/bin/phpstan analyse

# Code style fix
vendor/bin/php-cs-fixer fix

# Code style check (dry-run)
vendor/bin/php-cs-fixer fix --dry-run

# Database services only (MariaDB + Redis + Adminer at :8081)
docker-compose up -d

# Doctrine migrations
php bin/console doctrine:migrations:migrate
```

### Verifying UI changes

A dev server runs at `http://localhost:8000` — use the Playwright MCP tools against it to verify front-end work (the session is already authenticated as the project owner, so the logged-in navbar/profile/notifications are visible). Resize to a mobile viewport (e.g. 390×844) first, since ~75% of usage is mobile.

Don't screenshot on every template save — it's slow and noisy. After finishing a UI-touching change, take an accessibility snapshot first to verify structure, then one mobile screenshot; only add desktop/dark-mode screenshots if the snapshot reveals an issue.

Save all Playwright screenshots to `screenshots/` (gitignored) — e.g. `browser_take_screenshot({ filename: "screenshots/my-check.png" })`. Never save screenshots to the repo root.

## Core development principles

- **KISS, never over-engineer** — choose the simplest solution that works.
- **Mobile-first**: ~75% of usage is mobile, ~25% desktop. Design at 390px, enhance at `sm:`/`lg:`. Desktop is secondary but must stay functional.
- **Stimulus-first** on the client; no jQuery.
- **No CSS in JavaScript or Twig** — utility classes in templates, `assets/css/` for anything that can't be a utility.

## Architecture

### Layer separation

- **`src/Controller/`** — HTTP request/response only. Validate input via Symfony forms, delegate everything else. Never contains business logic or direct queries.
- **`src/Service/`** — all business logic and orchestration. Uses repositories for data access, handles external API calls.
- **`src/Repository/`** — Doctrine DQL/QueryBuilder queries only. Never contains business logic.

### Routing

All user-facing routes are locale-prefixed: `/{_locale<en|fr|es|de>}/`. The `routes.yaml` maps `../src/Controller/` with this prefix automatically. The root `/` and admin `/team` routes bypass the locale prefix.

### Core domain model

- **`Coaster`** — the main entity, belongs to a `Park`, has `Image`, vocabulary taxonomy (`MaterialType`, `Model`, `Manufacturer`, `Launch`, `Restraint`, `SeatingType`, `MainTag`, `Status`).
- **`RiddenCoaster`** — the central join entity linking `User` ↔ `Coaster` (unique per pair). Its existence means the user has ridden the coaster. Holds a **nullable** `rating` (allowed set `{0.5, 1.0, … 5.0}`; `NULL` = ridden without a rating), review text, language, pros/cons `Tag` collections, a computed `score`, and ride tracking: `firstRiddenAt`, `lastRiddenAt` (only when re-ridden), and `rideCount` (default 1). `rating IS NULL` entries are **excluded from the ranking** (`RankingService`). Mutations go through `RatingService` (mark ridden, set/clear rating, re-ride + undo).
- **`Top`** / **`TopCoaster`** — user-curated ordered lists of coasters.
- **`Ranking`** / **`RankingHistory`** — computed global ranking snapshots.
- **`CoasterSummary`** — AI-generated summary (via `BedrockService`) stored per coaster.

### Ranking algorithm

`RankingService` implements an ELO-like pairwise comparison. Constants to know:

- `MIN_COMPARISONS = 4` — minimum head-to-head comparisons between two coasters before counting
- `MIN_DUELS = 400` — minimum total duels for a coaster to appear in the ranking
- `ELITE_SCORE = 95` / `MIN_DUELS_ELITE_SCORE = 650` — stricter threshold for top-tier coasters

The ranking is recomputed by the `RankingCommand` console command and cached in Redis.

### Frontend

Assets live in `assets/`:

- `js/app.js` — entry point, imports CSS and Stimulus bootstrap
- `controllers/` — Stimulus controllers (one file per controller, named `*_controller.js`)
- `js/utils/` — shared vanilla JS utilities (`dom.js`, `animation.js`) — prefer these over inline style manipulation
- `css/app.css` — Tailwind v4 config via `@theme {}` blocks; imports `base/`, `components/`, `utilities/`

Tailwind v4 is configured entirely in CSS (no `tailwind.config.js`). Dark mode uses the `.dark` class strategy (`@variant dark (&:where(.dark, .dark *))`).

### Building UI — conventions

**Color: use semantic tokens, not raw palette + `dark:` pairs.** `app.css` defines intent-named tokens (in `:root`/`.dark`, registered via `@theme inline`) that resolve per color scheme automatically:

- Surfaces: `bg-surface`, `bg-surface-raised`, `bg-surface-sunken`, `bg-surface-muted`, `bg-surface-strong`
- Text: `text-content`, `text-content-secondary`, `text-content-tertiary`, `text-content-muted`, `text-content-subtle`
- Borders: `border-line`, `border-line-strong`, `border-line-input`

Prefer `bg-surface` over `bg-white dark:bg-neutral-900` — the token carries the dark value, so **no `dark:` variant needed**. Brand colors remain raw: `cc-blue-*`, `cc-warm-*`. Re-theming = edit the `:root`/`.dark` blocks. (A long tail of raw palette pairs still exists from before the token migration; convert opportunistically when touching a template.)

**Runtime-chosen colors must be safelisted.** Classes built from DB values (e.g. `bg-{{ status.color }}-400`) are invisible to Tailwind's scanner. They're covered by `@source inline(...)` directives at the top of `app.css` — keep that list in sync with `SELECT DISTINCT color FROM status`.

**Components: use anonymous Twig Components (`<twig:Name>`), not includes.** Reusable UI lives in `templates/components/` as PascalCase files (`EmptyState.html.twig` → `<twig:EmptyState>`) declaring `{% props %}` at the top. Pass dynamic/boolean/int values with the `:` binding (`:showCoaster="false"`, `:title="'x'|trans"`); plain strings without (`variant="full"`). Do **not** add new `{% include 'components/...' %}` or pass HTML via `|raw`.

**Macros** (`templates/macros/`) remain only for pure value-returning helpers (`scoreColor`, `scoreBadge`). The `ui.html.twig` macro file has been deleted — all UI macros (`button`, `badge`, `pageHeader`, `backLink`, `starRating`) have been migrated to Twig components or removed. Do **not** create new UI macros; use `<twig:>` components instead.

**Icons:** use the `ux_icon('tabler:name', {...})` function (the established convention, ~366 call sites). Always pass `'aria-hidden': 'true'` on decorative icons.

**Interactivity tech stack.** Three tools:

- **Twig Components** (`symfony/ux-twig-component`) — stateless presentational UI. Default choice.
- **Stimulus** (`data-controller` / `data-action` / `data-*-target`) — client-side behavior (toggles, geolocation, debounced form submit). One controller per file in `assets/controllers/*_controller.js`, extending `base_controller.js`.
- **Symfony UX Live Components** (`symfony/ux-live-component`) — stateful, server-driven interactivity (filters, search-as-you-type, pagination, editable lists). See `src/Components/SearchDropdown.php`/`TopListEditor.php` for the established pattern. Prefer a Live Component over hand-rolled Stimulus + `fetch` for new interactive features.

### Naming conventions

- **Controllers**: `{Feature}Controller` (e.g. `CoasterController`)
- **Services**: `{Purpose}Service` (e.g. `RatingService`)
- **Repositories**: `{Entity}Repository`
- **Voters**: `{Entity}Voter`
- **Form Types**: `{Purpose}Type`
- **Templates**: feature directories are PascalCase matching the controller name without "Controller" (`CoasterController` → `templates/Coaster/`); files themselves are snake_case (`show.html.twig`, `list_ratings.html.twig`)
- **Partial templates**: prefix with underscore (`_rating_widget.html.twig`), included only via `{% include %}` with the `only` keyword — never for reusable UI, that's what Twig Components are for
- **Value-only macro files**: prefix with `_macros_` (`_macros_helpers.html.twig`)
- **Layout files**: prefix with `base_` (`base.html.twig`, `base_auth.html.twig`)

### Polished-page patterns (the design baseline)

The Home (`Default/index`), Rankings (`Ranking/index`), Profile (`Profile/index`), and My Coasters (`Profile/my_coasters`) pages set the visual standard. **New/migrated pages should inherit these patterns and reuse their components** (`SectionHeading`, `EmptyState`, `ReviewCard`, `UserAvatar`, the `Profile*` family) rather than inventing new ones. Mobile-first always — design at 390px, enhance at `sm:`/`lg:`.

- **Page shell:** extend `layouts/base.html.twig`; set `{% set title %}` + `{% block title %}` and SEO `{% block header %}`. The base provides the `max-w-7xl` container; wrap reading-column pages in `max-w-2xl`/`max-w-3xl mx-auto`.
- **Page header:** use `<twig:PageHeader>` — props: `title` (required), `subtitle`, `icon`, `iconColor`, `backUrl`, `backLabel`. Omit `backUrl` for top-level pages (no arrow); include it for sub-pages. Always set `{% set title %}` + `{% block title %}` separately for the browser tab title.
- **Card:** `bg-surface rounded-2xl border border-line p-4` — solid background + border, not a bare ring. Sections separated by `mb-6`/`mb-8`.
- **Section heading:** use `<twig:SectionHeading>` — props: `icon`, `iconColor` (`text-cc-warm-500` for ratings/achievements, `text-cc-blue-500` for everything else), `title`, `count`, `seeAllUrl`, `seeAllLabel`, `extraLink`/`extraLinkUrl`/`extraLinkIcon`. "See all" renders as an arrow icon, not text. Action links render below the title row.
- **Mobile-scroll → desktop-grid** (carousels): `flex gap-3 overflow-x-auto pb-2 -mx-4 sm:mx-0 snap-x snap-mandatory scrollbar-hide scroll-px-4 sm:scroll-px-0 px-4 sm:px-0 sm:grid sm:grid-cols-N sm:overflow-visible`.
- **Pills / tabs:** `rounded-full ... text-xs font-medium`; active `bg-cc-blue-600 text-white shadow-sm`, inactive `bg-surface-muted text-content-tertiary hover:bg-neutral-200 dark:hover:bg-neutral-700`; nest count badges inside.
- **Progress bar:** track `h-2.5 rounded-full overflow-hidden bg-surface-strong` + fill `bg-linear-to-r from-cc-blue-500 to-green-500 transition-all duration-500`, with `role="img"` + aria-label.
- **Image hero/overlay:** `aspect-*` + `bg-linear-to-t from-black/80 via-black/30 to-transparent` scrim + `group-hover:scale-105 transition-transform duration-500` on the image.
- **Rank tiers:** gold `bg-cc-warm-500`, silver `bg-neutral-300`, bronze `bg-amber-700`, rest `bg-neutral-700/70 backdrop-blur-sm`.
- **Primary button:** `bg-cc-blue-600 hover:bg-cc-blue-700 text-white rounded-xl` (or `rounded-full` for FABs).
- **Empty / no-results:** `<twig:EmptyState>`; for in-list "no search results" use a centered icon + `text-sm` message + clear-filter link.

### Forms

The Symfony form theme at `templates/form/tailwind_theme.html.twig` styles all widgets automatically.

- Use `form_row(form.field)` as the default — renders label + widget + help + errors in one call
- Use the `help` option for helper text: `form_row(form.field, {'help': 'my.key'|trans})`
- Do **not** pass inline `attr.class` overrides to `form_widget` — the theme handles all styling
- Labels, constraints, and translation domains belong in the PHP Form Type, not the template
- Submit buttons: use `form_widget(form.submit)` or a plain `<button type="submit">` with `bg-cc-blue-600 hover:bg-cc-blue-700 text-white rounded-xl`
- `form_rest(form)` must come **before** the submit button row, not after

### Internationalisation

Translations use `intl-icu` format. Files are in `translations/` as `{domain}+intl-icu.{locale}.yml`. Supported locales: `en`, `fr`, `es`, `de`. Translation domains: `messages`, `navigation`, `security`, `validators`, `database`, `top`, `learnMoreRanking`, `ai_summary`, `policy`, `login`.
When working on a domain: export the translations to a new domain file if not done already to tidy things up.

### API

API Platform exposes read-only endpoints for `Coaster` and other entities. API docs at `/api/docs`. Serialization groups control what is exposed (`list_coaster`, `read_coaster`, etc.).

### Images

Images are uploaded to AWS S3 via `ImageManager` / Flysystem (`oneup/flysystem-bundle`). The `ImageListener` handles post-persist/update/delete lifecycle hooks.

### Code style

PHP follows the `@Symfony` + `@Symfony:risky` + `@PHP82Migration` ruleset (php-cs-fixer). All PHP files use `declare(strict_types=1)`. PHPDoc on single-line properties uses the `phpdoc_line_span: single` rule.

### Testing

- **Unit tests only** — business logic in isolation, mocking repositories/`EntityManager`. No `KernelTestCase`/`WebTestCase` integration tests currently.
- **Property tests** for invariants, using Eris (`giorgiosironi/eris`) — see `tests/Service/CoasterSummaryServicePropertyTest.php` for the pattern. Keep variant counts modest; the whole suite should stay fast (currently well under a second).
- **Naming:** `{ClassName}Test.php` for unit tests, `{ClassName}PropertyTest.php` for property tests.
- **Coverage expectations:** high coverage on `src/Service/` (business logic), request/response-only assertions on controllers, custom-query coverage on repositories.
- Structure: Arrange-Act-Assert, one assertion-concept per test, data providers for multi-scenario cases.
