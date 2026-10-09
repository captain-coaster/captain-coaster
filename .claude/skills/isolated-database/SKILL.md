---
name: isolated-database
description: Gives the current checkout its own copy of the shared `captain` database, or drops that copy. Use when the user asks for an isolated or copied database.
---

# Isolated database

`captain` is a clean mirror of production in the `db-captain` container, shared by every worktree: a migration run against it breaks the others.

## Create

1. Name the database `captain_<slug>`: the checkout's directory name, lowercased, anything outside `[a-z0-9_]` replaced by `_`.
2. Create it and clone `captain` into it, through a dump file (a piped `docker exec ... | ...` is refused by the command guard of a worktree session):

       docker exec db-captain mariadb -uroot -proot123 -e "CREATE DATABASE \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
       docker exec db-captain mariadb-dump --single-transaction --routines --events -uroot -proot123 captain > /tmp/captain_dump.sql
       docker exec -i db-captain mariadb -uroot -proot123 "$db" < /tmp/captain_dump.sql
       rm /tmp/captain_dump.sql

3. Write `.env.dev.local`, which Symfony loads after `.env.local`:

       DATABASE_URL="mysql://root:root123@127.0.0.1:3306/$db?serverVersion=11.8.0-MariaDB&charset=utf8mb4"

4. Done when `php bin/console debug:dotenv DATABASE_URL` shows `captain_<slug>`.

## Drop

1. `docker exec db-captain mariadb -uroot -proot123 -e "DROP DATABASE \`$db\`"`, with `$db` named as in Create. `captain` itself is never dropped.
2. Delete `.env.dev.local`.
3. Done when `debug:dotenv DATABASE_URL` shows `captain` again.
