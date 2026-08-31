#!/bin/sh
# After a push, block until CI reports, so a PR is never called ready on faith.
# Always exits 0: a red run is information to act on, not an action to block.
set -u

if [ -z "${CC_HOOK_BRANCH:-}" ]; then
    # -E for portable alternation (BSD sed's BRE mode lacks GNU's `\|`).
    command=$(sed -nE 's/.*"command"[[:space:]]*:[[:space:]]*"((\\.|[^"\\])*)".*/\1/p' | sed 's/\\"/"/g; s/\\\\/\\/g')
    printf '%s' "$command" | grep -Eq "(^|[;&|]|&&)[[:space:]]*git([[:space:]]+-[^[:space:]]+)*[[:space:]]+push([[:space:]]|\$)" || exit 0
fi

cd "$(git rev-parse --show-toplevel)" || exit 0

branch=${CC_HOOK_BRANCH:-$(git rev-parse --abbrev-ref HEAD)}
[ "$branch" != HEAD ] || exit 0

gh pr view "$branch" --json url -q .url >/dev/null 2>&1 || exit 0

printf 'Watching CI for %s...\n' "$branch"
gh pr checks "$branch" --watch 2>&1 || true
exit 0
