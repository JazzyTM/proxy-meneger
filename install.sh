#!/bin/bash

# Proxy Manager - Modern SSL & Nginx Management
# Installation Script
# Supports: Ubuntu, Debian, CentOS, Fedora
# GitHub: https://github.com/JazzyTM/proxy-meneger
# Telegram: @jazzytm

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Banner
print_banner() {
    echo -e "${CYAN}"
    echo "╔═══════════════════════════════════════════════════════════╗"
    echo "║                                                           ║"
    echo "║              Proxy Manager - Installer                   ║"
    echo "║         Modern SSL & Nginx Management                    ║"
    echo "║                                                           ║"
    echo "╚═══════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
}

# Print colored messages
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
    echo -e "${PURPLE}▶${NC} $1"
}

# Check if running as root
check_root() {
    if [ "$EUID" -ne 0 ]; then 
        print_error "Please run as root or with sudo"
        exit 1
    fi
}

# Detect OS
detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$ID
        VER=$VERSION_ID
    else
        print_error "Cannot detect OS"
        exit 1
    fi
    print_info "Detected OS: $OS $VER"
}

# Check if Docker is installed
check_docker() {
    print_step "Checking Docker installation..."
    if command -v docker &> /dev/null; then
        DOCKER_VERSION=$(docker --version | cut -d ' ' -f3 | cut -d ',' -f1)
        print_success "Docker is installed (version $DOCKER_VERSION)"
        return 0
    else
        print_warning "Docker is not installed"
        return 1
    fi
}

# Install Docker
install_docker() {
    print_step "Installing Docker..."
    
    case $OS in
        ubuntu|debian)
            apt-get update
            apt-get install -y ca-certificates curl gnupg lsb-release
            
            # Add Docker's official GPG key
            install -m 0755 -d /etc/apt/keyrings
            curl -fsSL https://download.docker.com/linux/$OS/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
            chmod a+r /etc/apt/keyrings/docker.gpg
            
            # Set up repository
            echo \
              "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/$OS \
              $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
            
            apt-get update
            apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
            ;;
            
        centos|fedora|rhel)
            yum install -y yum-utils
            yum-config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
            yum install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
            systemctl start docker
            systemctl enable docker
            ;;
            
        *)
            print_error "Unsupported OS for automatic Docker installation"
            print_info "Please install Docker manually: https://docs.docker.com/engine/install/"
            exit 1
            ;;
    esac
    
    print_success "Docker installed successfully"
}

# Check ports availability
check_ports() {
    print_step "Checking required ports..."
    
    PORTS=(80 443 8080)
    PORTS_IN_USE=()
    
    for port in "${PORTS[@]}"; do
        if netstat -tuln 2>/dev/null | grep -q ":$port " || ss -tuln 2>/dev/null | grep -q ":$port "; then
            PORTS_IN_USE+=($port)
        fi
    done
    
    if [ ${#PORTS_IN_USE[@]} -gt 0 ]; then
        print_warning "The following ports are already in use: ${PORTS_IN_USE[*]}"
        read -p "Do you want to continue anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    else
        print_success "All required ports are available"
    fi
}

# Create directory structure
create_directories() {
    print_step "Creating directory structure..."
    
    mkdir -p db
    mkdir -p certs/certificates
    mkdir -p nginx-configs
    mkdir -p src/www/.well-known/acme-challenge
    
    # Set permissions
    chmod -R 777 db
    chmod -R 777 certs
    chmod -R 777 nginx-configs
    chmod -R 777 src/www/.well-known
    
    # Create .gitkeep files
    touch db/.gitkeep
    touch certs/.gitkeep
    touch nginx-configs/.gitkeep
    
    print_success "Directory structure created"
}

# Create .env file
create_env() {
    print_step "Creating environment configuration..."
    
    if [ ! -f .env ]; then
        # Get Docker group ID
        DOCKER_GID=$(getent group docker | cut -d: -f3)
        
        cat > .env << EOF
# Docker Configuration
DOCKER_GID=${DOCKER_GID:-999}

# Application Settings
APP_ENV=production
APP_DEBUG=false

# Database
DB_PATH=/db/db.db

# Certificates
CERTS_PATH=/certs

# Nginx
NGINX_CONFIGS_PATH=/nginx-configs
EOF
        print_success "Environment file created"
    else
        print_info "Environment file already exists, skipping..."
    fi
}

# Build and start containers
start_services() {
    print_step "Building Docker images..."
    docker compose build
    
    print_step "Starting services..."
    docker compose up -d
    
    # Wait for services to be ready
    print_info "Waiting for services to start..."
    sleep 5
    
    # Set correct permissions for PHP-FPM user
    print_step "Setting permissions..."
    docker compose exec -T webui chown -R nobody:nogroup /db /certs /nginx-configs
    docker compose exec -T webui sh -c "chmod 666 /db/*.db /db/.jwt_secret 2>/dev/null || true"
    chmod -R 755 certs/ nginx-configs/
    
    print_success "Permissions configured"
    
    # Check if containers are running
    if docker compose ps | grep -q "Up"; then
        print_success "Services started successfully"
    else
        print_error "Failed to start services"
        docker compose logs
        exit 1
    fi
}

# Get server IP
get_server_ip() {
    SERVER_IP=$(curl -s https://ipinfo.io/ip 2>/dev/null || hostname -I | awk '{print $1}')
    echo $SERVER_IP
}

# Print final instructions
print_instructions() {
    SERVER_IP=$(get_server_ip)
    
    echo ""
    echo -e "${GREEN}╔═══════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                                                           ║${NC}"
    echo -e "${GREEN}║           Installation completed successfully!           ║${NC}"
    echo -e "${GREEN}║                                                           ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${CYAN}📋 Access Information:${NC}"
    echo -e "   Web Interface: ${GREEN}http://${SERVER_IP}:8080${NC}"
    echo ""
    echo -e "${CYAN}🔐 First Steps:${NC}"
    echo -e "   1. Open the web interface in your browser"
    echo -e "   2. Create your admin account"
    echo -e "   3. Add your first domain"
    echo -e "   4. Generate SSL certificate"
    echo ""
    echo -e "${CYAN}📚 Useful Commands:${NC}"
    echo -e "   View logs:        ${YELLOW}docker compose logs -f${NC}"
    echo -e "   Stop services:    ${YELLOW}docker compose stop${NC}"
    echo -e "   Start services:   ${YELLOW}docker compose start${NC}"
    echo -e "   Restart services: ${YELLOW}docker compose restart${NC}"
    echo -e "   Update:           ${YELLOW}git pull && docker compose up -d --build${NC}"
    echo ""
    echo -e "${CYAN}⚠️  Security Notes:${NC}"
    echo -e "   • Change default admin password immediately"
    echo -e "   • Configure firewall to restrict access to port 8080"
    echo -e "   • Keep your system and Docker updated"
    echo ""
    echo -e "${CYAN}📖 Documentation & Support:${NC}"
    echo -e "   GitHub:   https://github.com/JazzyTM/proxy-meneger"
    echo -e "   Telegram: @jazzytm"
    echo ""
}

# Main installation flow
main() {
    print_banner
    
    check_root
    detect_os
    
    # Check and install Docker
    if ! check_docker; then
        read -p "Do you want to install Docker? (Y/n): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]] || [[ -z $REPLY ]]; then
            install_docker
        else
            print_error "Docker is required. Exiting..."
            exit 1
        fi
    fi
    
    check_ports
    create_directories
    create_env
    start_services
    print_instructions
}

# Run main function
main
