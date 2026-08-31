#!/bin/sh
set -u
. "$(dirname "$0")/assert.sh"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
H="$ROOT/.claude/hooks"

assert_eq "settings.json is valid json" \
    "$(python3 -c 'import json,sys; json.load(open(sys.argv[1])); print("valid")' "$ROOT/.claude/settings.json" 2>/dev/null)" \
    "valid"

assert_eq "both hooks are executable" \
    "$(for h in post-push-ci-watch session-start; do \
         [ -x "$H/$h.sh" ] || echo "missing:$h"; done)" \
    ""

assert_eq "post-push ignores a Bash call that is not a push" \
    "$(printf '{"tool_input":{"command":"ls -la"}}' | sh "$H/post-push-ci-watch.sh" >/dev/null 2>&1; echo "rc=$?")" \
    "rc=0"

assert_eq "session-start exits 0 even with no worktrees to report" \
    "$(sh "$H/session-start.sh" >/dev/null 2>&1; echo "rc=$?")" "rc=0"

assert_eq "post-push exits 0 when the branch has no PR" \
    "$(cd "$ROOT" && CC_HOOK_BRANCH=definitely-not-a-branch sh "$H/post-push-ci-watch.sh" >/dev/null 2>&1; echo "rc=$?")" \
    "rc=0"

assert_done
