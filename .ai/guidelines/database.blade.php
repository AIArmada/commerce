# Database Guidelines

- Primary keys: `uuid('id')->primary()` only.
- Foreign keys: `foreignUuid('relation_id')`; never use `constrained()` or DB-level cascades—handle in application logic.
- Sample:
```php
Schema::create('orders', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id');
    $table->foreignUuid('cart_id');
    $table->timestamps();
});
```
- Verify migrations contain no DB constraints; ensure cascades are implemented in models/services instead.
