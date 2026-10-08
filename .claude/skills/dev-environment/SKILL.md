---
name: dev-environment
description: Local development environment of this project. Use when starting, restarting or stopping the Symfony and Vite servers, before running a migration, fixtures or a bulk data change (database isolation), when testing on a phone, or when cleaning up worktrees.
---

# Local development environment

The app runs through `symfony server:start`. MariaDB (`db-captain`), Redis and Adminer (http://localhost:8081) run in Docker containers shared by every worktree.

**Never run `docker compose down`**: it stops them for every worktree. `docker-compose.full.yml` is an alternative all-container setup, not used here.

## Start

1. In a fresh worktree, install dependencies: `composer install` and `npm install` (`vendor/` and `node_modules/` are per worktree).
2. If the task will run a migration, load fixtures or make a bulk or destructive data change, isolate the database first (below). Otherwise keep the shared `captain` database that `.env.local` already points at.
3. `symfony server:start -d`. It starts the Vite dev server too (a worker in `.symfony.local.yaml`). Read the printed port: each worktree gets its own.
4. Done when the printed URL answers 200.

## Stop

`symfony server:stop` in the checkout. It stops Vite too.

## Isolate the database

`captain` is a clean mirror of production, shared by every worktree: a migration run against it breaks the others.

1. Name the database `captain_<slug>`: the worktree directory name, lowercased, anything outside `[a-z0-9_]` replaced by `_`.
2. Create it and clone `captain` into it, through a dump file (a piped `docker exec ... | ...` is refused by the command guard of a worktree session):

       docker exec db-captain mariadb -uroot -proot123 -e "CREATE DATABASE \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
       docker exec db-captain mariadb-dump --single-transaction --routines --events -uroot -proot123 captain > /tmp/captain_dump.sql
       docker exec -i db-captain mariadb -uroot -proot123 "$db" < /tmp/captain_dump.sql

3. Write `.env.dev.local`, which Symfony loads after `.env.local`:

       DATABASE_URL="mysql://root:root123@127.0.0.1:3306/$db?serverVersion=11.8.0-MariaDB&charset=utf8mb4"

4. **Guardrail.** Before any `doctrine:migrations:migrate`, run `php bin/console debug:dotenv DATABASE_URL`. Proceed only when the database name it shows is not `captain`.

**Without the shared `captain` data** (a fresh clone, an external contributor's setup): create `captain_<slug>` empty, then `composer db-setup` (builds the schema from the entities and marks every migration as applied) and `php bin/console doctrine:fixtures:load` for sample data.

## Test on a phone

    symfony server:stop && npm run build && symfony server:start -d --allow-all-ip --no-workers

Open `https://<LAN IP>:<port>` on the phone and accept the certificate warning. `--no-workers` keeps the Vite dev server from replacing the built assets. Afterwards restart with a plain `symfony server:start -d`: `--allow-all-ip` exposes the debug toolbar to the network.

## Clean up worktrees

On request only.

1. For each worktree under `.claude/worktrees/`, read its PR state: `gh pr view <branch> --json state -q .state`. Eligible: `MERGED` or `CLOSED`, or no PR and older than 7 days (list that one as "probably abandoned").
2. List everything eligible and get one confirmation for the whole batch before deleting anything.
3. For each confirmed worktree, as one unit:
   - stop its server (`symfony server:stop --dir=<path>`) and Vite (`pkill -f <path>/node_modules/.bin/vite`);
   - drop its database if it has one (`DROP DATABASE IF EXISTS \`captain_<slug>\``, never `captain`);
   - delete the branch (`-D` when the PR is `MERGED`, `-d` otherwise);
   - remove the worktree directory.

Orphaned servers need no confirmation: `symfony server:list` shows servers whose directory no longer exists; stop them with `symfony server:stop --dir=<path>`.
