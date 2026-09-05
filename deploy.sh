#!/bin/bash

# Captain Coaster Safe Deployment Script
# This script demonstrates best practices for deploying with maintenance mode

set -e
# Without this, a failure in the middle of a pipe is invisible to `set -e`
# — only the last command's exit code is checked, so e.g. `foo | gzip`
# would report success even if `foo` failed and gzip just compressed
# nothing. Cheap insurance for any pipe used in this script, now or later.
set -o pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
# Commit HEAD pointed to right before the last deploy's update_code ran.
# rollback() reads this to know exactly how far to undo, instead of
# assuming a deploy only ever pulls a single commit.
STATE_FILE="$PROJECT_DIR/.deploy_previous_commit"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}✅ $1${NC}"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

error() {
    echo -e "${RED}❌ $1${NC}"
}

# ERR trap for full_deploy()/rollback(): `set -e` would otherwise abort
# silently, leaving the site in maintenance mode with no indication why.
# Staying in maintenance on failure is deliberate (see full_deploy's
# comment) — this only makes that state visible to whoever is watching.
on_deploy_error() {
    error "Operation failed partway through — site is still in maintenance mode. Fix the underlying issue, then run '$0 maintenance off' once it's safe."
}

# Function to reload nginx so its file-existence cache picks up the
# maintenance.html toggle immediately. Without this, nginx's
# open_file_cache (configured VPS-wide, not something this script owns)
# can keep serving a stale cached result — observed in practice: enabling
# maintenance takes effect immediately, but disabling it silently kept
# serving 503s until nginx was reloaded.
reload_nginx() {
    if sudo systemctl reload nginx 2>/dev/null; then
        success "nginx reloaded"
    elif sudo service nginx reload 2>/dev/null; then
        success "nginx reloaded"
    else
        warning "Could not reload nginx automatically. Run manually: sudo systemctl reload nginx"
    fi
}

# Function to enable maintenance mode
enable_maintenance() {
    log "Enabling maintenance mode..."
    cp "$PROJECT_DIR/maintenance.html" "$PROJECT_DIR/public/maintenance.html"
    reload_nginx
    success "Maintenance mode enabled"
}

# Function to disable maintenance mode
disable_maintenance() {
    log "Disabling maintenance mode..."
    rm -f "$PROJECT_DIR/public/maintenance.html"
    reload_nginx
    success "Maintenance mode disabled"
}

# Function to update code
update_code() {
    log "Updating code from repository..."
    git fetch origin
    if ! git merge --ff-only origin/main; then
        # Local branch diverged from origin/main — e.g. history was
        # rewritten upstream (a squash/amend + force-push). Resync to
        # match origin rather than leaving the deploy stuck. Safe here
        # specifically because this checkout only ever tracks main and
        # is never the place local work is authored.
        warning "Local branch diverged from origin/main, resetting to match"
        git reset --hard origin/main
    fi
    success "Code updated"
}

# Function to install PHP dependencies
install_dependencies() {
    log "Installing/updating PHP dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
    success "PHP dependencies updated"
}

# Function to install Node.js dependencies
install_node_dependencies() {
    log "Installing/updating Node.js dependencies..."
    
    # Only install if package-lock.json exists
    if [ ! -f "package-lock.json" ]; then
        warning "package-lock.json not found, skipping Node.js dependencies"
        return 0
    fi
    
    # Check if npm is available
    if ! command -v npm &> /dev/null; then
        error "npm is not installed or not in PATH"
        return 1
    fi
    
    npm ci --production=false
    success "Node.js dependencies updated"
}

# Function to build production assets
build_assets() {
    log "Building production assets with Webpack Encore..."
    
    # Check if npm is available
    if ! command -v npm &> /dev/null; then
        error "npm is not installed or not in PATH"
        return 1
    fi
    
    # Check if node_modules exists
    if [ ! -d "node_modules" ]; then
        warning "node_modules directory not found. Run 'install-node' first."
        return 1
    fi
    
    # Clean and build
    npm run clean
    npm run build
    
    success "Production assets built successfully"
}

# Function to run database migrations
run_migrations() {
    log "Running database migrations..."
    php bin/console doctrine:migrations:migrate
    success "Database migrations completed"
}

# Function to clear cache
clear_cache() {
    log "Clearing application cache..."
    php bin/console cache:clear --env=prod --no-debug
    success "Cache cleared"
}

# Function to warm up cache
warm_cache() {
    log "Warming up cache..."
    php bin/console cache:warmup --env=prod
    success "Cache warmed up"
}

# Function to reload PHP-FPM to clear OPcache
reload_php_fpm() {
    log "Reloading PHP-FPM to clear OPcache..."
    if sudo systemctl reload php8.5-fpm 2>/dev/null; then
        success "PHP-FPM reloaded"
    elif sudo service php8.5-fpm reload 2>/dev/null; then
        success "PHP-FPM reloaded"
    else
        warning "Could not reload PHP-FPM automatically. Run manually: sudo systemctl reload php8.5-fpm"
    fi
}

