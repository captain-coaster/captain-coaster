# Minimal POSIX assertion harness. Source it, call assert_eq, end with assert_done.
# shellcheck and bats are not installed on this project; this is deliberate and sufficient.
assert_failures=0

assert_eq() {
    if [ "$2" = "$3" ]; then
        printf 'ok   %s\n' "$1"
    else
        printf 'FAIL %s\n  expected: [%s]\n  actual:   [%s]\n' "$1" "$3" "$2" >&2
        assert_failures=$((assert_failures + 1))
    fi
}

assert_done() {
    if [ "$assert_failures" -gt 0 ]; then
        printf '\n%s assertion(s) failed\n' "$assert_failures" >&2
        exit 1
    fi
    printf '\nall assertions passed\n'
    exit 0
}
