---
title: Troubleshooting
---

# Troubleshooting

## Common Issues

### Resources Not Appearing in Navigation

**Symptom**: After installing, no product resources appear in the Filament sidebar.

**Causes & Solutions**:

1. **Plugin not registered**:

```php
// app/Providers/Filament/AdminPanelProvider.php
->plugins([
    FilamentProductsPlugin::make(),
])
```

2. **Resources disabled in config**:

```php
// Check config/filament-products.php
'resources' => [
    'product' => [
        'class' => \AIArmada\FilamentProducts\Resources\ProductResource::class,
        // Not null
    ],
],
```

3. **Navigation group mismatch**: Resources might be in a collapsed group. Check the navigation group setting.

---

### Price Display Shows Wrong Value

**Symptom**: Prices appear 100x too high (RM 2999.00 instead of RM 29.99).

**Cause**: The form is not using the format/dehydrate state functions.

**Solution**: This was a bug that has been fixed. Prices are now correctly handled:
- `formatStateUsing` divides by 100 for display
- `dehydrateStateUsing` multiplies by 100 for storage

If you're seeing issues, ensure you're on the latest version.

---

### Categories Not Loading in Product Form

**Symptom**: Category select field is empty or shows spinner indefinitely.

**Causes & Solutions**:

1. **No categories exist**: Create categories first.

2. **Owner scoping issue**: Categories must belong to the same owner:

```php
// Check if categories exist for current owner
Category::forOwner()->count();
```

3. **Relationship query modifier failing**: Check the Select component configuration in ProductResource.

---

### Attribute Group Shows Wrong Count

**Symptom**: Attributes count badge shows 0 even when attributes are assigned.

**Cause**: The relationship name was incorrect (using `attributes` instead of `groupAttributes`).

**Solution**: This has been fixed. The correct relationship is `groupAttributes`:

```php
Tables\Columns\TextColumn::make('group_attributes_count')
    ->counts('groupAttributes')
```

---

### Media Upload Failing

**Symptom**: Clicking upload doesn't work or shows error.

**Causes & Solutions**:

1. **Storage not linked**:

```bash
php artisan storage:link
```

2. **Permissions issue**: Check storage directory permissions.

3. **File size limit**: Check `upload_max_filesize` and `post_max_size` in php.ini.

4. **Spatie MediaLibrary not configured**: Ensure migrations are run:

```bash
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate
```

---

### Import/Export Page Not Found

**Symptom**: 404 error when navigating to import/export.

**Causes & Solutions**:

1. **Feature disabled**:

```php
// config/filament-products.php
'features' => [
    'import_export' => true,  // Must be true
],
```

2. **Page not registered**: Check FilamentProductsPlugin registers the page.

---

### Cross-Tenant Data Visible

**Symptom**: Products or categories from other tenants appear in lists.

**Cause**: Owner scoping not properly configured.

**Solution**:

1. Verify owner resolver is bound:

```php
$this->app->bind(OwnerResolverInterface::class, YourResolver::class);
```

2. Check products have owner set:

```php
Product::withoutOwnerScope()->whereNull('owner_id')->get();
```

3. Verify all resources use `getEloquentQuery()` with proper scoping.

---

### Form Validation Errors Not Showing

**Symptom**: Form fails to save but no error messages appear.

**Causes & Solutions**:

1. **Check browser console**: JavaScript errors may prevent display.

2. **Check Laravel logs**:

```bash
tail -f storage/logs/laravel.log
```

3. **Validation happening in wrong hook**: Ensure validation is in form schema, not just mutate methods.

---

### Bulk Edit Not Applying Changes

**Symptom**: Bulk edit appears to complete but products unchanged.

**Causes & Solutions**:

1. **Mass assignment protection**: Ensure fields are in `$fillable`:

```php
// In Product model
protected $fillable = [
    'status',
    'visibility',
    // ...
];
```

2. **Owner validation rejecting IDs**: Check the OwnerScope helper isn't filtering out all IDs.

---

## Debugging Tips

### Enable Query Logging

```php
// In AppServiceProvider
DB::listen(function ($query) {
    logger($query->sql, $query->bindings);
});
```

### Check Owner Context

```php
// In a controller or tinker
$resolver = app(OwnerResolverInterface::class);
dd($resolver->resolve());
```

### Verify Resource Registration

```php
// Check what resources are registered
$plugin = app(FilamentProductsPlugin::class);
dd($plugin->getResources());
```

### Test Form Data Mutation

```php
// Add logging to CreateProduct
protected function mutateFormDataBeforeCreate(array $data): array
{
    logger('Form data before create', $data);
    
    // ... existing code
    
    logger('Form data after mutation', $data);
    return $data;
}
```

---

## Getting Help

1. Check the products package docs for model-level issues
2. Check Filament v5 documentation for form/table issues
3. Ensure commerce-support is properly configured for multi-tenancy
