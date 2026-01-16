---
title: Usage
---

# Usage

## Product Resource

The ProductResource provides a comprehensive interface for managing products.

### Form Structure

The product form is organized into tabs:

1. **Basic Information**
   - Name, SKU, Type, Status, Visibility
   - Short Description, Full Description
   - Featured toggle

2. **Pricing**
   - Price (stored in cents, displayed as currency)
   - Compare Price (original/strike-through price)
   - Cost (for margin calculations)
   - Taxable toggle

3. **Inventory**
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

### Variant Management

For configurable products, use the Variants relation manager:

1. Navigate to a configurable product
2. Click the "Variants" tab
3. Add/edit variants with:
   - SKU override
   - Price override
   - Weight/dimensions overrides
   - Option value assignments

### Bulk Actions

Available bulk actions on the product table:

- **Delete Selected**: Delete multiple products
- **Bulk Edit**: Opens bulk edit modal (if enabled)

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

Use the Products relation manager to:
- View products in category
- Attach/detach products
- Quick-create products

---

## Collection Resource

### Manual Collections

1. Create collection with type "Manual"
2. Use Products relation manager to add products

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

## Import/Export Page

### Exporting Products

1. Navigate to "Import/Export Products"
2. Select export format (CSV)
3. Choose fields to export
4. Click "Export"

### Importing Products

1. Navigate to "Import/Export Products"
2. Upload CSV file
3. Map columns to fields
4. Preview and confirm
5. Click "Import"

**CSV Format Requirements**:
- UTF-8 encoding
- Header row required
- Prices in cents

---

## Bulk Edit Page

### Using Bulk Edit

1. Navigate to "Bulk Edit Products"
2. Filter products to edit
3. Select products
4. Choose field to update
5. Enter new value
6. Click "Apply"

### Editable Fields

- Status
- Visibility
- Price
- Categories
- Is Featured
- Is Taxable

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
| CategoryDistributionWidget | Categories with product counts |
| RecentProductsWidget | Latest created products |

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

The `OwnerScope` helper validates submitted IDs:

```php
// In CreateProduct page
protected function mutateFormDataBeforeCreate(array $data): array
{
    $data['categories'] = OwnerScope::ensureAllowed(
        'categories',
        Category::class,
        $data['categories'] ?? null
    );

    return $data;
}
```

This prevents cross-tenant category assignment attacks.
