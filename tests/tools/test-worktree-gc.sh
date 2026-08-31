#!/bin/sh
set -u
. "$(dirname "$0")/assert.sh"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WT="$ROOT/bin/worktree"
SCRATCH="$ROOT/.claude/worktrees/gctest"

# A detached scratch worktree has no branch and therefore no PR, so it is not stale.
git -C "$ROOT" worktree add -f --detach "$SCRATCH" HEAD >/dev/null 2>&1

assert_eq "dry run reports what it would do" \
    "$("$WT" gc | grep -c 'Re-run with --yes')" "1"

assert_eq "dry run removes nothing" \
    "$(git -C "$ROOT" worktree list | grep -c 'gctest')" "1"

assert_eq "gc never proposes removing the main checkout" \
    "$("$WT" gc | grep -c "^Would remove $ROOT (")" "0"

git -C "$ROOT" worktree remove --force "$SCRATCH" >/dev/null 2>&1

assert_eq "gc --yes prunes a stale registration" \
    "$("$WT" gc --yes >/dev/null 2>&1; git -C "$ROOT" worktree list --porcelain | grep -c '^prunable')" \
    "0"

assert_done
