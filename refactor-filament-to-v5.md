# Filament v4 → v5 Refactoring Guide

## STATUS: PARTIAL - $view properties fixed ✅

### Completed
- ✅ Changed all `$view` properties from `static` to non-static (5 files)
- ✅ Removed phpstan.neon excludePaths for new packages

### Remaining Work

This refactoring requires changing from Filament v4 API to v5 API across **17 Resource files**.

## Key Changes Required

### 1. Resource Method Signatures

**❌ OLD (Filament v4):**
```php
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;

public static function form(Form $form): Form
{
    return $form->schema([...]);
}

public static function table(Table $table): Table  
{
    return $table->columns([...]);
}

public static function infolist(Infolist $infolist): Infolist
{
    return $infolist->schema([...]);
}
```

**✅ NEW (Filament v5):**
```php
use Filament\Schemas\Schema;
use Filament\Tables\Table;

public static function form(Schema $schema): Schema
{
    return ResourceForm::configure($schema);
}

public static function table(Table $table): Table
{
    return ResourceTable::configure($table);
}

public static function infolist(Schema $schema): Schema
{
    return ResourceInfolist::configure($schema);
}
```

### 2. Extract to Schema Classes

Create separate schema classes in `Resources/{Resource}/Schemas/`:
- `{Resource}Form.php` - Form logic
- `{Resource}Table.php` - Table logic  
- `{Resource}Infolist.php` - Infolist logic (if exists)

**Example Structure:**
```php
<?php
namespace AIArmada\FilamentTax\Resources\TaxExemptionResource\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class TaxExemptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Details')
                ->schema([
                    TextInput::make('name')->required(),
                    // ... rest of form fields
                ]),
        ]);
    }
}
```

### 3. Update Imports

**❌ Remove:**
```php
use Filament\Forms\Form;
use Filament\Forms\Components\Group;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
```

**✅ Add:**
```php
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Fieldset;
```

**Note:** Form input components stay in `Filament\Forms\Components\*`

## Files to Refactor

### filament-tax (5 resources)
- [ ] TaxExemptionResource.php
- [ ] TaxRateResource.php
- [ ] TaxZoneResource.php
- [ ] TaxClassResource.php
- [ ] RatesRelationManager.php

### filament-pricing (4 resources)
- [ ] PriceListResource.php
- [ ] PromotionResource.php
- [ ] PricesRelationManager.php
- [ ] TiersRelationManager.php

### filament-orders (3 resources)
- [ ] OrderResource.php
- [ ] ItemsRelationManager.php
- [ ] NotesRelationManager.php

### filament-products (5 resources)
- [ ] ProductResource.php
- [ ] CategoryResource.php
- [ ] CollectionResource.php
- [ ] VariantsRelationManager.php
- [ ] OptionsRelationManager.php

## Estimated New Files

**~51 new schema files** to create:
- 17 resources × 2 (Form + Table) = 34 files
- 10 resources with Infolists × 1 = 10 files
- Plus existing ~7 existing schemas = ~51 total

## Reference Package

**filament-cart** has ZERO PHPStan errors - use it as the template!

Key files to study:
- `/packages/filament-cart/src/Resources/CartResource.php`
- `/packages/filament-cart/src/Resources/CartResource/Schemas/CartForm.php`
- `/packages/filament-cart/src/Resources/CartResource/Tables/CartsTable.php`

## Next Steps

1. Start with smallest package (filament-tax)
2. Create Schemas directory for each resource
3. Extract form/table/infolist logic to schema classes
4. Update resource method signatures
5. Update imports
6. Test with PHPStan
7. Repeat for next package

## Testing Command

```bash
./vendor/bin/phpstan analyse --level=6 packages/filament-{tax,pricing,orders,products}/src
```

## Expected Outcome

- ✅ 0 PHPStan errors (like filament-cart)
- ✅ All resources follow Filament v5 Schema API
- ✅ Code matches proven working pattern
