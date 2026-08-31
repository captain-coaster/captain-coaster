#!/bin/sh
set -u
. "$(dirname "$0")/assert.sh"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
A="$ROOT/AGENTS.md"

assert_eq "the false integration-test claim is gone" \
    "$(grep -c 'No .KernelTestCase' "$A" || true)" "0"
assert_eq "the Badge editorial aside is gone" \
    "$(grep -c 'candidate for a full refactor' "$A" || true)" "0"
assert_eq "the manual server-start prose is gone" \
    "$(grep -c 'symfony server:start' "$A" || true)" "0"

assert_eq "a Git workflow section exists" "$(grep -c '^## Git workflow' "$A")" "1"
assert_eq "a Continuous Integration section exists" "$(grep -c '^## Continuous Integration' "$A")" "1"
assert_eq "a Security section exists" "$(grep -c '^## Security' "$A")" "1"

assert_eq "the tooling is documented" "$(grep -c 'bin/worktree' "$A")" "3"
assert_eq "the ranking constants were kept" "$(grep -c 'MIN_DUELS = 400' "$A")" "1"
assert_eq "the naming conventions were kept" "$(grep -c '^### Naming conventions' "$A")" "1"

assert_eq "CLAUDE.md is still a symlink" "$(readlink "$ROOT/CLAUDE.md")" "AGENTS.md"
assert_done
