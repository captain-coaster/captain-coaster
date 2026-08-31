#!/bin/sh
# Provisions the current worktree and reports stale ones. Never destructive.
set -u
cd "$(git rev-parse --show-toplevel)" 2>/dev/null || exit 0

bin/worktree setup 2>/dev/null | grep -v '^Main checkout' || true

stale=$(bin/worktree status --stale-only 2>/dev/null | wc -l | tr -d ' ')
if [ "$stale" -gt 0 ]; then
    printf '%s stale worktree(s) — run `bin/worktree gc` to review.\n' "$stale"
fi
exit 0
