# Pure helpers shared by bin/worktree and bin/db.
# Every function writes its result to stdout and has no side effects,
# so the whole file is unit-testable without Docker or a checkout.

# Absolute path of the main checkout, from anywhere in any worktree.
wt_main_checkout() {
    dirname "$(git rev-parse --path-format=absolute --git-common-dir)"
}

# Directory name -> database-safe slug.
wt_slug() {
    local _slug_out
    _slug_out=$(printf '%s' "$1" \
        | tr '[:upper:]' '[:lower:]' \
        | sed -e 's/[^a-z0-9][^a-z0-9]*/_/g' -e 's/^_//' -e 's/_$//')
    [ -n "$_slug_out" ] || return 1
    printf '%s' "$_slug_out"
}

wt_db_name() {
    local _slug
    _slug=$(wt_slug "$1")
    [ -n "$_slug" ] || return 1
    printf 'captain_%s' "$_slug"
}

# 1..15. cksum is POSIX and gives a CRC-32, so no external dependency.
# Collisions only mean two worktrees share a Redis database; nothing corrupts.
wt_redis_index() {
    local _slug _sum
    _slug=$(wt_slug "$1")
    [ -n "$_slug" ] || return 1
    _sum=$(printf '%s' "$_slug" | cksum | cut -d' ' -f1)
    printf '%s' "$((1 + _sum % 15))"
}

# Replace the database segment of a Doctrine DSN, keeping any query string.
# The query is split off first: a literal '?' must be percent-encoded inside
# userinfo per RFC 3986, so the first '?' always starts the query.
wt_dsn_with_db() {
    local _dsn_query _dsn_base
    _dsn_query=''
    case "$1" in
        *\?*) _dsn_query="?${1#*\?}"; _dsn_base="${1%%\?*}" ;;
        *)    _dsn_base="$1" ;;
    esac
    printf '%s/%s%s' "${_dsn_base%/*}" "$2" "$_dsn_query"
}

# Replace the trailing Redis database index, or append one if absent.
wt_redis_url_with_index() {
    local _redis_query _redis_base
    _redis_query=''
    case "$1" in
        *\?*) _redis_query="?${1#*\?}"; _redis_base="${1%%\?*}" ;;
        *)    _redis_base="$1" ;;
    esac
    case "$_redis_base" in
        */[0-9]|*/[0-9][0-9]) _redis_base="${_redis_base%/*}" ;;
    esac
    printf '%s/%s%s' "$_redis_base" "$2" "$_redis_query"
}
