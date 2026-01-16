---
title: Troubleshooting
---

# Troubleshooting

Common issues and solutions for the Filament Affiliates plugin.

## Installation Issues

### Plugin Not Appearing in Panel

**Symptoms:** Resources and widgets don't show in your Filament panel.

**Solutions:**

1. Verify plugin registration in your panel provider:

```php
use AIArmada\FilamentAffiliates\FilamentAffiliatesPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentAffiliatesPlugin::make(),
        ]);
}
```

2. Clear caches:

```bash
php artisan filament:clear-cached-components
php artisan view:clear
php artisan config:clear
```

3. Ensure the core `affiliates` package is installed:

```bash
composer require aiarmada/affiliates
```

### Missing Icons

**Symptoms:** Broken or missing icons in navigation/tables.

**Solution:** The plugin uses Heroicons v2. Ensure Filament v5 is properly installed:

```bash
php artisan filament:install
```

## Portal Issues

### Portal Returns 404

**Symptoms:** Accessing `/affiliate` returns 404.

**Solutions:**

1. Verify portal is enabled:

```php
// config/filament-affiliates.php
'portal' => [
    'enabled' => true,
    'path' => 'affiliate',
],
```

2. Check that routes are registered:

```bash
php artisan route:list | grep affiliate
```

3. If using custom panel provider, ensure it extends `PanelProvider`:

```php
use Filament\PanelProvider;

class AffiliatePortalProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('affiliate')
            ->path('affiliate');
    }
}
```

### Authentication Loop

**Symptoms:** Login redirects back to login page.

**Solutions:**

1. Check auth guard matches your configuration:

```php
'portal' => [
    'auth_guard' => 'web', // Must match your auth setup
],
```

2. Verify user has affiliate record:

```php
$user = auth()->user();
$user->affiliate; // Must not be null
```

3. Check affiliate status is active:

```php
$user->affiliate->status; // Should be AffiliateStatus::Active
```

## Resource Issues

### Empty Tables

**Symptoms:** Resources show no data.

**Solutions:**

1. Check owner scoping (multi-tenant):

```php
// Verify owner context is set
app(OwnerResolverInterface::class)->getOwner();
```

2. Check database has records:

```bash
php artisan tinker
> \AIArmada\Affiliates\Models\Affiliate::withoutGlobalScopes()->count()
```

3. Verify Filament tenant configuration if using tenancy.

### Missing Relation Managers

**Symptoms:** Relation managers don't appear on resource pages.

**Solution:** Ensure relations are defined in your models:

```php
// App\Models\User
public function affiliate(): HasOne
{
    return $this->hasOne(\AIArmada\Affiliates\Models\Affiliate::class);
}
```

## Widget Issues

### Widgets Not Displaying

**Symptoms:** Dashboard widgets don't appear.

**Solutions:**

1. Register widgets in your plugin or panel:

```php
FilamentAffiliatesPlugin::make()
    ->widgets([
        \AIArmada\FilamentAffiliates\Widgets\AffiliateStatsWidget::class,
    ]);
```

2. Check authorization:

```php
public static function canView(): bool
{
    return auth()->user()->can('viewAffiliateWidgets');
}
```

3. Verify widget data exists:

```php
// Check if there's data to display
AffiliateConversion::count();
```

### Charts Not Rendering

**Symptoms:** Chart widgets show empty or broken.

**Solutions:**

1. Ensure Chart.js assets are loaded (Filament handles this automatically).

2. Check browser console for JavaScript errors.

3. Verify data format:

```php
protected function getData(): array
{
    return [
        'datasets' => [...],
        'labels' => [...], // Must not be empty
    ];
}
```

## Performance Issues

### Slow Resource Loading

**Symptoms:** Resource pages take too long to load.

**Solutions:**

1. Add eager loading to queries:

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->with(['affiliate', 'program', 'tier']);
}
```

2. Limit default records per page:

```php
protected static int $defaultPaginationPageOption = 25;
```

3. Use table polling only when necessary:

```php
protected static ?string $pollingInterval = null; // Disable auto-refresh
```

### Memory Issues with Large Exports

**Symptoms:** Export actions fail or timeout.

**Solutions:**

1. Use chunked exports:

```php
Tables\Actions\ExportBulkAction::make()
    ->chunkSize(1000);
```

2. Increase memory limit for export jobs:

```php
// In your queue worker
php artisan queue:work --memory=512
```

## Multi-Tenancy Issues

### Cross-Tenant Data Leaking

**Symptoms:** Users see data from other tenants.

**Solutions:**

1. Verify owner scoping in resource queries:

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->whereHas('affiliate', fn ($q) => $q->forCurrentOwner());
}
```

2. Check `HasOwner` trait is used on models.

3. Review action handlers for owner validation.

### Filament Tenancy Conflicts

**Symptoms:** Filament tenancy interferes with owner scoping.

**Solution:** Owner scoping is independent of Filament tenancy. Ensure both are configured correctly and don't conflict:

```php
// If using Filament tenancy for navigation only
$panel->tenant(Team::class, 'team');

// Owner scoping still applies via HasOwner trait
```

## Debugging Tips

### Enable Debug Mode

```php
// config/app.php
'debug' => true,
```

### Check Logs

```bash
tail -f storage/logs/laravel.log
```

### Inspect Queries

```php
DB::enableQueryLog();
// ... perform action
dd(DB::getQueryLog());
```

### Filament Debug Bar

Install for detailed Filament debugging:

```bash
composer require --dev barryvdh/laravel-debugbar
```

## Getting Help

If issues persist:

1. Check [Filament documentation](https://filamentphp.com/docs)
2. Review core `affiliates` package troubleshooting
3. Search existing issues on GitHub
4. Open a new issue with:
   - PHP version
   - Laravel version
   - Filament version
   - Package versions
   - Error messages
   - Steps to reproduce
