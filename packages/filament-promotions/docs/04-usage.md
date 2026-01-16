---
title: Usage
---

# Usage

This guide covers using the Filament Promotions resource to manage promotional discounts.

## Promotion Resource

The `PromotionResource` provides full CRUD operations for promotions.

### List View

The promotions table displays:

| Column | Description |
|--------|-------------|
| Name | Promotion name (searchable) |
| Code | Promo code badge (or "Auto" for automatic) |
| Type | Discount type with color badge |
| Discount | Formatted discount value |
| Uses | Usage count |
| Active | Boolean status icon |
| Stack | Stackable status icon |

**Filters:**
- Type (Percentage, Fixed, BuyXGetY)
- Active status
- Stackable status
- Has promo code

**Actions:**
- View promotion details
- Edit promotion
- Delete promotion
- Bulk delete

### Create View

The create form includes sections:

1. **Basic Information** — Name, description, promo code
2. **Discount Configuration** — Type, value, min/max limits
3. **Usage Limits** — Total and per-customer limits
4. **Scheduling** — Start and end dates
5. **Targeting Conditions** — Key-value conditions
6. **Options** — Active, stackable, priority

### Edit View

Same form as create, with current values populated.

### View View

Displays promotion details in an infolist format with:
- Basic information section
- Discount configuration with formatted values
- Usage statistics
- Schedule dates
- Status icons
- Targeting conditions (collapsible)

## Promotion Types

The Filament-aware `PromotionType` enum provides UI enhancements:

```php
use AIArmada\FilamentPromotions\Enums\PromotionType;

$type = PromotionType::Percentage;

$type->getLabel();  // "Percentage Off"
$type->getIcon();   // "heroicon-o-receipt-percent"
$type->getColor();  // "success"
```

### Type Reference

| Type | Label | Icon | Color |
|------|-------|------|-------|
| `Percentage` | Percentage Off | receipt-percent | success (green) |
| `Fixed` | Fixed Amount | currency-dollar | info (blue) |
| `BuyXGetY` | Buy X Get Y | gift | warning (yellow) |

## Stats Widget

Add the stats widget to your panel dashboard:

```php
use AIArmada\FilamentPromotions\Widgets\PromotionStatsWidget;

public function panel(Panel $panel): Panel
{
    return $panel
        ->widgets([
            PromotionStatsWidget::class,
        ]);
}
```

The widget displays:
- **Total Promotions** — All promotion count
- **Active** — Currently active promotions
- **Promo Codes** — Code-based promotions
- **Automatic** — Auto-applying active promotions

## Customizing the Resource

### Extend the Resource

```php
namespace App\Filament\Resources;

use AIArmada\FilamentPromotions\Resources\PromotionResource as BaseResource;

class PromotionResource extends BaseResource
{
    public static function getNavigationGroup(): ?string
    {
        return 'Sales';
    }

    public static function getRelations(): array
    {
        return [
            // Add custom relation managers
        ];
    }
}
```

### Custom Form Fields

Extend the form schema:

```php
use AIArmada\FilamentPromotions\Resources\PromotionResource\Schemas\PromotionForm;

class CustomPromotionForm extends PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return parent::configure($schema)
            ->components([
                // Add custom components
            ]);
    }
}
```

## Working with Owner Scoping

When owner scoping is enabled, the resource automatically filters promotions by owner:

```php
// In PromotionResource
public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery();

    return OwnerScopedQueries::scopePromotion($query);
}
```

To customize the scoping logic, extend `OwnerScopedQueries`.
