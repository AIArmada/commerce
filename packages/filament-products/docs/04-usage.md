---
title: Usage
---

# Usage

## Product Resource

The ProductResource provides a comprehensive interface for managing products.

### Product type vs behavior

The product form now separates **fulfillment/category semantics** from **behavior semantics**:

- `type` describes what kind of product you are modeling.
- `requires_shipping` describes whether the customer should go through shipping flows.
- `supports_variants` controls whether the product can manage purchasable sub-items such as dates, sizes, or editions.
- `tracks_inventory` controls whether stock should be consumed and validated.

This keeps the model orthogonal:

- `Configurable` remains the type for physical configurable goods.
- `Digital` products stay non-shipping by default.
- A `Digital` product can still opt into variants and inventory when the business case needs it, such as ticketed event dates.

### Form Structure

The product form is organized into tabs:

1. **Basic Information**
    - Name, SKU, Type, Status, Visibility
    - Supports Variants toggle
    - Track Inventory toggle
   - Short Description, Full Description
   - Featured toggle

2. **Pricing**
   - Price (stored in cents, displayed as currency)
   - Compare Price (original/strike-through price)
   - Cost (for margin calculations)
   - Taxable toggle

3. **Shipping / Inventory**
    - Requires Shipping toggle
   - Weight (grams)
   - Dimensions (length, width, height)

4. **Categories**
   - Multi-select with owner-scoped options

5. **SEO**
   - Meta Title, Meta Description

6. **Media**
   - Gallery images with conversions
   - Hero images
   - Documents

### Price Handling

Prices are automatically converted:
- **Display**: Stored cents → Displayed as currency (2999 → 29.99)
- **Save**: Input currency → Stored as cents (29.99 → 2999)

```php
// Form field with automatic conversion
Forms\Components\TextInput::make('price')
    ->numeric()
    ->prefix('RM')
    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
    ->dehydrateStateUsing(fn ($state) => $state ? (int) round($state * 100) : null);
```

### Optional customer selector

When `aiarmada/customers` is installed, the product form exposes its optional
customer selector. The field is guarded with `class_exists`, so the catalog
resource remains usable in installations that do not install the Customers
package.

### Variant Management

For any **variant-capable** product, use the Variants relation manager. Matrix generation failures surface inline: exceeding `products.features.variants.max_generated` shows a danger `Variant limit exceeded` notification with the combination count, while matrices above `queue_threshold` dispatch `GenerateVariantsJob` and toast `Variant generation started` (existing SKUs are skipped idempotently).

Common examples:

- Physical configurable goods: `Configurable` + variants + tracked inventory
- Digital event tickets: `Digital` + variants + tracked inventory
- Unlimited digital downloads: `Digital` without variants and without tracked inventory

When `supports_variants` is enabled:

1. Navigate to a variant-capable product
2. Click the "Variants" tab
3. Add/edit variants with:
   - SKU override
   - Price override
   - Weight/dimensions overrides
   - Option value assignments

The Options relation manager follows the same capability rule. If `supports_variants` is disabled, the variant-related managers stay hidden.

### Bulk Actions

