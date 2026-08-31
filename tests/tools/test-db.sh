#!/bin/sh
set -u
. "$(dirname "$0")/assert.sh"
DB="$(dirname "$0")/../../bin/db"
TARGET=captain_selftest

assert_eq "drop refuses to touch the source database" \
    "$("$DB" drop captain >/dev/null 2>&1; echo "rc=$?")" "rc=1"

assert_eq "captain still exists after the refused drop" \
    "$("$DB" list | awk '$1 == "captain" {print "present"}')" "present"

"$DB" drop "$TARGET" --yes >/dev/null 2>&1
assert_eq "clone creates the target" \
    "$("$DB" clone "$TARGET" >/dev/null 2>&1; "$DB" list | awk -v t="$TARGET" '$1 == t {print "present"}')" \
    "present"

assert_eq "clone copies the schema, not just an empty database" \
    "$("$DB" list | awk -v t="$TARGET" '$1 == t && $3 > 0 {print "has-tables"}')" \
    "has-tables"

assert_eq "clone is idempotent" \
    "$("$DB" clone "$TARGET" 2>&1 >/dev/null; echo "rc=$?")" "rc=0"

assert_eq "drop removes a non-source database" \
    "$("$DB" drop "$TARGET" --yes >/dev/null 2>&1; "$DB" list | awk -v t="$TARGET" '$1 == t {print "present"}')" \
    ""

assert_done
