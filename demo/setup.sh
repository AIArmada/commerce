#!/usr/bin/env bash

# Commerce Demo Setup Script
# This script sets up the demo application for local development

set -e

echo "🛒 Commerce Demo Setup"
echo "======================"
echo ""

# Navigate to demo directory
cd "$(dirname "$0")"

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "❌ Composer is not installed. Please install Composer first."
    exit 1
fi

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed. Please install PHP 8.4+ first."
    exit 1
fi

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
if [[ "$(echo "$PHP_VERSION < 8.4" | bc)" -eq 1 ]]; then
    echo "❌ PHP 8.4+ is required. Current version: $PHP_VERSION"
    exit 1
fi

echo "✅ PHP $PHP_VERSION detected"
echo ""

# Install dependencies
echo "📦 Installing Composer dependencies..."
composer install --no-interaction --prefer-dist

# Setup environment
if [ ! -f .env ]; then
    echo "📝 Creating .env file..."
    cp .env.example .env
fi

# Generate app key
echo "🔑 Generating application key..."
php artisan key:generate --ansi

# Create database directory if needed
mkdir -p database

# Create SQLite database
if [ ! -f database/database.sqlite ]; then
    echo "💾 Creating SQLite database..."
    touch database/database.sqlite
fi

# Run migrations and seed
echo "🗃️  Running migrations..."
php artisan migrate:fresh --seed --ansi

# Clear caches
echo "🧹 Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear

echo ""
echo "✨ Setup complete!"
echo ""
echo "To start the development server:"
echo "  cd demo && php artisan serve"
echo ""
echo "Then visit:"
echo "  http://localhost:8000       - Welcome page"
echo "  http://localhost:8000/admin - Admin panel"
echo ""
echo "Login credentials:"
echo "  Email:    admin@commerce.demo"
echo "  Password: password"
echo ""
