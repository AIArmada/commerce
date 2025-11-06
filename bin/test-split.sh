#!/bin/bash

# Test monorepo split locally
# Usage: ./bin/test-split.sh [package-name]

set -e

PACKAGE=${1:-cart}
SPLIT_DIR="../commerce-split-test"

echo "🔧 Testing split for package: $PACKAGE"

# Create a temporary directory for split testing
rm -rf "$SPLIT_DIR/$PACKAGE"
mkdir -p "$SPLIT_DIR"

echo "📦 Splitting packages/$PACKAGE using git subtree..."

# Use git subtree to split the package
git subtree split --prefix=packages/$PACKAGE -b split-$PACKAGE

# Create a new repo for the split package
cd "$SPLIT_DIR"
git clone --branch split-$PACKAGE ../../commerce "$PACKAGE"
cd "$PACKAGE"

echo "✅ Split completed!"
echo "📍 Split package location: $SPLIT_DIR/$PACKAGE"
echo ""
echo "🔍 Files in split package:"
ls -la

echo ""
echo "📄 composer.json content:"
cat composer.json | jq '.'

echo ""
echo "🧪 Testing composer install..."
composer install --no-interaction

echo ""
echo "✅ Split test completed successfully!"
echo "📍 Check the split at: $SPLIT_DIR/$PACKAGE"
echo ""
echo "🧹 To cleanup: rm -rf $SPLIT_DIR"

# Cleanup branch
cd ../../commerce
git branch -D split-$PACKAGE 2>/dev/null || true
