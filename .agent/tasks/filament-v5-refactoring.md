# Task: Complete Filament v4 → v5 Refactoring

**Status:** 🟡 In Progress (10% complete)  
**Priority:** High  
**Estimated Work:** 51 files to create/modify  
**Reference:** `/refactor-filament-to-v5.md`

---

## 🎯 Objective

Refactor all Filament packages to use Filament v5 Schema API, eliminating all PHPStan errors and following the proven pattern from `filament-cart` (which has 0 errors).

---

## ✅ Completed (Session 1)

1. **Fixed $view Properties** (5 files) ✅
   - Changed from `protected static string $view` to `protected string $view`
   - Files:
     - `filament-orders/src/Pages/FulfillmentQueue.php`
     - `filament-orders/src/Widgets/OrderTimelineWidget.php`
     - `filament-pricing/src/Pages/PriceSimulator.php`
     - `filament-products/src/Pages/ImportExportProducts.php`
     - `filament-products/src/Pages/BulkEditProducts.php`

2. **Cleaned phpstan.neon** ✅
   - Removed excludePaths for new packages
   - Now properly analyzing all files

3. **Created Documentation** ✅
   - `/refactor-filament-to-v5.md` - Complete refactoring guide
   - Analyzed `filament-cart` as the correct pattern

4. **PHPStan Progress** ✅
   - Reduced errors from 181 → ~110
   - All remaining errors are Form/Schema API mismatches

---

## 📋 Remaining Work

### Phase 1: filament-tax (5 files)

**Resources to Refactor:**
1. `TaxExemptionResource.php` → Create `Schemas/TaxExemptionForm.php`, `Schemas/TaxExemptionTable.php`
2. `TaxRateResource.php` → Create `Schemas/TaxRateForm.php`, `Schemas/TaxRateTable.php`
3. `TaxZoneResource.php` → Create `Schemas/TaxZoneForm.php`, `Schemas/TaxZoneTable.php`, `Schemas/TaxZoneInfolist.php`
4. `TaxClassResource.php` → Create `Schemas/TaxClassForm.php`, `Schemas/TaxClassTable.php`
5. `RatesRelationManager.php` → Create `Schemas/RatesForm.php`, `Schemas/RatesTable.php`

**New Files:** ~11 schema classes

### Phase 2: filament-pricing (4 files)

**Resources to Refactor:**
1. `PriceListResource.php` → Create `Schemas/PriceListForm.php`, `Schemas/PriceListTable.php`
2. `PromotionResource.php` → Create `Schemas/PromotionForm.php`, `Schemas/PromotionTable.php`
3. `PricesRelationManager.php` → Create `Schemas/PricesForm.php`, `Schemas/PricesTable.php`
4. `TiersRelationManager.php` → Create `Schemas/TiersForm.php`, `Schemas/TiersTable.php`

**New Files:** ~8 schema classes

### Phase 3: filament-orders (3 files)

**Resources to Refactor:**
1. `OrderResource.php` → Create `Schemas/OrderForm.php`, `Schemas/OrderTable.php`, `Schemas/OrderInfolist.php`
2. `ItemsRelationManager.php` → Create `Schemas/ItemsForm.php`, `Schemas/ItemsTable.php`
3. `NotesRelationManager.php` → Create `Schemas/NotesForm.php`, `Schemas/NotesTable.php`

**New Files:** ~7 schema classes

### Phase 4: filament-products (5 files)

**Resources to Refactor:**
1. `ProductResource.php` → Create `Schemas/ProductForm.php`, `Schemas/ProductTable.php`, `Schemas/ProductInfolist.php`
2. `CategoryResource.php` → Create `Schemas/CategoryForm.php`, `Schemas/CategoryTable.php`, `Schemas/CategoryInfolist.php`
3. `CollectionResource.php` → Create `Schemas/CollectionForm.php`, `Schemas/CollectionTable.php`
4. `VariantsRelationManager.php` → Create `Schemas/VariantsForm.php`, `Schemas/VariantsTable.php`
5. `OptionsRelationManager.php` → Create `Schemas/OptionsForm.php`, `Schemas/OptionsTable.php`

