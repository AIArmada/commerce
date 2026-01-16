---
title: Troubleshooting
---

# Troubleshooting

Common issues and solutions when working with filament-promotions.

## Plugin Not Appearing

### Check Registration

Ensure the plugin is registered in your panel provider:

```php
use AIArmada\FilamentPromotions\FilamentPromotionsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentPromotionsPlugin::make(),
        ]);
}
```

### Clear Cache

```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

### Check Service Provider

Verify the service provider is loaded:

```bash
php artisan package:discover --ansi
```

## Promotions Not Showing

### Check Database

Verify promotions exist:

```php
use AIArmada\FilamentPromotions\Models\Promotion;

Promotion::all();
```

### Check Owner Scoping

If owner scoping is enabled, verify the current owner context:

```php
// config/promotions.php
'features' => [
    'owner' => [
        'enabled' => true,      // Is this enabled?
        'include_global' => true, // Are global rows included?
    ],
],
```

### Check Eloquent Query

Debug the resource query:

```php
use AIArmada\FilamentPromotions\Resources\PromotionResource;

$query = PromotionResource::getEloquentQuery();
dump($query->toSql(), $query->getBindings());
```

## Type Enum Issues

### Wrong Enum Class

Ensure you're using the Filament-aware enum:

```php
// Correct - Filament version with HasColor/HasIcon/HasLabel
use AIArmada\FilamentPromotions\Enums\PromotionType;

// Wrong - Base version without UI contracts
use AIArmada\Promotions\Enums\PromotionType;
```

### Convert Between Enums

```php
use AIArmada\FilamentPromotions\Enums\PromotionType as FilamentType;
use AIArmada\Promotions\Enums\PromotionType as BaseType;

$baseType = BaseType::Percentage;
$filamentType = FilamentType::fromBase($baseType);

$filamentType = FilamentType::Fixed;
$baseType = $filamentType->toBase();
```

## Widget Not Displaying

### Check Feature Toggle

```php
// config/filament-promotions.php
'features' => [
    'widgets' => true, // Must be true
],
```

### Register Widget

Ensure the widget is registered in your panel:

```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->widgets([
            \AIArmada\FilamentPromotions\Widgets\PromotionStatsWidget::class,
        ]);
}
```

## Form Validation Errors

### Unique Code Constraint

The promo code must be unique. If editing a promotion, the constraint ignores the current record:

```php
TextInput::make('code')
    ->unique(ignoreRecord: true),
```

### Required Fields

These fields are required:
- `name`
- `type`
- `discount_value`

## Navigation Issues

### Wrong Group

Customize the navigation group:

```php
// config/filament-promotions.php
'navigation_group' => 'Your Group',
```

Or set to `null` for root navigation.

### Wrong Order

Adjust navigation sort:

```php
'resources' => [
    'navigation_sort' => [
        'promotions' => 5, // Lower = higher position
    ],
],
```

## Performance Issues

### Slow Table Loading

For large datasets, consider:

1. Disable the navigation badge count
2. Add database indexes
3. Enable pagination

```php
// Disable badge in custom resource
public static function getNavigationBadge(): ?string
{
    return null;
}
```

## Getting Help

1. Check the [documentation](01-overview.md)
2. Review [configuration options](03-configuration.md)
3. Search existing issues on GitHub
4. Open a new issue with:
   - PHP, Laravel, Filament versions
   - Package version
   - Minimal reproduction steps
   - Expected vs actual behavior
