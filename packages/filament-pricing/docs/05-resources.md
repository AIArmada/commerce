---
title: Resources
---

# Resources

## PriceListResource

The main resource for managing price lists.

### Model

```php
protected static ?string $model = PriceList::class;
```

### Navigation

```php
protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';
protected static ?string $recordTitleAttribute = 'name';

public static function getNavigationGroup(): string|UnitEnum|null
{
    return config('filament-pricing.navigation.group');
}

public static function getNavigationSort(): ?int
{
    $sort = config('filament-pricing.resources.navigation_sort.price_lists');

    return is_numeric($sort) ? (int) $sort : null;
}
```

### Multitenancy

The resource automatically scopes queries when owner mode is enabled:

```php
public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery();

    if (! (bool) config('pricing.features.owner.enabled', false)) {
        return $query;
    }

    $owner = self::resolveOwner();

    return $query->forOwner(
        $owner,
        (bool) config('pricing.features.owner.include_global', false),
    );
}
```

### Form Schema

Three-column layout:

**Main Content (2 columns)**:
- Price List Details section
- Scheduling section

**Sidebar (1 column)**:
- Settings section

### Table Columns

| Column | Type | Features |
|--------|------|----------|
| name | TextColumn | searchable, sortable |
| currency | TextColumn | badge |
| prices_count | TextColumn | `counts('prices')`, alignEnd |
| priority | TextColumn | numeric, sortable |
| is_default | IconColumn | boolean |
| deactivated_at | TextColumn | badge, `Active`/`Deactivated` via `formatStateUsing` + `->color()` closure |
| starts_at | TextColumn | `dateTime('d M Y')`, toggleable (hidden by default) |
| ends_at | TextColumn | `dateTime('d M Y')`, toggleable (hidden by default) |

Default sort is `priority` descending. Filters: a `TernaryFilter` on `deactivated_at`
(Active/Deactivated) and one on `is_default`.

### Pages

```php
public static function getPages(): array
{
    return [
        'index' => Pages\ListPriceLists::route('/'),
        'create' => Pages\CreatePriceList::route('/create'),
        'view' => Pages\ViewPriceList::route('/{record}'),
        'edit' => Pages\EditPriceList::route('/{record}/edit'),
    ];
}
```

### Relation Managers

```php
public static function getRelations(): array
{
    // Only available if products package installed
    return [
        RelationManagers\PricesRelationManager::class,
        RelationManagers\TiersRelationManager::class,
    ];
}
```

## Promotions

Promotion administration is provided by `aiarmada/filament-promotions`. This package exposes only pricing resources and optional promotion statistics.

---

## Extending Resources

> **warning**
> `PriceListResource`, `PricesRelationManager`, `TiersRelationManager`, `PriceListForm`, `PriceListInfolist`, and `PriceListsTable` are all declared `final`. `class CustomPriceListResource extends PriceListResource` is a fatal error, not a style violation.

The resource delegates its schema and table to `final` support classes, so there is no
`extends` seam. The supported customization points are:

| Seam | Where |
|------|-------|
| Navigation group / sort | `config/filament-pricing.php` → `navigation.group`, `resources.navigation_sort.price_lists` |
| Settings page ability | `config/filament-pricing.php` → `authorization.settings_ability` |
| Page navigation sort | `config/filament-pricing.php` → `pages.navigation_sort.*` |
| App-specific pricing screens | Register your own `Resource` on the panel alongside this one |

```php
// In your own FilamentServiceProvider — a new Resource, not a subclass
$panel->resources([
    \AIArmada\FilamentPricing\Resources\PriceListResource::class,
    \App\Filament\Resources\PriceMarkupResource::class,
]);
```

### Custom Relation Manager

Write a standalone `RelationManager` for the relationship you need:

```php
namespace App\Filament\Resources\PriceListResource\RelationManagers;

use AIArmada\Pricing\Models\PriceList;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;

final class PromotionalPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $title = 'Promotional Prices';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('amount')
                    ->label('Price (cents)')
                    ->numeric()
                    ->required(),
            ])
            ->columns(2);
    }
}
```

---

## PricesRelationManager

Manages prices within a price list.

### Relationship

```php
protected static string $relationship = 'prices';
protected static ?string $title = 'Prices';
```

### Key Features

1. **Dynamic Type Selection**: Switch between Product and Variant
2. **Owner-Scoped Searches**: Respects multitenancy for product/variant searches
3. **Formatted Money Display**: Prices shown in MYR format

### Form Fields

```php
// Type selector
Forms\Components\Select::make('priceable_type')
    ->options([
        Product::class => 'Product',
        Variant::class => 'Variant',
    ])
    ->live()

// Dynamic search based on type
Forms\Components\Select::make('priceable_id')
    ->searchable()
    ->getSearchResultsUsing(/* owner-scoped query */)
```

---

## TiersRelationManager

Manages price tiers within a price list.

### Relationship

```php
protected static string $relationship = 'tiers';
protected static ?string $title = 'Price Tiers';
protected static ?string $recordTitleAttribute = 'min_quantity';
```

### Key Features

1. **Quantity Range Preview**: Shows "10-49" or "50+" format
2. **Discount Type Indicator**: Badges for percentage/fixed/price
3. **Computed Columns**: Displays tierable item name dynamically

### Form Sections

**Tier Configuration**:
- Type selector (Product/Variant)
- Item selector
- Min/Max quantity with range preview

**Pricing**:
- Amount in cents
- Optional discount type
- Optional discount value
- Currency selector
