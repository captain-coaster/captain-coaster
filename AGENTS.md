# AGENTS.md

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

### Routing

All user-facing routes are locale-prefixed: `/{_locale<en|fr|es|de>}/`. The root `/` and admin `/team` routes bypass the locale prefix.

### Core domain model

Not an exhaustive entity list (see `src/Entity/`) — just the ones with behavior that isn't obvious from the class name alone.

- **`User`** — `enabled` and `deletedAt` are baked into the login-link and remember-me cookie signatures, so disabling or soft-deleting an account invalidates existing magic links/cookies without deleting the row.
- **`Coaster`** — the main entity, belongs to a `Park`, has `Image`, vocabulary taxonomy (`MaterialType`, `Model`, `Manufacturer`, `Launch`, `Restraint`, `SeatingType`, `MainTag`, `Status`).
- **`RiddenCoaster`** — the central join entity linking `User` ↔ `Coaster` (unique per pair). Its existence means the user has ridden the coaster. Holds a **nullable** `rating`, review text, language, pros/cons `Tag` collections, a computed `score`, and ride tracking: `firstRiddenAt`, `lastRiddenAt`, `rideCount`. `rating IS NULL` entries are **excluded from the ranking** (`RankingService`). Mutations happen directly in `RatingCoasterController` — there's no service layer here, the one exception to the layer-separation rule above.

### Frontend

Current state:

- **Stimulus-first** for new client-side behavior; avoid adding new jQuery.
- **No CSS in JavaScript or Twig** — styles belong in `assets/styles/`.
- Styling is LESS, organized by component under `assets/styles/components/`. There's no design-token or utility-class system yet.

Assets live in `assets/`:

- `js/` — vanilla JS entry points and utilities
- `controllers/` — Stimulus controllers (one file per controller, named `*_controller.js`), auto-registered by `assets/bootstrap.js` scanning the directory. `controllers.json` is Symfony UX's registry for bundle-provided controllers (currently empty — no such bundles in use), not how local ones get registered
- `styles/app.less` — entry point, imports `components/`, `icons/`, `theme/` (Bootstrap 3 Limitless theme), `utilities/`

Mid term aim:

- Tailwind to replace Bootstrap and the legacy theme
- Vite to replace Webpack Encore
- Symfony UX Twig/Live Components and Stimulus as a default
- No jQuery, vanilla JS

### Naming conventions

- **Controllers**: `{Feature}Controller` (e.g. `CoasterController`)
- **Services**: `src/Service/`, descriptive names — most end `Service` (e.g. `RankingService`), a few end `Manager` or a bare domain noun where that reads more naturally (`ImageManager`, `PromptNameSanitizer`); there's no strict suffix rule
- **Repositories**: `{Entity}Repository`
- **Voters**: `{Entity}Voter`
- **Form Types**: `{Purpose}Type`
- **Templates**: feature directories are PascalCase matching the controller name without "Controller" (`CoasterController` → `templates/Coaster/`); files themselves are snake_case (`show.html.twig`)
- **Partial templates**: prefix with underscore (`_rating_widget.html.twig`), included via `{% include %}`

### Internationalisation

Translations use `intl-icu` format. Files are in `translations/` as `{domain}+intl-icu.{locale}.yml`. Supported locales: `en`, `fr`, `es`, `de`. Translation domains in use: `messages`, `security`, `validators`, `database`, `top`, `learnMoreRanking`, `ai_summary`, `policy`.

Mid-term aim: one domain per major feature area, rather than today's mix of technical (`security`, `validators`) and per-feature (`top`, `ai_summary`) domains. Align a new domain to that when adding one; no rush to migrate existing ones.

### API

API Platform exposes read-only endpoints for `Coaster` and other entities. Not publicly advertised — `/api` and `/api/docs` are restricted to `ROLE_ADMIN` to stop new external sign-ups, but existing API keys and `ApiKeyAuthenticator` still work, and the frontend calls it internally (e.g. `search_controller.js`). Serialization groups control what's exposed (`list_coaster`, `read_coaster`, etc.).

### Images

Images are uploaded to AWS S3 via `ImageManager` / Flysystem (`oneup/flysystem-bundle`). The `ImageListener` handles post-persist/update/delete lifecycle hooks.

### Code style

PHP follows the `@Symfony` + `@Symfony:risky` + `@PHP82Migration:risky` + `@PHP85Migration` ruleset (php-cs-fixer). All PHP files use `declare(strict_types=1)`. PHPDoc on single-line const/method/property uses the `phpdoc_line_span: single` rule.

## Testing

- **Unit tests are the default** — business logic in isolation, mocking repositories/`EntityManager`. There's no kernel/database-backed test infrastructure yet (a database in CI is planned).
- **Naming**: `{ClassName}Test.php` for unit tests, `{ClassName}PropertyTest.php` for property tests.

## Git workflow

- Create worktrees with the built-in `EnterWorktree` tool, not `git worktree add`. Never edit `.env.local` — it holds live secrets and is shared by symlink across worktrees.
- There is no pre-commit hook — run `vendor/bin/php-cs-fixer fix` and `vendor/bin/phpunit` yourself before committing.
- Redis, MariaDB and Adminer run in shared containers. Never `docker compose down`.
- One PR per feature — keep it small where the change allows.
- PR titles must follow Conventional Commits (`type(scope): subject`) — CI enforces this (`pr-title.yml`); allowed types: `feat`, `fix`, `chore`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `revert`.
- Never push or open a PR without explicit confirmation first.

## Continuous Integration

`.github/workflows/ci.yml` runs: composer validate, PHPUnit, PHPStan, php-cs-fixer, Twig lint, container lint, Doctrine schema validate, and a compromised-dependency audit.

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
