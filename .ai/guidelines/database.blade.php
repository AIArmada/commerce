# Database Guidelines

## Primary Keys
- All tables must use `uuid('id')->primary()` for primary key.

## Foreign Keys
- Use `foreignUuid('relation_id')` for foreign key columns.
- **Do NOT** add `->constrained()`, `->cascadeOnDelete()`, or any DB-level constraints/cascading.
- Application logic must handle referential integrity and cascades.

## Example Migration
```php
Schema::create('orders', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id');
    $table->foreignUuid('cart_id');
    $table->timestamps();
});
```

## Verification
- Review migrations: no `constrained()` or cascade methods on foreign keys.
- Ensure Eloquent relations handle cascades (e.g., `cascadeOnDelete()` in models).
