#!/bin/bash

# Quick split test using symplify/monorepo-builder
# Usage: ./bin/quick-split-test.sh [package-name]

set -e

PACKAGE=${1:-cart}

echo "🧪 Testing package: $PACKAGE"
echo ""

# Navigate to package directory
cd "packages/$PACKAGE"

echo "✅ Package structure:"
ls -la

echo ""
echo "📄 composer.json:"
cat composer.json

echo ""
echo "🔍 Checking if package is valid..."

# Test composer validation
composer validate --no-check-all --no-check-publish

echo ""
echo "📦 Testing composer install (dry-run)..."
composer install --dry-run --no-interaction

echo ""
echo "✅ Package $PACKAGE is ready for splitting!"
