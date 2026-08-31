#!/bin/sh
set -u
. "$(dirname "$0")/assert.sh"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WT="$ROOT/bin/worktree"
MAIN="$(dirname "$(git -C "$ROOT" rev-parse --path-format=absolute --git-common-dir)")"

assert_eq "setup is a no-op in the main checkout" \
    "$(cd "$MAIN" && "$WT" setup 2>&1 | head -1)" \
    "Main checkout, nothing to provision."

assert_eq "main checkout .env.local is still a real file, not a symlink" \
    "$([ -f "$MAIN/.env.local" ] && [ ! -L "$MAIN/.env.local" ] && echo untouched)" \
    "untouched"

# Provision a scratch worktree end to end.
SCRATCH="$ROOT/.claude/worktrees/selftest"
git -C "$ROOT" worktree add -f --detach "$SCRATCH" HEAD >/dev/null 2>&1
# Skip the clone: Task 3 covers it, and this test only asserts env provisioning.
export WT_SKIP_DB_CLONE=1
(cd "$SCRATCH" && "$WT" setup >/dev/null 2>&1)

assert_eq "worktree .env.local is a symlink to the main checkout" \
    "$(readlink "$SCRATCH/.env.local")" "$MAIN/.env.local"

assert_eq "override points at the worktree database" \
    "$(grep -c '^DATABASE_URL=.*captain_selftest' "$SCRATCH/.env.dev.local")" "1"

assert_eq "override sets a redis index in range" \
    "$(i=$(sed -n 's#^REDIS_URL=.*/\([0-9]\{1,\}\)$#\1#p' "$SCRATCH/.env.dev.local"); \
       [ "$i" -ge 1 ] && [ "$i" -le 15 ] && echo in-range)" \
    "in-range"

assert_eq "override carries no secrets beyond the two overridden keys" \
    "$(grep -c '^[A-Z]' "$SCRATCH/.env.dev.local")" "2"

assert_eq "second run is idempotent" \
    "$(cd "$SCRATCH" && "$WT" setup >/dev/null 2>&1; echo "rc=$?")" "rc=0"

BEFORE=$(md5 -q "$SCRATCH/.env.dev.local")
(cd "$SCRATCH" && "$WT" setup >/dev/null 2>&1)
assert_eq "second run does not rewrite the override" \
    "$(md5 -q "$SCRATCH/.env.dev.local")" "$BEFORE"

# Teardown.
"$ROOT/bin/db" drop captain_selftest --yes >/dev/null 2>&1
git -C "$ROOT" worktree remove --force "$SCRATCH" >/dev/null 2>&1

assert_done
