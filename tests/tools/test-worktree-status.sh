#!/bin/sh
set -u
. "$(dirname "$0")/assert.sh"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WT="$ROOT/bin/worktree"

assert_eq "status lists the main checkout" \
    "$("$WT" status | awk -F'\t' -v m="$ROOT" '$1 == m {print "listed"}')" \
    "listed"

assert_eq "status emits seven columns" \
    "$("$WT" status | head -1 | awk -F'\t' '{print NF}')" \
    "7"

assert_eq "the main checkout is never stale" \
    "$("$WT" status | awk -F'\t' -v m="$ROOT" '$1 == m {print $7}')" \
    "no"

assert_eq "--stale-only never lists the main checkout" \
    "$("$WT" status --stale-only | awk -F'\t' -v m="$ROOT" '$1 == m {print "leaked"}')" \
    ""

assert_eq "--stale-only output contains only stale rows" \
    "$("$WT" status --stale-only | awk -F'\t' '$7 != "yes" {print "bad"}' | head -1)" \
    ""

# Regression: tab is "IFS whitespace" per POSIX, so a plain `IFS=<tab>` read
# collapses an empty middle field instead of preserving it — a detached-HEAD
# worktree's empty branch field would shift prunable's value into branch.
# The awk-to-read pipe must use a delimiter that is never collapsed (\037).
assert_eq "a detached-HEAD row does not corrupt column count via field collapse" \
    "$(printf 'worktree /tmp/x\nHEAD abc123\n\n' \
        | awk -v OFS="$(printf '\037')" '
            /^worktree /  { path = substr($0, 10); branch = ""; prunable = "no" }
            /^branch /    { branch = substr($0, 8); sub("refs/heads/", "", branch) }
            /^prunable /  { prunable = "yes" }
            /^$/          { if (path != "") print path, branch, prunable; path = "" }
          ' \
        | { IFS="$(printf '\037')" read -r p b pr; printf 'b=[%s] pr=[%s]' "$b" "$pr"; })" \
    "b=[] pr=[no]"

assert_done
