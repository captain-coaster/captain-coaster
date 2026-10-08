# Captain Coaster

A participative guide for roller coaster enthusiasts: members rate, review and build Top lists of the coasters they have ridden, and a world ranking is computed every month from pairwise comparisons of those ratings and Tops.

Symfony 8 on PHP 8.5, Doctrine ORM, MariaDB, Redis, API Platform, EasyAdmin, Twig Components, Stimulus, Tailwind CSS v4 built by Vite (`symfony/reprise`). Exact versions: `composer.json`, `package.json`.

## Principles

- **KISS.** The simplest robust solution wins.
- **Mobile-first.** About 80% of usage is on a phone. Desktop is secondary and stays functional.
- **Few queries.** Doctrine multiplies them silently: a lazy association read in a loop is one query per row. Load what a page needs in the repository query (joins, a dedicated select, an aggregate), and check the page's query count in the Symfony profiler before and after a change.
- **Cache wherever it fits.** Data that is expensive to compute and changes on a known event or can be a little stale belongs in a cache. Choosing the policy: `docs/agents/architecture.md`, Caching.
- **Terse comments and PR descriptions.** A PR description states what changed, why, and what a reviewer needs to know. A comment states what the code can't say itself.
- **The repo is public.** Production metrics, infrastructure specifics and anything from `PRODUCT.md` (local only, gitignored) stay out of commits, PRs and issues.

## Commands

```bash
vendor/bin/phpunit            # tests
vendor/bin/phpstan analyse    # static analysis
vendor/bin/php-cs-fixer fix   # code style
npm run check:css-contract    # no retired class names in templates
npm run check:icon-sets       # locked icons are Lucide or a listed exception
```

There is no pre-commit hook: run the first three before every commit. CI (`.github/workflows/ci.yml`) runs all of them plus Twig, container and Doctrine mapping lints.

Starting, stopping or isolating the local environment: the `dev-environment` skill.

## Git workflow

- Worktrees: the built-in `EnterWorktree` tool, which copies the files listed in `.worktreeinclude` (`.env.local` among them).
- `.env.local` holds live secrets: read-only for agents, never edited.
- Redis, MariaDB and Adminer run in containers shared by every worktree: never `docker compose down`.
- One small PR per feature. Its title follows Conventional Commits (`type(scope): subject`), enforced by CI.
- Push or open a PR only after explicit confirmation.
- Run the `security-review` skill before a PR that touches authentication, user input handling, file uploads, external API or AI calls, or admin routes.

## Delegating to subagents

Give a subagent a cheaper model whenever the task allows it: codebase search, reading logs or long outputs, running tests and linters, mechanical refactors, boilerplate, translation keys across the four locales. In Claude Code, pass `model: "sonnet"` on the Agent call (`"haiku"` for pure search and reading).

Keep the session's own model for architecture, non-trivial debugging, security review and design decisions.

## Backend

### Layers

- **`src/Controller/`**: HTTP only. Validates input through Symfony forms and delegates.
- **`src/Service/`**: business logic and orchestration, external calls.
- **`src/Repository/`**: Doctrine queries only.

One exception: `RatingCoasterController` persists ride records itself.

### Services

`src/Service/` is grouped by subject. A subject with two or more classes has its own folder (`Service/Ranking/`), tests mirrored under `tests/Service/{Subject}/`; a lone class stays at the root.

- A new class goes in its subject's folder. When it is the subject's second class, create the folder and move the first one in the same PR.
- Split a feature where deciding meets I/O: one class works out what to produce from the repositories, another writes it (file, S3, HTTP), so each is tested alone. `SitemapEntries` lists URLs, `SitemapWriter` streams them to disk.
- Name a class for what it does (`RankingCalculator`, `PictureUrlSigner`). `Service` is the fallback suffix when no sharper noun fits.

### Conventions

- All PHP files declare `strict_types=1`; style is whatever `php-cs-fixer` produces.
- Templates: a PascalCase folder named after the controller (`CoasterController` → `templates/Coaster/`), snake_case files, partials prefixed with `_`.
- Routes: every user-facing route is locale-prefixed (`/{_locale<en|fr|es|de>}/`). Only `/` and the admin (`/team`) are not.
- Tests are unit tests with mocked repositories and `EntityManager`: there is no kernel or database-backed test infrastructure. `{ClassName}Test.php`, or `{ClassName}PropertyTest.php` for property tests.

### Translations

Four locales, `en`, `fr`, `es`, `de`, in `translations/{domain}+intl-icu.{locale}.yml`. A key exists in all four or in none.

- A major feature area has its own domain (`home`, `profile`, `ranking`, `top`); `messages` holds the shared rest.
- Keys are `snake_case`. A form's strings: `{feature}.form.{field}`, `{field}_help`, `{field}_placeholder`, `submit`, `submitting`. Its flashes: `{feature}.flash.*`. Strings shared by several forms: `form.*`. A feature uses its own keys, never another feature's.
- Exception: `filters.{name}` follows the filter's query parameter name.
- Wording follows the Voice section of `DESIGN.md`.

## Frontend

- **`assets/styles/tokens.css` is the one token source.** Tailwind's default color palette is switched off: only token colors exist as utilities. A missing shade is a new ramp step plus a semantic role there, never a standalone color.
- **New or reworked UI** is Twig Components (`templates/components/`) styled with Tailwind utilities in the markup, no CSS in JavaScript or Twig. The Ranking page is the reference.
- **Legacy UI**: a template that uses a `.cc-*` class, a helper from the old CSS files (`text-semibold`, `text-size-small`) or `var(--cc-*)` has not been redesigned yet. Leave its styling alone for a small fix; a rework migrates the whole page.
- **Behavior**: native elements first (`dialog`, `details`), then a Stimulus controller (`assets/controllers/*_controller.js`, registered automatically).
- **Verify UI changes** in the browser with the Playwright MCP tools against the local server, at a phone viewport first (390×844). The port varies per worktree: read it from the server's output.

## Reference docs

Read the matching document before working in its area.

- **`DESIGN.md`**: the design system. Any visual decision, component look, copy tone, icon choice.
- **`docs/agents/design-workflow.md`**: building or migrating a page or component; Tailwind and Twig Component practice, browser support, icons, the checks to run.
- **`docs/agents/architecture.md`**: the ride record and ranking model, database vocabularies and their labels, the API, images and picture URLs, caching, sitemaps, the frontend build at deploy.

## Agent skills

### Issue tracker

Issues live in GitHub Issues (`captain-coaster/captain-coaster`), via the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

The canonical roles map to `status/*` labels and native Issue Types. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: `CONTEXT.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.
