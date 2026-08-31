#!/bin/sh
set -u
. "$(dirname "$0")/assert.sh"
. "$(dirname "$0")/../../bin/lib/worktree-lib.sh"

assert_eq "slug lowercases and replaces dashes" "$(wt_slug 'db-perf-optim')" "db_perf_optim"
assert_eq "slug collapses runs of separators" "$(wt_slug 'a--b__c')" "a_b_c"
assert_eq "slug strips leading and trailing separators" "$(wt_slug '-lead-trail-')" "lead_trail"
assert_eq "slug folds uppercase" "$(wt_slug 'Feature/Tailwind')" "feature_tailwind"

assert_eq "db name is prefixed" "$(wt_db_name 'db-perf-optim')" "captain_db_perf_optim"

assert_eq "redis index is stable for a slug" \
    "$(wt_redis_index 'db-perf-optim')" "$(wt_redis_index 'db-perf-optim')"
assert_eq "redis index is in range for a sample slug" \
    "$(i=$(wt_redis_index 'db-perf-optim'); [ "$i" -ge 1 ] && [ "$i" -le 15 ] && echo in-range)" \
    "in-range"
assert_eq "redis index ignores separator style" \
    "$(wt_redis_index 'db-perf-optim')" "$(wt_redis_index 'db_perf_optim')"

assert_eq "dsn rewrite preserves the query string" \
    "$(wt_dsn_with_db 'mysql://root:pw@127.0.0.1:3306/captain?serverVersion=11.8.0-MariaDB' 'captain_x')" \
    "mysql://root:pw@127.0.0.1:3306/captain_x?serverVersion=11.8.0-MariaDB"
assert_eq "dsn rewrite works without a query string" \
    "$(wt_dsn_with_db 'mysql://root:pw@127.0.0.1:3306/captain' 'captain_x')" \
    "mysql://root:pw@127.0.0.1:3306/captain_x"
assert_eq "dsn rewrite leaves host and credentials alone" \
    "$(wt_dsn_with_db 'mysql://root:root123@127.0.0.1:3306/captain' 'captain_db_perf_optim')" \
    "mysql://root:root123@127.0.0.1:3306/captain_db_perf_optim"

assert_eq "redis url index is replaced" \
    "$(wt_redis_url_with_index 'redis://localhost:6379/0' 7)" "redis://localhost:6379/7"
assert_eq "redis url index is appended when absent" \
    "$(wt_redis_url_with_index 'redis://localhost:6379' 7)" "redis://localhost:6379/7"

assert_eq "empty input is rejected" "$(wt_db_name '' 2>/dev/null; echo "rc=$?")" "rc=1"

assert_done
