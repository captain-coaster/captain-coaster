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

# Identical file -> replaced with a symlink.
SCRATCH2="$ROOT/.claude/worktrees/selftest2"
git -C "$ROOT" worktree add -f --detach "$SCRATCH2" HEAD >/dev/null 2>&1
cp "$MAIN/.env.local" "$SCRATCH2/.env.local"
export WT_SKIP_DB_CLONE=1
(cd "$SCRATCH2" && "$WT" setup >/dev/null 2>&1)
assert_eq "identical .env.local is replaced with a symlink" \
    "$(readlink "$SCRATCH2/.env.local")" "$MAIN/.env.local"
"$ROOT/bin/db" drop captain_selftest2 --yes >/dev/null 2>&1
git -C "$ROOT" worktree remove --force "$SCRATCH2" >/dev/null 2>&1

# Diverged file -> moved aside, never deleted, then symlinked.
SCRATCH3="$ROOT/.claude/worktrees/selftest3"
git -C "$ROOT" worktree add -f --detach "$SCRATCH3" HEAD >/dev/null 2>&1
printf 'DATABASE_URL=mysql://diverged\n' > "$SCRATCH3/.env.local"
(cd "$SCRATCH3" && "$WT" setup >/dev/null 2>&1)
assert_eq "diverged .env.local is backed up, not deleted" \
    "$(ls "$SCRATCH3"/.env.local.orphan-* 2>/dev/null | wc -l | tr -d ' ')" "1"
assert_eq "diverged .env.local is still symlinked after backup" \
    "$(readlink "$SCRATCH3/.env.local")" "$MAIN/.env.local"
"$ROOT/bin/db" drop captain_selftest3 --yes >/dev/null 2>&1
git -C "$ROOT" worktree remove --force "$SCRATCH3" >/dev/null 2>&1

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
