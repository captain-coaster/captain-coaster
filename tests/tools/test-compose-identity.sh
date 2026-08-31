#!/bin/sh
# Verifies the Compose stack has a stable identity and that Docker owns Redis.
set -u
. "$(dirname "$0")/assert.sh"

assert_eq "compose project name is pinned" \
    "$(docker compose config --format json 2>/dev/null \
        | python3 -c 'import json,sys; print(json.load(sys.stdin)["name"])' 2>/dev/null)" \
    "captain-coaster"

assert_eq "redis container is named" \
    "$(docker ps --filter name=redis-captain --format '{{.Names}}')" \
    "redis-captain"

assert_eq "port 6379 is not served by Homebrew redis" \
    "$(lsof -nP -iTCP:6379 -sTCP:LISTEN -F c 2>/dev/null | sed -n 's/^c//p' | sort -u | grep -c '^redis-server$')" \
    "0"

assert_done