Available bulk actions on the product table (see [Bulk Edit](#bulk-edit)):

- **Delete Selected**
- **Activate**
- **Set to Draft**
- **Update Price**
- **Update Visibility**
- **Assign Categories**

---

## Category Resource

### Tree View

Categories display in a hierarchical tree structure showing parent-child relationships.

### Creating Categories

1. Click "New Category"
2. Fill in name and optional parent
3. Set position for ordering
4. Toggle active status

### Managing Products

`CategoryResource::getRelations()` returns `[]` — there is no products relation
manager. The category table exposes a products count column, a parent filter,
and an **Add Child** row action. Assign products to a category from the product
side (Categories tab on the product form, or the **Assign Categories** bulk
action).

---

## Collection Resource

### Manual Collections

1. Create collection with type "Manual"
2. Add products from the collection form (owner-scoped `products` select,
   validated in `CreateCollection` / `EditCollection`)

### Automatic Collections

1. Create collection with type "Automatic"
2. Add conditions using the repeater:

```php
// Condition structure
[
    'field' => 'is_featured',
    'operator' => '=',
    'value' => true,
]
```

**Supported Fields**: Any product database column
**Supported Operators**: `=`, `!=`, `>`, `<`, `>=`, `<=`, `like`

---

## Attribute Resources

### Creating Attributes

1. Navigate to Attributes
2. Click "New Attribute"
3. Configure:
   - **Code**: Unique identifier (e.g., `material`, `fabric_weight`)
   - **Name**: Display name
   - **Type**: Text, Textarea, Number, Boolean, Select, MultiSelect, Date, DateTime
   - **Options**: For Select/MultiSelect types
   - **Validation**: Required, filterable, visible flags

### Attribute Groups

Organize attributes into logical groups:

1. Create group (e.g., "Specifications", "Dimensions")
2. Assign attributes to group

### Attribute Sets

Combine groups into sets for product types:

1. Create set (e.g., "Apparel", "Electronics")
2. Assign groups to set
3. Assign set to products

---

## Import / Export

There is no import/export page. Import, export, and template download are
header actions on the product list table (`ProductsTable::configure()`):

### Exporting Products

1. Open the product list (`/admin/products`)
2. Click **Export**
3. Tick the fields to export (name, SKU, slug, description, short description,
   currency, price, compare price, cost, weight, status, type, visibility,
   is_featured, is_taxable, requires_shipping, tax_class)
4. Optionally filter by status
5. Click **Export** to stream the CSV

### Importing Products

1. Open the product list
2. Click **Import**
3. Upload a CSV file (max size from `filament-products.import.max_file_kb`)
4. Toggle **Update Existing Products** to match rows by SKU
5. Toggle **Skip Errors** to continue past bad rows
6. Click **Import**

**CSV Format Requirements**:
- UTF-8 encoding
- Header row required
- Prices in cents

**Import guards**: files with more rows than `filament-products.import.max_rows`
are rejected before any row is written. New rows require a name and a numeric
price; invalid status, type, visibility, or currency cells are reported as row
errors instead of silently defaulting. Updates match by SKU and leave blank
cells unchanged. With "Skip Errors" off, the whole import runs in one
transaction and rolls back on the first bad row. Exports stream row by row, so
large catalogs do not exhaust memory.

A **Download CSV Template** header action emits a starter CSV with the expected
header row.

---

## Authorization

Every catalog resource gates access through `FilamentPermission` abilities:

| Resource | Ability prefix |
|----------|----------------|
| Products | `product.*` |
| Categories | `category.*` |
| Collections | `collection.*` |
| Attributes | `attribute.*` |
| Attribute groups | `attribute-group.*` |
| Attribute sets | `attribute-set.*` |

Each prefix supports `viewAny`, `view`, `create`, `update`, and `delete`. Bulk actions require the matching `update` (or `delete`) ability. Category slugs must be unique per owner and parent, matching the domain rule; a category cannot be moved under itself or one of its descendants.

---

## Bulk Edit

There is no bulk edit page. Bulk updates are table bulk actions on the product
list, available after selecting rows.

### Using Bulk Actions

1. Open the product list (`/admin/products`)
2. Select the products to edit
3. Choose a bulk action
4. Fill in any required values and confirm

### Available Bulk Actions

- **Delete Selected**
- **Activate** — sets `status` to Active
- **Set to Draft** — sets `status` to Draft
- **Update Price** — set, increase/decrease by percentage, or increase by amount
- **Update Visibility**
- **Assign Categories** — owner-scoped category assignment

---

## Dashboard Widgets

### Adding Widgets

Widgets are automatically registered with the panel. To customize placement:

```php
// In AdminPanelProvider
use AIArmada\FilamentProducts\Widgets\ProductStatsWidget;

public function panel(Panel $panel): Panel
{
    return $panel
        ->widgets([
            ProductStatsWidget::class,
        ]);
}
```

### Available Widgets

| Widget | Description |
|--------|-------------|
| ProductStatsWidget | Total products, active count, draft count |
| ProductTypeDistributionWidget | Products by type distribution |
| CategoryDistributionChart | Categories with product counts |
| TopSellingProductsWidget | Best-selling products by quantity |

---

## Owner Scoping

All resources automatically scope to the current owner:

```php
// In ProductResource
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->where(function (Builder $query) {
            // Automatically applied by commerce-support
        });
}
```

### Validating Foreign IDs

`AIArmada\CommerceSupport\Support\Filament\OwnerScopedIds::ensureAllowed()`
validates submitted IDs:

```php
// In CreateProduct page
protected function mutateFormDataBeforeCreate(array $data): array
{
    $data['categories'] = OwnerScopedIds::ensureAllowed(
        'categories',
        Category::class,
        $data['categories'] ?? null
    );

    return $data;
}
```

This prevents cross-tenant category assignment attacks.
