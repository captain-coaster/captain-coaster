# AGENTS.md

This file provides guidance to AI coding agents (Claude Code, Codex, etc.) when working with code in this repository.

## About the project

Captain Coaster is a participative guide for roller coaster enthusiasts — users rate, review, and build top lists for coasters they have ridden. The global ranking is computed from user ratings using an ELO-like algorithm.

## Tech stack

- **Backend**: Symfony 7.x, PHP 8.5, Doctrine ORM
- **Database**: MariaDB 11.8
- **Cache/Queue**: Redis
- **Frontend**: Webpack Encore, LESS, Bootstrap 3 (Limitless theme), Stimulus (Hotwire) — jQuery is still present for legacy pieces, being phased out in favor of Stimulus
- **API**: API Platform v4
- **Admin**: EasyAdmin v4
- **Storage**: AWS S3 via Flysystem
- **AI**: AWS Bedrock (`BedrockService`) — used for coaster summaries
- **Auth**: Google OAuth (KnpOAuth2ClientBundle) + Cloudflare Turnstile

## Core development principles

- **KISS, never over-engineer** — choose the simplest solution that works.
- **Mobile-first**: ~75% of usage is mobile, ~25% desktop. Desktop is secondary but must stay functional.
- **Stimulus-first** for new client-side behavior; avoid adding new jQuery.
- **No CSS in JavaScript or Twig** — styles belong in `assets/styles/`.
- **Terse code comments and PR descriptions.** No narration of the development process (what was tried, reverted, or caught in review), no restating what a diff already shows. A PR description states what changed, why, and what a reviewer needs to know — not a session log.

## Development commands

```bash
# Start dev server (PHP)
symfony server:start

# Start Webpack Encore asset pipeline (required alongside Symfony server)
npm run dev-server

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

A dev server runs at `http://localhost:8000` — use the Playwright MCP tools against it to verify front-end work. Resize to a mobile viewport (e.g. 390×844) first, since ~75% of usage is mobile.

Don't screenshot on every template save — it's slow and noisy. After finishing a UI-touching change, take an accessibility snapshot first to verify structure, then one mobile screenshot; only add a desktop screenshot if the snapshot reveals an issue.

Save Playwright screenshots to `screenshots/` (gitignored) — e.g. `browser_take_screenshot({ filename: "screenshots/my-check.png" })`. Never save screenshots to the repo root.

## Architecture

### Layer separation

- **`src/Controller/`** — HTTP request/response only. Validate input via Symfony forms, delegate everything else. Never contains business logic or direct queries.
- **`src/Service/`** — all business logic and orchestration. Uses repositories for data access, handles external API calls.
- **`src/Repository/`** — Doctrine DQL/QueryBuilder queries only. Never contains business logic.

### Routing

All user-facing routes are locale-prefixed: `/{_locale<en|fr|es|de>}/`. The root `/` and admin `/team` routes bypass the locale prefix.

### Core domain model

- **`Coaster`** — the main entity, belongs to a `Park`, has `Image`, vocabulary taxonomy (`MaterialType`, `Model`, `Manufacturer`, `Launch`, `Restraint`, `SeatingType`, `MainTag`, `Status`).
- **`RiddenCoaster`** — the central join entity linking `User` ↔ `Coaster` (unique per pair). Its existence means the user has ridden the coaster. Holds a **nullable** `rating`, review text, language, pros/cons `Tag` collections, a computed `score`, and ride tracking: `firstRiddenAt`, `lastRiddenAt`, `rideCount`. `rating IS NULL` entries are **excluded from the ranking** (`RankingService`). Mutations go through `RatingService`.
- **`Top`** / **`TopCoaster`** — user-curated ordered lists of coasters.
- **`Ranking`** / **`RankingHistory`** — computed global ranking snapshots.
- **`CoasterSummary`** — AI-generated summary (via `BedrockService`) stored per coaster.
- **`Badge`** — achievement system for user engagement (candidate for a full refactor).