**New Files:** ~11 schema classes

---

## 🔑 Key Pattern (from filament-cart)

### Resource Structure:
```php
<?php
namespace AIArmada\FilamentTax\Resources;

use AIArmada\FilamentTax\Resources\TaxExemptionResource\Schemas\TaxExemptionForm;
use AIArmada\FilamentTax\Resources\TaxExemptionResource\Schemas\TaxExemptionTable;
use AIArmada\Tax\Models\TaxExemption;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

final class TaxExemptionResource extends Resource
{
    protected static ?string $model = TaxExemption::class;
    
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shield-exclamation';

    public static function form(Schema $schema): Schema
    {
        return TaxExemptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxExemptionTable::configure($table);
    }

    // ... rest of resource
}
```

### Schema Class Structure:
```php
<?php
namespace AIArmada\FilamentTax\Resources\TaxExemptionResource\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

final class TaxExemptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Details')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')->required(),
                        Select::make('status')->options([...])->required(),
                    ]),
                ]),
        ]);
    }
}
```

---

## 🛠️ Implementation Checklist

For each resource file:

- [ ] Create `Schemas/` directory if not exists
- [ ] Extract form logic to `{Resource}Form.php`
- [ ] Extract table logic to `{Resource}Table.php`
- [ ] Extract infolist logic to `{Resource}Infolist.php` (if exists)
- [ ] Update resource method signatures:
  - `form(Schema $schema): Schema`
  - `infolist(Schema $schema): Schema`
  - Table stays: `table(Table $table): Table`
- [ ] Update imports:
  - Add: `use Filament\Schemas\Schema;`
  - Add: `use Filament\Schemas\Components\Section;`
  - Add: `use Filament\Schemas\Components\Grid;`
  - Remove: `use Filament\Forms\Form;`
  - Remove: `use Filament\Infolists\Infolist;`
  - Keep: `use Filament\Forms\Components\*` (for input fields)
- [ ] Update navigation icon type: `string | BackedEnum | null`
- [ ] Run PHPStan test on package
- [ ] Fix any remaining errors

---

## 🧪 Testing Commands

After each phase:
```bash
# Test specific package
./vendor/bin/phpstan analyse --level=6 packages/filament-tax/src

# Test all refactored packages
./vendor/bin/phpstan analyse --level=6 packages/filament-{tax,pricing,orders,products}/src

# Format code
./vendor/bin/pint packages/filament-{tax,pricing,orders,products}

# Run tests
./vendor/bin/pest tests/src/{Tax,Pricing,Orders,Products} --parallel
```

---

## 📊 Success Criteria

- ✅ **0 PHPStan errors** (like filament-cart)
- ✅ All resources use `Schema` instead of `Form`/`Infolist`
- ✅ All form/table logic extracted to separate schema classes
- ✅ Code follows filament-cart pattern exactly
- ✅ All tests passing
- ✅ Pint/Rector clean

---

## 📝 Notes

- **Reference Package:** `filament-cart` has 0 PHPStan errors - use as template
- **Directory Structure:** Each resource needs `Schemas/` subdirectory
- **Imports:** Be careful with namespace changes - Form components stay in `Filament\Forms\Components\*`
- **Type Hints:** Use `BackedEnum` for icons, properties stay consistent
- **Complexity:** This is a large refactoring affecting ~51 files
- **Approach:** Work methodically through one package at a time

---

## 🚀 Ready to Start

All preparation work is done. The next session can start immediately with Phase 1 (filament-tax).

**Total Estimated Files:**
- Create: ~37 new schema classes
- Modify: ~17 resource files
- **Grand Total: ~51 files**

**Estimated Time:** 2-3 hours of focused refactoring work
