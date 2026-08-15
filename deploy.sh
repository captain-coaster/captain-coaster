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
    git pull origin main
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

# Function to run the full deploy in the right order, skipping steps that
# the pulled changes don't actually touch. set -e (top of this script)
# means any failure here stops the whole sequence immediately — and since
# disable_maintenance only runs at the very end, maintenance mode stays on
# if anything fails, rather than exposing a half-deployed site.
#
# No DB backup step here on purpose — that's handled by dedicated backup
# tooling outside this script, not duplicated here.
full_deploy() {
    local old_commit
    old_commit=$(git rev-parse HEAD)

    enable_maintenance
    update_code

    local changed
    changed=$(git diff --name-only "$old_commit" HEAD)

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

    if echo "$changed" | grep -q '^migrations/'; then
        run_migrations
    else
        log "no new migrations, skipping migrate"
    fi

    # cheap regardless of what changed — always safe to run
    clear_cache
    warm_cache
    reload_php_fpm
    verify_deployment
    disable_maintenance

    success "Deploy complete ($(echo "$changed" | wc -l | tr -d ' ') files changed)"
}

# Function to rollback deployment
rollback() {
    error "Starting rollback process..."
    
    # Ask about migration rollback
    echo ""
    read -p "Do you want to rollback database migrations? (y/N): " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        warning "Rolling back migrations..."
        php bin/console doctrine:migrations:migrate prev --no-interaction
        success "Migrations rolled back"
    fi
    
    # Reset to previous git commit
    warning "Resetting code to previous commit..."
    git reset --hard HEAD~1
    

    
    # Clear cache
    clear_cache
    
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