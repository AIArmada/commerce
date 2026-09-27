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

Available bulk actions on the product table:

- **Delete Selected**: Delete multiple products
- **Activate** / **Set to Draft**: Update status
- **Update Price**: Adjust prices
- **Change Visibility**: Update visibility

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

There is no Products relation manager on categories. Assign categories from the product form's multi-select instead.

---

## Collection Resource

### Manual Collections

1. Create collection with type "Manual"
2. Attach products in code via `$collection->products()->sync([...])` (there is no Products relation manager)

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

**Supported Fields**: The six named rules only — `price_min`, `price_max`, `type`, `category`, `tag`, `is_featured`.
**Supported Operators**: The form has no operator input; conditions default to `=`.

---

## Attribute Resources

### Creating Attributes

1. Navigate to Attributes
2. Click "New Attribute"
3. Configure:
   - **Code**: Unique identifier (e.g., `material`, `fabric_weight`)
   - **Name**: Display name
   - **Type**: Text, Textarea, Number, Boolean, Select, Multiselect, Date, Color, Media
   - **Options**: For Select/MultiSelect types
   - **Validation**: Required, filterable, visible flags

### Attribute Groups

Organize attributes into logical groups:

1. Create group (e.g., "Specifications", "Dimensions")
2. Assign attributes to group

### Attribute Sets

Combine groups into sets for product types:

1. Create set (e.g., "Apparel", "Electronics")
2. Assign attributes and groups to the set

---

## Import/Export Actions

Import and export live as header actions on the products table, not a separate page.

### Exporting Products

1. Open the products list
2. Click "Export"
3. Choose fields to export (CSV)

### Importing Products

1. Open the products list
2. Click "Import"
3. Upload the CSV file
4. Toggle "Update Existing Products" (match by SKU) and "Skip Errors" as needed
5. Confirm to run the import

**CSV Format Requirements**:
- UTF-8 encoding
- Header row required
- Prices in major units (converted to cents on import)

**Import guards**: files larger than `import.max_rows` are rejected before any row is written. New rows require a name and a numeric price; invalid status, type, visibility, or currency cells are reported as row errors instead of silently defaulting. Updates match by SKU and leave blank cells unchanged. With "Skip Errors" off, the whole import runs in one transaction and rolls back on the first bad row. Exports stream row by row, so large catalogs do not exhaust memory.

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

## Bulk Updates

There is no separate bulk-edit page. Select rows on the products table and choose a bulk action:

- **Activate** / **Set to Draft**: update status
- **Update Price**: adjust prices
- **Change Visibility**: update visibility

Categories, featured, and taxable flags are edited per product.

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
| TopSellingProductsWidget | Latest created products |

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

The `OwnerScopedIds` helper validates submitted IDs:

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
