#!/bin/bash

# Fix permissions for Laravel application on cPanel server
# Run this script after extracting the release package on your cPanel server
# Usage: bash fix-permissions.sh

echo "Fixing file permissions for Laravel application..."

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
APP_DIR="$(dirname "$SCRIPT_DIR")"

cd "$APP_DIR" || exit 1

echo "Working in directory: $APP_DIR"

# Set directory permissions to 755
find . -type d -exec chmod 755 {} \;

# Set file permissions to 644
find . -type f -exec chmod 644 {} \;

# Make artisan executable
if [ -f "artisan" ]; then
    chmod 755 artisan
    echo "Made artisan executable"
fi

# Create and ensure storage directories exist with proper structure
if [ ! -d "storage" ]; then
    mkdir -p storage
fi

# Create required storage subdirectories
mkdir -p storage/app/public
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/testing
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p storage/fonts

# Ensure storage and bootstrap/cache directories are writable
chmod -R 775 storage
echo "Created and set storage directory permissions"

# Create bootstrap/cache directory if it doesn't exist
if [ ! -d "bootstrap/cache" ]; then
    mkdir -p bootstrap/cache
fi

chmod -R 775 bootstrap/cache
echo "Created and set bootstrap/cache directory permissions"

# Ensure vendor directory is readable
if [ -d "vendor" ]; then
    find vendor -type d -exec chmod 755 {} \;
    find vendor -type f -exec chmod 644 {} \;
    echo "Fixed vendor directory permissions"
fi

echo "Permission fix complete!"
echo ""

# Check for .env file and APP_KEY
if [ ! -f ".env" ]; then
    echo "WARNING: .env file not found!"
    if [ -f ".env.example" ]; then
        echo "Copying .env.example to .env..."
        cp .env.example .env
        echo "Created .env file from .env.example"
    else
        echo "ERROR: .env.example not found. Please create .env file manually."
        echo "You can create it with: cp .env.example .env"
        exit 1
    fi
fi

# Ensure .env file is writable (needed for key:generate)
if [ -f ".env" ]; then
    chmod 664 .env
    echo "Set .env file permissions to 664 (writable)"
fi

# Check if APP_KEY is set in .env
if [ -f ".env" ]; then
    if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
        echo ""
        echo "APP_KEY not found or invalid, generating application encryption key..."
        if php artisan key:generate --force 2>&1; then
            echo "Application key generated successfully!"
        else
            echo "WARNING: Failed to generate application key automatically."
            echo "You may need to run this manually: php artisan key:generate --force"
            echo "Or set APP_KEY manually in .env file"
        fi
    else
        APP_KEY_VALUE=$(grep "^APP_KEY=" .env | cut -d '=' -f2)
        echo "Application encryption key already exists in .env: ${APP_KEY_VALUE:0:20}..."
        echo "Using existing APP_KEY from build/deployment"
    fi
fi

# Clear Laravel caches to ensure fresh start
echo ""
echo "Clearing Laravel caches..."
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
echo "Cache cleared"

echo ""
echo "=== Troubleshooting ==="
echo ""
echo "If you still encounter permission issues:"
echo "1. Check file ownership: Files should be owned by your cPanel user"
echo "   Run: ls -la .env (check the owner)"
echo "2. Fix ownership if needed: chown youruser:youruser .env"
echo "3. Ensure .env is writable: chmod 664 .env"
echo "4. Contact your hosting provider if issues persist"
echo ""
echo "If you encounter APP_KEY or .env write errors:"
echo "1. Manually set permissions: chmod 664 .env"
echo "2. Check ownership: ls -la .env"
echo "3. Generate key manually: php artisan key:generate --force"
echo "4. Or edit .env manually and add: APP_KEY=base64:..."
echo ""
echo "To check PHP user (who PHP runs as):"
echo "  Create a test file with: <?php echo exec('whoami'); ?>"
echo ""
echo "If you encounter cache path errors:"
echo "1. Ensure all storage directories exist and are writable (775)"
echo "2. Run: php artisan config:clear"
echo "3. Run: php artisan cache:clear"
echo "4. Check storage/framework/cache/data directory exists"

