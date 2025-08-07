#!/bin/bash

# Link2Investors Improved Plugins Deployment Script
# This script helps deploy the improved plugins to fix Cloudways compatibility issues

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to backup existing plugins
backup_plugins() {
    local backup_dir="$1"
    local plugins_dir="$2"
    
    print_status "Creating backup of existing plugins..."
    
    if [ -d "$plugins_dir" ]; then
        mkdir -p "$backup_dir"
        cp -r "$plugins_dir" "$backup_dir/"
        print_success "Backup created at: $backup_dir"
    else
        print_warning "Plugins directory not found: $plugins_dir"
    fi
}

# Function to deploy improved plugins
deploy_plugins() {
    local target_dir="$1"
    local source_dir="$(pwd)"
    
    print_status "Deploying improved plugins..."
    
    # Create target directory if it doesn't exist
    mkdir -p "$target_dir"
    
    # Deploy ProjectTheme LiveChat
    if [ -d "$source_dir/improved-ProjectTheme_livechat" ]; then
        print_status "Deploying ProjectTheme LiveChat..."
        cp -r "$source_dir/improved-ProjectTheme_livechat" "$target_dir/"
        print_success "ProjectTheme LiveChat deployed"
    else
        print_error "ProjectTheme LiveChat source not found"
        return 1
    fi
    
    # Deploy Link2Investors Custom Features
    if [ -d "$source_dir/improved-link2investors-custom-features" ]; then
        print_status "Deploying Link2Investors Custom Features..."
        cp -r "$source_dir/improved-link2investors-custom-features" "$target_dir/"
        print_success "Link2Investors Custom Features deployed"
    else
        print_error "Link2Investors Custom Features source not found"
        return 1
    fi
}

# Function to set proper permissions
set_permissions() {
    local plugins_dir="$1"
    
    print_status "Setting proper file permissions..."
    
    # Set directory permissions
    find "$plugins_dir" -type d -exec chmod 755 {} \;
    
    # Set file permissions
    find "$plugins_dir" -type f -exec chmod 644 {} \;
    
    # Make sure PHP files are readable
    find "$plugins_dir" -name "*.php" -exec chmod 644 {} \;
    
    print_success "Permissions set successfully"
}

# Function to validate deployment
validate_deployment() {
    local plugins_dir="$1"
    
    print_status "Validating deployment..."
    
    # Check if main plugin files exist
    local required_files=(
        "ProjectTheme_livechat/ProjectTheme_livechat.php"
        "ProjectTheme_livechat/chat-regular.class.php"
        "ProjectTheme_livechat/messaging.php"
        "ProjectTheme_livechat/messages.js"
        "link2investors-custom-features/link2investors-custom-features.php"
        "link2investors-custom-features/assets/js/pt-custom-script.js"
        "link2investors-custom-features/assets/css/pt-custom-style.css"
    )
    
    for file in "${required_files[@]}"; do
        if [ -f "$plugins_dir/$file" ]; then
            print_success "✓ $file"
        else
            print_error "✗ Missing: $file"
            return 1
        fi
    done
    
    print_success "Deployment validation completed"
}

# Function to display usage
show_usage() {
    echo "Usage: $0 [OPTIONS] <wordpress_plugins_directory>"
    echo ""
    echo "Options:"
    echo "  -b, --backup-dir DIR    Backup directory (default: ./backup-$(date +%Y%m%d-%H%M%S))"
    echo "  -h, --help             Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 /var/www/html/wp-content/plugins"
    echo "  $0 -b /path/to/backup /var/www/html/wp-content/plugins"
    echo ""
    echo "This script will:"
    echo "  1. Backup existing plugins"
    echo "  2. Deploy improved plugins"
    echo "  3. Set proper permissions"
    echo "  4. Validate deployment"
}

# Main script
main() {
    local backup_dir=""
    local plugins_dir=""
    
    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            -b|--backup-dir)
                backup_dir="$2"
                shift 2
                ;;
            -h|--help)
                show_usage
                exit 0
                ;;
            -*)
                print_error "Unknown option: $1"
                show_usage
                exit 1
                ;;
            *)
                if [ -z "$plugins_dir" ]; then
                    plugins_dir="$1"
                else
                    print_error "Multiple plugin directories specified"
                    exit 1
                fi
                shift
                ;;
        esac
    done
    
    # Check if plugins directory is provided
    if [ -z "$plugins_dir" ]; then
        print_error "WordPress plugins directory not specified"
        show_usage
        exit 1
    fi
    
    # Set default backup directory if not provided
    if [ -z "$backup_dir" ]; then
        backup_dir="./backup-$(date +%Y%m%d-%H%M%S)"
    fi
    
    # Check if we're in the right directory
    if [ ! -d "improved-ProjectTheme_livechat" ] || [ ! -d "improved-link2investors-custom-features" ]; then
        print_error "Please run this script from the directory containing the improved plugins"
        exit 1
    fi
    
    print_status "Starting deployment process..."
    print_status "Target plugins directory: $plugins_dir"
    print_status "Backup directory: $backup_dir"
    
    # Create backup
    backup_plugins "$backup_dir" "$plugins_dir"
    
    # Deploy plugins
    deploy_plugins "$plugins_dir"
    
    # Set permissions
    set_permissions "$plugins_dir"
    
    # Validate deployment
    validate_deployment "$plugins_dir"
    
    print_success "Deployment completed successfully!"
    echo ""
    print_status "Next steps:"
    echo "  1. Go to WordPress Admin → Plugins"
    echo "  2. Deactivate old plugins (if still active)"
    echo "  3. Activate the improved plugins:"
    echo "     - ProjectTheme LiveChat Users (Improved)"
    echo "     - Link2Investors Custom Features (Improved)"
    echo "  4. Test the messaging functionality"
    echo ""
    print_warning "If you encounter any issues, check the troubleshooting section in README.md"
}

# Run main function with all arguments
main "$@"