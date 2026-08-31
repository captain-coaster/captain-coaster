# Pure helpers shared by bin/worktree and bin/db.
# Every function writes its result to stdout and has no side effects,
# so the whole file is unit-testable without Docker or a checkout.

# Absolute path of the main checkout, from anywhere in any worktree.
wt_main_checkout() {
    dirname "$(git rev-parse --path-format=absolute --git-common-dir)"
}

# Directory name -> database-safe slug.
wt_slug() {
    printf '%s' "$1" \
        | tr '[:upper:]' '[:lower:]' \
        | sed -e 's/[^a-z0-9][^a-z0-9]*/_/g' -e 's/^_//' -e 's/_$//'
}

wt_db_name() {
    _slug=$(wt_slug "$1")
    [ -n "$_slug" ] || return 1
    printf 'captain_%s' "$_slug"
}

# 1..15. cksum is POSIX and gives a CRC-32, so no external dependency.
# Collisions only mean two worktrees share a Redis database; nothing corrupts.
wt_redis_index() {
    _slug=$(wt_slug "$1")
    [ -n "$_slug" ] || return 1
    _sum=$(printf '%s' "$_slug" | cksum | cut -d' ' -f1)
    printf '%s' "$((1 + _sum % 15))"
}

# Replace the database segment of a Doctrine DSN, keeping any query string.
wt_dsn_with_db() {
    printf '%s' "$1" | sed -E "s#/[^/?]+(\\?|\$)#/$2\\1#"
}

# Replace the trailing Redis database index, or append one if absent.
wt_redis_url_with_index() {
    case "$1" in
        */[0-9]|*/[0-9][0-9]) printf '%s' "$1" | sed -E "s#/[0-9]+\$#/$2#" ;;
        *) printf '%s/%s' "$1" "$2" ;;
    esac
}