# Function to restart the messenger worker (systemd service consuming the
# async transport). A long-running CLI worker doesn't benefit from PHP-FPM's
# OPcache reload above — without this, it keeps running whatever code was
# loaded when it last started, indefinitely.
restart_messenger_worker() {
    log "Restarting messenger worker..."
    if sudo systemctl restart captain-messenger.service 2>/dev/null; then
        success "Messenger worker restarted"
    else
        warning "Could not restart messenger worker automatically. Run manually: sudo systemctl restart captain-messenger.service"
    fi
}

# Function to verify deployment
verify_deployment() {
    log "Verifying deployment..."
    
    # Check if the application is responding
    if php bin/console about > /dev/null 2>&1; then
        success "Application is responding correctly"
    else
        error "Application verification failed"
        return 1
    fi
}

# Installs/builds only what $changed actually touched. Shared by
# full_deploy() and rollback() so the detection patterns can't drift
# between the forward and backward direction of the same operation.
apply_dependency_changes() {
    local changed="$1"

    if echo "$changed" | grep -q '^composer\.lock$'; then
        install_dependencies
    else
        log "composer.lock unchanged, skipping install"
    fi

    if echo "$changed" | grep -q '^package-lock\.json$'; then
        install_node_dependencies
    else
        log "package-lock.json unchanged, skipping install-node"
    fi

    # package-lock.json is included here too, not just assets/ and
    # package.json: a changed lockfile means node_modules content changed,
    # which can change compiled output (e.g. a bundled polyfill version)
    # even when no source file under assets/ was touched.
    if echo "$changed" | grep -qE '^(assets/|package\.json$|package-lock\.json$|webpack\.config\.js$)'; then
        build_assets
    else
        log "no asset changes, skipping build"
    fi
}

# Closing sequence shared by full_deploy() and rollback() — cheap regardless
# of what changed, always safe to run.
finalize_deploy() {
    clear_cache
    warm_cache
    reload_php_fpm
    restart_messenger_worker
    verify_deployment
    disable_maintenance
}

# Function to run the full deploy in the right order, skipping steps that
# the pulled changes don't actually touch. set -e (top of this script)
# means any failure here stops the whole sequence immediately — and since
# disable_maintenance only runs at the very end, maintenance mode stays on
# if anything fails, rather than exposing a half-deployed site.
#
# No DB backup step here on purpose — that's handled by dedicated backup
# tooling outside this script, not duplicated here.
full_deploy() {
    trap on_deploy_error ERR

    if [ -z "${DEPLOY_CONTINUE:-}" ]; then
        # First pass: pull, then hand off to the script file we just
        # pulled. Bash already parsed this function's body into memory
        # before update_code runs — a git pull mid-script does NOT
        # hot-swap that, so without this re-exec, every step below would
        # silently keep running pre-pull logic even after deploy.sh
        # itself changed (observed in practice: a fix to the asset-build
        # condition was ignored on the deploy that pulled it in).
        local old_commit
        old_commit=$(git rev-parse HEAD)
        # Persisted so rollback() knows exactly what to undo, even across
        # a separate invocation of this script.
        echo "$old_commit" > "$STATE_FILE"
        enable_maintenance
        update_code
        DEPLOY_OLD_COMMIT="$old_commit" DEPLOY_CONTINUE=1 exec "$0" deploy
    fi

    # Second pass: fresh process, this file read fresh from disk — every
    # function below is guaranteed to be the version that was just pulled.
    local changed
    changed=$(git diff --name-only "$DEPLOY_OLD_COMMIT" HEAD)

    apply_dependency_changes "$changed"

    if echo "$changed" | grep -q '^migrations/'; then
        run_migrations
    else
        log "no new migrations, skipping migrate"
    fi

    finalize_deploy

    success "Deploy complete ($(echo "$changed" | wc -l | tr -d ' ') files changed)"
}

