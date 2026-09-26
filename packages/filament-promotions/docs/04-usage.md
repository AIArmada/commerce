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
- Type (Percentage, Fixed)
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

Create and deactivate go through domain actions (`Pages\CreatePromotion` calls `CreatePromotion::handle($data)`; `Pages\EditPromotion` deactivate header action calls `DeactivatePromotion::handle($record)`) so validation, code normalization, and events stay in `aiarmada/promotions`.

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
| `Fixed` | Fixed Amount | currency-dollar | primary |

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

`PromotionResource` and `PromotionForm` are both declared `final`, so neither
can be subclassed. To change the navigation group, use the config key the
resource already reads — do not hardcode a group string:

```php
// config/filament-promotions.php
'navigation' => [
    'group' => 'Sales',
],
```

To add columns, filters, or fields, register your own resource against
`AIArmada\Promotions\Models\Promotion` in your panel provider, or extend the
table/form classes through composition:

```php
namespace App\Filament\Resources;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\Promotions\Models\Promotion;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return config('filament-promotions.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-promotions.resources.navigation_sort.promotions');
    }

    public static function table(Table $table): Table
    {
        // `type` is cast to AIArmada\Promotions\Enums\PromotionType
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('discount_value')
                ->label('Discount')
                ->formatStateUsing(fn ($state, Promotion $record): string => $record->type->value === 'percentage'
                    ? $state . '%'
                    : MoneyFormatter::formatMinor($state, 'MYR')),
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
