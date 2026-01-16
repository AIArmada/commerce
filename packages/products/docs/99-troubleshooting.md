---
title: Troubleshooting
---

# Troubleshooting

## Common Issues

### Prices Stored Incorrectly

**Symptom**: Prices appear 100x too high or too low.

**Cause**: Price values should be stored in cents (integer). If you see `2999` displayed as `RM 2999.00` instead of `RM 29.99`, the display logic is not dividing by 100.

**Solution**: Ensure you're using the built-in money helpers:

```php
// Correct - uses Money library
$product->getFormattedPrice();  // "RM 29.99"

// Incorrect - raw value
$product->price;  // 2999 (cents)
```

When creating products, always provide prices in cents:

```php
Product::create([
    'price' => 2999,  // RM 29.99 in cents
]);
```

---

### Variants Not Generating

**Symptom**: `VariantGeneratorService::generate()` returns empty collection.

**Cause**: Options must be attached to the product AND have option values.

**Solution**:

```php
// 1. Create option with values FIRST
$option = Option::create(['name' => 'Size']);
OptionValue::create(['option_id' => $option->id, 'value' => 'S']);
OptionValue::create(['option_id' => $option->id, 'value' => 'M']);

// 2. Attach option to product
$product->options()->attach($option->id);

// 3. Now generate variants
$generator = app(VariantGeneratorService::class);
$variants = $generator->generate($product);
```

---

### Owner Scoping Not Working

**Symptom**: Products from other tenants visible, or no products visible at all.

**Cause**: Missing or misconfigured `OwnerResolverInterface` binding.

**Solution**:

1. Verify the resolver is bound:

```php
// AppServiceProvider.php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;

public function register(): void
{
    $this->app->bind(OwnerResolverInterface::class, function () {
        return new class implements OwnerResolverInterface {
            public function resolve(): ?object
            {
                return auth()->user()?->currentTeam;
            }
        };
    });
}
```

2. Verify owner mode is enabled:

```php
// config/products.php
'owner_mode' => 'enabled',
```

3. Verify products have owner set:

```php
// Owner is set automatically when creating products
// But verify with:
Product::withoutOwnerScope()->whereNull('owner_id')->get();
```

---

### Category Hierarchy Circular Reference

**Symptom**: Application hangs or max recursion error when loading categories.

**Cause**: A category is set as its own parent (directly or indirectly).

**Solution**:

```php
// Find circular references
Category::whereColumn('id', 'parent_id')->get();

// Or validate before saving
public function setParentIdAttribute($value): void
{
    if ($value === $this->id) {
        throw new \InvalidArgumentException('Category cannot be its own parent');
    }
    $this->attributes['parent_id'] = $value;
}
```

---

### Media Conversions Not Generated

**Symptom**: Uploaded images work but conversions (thumb, medium, large) return 404.

**Cause**: Queue not processing or conversions marked as queued.

**Solution**:

1. Check if conversions are queued:

```php
// In Product model, conversions use ->nonQueued()
$this->addMediaConversion('thumb')
    ->fit(Fit::Contain, 150, 150)
    ->nonQueued();  // Runs synchronously
```

2. If using queued conversions, ensure queue worker is running:

```bash
php artisan queue:work
```

3. Regenerate conversions:

```php
$product->getMedia('gallery')->each(function ($media) {
    // Force regenerate
    dispatch(new \Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob($media));
});
```

---

### Automatic Collection Not Returning Products

**Symptom**: `$collection->applyConditions()` returns empty results.

**Cause**: Condition field names or operators don't match actual database columns.

**Solution**:

1. Verify condition structure:

```php
// Correct format
$collection->conditions = [
    [
        'field' => 'is_featured',    // Must match DB column
        'operator' => '=',            // =, !=, >, <, >=, <=, like
        'value' => true,
    ],
];
$collection->save();
```

2. Check supported operators in `Collection::applyConditions()`:

```php
// Currently supports: =, !=, >, <, >=, <=, like
// Other operators are ignored
```

---

### Attribute Values Not Saving

**Symptom**: `setAttributeValue()` doesn't persist the value.

**Cause**: Product must be saved before setting attribute values (needs ID).

**Solution**:

```php
// Wrong - product not saved yet
$product = new Product([...]);
$product->setAttributeValue('material', 'Cotton');  // Fails - no ID

// Correct - product saved first
$product = Product::create([...]);
$product->setAttributeValue('material', 'Cotton');  // Works
```

---

### Migration Fails on JSON Column

**Symptom**: Migration error on older MySQL versions.

**Cause**: MySQL < 5.7 doesn't support native JSON columns.

**Solution**:

```php
// config/products.php
'database' => [
    'json_column_type' => 'text',  // Use TEXT instead of JSON
],
```

---

## Debug Commands

### Check Product Counts by Owner

```php
use AIArmada\Products\Models\Product;

Product::withoutOwnerScope()
    ->selectRaw('owner_type, owner_id, count(*) as count')
    ->groupBy('owner_type', 'owner_id')
    ->get();
```

### Verify Variant Option Combinations

```php
use AIArmada\Products\Models\Product;

$product = Product::with(['variants.optionValues.option'])->find($id);

foreach ($product->variants as $variant) {
    $options = $variant->optionValues->pluck('value', 'option.name');
    dump($variant->sku, $options->toArray());
}
```

### Find Orphaned Variants

```php
use AIArmada\Products\Models\Variant;

Variant::withoutOwnerScope()
    ->whereDoesntHave('product')
    ->get();
```

### Validate Attribute Structure

```php
use AIArmada\Products\Models\AttributeSet;

$set = AttributeSet::with(['groups.groupAttributes'])->find($id);

foreach ($set->groups as $group) {
    dump("Group: {$group->name}");
    foreach ($group->groupAttributes as $attr) {
        dump("  - {$attr->code}: {$attr->type->value}");
    }
}
```
