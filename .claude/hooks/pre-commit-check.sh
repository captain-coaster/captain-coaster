#!/bin/sh
# Blocks `git commit` when staged files fail style or the suite fails.
# Exit 2 is the only blocking exit code Claude Code honours for PreToolUse.
set -u

# Claude Code passes the tool input as JSON on stdin. The Bash matcher fires on
# every Bash call, so this hook must decide for itself whether it applies:
# without this gate the full suite would run on every command with a dirty index.
command=$(cat 2>/dev/null | python3 -c \
    'import json,sys; print(json.load(sys.stdin).get("tool_input",{}).get("command",""))' \
    2>/dev/null || true)
case "$command" in
    *"git commit"*) ;;
    *) exit 0 ;;
esac

cd "$(git rev-parse --show-toplevel)" || exit 0

staged=$(git diff --cached --name-only --diff-filter=ACM)
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
