#!/bin/bash

# Captain Coaster Deployment Script

set -e

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_DIR"

PRE_DEPLOY_SHA=""

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

# Function to update code
update_code() {
    log "Updating code from repository..."
    PRE_DEPLOY_SHA=$(git rev-parse HEAD)
    git pull origin main
    success "Code updated (was: ${PRE_DEPLOY_SHA:0:8})"
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

    if [ ! -f "package-lock.json" ]; then
        warning "package-lock.json not found, skipping Node.js dependencies"
        return 0
    fi

    if ! command -v npm &> /dev/null; then
        error "npm is not installed or not in PATH"
        return 1
    fi

    npm ci --production=false
    success "Node.js dependencies updated"
}

# Function to build production assets
build_assets() {
    log "Building production assets with Vite..."

    if ! command -v npm &> /dev/null; then
        error "npm is not installed or not in PATH"
        return 1
    fi

    if [ ! -d "node_modules" ]; then
        warning "node_modules directory not found. Run 'install-node' first."
        return 1
    fi

    npm run build
    success "Production assets built successfully"
}

# Function to run database migrations
run_migrations() {
    log "Pending migrations:"
    php bin/console doctrine:migrations:status --env=prod --no-debug || true
    echo ""

    log "Running database migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction --env=prod --no-debug
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
    if php bin/console about > /dev/null 2>&1; then
        success "Application is responding correctly"
    else
        error "Application verification failed"
        return 1
    fi
}

# Full deployment: runs all steps in order
full_deploy() {
    log "Starting full deployment..."
    echo ""

    update_code
    install_dependencies
    install_node_dependencies
    build_assets
    run_migrations
    clear_cache
    warm_cache
    reload_php_fpm
    verify_deployment

    echo ""
    success "Deployment complete!"
}

# Function to rollback deployment
rollback() {
    error "Starting rollback process..."

    if [ -z "$PRE_DEPLOY_SHA" ]; then
        warning "No pre-deploy SHA recorded in this session."
        read -p "Enter the git SHA to rollback to (or press Enter to rollback 1 commit): " sha
        if [ -z "$sha" ]; then
            sha="HEAD~1"
        fi
    else
        sha="$PRE_DEPLOY_SHA"
        warning "Rolling back to pre-deploy commit: ${sha:0:8}"
    fi

    echo ""
    read -p "Do you want to rollback database migrations? (y/N): " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        warning "Rolling back migrations..."
        php bin/console doctrine:migrations:migrate prev --no-interaction --env=prod --no-debug
        success "Migrations rolled back"
    fi

    warning "Resetting code to ${sha}..."
    git reset --hard "$sha"

    install_dependencies
    build_assets
    clear_cache
    warm_cache
    reload_php_fpm

    success "Rollback completed"
}

# Show usage information
show_usage() {
    echo "Captain Coaster Deployment Tools"
    echo ""
    echo "Usage: $0 [COMMAND]"
    echo ""
    echo "Commands:"
    echo "  deploy       Full deployment (all steps in order)"
    echo "  update       Pull latest code from repository"
    echo "  install      Install/update PHP dependencies"
    echo "  install-node Install/update Node.js dependencies"
    echo "  assets       Build production assets with Vite"
    echo "  migrate      Run database migrations"
    echo "  cache        Clear and warm cache"
    echo "  verify       Verify deployment"
    echo "  rollback     Rollback code and optionally migrations"
    echo "  help         Show this help message"
}

# Main script logic
case "${1:-help}" in
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
