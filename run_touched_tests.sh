#!/bin/zsh
PHP="/Users/saiffil/Library/Application Support/Herd/bin/php"
PEST="vendor/bin/pest"

# Array of test paths
paths=(
tests/src/CashierChip
tests/src/Cashier
tests/src/Chip
tests/src/Support
tests/src/Customers
tests/src/Docs
tests/src/FilamentAffiliates
tests/src/FilamentAuthz
tests/src/FilamentCart
tests/src/FilamentCashierChip
tests/src/FilamentCashier
tests/src/FilamentChip
tests/src/FilamentCustomers
tests/src/FilamentDocs
tests/src/FilamentOrders
tests/src/FilamentPricing
tests/src/FilamentProducts
tests/src/FilamentShipping
tests/src/FilamentTax
tests/src/FilamentVouchers
tests/src/Inventory
tests/src/Jnt
tests/src/Orders
tests/src/Products
tests/src/Shipping
tests/src/Vouchers
)

for path in "${paths[@]}"; do
    if [ -d "$path" ]; then
        echo "----------------------------------------------------------------"
        echo "Running tests for $path"
        echo "----------------------------------------------------------------"
        "$PHP" "$PEST" --parallel "$path" --no-coverage
    else
        echo "Skipping $path: Directory not found"
    fi
done
