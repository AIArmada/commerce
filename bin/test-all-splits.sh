#!/bin/bash

# Test all packages are ready for split
# Usage: ./bin/test-all-splits.sh

set -e

PACKAGES=(
    "support"
    "cart"
    "chip"
    "docs"
    "filament-cart"
    "filament-chip"
    "filament-vouchers"
    "jnt"
    "stock"
    "vouchers"
)

echo "🧪 Testing all packages for split readiness..."
echo ""

FAILED=()
PASSED=()

for PACKAGE in "${PACKAGES[@]}"; do
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "📦 Testing: $PACKAGE"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    cd "packages/$PACKAGE"
    
    # Check if composer.json exists
    if [ ! -f "composer.json" ]; then
        echo "❌ composer.json not found!"
        FAILED+=("$PACKAGE - no composer.json")
        cd ../..
        continue
    fi
    
    # Validate composer.json
    if ! composer validate --no-check-all --no-check-publish --quiet; then
        echo "❌ composer.json validation failed!"
        FAILED+=("$PACKAGE - invalid composer.json")
        cd ../..
        continue
    fi
    
    # Check required files
    MISSING_FILES=()
    [ ! -f "README.md" ] && MISSING_FILES+=("README.md")
    [ ! -f "CHANGELOG.md" ] && MISSING_FILES+=("CHANGELOG.md")
    [ ! -f "LICENSE" ] && [ ! -f "LICENSE.md" ] && MISSING_FILES+=("LICENSE")
    
    if [ ${#MISSING_FILES[@]} -gt 0 ]; then
        echo "⚠️  Missing files: ${MISSING_FILES[*]}"
    fi
    
    echo "✅ $PACKAGE is valid"
    PASSED+=("$PACKAGE")
    
    cd ../..
    echo ""
done

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 SUMMARY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "✅ Passed: ${#PASSED[@]}"
for pkg in "${PASSED[@]}"; do
    echo "   - $pkg"
done

if [ ${#FAILED[@]} -gt 0 ]; then
    echo ""
    echo "❌ Failed: ${#FAILED[@]}"
    for fail in "${FAILED[@]}"; do
        echo "   - $fail"
    done
    exit 1
fi

echo ""
echo "🎉 All packages are ready for monorepo split!"
