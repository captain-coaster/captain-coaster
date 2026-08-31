---
name: dev-environment
description: Use when starting, restarting, or stopping this project's local development environment - the Docker services, the Symfony server, and the Webpack Encore dev server.
---

# Local development environment

Canonical path is `symfony server:start`. `docker-compose.full.yml` (nginx +
php-fpm) is an alternative full-container setup — do not start it here.

## Start

1. **Provision the checkout.** Run `bin/worktree setup`. Idempotent; in the
   main checkout it prints `Main checkout, nothing to provision.` and stops.
   In a worktree it symlinks `.env.local`, generates `.env.dev.local`, and
   clones the database on first run (about a minute).

2. **Check Redis ownership.** Run:

       lsof -nP -iTCP:6379 -sTCP:LISTEN -F c | sed -n 's/^c//p' | sort -u

   Expect a Docker process. If it shows `redis-server`, Homebrew's Redis has
   the port and the container cannot bind. Fix with `brew services stop redis`
   then `docker compose up -d`. Do not work around it by changing the port.

3. **Start the Symfony server** from the current checkout:

       symfony server:start -d

   The CLI picks a free port and prints the URL. Two worktrees get two ports;
   read the URL rather than assuming 8000.

4. **Start Encore:** `npm run dev-server`.

5. **Health check:** request the printed URL and confirm 200.

## Stop

`symfony server:stop` for this checkout only.

**Never run `docker compose down`.** The database, Redis and Adminer
containers are shared by every worktree. Stop them only when the user asks.

## Notes

- `composer install` and `npm install` are per-worktree; `vendor/` and
  `node_modules/` are not shared.
- Each worktree has its own database, so `doctrine:migrations:migrate` there
  cannot affect another worktree.
- Adminer is on http://localhost:8081.
