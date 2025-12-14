#!/bin/bash

set -e

echo "🛠️  Setting up Mr. Mini Exchanger..."
echo ""

# Get the directory where the script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ==========================================
# Backend (Laravel API) Setup
# ==========================================
echo "📦 Setting up Laravel API..."
cd "$SCRIPT_DIR/api"

# Install PHP dependencies
if [ -f "composer.json" ]; then
    echo "   → Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist
fi

# Copy .env file if it doesn't exist
if [ ! -f ".env" ]; then
    echo "   → Creating .env file..."
    cp .env.example .env
fi

# Generate application key if not set
if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
    echo "   → Generating application key..."
    php artisan key:generate
fi

# Create SQLite database if using SQLite
if grep -q "DB_CONNECTION=sqlite" .env 2>/dev/null; then
    DB_PATH=$(grep "DB_DATABASE=" .env | cut -d '=' -f2)
    if [ -z "$DB_PATH" ]; then
        DB_PATH="database/database.sqlite"
    fi
    if [ ! -f "$DB_PATH" ]; then
        echo "   → Creating SQLite database..."
        touch "$DB_PATH"
    fi
fi

# Run migrations
echo "   → Running database migrations..."
php artisan migrate --force

# Seed the database
echo "   → Seeding database..."
php artisan db:seed --force

# Clear and cache config
echo "   → Optimizing Laravel..."
php artisan config:clear
php artisan cache:clear

echo "✅ Laravel API setup complete!"
echo ""

# ==========================================
# Frontend (Vue.js) Setup
# ==========================================
echo "🎨 Setting up Vue Frontend..."
cd "$SCRIPT_DIR/frontend"

# Install Node dependencies
if [ -f "package.json" ]; then
    echo "   → Installing npm dependencies..."
    npm install
fi

echo "✅ Vue Frontend setup complete!"
echo ""

echo "=========================================="
echo "🎉 Setting PHP & Node server using ./start.sh command"

cd "$SCRIPT_DIR" && ./start.sh

