---
title: Installation
---

# Installation

## Requirements

- PHP 8.5 or higher
- Laravel 13.x
- `aiarmada/commerce-support` package

## Install via Composer

```bash
composer require aiarmada/inventory
```

The package will auto-register its service provider.

## Publish Configuration

```bash
php artisan vendor:publish --tag=inventory-config
```

This publishes `config/inventory.php` with all configuration options.

## Run Migrations

```bash
php artisan migrate
```

This creates the following tables (with configurable prefix):

| Table | Purpose |
|-------|---------|
| `inventory_locations` | Warehouse/bin locations |
| `inventory_levels` | Stock levels per SKU per location |
| `inventory_movements` | Movement audit trail |
| `inventory_allocations` | Cart/order reservations |
| `inventory_batches` | Batch/lot tracking |
| `inventory_serials` | Serial number tracking |
| `inventory_serial_history` | Serial number audit trail |
| `inventory_cost_layers` | FIFO cost layers |
| `inventory_standard_costs` | Standard cost records |
| `inventory_valuation_snapshots` | Period-end valuations |
| `inventory_backorders` | Backorder tracking |
| `inventory_demand_history` | Demand records for forecasting |
| `inventory_supplier_leadtimes` | Supplier lead time data |
| `inventory_reorder_suggestions` | Auto-generated reorder recommendations |
| `inventory_reservations` | Checkout reservation groups |
| `inventory_operations` | Idempotent order-driven operation log |

## Optional: Cart Integration

For cart integration features:

```bash
composer require aiarmada/cart
```

Then in `config/inventory.php`, enable cart features:

```php
'cart' => [
    'validate_on_add' => true,
    'auto_allocate_on_add' => true,
    'allow_backorder' => false,
],
'allocation_ttl_minutes' => 30,
```

## Optional: Multi-Tenancy

To enable owner-scoped inventory:

```php
// config/inventory.php
'owner' => [
    'enabled' => true,
    'include_global' => false, // Whether to include global inventory
],
```

Ensure you have bound `OwnerResolverInterface` in your service container.

## Make Models Inventoryable

Add the `HasInventory` trait to any model that should track inventory:

```php
use AIArmada\Inventory\Traits\HasInventory;

class Product extends Model
{
    use HasInventory;
}
```

This provides:
- `$product->inventoryLevels()` - Stock levels across locations
- `$product->inventoryMovements()` - Movement history
- And read helpers such as `getTotalAvailable()`, `getTotalOnHand()`, `getAvailability()`, and `hasInventory()`

Serial numbers come from the separate `HasSerialNumbers` trait, and batch records via `Features\Traceability\Queries\BatchLookupService::getBatchesForModel()`.

Stock mutations belong to `InventoryService`; cart allocations belong to
`InventoryAllocationService` or the checkout reservation contract.

## Verify Installation

```php
use AIArmada\Inventory\Facades\Inventory;

// Create a location
$location = \AIArmada\Inventory\Features\Stock\Models\InventoryLocation::create([
    'name' => 'Main Warehouse',
    'code' => 'WH-001',
    'is_active' => true,
]);

// Receive some inventory
Inventory::receive($product, $location->id, 100);

// Check it worked
echo $product->getTotalAvailable(); // 100
```

## Extending Contracts

Use the package's public contracts under `Contracts/`:

| Contract | Purpose |
|---|---|
| `InventoryableInterface` | Scalar inventory availability |
| `CheckoutReservationServiceInterface` | Checkout reservation groups |
| `ProvidesInventoryCommitContext` | Cart/order context for payment commit listeners |
| `ScopedCapacityReservationServiceInterface` | Scoped capacity reservation lifecycle |
| `ExportableInterface` | Export headers, rows and filename |

Pass an `ExportableInterface` implementation to `Features\\Reporting\\Actions\\ExportService`. Reporting queries live under `Features/Reporting/Queries`. Costing adapters belong to `Features/Valuation/Persistence`.

## Next Steps

- [Configuration](03-configuration.md) - Customize all options
- [Usage](04-usage.md) - Learn the API
- [Troubleshooting](99-troubleshooting.md) - Common issues
