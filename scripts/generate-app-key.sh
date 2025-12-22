#!/bin/bash

# Generate Laravel application encryption key
# Usage: bash scripts/generate-app-key.sh

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
APP_DIR="$(dirname "$SCRIPT_DIR")"

cd "$APP_DIR" || exit 1

echo "Generating application encryption key..."

if [ ! -f ".env" ]; then
    echo "ERROR: .env file not found!"
    echo "Please create .env file first:"
    echo "  cp .env.example .env"
    exit 1
fi

php artisan key:generate --force

if [ $? -eq 0 ]; then
    echo "Application key generated successfully!"
    echo ""
    echo "Make sure your .env file has the correct production settings:"
    echo "  APP_ENV=production"
    echo "  APP_DEBUG=false"
else
    echo "ERROR: Failed to generate application key"
    echo "Make sure PHP and artisan are accessible"
    exit 1
fi

