# AGENTS.md

## About the project

Captain Coaster is a participative guide for roller coaster enthusiasts — users rate, review, and build top lists for coasters they have ridden. The global ranking is computed monthly from pairwise comparisons of the riders' ratings and top lists.

## Tech stack

- **Backend**: Symfony 7.x, PHP 8.5, Doctrine ORM
- **Database**: MariaDB 11.8
- **Cache/Queue**: Redis
- **Frontend**: Vite (via `symfony/reprise`), Tailwind CSS v4, Stimulus (Hotwire) — no jQuery, no Bootstrap/LESS (fully removed, #383/#391)
- **API**: API Platform v4
- **Admin**: EasyAdmin v4
- **Storage**: AWS S3 via Flysystem
- **AI**: AWS Bedrock (`BedrockService`) — used for coaster summaries
- **Auth**: Google OAuth (KnpOAuth2ClientBundle) + Symfony login-link (magic-link email) + API key auth for `/api` + Cloudflare Turnstile

## Core development principles

- **KISS, never over-engineer** — choose the simplest but robust solution that works.
- **Mobile-first**: ~80% of usage is mobile, ~20% desktop. Desktop is secondary but must stay functional.
- **Terse code comments and PR descriptions.** No narration of the development process (what was tried, reverted, or caught in review), no restating what a diff already shows. A PR description states what changed, why, and what a reviewer needs to know — not a session log.

## Architecture

### Layer separation

- **`src/Controller/`** — HTTP request/response only. Validate input via Symfony forms, delegate everything else. Never contains business logic or direct queries.
- **`src/Service/`** — all business logic and orchestration. Uses repositories for data access, handles external API calls.
- **`src/Repository/`** — Doctrine DQL/QueryBuilder queries only. Never contains business logic.

### Service layout

`src/Service/` is grouped by subject: a feature with two or more classes gets its own folder (`Service/Ranking/`, `Service/Sitemap/`), tests mirrored under `tests/Service/{Subject}/`. A lone class stays at the root.

- **New class**: put it in its subject's folder; when it is the second class of a subject, create the folder and move the first one in the same PR.
- **Existing flat classes** (most of the root today) move when their subject is next reworked, one subject per PR.
- **Split a feature where deciding meets I/O**: one class works out what to produce from the repositories, another writes it (file, S3, HTTP), so each is tested without the other. `SitemapEntries` lists URLs, `SitemapWriter` streams them to disk.

### Routing

All user-facing routes are locale-prefixed: `/{_locale<en|fr|es|de>}/`. The root `/` and admin `/team` routes bypass the locale prefix.

### Core domain model

Not an exhaustive entity list (see `src/Entity/`) — just the ones with behavior that isn't obvious from the class name alone.

- **`User`** — `enabled` and `deletedAt` are baked into the login-link and remember-me cookie signatures, so disabling or soft-deleting an account invalidates existing magic links/cookies without deleting the row.
- **`Coaster`** — the main entity, belongs to a `Park`, has `Image`, vocabulary taxonomy (`MaterialType`, `Model`, `Manufacturer`, `Launch`, `Restraint`, `SeatingType`, `Status`).
- **`RiddenCoaster`** — the central join entity linking `User` ↔ `Coaster` (unique per pair). Its existence means the user has ridden the coaster. Holds a **required** rating (`value`, column `rating`, 0.5–5), review text, language, pros/cons `Tag` collections, a computed `score`, and `riddenAt`. The ranking already skips `rating IS NULL`, ready for ridden-without-rating entries (prototyped on the old `feature/tailwind` branch, not on `main`). Mutations happen directly in `RatingCoasterController` — there's no service layer here, the one exception to the layer-separation rule above.
- **`Ranking`** / **`RankingHistory`** — one `Ranking` per month (`month`, unique) and one `RankingHistory` row per ranked coaster: the source of truth. `ranking:update` computes the ranking (`RankingCalculator`: pairwise duels from ratings and main Tops) and stages it, pending (`publishedAt` null, invisible on the site); `ranking:publish` publishes it on the 1st at noon UTC by copying its ranks into `Coaster::$rank`/`$previousRank`, a denormalized copy the pages sort on. `Ranking::$report` holds the run's report (top 10, newcomers, big moves, anomalies posted to Discord).

### Frontend

Current state:

- **Stimulus-first** for client-side behavior. No jQuery (removed, #389).
- **No CSS in JavaScript or Twig** — styles belong in `assets/styles/`.
- **Tailwind CSS v4.** Bootstrap 3/Limitless/LESS are fully removed (#383, #391) — cascade layers (`theme`/`base`/`components`/`utilities`), one token source (`assets/styles/tokens.css`: colors, breakpoints), `theme()` and the modern range-syntax (`width < ...`) for breakpoint media queries.
- **Supported browsers: Safari/iOS 16.4+, Chrome/Edge 111+, Firefox 128+.** Set by Tailwind v4's fixed Lightning CSS targets and Vite's default `build.target` (`baseline-widely-available`); there's no `browserslist`. A baseline, not a hard limit: newer features are fine when older browsers degrade cleanly (e.g. `starting:` dialog transitions), and the baseline itself can move up for a good enough reason.
- **New components: compose Tailwind utility classes directly in Twig markup by default.** Existing `.cc-*` files (`cc-panel`, `cc-media`, `cc-alert`, ...) are hand-written, BEM-named CSS (`.block__element--modifier`) that intentionally mirrors the old Bootstrap-Limitless component shape — kept as-is to avoid visual churn during the hardening pass (#385), not a pattern to extend. Add a new hand-written CSS class only when the same result genuinely can't be expressed as utilities in markup (a pseudo-element icon, a complex multi-selector interaction) — and say why in a comment when you do, the way `alerts.css`'s file header does. The redesign is the right moment to replace a `.cc-*` file outright rather than add to it.
- `scripts/check-css-contract.mjs` (`npm run check:css-contract`, enforced in CI) fails the build if a deprecated Bootstrap class name reappears in source — extend its `deprecatedClassFamilies` list when retiring another one.

Assets live in `assets/`:

- `js/` — vanilla JS entry points and utilities
- `controllers/` — Stimulus controllers (one file per controller, named `*_controller.js`), auto-registered via `vite.config.js`'s `stimulus` option and started from `assets/bootstrap.js`. `controllers.json` is Symfony UX's registry for bundle-provided controllers (currently empty — no such bundles in use), not how local ones get registered
- `styles/app.css` — entry point; imports `tokens.css` first, then every component file under `layer(components)`. Two page-specific CSS entries (`styles/coaster.css`, `styles/top-list.css`, declared in `vite.config.js`) stay out of `app.css` — files there that use `theme()` import `tokens.css` directly too, since each Vite CSS entry runs its own independent Tailwind build and `theme()` only resolves within that entry's own graph
- `icons/` — locked Iconify SVGs, committed via `php bin/console ux:icons:lock` — CI fails if a template references an icon that isn't locked, or if a locked icon is outside Lucide (`lucide:`) and the exceptions in `scripts/check-icon-sets.mjs`. Icons referenced only from PHP (`NotificationType`) aren't found by the lock command: add them with `ux:icons:import`. Rules: DESIGN.md, Iconography

Mid term aim:

- Symfony UX Twig/Live Components as a default for new interactive UI
- A visual redesign, now that Bootstrap/LESS removal and CSS hardening (#385) are done

### Redesign: target vs. current

`DESIGN.md` describes the **target** design system. Its colors, fonts and tokens are live in `assets/styles/tokens.css` since the reskin (#413), and the page shell and navigation are migrated (Twig Components under `templates/components/Nav/` and `Page/`); page content components are still the **legacy** `.cc-*` files, migrated one surface at a time. Plan and progress: #375. How to run each step (Impeccable usage, 2026 baseline per step): `docs/agents/design-workflow.md`.

- Order: reskin (old `--cc-*` tokens become aliases of the target colors, new fonts — whole site at once) → page shell → navigation → Home (reference page) → remaining pages.
- It's a complete makeover: use the state-of-the-art approach for each step, not the minimal change.
- The livery (#469) is the brand layer on top: the navy `brand` surface, sunshine livery motifs, Barlow Condensed 800 italic numerals, and a clean ↔ fun budget per page type (DESIGN.md, Overview). Place each migrated page on that budget before designing it; reference board: `docs/design/livery.html` (local only, gitignored).
- Every new or reworked component:
  - is a Twig Component (`templates/components/`, anonymous unless it needs logic), variants via `html_cva`;
  - uses only Tailwind utilities, including those generated from the target tokens (`bg-action`, `text-ink`, `rounded-card`);
  - never uses a legacy class (`.cc-*`, or helpers from the old CSS files like `text-semibold`, `text-size-small`) nor `var(--cc-*)`;
  - replaces hardcoded px spacing (`mb-[15px]`, px literals in CSS) with Tailwind's spacing scale;
  - deletes the `.cc-*` rules it replaces once nothing else uses them.
- `--cc-*` tokens are compatibility aliases, removed as their users migrate.
- `assets/styles/tokens.css` is the canonical token source.
- Tailwind's default color palette is switched off: only token colors (plus white) exist as utilities. Add a missing shade as a ramp step + semantic role in `tokens.css`, never a standalone color.

### Naming conventions

- **Controllers**: `{Feature}Controller` (e.g. `CoasterController`)
- **Services**: named for what the class does (`RankingCalculator`, `SitemapWriter`, `PictureUrlSigner`, `PromptNameSanitizer`). Many older ones end `Service` or `Manager` (`RankingService`, `ImageManager`); for a new class, `Service` is the fallback when no sharper noun fits
- **Repositories**: `{Entity}Repository`
- **Voters**: `{Entity}Voter`
- **Form Types**: `{Purpose}Type`
- **Templates**: feature directories are PascalCase matching the controller name without "Controller" (`CoasterController` → `templates/Coaster/`); files themselves are snake_case (`show.html.twig`)
- **Partial templates**: prefix with underscore (`_rating_widget.html.twig`), included via `{% include %}`

### Internationalisation

Translations use `intl-icu` format. Files are in `translations/` as `{domain}+intl-icu.{locale}.yml`. Supported locales: `en`, `fr`, `es`, `de`. Translation domains in use: `messages`, `security`, `validators`, `database`, `top`, `ranking`, `ai_summary`, `policy`, `notification`.

Mid-term aim: one domain per major feature area, rather than today's mix of technical (`security`, `validators`) and per-feature (`top`, `ai_summary`) domains. Align a new domain to that when adding one; no rush to migrate existing ones.

Key naming, `snake_case`: a form's strings are `{feature}.form.{field}` (label), `{field}_help`, `{field}_placeholder`, `submit` and `submitting`; its flash messages `{feature}.flash.*`; strings shared by several forms live under `form.*` (`form.cancel`, `form.email`). Don't borrow another feature's key. A key exists in all four locales or in none. Exception: `filters.{name}` follows the filter's query parameter name.

Database vocabularies (`App\Entity\Vocabulary`: country, continent, status, launch, restraint, tag, material and seating types) have a stable `code` and an English `name`. Display them through `VocabularyLabeler` (`|vocab_label` in Twig, `|vocab_term` for scalar rows), never `name|trans`: it translates the code and falls back to `name`, so a value added in the admin shows in English until its translation is added. It also sorts lists by translated label (SQL can't).

- Country: `code` is the ISO 3166-1 alpha-2 code (required), labelled by Symfony Intl. No translation file. `SYMFONY_INTL_WITH_USER_ASSIGNED` (`.env`) adds Kosovo (`XK`).
- The others: `code` is a key of the `database` domain (`status.operating`), and what PHP, CSS (`data-status`) and templates compare on. `DatabaseTranslationsTest` keeps the four locale files on the same keys.

### API

API Platform exposes read-only endpoints for `Coaster` and other entities. Not publicly advertised — `/api` and `/api/docs` are restricted to `ROLE_ADMIN` to stop new external sign-ups, but existing API keys and `ApiKeyAuthenticator` still work, and the frontend calls it internally (e.g. `search_controller.js`). Serialization groups control what's exposed (`list_coaster`, `read_coaster`, etc.).

### Images

Images are uploaded to AWS S3 via `ImageManager` / Flysystem (`oneup/flysystem-bundle`). The `ImageListener` handles post-persist/update/delete lifecycle hooks.

Cropping/resizing does **not** happen in this repo — it's handled by a Lambda (`sharp`/libvips) in the sibling `captain-infra` project. This app only signs request URLs via `PictureUrlSigner` (canonical strings + HMAC scheme must stay identical to captain-infra's `v2.mjs` for `/i/*` and `/a/*`; `PictureUrlSignerTest` holds shared vectors). Any per-image data the crop step needs (e.g. the `watermarked` flag) rides along as S3 object metadata set in `ImageManager::upload()`, since the Lambda has no DB access.

### Caching

Pick a cache's policy from what changes its data:

- **A known event** (ranking publication, a coaster/park/image/user edit): an explicit cache id, cleared by the event's listener or subscriber (`src/EventListener/`, `RankingCacheSubscriber`), with a long TTL as a backstop. Example: `CoasterSummaryRepository`.
- **A continuous stream** (latest reviews, home stats): a short TTL, staleness accepted.
- **The current member's own actions**: uncached when the query is per-member (they're cheap and indexed); when it's shared, a listener clears the entry, so a member sees their action right away (`RiddenCoasterListener`).

Each piece of data lives in one cache layer: search results are cached in `search.cache_pool` only, so the listeners' clear takes effect immediately.

### Sitemaps

`/sitemap.xml` and `/sitemap_image.xml` are static files, with no route or controller: the daily `sitemap:update` cron writes them into `public/` (git-ignored) and the web server serves them. A page type joins the sitemap in `SitemapEntries`, and its template sets `canonical` (`{route, params}`), which `base.html.twig` turns into the same canonical URL and `hreflang` alternates in the `<head>`. `SitemapWriter` refuses a file above 50,000 URLs (the format's limit) and keeps the previous one; the pages sitemap is at about 44,000, the next step is a sitemap index.

### Code style

PHP follows the `@Symfony` + `@Symfony:risky` + `@PHP82Migration:risky` + `@PHP85Migration` ruleset (php-cs-fixer). All PHP files use `declare(strict_types=1)`. PHPDoc on single-line const/method/property uses the `phpdoc_line_span: single` rule.

## Testing

- **Unit tests are the default** — business logic in isolation, mocking repositories/`EntityManager`. There's no kernel/database-backed test infrastructure yet (a database in CI is planned).
- **Naming**: `{ClassName}Test.php` for unit tests, `{ClassName}PropertyTest.php` for property tests.

## Git workflow

- Create worktrees with the built-in `EnterWorktree` tool, not `git worktree add`. `.worktreeinclude` makes it copy `.env.local` into each new worktree automatically. Never edit `.env.local` — it holds live secrets, and per-worktree copies are point-in-time snapshots, not kept in sync with each other.
- There is no pre-commit hook — run `vendor/bin/php-cs-fixer fix` and `vendor/bin/phpunit` yourself before committing.
- Redis, MariaDB and Adminer run in shared containers. Never `docker compose down`.
- One PR per feature — keep it small where the change allows.
- PR titles must follow Conventional Commits (`type(scope): subject`) — CI enforces this (`pr-title.yml`); allowed types: `feat`, `fix`, `chore`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `revert`.
- Never push or open a PR without explicit confirmation first.

## Continuous Integration

`.github/workflows/ci.yml` runs: composer validate, the CSS contract check (no deprecated Bootstrap classes), PHPUnit, PHPStan, php-cs-fixer, Twig lint, locked-icon check, container lint, Doctrine schema validate, ShellCheck on `deploy.sh`, and a compromised-dependency audit.

On `main`, it also publishes `public/build` to GitHub Packages (`ghcr.io/captain-coaster/frontend-build`, tagged with the commit SHA). `deploy.sh` pulls that build with `oras` instead of running Vite, and builds locally only when CI has none for the commit (#400).

## Security

Run the `security-review` skill before opening a PR that touches authentication, user input handling, file uploads, external API or AI calls (`BedrockService`), or admin/EasyAdmin routes.

## Development commands

Local startup is covered by the `dev-environment` skill — invoke it rather than running these by hand.

```bash
# Run all tests
vendor/bin/phpunit

# Static analysis (level 7)
vendor/bin/phpstan analyse

# Code style fix
vendor/bin/php-cs-fixer fix
```

### Verifying UI changes

A dev server runs locally — use the Playwright MCP tools against it to verify front-end work. The port varies per worktree (see `.claude/skills/dev-environment/SKILL.md`), so read the actual URL rather than assuming `localhost:8000`. Resize to a mobile viewport (e.g. 390×844) first, since ~80% of usage is mobile.

## Agent skills

### Issue tracker

Issues live in GitHub Issues (`captain-coaster/captain-coaster`), via the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

Default canonical roles, except `wontfix` reuses the existing `status/wontfix` label. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: `CONTEXT.md` + `docs/adr/` at the repo root. See `docs/agents/domain.md`.
