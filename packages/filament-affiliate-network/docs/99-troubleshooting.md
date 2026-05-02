---
title: Troubleshooting
---

# Troubleshooting

Common issues and solutions for the Filament Affiliate Network plugin.

## Installation Issues

### Plugin Not Appearing

**Symptoms:** Resources and pages don't show in panel.

**Solutions:**

1. Verify plugin registration:
```php
FilamentAffiliateNetworkPlugin::make(),
```

2. Clear caches:
```bash
php artisan filament:clear-cached-components
php artisan view:clear
php artisan config:clear
```

3. Verify core package is installed:
```bash
composer require aiarmada/affiliate-network
```

### Missing Heroicons

**Symptoms:** Icons don't display.

**Solution:** Plugin uses Filament v5 Heroicons. Ensure Filament is properly installed:

```bash
php artisan filament:install
```

## Resource Issues

### Empty Tables

**Symptoms:** Resources show no data.

**Solutions:**

1. Check data exists:
```php
AffiliateSite::withoutGlobalScopes()->count();
```

2. Verify owner scoping:
```php
app(OwnerResolverInterface::class)->resolve();
```

3. Check global scopes aren't over-filtering.

### Form Validation Errors

**Symptoms:** Forms fail to save.

**Solutions:**

1. Check required fields are filled.

2. Verify unique constraints:
```php
// Domain must be unique
TextInput::make('domain')->unique(ignoreRecord: true)
```

3. Check foreign key relationships exist.

## Marketplace Issues

### Marketplace Returns 404

**Symptoms:** `/affiliate-network/marketplace` not found.

**Solutions:**

1. Verify feature is enabled:
```php
'marketplace' => [
    'show_commission_rates' => true,
    'show_cookie_duration' => true,
],
```

2. Check routes:
```bash
php artisan route:list | grep marketplace
```

3. Clear route cache:
```bash
php artisan route:clear
```

### Can't Apply to Offers

**Symptoms:** Apply button doesn't work or shows error.

**Solutions:**

1. User must have affiliate record:
```php
$user->affiliate; // Must not be null
```

2. Check affiliate email matches user:
```php
Affiliate::where('contact_email', $user->email)->first();
```

3. Check cooldown period for rejected applications.

### Links Not Generating

**Symptoms:** "Get Link" button fails.

**Solutions:**

1. Verify application is approved:
```php
$application->status === 'approved';
```

2. Check OfferLinkService is registered:
```php
app(\AIArmada\AffiliateNetwork\Services\OfferLinkService::class);
```

## Widget Issues

### Widgets Not Displaying

**Symptoms:** Dashboard widgets missing.

**Solutions:**

1. Widgets auto-register via plugin. Verify plugin is registered.

2. Check authorization:
```php
public static function canView(): bool
{
    return true; // Ensure this returns true
}
```

3. Manually register widgets:
```php
$panel->widgets([
    NetworkStatsWidget::class,
    TopOffersWidget::class,
]);
```

### Widget Shows Wrong Data

**Symptoms:** Stats don't match expected values.

**Solutions:**

1. Check owner scoping is consistent.

2. Verify query logic:
```php
AffiliateOfferLink::sum('clicks');
```

3. Clear cache if using cached queries.

## View Issues

### Views Not Found

**Symptoms:** View file errors.

**Solutions:**

1. Publish views:
```bash
php artisan vendor:publish --tag=filament-affiliate-network-views
```

2. Clear view cache:
```bash
php artisan view:clear
```

### Blade Component Errors

**Symptoms:** Unknown component errors.

**Solutions:**

1. Ensure Filament components are available:
```bash
php artisan filament:install
```

2. Check Livewire is installed:
```bash
composer require livewire/livewire
```

## Multi-Tenancy Issues

### Cross-Tenant Data Visible

**Symptoms:** Users see other tenants' data.

**Solutions:**

1. Verify owner scoping enabled in core package:
```env
AFFILIATE_NETWORK_OWNER_ENABLED=true
```

2. Check Filament tenant configuration aligns with owner resolver.

3. Override `getEloquentQuery()` in resources if needed:
```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        // Add additional scoping if needed
        ->where('some_condition', true);
}
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

### Inspect Component

In browser dev tools, find Livewire component and check:
- Component properties
- Method calls
- Error messages

### Filament Debug Bar

```bash
composer require --dev barryvdh/laravel-debugbar
```

## Getting Help

If issues persist:

1. Check [core package troubleshooting](../affiliate-network/99-troubleshooting.md)
2. Review Filament v5 documentation
3. Open an issue with:
   - PHP/Laravel/Filament versions
   - Package versions
   - Full error message
   - Steps to reproduce
