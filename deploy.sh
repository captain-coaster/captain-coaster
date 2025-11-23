#!/bin/bash

# Captain Coaster Safe Deployment Script
# This script demonstrates best practices for deploying with maintenance mode

set -e

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

# Function to enable maintenance mode
enable_maintenance() {
    log "Enabling maintenance mode..."
    cp "$PROJECT_DIR/maintenance.html" "$PROJECT_DIR/public/maintenance.html"
    success "Maintenance mode enabled"
}

# Function to disable maintenance mode
disable_maintenance() {
    log "Disabling maintenance mode..."
    rm -f "$PROJECT_DIR/public/maintenance.html"
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
    echo "Example deployment workflow:"
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
    "backup")
        backup_database
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