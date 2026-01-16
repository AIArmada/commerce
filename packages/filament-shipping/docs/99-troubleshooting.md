---
title: Troubleshooting
---

# Troubleshooting

## Resources Not Appearing

### Check Plugin Registration

Ensure the plugin is registered in your panel provider:

```php
use AIArmada\FilamentShipping\FilamentShippingPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentShippingPlugin::make(),
        ]);
}
```

### Check Permissions

Users need appropriate permissions to view resources:

```php
// Check if user can view shipments
auth()->user()->can('view_any_shipment');
```

Ensure your policy is returning `true`:

```php
// App\Policies\ShipmentPolicy
public function viewAny(User $user): bool
{
    return true; // Or your permission logic
}
```

### Check Feature Toggles

Verify features are enabled in config:

```php
// config/filament-shipping.php
'features' => [
    'fulfillment_queue' => true,
    'manifest_page' => true,
    'dashboard' => true,
],
```

## Fulfillment Queue Not Showing

### Orders Package Required

The fulfillment queue requires the Orders package:

```bash
composer require aiarmada/orders
```

### Check Order Status

The queue shows orders in "Processing" status. Verify orders exist:

```php
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Processing;

Order::whereState('status', Processing::class)->count();
```

## Actions Failing

### Ship Action Errors

Common causes:
1. **No carrier selected**: Ensure carrier is configured
2. **Invalid destination**: Carrier doesn't service the address
3. **API failure**: Check carrier API credentials

Debug:
```php
use AIArmada\Shipping\Services\ShipmentService;

$service = app(ShipmentService::class);

try {
    $result = $service->ship($shipment, 'jnt');
} catch (\Throwable $e) {
    dd($e->getMessage());
}
```

### Cancel Action Not Visible

Cancel is only visible for cancellable statuses:
- Draft
- Pending

Once a shipment is Shipped or beyond, it cannot be cancelled through the UI.

### Label Action Not Working

1. Check carrier supports labels:
   ```php
   Shipping::driver('jnt')->supports(DriverCapability::LabelGeneration);
   ```
2. Verify shipment has tracking number
3. Check carrier API is responding

## Widget Issues

### Widgets Show Zero/Empty

1. **Owner scoping**: If enabled, ensure owner context is set
2. **No data**: Verify shipments exist in database
3. **Permissions**: User needs view permissions

Debug:
```php
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Enums\ShipmentStatus;

// Check data exists
Shipment::where('status', ShipmentStatus::Pending)->count();

// Check with owner scope
Shipment::forOwner($owner)->where('status', ShipmentStatus::Pending)->count();
```

### Widget Not Refreshing

Check polling is enabled:
```php
// config/filament-shipping.php
'table_poll_interval' => '30s', // Not null
```

## Multi-Tenancy Issues

### Cross-Tenant Data Visible

1. Verify owner scoping is enabled in shipping config:
   ```php
   // config/shipping.php
   'features' => [
       'owner' => [
           'enabled' => true,
       ],
   ],
   ```

2. Ensure `OwnerContext` is set in middleware:
   ```php
   use AIArmada\CommerceSupport\Support\OwnerContext;
   
   OwnerContext::set($tenant);
   ```

3. Check resource query is owner-safe:
   ```php
   // Resources should use getEloquentQuery() with owner scope
   public static function getEloquentQuery(): Builder
   {
       return parent::getEloquentQuery();
       // Model's global scope handles filtering
   }
   ```

### Navigation Badge Shows Wrong Count

The badge caches counts for 15 seconds. Wait or clear cache:

```php
Cache::forget('filament-shipping.fulfillment-queue.badge.*');
```

## Performance Issues

### Slow Table Loading

1. Reduce polling interval or disable:
   ```php
   'table_poll_interval' => null, // Disable auto-refresh
   ```

2. Add database indexes:
   ```php
   Schema::table('shipping_shipments', function (Blueprint $table) {
       $table->index('status');
       $table->index(['owner_type', 'owner_id']);
   });
   ```

3. Eager load relationships in resource:
   ```php
   public static function getEloquentQuery(): Builder
   {
       return parent::getEloquentQuery()
           ->with(['items', 'shippable']);
   }
   ```

### Dashboard Widgets Slow

Widgets cache data. If slow, check:
1. Database query performance
2. Carrier API response times (for tracking sync)

## Form Validation Errors

### Address Fields Required

Ensure origin and destination addresses are complete:
- Line 1 is required
- City is required  
- State is required
- Postcode is required
- Country is required (2-letter ISO code)

### Invalid Carrier

Check carrier is configured in shipping drivers:

```php
// config/shipping.php
'drivers' => [
    'your_carrier' => [
        'name' => 'Your Carrier',
        // ... config
    ],
],
```

## Getting Help

1. Check Laravel logs: `storage/logs/laravel.log`
2. Enable Filament debug mode
3. Review the [shipping package docs](../shipping/01-overview.md)
4. Open an issue on [GitHub](https://github.com/aiarmada/commerce/issues)
