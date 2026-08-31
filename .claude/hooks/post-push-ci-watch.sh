#!/bin/sh
# After a push, block until CI reports, so a PR is never called ready on faith.
# Always exits 0: a red run is information to act on, not an action to block.
set -u

# Same gate as the pre-commit hook: the Bash matcher is not selective on its own.
# CC_HOOK_BRANCH lets the tests drive this without an actual push.
if [ -z "${CC_HOOK_BRANCH:-}" ]; then
    command=$(cat 2>/dev/null | python3 -c \
        'import json,sys; print(json.load(sys.stdin).get("tool_input",{}).get("command",""))' \
        2>/dev/null || true)
    case "$command" in
        *"git push"*) ;;
        *) exit 0 ;;
    esac
fi

cd "$(git rev-parse --show-toplevel)" || exit 0

branch=${CC_HOOK_BRANCH:-$(git rev-parse --abbrev-ref HEAD)}
[ "$branch" != HEAD ] || exit 0

gh pr view "$branch" --json url -q .url >/dev/null 2>&1 || exit 0

printf 'Watching CI for %s...\n' "$branch"
if command -v timeout >/dev/null 2>&1; then
    timeout 600 gh pr checks "$branch" --watch 2>&1 || true
else
    gh pr checks "$branch" --watch 2>&1 || true
fi
exit 0
