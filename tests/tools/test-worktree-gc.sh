#!/bin/sh
set -u
. "$(dirname "$0")/assert.sh"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WT="$ROOT/bin/worktree"

# --- Dry run assertions -------------------------------------------------
# Safe to run directly against the real repo: cmd_gc's dry-run path never
# mutates worktree, branch, or database state now that `git worktree prune`
# is gated behind --yes.

SCRATCH="$ROOT/.claude/worktrees/gctest"
git -C "$ROOT" worktree add -f --detach "$SCRATCH" HEAD >/dev/null 2>&1

assert_eq "dry run reports what it would do" \
    "$("$WT" gc | grep -c 'Re-run with --yes')" "1"

assert_eq "dry run removes nothing" \
    "$(git -C "$ROOT" worktree list | grep -c 'gctest')" "1"

assert_eq "gc never proposes removing the main checkout" \
    "$("$WT" gc | grep -c "^Would remove $ROOT (")" "0"

git -C "$ROOT" worktree remove --force "$SCRATCH" >/dev/null 2>&1

# --- Destructive assertion -----------------------------------------------
# `gc --yes` calls `git worktree prune`, which acts on EVERY prunable
# registration in the repository it runs against — not just this test's own
# scratch entry. Running it against $ROOT would remove any other real
# prunable registration that happens to exist on this machine at the moment
# the test runs (this is exactly how a prior run of this test destroyed real
# worktree/branch/database state). To make this assertion structurally
# incapable of touching real state, it runs inside a throwaway clone:
# worktree registrations live under a repository's own .git directory and
# are never transferred by clone/fetch/push, so a fresh clone starts with
# zero worktrees and cannot enumerate the real repo's real worktrees — by
# construction, not by luck.
CLONEDIR=$(mktemp -d)
CLONE="$CLONEDIR/repo"
git clone -q "$ROOT" "$CLONE" >/dev/null 2>&1

CLONE_SCRATCH="$CLONE/gctest"
git -C "$CLONE" worktree add -f --detach "$CLONE_SCRATCH" HEAD >/dev/null 2>&1
# Removing the directory directly (not via `git worktree remove`) leaves the
# clone's own admin metadata behind — that mismatch is what makes
# `git worktree list --porcelain` report the entry as prunable.
rm -rf "$CLONE_SCRATCH"

( cd "$CLONE" && "$WT" gc --yes >/dev/null 2>&1 )

assert_eq "gc --yes prunes a stale registration" \
    "$(git -C "$CLONE" worktree list --porcelain | grep -c '^prunable')" \
    "0"

rm -rf "$CLONEDIR"

assert_done
