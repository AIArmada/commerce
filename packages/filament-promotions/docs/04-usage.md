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
- Issue vouchers from the promotion (when voucher issuance tracking is available)
- Delete promotion
- Bulk delete

The list page also includes analytics widgets so admins can see performance without leaving the resource:

- `PromotionStatsWidget`
- `TopPromotionsUsageChart`

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

When promotion-issued voucher tracking is supported, the record view also exposes an `IssuedVouchersRelationManager` so admins can review the generated vouchers without leaving the promotion.

## Issuing vouchers from promotions

When the vouchers package is installed and `Promotion::supportsIssuedVoucherTracking()` returns `true`, Filament Promotions adds two issuance entry points:

- `IssuePromotionVouchersAction` on promotion record pages and table rows
- `IssuePromotionVouchersFromListAction` on the list page for issuing without opening a record first

Both actions let admins choose a voucher count and optional code prefix. Global promotions are issued inside explicit global context automatically, while owner-scoped promotions are revalidated through `OwnerWriteGuard` before vouchers are created.

The generated vouchers are one-time use by default and remain linked back to the source promotion via `promotion_id`, which powers the issued-vouchers relation manager and downstream voucher reporting.

## Promotion Types

The `PromotionType` enum provides display helpers:

```php
use AIArmada\Promotions\Enums\PromotionType;

$type = PromotionType::Percentage;

$type->label();  // "Percentage Off"
$type->icon();   // "heroicon-o-receipt-percent"
$type->color();  // "success"
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
- **Total Promotions** — All configured promotions
- **Active Promotions** — Active promotion count with code/automatic breakdown
- **Orders Influenced** — Orders whose `discount_data.promotions` payload includes a promotion
- **Influenced Revenue** — Revenue from influenced orders when a single reporting currency is available
- **Discount Attributed** — Summed applied promotion discounts from order metadata

When the Orders package is installed, these widgets use `order.metadata.discount_data.promotions` as the source of truth. The chart prefers top promotions by influenced orders and falls back to usage counts only when order-backed analytics are unavailable.

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

    return $query->forOwner($owner);
}

```

To customize the scoping logic, override `getEloquentQuery` in your resource subclass.
