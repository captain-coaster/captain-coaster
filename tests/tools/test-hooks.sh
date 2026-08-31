#!/bin/sh
set -u
. "$(dirname "$0")/assert.sh"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
H="$ROOT/.claude/hooks"

assert_eq "settings.json is valid json" \
    "$(python3 -c 'import json,sys; json.load(open(sys.argv[1])); print("valid")' "$ROOT/.claude/settings.json" 2>/dev/null)" \
    "valid"

assert_eq "all three hooks are executable" \
    "$(for h in pre-commit-check post-push-ci-watch session-start; do \
         [ -x "$H/$h.sh" ] || echo "missing:$h"; done)" \
    ""

assert_eq "pre-commit ignores a Bash call that is not a commit" \
    "$(printf '{"tool_input":{"command":"ls -la"}}' | sh "$H/pre-commit-check.sh" >/dev/null 2>&1; echo "rc=$?")" \
    "rc=0"

assert_eq "post-push ignores a Bash call that is not a push" \
    "$(printf '{"tool_input":{"command":"ls -la"}}' | sh "$H/post-push-ci-watch.sh" >/dev/null 2>&1; echo "rc=$?")" \
    "rc=0"

assert_eq "session-start exits 0 even with no worktrees to report" \
    "$(sh "$H/session-start.sh" >/dev/null 2>&1; echo "rc=$?")" "rc=0"

assert_eq "post-push exits 0 when the branch has no PR" \
    "$(cd "$ROOT" && CC_HOOK_BRANCH=definitely-not-a-branch sh "$H/post-push-ci-watch.sh" >/dev/null 2>&1; echo "rc=$?")" \
    "rc=0"

# Pre-commit gate: a staged file that violates php-cs-fixer must block.
BAD="$ROOT/src/Service/GateProbe.php"
printf '<?php\nclass GateProbe { public function x() { return    1; } }\n' > "$BAD"
git -C "$ROOT" add "$BAD"
assert_eq "pre-commit blocks a style violation with exit 2" \
    "$(printf '{"tool_input":{"command":"git commit -m x"}}' \
        | sh "$H/pre-commit-check.sh" >/dev/null 2>&1; echo "rc=$?")" "rc=2"
git -C "$ROOT" reset -q "$BAD"
rm -f "$BAD"

assert_eq "pre-commit passes on a clean index" \
    "$(printf '{"tool_input":{"command":"git commit -m x"}}' \
        | sh "$H/pre-commit-check.sh" >/dev/null 2>&1; echo "rc=$?")" "rc=0"

assert_done
