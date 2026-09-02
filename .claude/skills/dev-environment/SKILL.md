---
name: dev-environment
description: Use when starting, restarting, or stopping this project's local development environment - the Docker services, the Symfony server, and the Webpack Encore dev server.
---

# Local development environment

Canonical path is `symfony server:start`. `docker-compose.full.yml` (nginx + php-fpm) is an alternative full-container setup — do not start it here.

## Start

1. **Provision the checkout, if it's a worktree.** Main checkout: nothing to do, skip to step 2. In a worktree, do this once (it's idempotent — check each condition before acting):

   - **`.env.local`**: it must end up a symlink to the main checkout's copy (find the main checkout with `git rev-parse --path-format=absolute --git-common-dir`, then its parent). If it's already that symlink, leave it. If it's a regular file identical to the main copy, replace it with the symlink. If it's a regular file that *differs*, or a symlink pointing anywhere else, stop and ask the user rather than discarding it — it may hold worktree-specific secrets. Never edit `.env.local` itself.
   - **`.env.dev.local`**: write it with `DATABASE_URL` copied from `.env.local` but with the database name swapped (below). `REDIS_URL` is left as-is — worktrees share the Redis cache. Symfony loads this file after `.env.local`, so it wins.
   - **Database**: pick a name `captain_<slug>`, where `<slug>` is the worktree directory name, lowercased, with anything outside `[a-z0-9_]` collapsed to `_`. Clone it from `captain` (shared containers, so this works from any worktree):

         docker exec db-captain sh -c \
           'mariadb-dump --single-transaction --routines --events -uroot -p"$MYSQL_ROOT_PASSWORD" captain \
            | mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" '"$db"

     Create the database first (`CREATE DATABASE \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`) — see root password in `docker-compose.yml`. If it already exists, leave it; this is a one-time clone, not a resync.

2. **Start the Symfony server** from the current checkout:

       symfony server:start -d

   The CLI picks a free port and prints the URL. Two worktrees get two ports; read the URL rather than assuming 8000.

3. **Start Encore:** `npm run dev-server`.

4. **Health check:** request the printed URL and confirm 200.

## Stop

`symfony server:stop` for this checkout only.

**Never run `docker compose down`.** The database, Redis and Adminer containers are shared by every worktree. Stop them only when the user asks.

## Removing a worktree

When a worktree's branch is merged or abandoned and it's being cleaned up (via `ExitWorktree` or manually):

1. Check the branch's PR state (`gh pr view <branch> --json state -q .state`).
2. Delete the branch: `-D` (force) only if the PR is `MERGED`; otherwise `-d` (safe delete, refuses if there's unpushed content) — never force-delete a branch whose PR isn't merged.
3. Drop its database: `DROP DATABASE IF EXISTS \`captain_<slug>\`` — never drop `captain` itself.

## Notes

- `composer install` and `npm install` are per-worktree; `vendor/` and `node_modules/` are not shared.
- Each worktree has its own database, so `doctrine:migrations:migrate` there cannot affect another worktree.
- Adminer is on http://localhost:8081.
