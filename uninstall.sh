#!/bin/bash

# Proxy Manager - Modern SSL & Nginx Management
# Uninstallation Script
# GitHub: https://github.com/JazzyTM/proxy-meneger
# Telegram: @jazzytm

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

print_banner() {
    echo -e "${CYAN}"
    echo "╔═══════════════════════════════════════════════════════════╗"
    echo "║                                                           ║"
    echo "║            Proxy Manager - Uninstaller                   ║"
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

print_step() {
    echo -e "${CYAN}▶${NC} $1"
}

check_root() {
    if [ "$EUID" -ne 0 ]; then 
        print_error "Please run as root or with sudo"
        exit 1
    fi
}

confirm_uninstall() {
    echo ""
    print_warning "This will remove all Proxy Manager containers and optionally delete data"
    echo ""
    read -p "Are you sure you want to continue? (yes/no): " -r
    echo
    if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
        echo "Uninstallation cancelled"
        exit 0
    fi
}

stop_services() {
    print_step "Stopping services..."
    if docker compose ps &>/dev/null; then
        docker compose down
        print_success "Services stopped"
    else
        print_warning "Services are not running"
    fi
}

remove_containers() {
    print_step "Removing containers..."
    docker compose down -v 2>/dev/null || true
    
    # Remove any orphaned containers
    docker rm -f reverse-proxy webui 2>/dev/null || true
    
    print_success "Containers removed"
}

remove_images() {
    print_step "Removing Docker images..."
    docker rmi proxy-meneger-reverse-proxy proxy-meneger-webui 2>/dev/null || true
    print_success "Images removed"
}

remove_network() {
    print_step "Removing Docker network..."
    docker network rm proxy-meneger_proxy-network 2>/dev/null || true
    print_success "Network removed"
}

remove_data() {
    echo ""
    read -p "Do you want to remove all data (databases, certificates, configs)? (yes/no): " -r
    echo
    if [[ $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
        print_step "Removing data directories..."
        
        rm -rf db/*
        rm -rf certs/*
        rm -rf nginx-configs/*
        rm -rf src/www/.well-known/*
        
        print_success "Data removed"
    else
        print_warning "Data preserved in: db/, certs/, nginx-configs/"
    fi
}

remove_env() {
    read -p "Do you want to remove .env file? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        rm -f .env
        print_success ".env file removed"
    fi
}

print_final() {
    echo ""
    echo -e "${GREEN}╔═══════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                                                           ║${NC}"
    echo -e "${GREEN}║         Uninstallation completed successfully!           ║${NC}"
    echo -e "${GREEN}║                                                           ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${CYAN}Thank you for using Proxy Manager!${NC}"
    echo ""
    echo -e "GitHub:   https://github.com/JazzyTM/proxy-meneger"
    echo -e "Telegram: @jazzytm"
    echo ""
}

main() {
    print_banner
    check_root
    confirm_uninstall
    
    stop_services
    remove_containers
    remove_images
    remove_network
    remove_data
    remove_env
    
    print_final
}

main
