---
name: dev-environment
description: Use when starting, restarting, or stopping this project's local development environment - the Docker services, the Symfony server, and the Vite dev server. Also covers worktree DB isolation and cleaning up worktrees.
---

# Local development environment

Canonical path is `symfony server:start`. `docker-compose.full.yml` (nginx + php-fpm) is an alternative full-container setup — do not start it here.

## Start

1. **Nothing to provision by default, even in a worktree.** `.worktreeinclude` already copied `.env.local` in via `EnterWorktree`, and its `DATABASE_URL` points at the shared `captain` database — that's the right default, no isolation needed for routine feature work.

   Only isolate the DB if this task will run `doctrine:migrations:migrate`, load fixtures, or do a bulk/destructive mutation — `captain` is a clean mirror of prod and should stay that way. To isolate:

   - Pick `captain_<slug>`: the worktree directory name, lowercased, anything outside `[a-z0-9_]` collapsed to `_`.
   - Create and clone it (root creds are fixed for local dev, see `docker-compose.yml`). Dump to a scratch file and reload from it rather than piping `mariadb-dump` straight into `mariadb` through `docker exec ... sh -c '... | ...'` — a worktree-isolated agent session's command guard refuses that piped form:

         docker exec db-captain mariadb -uroot -proot123 -e "CREATE DATABASE \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
         docker exec db-captain mariadb-dump --single-transaction --routines --events -uroot -proot123 captain > /tmp/captain_dump.sql
         docker exec -i db-captain mariadb -uroot -proot123 "$db" < /tmp/captain_dump.sql

   - Write `.env.dev.local` (Symfony loads it after `.env.local`, so it wins) — the DSN is always the same shape, no need to read `.env.local` to build it:

         DATABASE_URL="mysql://root:root123@127.0.0.1:3306/$db?serverVersion=11.8.0-MariaDB&charset=utf8mb4"

   - **Guardrail:** before any `doctrine:migrations:migrate`, run `php bin/console debug:dotenv DATABASE_URL` and confirm the database name in it isn't `captain`. Refuse to run the migration otherwise — a migration against the shared DB breaks every other worktree using it concurrently.

   **No access to the shared `captain` DB** (e.g. simulating an external contributor's setup, or an actually fresh clone): create the isolated `captain_<slug>` DB empty instead of cloning it, then `composer db-setup` (builds the schema from the current entities and marks all migrations as already applied — running the oldest migrations directly fails on an empty DB) followed by `php bin/console doctrine:fixtures:load` for a small set of sample data.

2. Start Symfony and Vite: `bin/dev`. Ports are fixed per checkout: the main checkout is always http://localhost:8000 (Vite 5173); a worktree keeps the slot it was first given in `var/dev-slot` (8001/5174, 8002/5175, ...). It prints the desktop and phone (LAN IP) URLs; re-run it after changing Wi-Fi. Plain HTTP on purpose (`.symfony.local.yaml`): a phone can't trust the local CA.
3. Health check: request the printed URL, confirm 200.
4. `app:dev:login-link <email>` prints a link to http://localhost:8000; in a worktree or for the phone, pass the URL `bin/dev` printed: `--base-url=http://<LAN IP>:<port>`.

## Stop

For the current checkout: `bin/dev stop` (stops Vite too — it's a Symfony CLI worker).

**Never run `docker compose down`.** Redis, MariaDB and Adminer are shared by every worktree.

## Cleanup sweep (on demand — e.g. "clean up my worktrees")

1. For each worktree under `.claude/worktrees/`, check its branch's PR: `gh pr view <branch> --json state -q .state`. Eligible for removal if `MERGED`/`CLOSED`, or if there's no PR at all and the worktree is >7 days old (flag that one as "probably abandoned" rather than assuming). List everything eligible and confirm once with the user for the whole batch — never delete without asking, never one at a time.
2. For each one confirmed: stop its server and Vite (`symfony server:stop --dir=<path>`; for a server started before `bin/dev` existed, also `pkill -f <path>/node_modules/.bin/vite`), drop its DB if it has one (`DROP DATABASE IF EXISTS \`captain_<slug>\`` — never `captain` itself), delete the branch (`-D` only if the PR is `MERGED`, `-d` otherwise), remove the worktree directory. Treat these as one unit — never drop the DB without removing the worktree, or vice versa.
3. Orphaned servers need no confirmation — they can't lose data. `symfony server:list` shows every running server by directory, including ones whose directory no longer exists on disk (e.g. removed outside the tool). Stop those directly (`symfony server:stop --dir=<path>`) whenever noticed.

## Notes

- `composer install` and `npm install` are per-worktree; `vendor/` and `node_modules/` are not shared.
- Adminer is on http://localhost:8081.
- Over HTTP on a LAN IP (phone), the `Secure` remember-me cookie isn't stored and secure-context browser APIs (geolocation) are off; localhost is unaffected. Google sign-in only works where the redirect URI is registered — use `app:dev:login-link` otherwise.
