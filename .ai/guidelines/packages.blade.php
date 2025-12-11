# Packages Guidelines

- Independence: each package must run standalone; prefer `suggest`/optional deps over `require`.
- Integration: when co-installed, auto-enable hooks via service providers using `class_exists()`/config toggles.
- DTOs: all DTOs must use Laravel Data for consistency.
- Example integration pattern:
```php
public function boot(): void
{
    if (class_exists(Cashier::class)) {
        // Cart-Cashier integration
    }
    if (class_exists(Chip::class)) {
        // Cart-Chip integration
    }
}
```
- Verification: test package alone via `composer require package/<pkg>` and together to confirm auto-features.
