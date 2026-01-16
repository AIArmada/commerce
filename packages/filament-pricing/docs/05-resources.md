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
protected static string|UnitEnum|null $navigationGroup = 'Pricing';
protected static ?int $navigationSort = 1;
protected static ?string $recordTitleAttribute = 'name';
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
| prices_count | TextColumn | counts relation |
| priority | TextColumn | numeric, sortable |
| is_default | IconColumn | boolean |
| is_active | IconColumn | boolean |
| starts_at | TextColumn | datetime, toggleable |
| ends_at | TextColumn | datetime, toggleable |

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

---

## PromotionResource

Resource for managing promotions (conditional on `aiarmada/promotions`).

### Model

```php
protected static ?string $model = Promotion::class;
```

### Navigation

```php
protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-gift';
protected static string|UnitEnum|null $navigationGroup = 'Pricing';
protected static ?int $navigationSort = 2;
protected static ?string $recordTitleAttribute = 'name';
```

### Navigation Badge

Shows count of active promotions:

```php
public static function getNavigationBadge(): ?string
{
    $count = static::getEloquentQuery()
        ->where('is_active', true)
        ->count();

    return $count ? (string) $count : null;
}
```

### Duplicate Action

Custom table action to duplicate promotions:

```php
Actions\Action::make('duplicate')
    ->label('Duplicate')
    ->icon('heroicon-o-document-duplicate')
    ->authorize(fn (): bool => static::canCreate())
    ->action(function (Promotion $record) {
        $new = $record->replicate();
        $new->name = $record->name . ' (Copy)';
        $new->code = null;
        $new->usage_count = 0;
        $new->save();

        return redirect(static::getUrl('edit', ['record' => $new]));
    })
```

### Table Columns

| Column | Type | Features |
|--------|------|----------|
| name | TextColumn | searchable, sortable, with code description |
| type | TextColumn | badge, colored by type |
| discount_value | TextColumn | formatted by type |
| usage_count | TextColumn | shows limit if set |
| is_active | IconColumn | boolean |
| starts_at | TextColumn | datetime, placeholder |
| ends_at | TextColumn | datetime, placeholder |

---

## Extending Resources

### Custom PriceListResource

```php
namespace App\Filament\Resources;

use AIArmada\FilamentPricing\Resources\PriceListResource as BaseResource;
use Filament\Schemas\Schema;

class CustomPriceListResource extends BaseResource
{
    public static function form(Schema $schema): Schema
    {
        $baseSchema = parent::form($schema);
        
        // Add custom fields
        return $baseSchema;
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->columns([
                // Add custom columns
            ]);
    }
}
```

### Custom Relation Manager

```php
namespace App\Filament\Resources\PriceListResource\RelationManagers;

use AIArmada\FilamentPricing\Resources\PriceListResource\RelationManagers\PricesRelationManager as BaseManager;

class CustomPricesRelationManager extends BaseManager
{
    public function form(Schema $schema): Schema
    {
        return parent::form($schema)->schema([
            // Override form fields
        ]);
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