# Function to rollback deployment. Mirrors full_deploy(): maintenance mode
# wraps the mutating part, the same $changed-driven install/build steps
# run, and the closing sequence is shared — so a rollback leaves the site
# in as consistent a state as a forward deploy would, instead of just
# moving the git HEAD.
rollback() {
    trap on_deploy_error ERR

    if [ -z "${ROLLBACK_CONTINUE:-}" ]; then
        error "Starting rollback process..."

        # Undo exactly what the last deploy pulled, not just one commit —
        # a deploy's update_code can fast-forward through several commits
        # at once. Falls back to HEAD~1 if there's no recorded state (e.g.
        # first run after this script was updated, or code moved outside
        # deploy.sh) or the recorded commit no longer exists locally.
        local target_commit=""
        if [ -f "$STATE_FILE" ]; then
            target_commit=$(cat "$STATE_FILE")
        fi
        if [ -z "$target_commit" ] || ! git cat-file -e "${target_commit}^{commit}" 2>/dev/null; then
            warning "No valid recorded pre-deploy commit found, falling back to HEAD~1"
            target_commit=$(git rev-parse HEAD~1)
        fi

        # Diff of the commit(s) we're about to undo. Same file list
        # regardless of diff direction, so it doubles as the "what needs
        # reinstalling after reset" check, same as full_deploy's $changed.
        local changed
        changed=$(git diff --name-only "$target_commit" HEAD)

        # Count migration files actually added by the commit(s) being
        # undone — `migrate prev` only steps back one version at a time,
        # so a commit that added several needs several calls, not one.
        local migration_count
        migration_count=$(git diff --name-status "$target_commit" HEAD -- migrations/ | grep -c '^A' || true)

        local do_migration_rollback="n"
        if [ "$migration_count" -gt 0 ]; then
            if [ -t 0 ]; then
                echo ""
                read -p "Do you want to rollback $migration_count migration(s)? (y/N): " -n 1 -r
                echo ""
                do_migration_rollback="$REPLY"
            else
                warning "Non-interactive shell: skipping migration rollback prompt. Run 'php bin/console doctrine:migrations:migrate prev' manually $migration_count time(s) if needed."
            fi
        else
            log "No new migrations in this commit, skipping migration rollback prompt"
        fi

        # Only now, once every decision that needs a human is made, does
        # the site actually need to go down — no reason to hold maintenance
        # mode open while waiting on the prompt above.
        enable_maintenance

        # Migrations must be rolled back BEFORE the reset, while the
        # migration classes from the commit(s) being undone still exist.
        if [[ $do_migration_rollback =~ ^[Yy]$ ]]; then
            warning "Rolling back $migration_count migration(s)..."
            for ((i = 0; i < migration_count; i++)); do
                php bin/console doctrine:migrations:migrate prev --no-interaction
            done
            success "Migrations rolled back"
        fi

        warning "Resetting code to previous commit..."
        git reset --hard "$target_commit"
        success "Code reset"

        # Re-exec for the same reason full_deploy() does after update_code:
        # the git reset above just changed deploy.sh on disk, but this
        # process already has the old (being-undone) function bodies
        # parsed in memory. Everything from here on must run the restored
        # version of this script.
        ROLLBACK_CHANGED="$changed" ROLLBACK_CONTINUE=1 exec "$0" rollback
    fi

    # Second pass: fresh process, this file read fresh from disk after the
    # reset — every function below is guaranteed to be the restored version.
    local changed="$ROLLBACK_CHANGED"

    apply_dependency_changes "$changed"
    finalize_deploy

    success "Rollback completed"
}



# Show usage information
show_usage() {
    echo "Captain Coaster Deployment Tools"
    echo ""
    echo "Usage: $0 [COMMAND]"
    echo ""
    echo "Commands:"
    echo "  deploy       Full deploy: pull, install/build/migrate only what changed,"
    echo "               cache, verify. Stops on first failure, leaving maintenance"
    echo "               mode ON so nothing half-broken goes live."
    echo "  maintenance  [on|off|status] - Control maintenance mode"
    echo "  update       Pull latest code from repository"
    echo "  install      Install/update PHP dependencies"
    echo "  install-node Install/update Node.js dependencies (only if package-lock.json exists)"
    echo "  assets       Build production assets with Webpack Encore"
    echo "  migrate      Run database migrations"
    echo "  cache        Clear and warm cache"
    echo "  messenger-restart  Restart the messenger worker (systemd)"
    echo "  verify       Verify deployment"
    echo "  rollback     Rollback code and optionally migrations"
    echo "  help         Show this help message"
    echo ""
    echo "Normal deploy:"
    echo "  $0 deploy"
    echo ""
    echo "Manual step-by-step (same steps 'deploy' runs, for when you want to"
    echo "watch/control each stage yourself):"
    echo "  $0 maintenance on"
    echo "  $0 update"
    echo "  $0 install"
    echo "  $0 install-node"
    echo "  $0 assets"
    echo "  $0 migrate"
    echo "  $0 cache"
    echo "  $0 messenger-restart"
    echo "  $0 verify"
    echo "  $0 maintenance off"
}

# Main script logic
case "${1:-help}" in
    "maintenance")
        case "${2:-status}" in
            "on") enable_maintenance ;;
            "off") disable_maintenance ;;
            "status")
                if [ -f "$PROJECT_DIR/public/maintenance.html" ]; then
                    echo "🔧 Maintenance mode is ENABLED"
                else
                    echo "✅ Maintenance mode is DISABLED"
                fi
                ;;
            *) echo "Usage: $0 maintenance [on|off|status]" ;;
        esac
        ;;
    "deploy")
        full_deploy
        ;;
    "update")
        update_code
        ;;
    "install")
        install_dependencies
        ;;
    "install-node")
        install_node_dependencies
        ;;
    "assets")
        build_assets
        ;;
    "migrate")
        run_migrations
        ;;
    "cache")
        clear_cache
        warm_cache
        reload_php_fpm
        ;;
    "messenger-restart")
        restart_messenger_worker
        ;;
    "verify")
        verify_deployment
        ;;
    "rollback")
        rollback
        ;;
    "help"|"--help"|"-h")
        show_usage
        ;;
    *)
        echo "❌ Unknown command: $1"
        echo ""
        show_usage
        exit 1
        ;;
esac