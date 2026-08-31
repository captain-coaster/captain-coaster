#!/bin/sh
# Blocks `git commit` when staged files fail style or the suite fails.
# Exit 2 is the only blocking exit code Claude Code honours for PreToolUse.
set -u

# The Bash matcher fires on every Bash call, so this hook decides for itself
# whether it applies. Extraction is done with sed rather than a JSON parser:
# Claude Code controls this payload's shape (a flat {"tool_input":{"command":"..."}}
# object), so a targeted regex is reliable here and has no external dependency
# to fail open on.
# -E (extended regex) is required for the alternation below: BSD sed's
# default BRE mode doesn't support GNU's `\|` extension, and -E works
# identically on both BSD (macOS) and GNU sed.
command=$(sed -nE 's/.*"command"[[:space:]]*:[[:space:]]*"((\\.|[^"\\])*)".*/\1/p' | sed 's/\\"/"/g; s/\\\\/\\/g')

# Anchored to command position (start of string, or after a separator) so a
# command whose TEXT merely mentions "git commit" (e.g. `echo "git commit"`)
# does not match, while `git -C dir commit ...` still does.
is_git_subcommand() {
    printf '%s' "$command" | grep -Eq "(^|[;&|]|&&)[[:space:]]*git([[:space:]]+-[^[:space:]]+)*[[:space:]]+$1([[:space:]]|\$)"
}

is_git_subcommand commit || exit 0

cd "$(git rev-parse --show-toplevel)" || exit 0

# `git commit -a`/`--all`/`-am` commits the working tree, not the index, so
# the staged-files check must fall back to the working-tree diff in that case
# or the gate silently sees nothing and exits 0 on a real commit.
if printf '%s' "$command" | grep -Eq -- '(^|[[:space:]])(-a|--all|-am|-a[a-z]*m)([[:space:]]|$)'; then
    staged=$(git diff --name-only --diff-filter=ACM)
else
    staged=$(git diff --cached --name-only --diff-filter=ACM)
fi
[ -n "$staged" ] || exit 0

fail() { printf '%s\n' "$1" >&2; printf '%s\n' "$2" >&2; exit 2; }

php_files=$(printf '%s\n' "$staged" | grep '\.php$' || true)
if [ -n "$php_files" ]; then
    out=$(printf '%s\n' "$php_files" | xargs vendor/bin/php-cs-fixer fix --dry-run --diff 2>&1) \
        || fail "php-cs-fixer found style violations in staged files:" "$out"
fi

js_files=$(printf '%s\n' "$staged" | grep '^assets/.*\.js$' || true)
if [ -n "$js_files" ]; then
    out=$(printf '%s\n' "$js_files" | xargs npx prettier --check 2>&1) \
        || fail "prettier found formatting violations in staged files:" "$out"
fi

out=$(vendor/bin/phpunit 2>&1) || fail "PHPUnit failed:" "$out"

exit 0
