---
title: Troubleshooting
---

# Troubleshooting

## Common Issues

### Orders not appearing in the list

**Cause**: Owner scoping is filtering out orders.

**Solution**:
1. Check that the current user has the correct tenant/owner context.
2. Verify `config('orders.owner.enabled')` matches your setup.
3. Check if orders were created with the correct `owner_id`.

```php
// Debug in tinker
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;

dump(OwnerContext::resolve());
dump(Order::query()->forOwner()->count());
dump(Order::withoutGlobalScopes()->count()); // Compare total
```

### Header actions not appearing

**Cause**: Order is in wrong state or user lacks permission.

**Solution**:
1. Check order state matches the action's visibility condition.
2. Verify user has required permissions.

```php
// Check state
dump(get_class($order->status));
dump($order->status->canCancel());

// Check permissions
dump(Gate::forUser(auth()->user())->allows('update', $order));
```

### Invoice download fails

**Cause**: `spatie/laravel-pdf` not configured or route not registered.

**Solution**:
1. Ensure `spatie/laravel-pdf` is installed and configured.
2. Check the route exists:

```bash
php artisan route:list --name=filament-orders
```

3. Verify Chromium/Browsershot is working:

```php
use Spatie\LaravelPdf\Facades\Pdf;

Pdf::html('<h1>Test</h1>')->save('/tmp/test.pdf');
```

### Widgets not showing data

**Cause**: Permissions or empty dataset.

**Solution**:
1. Check user has `viewAny` permission for orders.
2. Verify orders exist for the current owner context.
3. Check widget cache hasn't expired with stale data.

### Form validation errors on actions

**Cause**: Required fields not provided or invalid data.

**Solution**: Check action form requirements:

```php
// Confirm Payment requires:
// - transaction_id (string)
// - gateway (from config options)

// Ship Order requires:
// - carrier (from config options)  
// - tracking_number (string, max 100 chars)
```

### Table polling causing performance issues

**Cause**: Poll interval too aggressive or expensive queries.

**Solution**: Adjust poll interval in config:

```php
// config/filament-orders.php
'tables' => [
    'poll_interval' => '60s', // Increase from 30s
],
```

Or disable polling entirely in custom page:

```php
public function table(Table $table): Table
{
    return parent::table($table)->poll(null);
}
```

## Performance Optimization

### Slow Order List

1. Ensure database indexes exist on frequently filtered columns.
2. Use eager loading for relations.
3. Limit search to indexed columns.

### Slow Dashboard Widgets

1. Widgets use 15-30 second cache by default.
2. For larger datasets, increase cache duration.
3. Consider reducing query complexity.

### Memory Issues with Large Orders

1. Paginate relation managers.
2. Use lazy loading for order items.
3. Consider chunking for exports.

## Debug Mode

Enable query logging to identify slow queries:

```php
// AppServiceProvider.php
if (config('app.debug')) {
    DB::listen(function ($query) {
        if ($query->time > 100) { // > 100ms
            Log::warning('Slow query', [
                'sql' => $query->sql,
                'time' => $query->time,
            ]);
        }
    });
}
```

## Cache Keys

The package uses these cache keys:

| Key Pattern | TTL | Description |
|-------------|-----|-------------|
| `filament-orders.stats.*` | 15s | Stats widget data |
| `filament-orders.status-distribution.*` | 30s | Status chart data |

Clear specific cache:

```php
Cache::forget('filament-orders.stats.tenant:1.owner-only.2024-01-15');
```

## Getting Help

1. Check the [Configuration](03-configuration.md) documentation
2. Review Filament documentation for base component issues
3. Check Laravel logs in `storage/logs/laravel.log`
4. Open an issue on the GitHub repository

### Useful Debug Commands

```bash
# Check routes
php artisan route:list --name=filament-orders

# Check config
php artisan config:show orders
php artisan config:show filament-orders

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```
