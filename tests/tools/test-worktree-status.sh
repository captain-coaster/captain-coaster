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

assert_done
