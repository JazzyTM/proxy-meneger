#!/bin/bash

# Proxy Manager - Modern SSL & Nginx Management
# Update Script
# GitHub: https://github.com/JazzyTM/proxy-meneger
# Telegram: @jazzytm

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

print_banner() {
    echo -e "${CYAN}"
    echo "╔═══════════════════════════════════════════════════════════╗"
    echo "║                                                           ║"
    echo "║              Proxy Manager - Updater                     ║"
    echo "║         Modern SSL & Nginx Management                    ║"
    echo "║                                                           ║"
    echo "╚═══════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

print_step() {
    echo -e "${CYAN}▶${NC} $1"
}

check_root() {
    if [ "$EUID" -ne 0 ]; then 
        print_error "Please run as root or with sudo"
        exit 1
    fi
}

check_git() {
    if ! command -v git &> /dev/null; then
        print_error "Git is not installed"
        print_info "Install git: apt install git (Ubuntu/Debian) or yum install git (CentOS/RHEL)"
        exit 1
    fi
}

backup_data() {
    print_step "Creating backup..."
    
    BACKUP_DIR="backup_$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$BACKUP_DIR"
    
    # Backup databases and configs
    if [ -d "db" ]; then
        cp -r db "$BACKUP_DIR/" 2>/dev/null || true
    fi
    
    if [ -f ".env" ]; then
        cp .env "$BACKUP_DIR/" 2>/dev/null || true
    fi
    
    print_success "Backup created: $BACKUP_DIR"
}

check_updates() {
    print_step "Checking for updates..."
    
    git fetch origin
    
    LOCAL=$(git rev-parse @)
    REMOTE=$(git rev-parse @{u})
    
    if [ $LOCAL = $REMOTE ]; then
        print_info "Already up to date!"
        read -p "Do you want to rebuild anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 0
        fi
    else
        print_info "Updates available"
    fi
}

pull_updates() {
    print_step "Pulling latest changes..."
    
    # Stash local changes
    git stash push -m "Auto-stash before update $(date +%Y%m%d_%H%M%S)"
    
    # Pull updates
    git pull origin main || git pull origin master
    
    print_success "Code updated"
}

stop_services() {
    print_step "Stopping services..."
    docker compose down
    print_success "Services stopped"
}

rebuild_images() {
    print_step "Rebuilding Docker images..."
    docker compose build --no-cache
    print_success "Images rebuilt"
}

start_services() {
    print_step "Starting services..."
    docker compose up -d
    
    # Wait for services
    sleep 5
    
    if docker compose ps | grep -q "Up"; then
        print_success "Services started successfully"
    else
        print_error "Failed to start services"
        print_info "Check logs: docker compose logs"
        exit 1
    fi
}

cleanup_old_images() {
    print_step "Cleaning up old images..."
    docker image prune -f
    print_success "Cleanup completed"
}

check_health() {
    print_step "Checking service health..."
    
    sleep 3
    
    # Check webui
    if curl -sf http://localhost:8080 > /dev/null; then
        print_success "WebUI is healthy"
    else
        print_warning "WebUI might not be responding yet"
    fi
    
    # Check reverse-proxy
    if docker exec reverse-proxy nginx -t &>/dev/null; then
        print_success "Reverse proxy is healthy"
    else
        print_warning "Reverse proxy configuration might have issues"
    fi
}

print_final() {
    echo ""
    echo -e "${GREEN}╔═══════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                                                           ║${NC}"
    echo -e "${GREEN}║           Update completed successfully!                 ║${NC}"
    echo -e "${GREEN}║                                                           ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${CYAN}📋 What's New:${NC}"
    echo -e "   Check changelog: https://github.com/JazzyTM/proxy-meneger/releases"
    echo ""
    echo -e "${CYAN}📚 Useful Commands:${NC}"
    echo -e "   View logs:    ${YELLOW}docker compose logs -f${NC}"
    echo -e "   Restart:      ${YELLOW}docker compose restart${NC}"
    echo -e "   Status:       ${YELLOW}docker compose ps${NC}"
    echo ""
    echo -e "${CYAN}💾 Backup Location:${NC}"
    if [ -d "$BACKUP_DIR" ]; then
        echo -e "   $BACKUP_DIR"
    fi
    echo ""
}

main() {
    print_banner
    check_root
    check_git
    
    # Confirm update
    echo ""
    read -p "Do you want to update Proxy Manager? (Y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Nn]$ ]]; then
        echo "Update cancelled"
        exit 0
    fi
    
    backup_data
    check_updates
    pull_updates
    stop_services
    rebuild_images
    start_services
    cleanup_old_images
    check_health
    print_final
}

main
