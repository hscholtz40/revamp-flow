#!/bin/bash

# Quick fix for .env file permission issues
# Run this if you get "Permission denied" when writing to .env
# Usage: bash scripts/fix-env-permissions.sh

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
APP_DIR="$(dirname "$SCRIPT_DIR")"

cd "$APP_DIR" || exit 1

echo "Fixing .env file permissions..."

if [ ! -f ".env" ]; then
    echo "ERROR: .env file not found!"
    if [ -f ".env.example" ]; then
        echo "Creating .env from .env.example..."
        cp .env.example .env
    else
        echo "ERROR: .env.example not found. Cannot create .env file."
        exit 1
    fi
fi

# Set permissions to 664 (readable/writable by owner and group)
chmod 664 .env

# Try to get current user and set ownership (if running as root/sudo)
CURRENT_USER=$(whoami)
if [ "$CURRENT_USER" != "root" ]; then
    echo "Setting ownership to $CURRENT_USER..."
    chown "$CURRENT_USER:$CURRENT_USER" .env 2>/dev/null || echo "Note: Could not change ownership (may need sudo)"
fi

echo ""
echo "Current .env file permissions:"
ls -la .env

echo ""
echo "Testing if PHP can write to .env..."
if php -r "file_put_contents('.env', file_get_contents('.env'));" 2>/dev/null; then
    echo "SUCCESS: PHP can write to .env file"
else
    echo "WARNING: PHP may not be able to write to .env"
    echo "You may need to:"
    echo "  1. Check file ownership: ls -la .env"
    echo "  2. Set ownership: chown yourcpaneluser:yourcpaneluser .env"
    echo "  3. Set permissions: chmod 664 .env"
fi

echo ""
echo "If APP_KEY is missing, run: php artisan key:generate --force"