### Ranking algorithm

`RankingService` implements an ELO-like pairwise comparison. Constants to know:

- `MIN_COMPARISONS = 4` — minimum head-to-head comparisons between two coasters before counting
- `MIN_DUELS = 400` — minimum total duels for a coaster to appear in the ranking
- `ELITE_SCORE = 95` / `MIN_DUELS_ELITE_SCORE = 650` — stricter threshold for top-tier coasters

The ranking is recomputed by the `RankingCommand` console command and cached in Redis.

### Frontend

Assets live in `assets/`:

- `js/` — vanilla JS entry points and utilities
- `controllers/` — Stimulus controllers (one file per controller, named `*_controller.js`), registered via `controllers.json`
- `styles/app.less` — entry point, imports `components/`, `icons/`, `theme/` (Bootstrap 3 Limitless theme), `utilities/`

Styling is LESS, organized by component under `assets/styles/components/`. There's no design-token or utility-class system yet.

### Naming conventions

- **Controllers**: `{Feature}Controller` (e.g. `CoasterController`)
- **Services**: `{Purpose}Service` (e.g. `RatingService`)
- **Repositories**: `{Entity}Repository`
- **Voters**: `{Entity}Voter`
- **Form Types**: `{Purpose}Type`
- **Templates**: feature directories are PascalCase matching the controller name without "Controller" (`CoasterController` → `templates/Coaster/`); files themselves are snake_case (`show.html.twig`)
- **Partial templates**: prefix with underscore (`_rating_widget.html.twig`), included via `{% include %}`

### Internationalisation

Translations use `intl-icu` format. Files are in `translations/` as `{domain}+intl-icu.{locale}.yml`. Supported locales: `en`, `fr`, `es`, `de`. Translation domains in use: `messages`, `security`, `validators`, `database`, `top`, `learnMoreRanking`, `ai_summary`, `policy`.

### API

API Platform exposes read-only endpoints for `Coaster` and other entities. API docs at `/api/docs`. Serialization groups control what is exposed (`list_coaster`, `read_coaster`, etc.).

### Images

Images are uploaded to AWS S3 via `ImageManager` / Flysystem (`oneup/flysystem-bundle`). The `ImageListener` handles post-persist/update/delete lifecycle hooks.

### Code style

PHP follows the `@Symfony` + `@Symfony:risky` + `@PHP80Migration:risky` + `@PHP82Migration` ruleset (php-cs-fixer). All PHP files use `declare(strict_types=1)`. PHPDoc on single-line const/method/property uses the `phpdoc_line_span: single` rule.

### Testing

- **Unit tests only** — business logic in isolation, mocking repositories/`EntityManager`. No `KernelTestCase`/`WebTestCase` integration tests currently.
- **Property tests** for invariants, using Eris (`giorgiosironi/eris`) — see `tests/Service/CoasterSummaryServicePropertyTest.php` for the pattern. Keep variant counts modest; the whole suite should stay fast.
- **Naming**: `{ClassName}Test.php` for unit tests, `{ClassName}PropertyTest.php` for property tests.
- **Coverage expectations**: high coverage on `src/Service/` (business logic), request/response-only assertions on controllers, custom-query coverage on repositories.
- Structure: Arrange-Act-Assert, one assertion-concept per test, data providers for multi-scenario cases.

## Working documents are not repo history

Files under `docs/superpowers/` (specs, plans, and other AI-agent-session working documents produced by the brainstorming/writing-plans workflow) are local scratch artifacts for a given collaboration, not part of this repository's committed history. Never commit them — the directory is gitignored specifically to enforce this. This overrides any default in a skill's workflow that suggests committing a design doc or plan to git.

---

`feature/tailwind` is an in-progress migration of the frontend to Tailwind CSS v4 + Vite + Symfony UX Twig/Live Components, with its own more detailed `AGENTS.md`/`CLAUDE.md`. Once that branch merges, replace this file with its version rather than reconciling the two by hand.
